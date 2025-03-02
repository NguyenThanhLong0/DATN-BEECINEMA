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





class PaymentController extends Controller
{
    // public function payment(Request $request)
    // {
    //     // 1. Xác thực dữ liệu đầu vào
    //     $validator = Validator::make($request->all(), [
    //         'seat_id' => 'required|array',
    //         'seat_id.*' => 'integer|exists:seats,id',
    //         'combo' => 'nullable|array',
    //         'combo.*' => 'nullable|integer|min:0|max:10',
    //         'voucher_code' => 'nullable|string|exists:vouchers,code',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 400);
    //     }

    //     $seatIds = $request->seat_id; // Danh sách ghế từ request
    //     $showtimeId = $request->showtime_id;
    //     $userId = auth()->id(); // Lấy ID người dùng đang đăng nhập
    //     $showtime = Showtime::where('id', $showtimeId)->first();

    //     if (!$showtime) {
    //         return response()->json(['error' => 'Showtime không hợp lệ.'], 400);
    //     }

    //     // Kiểm tra ghế và tính tổng giá ghế trước khi bắt đầu transaction
    //     $seatShowtimes = DB::table('seat_showtimes')
    //         ->whereIn('seat_id', $seatIds)
    //         ->where('showtime_id', $showtimeId)
    //         ->get();
    //     $priceSeat = $seatShowtimes->sum('price');

    //     // Tính giá combo
    //     $priceCombo = 0;
    //     foreach ($request->combo as $comboId => $quantity) {
    //         if ($quantity > 0) {
    //             $combo = Combo::findOrFail($comboId);
    //             $comboPrice = $combo->price_sale ?? $combo->price;
    //             $priceCombo += $comboPrice * $quantity;
    //         }
    //     }

    //     // Tính giảm giá từ voucher
    //     $voucherDiscount = 0;
    //     $voucher = null;
    //     if ($request->voucher_code) {
    //         $voucher = Voucher::where('code', $request->voucher_code)->first();
    //         if ($voucher && $voucher->quantity > 0) {
    //             $voucherDiscount = $voucher->discount;
    //             $voucher->decrement('quantity');
    //         }
    //     }

    //     // Tính giảm giá từ điểm tích lũy
    //     $pointDiscount = $request->point_discount ?? 0;

    //     // Tính tổng giá thanh toán
    //     $totalPrice = $priceSeat + $priceCombo;
    //     $totalDiscount = $pointDiscount + $voucherDiscount;
    //     $totalPayment = max($totalPrice - $totalDiscount, 10000); // Đảm bảo giá tối thiểu là 10k

    //     // Kiểm tra nếu có ghế hết thời gian giữ chỗ
    //     $hasExpiredSeats = false;
    //     foreach ($seatShowtimes as $seatShowtime) {
    //         if ($seatShowtime->hold_expires_at < now() || $seatShowtime->user_id != $userId || $seatShowtime->status != 'hold') {
    //             $hasExpiredSeats = true;
    //             break;
    //         }
    //     }

    //     // if ($hasExpiredSeats) {
    //     //     return response()->json(['error' => 'Ghế đã hết thời gian giữ chỗ hoặc ghế đã bán. Vui lòng chọn lại ghế.'], 400);
    //     // }

    //     try {
    //         // Thực hiện transaction
    //         DB::transaction(function () use ($seatIds, $showtimeId, $userId, $request, $voucherDiscount, $totalPayment, $pointDiscount,$dataUsePoint, $priceSeat, $priceCombo, $voucher) {
    //             // Gia hạn thời gian giữ chỗ thêm 15 phút
    //             DB::table('seat_showtimes')
    //                 ->whereIn('seat_id', $seatIds)
    //                 ->where('showtime_id', $showtimeId)
    //                 ->update([
    //                     'hold_expires_at' => now()->addMinutes(15),
    //                 ]);

    //             // Lưu thông tin thanh toán vào session
    //             session([
    //                 'payment_data' => [
    //                     'code' => Ticket::generateTicketCode(),
    //                     'user_id' => $request->user_id,
    //                     'payment_name' => $request->payment_name,
    //                     'voucher_code' => $voucher->code ?? null,
    //                     'voucher_discount' => $voucher->discount ?? 0,
    //                     'point_use' => $dataUsePoint['use_points'] ?? null,
    //                     'point_discount' => $pointDiscount,
    //                     'total_price' => $totalPayment,
    //                     'showtime_id' => $request->showtime_id,
    //                     'seat_id' => $request->seat_id,
    //                     'priceSeat' => $priceSeat,
    //                     'priceCombo' => $priceCombo,
    //                     'combo' => $request->combo,
    //                 ]
    //             ]);

    //             // Dispatch job để giải phóng ghế sau 15 phút
    //             foreach ($seatIds as $seatId) {
    //                 ReleaseSeatHoldJob::dispatch($seatId, $showtimeId, $voucher->code ?? null)
    //                     ->delay(now()->addMinutes(15));
    //             }
    //         });

    //         // Chuyển hướng tới payment method (Ví dụ: MoMo, VNPAY)
    //         return response()->json([
    //             'message' => 'Thanh toán thành công, chuyển hướng đến phương thức thanh toán.',
    //             'payment_method' => $request->payment_name, // Hiển thị phương thức thanh toán
    //             'redirect_url' => $this->getPaymentRedirectUrl($request->payment_name) // Trả về URL chuyển hướng, ví dụ cho VNPAY
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['errors' => $e->getMessage(), 'error' => 'Đã xảy ra lỗi khi xử lý thanh toán.'], 500);
    //     }
    // }

    public function payment(Request $request)
    {
        // 1. Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'seat_id' => 'required|array',
            'seat_id.*' => 'integer|exists:seats,id',
            'combo' => 'nullable|array',
            'combo.*' => 'nullable|integer|min:0|max:10',
            'voucher_code' => 'nullable|string|exists:vouchers,code',
            'showtime_id' => 'required|integer|exists:showtimes,id',
            'payment_name' => 'required|string', // Thêm xác thực cho phương thức thanh toán
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $seatIds = $request->seat_id; // Danh sách ghế từ request
        $showtimeId = $request->showtime_id;
        $userId = auth()->id(); // Lấy ID người dùng đang đăng nhập
        $showtime = Showtime::where('id', $showtimeId)->first();

        if (!$showtime) {
            return response()->json(['error' => 'Showtime không hợp lệ.'], 400);
        }

        // Kiểm tra ghế và tính tổng giá ghế trước khi bắt đầu transaction
        $seatShowtimes = DB::table('seat_showtimes')
            ->whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtimeId)
            ->get();
        $priceSeat = $seatShowtimes->sum('price');

        // 4. Tính giá combo
        $priceCombo = 0;
        foreach ($request->combo as $comboId => $quantity) {
            if ($quantity > 0) {
                $combo = Combo::findOrFail($comboId);
                $comboPrice = $combo->price_sale ?? $combo->price; // Nếu có giá khuyến mãi thì dùng, không thì dùng giá gốc
                $priceCombo += $comboPrice * $quantity;
            }
        }

        // 5. Xác thực và tính giá voucher
        $voucherDiscount = 0;
        $voucher = null;
        if ($request->voucher_code) {
            $voucher = Voucher::where('code', $request->voucher_code)->first();
            if ($voucher && $voucher->quantity > 0) {
                $voucherDiscount = $voucher->discount;
                $voucher->decrement('quantity'); // Giảm số lượng voucher sau khi sử dụng
            }
        }

        // 6. Tính giảm giá từ điểm tích lũy
        $pointDiscount = $request->point_discount ?? 0;

        // 7. Tính tổng giá, tổng giảm giá và tổng thanh toán
        $totalPrice = $priceSeat + $priceCombo;
        $totalDiscount = $pointDiscount + $voucherDiscount;
        $totalPayment = max($totalPrice - $totalDiscount, 10000); // Đảm bảo giá tối thiểu là 10k

        // Kiểm tra nếu có ghế hết thời gian giữ chỗ
        $hasExpiredSeats = false;
        foreach ($seatShowtimes as $seatShowtime) {
            if ($seatShowtime->hold_expires_at < now() || $seatShowtime->user_id != $userId || $seatShowtime->status != 'hold') {
                $hasExpiredSeats = true;
                break;
            }
        }

        if ($hasExpiredSeats) {
            return response()->json(['error' => 'Ghế đã hết thời gian giữ chỗ hoặc ghế đã bán. Vui lòng chọn lại ghế.'], 400);
        }

        try {
            // Thực hiện transaction
            DB::transaction(function () use ($seatIds, $showtimeId, $userId, $request, $voucherDiscount, $totalPayment, $pointDiscount, $voucher, $priceSeat, $priceCombo) {
                // Gia hạn thời gian giữ chỗ thêm 15 phút
                DB::table('seat_showtimes')
                    ->whereIn('seat_id', $seatIds)
                    ->where('showtime_id', $showtimeId)
                    ->update([
                        'hold_expires_at' => now()->addMinutes(15),
                    ]);

                // Lưu thông tin thanh toán vào session
                session([
                    'payment_data' => [
                        'code' => Ticket::generateTicketCode(),
                        'user_id' => $userId,
                        'payment_name' => $request->payment_name,
                        'voucher_code' => $voucher->code ?? null,
                        'voucher_discount' => $voucher->discount ?? 0,
                        'point_discount' => $pointDiscount,
                        'total_price' => $totalPayment,
                        'showtime_id' => $showtimeId,
                        'seat_id' => $seatIds,
                        'priceSeat' => $priceSeat,
                        'priceCombo' => $priceCombo,
                        'combo' => $request->combo,
                    ]
                ]);

                // Dispatch job để giải phóng ghế sau 15 phút
                foreach ($seatIds as $seatId) {
                    ReleaseSeatHoldJob::dispatch($seatId, $showtimeId, $voucher->code ?? null)
                        ->delay(now()->addMinutes(15));
                }
            });

            // Chuyển hướng tới payment method (Ví dụ: MoMo, VNPAY)
            return response()->json([
                'message' => 'Thanh toán thành công, chuyển hướng đến phương thức thanh toán.',
                'payment_method' => $request->payment_name, // Hiển thị phương thức thanh toán
                'redirect_url' => $this->getPaymentRedirectUrl($request->payment_name) // Trả về URL chuyển hướng, ví dụ cho VNPAY
            ]);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e->getMessage(), 'error' => 'Đã xảy ra lỗi khi xử lý thanh toán.'], 500);
        }
    }

    // ====================THANH TOÁN VNPAY====================

    public function vnPayPayment(Request $request)
    {
        $paymentData = session()->get('payment_data', []);

        if (empty($paymentData) || !isset($paymentData['code'])) {
            // Ghi log nếu không có dữ liệu thanh toán trong session
            Log::error('Dữ liệu thanh toán không hợp lệ hoặc không tồn tại.', ['payment_data' => $paymentData]);

            return response()->json(['error' => 'Dữ liệu thanh toán không hợp lệ hoặc không tồn tại.'], 400);
        }

        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = route('vnpay.return');
        $vnp_TmnCode = "5TZE79MF"; // Mã website tại VNPAY
        $vnp_HashSecret = "47HZGTHKZKUYF1EMWFKE392S8ZHVZGRO"; // Chuỗi bí mật
        $vnp_TxnRef = $paymentData['code']; // Mã đơn hàng
        $vnp_OrderInfo = 'Nội dung thanh toán';
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $paymentData['total_price'] * 100; // Chuyển sang đơn vị tiền tệ phù hợp
        $vnp_Locale = 'vn';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

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

        $vnp_Url = $vnp_Url . "?" . $query;
        $vnp_Url .= 'vnp_SecureHash=' . hash_hmac('sha512', $hashdata, $vnp_HashSecret);

        return redirect($vnp_Url);
    }

    public function getPaymentRedirectUrl($paymentName)
    {
        // Kiểm tra phương thức thanh toán và trả về URL tương ứng
        switch ($paymentName) {
            case 'VNPAY':
                return "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html"; // URL VNPAY
            case 'MoMo':
                return "https://www.momo.vn";
            default:
                // Trường hợp không hợp lệ, trả về lỗi hoặc URL mặc định
                return url('/'); // Hoặc có thể trả về một thông báo lỗi hoặc trang mặc định
        }
    }


    public function returnVnpay(Request $request)
    {
        // lấy dữ liệu của session và kiểm tra nó xem còn tồn tại hay không
        $paymentData = session()->get('payment_data', []);
        if (empty($paymentData)) {
            return redirect()->route('home')->with('error', 'Dữ liệu thanh toán không tồn tại.');
        }

        $vnp_ResponseCode = $request->input('vnp_ResponseCode');
        $vnp_TxnRef = $request->input('vnp_TxnRef'); // mã giao dịch duy nhất
        $showtime = Showtime::find($paymentData['showtime_id']);

        // Kiểm tra nếu thanh toán thành công
        if ($vnp_ResponseCode == '00') {
            try {
                DB::transaction(function () use ($paymentData, $showtime) {
                    $existingTicket = Ticket::where('code', $paymentData['code'])->first();
                    if ($existingTicket) {
                        throw new \Exception('Đơn hàng đã được xử lý.');
                    }

                    // Lưu vào bảng tickets
                    $ticket = Ticket::create([
                        'user_id' => $paymentData['user_id'],
                        'cinema_id' => $showtime->cinema_id,
                        'room_id' => $showtime->room_id,
                        'movie_id' => $showtime->movie_id,
                        'showtime_id' => $paymentData['showtime_id'],
                        'voucher_code' => $paymentData['voucher_code'],
                        'voucher_discount' => $paymentData['voucher_discount'],
                        'point_use' => $paymentData['point_use'],
                        'point_discount' => $paymentData['point_discount'],
                        'payment_name' => "Ví VnPay",
                        'code' => $paymentData['code'],
                        'total_price' => $paymentData['total_price'],
                        'status' => 'Chưa xuất vé',
                        'expiry' => $showtime->end_time,
                    ]);

                    // Lưu thông tin bảng ticket_seat và update lại status của ghế
                    foreach ($paymentData['seat_id'] as $seatId) {
                        Ticket_Seat::create([
                            'ticket_id' => $ticket->id,
                            // 'showtime_id' => $paymentData['showtime_id'],
                            'seat_id' => $seatId,
                            'price' => DB::table('seat_showtimes')
                                ->where('seat_id', $seatId)
                                ->where('showtime_id', $paymentData['showtime_id'])
                                ->value('price'),
                        ]);

                        DB::table('seat_showtimes')
                            ->where('seat_id', $seatId)
                            ->where('showtime_id', $paymentData['showtime_id'])
                            ->update([
                                'status' => 'sold',
                                'hold_expires_at' => null,
                            ]);

                        // event(new SeatStatusChange($seatId, $paymentData['showtime_id'], 'sold'));
                        broadcast(new SeatStatusChange($seatId, $paymentData['showtime_id'], 'sold', auth()->id()))->toOthers();
                    }

                    // Lưu thông tin combo vào bảng ticket_combos
                    foreach ($paymentData['combo'] as $comboId => $quantity) {
                        if ($quantity > 0) {
                            $combo = Combo::find($comboId);

                            // Tính giá bằng price_sale nếu có, nếu không thì lấy price
                            $price = $combo->price_sale ?? $combo->price;

                            Ticket_Combo::create([
                                'ticket_id' => $ticket->id,
                                'combo_id' => $comboId,
                                'price' => $price * $quantity,  // Nhân giá với số lượng
                                'quantity' => $quantity,
                                // 'status' => 'Chưa lấy đồ ăn',
                            ]);
                        }
                    }

                    // Lấy thông tin thành viên
                    $membership = Membership::findOrFail($ticket->user_id);

                    // Tiêu điểm
                    if ($ticket->point_use > 0) {
                        $membership->decrement('points', $ticket->point_use);
                        PointHistory::create([
                            'membership_id' => $membership->id,
                            'points' => $ticket->point_use,
                            'type' => PointHistory::POINTS_SPENT,
                        ]);
                    }

                    // Tích điểm
                    $rank = Rank::findOrFail($membership->rank_id);
                    $pointsForTicket = $paymentData['priceSeat'] * ($rank->ticket_percentage / 100);
                    $pointsForCombo = $paymentData['priceCombo'] * ($rank->combo_percentage / 100);
                    $totalPoints = $pointsForTicket + $pointsForCombo;

                    $membership->increment('points', $totalPoints);
                    $membership->increment('total_spent', $ticket->total_price);
                    PointHistory::create([
                        'membership_id' => $membership->id,
                        'points' => $totalPoints,
                        'type' => PointHistory::POINTS_ACCUMULATED,

                    ]);

                    // Kiểm tra thăng hạng
                    $newRank = Rank::where('total_spent', '<=', $membership->total_spent)
                        ->orderBy('total_spent', 'desc')
                        ->first();

                    if ($newRank && $newRank->id != $membership->rank_id) {
                        $membership->update(['rank_id' => $newRank->id]);
                    }

                    if (session()->has('payment_voucher')) {
                        $voucher = Voucher::find(session('payment_voucher.voucher_id'));
                        if ($voucher) {
                            $userVoucher = UserVoucher::where('user_id', $paymentData['user_id'])
                                ->where('voucher_id', $voucher->id)
                                ->first();

                            if ($userVoucher) {
                                // Nếu đã tồn tại, tăng usage_count
                                $userVoucher->increment('usage_count');
                            } else {
                                // Nếu chưa tồn tại, tạo bản ghi mới với usage_count = 1
                                UserVoucher::create([
                                    'user_id' => $paymentData['user_id'],
                                    'voucher_id' => $voucher->id,
                                    'usage_count' => 1,
                                ]);
                            }
                        }
                    }
                    // // lưu voucher lượt sd voucher
                    // if ($paymentData['voucher_code'] != null) {
                    //     $voucher = Voucher::where('code', $paymentData['voucher_code'])->first();
                    //     if ($voucher) {
                    //         $userVoucher = UserVoucher::where('user_id', $paymentData['user_id'])
                    //             ->where('voucher_id', $voucher->id)
                    //             ->first();

                    //         if ($userVoucher) {
                    //             // Nếu đã tồn tại, tăng usage_count
                    //             $userVoucher->increment('usage_count');
                    //         } else {
                    //             // Nếu chưa tồn tại, tạo bản ghi mới với usage_count = 1
                    //             UserVoucher::create([
                    //                 'user_id' => $paymentData['user_id'],
                    //                 'voucher_id' => $voucher->id,
                    //                 'usage_count' => 1,
                    //             ]);
                    //         }
                    //     }
                    // }

                    // Gửi email hóa đơn
                    // Mail::to($ticket->user->email)->send(new TicketInvoiceMail($ticket));
                });

                $timeKey = 'timeData.' . $paymentData['showtime_id'];

                session()->forget($timeKey);
                session()->forget("checkout_data.$showtime->id");
                session()->forget('payment_data');

                return redirect()->route('home')->with('success', 'Thanh toán thành công!');
            } catch (\Exception $e) {
                // Xử lý thanh toán thất bại hoặc hủy
                return $this->handleFailedPayment($paymentData);
            }
        } else {
            // Xử lý thanh toán thất bại hoặc hủy
            return $this->handleFailedPayment($paymentData);
        }
    }

    // ====================END THANH TOÁN VNPAY====================



}
