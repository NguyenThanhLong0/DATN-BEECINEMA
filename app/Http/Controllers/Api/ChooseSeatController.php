<?php

namespace App\Http\Controllers\API;

use App\Events\ChangeSeat;
use App\Events\SeatRelease;
use App\Events\SeatHold;
use App\Events\SeatStatusChange;
use App\Http\Controllers\Controller;
use App\Jobs\ReleaseSeatHoldJob;
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
        $showtime = Showtime::with(['room.cinema', 'room', 'movieVersion', 'movie', 'seats'])
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->first();

        if (!$showtime) {
            return response()->json(['error' => 'Suất chiếu không tồn tại.'], 404);
        }

        if (Auth::user() && Auth::user()->role !== 'admin' && $showtime->start_time <= now()->addMinutes(10)) {
            return response()->json(['error' => 'Đã hết thời gian đặt vé.'], 403);
        }

        $matrixSeat = SeatTemplate::getMatrixById($showtime->room->seatTemplate->matrix_id);
        $seats = $showtime->seats;

        $seatMap = [];
        foreach ($seats as $seat) {
            $seatMap[$seat->coordinates_y][$seat->coordinates_x] = $seat;
        }

        return response()->json([
            'showtime' => $showtime,
            'matrixSeat' => $matrixSeat,
            'seatMap' => $seatMap
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

    // ko có sk
    // public function updateSeat(Request $request)
    // {
    //     try {
    //         $seatId = $request->seat_id;
    //         $showtimeId = $request->showtime_id;
    //         $action = $request->action;
    //         $userId = Auth::id();

    //         Log::info("🔴 Broadcasting seat event", [
    //             'seat_id' => $seatId,
    //             'showtime_id' => $showtimeId,
    //             'status' => $action
    //         ]);
    //         broadcast(new SeatStatusChange($seatId, $showtimeId, $action))->toOthers();

    //         // Lấy thông tin ghế + kiểm tra is_active
    //         $seatShowtime = SeatShowtime::join('seats', 'seats.id', '=', 'seat_showtimes.seat_id')
    //             ->where('seat_showtimes.seat_id', $seatId)
    //             ->where('seat_showtimes.showtime_id', $showtimeId)
    //             ->where('seats.is_active', true) // Chỉ lấy ghế đang hoạt động
    //             ->select('seat_showtimes.*') // Chỉ lấy dữ liệu từ bảng seat_showtimes
    //             ->lockForUpdate()
    //             ->first();

    //         // Kiểm tra nếu ghế không tồn tại hoặc bị vô hiệu hóa
    //         if (!$seatShowtime) {
    //             return response()->json(['error' => 'Ghế không tồn tại hoặc đã bị vô hiệu hóa.'], 404);
    //         }

    //         // 🚀 **THÊM KIỂM TRA: Nếu ghế đã bị giữ bởi người khác, từ chối request**
    //         if (
    //             $action === 'hold' &&
    //             $seatShowtime->status === 'hold' &&
    //             $seatShowtime->user_id !== null &&
    //             $seatShowtime->user_id != $userId
    //         ) {
    //             return response()->json([
    //                 'error' => 'Ghế này đã có người khác giữ. Vui lòng chọn ghế khác.',
    //             ], 409);
    //         }

    //         DB::transaction(function () use ($seatShowtime, $seatId, $showtimeId, $userId, $action) {
    //             Log::info("Before update:", $seatShowtime->toArray());

    //             if ($action === 'hold' && $seatShowtime->status === 'available') {
    //                 $seatShowtime->update([
    //                     'status' => 'hold',
    //                     'user_id' => $userId, // ✅ Giữ user_id khi giữ ghế
    //                     'hold_expires_at' => now()->addMinutes(10),
    //                 ]);

    //                 Log::info("After update:", $seatShowtime->fresh()->toArray());

    //                 event(new SeatHold($seatId, $showtimeId));
    //                 ReleaseSeatHoldJob::dispatch([$seatId], $showtimeId, null)->delay(now()->addMinutes(10));
    //             } elseif ($action === 'release' && $seatShowtime->status === 'hold' && $seatShowtime->user_id === $userId) {
    //                 $seatShowtime->update([
    //                     'status' => 'available',
    //                     'user_id' => null,
    //                     'hold_expires_at' => null,
    //                 ]);

    //                 event(new SeatRelease($seatId, $showtimeId));
    //             }
    //         });

    //         $updatedSeat = SeatShowtime::where('seat_id', $seatId)->where('showtime_id', $showtimeId)->first();

    //         return response()->json([
    //             'message' => 'Cập nhật trạng thái ghế thành công.',
    //             'seat' => $updatedSeat
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error("Lỗi cập nhật ghế: " . $e->getMessage());
    //         return response()->json(['error' => 'Có lỗi xảy ra khi cập nhật trạng thái ghế.'], 500);
    //     }
    // }

    //thêm sk front end
    // public function updateSeat(Request $request)
    // {
    //     try {
    //         $seatId = $request->seat_id;
    //         $showtimeId = $request->showtime_id;
    //         $action = $request->action;
    //         $userId = Auth::id();

    //         //  Phát sự kiện để frontend nhận realtime
    //         broadcast(new SeatStatusChange($seatId, $showtimeId, $action))->toOthers();

    //         // Kiểm tra nếu ghế đã bị giữ bởi người khác
    //         $seatShowtime = SeatShowtime::join('seats', 'seats.id', '=', 'seat_showtimes.seat_id')
    //             ->where('seat_showtimes.seat_id', $seatId)
    //             ->where('seat_showtimes.showtime_id', $showtimeId)
    //             ->where('seats.is_active', true)
    //             ->select('seat_showtimes.*')
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$seatShowtime) {
    //             return response()->json(['error' => 'Ghế không tồn tại hoặc đã bị vô hiệu hóa.'], 404);
    //         }

    //         if ($action === 'hold' && $seatShowtime->status === 'hold' && $seatShowtime->user_id !== $userId) {
    //             return response()->json([
    //                 'error' => 'Ghế này đã có người khác giữ. Vui lòng chọn ghế khác.',
    //             ], 409);
    //         }

    //         DB::transaction(function () use ($seatShowtime, $seatId, $showtimeId, $userId, $action) {
    //             if ($action === 'hold' && $seatShowtime->status === 'available') {
    //                 $seatShowtime->update([
    //                     'status' => 'hold',
    //                     'user_id' => $userId,
    //                     'hold_expires_at' => now()->addMinutes(10),
    //                 ]);
    //                 //  Gửi sự kiện Pusher để frontend cập nhật UI
    //                 broadcast(new SeatStatusChange($seatId, $showtimeId, 'hold'))->toOthers();
    //             } elseif ($action === 'release' && $seatShowtime->status === 'hold' && $seatShowtime->user_id === $userId) {
    //                 $seatShowtime->update([
    //                     'status' => 'available',
    //                     'user_id' => null,
    //                     'hold_expires_at' => null,
    //                 ]);
    //                 //  Gửi sự kiện khi ghế được thả ra
    //                 broadcast(new SeatStatusChange($seatId, $showtimeId, 'available'))->toOthers();
    //             }
    //         });

    //         $updatedSeat = SeatShowtime::where('seat_id', $seatId)->where('showtime_id', $showtimeId)->first();

    //         return response()->json([
    //             'message' => 'Cập nhật trạng thái ghế thành công.',
    //             'seat' => $updatedSeat
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error("Lỗi cập nhật ghế: " . $e->getMessage());
    //         return response()->json(['error' => 'Có lỗi xảy ra khi cập nhật trạng thái ghế.'], 500);
    //     }
    // }

    //có session
    public function updateSeat(Request $request)
{
    try {
        $seatId = $request->seat_id;
        $showtimeId = $request->showtime_id;
        $action = $request->action; // 'hold' hoặc 'release'
        $userId = auth()->id();

        if (!$userId) {
            return response()->json(['error' => 'Không xác định được người dùng.'], 400);
        }

        //  Lấy trạng thái ghế mới nhất từ database
        $seatShowtime = SeatShowtime::join('seats', 'seats.id', '=', 'seat_showtimes.seat_id')
            ->where('seat_showtimes.seat_id', $seatId)
            ->where('seat_showtimes.showtime_id', $showtimeId)
            ->where('seats.is_active', 1)
            ->select('seat_showtimes.*') 
            ->lockForUpdate()
            ->first();

        if (!$seatShowtime) {
            return response()->json(['error' => 'Ghế không tồn tại hoặc đã bị vô hiệu hóa.'], 404);
        }

        //  Kiểm tra nếu ghế đã bị giữ bởi người khác
        if ($action === 'hold' && $seatShowtime->status === 'hold' && $seatShowtime->user_id !== $userId) {
            return response()->json([
                'error' => 'Ghế này đã có người khác giữ. Vui lòng chọn ghế khác.',
                'seat_status' => $seatShowtime->status,
                'hold_expires_at' => $seatShowtime->hold_expires_at
            ], 409);
        }

        //  Xác định thời gian hết hạn giữ ghế
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
                broadcast(new SeatStatusChange($seatId, $showtimeId, 'hold'))->toOthers();
            } elseif ($action === 'release' && $seatShowtime->status === 'hold' && $seatShowtime->user_id === $userId) {
                DB::table('seat_showtimes')
                    ->where('seat_id', $seatId)
                    ->where('showtime_id', $showtimeId)
                    ->update([
                        'status' => 'available',
                        'user_id' => null,
                        'hold_expires_at' => null,
                    ]);
                broadcast(new SeatStatusChange($seatId, $showtimeId, 'available'))->toOthers();
            }
        });

        //  Lấy lại dữ liệu ghế sau khi transaction hoàn tất
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

}
