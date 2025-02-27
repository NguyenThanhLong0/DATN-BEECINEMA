<?php

namespace App\Jobs;

use App\Events\SeatStatusChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastSeatStatusChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $seatId, $showtimeId, $status, $userId;

    public function __construct($seatId, $showtimeId, $status, $userId)
    {
        $this->seatId = $seatId;
        $this->showtimeId = $showtimeId;
        $this->status = $status;
        $this->userId = $userId;
    }

    public function handle()
    {
            broadcast(new SeatStatusChange($this->seatId, $this->showtimeId, $this->status, $this->userId))->toOthers();
    }
}
