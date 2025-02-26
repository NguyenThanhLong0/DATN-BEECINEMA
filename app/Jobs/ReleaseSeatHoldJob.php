<?php

namespace App\Jobs;

use App\Events\SeatStatusChange;
use App\Events\SeatRelease;
use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReleaseSeatHoldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $seatIds;
    public $showtimeId;

    public $voucherCode;

    /**
     * Create a new job instance.
     *
     * @param array $seatIds
     * @param mixed $showtimeId
     * @return void
     */
    public function __construct(array $seatIds, $showtimeId, $voucherCode = null) //mấy nữa mở null cái này ra
    {
        $this->seatIds = $seatIds;
        $this->showtimeId = $showtimeId;
        $this->voucherCode = $voucherCode;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Gói trong transaction để đảm bảo tính toàn vẹn dữ liệu
        DB::transaction(function () {
            // Lấy các ghế cần giải phóng và khóa chúng lại
            $seats = DB::table('seat_showtimes')
                ->whereIn('seat_id', $this->seatIds)
                ->where('showtime_id', $this->showtimeId)
                ->lockForUpdate()
                ->get();

            $now = Carbon::now('Asia/Ho_Chi_Minh');
            $expiredSeatIds = [];

            foreach ($seats as $seat) {
                // Chỉ giải phóng nếu thời gian hold đã hết và ghế vẫn ở trạng thái 'hold'
                if ($seat->status === 'hold' && $seat->hold_expires_at <= $now) {
                    DB::table('seat_showtimes')
                        ->where('seat_id', $seat->seat_id)
                        ->where('showtime_id', $this->showtimeId)
                        ->update([
                            'user_id' => null,
                            'status' => 'available', // Trạng thái ghế trở lại 'available'
                            'hold_expires_at' => null,
                        ]);

                    $expiredSeatIds[] = $seat->seat_id;

                    // Phát sự kiện giải phóng ghế
                    // broadcast(new SeatStatusChange($seat->seat_id, $this->showtimeId, 'available'))->toOthers();
                    broadcast(new SeatStatusChange($seat->seat_id, $this->showtimeId, 'available', auth()->id()))->toOthers();

                    // event(new SeatStatusChange($seat->seat_id, $this->showtimeId, 'available'));
                    // event(new SeatRelease($seat->seat_id, $this->showtimeId));
                }
            }

            if ($this->voucherCode != null) {
                $voucher = Voucher::where('code', $this->voucherCode)->first();
                if ($voucher) {
                    $voucher->increment('quantity');
                }
            }

            // Nếu không có ghế nào bị giải phóng, không làm gì cả
            if (empty($expiredSeatIds)) {
                return;
            }

            // Ghi log hoặc xử lý thêm nếu cần (nếu cần ghi log)
            // Log::info('Seats released: ' . implode(', ', $expiredSeatIds));
        });
    }
}
