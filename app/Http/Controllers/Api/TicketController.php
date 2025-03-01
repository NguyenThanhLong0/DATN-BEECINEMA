<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\PointHistory;
use App\Models\Rank;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Ticket_combo;
use App\Models\Ticket_Seat;
use App\Models\User;
use App\Models\UserVoucher;
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

    // Tạo mới ticket
    public function store(Request $request)
    {
        // Lấy user_id từ token
        $user_id = Auth()->id();

        // Validation
        $validatedData = Validator::make($request->all(), [
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
            $ticket = Ticket::create(array_merge(
                $request->only([
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
                ]),
                ['user_id' => $user_id, 'total_price' => 0]
            ));

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
                    $discount = ($voucher->type == 'percent')
                        ? ($total_price * $voucher->discount) / 100
                        : min($voucher->discount, $total_price);
                    $total_price -= $discount;
                    //lưu giá trị discount vào ticket
                    $ticket->update([
                        'voucher_discount' => $discount,
                        'voucher_code' => $voucher->code
                    ]);
                    $userVoucher = UserVoucher::firstOrCreate(
                        ['user_id' => $user_id, 'voucher_id' => $request->voucher_id],
                        ['usage_count' => 0]
                    );
                    $userVoucher->increment('usage_count');
                }
            }

            // Xử lý Membership
            $user = User::findOrFail($user_id);
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

                PointHistory::create([
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
            PointHistory::create([
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
            //lấy ra sô lượng ghế đã đặt từ trước
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
            //Cập nhật danh sách combo 
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
            //Cập nhật danh sách ghế
            Ticket_Seat::where('ticket_id', $id)->delete(); // xoá các ghế cũ
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
            //Kiểm tra xem có áp dụng voucher hay k
            if ($request->voucher_id) {
                $voucher = Voucher::find($request->voucher_id);
                if ($voucher) {
                    $discount = ($voucher->type == '1')
                        ? ($total_price * $voucher->discount) / 100
                        : min($voucher->discount, $total_price);
                    $total_price -= $discount;
                    //lưu giá trị discount vào ticket
                    $ticket->update([
                        'voucher_discount' => $discount,
                        'voucher_code' => $voucher->code
                    ]);
                    $userVoucher = UserVoucher::firstOrCreate(
                        ['user_id' => $request->user_id, 'voucher_id' => $request->voucher_id],
                        ['usage_count' => 0]
                    );
                    $userVoucher->increment('usage_count');
                }
            }
            //kiểm tra thành viên
            $membership = Membership::firstOrCreate(
                ['user_id' => $request->user_id],
                ['total_spent' => 0, 'rank_id' => null, 'points' => 0]
            );
            //Trừ điểm của họ khi họ muốn dùng điểm để thanh toán
            if ($request->has('use_points') && $request->use_points > 0) {
                $usePoints = (int) $request->use_points;
                if ($usePoints > $membership->points) {
                    return response()->json(['message' => 'Điểm không đủ'], 400);
                }
                $membership->decrement('points', $usePoints);
                $total_price -= $usePoints;
                //Lưu lịch sử khi người dùng tiêu điểm "trừ điểm"
                PointHistory::create([
                    'membership_id' => $membership->id,
                    'points' => -$usePoints,
                    'type' => 'trừ điểm',
                ]);
            }

            $ticket->update(['total_price' => $total_price]); //cập nhật lại tổng tiền vé
            $membership->increment('total_spent', $total_price); //cập nhật lại số tiền mà khách đa chi tiêu
            // cập nhật rank của thành viên dựa trên số tiền khách đã chi tiêu
            $rank = Rank::where('total_spent', '<=', $membership->total_spent)
                ->orderBy('total_spent', 'desc')
                ->first() ?? Rank::orderBy('total_spent', 'asc')->first();
            //nếu tìm thấy rank phù hợp thì cập nhật lại rank cho họ
            if ($rank) {
                $membership->rank_id = $rank->id;
                $membership->save();
            }
            //Tính điểm theo số lượng ghế chênh lệch khi tăng hoặc giảm số ghế cập nhật lại vé
            $pointsDifference = ($newSeatsCount - $oldSeatsCount) * 2500;
            if ($pointsDifference != 0) {
                $membership->increment('points', $pointsDifference);
                PointHistory::create([
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
    // lịch sử đặt vé của người dùng
    public function getBookingHistory(Request $request)
    {
        try {
            // Lấy user_id từ token
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $user_id = $user->id;

            // Lấy danh sách vé của người dùng
            $tickets = Ticket::where('user_id', $user_id)
                ->with([
                    'seats' => function ($query) {
                        $query->select('ticket_id', 'seat_id', 'price')
                            ->with('seat:id,name');
                    },
                    'combos' => function ($query) {
                        $query->select('ticket_id', 'combo_id', 'price', 'quantity')
                            ->with('combo:id,name');
                    },
                    'movie:id,name,duration,img_thumbnail', //lấy ra tên và thời lượng phim
                    'room:id,name',
                    'showtime:id,start_time,end_time',
                    'voucher:id,code,type,discount',
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($ticket) {
                // Tính tổng tiền ghế
                $ticket->total_seat_price = $ticket->seats->sum('price');

                // Tính tổng tiền combo
                $totalComboPrice = 0;
                foreach ($ticket->combos as $combo) {
                    $totalComboPrice += $combo->price * $combo->quantity;
                }
                
                $ticket->total_combo_price = $totalComboPrice;

                return $ticket;
            });

            return response()->json([
                'message' => 'Lịch sử đặt vé',
                'data' => $tickets,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy lịch sử đặt vé!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
