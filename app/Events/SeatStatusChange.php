<?php
namespace App\Events;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatStatusChange implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $seatId;
    public $showtimeId;
    public $status;
    public $userId;

    public function __construct($seatId, $showtimeId, $status, $userId)
    {
        $this->seatId = $seatId;
        $this->showtimeId = $showtimeId;
        $this->status = $status;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new Channel('showtime.'.$this->showtimeId);
    }

    public function broadcastAs()
    {
        return 'seatStatusChange';
    }
}
