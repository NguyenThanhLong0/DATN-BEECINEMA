<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Ticket_Combo;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function revenueByCombo(Request $request)
{
    $query = Ticket_Combo::query()
        ->join('tickets', 'ticket_combos.ticket_id', '=', 'tickets.id')
        ->where('tickets.status', '!=', 'đã hủy')
        ->selectRaw('ticket_combos.combo_id, SUM(ticket_combos.quantity) as total_quantity, SUM(ticket_combos.price * ticket_combos.quantity) as total_revenue');

    // Lọc theo ngày
    if ($request->filled('from_date') && $request->filled('to_date')) {
        $query->whereBetween('tickets.created_at', [$request->input('from_date'), $request->input('to_date')]);
    }

    // Lọc theo tháng và năm
    if ($request->filled('month') && $request->filled('year')) {
        $query->whereMonth('tickets.created_at', $request->input('month'))
              ->whereYear('tickets.created_at', $request->input('year'));
    }

    // Lọc theo rạp chiếu (cinema)
    if ($request->filled('cinema_id')) {
        $query->where('tickets.cinema_id', $request->input('cinema_id'));
    }

    // Lọc theo chi nhánh (branch)
    if ($request->filled('branch_id')) {
        $query->where('cinemas.branch_id', $request->input('branch_id'));
    }

    // Lọc theo combo
    if ($request->filled('combo_id')) {
        $query->where('ticket_combos.combo_id', $request->input('combo_id'));
    }

    // Nhóm theo combo_id để tính tổng doanh thu và số lượng
    $ComboRevenue = $query->groupBy('ticket_combos.combo_id')->get();

    $totalAllRevenue = $ComboRevenue->sum('total_revenue');

    $ComboRevenue->push([
        'total_all_Revenue' => $totalAllRevenue
    ]);
    return response()->json($ComboRevenue);

}

    
public function revenueByMovie(Request $request)
{
    $query = Ticket::query()
    ->leftJoin('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id') // Kết nối bảng ticket_seats 
    ->leftJoin('point_histories', function ($join) {
        $join->on('tickets.id', '=', 'point_histories.ticket_id')
            ->where('point_histories.type', '=', 'Dùng điểm'); // Chỉ lấy điểm đã sử dụng
    })
    ->where('tickets.status', '!=', 'đã hủy')
    ->selectRaw('
        tickets.movie_id, 
        SUM(ticket_seats.price) 
        - COALESCE(SUM(tickets.voucher_discount), 0) 
        - COALESCE(SUM(point_histories.points), 0) as total_revenue,
        COUNT(DISTINCT tickets.id) as total_tickets
    ');
    // Lọc theo ngày
    if ($request->filled('from_date') && $request->filled('to_date')) {
        $query->whereBetween('tickets.created_at', [$request->from_date, $request->to_date]);
    }

    // Lọc theo tháng/năm
    if ($request->filled('month') && $request->filled('year')) {
        $query->whereMonth('tickets.created_at', $request->month)
              ->whereYear('tickets.created_at', $request->year);
    }

    // Lọc theo rạp (cinema)
    if ($request->filled('cinema_id')) {
        $query->where('tickets.cinema_id', $request->cinema_id);
    }

    // Lọc theo chi nhánh
    if ($request->filled('branch_id')) {
        $query->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
              ->where('cinemas.branch_id', $request->branch_id);
    }

    // Lọc theo phim (movie_id)
    if ($request->filled('movie_id')) {
        $query->where('tickets.movie_id', $request->movie_id);
    }

    // Nhóm theo movie_id để tính doanh thu theo từng phim
    $moviesRevenue = $query->groupBy('tickets.movie_id')->get();

    // Tổng doanh thu của tất cả phim
    $totalAllRevenue = $moviesRevenue->sum('total_revenue');

    $moviesRevenue->push([
        'total_all_revenue' => $totalAllRevenue,
    ]);

    return response()->json($moviesRevenue);
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
