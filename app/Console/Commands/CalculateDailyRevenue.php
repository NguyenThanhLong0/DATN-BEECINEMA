<?php

namespace App\Console\Commands;

use App\Models\RevenueReport;
use App\Models\Ticket;
use App\Models\Ticket_Combo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateDailyRevenue extends Command
{
    protected $signature = 'revenue:calculate-daily';
    protected $description = 'Tính tổng doanh thu hàng ngày và lưu vào bảng revenue_reports';

    public function handle()
    {
        $date = Carbon::today(); // Ngày hiện tại

        // Tính tổng doanh thu từ vé xem phim
        $totalMovieRevenue = Ticket::whereDate('tickets.created_at', $date)
            ->leftJoin('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->leftJoin('point_histories', function ($join) {
                $join->on('tickets.id', '=', 'point_histories.ticket_id')
                    ->where('point_histories.type', '=', 'Dùng điểm');
            })
            ->where('tickets.status', '!=', 'đã hủy')
            ->selectRaw('
                SUM(ticket_seats.price) 
                - COALESCE(SUM(tickets.voucher_discount), 0) 
                - COALESCE(SUM(point_histories.points), 0) as total_movie_revenue
            ')
            ->value('total_movie_revenue') ?? 0;

        // Tính tổng doanh thu từ combo
        $totalComboRevenue = Ticket_Combo::join('tickets', 'ticket_combos.ticket_id', '=', 'tickets.id')
            ->whereDate('tickets.created_at', $date)
            ->where('tickets.status', '!=', 'đã hủy')
            ->sum(DB::raw('ticket_combos.price * ticket_combos.quantity'));

        // Tổng doanh thu
        $totalRevenue = ($totalMovieRevenue ?? 0) + ($totalComboRevenue ?? 0);

        // Lưu vào bảng revenue_reports
        RevenueReport::updateOrCreate(
            ['date' => $date->toDateString()],
            [
                'total_movie_revenue' => $totalMovieRevenue,
                'total_combo_revenue' => $totalComboRevenue,
                'total_revenue' => $totalRevenue
            ]
        );

        $this->info('Doanh thu ngày ' . $date->toDateString() . ' đã được cập nhật.');
    }
}
