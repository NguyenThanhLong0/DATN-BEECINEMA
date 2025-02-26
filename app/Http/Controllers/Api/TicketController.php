<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Point_history;
use App\Models\Rank;
use App\Models\Ticket_Seat;
use App\Models\Ticket_Combo;
use App\Models\User_Voucher;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    // Lấy danh sách tất cả tickets
    public function index()
    {
        return response()->json(Ticket::all(), 200);
    }

    // Lấy thông tin 1 ticket theo ID
    public function show($id)
    {
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }
        return response()->json($ticket, 200);
    }
    // public function store(Request $request)
    // {
    //     // Validation
    //     $validatedData = Validator::make($request->all(), [
    //         'user_id' => 'required|exists:users,id',
    //         'cinema_id' => 'required|exists:cinemas,id',
    //         'room_id' => 'required|exists:rooms,id',
    //         'movie_id' => 'required|exists:movies,id',
    //         'showtime_id' => 'required|exists:showtimes,id',
    //         'voucher_code' => 'nullable|string',
    //         'voucher_discount' => 'nullable|integer',
    //         'payment_name' => 'required|string',
    //         'code' => 'required|string|unique:tickets,code',
    //         'status' => ['required', 'string', function ($attribute, $value, $fail) {
    //                 $allowedValues = ['chưa xuất vé', 'đã xuất vé']; 
    //                 if (!in_array(mb_strtolower($value), $allowedValues)) { 
    //                     $fail("Giá trị của $attribute không hợp lệ.");
    //                 }
    //         }],
    //         'staff' => 'nullable|string',
    //         'expiry' => 'required|date_format:Y-m-d H:i:s',
    //         'combos' => 'nullable|array',
    //         'combos.*.combo_id' => 'required|exists:combos,id',
    //         'combos.*.price' => 'required|integer|min:0',
    //         'combos.*.quantity' => 'required|integer|min:1',
    //         'seats' => 'nullable|array',
    //         'seats.*.seat_id' => 'required|exists:seats,id',
    //         'seats.*.price' => 'required|integer|min:0',
    //         'voucher_id' => 'nullable|exists:vouchers,id',
    //     ]);

    //     if ($validatedData->fails()) {
    //         return response()->json(['errors' => $validatedData->errors()], 422);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         // 🏷 Tạo ticket trước, chưa có total_price
    //         $ticket = Ticket::create($request->only([
    //             'user_id', 'cinema_id', 'room_id', 'movie_id', 'showtime_id',
    //             'voucher_code', 'voucher_discount', 'payment_name', 'code',
    //             'status', 'staff', 'expiry',
    //         ]) + ['total_price' => 0]); // Ban đầu đặt total_price = 0

    //         $total_price = 0;

    //         //  Lưu combo nếu có
    //         if (!empty($request->combos)) {
    //             foreach ($request->combos as $combo) {
    //                 Ticket_Combo::create([
    //                     'ticket_id' => $ticket->id,
    //                     'combo_id' => $combo['combo_id'],
    //                     'price' => $combo['price'],
    //                     'quantity' => $combo['quantity']
    //                 ]);

    //                 //  Cộng tiền combo (price * quantity)
    //                 $total_price += $combo['price'] * $combo['quantity'];
    //             }
    //         }

    //         //  Lưu ghế nếu có
    //         if (!empty($request->seats)) {
    //             foreach ($request->seats as $seat) {
    //                 Ticket_Seat::create([
    //                     'ticket_id' => $ticket->id,
    //                     'seat_id' => $seat['seat_id'],
    //                     'price' => $seat['price']
    //                 ]);

    //                 //  Cộng tiền ghế
    //                 $total_price += $seat['price'];
    //             }
    //         }

    //         //  Áp dụng Voucher nếu có
    //         if ($request->voucher_id) {
    //             $voucher = Voucher::find($request->voucher_id);
    //             if ($voucher) {
    //                 if ($voucher->type == '1') {
    //                     //  Giảm giá theo % (Ví dụ: 10% -> giảm 10% tổng tiền)
    //                     $discount = ($total_price * $voucher->discount) / 100;

    //                 } else {
    //                     //  Giảm giá số tiền cố định (Ví dụ: -50K)
    //                     $discount = min($voucher->value, $total_price); // Không giảm nhiều hơn tổng tiền
    //                 }

    //                 $total_price -= $discount; //  Cập nhật tổng tiền sau giảm giá

    //                 //  Lưu voucher vào User_Voucher
    //                 $userVoucher = User_Voucher::firstOrCreate(
    //                     ['user_id' => $request->user_id, 'voucher_id' => $request->voucher_id],
    //                     ['usage_count' => 0]
    //                 );
    //                 $userVoucher->increment('usage_count');
    //             }
    //         }

    //         //  Cập nhật lại total_price trong ticket
    //         $ticket->update(['total_price' => $total_price]);

    //         //  Kiểm tra & tạo Membership nếu chưa có
    //         $membership = Membership::firstOrCreate(
    //             ['user_id' => $request->user_id], 
    //             ['total_spent' => 0, 'rank_id' => null]
    //         );

    //         //  Cộng tổng tiền đã chi tiêu (đã áp dụng voucher)
    //         $membership->increment('total_spent', $total_price);

    //         //  Xác định rank dựa trên tổng số tiền đã chi tiêu
    //         $rank = Rank::where('total_spent', '<=', $membership->total_spent)
    //                     ->orderBy('total_spent', 'desc')
    //                     ->first();

    //         // Nếu không có rank nào phù hợp, chọn rank thấp nhất
    //         if (!$rank) {
    //             $rank = Rank::orderBy('total_spent', 'asc')->first();
    //         }

    //         //  Gán rank mới vào membership
    //         if ($rank) {
    //             $membership->rank_id = $rank->id;
    //             $membership->save();
    //         }

    //         // ✅ Hoàn tất transaction
    //         DB::commit();
    //         return response()->json([
    //             'message' => 'Đặt vé thành công!',
    //             'ticket' => $ticket
    //         ], 201);
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Đặt vé thất bại!',
    //             'error' => $th->getMessage()
    //         ], 500);
    //     }
    // }

    public function store(Request $request)
    {
        // Validation
        $validatedData = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cinema_id' => 'required|exists:cinemas,id',
            'room_id' => 'required|exists:rooms,id',
            'movie_id' => 'required|exists:movies,id',
            'showtime_id' => 'required|exists:showtimes,id',
            'voucher_code' => 'nullable|string',
            'voucher_discount' => 'nullable|integer',
            'payment_name' => 'required|string',
            'code' => 'required|string|unique:tickets,code',
            'status' => ['required', 'string', function ($attribute, $value, $fail) {
                $allowedValues = ['chưa xuất vé', 'đã xuất vé'];
                if (!in_array(mb_strtolower($value), $allowedValues)) {
                    $fail("Giá trị của $attribute không hợp lệ.");
                }
            }],
            'staff' => 'nullable|string',
            'expiry' => 'required|date_format:Y-m-d H:i:s',
            'combos' => 'nullable|array',
            'combos.*.combo_id' => 'required|exists:combos,id',
            'combos.*.price' => 'required|integer|min:0',
            'combos.*.quantity' => 'required|integer|min:1',
            'seats' => 'nullable|array',
            'seats.*.seat_id' => 'required|exists:seats,id',
            'seats.*.price' => 'required|integer|min:0',
            'voucher_id' => 'nullable|exists:vouchers,id',
            'use_points' => 'nullable|integer|min:0',
        ]);

        if ($validatedData->fails()) {
            return response()->json(['errors' => $validatedData->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $ticket = Ticket::create($request->only([
                'user_id',
                'cinema_id',
                'room_id',
                'movie_id',
                'showtime_id',
                'voucher_code',
                'voucher_discount',
                'payment_name',
                'code',
                'status',
                'staff',
                'expiry',
            ]) + ['total_price' => 0]);

            $total_price = 0;
            $total_seats = 0;

            // Lưu combo nếu có
            if (!empty($request->combos)) {
                foreach ($request->combos as $combo) {
                    Ticket_Combo::create([
                        'ticket_id' => $ticket->id,
                        'combo_id' => $combo['combo_id'],
                        'price' => $combo['price'],
                        'quantity' => $combo['quantity']
                    ]);
                    $total_price += $combo['price'] * $combo['quantity'];
                }
            }

            // Lưu ghế nếu có
            if (!empty($request->seats)) {
                foreach ($request->seats as $seat) {
                    Ticket_Seat::create([
                        'ticket_id' => $ticket->id,
                        'seat_id' => $seat['seat_id'],
                        'price' => $seat['price']
                    ]);
                    $total_price += $seat['price'];
                    $total_seats++;
                }
            }

            // Áp dụng voucher nếu có
            if ($request->voucher_id) {
                $voucher = Voucher::find($request->voucher_id);
                if ($voucher) {
                    $discount = ($voucher->type == '1')
                        ? ($total_price * $voucher->discount) / 100
                        : min($voucher->discount, $total_price);
                    $total_price -= $discount;
                    $userVoucher = User_Voucher::firstOrCreate(
                        ['user_id' => $request->user_id, 'voucher_id' => $request->voucher_id],
                        ['usage_count' => 0]
                    );
                    $userVoucher->increment('usage_count');
                }
            }

            // Xử lý Membership
            $user = User::findOrFail($request->user_id);
            $isFirstBooking = !Membership::where('user_id', $user->id)->exists(); // Kiểm tra lần đầu đặt vé không

            $membership = Membership::firstOrCreate(
                ['user_id' => $user->id],
                ['total_spent' => 0, 'rank_id' => null, 'points' => 0]
            );

            // Xử lý trừ điểm nếu người dùng nhập số điểm muốn sử dụng
            if ($request->has('use_points') && $request->use_points > 0) {
                $usePoints = (int) $request->use_points;

                // Kiểm tra điểm có đủ không
                if ($usePoints > $membership->points) {
                    return response()->json(['message' => 'Điểm không đủ'], 400);
                }

                // Trừ điểm và cập nhật lịch sử
                $membership->decrement('points', $usePoints);
                $total_price -= $usePoints;

                Point_History::create([
                    'membership_id' => $membership->id,
                    'points' => -$usePoints,
                    'type' => 'trừ điểm',
                ]);
            }

            // Cập nhật total_price của ticket
            $ticket->update(['total_price' => $total_price]);

            // Cập nhật tổng tiền đã chi tiêu
            $membership->increment('total_spent', $total_price);

            // Xác định rank mới
            $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                ->orderBy('total_spent', 'desc')
                ->first() ?? Rank::orderBy('total_spent', 'asc')->first();

            if ($rank) {
                $membership->rank_id = $rank->id;
                $membership->save();
            }

            // Tích điểm: 2500 điểm mỗi ghế đã đặt
            $pointsEarned = $total_seats * 2500;
            $membership->increment('points', $pointsEarned);

            // Lưu vào lịch sử điểm với type "Tích Điểm"
            Point_History::create([
                'membership_id' => $membership->id,
                'points' => $pointsEarned,
                'type' => 'Tích Điểm',
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Đặt vé thành công!',
                'ticket' => $ticket,
                'earned_points' => $pointsEarned,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Đặt vé thất bại!',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    // Cập nhật thông tin ticket
    // public function update(Request $request, $id)
    // {
    //     $ticket = Ticket::find($id);
    //     if (!$ticket) {
    //         return response()->json(['message' => 'Ticket not found'], 404);
    //     }

    //     $validatedData = Validator::make($request->all(), [
    //         'user_id' => 'sometimes|exists:users,id',
    //         'cinema_id' => 'sometimes|exists:cinemas,id',
    //         'room_id' => 'sometimes|exists:rooms,id',
    //         'movie_id' => 'sometimes|exists:movies,id',
    //         'showtime_id' => 'sometimes|exists:showtimes,id',
    //         'voucher_code' => 'nullable|string',
    //         'voucher_discount' => 'nullable|integer',
    //         'payment_name' => 'sometimes|string',
    //         'code' => 'sometimes|string|unique:tickets,code,' . $id,
    //         'total_price' => 'sometimes|integer',
    //         'status' => 'sometimes|string|in:chưa xuất vé,đã xuất vé',
    //         'staff' => 'nullable|string',
    //         'expiry' => 'sometimes|date'
    //     ]);
    //     if ($validatedData->fails()) {
    //         return response()->json(['errors' => $validatedData->errors()], 422);
    //     }

    //     try {
    //         $ticket->update($request->all());
    //         return response()->json(['message' => 'Sửa mới thành công!', 'ticket' => $ticket], 201);
    //     } catch (\Throwable $th) {
    //         return response()->json(['message' => 'Sửa mới thất bại!', 'error' => $th->getMessage()], 500);
    //     }
    // }
    public function update(Request $request, $id)
    {
        // Validation
        $validatedData = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'cinema_id' => 'required|exists:cinemas,id',
            'room_id' => 'required|exists:rooms,id',
            'movie_id' => 'required|exists:movies,id',
            'showtime_id' => 'required|exists:showtimes,id',
            'voucher_code' => 'nullable|string',
            'voucher_discount' => 'nullable|integer',
            'payment_name' => 'required|string',
            'code' => 'required|string|unique:tickets,code,' . $id,
            'status' => ['required', 'string', function ($attribute, $value, $fail) {
                $allowedValues = ['chưa xuất vé', 'đã xuất vé'];
                if (!in_array(mb_strtolower($value), $allowedValues)) {
                    $fail("Giá trị của $attribute không hợp lệ.");
                }
            }],
            'staff' => 'nullable|string',
            'expiry' => 'required|date_format:Y-m-d H:i:s',
            'combos' => 'nullable|array',
            'combos.*.combo_id' => 'required|exists:combos,id',
            'combos.*.price' => 'required|integer|min:0',
            'combos.*.quantity' => 'required|integer|min:1',
            'seats' => 'nullable|array',
            'seats.*.seat_id' => 'required|exists:seats,id',
            'seats.*.price' => 'required|integer|min:0',
            'voucher_id' => 'nullable|exists:vouchers,id',
            'use_points' => 'nullable|integer|min:0',
        ]);

        if ($validatedData->fails()) {
            return response()->json(['errors' => $validatedData->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $ticket = Ticket::findOrFail($id);
            $oldSeatsCount = Ticket_Seat::where('ticket_id', $id)->count();

            $ticket->update($request->only([
                'user_id',
                'cinema_id',
                'room_id',
                'movie_id',
                'showtime_id',
                'voucher_code',
                'voucher_discount',
                'payment_name',
                'code',
                'status',
                'staff',
                'expiry',
            ]));

            $total_price = 0;
            $newSeatsCount = 0;

            Ticket_Combo::where('ticket_id', $id)->delete();
            if (!empty($request->combos)) {
                foreach ($request->combos as $combo) {
                    Ticket_Combo::create([
                        'ticket_id' => $ticket->id,
                        'combo_id' => $combo['combo_id'],
                        'price' => $combo['price'],
                        'quantity' => $combo['quantity']
                    ]);
                    $total_price += $combo['price'] * $combo['quantity'];
                }
            }

            Ticket_Seat::where('ticket_id', $id)->delete();
            if (!empty($request->seats)) {
                foreach ($request->seats as $seat) {
                    Ticket_Seat::create([
                        'ticket_id' => $ticket->id,
                        'seat_id' => $seat['seat_id'],
                        'price' => $seat['price']
                    ]);
                    $total_price += $seat['price'];
                    $newSeatsCount++;
                }
            }

            if ($request->voucher_id) {
                $voucher = Voucher::find($request->voucher_id);
                if ($voucher) {
                    $discount = ($voucher->type == '1')
                        ? ($total_price * $voucher->discount) / 100
                        : min($voucher->discount, $total_price);
                    $total_price -= $discount;
                    $userVoucher = User_Voucher::firstOrCreate(
                        ['user_id' => $request->user_id, 'voucher_id' => $request->voucher_id],
                        ['usage_count' => 0]
                    );
                    $userVoucher->increment('usage_count');
                }
            }

            $membership = Membership::firstOrCreate(
                ['user_id' => $request->user_id],
                ['total_spent' => 0, 'rank_id' => null, 'points' => 0]
            );

            if ($request->has('use_points') && $request->use_points > 0) {
                $usePoints = (int) $request->use_points;
                if ($usePoints > $membership->points) {
                    return response()->json(['message' => 'Điểm không đủ'], 400);
                }
                $membership->decrement('points', $usePoints);
                $total_price -= $usePoints;

                Point_History::create([
                    'membership_id' => $membership->id,
                    'points' => -$usePoints,
                    'type' => 'trừ điểm',
                ]);
            }

            $ticket->update(['total_price' => $total_price]);
            $membership->increment('total_spent', $total_price);

            $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                ->orderBy('total_spent', 'desc')
                ->first() ?? Rank::orderBy('total_spent', 'asc')->first();

            if ($rank) {
                $membership->rank_id = $rank->id;
                $membership->save();
            }

            $pointsDifference = ($newSeatsCount - $oldSeatsCount) * 2500;
            if ($pointsDifference != 0) {
                $membership->increment('points', $pointsDifference);
                Point_History::create([
                    'membership_id' => $membership->id,
                    'points' => $pointsDifference,
                    'type' => $pointsDifference > 0 ? 'Tích Điểm' : 'Trừ Điểm',
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Cập nhật vé thành công!',
                'ticket' => $ticket,
                'earned_points' => $pointsDifference,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cập nhật vé thất bại!',
                'error' => $th->getMessage()
            ], 500);
        }
    }



    // Xóa ticket
    // public function destroy($id)
    // {
    //     $ticket = Ticket::find($id);
    //     if (!$ticket) {
    //         return response()->json(['message' => 'Ticket not found'], 404);
    //     }

    //     $ticket->delete();
    //     return response()->json(['message' => 'Ticket deleted'], 200);
    // }
}
