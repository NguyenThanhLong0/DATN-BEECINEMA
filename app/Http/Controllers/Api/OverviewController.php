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
        $month = request()->input('month');
        $year = request()->input('year');
        $totalRevenue = 0;
    
        $query = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_id')
            ->where('tickets.status', 'Đã thanh toán');
        
        if (!empty($month) && !empty($year)) {
            $query->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
        }
        
        $ticketSeats = $query->count();
    
        $newCustomersQuery = User::where('role', 'member');
        if (!empty($month) && !empty($year)) {
            $newCustomersQuery->whereMonth('created_at', $month)->whereYear('created_at', $year);
        }
        $newCustomers = $newCustomersQuery->count();
    
        // revenueChart
        $revenueChart = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($year ?? Carbon::now()->year, $month ?? Carbon::now()->month, $day)->toDateString();
            $tickets = Ticket::whereDate('created_at', $date)->get();
            if ($tickets->isEmpty()) {
                continue;
            }
            $totalRevenueDay = $tickets->sum('total_price');
            $totalRevenue += $totalRevenueDay;
            $revenueChart[] = [
                'date' => $date,
                'revenue' => $totalRevenueDay
            ];
        }
    
        // top 5 movies
        $moviesQuery = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('movies', 'movies.id', '=', 'tickets.movie_id')
            ->where('tickets.status', 'Đã thanh toán')
            ->selectRaw('movies.name, movies.img_thumbnail, COUNT(DISTINCT ticket_seats.id) as total_tickets')
            ->groupBy('movies.name', 'movies.img_thumbnail')
            ->orderByDesc('total_tickets')
            ->limit(5);
    
        if (!empty($month) && !empty($year)) {
            $moviesQuery->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
        }
        $movies = $moviesQuery->get();
    
        // bookingHeatmap
        $totalTicketsQuery = DB::table('ticket_seats')
            ->join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', 'Đã thanh toán');
        
        if (!empty($month) && !empty($year)) {
            $totalTicketsQuery->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
        }
        $totalTickets = $totalTicketsQuery->count();
    
        $top5TimeSlotsQuery = DB::table('tickets')
            ->join('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
            ->where('tickets.status', 'Đã thanh toán')
            ->selectRaw("CASE 
                WHEN HOUR(showtimes.start_time) BETWEEN 7 AND 8 THEN '07:00 - 09:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 9 AND 10 THEN '09:00 - 11:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 11 AND 12 THEN '11:00 - 13:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 13 AND 14 THEN '13:00 - 15:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 15 AND 16 THEN '15:00 - 17:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 17 AND 18 THEN '17:00 - 19:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 19 AND 20 THEN '19:00 - 21:00'
                WHEN HOUR(showtimes.start_time) BETWEEN 21 AND 22 THEN '21:00 - 23:00'
                ELSE 'Khác' END as time_slot,
                COUNT(ticket_seats.id) as totalbooking")
            ->groupBy('time_slot')
            ->orderByDesc('totalbooking')
            ->limit(5);
        
        if (!empty($month) && !empty($year)) {
            $top5TimeSlotsQuery->whereMonth('tickets.created_at', $month)->whereYear('tickets.created_at', $year);
        }
        $top5TimeSlots = $top5TimeSlotsQuery->get()
            ->map(fn($item) => [
                'time' => $item->time_slot,
                'totalbooking' => $item->totalbooking,
                'percentage' => $totalTickets > 0 ? round(($item->totalbooking / $totalTickets) * 100, 2) . '%' : '0%'
            ]);
    
        return response()->json(array_filter([
            'month' => !empty($month) ? $month : null,
            'year' => !empty($year) ? $year : null,
            'totalRevenue' => $totalRevenue,
            'ticketsSold' => $ticketSeats,
            'newCustomers' => $newCustomers,
            'revenueChart' => $revenueChart,
            'movies' => $movies,
            'bookingHeatmap' => $top5TimeSlots,
        ], fn($value) => !is_null($value)));
    }
    
}
