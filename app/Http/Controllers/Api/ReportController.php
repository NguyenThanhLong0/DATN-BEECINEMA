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
        $start_date = $request->input('start_date'); // Thêm tham số ngày bắt đầu
        $end_date = $request->input('end_date'); // Thêm tham số ngày kết thúc
        $timeGroup = $request->input('group_by', 'month'); // Mặc định nhóm theo tháng
        
        // Tạo điều kiện chung cho tất cả truy vấn
        $filterConditions = function ($query) use ($month, $year, $start_date, $end_date) {
            if ($year) {
                $query->whereYear('tickets.created_at', $year);
            }
            if ($month) {
                $query->whereMonth('tickets.created_at', $month);
            }
            if ($start_date && $end_date) {
                $query->whereBetween('tickets.created_at', [$start_date, $end_date]);
            } elseif ($start_date) {
                $query->where('tickets.created_at', '>=', $start_date);
            } elseif ($end_date) {
                $query->where('tickets.created_at', '<=', $end_date);
            }
        };
        
        // Tổng doanh thu
        $totalRevenue = Ticket::where('status', '!=', 'đã hủy')
            ->when($month || $year || $start_date || $end_date, $filterConditions)
            ->sum('total_price');
        
        // Doanh thu theo rạp
        $revenueByCinema = Ticket::where('status', '!=', 'đã hủy')
            ->join('cinemas', 'tickets.cinema_id', '=', 'cinemas.id')
            ->selectRaw('cinemas.name as cinema, SUM(tickets.total_price) as revenue')
            ->groupBy('cinemas.name')
            ->when($month || $year || $start_date || $end_date, $filterConditions)
            ->get()
            ->map(function ($item) use ($totalRevenue) {
                $percentage = $totalRevenue > 0 ? round(($item->revenue / $totalRevenue) * 100, 2) : 0;
                return [
                    'cinema' => $item->cinema,
                    'revenue' => $item->revenue,
                    'percentage' => $percentage . '%',
                    'percentage_value' => $percentage
                ];
            })
            ->sortByDesc('percentage_value')
            ->values();
        
        // Doanh thu theo phim
        $revenueByMovie = Ticket::where('status', '!=', 'đã hủy')
            ->join('movies', 'tickets.movie_id', '=', 'movies.id')
            ->selectRaw('movies.name as movie, SUM(tickets.total_price) as revenue')
            ->groupBy('movies.name')
            ->when($month || $year || $start_date || $end_date, $filterConditions)
            ->get()
            ->map(function ($item) use ($totalRevenue) {
                $percentage = $totalRevenue > 0 ? round(($item->revenue / $totalRevenue) * 100, 2) : 0;
                return [
                    'movie' => $item->movie,
                    'revenue' => $item->revenue,
                    'percentage' => $percentage . '%',
                    'percentage_value' => $percentage
                ];
            })
            ->sortByDesc('percentage_value')
            ->values();
        
        // Thống kê hình thức thanh toán
        $paymentMethods = Ticket::where('status', '!=', 'đã hủy')
            ->selectRaw('payment_name, SUM(total_price) as total_amount')
            ->groupBy('payment_name')
            ->when($month || $year || $start_date || $end_date, $filterConditions)
            ->get()
            ->map(function ($item) use ($totalRevenue) {
                $percentage = $totalRevenue > 0 ? round(($item->total_amount / $totalRevenue) * 100, 2) : 0;
                return [
                    'payment_name' => $item->payment_name,
                    'total_amount' => $item->total_amount,
                    'percentage' => $percentage . '%',
                    'percentage_value' => $percentage
                ];
            })
            ->sortByDesc('percentage_value')
            ->values()
            ->map(function ($item) {
                return [
                    'payment_name' => $item['payment_name'],
                    'total_amount' => $item['total_amount'],
                    'percentage' => $item['percentage']
                ];
            });
        
        // Xu hướng doanh thu theo tháng hoặc ngày
        $trendColumn = $timeGroup === 'day' ? "DATE_FORMAT(tickets.created_at, '%d-%m')" : "DATE_FORMAT(tickets.created_at, '%b')";
        
        $revenueTrend = Ticket::where('status', '!=', 'đã hủy')
            ->selectRaw("$trendColumn as time, SUM(tickets.total_price) as revenue")
            ->groupBy('time')
            ->orderByRaw('MIN(tickets.created_at)')
            ->when($year, fn($query) => $query->whereYear('tickets.created_at', $year))
            ->when($month && $timeGroup === 'day', fn($query) => $query->whereMonth('tickets.created_at', $month))
            ->when($start_date && $end_date, fn($query) => $query->whereBetween('tickets.created_at', [$start_date, $end_date]))
            ->when($start_date && !$end_date, fn($query) => $query->where('tickets.created_at', '>=', $start_date))
            ->when($end_date && !$start_date, fn($query) => $query->where('tickets.created_at', '<=', $end_date))
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
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $timeGroup = $request->input('group_by', 'day');

        // Tạo điều kiện lọc chung dựa trên start_date và end_date
        $filterConditions = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->where('tickets.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('tickets.created_at', '<=', $endDate);
            }
        };

        // Biểu đồ xu hướng bán vé theo ngày/tuần/tháng
        $ticketSalesTrend = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->selectRaw('DATE_FORMAT(ticket_seats.created_at, ?) as time_group, COUNT(ticket_seats.id) as total_tickets', [$this->getTimeFormat($timeGroup)])
            ->when($startDate || $endDate, $filterConditions)
            ->groupBy('time_group')
            ->orderByRaw('MIN(ticket_seats.created_at)')
            ->get();

        // Tổng số vé đã bán
        $totaltickets = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->when($startDate || $endDate, $filterConditions)
            ->count('ticket_seats.id');

        // Số vé trung bình mỗi ngày
        $days = $startDate && $endDate ? (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1 : 1;
        $avgTicketsPerDay = $days > 0 ? round($totaltickets / $days, 2) : 0;

        // Phân loại vé theo type_seat_id từ bảng seat
        $ticketsByType = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('seats', 'ticket_seats.seat_id', '=', 'seats.id')
            ->when($startDate || $endDate, $filterConditions)
            ->selectRaw('seats.type_seat_id as seat_type, COUNT(ticket_seats.id) as total_tickets')
            ->groupBy('seats.type_seat_id')
            ->get();

        // Top khung giờ đông khách
        $peakHours = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
            ->when($startDate || $endDate, $filterConditions)
            ->selectRaw('HOUR(showtimes.start_time) as hour, COUNT(ticket_seats.id) as total_tickets')
            ->groupBy('hour')
            ->orderByDesc('total_tickets')
            ->limit(5)
            ->get();

        // Bảng các phim có số vé cao nhất
        $topMoviesByTickets = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('movies', 'tickets.movie_id', '=', 'movies.id')
            ->selectRaw('movies.name as movie, COUNT(ticket_seats.id) as total_tickets')
            ->when($startDate || $endDate, $filterConditions)
            ->groupBy('movies.name')
            ->orderByDesc('total_tickets')
            ->limit(10)
            ->get();

        // Bảng các rạp có tỷ lệ lấp đầy ghế cao nhất
        $cinemas = Cinema::whereHas('showtimes', function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->where('date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('date', '<=', $endDate);
            }
        })->get();

        $data = [];

        foreach ($cinemas as $cinema) {
            $showtimes = Showtime::where('cinema_id', $cinema->id)
                ->when($startDate, fn($query) => $query->where('date', '>=', $startDate))
                ->when($endDate, fn($query) => $query->where('date', '<=', $endDate))
                ->get();

            $totalSeats = $showtimes->sum(fn($showtime) => Seat::where('room_id', $showtime->room_id)->count());
            $totalBookedSeats = $showtimes->sum(fn($showtime) => SeatShowtime::where('showtime_id', $showtime->id)->where('status', 'booked')->count());
            $occupancyRate = $totalSeats > 0 ? round(($totalBookedSeats / $totalSeats) * 100, 2) : 0;

            $cinemaData = [
                'cinema' => $cinema->name,
                'total_seats' => $totalSeats,
                'booked_seats' => $totalBookedSeats,
                'empty_seats' => $totalSeats - $totalBookedSeats,
                'occupancy_rate' => $occupancyRate . '%'
            ];

            if ($startDate) {
                $cinemaData['start_date'] = $startDate;
            }
            if ($endDate) {
                $cinemaData['end_date'] = $endDate;
            }

            $data[] = $cinemaData;
        }

        $data = collect($data)->sortByDesc('occupancy_rate')->take(5)->values()->all();

        return response()->json([
            'totaltickets' => $totaltickets,
            'avgTicketsPerDay' => $avgTicketsPerDay,
            'ticketsByType' => $ticketsByType,
            'peakHours' => $peakHours,
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
    public function bookingTrends()
    {
        // Validation cho request
        $validated = request()->validate([
            'start_date' => 'nullable|date|before_or_equal:end_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
    
        // Lấy khoảng thời gian mặc định nếu không có input (mặc định là tháng hiện tại)
        $startDate = request()->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = request()->input('end_date', Carbon::now()->endOfMonth()->toDateString());
        $startOfPeriod = Carbon::parse($startDate)->startOfDay();
        $endOfPeriod = Carbon::parse($endDate)->endOfDay();
    
        // 1. Booking Trend by Time (Xu hướng đặt vé theo ngày trong khoảng thời gian)
        $bookingTrendByTime = Ticket::where('status', '!=', 'đã hủy')
            ->whereBetween('created_at', [$startOfPeriod, $endOfPeriod])
            ->selectRaw('DATE(created_at) as date, COUNT(id) as total_bookings, SUM(total_price) as total_revenue')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                $prevDay = Ticket::where('status', '!=', 'đã hủy')
                    ->whereDate('created_at', Carbon::parse($item->date)->subDay())
                    ->count();
                $growthRate = $prevDay > 0 ? round((($item->total_bookings - $prevDay) / $prevDay) * 100, 2) : null;
                return [
                    'date' => $item->date,
                    'total_bookings' => $item->total_bookings,
                    'total_revenue' => (int) $item->total_revenue,
                    'growth_rate' => $growthRate ? "$growthRate%" : 'N/A',
                ];
            });
    
        // 2. Top Booking Customers (Khách hàng đặt vé nhiều nhất trong khoảng thời gian)
        $topBookingCustomers = Ticket_Seat::join('tickets', 'tickets.id', '=', 'ticket_seats.ticket_id')
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->where('tickets.status', '!=', 'đã hủy')
            ->whereBetween('tickets.created_at', [$startOfPeriod, $endOfPeriod])
            ->selectRaw('users.id, users.name as user, COUNT(ticket_seats.id) as total_tickets, SUM(tickets.total_price) as total_spent, MAX(tickets.created_at) as last_booking')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_tickets')
            ->limit(5)
            ->get()
            ->map(function ($item) {

                return [
                    'user_id' => $item->id,
                    'user' => $item->user,
                    'total_tickets' => $item->total_tickets,
                    'total_spent' => (int) $item->total_spent,
                    'last_booking' => Carbon::parse($item->last_booking)->toDateString(),
                ];

            });
    
        // 3. Booking Payment Methods (Phương thức thanh toán trong khoảng thời gian)
        $bookingPaymentMethods = Ticket::where('status', '!=', 'đã hủy')
            ->whereBetween('created_at', [$startOfPeriod, $endOfPeriod])
            ->selectRaw('payment_name, COUNT(id) as total_bookings, SUM(total_price) as total_amount')
            ->groupBy('payment_name')
            ->get();
    
        $totalBookings = $bookingPaymentMethods->sum('total_bookings');
        $paymentMethodsStats = $bookingPaymentMethods->map(function ($item) use ($totalBookings) {
            $percentage = $totalBookings > 0 ? round(($item->total_bookings / $totalBookings) * 100, 2) : 0;
            return [
                'payment_name' => $item->payment_name ?? 'Unknown',
                'total_bookings' => $item->total_bookings,
                'total_amount' => (int) $item->total_amount,
                'percentage' => "$percentage%",
            ];
        });
    
        // 4. Returning Booking Rate (Tỷ lệ khách hàng quay lại trong khoảng thời gian)
        $customersBefore = Ticket::where('created_at', '<', $startOfPeriod)
            ->pluck('user_id')
            ->unique();
    
        $totalBookingsThisPeriod = Ticket::whereBetween('created_at', [$startOfPeriod, $endOfPeriod])
            ->pluck('user_id')
            ->unique();
    
        $returningCustomers = Ticket::whereBetween('created_at', [$startOfPeriod, $endOfPeriod])
            ->whereIn('user_id', $customersBefore)
            ->pluck('user_id')
            ->unique();
    
        $totalCustomersBefore = $customersBefore->count();
        $returningCount = $returningCustomers->count();
        $retentionRate = $totalCustomersBefore > 0 ? round(($returningCount / $totalCustomersBefore) * 100, 2) : 0;
    
        $returningBookingRate = [
            'period' => "$startDate to $endDate",
            'total_customers_before' => $totalCustomersBefore,
            'returning_customers' => $returningCount,
            'total_customers_this_period' => $totalBookingsThisPeriod->count(),
            'retention_rate' => "$retentionRate%",
        ];
    
        
    
        
        // 5. Peak Booking Hours (Giờ cao điểm đặt vé trong khoảng thời gian)
        $peakBookingHours = Ticket::where('status', '!=', 'đã hủy')
            ->whereBetween('created_at', [$startOfPeriod, $endOfPeriod])
            ->selectRaw('HOUR(created_at) as hour, COUNT(id) as total_bookings')
            ->groupBy('hour')
            ->orderBy('total_bookings', 'desc')
            ->get()
            ->map(function ($item) use ($totalBookings) {
                $percentage = $totalBookings > 0 ? round(($item->total_bookings / $totalBookings) * 100, 2) : 0;
                return [
                    'hour' => $item->hour,
                    'total_bookings' => $item->total_bookings,
                    'percentage' => "$percentage%",
                ];
            });
    
        // Trả về response
        return response()->json([
            'booking_trend_by_time' => $bookingTrendByTime,
            'top_booking_customers' => $topBookingCustomers,
            'booking_payment_methods' => $paymentMethodsStats,
            'returning_booking_rate' => $returningBookingRate,
            'peak_booking_hours' => $peakBookingHours,
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
