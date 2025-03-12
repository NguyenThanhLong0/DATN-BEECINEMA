<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ticket;
use Carbon\Carbon;

class UpdateTicketStatus extends Command
{
    protected $signature = 'tickets:update-status';
    protected $description = 'Cập nhật trạng thái vé khi phim đã chiếu xong';

    public function handle()
    {
        $expiredTickets = Ticket::where('expiry', '<=', Carbon::now())
        ->whereIn('status', ['Đã thanh toán', 'Đã xuất vé']) 
        ->update(['status' => 'Đã hết hạn']);

        $this->info("Đã cập nhật {$expiredTickets} vé.");
    }
}
