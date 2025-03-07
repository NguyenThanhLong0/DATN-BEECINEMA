<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Events\SeatStatusChange;
use App\Http\Controllers\Controller;
use App\Jobs\ReleaseSeatHoldJob;
use App\Jobs\BroadcastSeatStatusChange;
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

class PaymentController extends Controller
{

    private function generateOrderCode()
    {
        $uniquePart = substr(uniqid(), -8); // Lấy 8 số cuối của uniqid()
        $randomPart = rand(10000000, 99999999); // Thêm 8 số ngẫu nhiên

        return $uniquePart . $randomPart; // Tổng cộng 16 số
    }

    public function payment(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'seat_id' => 'required|array',
            'seat_id.*' => 'integer|exists:seats,id',
            'combo' => 'nullable|array',
            'combo.*' => 'nullable|integer|min:0|max:10',
            'voucher_code' => 'nullable|string|exists:vouchers,code',
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'payment_name' => 'required|string',
            'use_points' => 'nullable|integer|min:0',
            'total_price' => 'required|numeric|min:10000', // Tổng tiền còn lại
        ]);

        $userId = auth()->id();
        $showtime = Showtime::findOrFail($request->showtime_id);
        $seatIds = $request->seat_id;

        // Kiểm tra trạng thái ghế
        $seatShowtimes = DB::table('seat_showtimes')
            ->whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtime->id)
            ->get();

        foreach ($seatShowtimes as $seat) {
            if ($seat->hold_expires_at < now() || $seat->user_id != $userId || $seat->status != 'hold') {
                return response()->json(['error' => 'Một hoặc nhiều ghế không hợp lệ.'], 400);
            }
        }

        // Kiểm tra và áp dụng voucher
        $voucherDiscount = 0;
        $voucher = Voucher::where('code', $request->voucher_code)->first();
        if ($voucher && $voucher->quantity > 0) {
            $voucherDiscount = $voucher->discount;
        }

        // Kiểm tra điểm tích lũy
        $membership = Membership::where('user_id', $userId)->first();
        $pointUsed = min($membership->points ?? 0, $request->use_points ?? 0); // Số điểm sử dụng không được vượt quá số điểm hiện có
        $pointDiscount = $pointUsed; // 1 điểm = 1 VND



        // Lưu tổng tiền đã tính toán từ frontend (tổng tiền đã giảm)
        $totalPayment = $request->total_price;

        Log::info("Tổng tiền thanh toán đã gửi từ frontend: $totalPayment");


        // Tạo mã đơn hàng 16 số cho tất cả các thanh toán
        $orderCode = $this->generateOrderCode();
        $zalopayOrderCode = date("ymd") . "_" . $orderCode;

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
                'hold_expires_at' => $holdTime,
                'user_id' => $userId,
            ]);

        // Thêm job để tự động giải phóng ghế sau 15 phút nếu chưa thanh toán
        foreach ($seatIds as $seatId) {
            ReleaseSeatHoldJob::dispatch($seatId, $showtime->id)->delay($holdTime);
        }

        // Log vào cache
        Cache::put("payment_{$orderCode}", [
            'user_id' => $userId,
            'cinema_id' => $showtime->cinema_id,
            'room_id' => $showtime->room_id,
            'movie_id' => $showtime->movie_id,
            'showtime_id' => $showtime->id,
            'voucher_code' => $voucher->code ?? null,
            'voucher_discount' => $voucherDiscount,
            'point_use' => $pointUsed, // Số điểm đã dùng
            'point_discount' => $pointDiscount,
            'payment_name' => $request->payment_name,
            'code' => $orderCode,
            'total_price' => $totalPayment,
            'expiry' => $showtime->end_time,
            'seats' => $seatIds,
            'combos' => $request->combo ?? [],
        ], now()->addMinutes(60));

        // Chuyển hướng đến phương thức thanh toán
        if ($request->payment_name == 'VNPAY') {
            return $this->vnPayPayment($orderCode);
        } elseif ($request->payment_name == 'ZALOPAY') {
            return $this->zalopayPayment($orderCode);
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

        // Tạo danh sách tham số gửi đi
        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query = "";
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $paymentUrl = $vnp_Url . "?" . $query . "vnp_SecureHash=" . $vnp_SecureHash;

        Log::info('VNPAY Redirect URL:', ['url' => $paymentUrl]);

        return response()->json([
            'status' => 'success',
            'payment_url' => $paymentUrl
        ]);
    }

    public function returnVnpay(Request $request)
    {
        $vnp_HashSecret = "47HZGTHKZKUYF1EMWFKE392S8ZHVZGRO";

        // Lấy dữ liệu từ request
        $inputData = $request->all();
        $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? null;

        if (!$vnp_TxnRef) {
            return response()->json(['error' => 'Thiếu mã đơn hàng!'], 400);
        }

        // Lấy dữ liệu từ Cache
        $paymentData = Cache::get("payment_{$vnp_TxnRef}");

        // Nếu thanh toán thất bại, giải phóng ghế
        if ($inputData['vnp_ResponseCode'] != '00') {
            Log::warning("Thanh toán thất bại, giải phóng ghế.");

            // Giải phóng ghế nếu thanh toán thất bại
            DB::table('seat_showtimes')
                ->whereIn('seat_id', $paymentData['seats'])
                ->where('showtime_id', $paymentData['showtime_id'])
                ->update([
                    'status' => 'available',
                    'user_id' => null,
                    'hold_expires_at' => null,
                ]);

            // Xóa cache để tránh lỗi khi xử lý lại
            Cache::forget("payment_{$vnp_TxnRef}");

            return response()->json(['error' => 'Thanh toán thất bại'], 400);
        }

        // Nếu thanh toán thành công, tạo vé và cập nhật điểm
        if ($inputData['vnp_ResponseCode'] == '00') {
            DB::transaction(function () use ($paymentData) {
                // Tạo vé
                $ticket = Ticket::create([
                    'user_id' => $paymentData['user_id'],
                    'cinema_id' => $paymentData['cinema_id'],
                    'room_id' => $paymentData['room_id'],
                    'movie_id' => $paymentData['movie_id'],
                    'showtime_id' => $paymentData['showtime_id'],
                    'voucher_code' => $paymentData['voucher_code'],
                    'voucher_discount' => $paymentData['voucher_discount'],
                    'point_use' => $paymentData['point_use'],
                    'point_discount' => $paymentData['point_discount'],
                    'payment_name' => $paymentData['payment_name'],
                    'code' => $paymentData['code'],
                    'total_price' => $paymentData['total_price'],
                    'status' => 'Đã thanh toán',
                    'expiry' => $paymentData['expiry'],
                ]);

                // Dispatch job gửi email
                SendTicketEmail::dispatch($ticket)->onQueue('emails');

                // **Lưu thông tin ghế vào bảng ticket_seats**
                foreach ($paymentData['seats'] as $seatId) {
                    Ticket_Seat::create([
                        'ticket_id' => $ticket->id,
                        'seat_id' => $seatId,
                        'price' => DB::table('seat_showtimes')->where('seat_id', $seatId)->value('price'),
                    ]);
                }

                // **Lưu combo vào bảng ticket_combos**
                if (!empty($paymentData['combos'])) {
                    foreach ($paymentData['combos'] as $comboId => $quantity) {
                        Ticket_Combo::create([
                            'ticket_id' => $ticket->id,
                            'combo_id' => $comboId,
                            'quantity' => $quantity,
                            'price' => Combo::find($comboId)->price * $quantity,
                        ]);
                    }
                }

                //  Cập nhật trạng thái ghế thành "booked"
                DB::table('seat_showtimes')
                    ->whereIn('seat_id', $paymentData['seats'])
                    ->where('showtime_id', $paymentData['showtime_id'])
                    ->update([
                        'status' => 'booked',
                        'user_id' => $paymentData['user_id'],
                        'updated_at' => now()
                    ]);

                // XÓA JOB GIỮ GHẾ 
                foreach ($paymentData['seats'] as $seatId) {
                    Cache::forget("seat_hold_{$seatId}_{$paymentData['showtime_id']}");
                }


                // Trừ điểm của người dùng
                if ($paymentData['point_use'] > 0) {
                    $membership = Membership::where('user_id', $ticket->user_id)->first();
                    if ($membership) {
                        $membership->decrement('points', $paymentData['point_use']);
                        PointHistory::create([
                            'membership_id' => $membership->id,
                            'points' => -$paymentData['point_use'],
                            'type' => 'Dùng điểm',
                        ]);
                    }
                }

                // Tích điểm mới cho người dùng và cộng total_spent
                $membership = Membership::where('user_id', $ticket->user_id)->first();
                if ($membership) {
                    // Cộng thêm vào total_spent
                    $membership->increment('total_spent', $paymentData['total_price']);
                    // Tích điểm mới cho người dùng
                    $pointsEarned = $paymentData['total_price'] * 0.03; // 3% giá trị thanh toán
                    $membership->increment('points', $pointsEarned);
                    PointHistory::create([
                        'membership_id' => $membership->id,
                        'points' => $pointsEarned,
                        'type' => 'Nhận điểm',
                    ]);
                }

                // Xác định rank mới
                $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                    ->orderBy('total_spent', 'desc')
                    ->first() ?? Rank::orderBy('total_spent', 'asc')->first();

                if ($rank) {
                    $membership->rank_id = $rank->id;
                    $membership->save();
                }
            });

            Cache::forget("payment_{$vnp_TxnRef}");

            return redirect(env('FRONTEND_URL') . "/thanks/{$paymentData['code']}?status=success");
        }

        return response()->json(['error' => 'Thanh toán thất bại.'], 400);
    }

    // ====================END THANH TOÁN VNPAY==================== //


    // ====================THANH TOÁN ZALOPAY==================== //


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

        // Log kiểm tra
        Log::info("ZaloPay - Gửi thanh toán", [
            'zalopay_trans_id' => $zalopayTransId,
            'zalopay_order_code' => $zalopayOrderCode,
            'payment_data' => $paymentData
        ]);

        // Tạo yêu cầu thanh toán ZaloPay
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
            Log::error("Lỗi khi gọi API ZaloPay", ["response" => $responseData]);
            return response()->json(["error" => "Lỗi khi gọi API ZaloPay", "details" => $responseData], 500);
        }

        return response()->json(["status" => "success", "payment_url" => $responseData["order_url"]]);
    }


    public function zalopayCallback(Request $request)
    {
        try {
            $key2 = env('ZALOPAY_KEY2'); // Key2 để xác thực callback
            $postdata = $request->getContent();
            $postdatajson = json_decode($postdata, true);

            // Log dữ liệu callback từ ZaloPay
            Log::info("Dữ liệu callback từ ZaloPay:", ['data' => $postdatajson]);

            // Kiểm tra chữ ký MAC
            $mac = hash_hmac("sha256", $postdatajson["data"], $key2);
            if (strcmp($mac, $postdatajson["mac"]) !== 0) {
                Log::error('ZaloPay callback failed: MAC không hợp lệ.');
                return response()->json(["return_code" => -1, "return_message" => "mac not equal"]);
            }

            // Giải mã dữ liệu từ callback
            $datajson = json_decode($postdatajson["data"], true);
            $zalopayTransId = $datajson["app_trans_id"]; // Mã giao dịch gốc từ ZaloPay
            $orderCode = str_replace(date("ymd") . "_", "", $zalopayTransId); // Lấy 16 số cuối làm mã đơn hàng nội bộ

            // Kiểm tra cache với mã ZaloPayTransId
            $paymentData = Cache::get("payment_{$zalopayTransId}");

            if (!$paymentData) {
                Log::warning("Không tìm thấy đơn hàng trong Cache cho ZaloPay Trans ID: {$zalopayTransId}");
                return response()->json(['error' => 'Không tìm thấy đơn hàng.'], 400);
            }

            Log::info("ZaloPay xác nhận thanh toán thành công, mã đơn hàng: {$orderCode}");

            // Kiểm tra nếu vé đã tồn tại để tránh duplicate
            $existingTicket = Ticket::where('code', $orderCode)->first();
            if ($existingTicket) {
                Log::warning("Vé đã tồn tại, không tạo lại: {$orderCode}");
            } else {
                DB::transaction(function () use ($paymentData, $orderCode) {
                    // Tạo vé
                    $ticket = Ticket::create([
                        'user_id' => $paymentData['user_id'],
                        'cinema_id' => $paymentData['cinema_id'],
                        'room_id' => $paymentData['room_id'],
                        'movie_id' => $paymentData['movie_id'],
                        'showtime_id' => $paymentData['showtime_id'],
                        'voucher_code' => $paymentData['voucher_code'],
                        'voucher_discount' => $paymentData['voucher_discount'],
                        'point_use' => $paymentData['point_use'],
                        'point_discount' => $paymentData['point_discount'],
                        'payment_name' => 'ZALOPAY',
                        'code' => $orderCode, // Chỉ lưu 16 số cuối
                        'total_price' => $paymentData['total_price'],
                        'status' => 'Đã thanh toán',
                        'expiry' => $paymentData['expiry'],
                    ]);

                    Log::info("Đã tạo vé thành công cho đơn hàng: {$orderCode}");

                    SendTicketEmail::dispatch($ticket)->onQueue('emails');

                    // Lưu thông tin ghế vào bảng ticket_seats
                    foreach ($paymentData['seats'] as $seatId) {
                        Ticket_Seat::create([
                            'ticket_id' => $ticket->id,
                            'seat_id' => $seatId,
                            'price' => DB::table('seat_showtimes')->where('seat_id', $seatId)->value('price'),
                        ]);
                    }

                    // Lưu thông tin combo vào bảng ticket_combos
                    if (!empty($paymentData['combos'])) {
                        foreach ($paymentData['combos'] as $comboId => $quantity) {
                            Ticket_Combo::create([
                                'ticket_id' => $ticket->id,
                                'combo_id' => $comboId,
                                'quantity' => $quantity,
                                'price' => Combo::find($comboId)->price * $quantity,
                            ]);
                        }
                    }

                    // Cập nhật trạng thái ghế thành "booked"
                    DB::table('seat_showtimes')
                        ->whereIn('seat_id', $paymentData['seats'])
                        ->where('showtime_id', $paymentData['showtime_id'])
                        ->update([
                            'status' => 'booked',
                            'user_id' => $paymentData['user_id'],
                            'updated_at' => now()
                        ]);

                    // Xóa Job giữ ghế trong cache
                    foreach ($paymentData['seats'] as $seatId) {
                        Cache::forget("seat_hold_{$seatId}_{$paymentData['showtime_id']}");
                    }

                    // Trừ điểm của người dùng nếu sử dụng
                    if ($paymentData['point_use'] > 0) {
                        $membership = Membership::where('user_id', $paymentData['user_id'])->first();
                        if ($membership) {
                            $membership->decrement('points', $paymentData['point_use']);
                            PointHistory::create([
                                'membership_id' => $membership->id,
                                'points' => -$paymentData['point_use'],
                                'type' => 'Dùng điểm',
                            ]);
                        }
                    }

                    // Cộng điểm thưởng mới cho người dùng
                    $membership = Membership::where('user_id', $paymentData['user_id'])->first();
                    if ($membership) {
                        $membership->increment('total_spent', $paymentData['total_price']);
                        $pointsEarned = round($paymentData['total_price'] * 0.03);
                        $membership->increment('points', $pointsEarned);
                        PointHistory::create([
                            'membership_id' => $membership->id,
                            'points' => $pointsEarned,
                            'type' => 'Nhận điểm',
                        ]);
                    }

                    // Kiểm tra rank mới của thành viên
                    $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                        ->orderBy('total_spent', 'desc')
                        ->first() ?? Rank::orderBy('total_spent', 'asc')->first();

                    if ($rank) {
                        $membership->rank_id = $rank->id;
                        $membership->save();
                    }

                    Log::info("Hoàn tất xử lý vé cho đơn hàng: {$orderCode}");
                });
            }

            // Xóa đơn hàng khỏi Cache sau khi xử lý thành công
            Cache::forget("payment_{$zalopayTransId}");
            Log::info("Đã xóa Cache cho đơn hàng: payment_{$zalopayTransId}");

            return response()->json(["return_code" => 1, "return_message" => "success"]);
        } catch (Exception $e) {
            Log::error('ZaloPay callback error: ' . $e->getMessage());
            return response()->json(["return_code" => 0, "return_message" => $e->getMessage()]);
        }
    }

    public function handleZaloPayRedirect(Request $request)
    {
        $status = $request->input('status'); // Lấy trạng thái thanh toán từ params
        $orderCode = $request->input('orderCode'); // Lấy mã đơn hàng từ params

        // Kiểm tra nếu thanh toán thành công
        if ($status == 1) {
            return response()->json([
                'status' => 'success',
                'message' => 'Thanh toán thành công',
            ]); // Trả về JSON thông báo thành công
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
        }

        return response()->json([
            'orderCode' => $orderCode,
            'status' => 'failure',
            'message' => 'Thanh toán thất bại, ghế đã được giải phóng',
        ]); // Trả về JSON thông báo thất bại
    }

    // ====================END THANH TOÁN ZALOPAY==================== //
}
