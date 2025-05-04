<?php

namespace App\Http\Controllers\API;

use App\Events\ChangeSeat;
use App\Events\SeatRelease;
use App\Events\SeatHold;
use App\Events\SeatStatusChange;
use App\Http\Controllers\Controller;
use App\Jobs\ReleaseSeatHoldJob;
use App\Jobs\BroadcastSeatStatusChange;
use App\Models\SeatShowtime;
use App\Models\Showtime;
use App\Models\SeatTemplate;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;



class ChooseSeatController extends Controller
{
    public function show(string $slug)
    {
        // Lấy thông tin suất chiếu
        $showtime = Showtime::with(['room.cinema', 'room', 'movieVersion', 'movie', 'seats' => function ($query) {
            $query->withPivot(['status', 'price', 'user_id', 'hold_expires_at']);
        }])->where('slug', $slug)
            ->where('is_active', 1)
            ->first();

        // Kiểm tra nếu không tìm thấy suất chiếu
        if (!$showtime) {
            return response()->json(['error' => 'Suất chiếu không tồn tại.'], 404);
        }

        // Kiểm tra quyền và thời gian đặt vé
        if (Auth::user() && Auth::user()->role !== 'admin' && $showtime->start_time <= now()->addMinutes(10)) {
            return response()->json(['error' => 'Đã hết thời gian đặt vé.'], 403);
        }

        // Lấy ma trận ghế từ SeatTemplate
        $matrixSeat = SeatTemplate::getMatrixById($showtime->room->seatTemplate->matrix_id);

        // Lấy danh sách ghế cho suất chiếu
        $seats = $showtime->seats;

        // Khởi tạo mảng seatMap 
        $seatMap = [];

        // Duyệt qua tất cả các ghế và nhóm chúng theo hàng 
        foreach ($seats as $seat) {
            // Kiểm tra nếu hàng chưa tồn tại trong seatMap
            if (!isset($seatMap[$seat->coordinates_y])) {
                $seatMap[$seat->coordinates_y] = [
                    'row' => $seat->coordinates_y,
                    'seats' => []
                ];
            }

            // Thêm ghế vào danh sách seats của hàng
            $seatMap[$seat->coordinates_y]['seats'][] = [
                'id' => $seat->id,
                'room_id' => $seat->room_id,
                'type_seat_id' => $seat->type_seat_id,
                'coordinates_x' => $seat->coordinates_x,
                'coordinates_y' => $seat->coordinates_y,
                'name' => $seat->name,
                'is_active' => $seat->is_active,
                'created_at' => $seat->created_at,
                'updated_at' => $seat->updated_at,
                'pivot' => [
                    'showtime_id' => $seat->pivot->showtime_id,
                    'seat_id' => $seat->pivot->seat_id,
                    'status' => $seat->pivot->status,
                    'price' => $seat->pivot->price,
                    'user_id' => $seat->pivot->user_id,
                    'hold_expires_at' => $seat->pivot->hold_expires_at,
                    'created_at' => $seat->pivot->created_at,
                    'updated_at' => $seat->pivot->updated_at,
                ]
            ];
        }

        // Chuyển seatMap thành mảng 
        $seatMap = array_values($seatMap);

        // Trả về dữ liệu JSON với thông tin suất chiếu, ma trận ghế và seatMap
        return response()->json([
            'showtime' => $showtime,
            'matrixSeat' => $matrixSeat,
            'seatMap' => $seatMap,
        ]);
    }

    public function saveInformation(Request $request, $showtimeId)
    {
        $seatIds = explode(',', $request->seatId);
        $userId = Auth::id();
        $slug = Showtime::where('id', $showtimeId)->where('is_active', '1')->pluck('slug')->first();

        if (!$slug) {
            return response()->json(['error' => 'Suất chiếu không tồn tại.'], 404);
        }

        $seatShowtimes = SeatShowtime::whereIn('seat_id', $seatIds)
            ->where('showtime_id', $showtimeId)
            ->get();

        foreach ($seatShowtimes as $seatShowtime) {
            if ($seatShowtime->hold_expires_at < now() || $seatShowtime->user_id != $userId || $seatShowtime->status != 'hold') {
                return response()->json(['error' => 'Ghế đã có người khác giữ hoặc ghế đã bán.'], 409);
            }
        }

        session()->put("checkout_data.$showtimeId", [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
            'selected_seats_name' => $request->selected_seats_name,
            'total_price' => $request->total_price,
            'remainingSeconds' => $request->remainingSeconds,
        ]);

        return response()->json(['message' => 'Thông tin giữ ghế đã được lưu.']);
    }

    public function updateSeat(Request $request)
    {
        try {
            $seatId = $request->seat_id;
            $showtimeId = $request->showtime_id;
            $action = $request->action;
            $seatName = $request->seat_name;
            $userId = auth()->id();

            if (!$userId) {
                return response()->json(['error' => 'Không xác định được người dùng.'], 400);
            }
            $newStatus = ($action === 'hold') ? 'hold' : 'available';


            $seatShowtime = SeatShowtime::join('seats', 'seats.id', '=', 'seat_showtimes.seat_id')
                ->where('seat_showtimes.seat_id', $seatId)
                ->where('seat_showtimes.showtime_id', $showtimeId)
                ->where('seats.is_active', 1)
                ->select('seat_showtimes.*')
                ->lockForUpdate()
                ->first();

            // Kiểm tra nếu ghế không tồn tại hoặc bị vô hiệu hóa
            if (!$seatShowtime) {
                return response()->json(['error' => 'Ghế không tồn tại hoặc đã bị vô hiệu hóa.'], 404);
            }

            //  Kiểm tra nếu ghế đã hết thời gian giữ
            if ($seatShowtime->status === 'hold' && $seatShowtime->hold_expires_at <= now()) {
                // Cập nhật lại trạng thái của ghế nếu thời gian giữ đã hết
                DB::table('seat_showtimes')
                    ->where('seat_id', $seatId)
                    ->where('showtime_id', $showtimeId)
                    ->update([
                        'status' => 'available',
                        'user_id' => null,
                        'hold_expires_at' => null,
                    ]);

                // Gửi sự kiện realtime để frontend cập nhật UI
                broadcast(new SeatStatusChange($seatId, $showtimeId, 'available', auth()->id()))->toOthers();

                return response()->json(['message' => 'Ghế đã hết thời gian giữ và chuyển sang trạng thái có sẵn.']);
            }

            //  Kiểm tra nếu ghế đã bị giữ bởi người khác
            if ($action === 'hold' && $seatShowtime->status === 'hold' && $seatShowtime->user_id !== $userId) {
                return response()->json([
                    'message' => "Ghế $seatName đã được giữ bởi người khác.",
                    'seat_status' => $seatShowtime->status,
                    'hold_expires_at' => $seatShowtime->hold_expires_at
                ], 409);
            }

            //  Xác định thời gian hết hạn giữ ghế (10 phút)
            $holdExpiresAt = ($action === 'hold') ? now()->addMinutes(10) : null;

            DB::transaction(function () use ($seatShowtime, $seatId, $showtimeId, $userId, $action, $holdExpiresAt) {
                if ($action === 'hold' && $seatShowtime->status === 'available') {
                    DB::table('seat_showtimes')
                        ->where('seat_id', $seatId)
                        ->where('showtime_id', $showtimeId)
                        ->update([
                            'status' => 'hold',
                            'user_id' => $userId,
                            'hold_expires_at' => $holdExpiresAt,
                        ]);
                    dispatch(new BroadcastSeatStatusChange($seatId, $showtimeId, "hold", $userId));
                    dispatch(new ReleaseSeatHoldJob($seatId, $showtimeId))->delay(now()->addMinutes(10));
                } elseif ($action === 'release' && $seatShowtime->status === 'hold' && $seatShowtime->user_id === $userId) {
                    DB::table('seat_showtimes')
                        ->where('seat_id', $seatId)
                        ->where('showtime_id', $showtimeId)
                        ->update([
                            'status' => 'available',
                            'user_id' => null,
                            'hold_expires_at' => null,
                        ]);
                    dispatch(new BroadcastSeatStatusChange($seatId, $showtimeId, "available", $userId));
                }
            });

            $updatedSeat = SeatShowtime::where('seat_id', $seatId)
                ->where('showtime_id', $showtimeId)
                ->first();

            return response()->json([
                'message' => 'Cập nhật trạng thái ghế thành công.',
                'seat' => $updatedSeat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Có lỗi xảy ra khi cập nhật trạng thái ghế.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    public function getUserHoldSeats(string $slug)
    {
        // Lấy thông tin user
        $userId = Auth::id();

        if (!$userId) {
            return response()->json(['error' => 'Không xác định được người dùng.'], 401);
        }

        // Lấy suất chiếu theo slug
        $showtime = Showtime::where('slug', $slug)
            ->where('is_active', 1)
            ->with(['movie', 'room.cinema']) // Lấy cả movie và cinema
            ->first();

        if (!$showtime) {
            return response()->json(['error' => 'Suất chiếu không tồn tại.'], 404);
        }

        // Lấy danh sách ghế đang được giữ kèm theo toàn bộ thông tin của ghế
        $holdSeats = SeatShowtime::where('showtime_id', $showtime->id)
            ->where('user_id', $userId)
            ->where('status', 'hold')
            ->with('seat') // Load đầy đủ thông tin của ghế
            ->get();

        return response()->json([
            'showtime' => $showtime,
            'holdSeats' => $holdSeats
        ]);
    }
}
