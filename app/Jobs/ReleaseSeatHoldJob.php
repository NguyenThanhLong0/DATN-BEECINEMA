<?php

namespace App\Jobs;

use App\Events\SeatStatusChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReleaseSeatHoldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $seatId;
    public $showtimeId;

    public function __construct(int $seatId, int $showtimeId)
    {
        $this->seatId = $seatId;
        $this->showtimeId = $showtimeId;
    }

    public function handle()
    {
        DB::transaction(function () {
            $now = Carbon::now('Asia/Ho_Chi_Minh');

            $seat = DB::table('seat_showtimes')
                ->where('seat_id', $this->seatId)
                ->where('showtime_id', $this->showtimeId)
                ->first();

            if (!$seat) {
                Log::warning("Không tìm thấy ghế để giải phóng", [
                    'seat_id' => $this->seatId,
                    'showtime_id' => $this->showtimeId
                ]);
                return;
            }

            if ($seat->status === 'hold') {
                $updatedRows = DB::table('seat_showtimes')
                    ->where('seat_id', $seat->seat_id)
                    ->where('showtime_id', $seat->showtime_id)
                    ->update([
                        'status' => 'available',
                        'user_id' => null,
                        'hold_expires_at' => null,
                    ]);

                if ($updatedRows > 0) {
                    Log::info("Đã giải phóng ghế thành công", [
                        'seat_id' => $this->seatId,
                        'showtime_id' => $this->showtimeId
                    ]);
                    
                    // Gửi sự kiện cập nhật trạng thái ghế
                    broadcast(new SeatStatusChange($this->seatId, $this->showtimeId, "available", null))->toOthers();
                } else {
                    Log::error("Cập nhật trạng thái ghế thất bại", [
                        'seat_id' => $this->seatId,
                        'showtime_id' => $this->showtimeId
                    ]);
                }
            }
        });
    }
}
