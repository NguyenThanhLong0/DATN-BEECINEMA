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
    public function OverView()
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $currentday = Carbon::now()->day;

        $totalTickets = Ticket::where('status', '=', 'đã thanh toán')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();
        $monthShowtimes = Showtime::whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->count();
        $dayShowtimes = Showtime::whereDay('date',$currentday)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->count();
        return response()->json([
            'day' => $currentday,
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
    public function seatOccupancyByMonth()
    {
        $daysInMonth= Carbon::now()->daysInMonth;
        $month =  Carbon::now()->month;
        $year =  Carbon::now()->year;

        $data = [];
        for( $day=1; $day<=$daysInMonth ; $day++ ){
            $date=Carbon::createFromDate($year,$month,$day)->toDateString();
            $showtimes = Showtime::whereDate('date', $date)->get();
            if ($showtimes->isEmpty()){
                continue;
            }
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
                'day'=>$date,
                'total_seats' => $totalSeats,
                'booked_seats' => $totalbookedSeats,
                'empty_seats' => $unSeats,
                'occupancy_rate' => round($occupancyRate, 2) . '%'
            ];
        }
        return response()->json([
            'month' => $month,
            'year' => $year,
            'showtimes' => $data
        ]);
    }

    public function card()
    {
        $daysInMonth = Carbon::now()->daysInMonth;
        $month = request()->input('month') ?? Carbon::now()->month;
        $year = request()->input('year') ?? Carbon::now()->year;
        
        // Xác định tháng và năm trước đó
        $previousMonthDate = Carbon::create($year, $month, 1)->subMonth();
        $previousMonth = $previousMonthDate->month;
        $previousYear = $previousMonthDate->year;
    
        // ticketsSold: Số vé tháng hiện tại
        $ticketSeatsQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_id');
        if (!empty($month) && !empty($year)) {
            $ticketSeatsQuery->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
        }
        $ticketSeats = $ticketSeatsQuery->count();
    
        // ticketsSold: Số vé tháng trước
        $ticketSeatsPreviousQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_id')
            ->whereMonth('tickets.created_at', $previousMonth)
            ->whereYear('tickets.created_at', $previousYear);
        $ticketSeatsPrevious = $ticketSeatsPreviousQuery->count();
    
        // Tính phần trăm thay đổi cho ticketsSold
        $ticketsSoldChange = $ticketSeatsPrevious > 0 
            ? round((($ticketSeats - $ticketSeatsPrevious) / $ticketSeatsPrevious) * 100, 1) 
            : ($ticketSeats > 0 ? 100 : 0);
        $ticketsSoldChange = $ticketsSoldChange >= 0 
            ? "+" . $ticketsSoldChange 
            : $ticketsSoldChange;
    
        // newCustomers: Số khách hàng mới tháng hiện tại
        $newCustomersQuery = User::where('role', 'member');
        if (!empty($month) && !empty($year)) {
            $newCustomersQuery->whereMonth('created_at', $month)->whereYear('created_at', $year);
        }
        $newCustomers = $newCustomersQuery->count();
    
        // newCustomers: Số khách hàng mới tháng trước
        $newCustomersPreviousQuery = User::where('role', 'member')
            ->whereMonth('created_at', $previousMonth)
            ->whereYear('created_at', $previousYear);
        $newCustomersPrevious = $newCustomersPreviousQuery->count();
    
        // Tính phần trăm thay đổi cho newCustomers
        $newCustomersChange = $newCustomersPrevious > 0 
            ? round((($newCustomers - $newCustomersPrevious) / $newCustomersPrevious) * 100, 1) 
            : ($newCustomers > 0 ? 100 : 0);
        $newCustomersChange = $newCustomersChange >= 0 
            ? "+" . $newCustomersChange 
            : $newCustomersChange;
    
        // totalRevenue: Doanh thu tháng hiện tại
        $totalRevenue = Ticket::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('total_price');
    
        // totalRevenue: Doanh thu tháng trước
        $totalRevenuePrevious = Ticket::whereMonth('created_at', $previousMonth)
            ->whereYear('created_at', $previousYear)
            ->sum('total_price');
    
        // Tính phần trăm thay đổi cho totalRevenue
        $totalRevenueChange = $totalRevenuePrevious > 0 
            ? round((($totalRevenue - $totalRevenuePrevious) / $totalRevenuePrevious) * 100, 1) 
            : ($totalRevenue > 0 ? 100 : 0);
        $totalRevenueChange = $totalRevenueChange >= 0 
            ? "+" . $totalRevenueChange 
            : $totalRevenueChange;
    
        // revenueChart
        $revenueChart = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($year, $month, $day)->toDateString();
            $tickets = Ticket::whereDate('created_at', $date)->get();
            if ($tickets->isEmpty()) {
                continue;
            }
            $totalRevenueDay = $tickets->sum('total_price');
            $revenueChart[] = [
                'date' => $date,
                'revenue' => $totalRevenueDay
            ];
        }
    
        // top 5 movies: Thêm doanh thu (revenue) cho mỗi phim
        $moviesQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('movies', 'movies.id', '=', 'tickets.movie_id')
            ->selectRaw('movies.name, movies.img_thumbnail, COUNT(DISTINCT ticket_seats.id) as total_tickets, SUM(DISTINCT tickets.total_price) as revenue')
            ->groupBy('movies.name', 'movies.img_thumbnail')
            ->orderByDesc('total_tickets')
            ->limit(6);
        
        if (!empty($month) && !empty($year)) {
            $moviesQuery->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
        }
        $movies = $moviesQuery->get();
    
        // bookingHeatmap
        $startDate = Carbon::now()->subDays(13)->startOfDay(); // Từ 09/03/2025
    $endDate = Carbon::now()->endOfDay(); // Đến 22/03/2025

    $bookingHeatmapQuery = DB::table('tickets')
        ->join('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
        ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
        ->where('tickets.status', 'Đã thanh toán')
        ->whereBetween('tickets.created_at', [$startDate, $endDate])
        ->select('showtimes.start_time')
        ->get();

    $bookingHeatmap = $bookingHeatmapQuery->map(function ($item) {
        $dateTime = Carbon::parse($item->start_time);
        return [
            'time' => $dateTime->toDateTimeString(), // "YYYY-MM-DD HH:MM:SS"
            'day' => $dateTime->format('l') // Tên ngày trong tuần: Monday, Tuesday, ...
        ];
    })->values()->all();
        // customerRetentionRate
        $totalCustomersThisMonth = DB::table('tickets')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->distinct()
            ->count('user_id');
    
        $returningCustomers = DB::table('tickets')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->whereIn('user_id', function ($query) use ($month, $year) {
                $query->select('user_id')
                      ->from('tickets')
                      ->where('created_at', '<', Carbon::create($year, $month, 1)->startOfMonth());
            })
            ->distinct()
            ->count('user_id');
    
        $customerRetentionRate = $totalCustomersThisMonth > 0 ? round(($returningCustomers / $totalCustomersThisMonth) * 100, 2) : 0;
    
        return response()->json(array_filter([
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
            'bookingHeatmap' => $bookingHeatmap,
        ], fn($value) => !is_null($value)));
    }
}
