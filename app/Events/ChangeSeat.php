<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeSeat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $seat;
    public $showtimeId;
    public function __construct($seat, $showtimeId)
    {
        $this->seat = $seat;
        $this->showtimeId = $showtimeId;
    }


    public function broadcastOn()
    {
        // Sự kiện sẽ được phát trên kênh showtime với id cụ thể
        return new Channel('showtime.' . $this->showtimeId);
    }
}
