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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    // Lấy danh sách tất cả tickets
    public function index()
    {
        return response()->json(Ticket::all(), 200);
    }

    // Lấy thông tin 1 ticket theo ID
    // public function show($id)
    // {
    //     $ticket = Ticket::find($id);
    //     if (!$ticket) {
    //         return response()->json(['message' => 'Ticket not found'], 404);
    //     }
    //     return response()->json($ticket, 200);
    // }

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
            // Lấy thông tin Membership của user
            $user = User::findOrFail($user_id);
            $membership = Membership::firstOrCreate(
                ['user_id' => $user->id],
                ['total_spent' => 0, 'rank_id' => null, 'points' => 0]
            );
            // Lấy rank hiện tại của user
            $currentRank = Rank::find($membership->rank_id)?->name ?? 'Member';
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
                    'expiry'
                ]),
                [
                    'user_id' => $user_id,
                    'total_price' => 0,
                    'rank_at_booking' => $currentRank,
                    'point' => 0,
                    'point_discount' => 0
                ]
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
            // $user = User::findOrFail($user_id);
            // $isFirstBooking = !Membership::where('user_id', $user->id)->exists(); // Kiểm tra lần đầu đặt vé không

            // $membership = Membership::firstOrCreate(
            //     ['user_id' => $user->id],
            //     ['total_spent' => 0, 'rank_id' => null, 'points' => 0]
            // );

            $usedPoints = 0;
            $pointDiscount = 0;
            // Xử lý trừ điểm nếu người dùng nhập số điểm muốn sử dụng
            if ($request->has('use_points') && $request->use_points > 0) {
                $usedPoints = (int) $request->use_points;

                // Kiểm tra điểm có đủ không
                if ($usedPoints > $membership->points) {
                    return response()->json(['message' => 'Điểm không đủ'], 400);
                }
                $pointDiscount = $usedPoints;
                // Trừ điểm và cập nhật lịch sử
                $membership->decrement('points', $usedPoints);
                $total_price -= $usedPoints;
                //lưu vào bảng Point_history
                PointHistory::create([
                    'membership_id' => $membership->id,
                    'points' => -$usedPoints,
                    'type' => 'trừ điểm',
                ]);
                $ticket->point = $usedPoints;
                $ticket->point_discount = $usedPoints;
                $ticket->save(); // Sử dụng save() thay vì update()
            }
            // Cập nhật total_price của ticket
            $ticket->update([
                'total_price' => max(0, $total_price), // Không cho giá trị âm
                'point' => $usedPoints, // Số điểm đã dùng
                'point_discount' => $pointDiscount // Giá trị quy đổi từ điểm
            ]);

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
            $pointUsed = 0;
            if ($request->has('use_points') && $request->use_points > 0) {
                $usePoints = (int) $request->use_points;
                if ($usePoints > $membership->points) {
                    return response()->json(['message' => 'Điểm không đủ'], 400);
                }
                $membership->decrement('points', $usePoints);
                $total_price -= $usePoints;
                $pointUsed = $usePoints;
                //Lưu lịch sử khi người dùng tiêu điểm "trừ điểm"
                PointHistory::create([
                    'membership_id' => $membership->id,
                    'points' => -$usePoints,
                    'type' => 'trừ điểm',
                ]);
            }


            // $ticket->update(['total_price' => $total_price]); 
            //cập nhật lại tổng tiền vé
            $ticket->total_price = $total_price;
            $ticket->point = $pointUsed;
            $ticket->point_discount = $pointUsed;
            $ticket->save();


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
                    'voucher:id,code,discount_type,discount_value',
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


    public function show($code)
    {
        // Tìm vé theo code
        $ticket = Ticket::where('code', $code)
            ->with(['user', 'cinema', 'room', 'movie', 'showtime', 'ticketSeats.seat', 'ticketCombos.combo.foods'])
            ->first();

        if (!$ticket) {
            return response()->json(['message' => 'Không tìm thấy vé'], 404);
        }

        // Tính tổng tiền combo
        $comboDetails = [];
        $totalComboPrice = 0;
        foreach ($ticket->ticketCombos as $ticketCombo) {
            $combo = $ticketCombo->combo;
            $foods = [];

            foreach ($combo->foods as $food) {
                $foods[] = [
                    'food_id' => $food->id,
                    'food_name' => $food->name,
                    'food_price' => $food->price,
                    'food_img' => $food->img_thumbnail,
                    'quantity' => $food->pivot->quantity, // Lấy số lượng từ bảng trung gian
                ];
            }

            $comboPrice = $combo->price * $ticketCombo->quantity;
            $totalComboPrice += $comboPrice;

            $comboDetails[] = [
                'combo_id' => $combo->id,
                'combo_name' => $combo->name,
                'img' => $combo->img_thumbnail,
                'quantity' => $ticketCombo->quantity,
                'price_per_unit' => $combo->price,
                'total_price' => $comboPrice,
                'foods' => $foods, // Thêm danh sách food vào combo
            ];
        }

        // Tính tổng tiền ghế
        $seatDetails = [];
        $totalSeatPrice = 0;
        foreach ($ticket->ticketSeats as $ticketSeat) {
            $totalSeatPrice += $ticketSeat->price;
            $seatDetails[] = [
                'seat_id' => $ticketSeat->seat->id,
                'seat_name' => $ticketSeat->seat->name,
                'price' => $ticketSeat->price,
            ];
        }

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'user' => [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                    'avata' => $ticket->user->avata,
                    'phone' => $ticket->user->phone,
                    'address' => $ticket->user->address,
                    'gender' => $ticket->user->gender,
                    'birthday' => $ticket->user->birthday,
                    'role' => $ticket->user->role,
                ],
                'cinema' => ['id' => $ticket->cinema->id, 'name' => $ticket->cinema->name, "address" => $ticket->cinema->address, 'branch' => optional($ticket->cinema->branch)->name],
                'room' => ['id' => $ticket->room->id, 'name' => $ticket->room->name],
                'movie' => ['id' => $ticket->movie->id, 'name' => $ticket->movie->name, 'img' => $ticket->movie->img_thumbnail, 'duration' => $ticket->movie->duration, 'age' => $ticket->movie->rating, 'category' => $ticket->movie->category],
                'showtime' => [
                    'id' => $ticket->showtime->id,
                    'format' => $ticket->showtime->format,
                    'start_time' => $ticket->showtime->start_time,
                    'end_time' => $ticket->showtime->end_time
                ],
                'voucher_code' => $ticket->voucher_code,
                'voucher_discount' => $ticket->voucher_discount,
                'payment_name' => $ticket->payment_name,
                'code' => $ticket->code,
                'status' => $ticket->status,
                'staff' => $ticket->staff,
                'expiry' => $ticket->expiry,
                'point' => $ticket->point,
                'point_discount' => $ticket->point_discount,
                'rank_at_booking' => $ticket->rank_at_booking,
                'total_price' => $ticket->total_price,
                'create_at' => $ticket->created_at,
                'update_at' => $ticket->update_at,
                'combos' => [
                    'details' => $comboDetails,
                    'total_combo_price' => $totalComboPrice,
                ],
                'seats' => [
                    'details' => $seatDetails,
                    'total_seat_price' => $totalSeatPrice,
                ],
            ],
        ], 200);
    }
    //lọc vé
    public function filter(Request $request)
        {
            
            // Log::info('Filter Request:', $request->all());
            try {
                $query = Ticket::query()
                    ->select([
                        'tickets.code',
                        'users.name as user_name',
                        'users.email as user_email',
                        'users.role as user_role',
                        'movies.img_thumbnail as movie_image',
                        'movies.name as movie_name',
                        'cinemas.name as cinema_name',
                        'rooms.name as room_name',
                        'tickets.total_price',
                        'tickets.status',
                        'tickets.payment_name',
                        'showtimes.start_time as start_time',
                        'showtimes.date as show_date',

                        'tickets.expiry',
                        'tickets.payment_name',
                        DB::raw('GROUP_CONCAT(seats.name ORDER BY seats.name ASC) as seat_names')
                    ])
                    ->join('users', 'tickets.user_id', '=', 'users.id')
                    ->join('movies', 'tickets.movie_id', '=', 'movies.id')
                    ->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                    ->join('rooms', 'tickets.room_id', '=', 'rooms.id')

                    ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
                    ->join('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id') // Bảng trung gian
                    ->join('seats', 'ticket_seats.seat_id', '=', 'seats.id') // Liên kết với bảng seats
                    ->groupBy('tickets.id');

                    // $query = Ticket::query();
                // Lọc theo branch_id nếu có
                if ($request->has('branch_id')) {
                    $query->where('cinemas.branch_id',  $request->branch_id);
                }

                // Lọc theo cinema_id nếu có
                if ($request->has('cinema_id')) {
                    $query->where('tickets.cinema_id',  $request->cinema_id);
                }

                // Lọc theo movie_id nếu có
                if ($request->has('movie_id')) {
                    $query->where('tickets.movie_id', $request->movie_id);
                }

                // Lọc theo ngày chiếu nếu có
                if ($request->has('date')) {
                    $query->whereDate('tickets.created_at', $request->date);
                }

                // Lọc theo trạng thái nếu có
                if ($request->has('status')) {
                    $query->where('tickets.status', $request->status);
                }

                // Lấy dữ liệu
                // Log::info('SQL Query:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
                $tickets = $query->get();
                // Log::info('Tickets count:', ['count' => $tickets->count()]);

                if ($tickets->isEmpty()) {
                    return response()->json(['message' => 'Không tìm thấy vé']);
                }
                // Trả về response
                return response()->json([
                    'success' => true,
                    'data' => $tickets
                ]);
                // dd($query->toSql(), $query->getBindings());
            } catch (\Exception $e) {
                // Xử lý lỗi và trả về JSON thông báo lỗi
                return response()->json([
                    'success' => false,
                    'message' => 'Đã có lỗi xảy ra khi lấy danh sách vé.',
                    'error' => $e->getMessage()
                ], 500);
            }


    }
}
