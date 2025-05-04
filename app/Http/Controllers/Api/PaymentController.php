<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Events\SeatStatusChange;
use App\Http\Controllers\Controller;
use App\Jobs\ReleaseSeatHoldJob;
use App\Jobs\BroadcastSeatStatusChange;
use App\Jobs\CancelVoucherJob;
use App\Models\Combo;
use App\Models\Membership;
use App\Models\PointHistory;
use App\Models\Rank;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\Ticket_Combo;
use App\Models\Ticket_Seat;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Jobs\SendTicketEmail;
use App\Services\PointService;

class PaymentController extends Controller
{

    function generateOrderCode() {
        $uniquePart = preg_replace('/\D/', '', microtime(true)); // Loại bỏ dấu chấm
        $uniquePart = substr($uniquePart, -8); 
        $randomPart = rand(10000000, 99999999);
    
        return $uniquePart . $randomPart;
    }

    public function getPoints(Request $request)
{
    try {
        $pointService = app(PointService::class);
        $code = $request->code;
        $membership = Membership::where('code', $code)->first();

        if (!$membership) {
            return response()->json(['error' => 'Membership not found'], 404);
        }
        $points = $pointService->getAvailablePoints($membership->id);

        return response()->json([
            'points' => $points
        ]);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function paymentOffline(Request $request)
{
    try {
        $request->validate([
            'seat_id' => 'required|array',
            'seat_id.*' => 'integer|exists:seats,id',
            'combo' => 'nullable|array',
            'combo.*' => 'nullable|integer|min:0|max:10',
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'points' => 'nullable|integer|min:0', 
            'price_combo' => 'nullable|numeric|min:0',
            'price_seat' => 'nullable|numeric|min:0',
            'combo_discount' => 'nullable|numeric|min:0',
            'point_discount' => 'nullable|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'total_price_before_discount' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
        ]);

        $showtime = Showtime::findOrFail($request->showtime_id);
        $seatIds = $request->seat_id;
        $orderCode = $this->generateOrderCode();
        $totalPayment = $request->total_price;

        // Lấy user_id từ code hoặc auth
        $code = $request->code;
        if ($code) {
            $userId = Membership::where('code', $code)->value('user_id');
            if (!$userId) {
                return response()->json(['message' => 'Code không hợp lệ'], 404);
            }
        } else {
            $userId = auth()->id();
        }

        $pointService = app(PointService::class);
        $pointUsed = $request->points ?? 0;
        $rank = null;

        // Kiểm tra membership và điểm
        if ($code) {
            $membership = Membership::where('code', $code)->first();
            if (!$membership) {
                return response()->json(['message' => 'Mã thành viên không hợp lệ.'], 404);
            }

            $rank = $membership->rank;
            $availablePoints = $pointService->getAvailablePoints($membership->id);
            if ($availablePoints < $pointUsed) {
                return response()->json(['message' => "Không đủ điểm. Bạn có $availablePoints điểm, cần $pointUsed điểm."], 400);
            }
        }

        // Kiểm tra trạng thái ghế
        $seatShowtimes = DB::table('seat_showtimes')
            ->whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtime->id)
            ->get();

        foreach ($seatShowtimes as $seat) {
            if ($seat->status != 'hold') {
                return response()->json(['message' => 'Một hoặc nhiều ghế không hợp lệ.'], 400);
            }
        }

        // Tạo ticket
        $ticket = Ticket::create([
            'user_id' => $userId,
            'cinema_id' => $showtime->cinema_id,
            'room_id' => $showtime->room_id,
            'movie_id' => $showtime->movie_id,
            'showtime_id' => $showtime->id,
            'voucher_code' => $request->voucher_id ?? null,
            'voucher_discount' => $request->voucher_discount ?? 0,
            'payment_name' => 'Tiền mặt',
            'code' => $orderCode,
            'total_price' => $totalPayment,
            'status' => 'Đã thanh toán',
            'staff' => auth()->id() ?? null,
            'expiry' => $showtime->end_time,
            'point' => $code ? floor($totalPayment * 0.03) : 0,
            'point_discount' => $pointUsed,
            'rank_at_booking' => $rank?->name ?? "none",
        ]);

        // Xử lý điểm
        if ($code && $membership && $pointUsed > 0) {
            $pointService->usePoints($membership->id, $pointUsed, $ticket->id);
        }
        if ($code && $membership && $totalPayment > 0) {
            $pointsToAdd = floor($totalPayment * 0.03);
            $pointService->earnPoints($membership->id, $pointsToAdd, $ticket->id);
        }
        $membership->increment('total_spent', $ticket['total_price']);
                        $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                            ->orderBy('total_spent', 'desc')
                            ->first() ?? Rank::orderBy('total_spent', 'asc')->first();
    
                        if ($rank) {
                            $membership->rank_id = $rank->id;
                            $membership->save();
                        }

        foreach ($request->seat_id as $seatId) {
            Ticket_Seat::create([
                'ticket_id' => $ticket->id,
                'seat_id' => $seatId,
                'price' => DB::table('seat_showtimes')->where('seat_id', $seatId)->value('price'),
            ]);
        }

        if (!empty($request->combo)) {
            foreach ($request->combo as $comboId => $quantity) {
                Ticket_Combo::create([
                    'ticket_id' => $ticket->id,
                    'combo_id' => $comboId,
                    'quantity' => $quantity,
                    'price' => Combo::find($comboId)->discount_price * $quantity,
                ]);
            }
        }

        // Cập nhật trạng thái ghế thành booked
        DB::table('seat_showtimes')
            ->whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtime->id)
            ->update([
                'status' => 'booked',
                'user_id' => $userId,
                'updated_at' => now(),
            ]);

        return response()->json([
            'mess' => "Đặt vé thành công",
            "code" => $orderCode
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (Exception $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}

    public function payment(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'rank_at_booking' => 'required|string|exists:ranks,name', // Hạng thành viên tại thời điểm đặt vé phải tồn tại trong bảng ranks
            'seat_id' => 'required|array',
            'seat_id.*' => 'integer|exists:seats,id',
            'combo' => 'nullable|array',
            'combo.*' => 'nullable|integer|min:0|max:10',
            'voucher_id' => 'nullable|integer|exists:vouchers,id',
            'voucher_code' => 'nullable|string|exists:vouchers,code',
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'payment_name' => 'required|string|in:VNPAY,ZALOPAY,MOMO',
            'points' => 'nullable|integer|min:0', 
            'price_combo' => 'nullable|numeric|min:0',
            'price_seat' => 'nullable|numeric|min:0',
            'combo_discount' => 'nullable|numeric|min:0',
            'voucher_discount' => 'nullable|numeric|min:0',
            'point_discount' => 'nullable|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'total_price_before_discount' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
        ]);

        $userId = auth()->id();
        $showtime = Showtime::findOrFail($request->showtime_id);
        $seatIds = $request->seat_id;

        $priceCombo = $request->price_combo; // Tiền tổng combo
        $priceSeat = $request->price_seat; //Tiền tổng ghế
        $comboDiscount = $request->combo_discount ?? 0; // Giảm giá combo
        $voucherDiscount = $request->voucher_discount ?? 0; // Giảm giá voucher
        $pointDiscount = $request->point_discount ?? 0; // Giảm giá điểm
        $totalDiscount = $request->total_discount ?? 0; // Tổng tiền giảm
        $totalPriceBeforeDiscount = $request->total_price_before_discount; // Tổng tiền chưa giảm
        $totalPayment = $request->total_price; // Tổng tiền thanh toán (đã giảm)

        
        // Lấy rank của người dùng từ bảng Membership
        $pointService = app(PointService::class);
        $membership = Membership::where('user_id', $userId)->first();
        // Kiểm tra số điểm đã sử dụng
        $pointUsed = $request->points ?? 0;
        $availablePoints = $pointService->getAvailablePoints($membership->id);
        if ($availablePoints < $pointUsed) {
            throw new Exception("Không đủ điểm để sử dụng: Cần $pointUsed điểm, nhưng chỉ có $availablePoints điểm hợp lệ.");
        }
        $pointDiscount = $pointUsed; // 1 điểm = 1 VND
        $rank = $membership ? $membership->rank : null;

        // Tiền giảm giá tổng tất cả theo rank (ticket_percentage)
        $ticketDiscount = 0;
        if ($rank) {
            $ticketDiscount = $totalPriceBeforeDiscount * ($rank->ticket_percentage / 100); // Giảm giá vé
        }


        // Kiểm tra trạng thái ghế
        $seatShowtimes = DB::table('seat_showtimes')
            ->whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtime->id)
            ->get();
        $priceSeat = $seatShowtimes->sum('price');

        foreach ($seatShowtimes as $seat) {
            if ($seat->hold_expires_at < now() || $seat->user_id != $userId || $seat->status != 'hold') {
                return response()->json(['error' => 'Một hoặc nhiều ghế không hợp lệ.'], 400);
            }
        }

        // Xác định thời gian giữ ghế theo phương thức thanh toán
        $holdTime = now();
        if ($request->payment_name == 'VNPAY' || $request->payment_name == 'ZALOPAY') {
            $holdTime = now()->addMinutes(15); // Giữ ghế 15 phút cho VNPAY và ZALOPAY
        } elseif ($request->payment_name == 'MOMO') {
            $holdTime = now()->addMinutes(10); // Giữ ghế 10 phút cho MOMO
        } else {
            $holdTime = now()->addMinutes(15); // Mặc định là 15 phút
        }

        // Cập nhật trạng thái ghế và thời gian giữ ghế cho tất cả ghế trong yêu cầu thanh toán
        DB::table('seat_showtimes')
            ->whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtime->id)
            ->update([
                'status' => 'hold',
                'hold_expires_at' => $holdTime, // Cập nhật thời gian giữ ghế
                'user_id' => $userId,
            ]);

        //  Thêm job để tự động giải phóng ghế sau 15 phút nếu chưa thanh toán
        foreach ($seatIds as $seatId) {
            ReleaseSeatHoldJob::dispatch($seatId, $showtime->id)->delay(now()->addMinutes(15));
        }

        // Nhận các giá trị
       
        // Kiểm tra ngày đặc biệt và giảm giá combo nếu là Thứ 7, Chủ Nhật, 2/9,...
        $comboDiscount = 0;
        $currentDate = now();

        // Kiểm tra nếu là Thứ 7, Chủ Nhật hoặc 2/9
        if ($currentDate->isWeekend() || $currentDate->is('2025-09-02')) {
            if ($rank && isset($rank->combo_percentage)) {
                $comboDiscount = $priceCombo * ($rank->combo_percentage / 100); // Áp dụng giảm giá combo theo rank
            }
        }

        // Log vào cache
        $orderCode = $this->generateOrderCode();
        Cache::put("payment_{$orderCode}", [
            'user_id' => $userId,
            'cinema_id' => $showtime->cinema_id,
            'room_id' => $showtime->room_id,
            'movie_id' => $showtime->movie_id,
            'showtime_id' => $showtime->id,
            'voucher_id' => $request->voucher_id ?? null,
            'voucher_code' => $request->voucher_code ?? null,
            'voucher_discount' => $voucherDiscount,
            'point_discount' => $pointDiscount, // Điểm đã sử dụng trong giao dịch này
            'point' => $paymentData['point_discount'] ?? 0,  // Lưu điểm tích lũy khi mua vé vào bảng ticket
            'rank_at_booking' => $request->rank_at_booking, // Lưu hạng thành viên tại thời điểm đặt vé
            'combo_discount' => $comboDiscount,
            'ticket_discount' => $ticketDiscount,
            'total_discount' => $totalDiscount,
            'total_price_before_discount' => $totalPriceBeforeDiscount,
            'seat_amount' => $priceSeat,
            'combo_amount' => $priceCombo,
            'total_price' => $totalPayment,
            'payment_name' => $request->payment_name,
            'code' => $orderCode,
            'expiry' => $showtime->end_time,
            'seats' => $seatIds,
            'combos' => $request->combo ?? [],
        ], now()->addMinutes(60));

        Log::info("Lưu paymentData vào cache:", ["key" => "payment_{$orderCode}", "data" => Cache::get("payment_{$orderCode}")]);

        // Hủy voucher sau thời gian tương ứng nếu có voucher_id
        if ($request->voucher_id) {
            $cancelTime = $request->payment_name == 'MOMO' ? now()->addMinutes(10) : now()->addMinutes(15);
            CancelVoucherJob::dispatch($userId, $request->voucher_id, $orderCode)->delay($cancelTime);
        }


        // Chuyển hướng đến phương thức thanh toán
        if ($request->payment_name == 'VNPAY') {
            return $this->vnPayPayment($orderCode);
        } elseif ($request->payment_name == 'ZALOPAY') {
            return $this->zalopayPayment($orderCode);
        } else if ($request->payment_name == 'MOMO') {
            return $this->MomoPayment($orderCode);
        } else {
            return response()->json(['error' => 'Phương thức thanh toán không được hỗ trợ'], 400);
        }
    }

    public function vnPayPayment($orderCode)
    {
        $paymentData = Cache::get("payment_{$orderCode}");

        if (!$paymentData) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng hoặc dữ liệu không hợp lệ.'], 400);
        }

        // Cấu hình VNPAY
        $vnp_TmnCode = "5TZE79MF";
        $vnp_HashSecret = "47HZGTHKZKUYF1EMWFKE392S8ZHVZGRO";
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = route('vnpay.return');

        // Tạo mã giao dịch
        $vnp_TxnRef = $paymentData['code'];
        $vnp_Amount = $paymentData['total_price'] * 100;
        $vnp_OrderInfo = "Thanh toán vé xem phim";
        $vnp_OrderType = "billpayment";
        $vnp_Locale = "vn";
        $vnp_IpAddr = request()->ip();
        $vnp_CreateDate = date('YmdHis');

        // Tạo danh sách tham số gửi đi
        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => $vnp_CreateDate,
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        Cache::put("vnp_CreateDate_{$orderCode}", [
            'vnp_CreateDate' => $vnp_CreateDate,
        ],now()->addMinutes(16));


        ksort($inputData);
        $query = "";
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $paymentUrl = $vnp_Url . "?" . $query . "vnp_SecureHash=" . $vnp_SecureHash;


        return response()->json([
            'status' => 'success',
            'payment_url' => $paymentUrl,
            'orderCode'  => $paymentData['code'],
            'paymentName' => $paymentData['payment_name']
        ]);
    }

    public function returnVnpay(Request $request)
    {
        $vnp_HashSecret = "47HZGTHKZKUYF1EMWFKE392S8ZHVZGRO";
    
        $inputData = $request->all();
        $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? null;
    
        if (!$vnp_TxnRef) {
            return response()->json(['error' => 'Thiếu mã đơn hàng!'], 400);
        }
    
        $paymentData = Cache::get("payment_{$vnp_TxnRef}");
    
        // Nếu thanh toán thất bại
        if ($inputData['vnp_ResponseCode'] != '00') {
    
            if (!empty($paymentData['voucher_id'])) {
                CancelVoucherJob::dispatchSync($paymentData['user_id'], $paymentData['voucher_id'], $paymentData['code']);
            }
    
            DB::table('seat_showtimes')
                ->whereIn('seat_id', $paymentData['seats'])
                ->where('showtime_id', $paymentData['showtime_id'])
                ->update([
                    'status' => 'available',
                    'user_id' => null,
                    'hold_expires_at' => null,
                ]);
    
                return redirect(env('FRONTEND_URL'));
        }
    
        // Nếu thanh toán thành công
        if ($inputData['vnp_ResponseCode'] == '00') {
            try {
                $pointUsed = $paymentData['point_discount'] ?? 0;
                $membership = Membership::where('user_id', $paymentData['user_id'])->first();
    
                // Kiểm tra điểm trước khi thanh toán
                if ($membership && $pointUsed > 0) {
                    $pointService = app(PointService::class);
                    $availablePoints = $pointService->getAvailablePoints($membership->id);
    
                    if ($availablePoints < $pointUsed) {
                        throw new Exception("Không đủ điểm để sử dụng: Cần $pointUsed điểm, nhưng chỉ có $availablePoints điểm hợp lệ.");
                    }
                }
    
                // Tiến hành tạo vé nếu hợp lệ
                DB::transaction(function () use ($paymentData) {
                    $ticket = Ticket::create([
                        'user_id' => $paymentData['user_id'],
                        'cinema_id' => $paymentData['cinema_id'],
                        'room_id' => $paymentData['room_id'],
                        'movie_id' => $paymentData['movie_id'],
                        'showtime_id' => $paymentData['showtime_id'],
                        'voucher_id' => $paymentData['voucher_id'],
                        'voucher_code' => $paymentData['voucher_code'],
                        'voucher_discount' => $paymentData['voucher_discount'],
                        'point_discount' => $paymentData['point_discount'] ?? 0,
                        'point' => 0,
                        'rank_at_booking' => $paymentData['rank_at_booking'] ?? null,
                        'payment_name' => $paymentData['payment_name'],
                        'code' => $paymentData['code'],
                        'total_price' => $paymentData['total_price'],
                        'status' => 'Đã thanh toán',
                        'expiry' => $paymentData['expiry'],
                        'combo_discount' => $paymentData['combo_discount'] ?? 0,
                        'ticket_discount' => $paymentData['ticket_discount'] ?? 0,
                        'total_discount' => $paymentData['total_discount'] ?? 0,
                        'total_price_before_discount' => $paymentData['total_price_before_discount'] ?? 0,
                        'seat_amount' => $paymentData['price_seat'] ?? 0,
                        'combo_amount' => $paymentData['price_combo'] ?? 0,
                    ]);
    
                    SendTicketEmail::dispatch($ticket, $paymentData)->onQueue('emails');
    
                    foreach ($paymentData['seats'] as $seatId) {
                        Ticket_Seat::create([
                            'ticket_id' => $ticket->id,
                            'seat_id' => $seatId,
                            'price' => DB::table('seat_showtimes')->where('seat_id', $seatId)->value('price'),
                        ]);
                    }
    
                    if (!empty($paymentData['combos'])) {
                        foreach ($paymentData['combos'] as $comboId => $quantity) {
                            Ticket_Combo::create([
                                'ticket_id' => $ticket->id,
                                'combo_id' => $comboId,
                                'quantity' => $quantity,
                                'price' => Combo::find($comboId)->discount_price * $quantity,
                            ]);
                        }
                    }
    
                    if (!empty($paymentData['voucher_id'])) {
                        UserVoucher::where('user_id', $paymentData['user_id'])
                            ->where('voucher_id', $paymentData['voucher_id'])
                            ->whereNull('ticket_id')
                            ->orderBy('id', 'desc')
                            ->update(['ticket_id' => $ticket->id]);
                    }
    
                    Cache::forget("cancel_voucher_{$paymentData['code']}");
    
                    DB::table('seat_showtimes')
                        ->whereIn('seat_id', $paymentData['seats'])
                        ->where('showtime_id', $paymentData['showtime_id'])
                        ->update([
                            'status' => 'booked',
                            'user_id' => $paymentData['user_id'],
                            'updated_at' => now()
                        ]);
    
                    foreach ($paymentData['seats'] as $seatId) {
                        Cache::forget("seat_hold_{$seatId}_{$paymentData['showtime_id']}");
                    }
    
                    // Cập nhật điểm
                    $pointService = app(PointService::class);
                    $membership = Membership::where('user_id', $ticket->user_id)->first();
    
                    if ($membership) {
                        $pointUsed = $paymentData['point_discount'] ?? 0;
                        if ($pointUsed > 0) {
                            $pointService->usePoints($membership->id, $pointUsed, $ticket->id);
                        }
    
                        $pointsEarned = floor($paymentData['total_price'] * 0.03);
                        if ($pointsEarned > 0) {
                            $pointService->earnPoints($membership->id, $pointsEarned, $ticket->id);
                            $ticket->point = $pointsEarned;
                            $ticket->save();
                        }
    
                        $membership->increment('total_spent', $paymentData['total_price']);
                        $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                            ->orderBy('total_spent', 'desc')
                            ->first() ?? Rank::orderBy('total_spent', 'asc')->first();
    
                        if ($rank) {
                            $membership->rank_id = $rank->id;
                            $membership->save();
                        }
                    }
                });
    
                return redirect(env('FRONTEND_URL' , 'http://localhost:3000') . "/thanks/{$paymentData['code']}?status=success");
    
            } catch (Exception $e) {
                return response()->json([
                    "return_code" => 0,
                    "return_message" => $e->getMessage()
                ]);
            }
        }
    }
    
    public function zalopayPayment($orderCode)
    {
        // Lấy dữ liệu đơn hàng từ cache
        $paymentData = Cache::get("payment_{$orderCode}");

        if (!$paymentData) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng.'], 400);
        }

        // Cấu hình ZaloPay
        $app_id = env('ZALOPAY_APP_ID');
        $key1 = env('ZALOPAY_KEY1');
        $endpoint = env('ZALOPAY_ENDPOINT');
        $callback_url = env('ZALOPAY_CALLBACK_URL');

        Log::info("Cấu hình ZaloPay:", [
            "app_id" => $app_id,
            "key1" => $key1,
            "endpoint" => $endpoint,
            "callback_url" => $callback_url
        ]);

        if (!$app_id || !$key1 || !$endpoint || !$callback_url) {
            return response()->json(["error" => "Thiếu cấu hình ZaloPay"], 400);
        }

        $apptime = round(microtime(true) * 1000);
        $zalopayTransId = date("ymd") . "_" . $orderCode; // Mã gửi lên ZaloPay
        $zalopayOrderCode = $orderCode; // Mã 16 số dùng trong hệ thống nội bộ

        // Lưu vào cache **với cả 2 mã**
        Cache::put("payment_{$zalopayTransId}", array_merge($paymentData, [
            'zalopay_trans_id' => $zalopayTransId, // Mã gửi lên ZaloPay
            'code' => $zalopayOrderCode // Mã 16 số dùng trong nội bộ
        ]), now()->addMinutes(60));
       
        // Embed data (tùy chỉnh)
        $embeddata = [
            "merchantinfo" => "embeddata123",
            //"redirecturl" => "http://localhost:5173/thanks/{$paymentData['code']}?status=success"
        ];

        // Danh sách sản phẩm
        $items = [
            [
                "itemid" => "ticket",
                "itemname" => "Vé xem phim",
                "itemprice" => $paymentData['total_price'],
                "itemquantity" => 1
            ]
        ];
       
        // Tạo mảng dữ liệu gửi đi
        $order = [
            "app_id" => $app_id,
            "app_time" => $apptime,
            "app_trans_id" => $zalopayTransId,
            "app_user" => "user_demo",
            "amount" => (int) $paymentData['total_price'],
            "description" => "Thanh toán vé xem phim - Đơn hàng #{$zalopayOrderCode}",
            "callback_url" => $callback_url,
            "embed_data" => json_encode([
                "merchantinfo" => "embeddata123",
                "redirecturl" => route('handleZaloPayRedirect', ['orderCode' => $zalopayOrderCode])
            ], JSON_UNESCAPED_UNICODE),
            "item" => json_encode([
                ["itemid" => "ticket", "itemname" => "Vé xem phim", "itemprice" => (int) $paymentData['total_price'], "itemquantity" => 1]
            ], JSON_UNESCAPED_UNICODE)
        ];

        // Tạo chữ ký MAC
        $data_string = implode("|", [
            $order["app_id"],
            $order["app_trans_id"],
            $order["app_user"],
            $order["amount"],
            $order["app_time"],
            $order["embed_data"],
            $order["item"]
        ]);
        $order["mac"] = hash_hmac("sha256", $data_string, $key1);

        // Gửi request đến ZaloPay
        $response = Http::asForm()->post($endpoint, $order);
        $responseData = $response->json();

        if ($response->failed() || $responseData["return_code"] != 1) {
            return response()->json(["error" => "Lỗi khi gọi API ZaloPay", "details" => $responseData], 500);
        }

        return response()->json(["status" => "success", "payment_url" => $responseData["order_url"] , "orderCode" => $zalopayTransId , "paymentName" => $paymentData['payment_name'] ]);
    }

   

    public function zalopayCallback(Request $request)
    {
        try {
            $key2 = env('ZALOPAY_KEY2');
            $postdata = $request->getContent();
            $postdatajson = json_decode($postdata, true);
    
    
            $mac = hash_hmac("sha256", $postdatajson["data"], $key2);
            if (strcmp($mac, $postdatajson["mac"]) !== 0) {
                return response()->json(["return_code" => -1, "return_message" => "mac not equal"]);
            }
    
            $datajson = json_decode($postdatajson["data"], true);
            $zalopayTransId = $datajson["app_trans_id"];
            $orderCode = str_replace(date("ymd") . "_", "", $zalopayTransId);
    
            $paymentData = Cache::get("payment_{$zalopayTransId}");
    
            if (!$paymentData) {
                return response()->json(['error' => 'Không tìm thấy đơn hàng.'], 400);
            }
    
    
            $existingTicket = Ticket::where('code', $orderCode)->first();
            if ($existingTicket) {
                return response()->json(["return_code" => 1, "return_message" => "success"]);
            }
    
            //  Kiểm tra điểm trước khi thực hiện DB transaction
            $pointUsed = $paymentData['point_discount'] ?? 0;
            $membership = Membership::where('user_id', $paymentData['user_id'])->first();
            if ($membership && $pointUsed > 0) {
                $pointService = app(PointService::class);
                $availablePoints = $pointService->getAvailablePoints($membership->id);
                if ($availablePoints < $pointUsed) {
                    return response()->json([
                        "return_code" => 0,
                        "return_message" => "Không đủ điểm để thực hiện giao dịch."
                    ]);
                }
            }
    
            DB::transaction(function () use ($paymentData, $orderCode, $pointUsed, $membership) {
                $ticket = Ticket::create([
                    'user_id' => $paymentData['user_id'],
                    'cinema_id' => $paymentData['cinema_id'],
                    'room_id' => $paymentData['room_id'],
                    'movie_id' => $paymentData['movie_id'],
                    'showtime_id' => $paymentData['showtime_id'],
                    'voucher_id' => $paymentData['voucher_id'],
                    'voucher_code' => $paymentData['voucher_code'],
                    'voucher_discount' => $paymentData['voucher_discount'],
                    'point_discount' => $pointUsed,
                    'point' => 0,
                    'rank_at_booking' => $paymentData['rank_at_booking'] ?? null,
                    'payment_name' => 'ZALOPAY',
                    'code' => $orderCode,
                    'total_price' => $paymentData['total_price'],
                    'status' => 'Đã thanh toán',
                    'expiry' => $paymentData['expiry'],
                    'combo_discount' => $paymentData['combo_discount'] ?? 0,
                    'ticket_discount' => $paymentData['ticket_discount'] ?? 0,
                    'total_discount' => $paymentData['total_discount'] ?? 0,
                    'total_price_before_discount' => $paymentData['total_price_before_discount'] ?? 0,
                    'seat_amount' => $paymentData['seat_amount'] ?? 0,
                    'combo_amount' => $paymentData['combo_amount'] ?? 0,
                ]);
    
                SendTicketEmail::dispatch($ticket, $paymentData)->onQueue('emails');
    
                if (!empty($paymentData['voucher_id'])) {
                    UserVoucher::where('user_id', $paymentData['user_id'])
                        ->where('voucher_id', $paymentData['voucher_id'])
                        ->whereNull('ticket_id')
                        ->orderBy('id', 'desc')
                        ->update(['ticket_id' => $ticket->id]);
                }
    
                Cache::forget("cancel_voucher_{$paymentData['code']}");
    
                foreach ($paymentData['seats'] as $seatId) {
                    Ticket_Seat::create([
                        'ticket_id' => $ticket->id,
                        'seat_id' => $seatId,
                        'price' => DB::table('seat_showtimes')->where('seat_id', $seatId)->value('price'),
                    ]);
                }
    
                if (!empty($paymentData['combos'])) {
                    foreach ($paymentData['combos'] as $comboId => $quantity) {
                        Ticket_Combo::create([
                            'ticket_id' => $ticket->id,
                            'combo_id' => $comboId,
                            'quantity' => $quantity,
                            'price' => Combo::find($comboId)->discount_price * $quantity,
                        ]);
                    }
                }
    
                DB::table('seat_showtimes')
                    ->whereIn('seat_id', $paymentData['seats'])
                    ->where('showtime_id', $paymentData['showtime_id'])
                    ->update([
                        'status' => 'booked',
                        'user_id' => $paymentData['user_id'],
                        'updated_at' => now()
                    ]);
    
                foreach ($paymentData['seats'] as $seatId) {
                    Cache::forget("seat_hold_{$seatId}_{$paymentData['showtime_id']}");
                }
    
                $pointService = app(PointService::class);
    
                if ($membership) {
                    if ($pointUsed > 0) {
                        $pointService->usePoints($membership->id, $pointUsed, $ticket->id);
                    }
    
                    $pointsEarned = floor($paymentData['total_price'] * 0.03);
                    if ($pointsEarned > 0) {
                        $pointService->earnPoints($membership->id, $pointsEarned, $ticket->id);
                        $ticket->point = $pointsEarned;
                        $ticket->save();
                    }
    
                    $membership->increment('total_spent', $paymentData['total_price']);
    
                    $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                        ->orderBy('total_spent', 'desc')
                        ->first() ?? Rank::orderBy('total_spent', 'asc')->first();
    
                    if ($rank) {
                        $membership->rank_id = $rank->id;
                        $membership->save();
                    }
                }
    
            });
    
            return response()->json(["return_code" => 1, "return_message" => "success"]);
        } catch (Exception $e) {
            return response()->json(["return_code" => 0, "return_message" => $e->getMessage()]);
        }
    }
    

    public function handleZaloPayRedirect(Request $request)
    {
        Log::info("Thanh toán ZaloPay: ", $request->all());
        $status = $request->input('status'); // Lấy trạng thái thanh toán từ params
        $orderCode = $request->input('apptransid'); // Lấy mã đơn hàng từ params
      
        // Kiểm tra nếu thanh toán thành công
        if ($status == 1) {
            return redirect(env('FRONTEND_URL') . "/thanks/{$orderCode}?status=success");
        }

        // Nếu thanh toán thất bại, giải phóng ghế
        $paymentData = Cache::get("payment_{$orderCode}");

        if ($paymentData) {
            // Giải phóng ghế
            DB::table('seat_showtimes')
                ->whereIn('seat_id', $paymentData['seats'])
                ->where('showtime_id', $paymentData['showtime_id'])
                ->update([
                    'status' => 'available',
                    'user_id' => null,
                    'hold_expires_at' => null,
                ]);

            // Xóa cache đơn hàng
            Cache::forget("payment_{$orderCode}");

            // Hủy ngay lập tức voucher nếu có
            if (!empty($paymentData['voucher_id'])) {
                CancelVoucherJob::dispatchSync($paymentData['user_id'], $paymentData['voucher_id'], $paymentData['code']);
            }
        }

        return redirect(env('FRONTEND_URL'));
    }

    // ====================END THANH TOÁN ZALOPAY==================== //

    public function checkVnpayStatus($orderCode, $transactionDate = null)
    {
        try {
            if (empty($orderCode)) {
                return response()->json(['status' => 'error', 'message' => 'Order code is required'], 400);
            }
    
            // Kiểm tra cache
            $CreateDate_Cache = Cache::get("vnp_CreateDate_{$orderCode}");
            if (empty($CreateDate_Cache) || !isset($CreateDate_Cache['vnp_CreateDate'])) {
                return response()->json(['status' => 'error', 'message' => 'Transaction date not found in cache'], 400);
            }
    
            // Lấy cấu hình VNPay
            $vnp_TmnCode = env('VNPAY_TMN_CODE');
            $vnp_HashSecret = env('VNPAY_HASH_SECRET');
            $vnp_ApiUrl = env('VNPAY_API_URL_STATUS');
    
            if (empty($vnp_TmnCode) || empty($vnp_HashSecret) || empty($vnp_ApiUrl)) {
                return response()->json(['status' => 'error', 'message' => 'VNPay configuration missing'], 500);
            }
    
            // Chuẩn bị dữ liệu gửi đi
            $vnp_RequestId = uniqid();
            $vnp_Version = '2.1.0';
            $vnp_Command = 'querydr';
            $vnp_OrderInfo = 'Truy van ket qua thanh toan';
            $vnp_TransactionDate = $CreateDate_Cache['vnp_CreateDate'];
            $vnp_CreateDate = date('YmdHis');
            $vnp_IpAddr = request()->ip();
    
            // Kiểm tra định dạng vnp_TransactionDate
            if (!preg_match('/^\d{14}$/', $vnp_TransactionDate)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid transaction date format'], 400);
            }
    
            $inputData = [
                'vnp_RequestId' => $vnp_RequestId,
                'vnp_Version' => $vnp_Version,
                'vnp_Command' => $vnp_Command,
                'vnp_TmnCode' => $vnp_TmnCode,
                'vnp_TxnRef' => $orderCode,
                'vnp_OrderInfo' => $vnp_OrderInfo,
                'vnp_TransactionDate' => $vnp_TransactionDate,
                'vnp_CreateDate' => $vnp_CreateDate,
                'vnp_IpAddr' => $vnp_IpAddr,
            ];
    
            // Tạo chữ ký theo tài liệu VNPay
            $data = $inputData['vnp_RequestId'] . '|' . 
                    $inputData['vnp_Version'] . '|' . 
                    $inputData['vnp_Command'] . '|' . 
                    $inputData['vnp_TmnCode'] . '|' . 
                    $inputData['vnp_TxnRef'] . '|' . 
                    $inputData['vnp_TransactionDate'] . '|' . 
                    $inputData['vnp_CreateDate'] . '|' . 
                    $inputData['vnp_IpAddr'] . '|' . 
                    $inputData['vnp_OrderInfo'];
            $vnp_SecureHash = hash_hmac('sha512', $data, $vnp_HashSecret);
            $inputData['vnp_SecureHash'] = $vnp_SecureHash;
    
            \Log::debug('VNPay checkPaymentStatus request', [
                'orderCode' => $orderCode,
                'requestId' => $vnp_RequestId,
                'inputData' => $inputData,
                'hashdata' => $data,
                'secure_hash' => $vnp_SecureHash,
            ]);
    
            // Gửi yêu cầu tới VNPay với Content-Type: application/json
            $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($vnp_ApiUrl, $inputData);
    
            \Log::info('VNPay checkPaymentStatus response', [
                'orderCode' => $orderCode,
                'http_status' => $response->status(),
                'response_body' => $response->body(),
            ]);
    
            if ($response->successful()) {
                $responseData = $response->json();
            
                if (empty($responseData)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Empty or invalid JSON response from VNPay'
                    ], 500);
                }
            
                if (empty($responseData['vnp_TransactionStatus'])) {
                    \Log::error('VNPay response missing vnp_TransactionStatus', [
                        'response' => $responseData
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Missing transaction status in VNPay response'
                    ], 500);
                }
            
                // Map trạng thái
                $statusMap = [
                    '00' => 'paid',
                    '01' => 'failed',
                    '02' => 'failed',
                    '04' => 'cancelled',
                    '05' => 'expired',
                    '06' => 'refunded',
                    '07' => 'pending_hold',
                    '09' => 'processing',
                    '91' => 'not_found',
                    '94' => 'processing',
                ];
            
                $transactionStatus = $responseData['vnp_TransactionStatus'];
                $status = $statusMap[$transactionStatus] ?? 'unknown';
            
                return response()->json([
                    'status' => $status,
                    'data' => $responseData
                ]);
            }
            
            // Nếu response không thành công
            $errorBody = $response->body();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['vnp_Message'] ?? 'Unknown VNPay error';
            
            return response()->json([
                'status' => 'error',
                'message' => 'VNPay request failed',
                'error' => $errorMessage,
                'http_status' => $response->status(),
                'response_body' => $errorData ?? $errorBody, // Cho nó log luôn raw body cho dễ debug
            ], 500);            
    
        } catch (\Exception $e) {
            \Log::error('VNPay checkPaymentStatus exception', [
                'orderCode' => $orderCode,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkZaloPayStatus($appTransId)
    {
        try {
            if (empty($appTransId)) {
                return response()->json(['status' => 'error', 'message' => 'App transaction ID is required'], 400);
            }
    
            // Config
            $appId = env('ZALOPAY_APP_ID');
            $key1 = env('ZALOPAY_KEY1');
            $apiUrl = env('ZALOPAY_API_URL', 'https://sandbox.zalopay.com.vn/v001/tpe/getstatusbyapptransid');
    
            if (empty($appId) || empty($key1)) {
                return response()->json(['status' => 'error', 'message' => 'ZaloPay configuration missing'], 500);
            }
    
            // Create MAC (chuẩn doc)
            $data = $appId . '|' . $appTransId . '|' . $key1;
            $mac = hash_hmac('sha256', $data, $key1);
    
            // Input data
            $inputData = [
                'appid' => $appId,
                'apptransid' => $appTransId,
                'mac' => $mac,
            ];
    
            \Log::debug('ZaloPay checkStatus request', [
                'apptransid' => $appTransId,
                'inputData' => $inputData,
            ]);
    
            // Gửi request
            $response = Http::asForm()->post($apiUrl, $inputData);
    
            \Log::info('ZaloPay checkStatus response', [
                'apptransid' => $appTransId,
                'http_status' => $response->status(),
                'response_body' => $response->body(),
            ]);
    
            if ($response->successful()) {
                $responseData = $response->json();
                if (empty($responseData)) {
                    return response()->json(['status' => 'error', 'message' => 'Empty or invalid JSON response from ZaloPay'], 500);
                }
    
                if (!isset($responseData['returncode'])) {
                    \Log::error('ZaloPay response missing returncode', ['response' => $responseData]);
                    return response()->json(['status' => 'error', 'message' => 'Missing return code in ZaloPay response'], 500);
                }
    
                // Status handling
                $returnCode = $responseData['returncode'];
                $returnMessage = $responseData['returnmessage'] ?? '';
    
                $isProcessing = $responseData['isprocessing'] ?? false;
                $status = match (true) {
                    $returnCode == 1 => ($isProcessing ? 'processing' : 'paid'),
                    default => 'failed',
                };
    
                return response()->json([
                    'status' => $status,
                    'message' => $returnMessage,
                    'data' => $responseData
                ]);
            }
    
            $errorData = $response->json() ?? json_decode($response->body(), true);
            $errorMessage = $errorData['returnmessage'] ?? 'Unknown ZaloPay error';
    
            return response()->json([
                'status' => 'error',
                'message' => 'ZaloPay request failed',
                'error' => $errorMessage,
                'http_status' => $response->status(),
            ], 500);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    


}
