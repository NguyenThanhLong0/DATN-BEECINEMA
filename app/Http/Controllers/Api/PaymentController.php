<?php

namespace App\Http\Controllers\Api;

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

class PaymentController extends Controller
{

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

        // Tính toán giá vé và combo
        $priceSeat = $seatShowtimes->sum('price');
        $priceCombo = 0;
        if ($request->combo) {
            foreach ($request->combo as $comboId => $quantity) {
                if ($quantity > 0) {
                    $combo = Combo::findOrFail($comboId);
                    $priceCombo += ($combo->price_sale ?? $combo->price) * $quantity;
                }
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

        // Tính tổng tiền sau khi trừ voucher và điểm tích lũy
        $totalPrice = $priceSeat + $priceCombo;
        $totalDiscount = $voucherDiscount + $pointDiscount;
        $totalPayment = max($totalPrice - $totalDiscount, 10000); // Đảm bảo giá tối thiểu 10k

        Log::info("Giá gốc: $totalPrice - Giảm giá: $totalDiscount - Số tiền thanh toán: $totalPayment");

        // Tạo mã đơn hàng
        $orderCode = date("ymd") . "_" . uniqid();

        // Xác định thời gian giữ ghế theo phương thức thanh toán
        $holdTime = now();
        if ($request->payment_name == 'VNPAY' || $request->payment_name == 'ZALOPAY') {
            $holdTime = now()->addMinutes(15); // Giữ ghế 15 phút cho VNPAY và ZALOPAY
        } elseif ($request->payment_name == 'MOMO') {
            $holdTime = now()->addMinutes(10); // Giữ ghế 10 phút cho MOMO
        } else {
            // Thêm các phương thức thanh toán khác
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


        //log
        Log::info("Lưu đơn hàng vào Cache: payment_{$orderCode}", [
            'data' => [
                'user_id' => $userId,
                'cinema_id' => $showtime->cinema_id,
                'room_id' => $showtime->room_id,
                'movie_id' => $showtime->movie_id,
                'showtime_id' => $showtime->id,
                'voucher_code' => $voucher->code ?? null,
                'voucher_discount' => $voucherDiscount,
                'point_use' => $pointUsed,
                'point_discount' => $pointDiscount,
                'payment_name' => $request->payment_name,
                'code' => $orderCode,
                'total_price' => $totalPayment,
                'expiry' => $showtime->end_time,
                'seats' => $seatIds,
                'combos' => $request->combo ?? [],
            ]
        ]);

        // Lưu vào cache
        Cache::put("payment_{$orderCode}", [
            'user_id' => $userId,
            'cinema_id' => $showtime->cinema_id,
            'room_id' => $showtime->room_id,
            'movie_id' => $showtime->movie_id,
            'showtime_id' => $showtime->id,
            'voucher_code' => $voucher->code ?? null,
            'voucher_discount' => $voucherDiscount,
            'point_use' => $pointUsed, // Số điểm đã dùng
            'point_discount' => $pointDiscount, // Tiền được giảm từ điểm
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

    // ====================THANH TOÁN VNPAY==================== //
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
            'paymentUrl' => $paymentUrl
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

        if (!$paymentData) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng hoặc dữ liệu không hợp lệ.'], 400);
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

                //  Cập nhật trạng thái ghế thành "booker"
                DB::table('seat_showtimes')
                    ->whereIn('seat_id', $paymentData['seats'])
                    ->where('showtime_id', $paymentData['showtime_id'])
                    ->update([
                        'status' => 'booker',
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

                // Tích điểm mới cho người dùng
                $membership = Membership::where('user_id', $ticket->user_id)->first();
                if ($membership) {
                    $pointsEarned = $paymentData['total_price'] * 0.1; // 10% giá trị thanh toán
                    $membership->increment('points', $pointsEarned);
                    PointHistory::create([
                        'membership_id' => $membership->id,
                        'points' => $pointsEarned,
                        'type' => 'Nhận điểm',
                    ]);
                }
            });

            Cache::forget("payment_{$vnp_TxnRef}");

            return response()->json(['message' => 'Thanh toán thành công!', 'order_code' => $paymentData['code']]);
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

        // Cấu hình ZaloPay từ .env
        $app_id = env('ZALOPAY_APP_ID');
        $key1 = env('ZALOPAY_KEY1');
        $endpoint = env('ZALOPAY_ENDPOINT');
        $callback_url = env('ZALOPAY_CALLBACK_URL');

        if (!$app_id || !$key1 || !$endpoint || !$callback_url) {
            return response()->json(["error" => "Thiếu cấu hình ZaloPay"], 400);
        }

        $apptime = round(microtime(true) * 1000);
        $apptransid = $orderCode; // Định dạng mã giao dịch theo yêu cầu của ZaloPay

        // Embed data (tùy chỉnh)
        $embeddata = [
            "merchantinfo" => "embeddata123",
            "redirecturl" => "http://127.0.0.1:8000"
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
            "app_trans_id" => $apptransid,
            "app_user" => "user_demo",
            "amount" => $paymentData['total_price'],
            "description" => "Thanh toán vé xem phim - Đơn hàng #{$orderCode}",
            "bank_code" => "", // Để trống để hỗ trợ nhiều hình thức thanh toán
            "callback_url" => $callback_url,
            "embed_data" => json_encode($embeddata, JSON_UNESCAPED_UNICODE),
            "item" => json_encode($items, JSON_UNESCAPED_UNICODE),
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
            return response()->json([
                "error" => "Lỗi khi gọi API ZaloPay",
                "details" => $responseData
            ], 500);
        }

        return response()->json([
            "status" => "success",
            "zp_trans_token" => $responseData["zp_trans_token"],
            "order_url" => $responseData["order_url"],
            "cashier_order_url" => $responseData["cashier_order_url"],
            "qr_code" => $responseData["qr_code"]
        ]);
    }


    public function zalopayCallback(Request $request)
    {
        $result = [];

        try {
            $key2 = env('ZALOPAY_KEY2'); // Key2 để xác thực callback
            $postdata = $request->getContent();
            $postdatajson = json_decode($postdata, true);

            // Log dữ liệu callback
            Log::info("Dữ liệu callback từ ZaloPay:", ['data' => $postdatajson]);

            // Kiểm tra chữ ký MAC
            $mac = hash_hmac("sha256", $postdatajson["data"], $key2);
            $requestmac = $postdatajson["mac"];

            if (strcmp($mac, $requestmac) != 0) {
                Log::error('ZaloPay callback failed: MAC không hợp lệ.');
                $result["return_code"] = -1;
                $result["return_message"] = "mac not equal";
            } else {
                $datajson = json_decode($postdatajson["data"], true);
                $orderCode = $datajson["app_trans_id"];

                Log::info("ZaloPay xác nhận thanh toán thành công, đơn hàng: {$orderCode}");

                // Kiểm tra đơn hàng từ Cache
                $paymentData = Cache::get("payment_{$orderCode}");
                //log
                Log::info(" Tìm đơn hàng trong Cache: payment_{$orderCode}", [
                    'found' => !empty($paymentData),
                    'orderCode' => $orderCode,
                    'paymentData' => $paymentData
                ]);

                if (!$paymentData) {
                    Log::warning("Không tìm thấy đơn hàng trong Cache, tìm trong Database: {$orderCode}");
                }

                if ($paymentData) {
                    // Kiểm tra nếu vé đã tồn tại để tránh duplicate
                    $existingTicket = Ticket::where('code', $paymentData['code'])->first();
                    if ($existingTicket) {
                        Log::warning("Vé đã tồn tại, không tạo lại: {$orderCode}");
                    } else {
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
                                'payment_name' => 'ZALOPAY',
                                'code' => $paymentData['code'],
                                'total_price' => $paymentData['total_price'],
                                'status' => 'Đã thanh toán',
                                'expiry' => $paymentData['expiry'],
                            ]);

                            Log::info("Đã tạo vé thành công cho đơn hàng: {$paymentData['code']}");

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

                            //  **Cập nhật trạng thái ghế thành "booker"**
                            DB::table('seat_showtimes')
                                ->whereIn('seat_id', $paymentData['seats'])
                                ->where('showtime_id', $paymentData['showtime_id'])
                                ->update([
                                    'status' => 'booker',
                                    'user_id' => $paymentData['user_id'],
                                    'updated_at' => now()
                                ]);

                            // **XÓA JOB GIỮ GHẾ**
                            foreach ($paymentData['seats'] as $seatId) {
                                Cache::forget("seat_hold_{$seatId}_{$paymentData['showtime_id']}");
                            }

                            // **Trừ điểm của người dùng**
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

                            // **Tích điểm mới cho người dùng**
                            $membership = Membership::where('user_id', $paymentData['user_id'])->first();
                            if ($membership) {
                                $pointsEarned = $paymentData['total_price'] * 0.1; // 10% giá trị thanh toán
                                $membership->increment('points', $pointsEarned);
                                PointHistory::create([
                                    'membership_id' => $membership->id,
                                    'points' => $pointsEarned,
                                    'type' => 'Nhận điểm',
                                ]);
                            }
                        });
                    }

                    // Xóa đơn hàng khỏi Cache sau khi xử lý
                    if (Cache::has("payment_{$orderCode}")) {
                        Cache::forget("payment_{$orderCode}");
                        Log::info("Đã xóa Cache cho đơn hàng: payment_{$orderCode}");
                    }
                } else {
                    Log::error("Không tìm thấy dữ liệu đơn hàng: {$orderCode}");
                }

                $result["return_code"] = 1;
                $result["return_message"] = "success";
            }
        } catch (\Exception $e) {
            Log::error('ZaloPay callback error: ' . $e->getMessage());
            $result["return_code"] = 0;
            $result["return_message"] = $e->getMessage();
        }

        Log::info("Phản hồi callback gửi lại ZaloPay:", ['response' => $result]);

        return response()->json($result);
    }


    // ====================END THANH TOÁN ZALOPAY==================== //
}
