<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SeatStatusChange  implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $seatId;
    public $showtimeId;
    public $status; // Trạng thái ghế (hold, available, sold)
    public $userId; // Id của người đặt vé

    /**
     * Tạo event với các tham số cần thiết
     */
    public function __construct($seatId, $showtimeId, $status ,$userId )   
    {
        $this->seatId = $seatId;
        $this->showtimeId = $showtimeId;
        $this->status = $status;
        $this->userId = $userId;
    }


    /**
     * Định nghĩa kênh để phát sự kiện.
     */
    public function broadcastOn()
    {
        // Sự kiện sẽ được phát trên kênh showtime với id cụ thể
        return new Channel('showtime.' . $this->showtimeId);
    }
    
    public function broadcastAs()
    {
        return 'SeatStatusChange';
    }
}



