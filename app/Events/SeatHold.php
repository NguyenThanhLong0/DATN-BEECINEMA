<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SeatHold implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $seatId;
    public $showtimeId;

    /**
     * Tạo event với các tham số cần thiết
     */
    public function __construct($seatId, $showtimeId)
    {
        $this->seatId = $seatId;
        $this->showtimeId = $showtimeId;
    }

    /**
     * Định nghĩa kênh để phát sự kiện.
     */
    public function broadcastOn()
    {
        // Sự kiện sẽ được phát trên kênh showtime với id cụ thể
        return new Channel('showtime.' . $this->showtimeId);
    }
}



