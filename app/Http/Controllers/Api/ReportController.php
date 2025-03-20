<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Seat;
use App\Models\SeatShowtime;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\Ticket_Combo;
use App\Models\Ticket_Seat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Constraint\Count;

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
        COUNT(DISTINCT ticket_seats.id) as total_tickets
    ');

    //top 5 movies
    $movies = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
    // ->whereMonth('tickets.created_at', $month)
    // ->whereYear('tickets.created_at', $year)
    ->where('tickets.status', 'Đã thanh toán')
    ->selectRaw('
        tickets.movie_id,
        COUNT(DISTINCT ticket_seats.id) as total_tickets
    ');

        // Lọc theo ngày
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('tickets.created_at', [$request->from_date, $request->to_date]);
            $movies->whereBetween('tickets.created_at', [$request->from_date, $request->to_date]);
        }

        // Lọc theo tháng/năm
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('tickets.created_at', $request->month)
                ->whereYear('tickets.created_at', $request->year);
            $movies->whereMonth('tickets.created_at', $request->month)
                ->whereYear('tickets.created_at', $request->year);
        }

        // Lọc theo rạp (cinema)
        if ($request->filled('cinema_id')) {
            $query->where('tickets.cinema_id', $request->cinema_id);
            $movies->where('tickets.cinema_id', $request->cinema_id);
        }

        // Lọc theo chi nhánh
        if ($request->filled('branch_id')) {
            $query->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                ->where('cinemas.branch_id', $request->branch_id);
            $movies->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
                ->where('cinemas.branch_id', $request->branch_id);
        }

        // Lọc theo phim (movie_id)
        if ($request->filled('movie_id')) {
            $query->where('tickets.movie_id', $request->movie_id);
        }

        // Nhóm theo movie_id để tính doanh thu theo từng phim
        $moviesRevenue = $query->groupBy('tickets.movie_id')->get();

        $top=$movies->groupBy('tickets.movie_id')
        ->orderByDesc('total_tickets') // Sắp xếp từ cao xuống thấp
        ->limit(5) // Lấy 5 phim có nhiều vé nhất
        ->get();

        // Tổng doanh thu của tất cả phim
        $totalAllRevenue = $moviesRevenue->sum('total_revenue');
        $totalAllTicket = $moviesRevenue->sum('total_tickets');

        $moviesRevenue->push([
            'total_all_revenue' => $totalAllRevenue,
            'total_all_ticket' => $totalAllTicket,
            'top_movie'=>$top
        ]);

        return response()->json($moviesRevenue);
    }

    public function totalRevenue(Request $request)
    {
        $month = $request->input('month'); // Mặc định null nếu không nhập
        $year = $request->input('year');
        $timeGroup = $request->input('group_by', 'month'); // Mặc định nhóm theo tháng
        
        // Tạo điều kiện chung cho tất cả truy vấn
        $filterConditions = function ($query) use ($month, $year) {
            if ($year) {
                $query->whereYear('tickets.created_at', $year);
            }
            if ($month) {
                $query->whereMonth('tickets.created_at', $month);
            }
        };
        
        // Tổng doanh thu
        $totalRevenue = Ticket::where('status', '!=', 'đã hủy')
            ->when($month || $year, $filterConditions)
            ->sum('total_price');
        
        // Doanh thu theo rạp
        $revenueByCinema = Ticket::where('status', '!=', 'đã hủy')
            ->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
            ->selectRaw('cinemas.name as cinema, SUM(tickets.total_price) as revenue')
            ->groupBy('cinemas.name')
            ->when($month || $year, $filterConditions)
            ->get();
        
        // Doanh thu theo phim
        $revenueByMovie = Ticket::where('status', '!=', 'đã hủy')
            ->join('movies', 'tickets.movie_id', '=', 'movies.id')
            ->selectRaw('movies.name as movie, SUM(tickets.total_price) as revenue')
            ->groupBy('movies.name')
            ->when($month || $year, $filterConditions)
            ->get();
        
        // Thống kê hình thức thanh toán
        $paymentMethods = Ticket::where('status', '!=', 'đã hủy')
            ->selectRaw('payment_name, SUM(total_price) as total_amount')
            ->groupBy('payment_name')
            ->when($month || $year, $filterConditions)
            ->get()
            ->mapWithKeys(function ($item) use ($totalRevenue) {
                $percentage = $totalRevenue > 0 ? round(($item->total_amount / $totalRevenue) * 100, 2) . '%' : '0%';
                return [$item->payment_name => $percentage];
            });
        
        // Xu hướng doanh thu theo tháng hoặc ngày
        $trendColumn = $timeGroup === 'day' ? "DATE_FORMAT(tickets.created_at, '%d-%m')" : "DATE_FORMAT(tickets.created_at, '%b')";
        
        $revenueTrend = Ticket::where('status', '!=', 'đã hủy')
            ->selectRaw("$trendColumn as time, SUM(tickets.total_price) as revenue")
            ->groupBy('time')
            ->orderByRaw('MIN(tickets.created_at)')
            ->when($year, fn($query) => $query->whereYear('tickets.created_at', $year))
            ->when($month && $timeGroup === 'day', fn($query) => $query->whereMonth('tickets.created_at', $month))
            ->get();

        return response()->json([
            'totalRevenue' => $totalRevenue,
            'revenueByCinema' => $revenueByCinema,
            'revenueByMovie' => $revenueByMovie,
            'paymentMethods' => $paymentMethods,
            'monthlyTrend' => $revenueTrend
        ]);
    }
    
    
    private function getTimeFormat($groupBy)
    {
        return match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };
    }
    

    public function revenueStatistics(Request $request)
    {
        // Validate input
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date|after_or_equal:from_date',
            'cinema_id'  => 'nullable|integer',
            'branch_id'  => 'nullable|integer',
        ]);
        // Log::info('Request Data:', $request->all());
        // Lấy ngày bắt đầu và kết thúc
        $fromDate = $request->from_date ?? Ticket::min('created_at');
        $toDate   = $request->to_date ?? Ticket::max('created_at');

        // Truy vấn chỉ lấy các vé có trạng thái "Đã thanh toán"
        $query = Ticket::where('status', 'Đã thanh toán');


        if ($fromDate && $toDate) {
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        }

        // Nếu có cinema_id, kiểm tra xem cinema có tồn tại không
        if (!empty($request->cinema_id)) {
            $cinemaExists = Cinema::where('id', $request->cinema_id)->exists();
            if (!$cinemaExists) {
                return response()->json([
                    'message'       => 'Cinema không tồn tại!',
                    'cinema_id'     => $request->cinema_id,
                    'total_revenue' => 0
                ], 400);
            }
            $query->where('cinema_id', $request->cinema_id);
        }


        // Nếu có branch_id, kiểm tra branch có chứa cinema nào không
        if (!empty($request->branch_id)) {
            $cinemaIds = Cinema::where('branch_id', $request->branch_id)->pluck('id')->toArray();

            if (empty($cinemaIds)) {
                return response()->json([
                    'message'       => 'Branch này không có Cinema nào!',
                    'branch_id'     => $request->branch_id,
                    'total_revenue' => 0
                ], 400);
            }

            // Nếu có cả cinema_id và branch_id, kiểm tra xem cinema có thuộc branch không
            if (!empty($request->cinema_id) && !in_array($request->cinema_id, $cinemaIds)) {
                return response()->json([
                    'message'       => 'Cinema không thuộc Branch này!',
                    'cinema_id'     => $request->cinema_id,
                    'branch_id'     => $request->branch_id,
                    'total_revenue' => 0
                ], 400);
            }

            $query->whereIn('cinema_id', $cinemaIds);
        }

        // Truy vấn doanh thu theo ngày
        // Lấy doanh thu theo từng ngày
        $revenues = $query->selectRaw('DATE(created_at) as date, SUM(total_price) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Log::debug($totalRevenue);
        return response()->json([
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'cinema_id'     => $request->cinema_id ?? 'All Cinemas',
            'branch_id'     => $request->branch_id ?? 'All Branches',
            'data'      => $revenues
        ]);
    }

    //Tickets 
    public function ticketStatistics(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
        $timeGroup = $request->input('group_by', 'day');
        
        // Tạo điều kiện lọc chung
        $filterConditions = function ($query) use ($month, $year) {
            if ($year) {
                $query->whereYear('tickets.created_at', $year);
            }
            if ($month) {
                $query->whereMonth('tickets.created_at', $month);
            }
        };
        
        // Biểu đồ số vé đã bán theo ngày/tuần/tháng
        $ticketSalesTrend = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->selectRaw('DATE_FORMAT(ticket_seats.created_at, ?) as time_group, COUNT(ticket_seats.id) as total_tickets', [$this->getTimeFormat($timeGroup)])
            ->when($month || $year, $filterConditions)
            ->groupBy('time_group')
            ->orderByRaw('MIN(ticket_seats.created_at)')
            ->get();
        
        // Tổng số vé đã bán
        $totaltickets = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->when($month || $year, $filterConditions)
            ->count('ticket_seats.id');
        
        // Bảng các phim có số vé cao nhất
        $topMoviesByTickets = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->join('movies', 'tickets.movie_id', '=', 'movies.id')
            ->selectRaw('movies.name as movie, COUNT(ticket_seats.id) as total_tickets')
            ->when($month || $year, $filterConditions)
            ->groupBy('movies.name')
            ->orderByDesc('total_tickets')
            ->limit(10)
            ->get();
        
// Bảng các rạp có tỷ lệ lấp đầy ghế cao nhất
$cinemas = Cinema::whereHas('showtimes', function ($query) use ($month, $year) {
    if ($month) {
        $query->whereMonth('date', $month);
    }
    if ($year) {
        $query->whereYear('date', $year);
    }
})->get();

$data = [];

foreach ($cinemas as $cinema) {
    // Lấy các suất chiếu theo điều kiện tháng/năm
    $showtimes = Showtime::where('cinema_id', $cinema->id)
        ->when($month, fn($query) => $query->whereMonth('date', $month)) // Lọc theo tháng nếu có
        ->when($year, fn($query) => $query->whereYear('date', $year))    // Lọc theo năm nếu có
        ->get();

    // Tính tổng số ghế và số ghế đã đặt
    $totalSeats = $showtimes->sum(fn($showtime) => Seat::where('room_id', $showtime->room_id)->count());
    $totalBookedSeats = $showtimes->sum(fn($showtime) => SeatShowtime::where('showtime_id', $showtime->id)->where('status', 'booked')->count());

    // Tính tỷ lệ lấp đầy
    $occupancyRate = $totalSeats > 0 ? round(($totalBookedSeats / $totalSeats) * 100, 2) : 0;

    // Xây dựng mảng dữ liệu
    $cinemaData = [
        'cinema' => $cinema->name,
        'total_seats' => $totalSeats,
        'booked_seats' => $totalBookedSeats,
        'empty_seats' => $totalSeats - $totalBookedSeats,
        'occupancy_rate' => $occupancyRate . '%'
    ];

    // Chỉ thêm month & year nếu chúng được nhập vào request
    if ($month) {
        $cinemaData['month'] = $month;
    }
    if ($year) {
        $cinemaData['year'] = $year;
    }

    $data[] = $cinemaData;
}

// Sắp xếp và lấy top 5 rạp có tỷ lệ lấp đầy cao nhất
$data = collect($data)->sortByDesc('occupancy_rate')->take(5)->values()->all();



    return response()->json([
        'totaltickets'=>$totaltickets,
        'ticketSalesTrend' => $ticketSalesTrend,
        'topMoviesByTickets' => $topMoviesByTickets,
        'cinemaOccupancy' => $data,
    ]);
}

    public function customer(){

        // Chọn tháng & năm cần truy vấn (có thể thay đổi khi gọi API)
        $targetMonth = request()->input('month', Carbon::now()->month); // Mặc định là tháng hiện tại
        $targetYear = request()->input('year', Carbon::now()->year);   // Mặc định là năm hiện tại
        $month = request()->input('month'); // Lấy tháng từ request (có thể null)
        $year = request()->input('year');   // Lấy năm từ request (có thể null)
        
        // New user: Lọc theo tháng/năm nếu có, nếu không lấy toàn bộ dữ liệu
        $newCustomersByMonth = User::where('role', 'member')
            ->when($year, fn($query) => $query->whereYear('created_at', $year))
            ->when($month, fn($query) => $query->whereMonth('created_at', $month))
            ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, COUNT(id) as total_customers')
            ->groupBy('month', 'year')
            ->orderBy('month')
            ->get();
        
        // Top user: Lọc theo tháng/năm nếu có
        $topUser = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->when($year, fn($query) => $query->whereYear('tickets.created_at', $year))
            ->when($month, fn($query) => $query->whereMonth('tickets.created_at', $month))
            ->selectRaw('users.name as user, COUNT(ticket_seats.id) as total_tickets')
            ->groupBy('user')
            ->orderByDesc('total_tickets')
            ->limit(5)
            ->get();
        
        // Payment methods: Lọc theo tháng/năm nếu có
        $paymentMethods = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->when($year, fn($query) => $query->whereYear('tickets.created_at', $year))
            ->when($month, fn($query) => $query->whereMonth('tickets.created_at', $month))
            ->selectRaw('tickets.payment_name, COUNT(tickets.id) as total_use')
            ->groupBy('payment_name')
            ->get();
        
        // Tổng số lần sử dụng phương thức thanh toán
        $totalAllUses = $paymentMethods->sum('total_use');
        
        // Tính phần trăm và trả về kết quả cuối cùng
        $paymentMethodsWithPercentage = $paymentMethods->map(function ($item) use ($totalAllUses) {
            $percentage = $totalAllUses > 0 ? round(($item->total_use / $totalAllUses) * 100, 2) : 0;
            return [
                'payment_name' => $item->payment_name,
                'total_use' => $item->total_use,
                'percentage' => $percentage . '%'
            ];
        });        
        
        //ti le khac hang quay lai

        // Lấy danh sách khách hàng từng mua vé trước tháng được chọn
        $customersBefore = Ticket::whereDate('created_at', '<', Carbon::create($targetYear, $targetMonth, 1))
                         ->pluck('user_id')
                         ->unique();

        // Lấy danh sách khách hàng quay lại trong tháng được chọn
        $customersThisMonth = Ticket::whereMonth('created_at', $targetMonth)
                            ->whereYear('created_at', $targetYear)
                            ->whereIn('user_id', $customersBefore) // Chỉ tính khách từng mua trước đây
                            ->pluck('user_id')
                            ->unique();

        // Tính tỷ lệ khách hàng quay lại
        $totalCustomersBefore = $customersBefore->count();
        $returningCustomers = $customersThisMonth->count();

        $retentionRate = ($totalCustomersBefore > 0) 
            ? round(($returningCustomers / $totalCustomersBefore) * 100, 2) : 0;

        // Trả về dữ liệu
        $returningCustomersRate = [
        "month" => "$targetYear-$targetMonth",
        "retentionRate" => $retentionRate . "%"
];

    
        
        return response()->json([
            'newCustomersMonthly'=>$newCustomersByMonth,
            'vipCustomers'=>$topUser,
            'popularPaymentMethods'=>$paymentMethodsWithPercentage,
            'returningCustomersRate'=>$returningCustomersRate
        ]);
    }



    //xu huong dat ve
    public function bookingTrends(){
        //gio dat ve pho bien
        // Xác định tháng và năm nếu không nhập
        $month = $month ?? Carbon::now()->month;
        $year = $year ?? Carbon::now()->year;
        
        // Danh sách thứ trong tuần
        $daysOfWeek = collect([
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ]);
        
        // Danh sách khung giờ cố định (2 tiếng từ 07:00 - 23:00)
        $timeSlots = collect(range(7, 23, 2))->map(fn($hour) => sprintf('%02d:00:00', $hour));
        
        // Lấy dữ liệu đặt vé theo `showtimes.start_time`
        $bookingData = DB::table('tickets')
            ->join('ticket_seats', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
            ->whereMonth('tickets.created_at', $month)
            ->whereYear('tickets.created_at', $year)
            ->where('tickets.status', 'Đã thanh toán')
            ->selectRaw("
                DAYOFWEEK(tickets.created_at) as weekday, 
                HOUR(showtimes.start_time) as show_hour,
                COUNT(ticket_seats.id) as totalbooking
            ")
            ->groupBy('weekday', 'show_hour')
            ->get()
            ->keyBy(fn($item) => $item->weekday . '-' . $item->show_hour); // Key dạng `weekday-HH`
        
        // Gộp dữ liệu vào danh sách thứ trong tuần + khung giờ
        $bookingPerWeekday = $daysOfWeek->map(function ($dayName, $weekday) use ($timeSlots, $bookingData) {
            return [
                'weekday' => $dayName,
                'times' => $timeSlots->map(function ($time) use ($weekday, $bookingData) {
                    $hour = intval(substr($time, 0, 2)); // Lấy giờ (07, 09, 11,...)
                    $totalBooking = 0;
        
                    // Cộng dồn số vé đặt trong khoảng 2 tiếng (VD: 07:00 - 08:59)
                    for ($h = $hour; $h < $hour + 2; $h++) {
                        $key = $weekday . '-' . $h;
                        if (isset($bookingData[$key])) {
                            $totalBooking += $bookingData[$key]->totalbooking;
                        }
                    }
        
                    return [
                        'time' => $time,
                        'totalbooking' => $totalBooking
                    ];
                })
            ];
        });
        
        // Tính tổng số vé trong tháng
        $totaltickets = $bookingPerWeekday->sum(fn($day) => collect($day['times'])->sum('totalbooking'));
        
        // Tính tỷ lệ phần trăm cho từng thứ trong tuần + khung giờ
        $BookingTypeRatioWithPercentage = $bookingPerWeekday->map(function ($day) use ($totaltickets) {
            return [
                'weekday' => $day['weekday'],
                'times' => collect($day['times'])->map(function ($item) use ($totaltickets) {
                    $percentage = $totaltickets > 0 ? round(($item['totalbooking'] / $totaltickets) * 100, 2) : 0;
                    return [
                        'time' => $item['time'],
                        'totalbooking' => $item['totalbooking'],
                        'percentage' => $percentage . '%'
                    ];
                })
            ];
        });
        // Tỷ lệ loại ghế được đặt
        $seatTypeRatio = Ticket_Seat::join('seats', 'seats.id', '=', 'ticket_seats.seat_id')
            ->join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('type_seats', 'type_seats.id', '=', 'seats.type_seat_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->whereMonth('ticket_seats.created_at', $month)
            ->whereYear('ticket_seats.created_at', $year)
            ->selectRaw('type_seats.name as type_seats, count(seats.type_seat_id) as total_type')
            ->groupBy('type_seats.name')
            ->get();

        $totalseats = $seatTypeRatio->sum('total_type');

        $seatTypeRatioWithPercentage = $seatTypeRatio->map(function ($item) use ($totalseats) {
            $percentage = $totalseats > 0 ? round(($item->total_type / $totalseats) * 100, 2) : 0;
            return [
                'seat_name' => $item->type_seats,
                'total_seat' => $item->total_type,
                'percentage' => $percentage . '%'
            ];
        });

        // Số lượng vé đặt theo từng ngày trong tuần
        $weeklyBooking = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->whereMonth('ticket_seats.created_at', $month)
            ->whereYear('ticket_seats.created_at', $year)
            ->selectRaw('dayname(ticket_seats.created_at) as day, count(ticket_seats.id) as total_tickets')
            ->groupBy('day')
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->get();

        // Xu hướng đặt vé trước bao nhiêu ngày
        $bookingAdvance = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->whereMonth('ticket_seats.created_at', $month)
            ->whereYear('ticket_seats.created_at', $year)
            ->selectRaw("
                CASE 
                    WHEN DATEDIFF(tickets.expiry, ticket_seats.created_at) = 0 THEN 'sameDay'
                    WHEN DATEDIFF(tickets.expiry, ticket_seats.created_at) BETWEEN 1 AND 3 THEN '1-3Days'
                    WHEN DATEDIFF(tickets.expiry, ticket_seats.created_at) BETWEEN 4 AND 7 THEN '4-7Days'
                    ELSE 'MoreThan7Days'
                END as booking_group,
                COUNT(ticket_seats.id) as total_tickets
            ")
            ->groupBy('booking_group')
            ->get();
                return response()->json([
                    'bookingHeatmap'=>$BookingTypeRatioWithPercentage,
                    'seatTypeRatio'=>$seatTypeRatioWithPercentage,
                    'weeklyBooking'=>$weeklyBooking,
                    'bookingAdvance'=>$bookingAdvance
                ]);
            }
}
