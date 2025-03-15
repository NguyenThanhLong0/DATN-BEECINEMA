<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seat;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Showtime;
use Carbon\Carbon;
use App\Models\SeatShowtime;
class OverviewController extends Controller
{
    public function OverView()
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $currentday = Carbon::now()->day;

        $totalTickets = Ticket::where('status', '!=', 'đã hủy')
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
        $showtimes = Showtime::whereDate('date', $date)->get();
        $totalSeats =0;
        $totalbookedSeats=0;
        $data = [];
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
            $occupancyRate = $totalSeats > 0 ? ($totalbookedSeats / $totalSeats) * 100 : 0;
        $data[] = [
            'date' => $showtime->date,
            'total_seats' => $totalSeats,
            'booked_seats' => $totalbookedSeats,
            'empty_seats ' => $unSeats,
            'occupancy_rate' => round($occupancyRate, 2) . '%'
        ];

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
                $occupancyRate = $totalSeats > 0 ? ($totalbookedSeats / $totalSeats) * 100 : 0;
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
}
