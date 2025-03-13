<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Ticket_Combo;
use App\Models\Combo;
use App\Models\Movie;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function revenueByCombo(Request $request)
    {
        $query = Ticket_Combo::query()
            ->join('tickets', 'ticket_combos.ticket_id', '=', 'tickets.id')
            ->join('combos', 'ticket_combos.combo_id', '=', 'combos.id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->selectRaw('ticket_combos.combo_id, combos.name as combo_name, SUM(ticket_combos.quantity) as total_quantity, SUM(ticket_combos.price * ticket_combos.quantity) as total_revenue');

        // Lọc theo ngày
        if ($request->query('from_date') && $request->query('to_date')) {
            $query->whereBetween('tickets.created_at', [$request->query('from_date'), $request->query('to_date')]);
        }

        // Lọc theo tháng và năm
        if ($request->query('month') && $request->query('year')) {
            $query->whereMonth('tickets.created_at', $request->query('month'))
                  ->whereYear('tickets.created_at', $request->query('year'));
        }

        // Lọc theo rạp chiếu (cinema)
        if ($request->query('cinema_id')) {
            $query->where('tickets.cinema_id', $request->query('cinema_id'));
        }

        // Lọc theo chi nhánh (branch)
        if ($request->query('branch_id')) {
            $query->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                  ->where('cinemas.branch_id', $request->query('branch_id'));
        }

        // Lọc theo combo
        if ($request->query('combo_id')) {
            $query->where('ticket_combos.combo_id', $request->query('combo_id'));
        }

        // Nhóm theo combo_id
        $ComboRevenue = $query->groupBy('ticket_combos.combo_id', 'combos.name')->get();
        $totalAllRevenue = $ComboRevenue->sum('total_revenue');

        return response()->json(["data" => $ComboRevenue, "total_all_revenue" => $totalAllRevenue]);
    }

    public function revenueByMovie(Request $request)
    {
        $query = Ticket::query()
            ->leftJoin('movies', 'tickets.movie_id', '=', 'movies.id')
            ->leftJoin('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->leftJoin('point_histories', function ($join) {
                $join->on('tickets.id', '=', 'point_histories.ticket_id')
                    ->where('point_histories.type', '=', 'Dùng điểm');
            })
            ->where('tickets.status', '!=', 'đã hủy')
            ->selectRaw('tickets.movie_id, movies.name as movie_name, 
                SUM(ticket_seats.price) - COALESCE(SUM(tickets.voucher_discount), 0) - COALESCE(SUM(point_histories.points), 0) as total_revenue,
                COUNT(DISTINCT tickets.id) as total_tickets');

        // Lọc theo ngày
        if ($request->query('from_date') && $request->query('to_date')) {
            $query->whereBetween('tickets.created_at', [$request->query('from_date'), $request->query('to_date')]);
        }

        // Lọc theo tháng/năm
        if ($request->query('month') && $request->query('year')) {
            $query->whereMonth('tickets.created_at', $request->query('month'))
                  ->whereYear('tickets.created_at', $request->query('year'));
        }

        // Lọc theo rạp
        if ($request->query('cinema_id')) {
            $query->where('tickets.cinema_id', $request->query('cinema_id'));
        }

        // Lọc theo chi nhánh
        if ($request->query('branch_id')) {
            $query->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                  ->where('cinemas.branch_id', $request->query('branch_id'));
        }

        // Lọc theo phim
        if ($request->query('movie_id')) {
            $query->where('tickets.movie_id', $request->query('movie_id'));
        }

        $moviesRevenue = $query->groupBy('tickets.movie_id', 'movies.name')->get();
        $totalAllRevenue = $moviesRevenue->sum('total_revenue');

        return response()->json(["data" => $moviesRevenue, "total_all_revenue" => $totalAllRevenue]);
    }
    public function totalRevenue(Request $request)
{
    // Query doanh thu từ phim
    $movieQuery = Ticket::query()
        ->leftJoin('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
        ->leftJoin('point_histories', function ($join) {
            $join->on('tickets.id', '=', 'point_histories.ticket_id')
                ->where('point_histories.type', '=', 'Dùng điểm');
        })
        ->where('tickets.status', '!=', 'đã hủy')
        ->selectRaw('
            SUM(ticket_seats.price) 
            - COALESCE(SUM(tickets.voucher_discount), 0) 
            - COALESCE(SUM(point_histories.points), 0) as total_movie_revenue,
            COUNT(DISTINCT tickets.id) as total_tickets
        ');

    // Query doanh thu từ combo
    $comboQuery = Ticket_Combo::query()
        ->join('tickets', 'ticket_combos.ticket_id', '=', 'tickets.id')
        ->where('tickets.status', '!=', 'đã hủy')
        ->selectRaw('SUM(ticket_combos.quantity * ticket_combos.price) as total_combo_revenue');

        // Bộ lọc chung cho cả hai query
        if ($request->filled('from_date')) {
            $movieQuery->where('tickets.created_at', '>=', $request->from_date);
            $comboQuery->where('tickets.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $movieQuery->where('tickets.created_at', '<=', $request->to_date);
            $comboQuery->where('tickets.created_at', '<=', $request->to_date);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $movieQuery->whereMonth('tickets.created_at', $request->month)
                       ->whereYear('tickets.created_at', $request->year);
            $comboQuery->whereMonth('tickets.created_at', $request->month)
                       ->whereYear('tickets.created_at', $request->year);
        }

        if ($request->filled('cinema_id')) {
            $movieQuery->where('tickets.cinema_id', $request->cinema_id);
            $comboQuery->where('tickets.cinema_id', $request->cinema_id);
        }

        if ($request->filled('branch_id')) {
            $movieQuery->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                       ->where('cinemas.branch_id', $request->branch_id);
            $comboQuery->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                       ->where('cinemas.branch_id', $request->branch_id);
        }

    // Lấy doanh thu tổng từ phim và combo
    $totalMovieRevenue = $movieQuery->value('total_movie_revenue') ?? 0;
    $totalComboRevenue = $comboQuery->value('total_combo_revenue') ?? 0;
    $totalRevenue = $totalMovieRevenue + $totalComboRevenue;

    // Trả về kết quả gồm cả tổng doanh thu từ phim, combo và tổng tất cả
    return response()->json([
        'total_movie_revenue' => $totalMovieRevenue,
        'total_combo_revenue' => $totalComboRevenue,
        'total_revenue' => $totalRevenue
    ]);
}
}
