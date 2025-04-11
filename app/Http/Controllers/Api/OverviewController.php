<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Seat;
use App\Models\Ticket_Seat;
use Database\Seeders\TicketSeeder;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Showtime;
use Carbon\Carbon;
use App\Models\SeatShowtime;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    public function OverView(Request $request)
    {
        $currentDay = $request->input('day') ?? Carbon::now()->day;
        $currentMonth = $request->input('month') ?? Carbon::now()->month;
        $currentYear = $request->input('year') ?? Carbon::now()->year;
        $cinemaId = $request->input('cinema_id');
    
        // Đếm tổng vé đã thanh toán
        $ticketQuery = Ticket::where('status', 'đã thanh toán')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear);
    
        // Nếu có cinema_id thì lọc theo rạp
        if ($cinemaId) {
            $ticketQuery->where('cinema_id', $cinemaId);
        }
    
        $totalTickets = $ticketQuery->count();
    
        // Số suất chiếu trong tháng
        $monthShowtimesQuery = Showtime::whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear);
    
        // Số suất chiếu trong ngày
        $dayShowtimesQuery = Showtime::whereDay('date', $currentDay)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear);
    
        if ($cinemaId) {
            $monthShowtimesQuery->where('cinema_id', $cinemaId);
            $dayShowtimesQuery->where('cinema_id', $cinemaId);
        }
    
        $monthShowtimes = $monthShowtimesQuery->count();
        $dayShowtimes = $dayShowtimesQuery->count();
    
        return response()->json([
            'day' => $currentDay,
            'month' => $currentMonth,
            'year' => $currentYear,
            'total_tickets' => $totalTickets,
            'total_month_showtimes' => $monthShowtimes,
            'total_day_showtimes' => $dayShowtimes
        ]);
    }
    

    // Tính phần trăm ghế đã đặt theo ngày
    public function seatOccupancyByDay()
    {
        $date = Carbon::today()->toDateString();

        $cinemas = Cinema::whereHas('showtimes')->get();

        $data = [];
        foreach ($cinemas as $cinema){
            $showtimes = Showtime::whereDate('date', $date)->where('cinema_id',$cinema->id)->get();
            $totalSeats =0;
            $totalbookedSeats=0;
            foreach ($showtimes as $showtime) {
                $roomId = $showtime->room_id;
                $Seats = Seat::where('room_id', $roomId)->count();
                $bookedSeats = SeatShowtime::where('showtime_id', $showtime->id)
                    ->where('status', 'booked')
                    ->count();
                    $totalbookedSeats+=$bookedSeats;
                    $totalSeats+=$Seats;
            }
                $unSeats=$totalSeats-$totalbookedSeats;
                $occupancyRate = $totalSeats > 0 ? round(($totalbookedSeats / $totalSeats) * 100, 2) : 0;
            $data[] = [
                'cinema'=>$cinema->name,
                'date' => $date,
                'total_seats' => $totalSeats,
                'booked_seats' => $totalbookedSeats,
                'empty_seats ' => $unSeats,
                'occupancy_rate' => round($occupancyRate, 2) . '%'
            ];
        }
        

        return response()->json([
            'showtimes' => $data
        ]);
    }

    // Tính phần trăm ghế đã đặt theo tháng
    public function seatOccupancyByMonth(Request $request)
{
    $month = $request->input('month') ?? Carbon::now()->month;
    $year = $request->input('year') ?? Carbon::now()->year;
    $cinemaId = $request->input('cinema_id');

    $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
    $data = [];

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = Carbon::createFromDate($year, $month, $day)->toDateString();

        $query = Showtime::whereDate('date', $date);
        if ($cinemaId) {
            $query->where('cinema_id', $cinemaId);
        }

        $showtimes = $query->get();

        if ($showtimes->isEmpty()) {
            continue;
        }

        $totalSeats = 0;
        $totalBookedSeats = 0;

        foreach ($showtimes as $showtime) {
            $roomId = $showtime->room_id;

            $seats = Seat::where('room_id', $roomId)->count();
            $bookedSeats = SeatShowtime::where('showtime_id', $showtime->id)
                ->where('status', 'booked')
                ->count();

            $totalBookedSeats += $bookedSeats;
            $totalSeats += $seats;
        }

        $unSeats = $totalSeats - $totalBookedSeats;
        $occupancyRate = $totalSeats > 0 ? round(($totalBookedSeats / $totalSeats) * 100, 2) : 0;

        $data[] = [
            'day' => $date,
            'total_seats' => $totalSeats,
            'booked_seats' => $totalBookedSeats,
            'empty_seats' => $unSeats,
            'occupancy_rate' => $occupancyRate . '%'
        ];
    }

    $cinemaName = null;
    if ($cinemaId) {
        $cinema = Cinema::find($cinemaId);
        $cinemaName = $cinema ? $cinema->name : null;
    }

    return response()->json([
        'cinema_id' => $cinemaId,
        'cinema_name' => $cinemaName,
        'month' => $month,
        'year' => $year,
        'showtimes' => $data
    ]);
}


public function card(Request $request)
{
    $cinemaId = $request->input('cinema_id'); // Lấy cinema_id nếu có
    $month = $request->input('month') ?? Carbon::now()->month;
    $year = $request->input('year') ?? Carbon::now()->year;
    $daysInMonth = Carbon::create($year, $month)->daysInMonth;

    // Tháng trước
    $previousMonthDate = Carbon::create($year, $month, 1)->subMonth();
    $previousMonth = $previousMonthDate->month;
    $previousYear = $previousMonthDate->year;

    // ticketsSold tháng hiện tại
    $ticketSeatsQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_id');
    if ($cinemaId) {
        $ticketSeatsQuery->where('tickets.cinema_id', $cinemaId);
    }
    $ticketSeatsQuery->whereMonth('tickets.created_at', $month)
                     ->whereYear('tickets.created_at', $year);
    $ticketSeats = $ticketSeatsQuery->count();

    // ticketsSold tháng trước
    $ticketSeatsPreviousQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_id');
    if ($cinemaId) {
        $ticketSeatsPreviousQuery->where('tickets.cinema_id', $cinemaId);
    }
    $ticketSeatsPreviousQuery->whereMonth('tickets.created_at', $previousMonth)
                             ->whereYear('tickets.created_at', $previousYear);
    $ticketSeatsPrevious = $ticketSeatsPreviousQuery->count();

    $ticketsSoldChange = $ticketSeatsPrevious > 0
        ? round((($ticketSeats - $ticketSeatsPrevious) / $ticketSeatsPrevious) * 100, 1)
        : ($ticketSeats > 0 ? 100 : 0);
    $ticketsSoldChange = $ticketsSoldChange >= 0 ? "+" . $ticketsSoldChange : $ticketsSoldChange;

    // newCustomers tháng hiện tại
    $newCustomersQuery = User::role('member');
    $newCustomersQuery->whereMonth('created_at', $month)->whereYear('created_at', $year);
    $newCustomers = $newCustomersQuery->count();

    // newCustomers tháng trước
    $newCustomersPreviousQuery = User::role('member');
    $newCustomersPreviousQuery->whereMonth('created_at', $previousMonth)->whereYear('created_at', $previousYear);
    $newCustomersPrevious = $newCustomersPreviousQuery->count();

    $newCustomersChange = $newCustomersPrevious > 0
        ? round((($newCustomers - $newCustomersPrevious) / $newCustomersPrevious) * 100, 1)
        : ($newCustomers > 0 ? 100 : 0);
    $newCustomersChange = $newCustomersChange >= 0 ? "+" . $newCustomersChange : $newCustomersChange;

    // Doanh thu tháng hiện tại
    $totalRevenueQuery = Ticket::whereMonth('created_at', $month)->whereYear('created_at', $year);
    if ($cinemaId) {
        $totalRevenueQuery->where('cinema_id', $cinemaId);
    }
    $totalRevenue = $totalRevenueQuery->sum('total_price');

    // Doanh thu tháng trước
    $totalRevenuePreviousQuery = Ticket::whereMonth('created_at', $previousMonth)->whereYear('created_at', $previousYear);
    if ($cinemaId) {
        $totalRevenuePreviousQuery->where('cinema_id', $cinemaId);
    }
    $totalRevenuePrevious = $totalRevenuePreviousQuery->sum('total_price');

    $totalRevenueChange = $totalRevenuePrevious > 0
        ? round((($totalRevenue - $totalRevenuePrevious) / $totalRevenuePrevious) * 100, 1)
        : ($totalRevenue > 0 ? 100 : 0);
    $totalRevenueChange = $totalRevenueChange >= 0 ? "+" . $totalRevenueChange : $totalRevenueChange;

    // revenueChart
    $revenueChart = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = Carbon::createFromDate($year, $month, $day)->toDateString();
        $ticketsQuery = Ticket::whereDate('created_at', $date);
        if ($cinemaId) {
            $ticketsQuery->where('cinema_id', $cinemaId);
        }
        $tickets = $ticketsQuery->get();

        if ($tickets->isEmpty()) continue;

        $revenueChart[] = [
            'date' => $date,
            'revenue' => $tickets->sum('total_price')
        ];
    }

    // Top 5 phim
    $moviesQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
        ->join('movies', 'movies.id', '=', 'tickets.movie_id')
        ->selectRaw('movies.name, movies.img_thumbnail, COUNT(DISTINCT ticket_seats.id) as total_tickets, SUM(DISTINCT tickets.total_price) as revenue')
        ->groupBy('movies.name', 'movies.img_thumbnail')
        ->orderByDesc('total_tickets')
        ->limit(6);

    if ($cinemaId) {
        $moviesQuery->where('tickets.cinema_id', $cinemaId);
    }
    $moviesQuery->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
    $movies = $moviesQuery->get();

    // Heatmap
    $startDate = Carbon::now()->subDays(13)->startOfDay();
    $endDate = Carbon::now()->endOfDay();

    $bookingHeatmapQuery = DB::table('tickets')
        ->join('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
        ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
        ->where('tickets.status', 'Đã thanh toán')
        ->whereBetween('tickets.created_at', [$startDate, $endDate]);

    if ($cinemaId) {
        $bookingHeatmapQuery->where('tickets.cinema_id', $cinemaId);
    }

    $bookingHeatmap = $bookingHeatmapQuery->select('showtimes.start_time')->get()
        ->map(function ($item) {
            $dateTime = Carbon::parse($item->start_time);
            return [
                'time' => $dateTime->toDateTimeString(),
                'day' => $dateTime->format('l')
            ];
        })->values()->all();

    // Tỷ lệ khách quay lại
    $returningCustomersQuery = DB::table('tickets')
        ->whereMonth('created_at', $month)
        ->whereYear('created_at', $year);
    if ($cinemaId) {
        $returningCustomersQuery->where('cinema_id', $cinemaId);
    }
    $totalCustomersThisMonth = $returningCustomersQuery->distinct()->count('user_id');

    $previousCustomersSubQuery = DB::table('tickets')
        ->where('created_at', '<', Carbon::create($year, $month, 1)->startOfMonth());
    if ($cinemaId) {
        $previousCustomersSubQuery->where('cinema_id', $cinemaId);
    }

    $returningCustomers = DB::table('tickets')
        ->whereMonth('created_at', $month)
        ->whereYear('created_at', $year)
        ->whereIn('user_id', $previousCustomersSubQuery->pluck('user_id'))
        ->distinct()
        ->count('user_id');

    $customerRetentionRate = $totalCustomersThisMonth > 0
        ? round(($returningCustomers / $totalCustomersThisMonth) * 100, 2)
        : 0;

    return response()->json([
        'month' => $month,
        'year' => $year,
        'totalRevenue' => [
            'value' => $totalRevenue,
            'change' => $totalRevenueChange
        ],
        'ticketsSold' => [
            'value' => $ticketSeats,
            'change' => $ticketsSoldChange
        ],
        'newCustomers' => [
            'value' => $newCustomers,
            'change' => $newCustomersChange
        ],
        'customerRetentionRate' => $customerRetentionRate,
        'revenueChart' => $revenueChart,
        'movies' => $movies,
        'bookingHeatmap' => $bookingHeatmap
    ]);
}
}