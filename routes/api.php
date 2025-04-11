<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CinemaController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\ComboFoodController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MovieReviewController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\SeatTemplateController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\RankController;
use App\Http\Controllers\Api\TypeRoomController;
use App\Http\Controllers\Api\VoucherApiController;
use App\Http\Controllers\Api\TypeSeatController;
use App\Http\Controllers\Api\ShowtimeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChooseSeatController;
use App\Http\Controllers\Api\OverviewController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication (Không cần auth:sanctum cho đăng nhập/đăng ký)
Route::get('/sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/verify/email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
// Các route cần xác thực (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // User Profile & Related
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/user/membership', [UserController::class, 'membership']);
    Route::get('/user/vouchers', [UserController::class, 'getUserVouchers']);

    // Posts
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostApiController::class, 'index']);
        Route::post('/', [PostApiController::class, 'store']);
        Route::get('{id}', [PostApiController::class, 'show']);
        Route::patch('{id}', [PostApiController::class, 'update']);
        Route::put('{id}', [PostApiController::class, 'update']);
        Route::delete('{id}', [PostApiController::class, 'destroy']);
    });

    // Vouchers
    Route::prefix('vouchers')->group(function () {
        Route::get('/', [VoucherApiController::class, 'index']);
        Route::post('/', [VoucherApiController::class, 'store']);
        Route::get('{id}', [VoucherApiController::class, 'show']);
        Route::post('/apply-voucher', [VoucherApiController::class, 'applyVoucher']);
        Route::post('/remove-voucher', [VoucherApiController::class, 'removeVoucher']);
        Route::put('{id}', [VoucherApiController::class, 'update']);
        Route::patch('{id}', [VoucherApiController::class, 'update']);
        Route::delete('{id}', [VoucherApiController::class, 'destroy']);
    });

    // Tickets
    Route::post('/tickets/confirm', [TicketController::class, 'confirm']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/booking-history', [TicketController::class, 'getBookingHistory']);

    // Choose Seat
    Route::post('/update-seat', [ChooseSeatController::class, 'updateSeat']);
    Route::post('save-information/{id}', [ChooseSeatController::class, 'saveInformation'])->name('save-information');
    Route::get('choose-seat/{slug}', [ChooseSeatController::class, 'show'])->name('choose-seat');
    Route::get('userHoldSeats/{slug}', [ChooseSeatController::class, 'getUserHoldSeats']);

    // Showtime Seat Hold
    Route::post('/updateSeatHoldtime', [ShowtimeController::class, 'updateSeatHoldTime']);

    // Payment
    Route::post('payment', [PaymentController::class, 'payment'])->name('payment');
    Route::get('vnpay-payment', [PaymentController::class, 'vnPayPayment'])->name('vnpay.payment');
    Route::post('/zalopay/payment', [PaymentController::class, 'createPayment']);
    Route::post('/momo-payment', [PaymentController::class, 'MomoPayment']);
});
    Route::get('showtimespage', [ShowtimeController::class, 'pageShowtime']);
    Route::get('showtimemovie', [ShowtimeController::class, 'showtimeMovie']);

// Admin Routes (auth:sanctum + role:admin)
Route::middleware(['role:admin'])->group(function () {
    // Users Management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::post('/users/create', [UserController::class, 'add']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/{id}/restore', [UserController::class, 'restore']);
    Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDelete']);

    // Branches
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::patch('branches/{branch}', [BranchController::class, 'update'])->name('branches.update.partial');
    Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

    // Cinemas
    Route::post('cinemas', [CinemaController::class, 'store'])->name('cinemas.store');
    Route::put('cinemas/{cinema}', [CinemaController::class, 'update'])->name('cinemas.update');
    Route::patch('cinemas/{cinema}', [CinemaController::class, 'update'])->name('cinemas.update.partial');
    Route::delete('cinemas/{cinema}', [CinemaController::class, 'destroy'])->name('cinemas.destroy');

    // Ranks
    Route::post('ranks', [RankController::class, 'store'])->name('ranks.store');
    Route::put('ranks/{rank}', [RankController::class, 'update'])->name('ranks.update');
    Route::patch('ranks/{rank}', [RankController::class, 'update'])->name('ranks.update.partial');
    Route::delete('ranks/{rank}', [RankController::class, 'destroy'])->name('ranks.destroy');

        // Movies
    Route::post('/movies', [MovieController::class, 'store']);
    Route::put('/movies/{movie}', [MovieController::class, 'update']);
    Route::patch('/movies/{movie}', [MovieController::class, 'update']);
    Route::delete('/movies/{movie}', [MovieController::class, 'destroy']);
    Route::post('movies/update-active', [MovieController::class, 'updateActive'])->name('movies.update-active');
    Route::post('movies/update-hot', [MovieController::class, 'updateHot'])->name('movies.update-hot');

    
    // Banners
    Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
    Route::put('banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
    Route::patch('banners/{banner}', [BannerController::class, 'update'])->name('banners.update.partial');
    Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');


    // Movie Reviews
    Route::post('movie-reviews', [MovieReviewController::class, 'store']);
    Route::put('movie-reviews/{movieReview}', [MovieReviewController::class, 'update']);
    Route::patch('movie-reviews/{movieReview}', [MovieReviewController::class, 'update']);
    Route::delete('movie-reviews/{movieReview}', [MovieReviewController::class, 'destroy']);

    // Contact
    Route::post('contact', [ContactController::class, 'store'])->name('contact.store');
    Route::put('contact/{contact}', [ContactController::class, 'update'])->name('contact.update');
    Route::patch('contact/{contact}', [ContactController::class, 'update'])->name('contact.update.partial');
    Route::delete('contact/{contact}', [ContactController::class, 'destroy'])->name('contact.destroy');

    //Permission
    Route::get('permission',[PermissionController::class,'index']); //danh sach
    Route::get('permission/{id}',[PermissionController::class,'show']); //chi tiet
    Route::post('permission/add',[PermissionController::class,'store']); //them
    Route::patch('permission/update/{id}',[PermissionController::class,'update']); //update
    Route::put('permission/update/{id}',[PermissionController::class,'update']); //update
    Route::delete('/permission/delete/{id}',[PermissionController::class,'destroy']); //delete

    Route::prefix('roles')->group(function () {
        Route::get('/permission', [RoleController::class, 'role']);
        Route::post('add', [RoleController::class, 'store']);
        Route::get('{id}', [RoleController::class, 'show']);
        Route::put('update/{id}', [RoleController::class, 'update']);
        Route::patch('update/{id}', [RoleController::class, 'update']);
        Route::delete('delete/{id}', [RoleController::class, 'destroy']);
    });
});


// Admin Cinema Routes (auth:sanctum + role:admin_cinema)
Route::middleware(['auth:sanctum','role:admin|cinema'])->group(function () {
    // Foods
    Route::post('foods', [FoodController::class, 'store'])->name('foods.store');
    Route::put('foods/{food}', [FoodController::class, 'update'])->name('foods.update');
    Route::patch('foods/{food}', [FoodController::class, 'update'])->name('foods.update.partial');
    Route::delete('foods/{food}', [FoodController::class, 'destroy'])->name('foods.destroy');

    // Seat Templates
    Route::post('/seat-templates', [SeatTemplateController::class, 'store']);
    Route::put('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'update']);
    Route::patch('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'update']);
    Route::delete('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'destroy']);
    Route::patch('seat-templates/change-active/{seatTemplate}', [SeatTemplateController::class, 'changeActive']);

    // Rooms
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{room}', [RoomController::class, 'update']);
    Route::patch('/rooms/{room}', [RoomController::class, 'update']);
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

    // Combos
    Route::post('combos', [ComboController::class, 'store'])->name('combos.store');
    Route::put('combos/{combo}', [ComboController::class, 'update'])->name('combos.update');
    Route::patch('combos/{combo}', [ComboController::class, 'update'])->name('combos.update.partial');
    Route::delete('combos/{combo}', [ComboController::class, 'destroy'])->name('combos.destroy');

    // Type Rooms
    Route::post('/type-rooms', [TypeRoomController::class, 'store']);
    Route::put('/type-rooms/{typeRoom}', [TypeRoomController::class, 'update']);
    Route::patch('/type-rooms/{typeRoom}', [TypeRoomController::class, 'update']);
    Route::delete('/type-rooms/{typeRoom}', [TypeRoomController::class, 'destroy']);

    // Type Seats
    Route::post('/type-seats', [TypeSeatController::class, 'store']);
    Route::put('/type-seats/{typeSeat}', [TypeSeatController::class, 'update']);
    Route::patch('/type-seats/{typeSeat}', [TypeSeatController::class, 'update']);
    Route::delete('/type-seats/{typeSeat}', [TypeSeatController::class, 'destroy']);

    // Showtimes

    Route::post('add-showtime-per-day', [ShowtimeController::class, 'addShowtimePerDay']);
    Route::post('showtimes', [ShowtimeController::class, 'store']);
    Route::put('showtimes/{showtime}', [ShowtimeController::class, 'update']);
    Route::patch('showtimes/{showtime}', [ShowtimeController::class, 'update']);
    Route::delete('showtimes/{showtime}', [ShowtimeController::class, 'destroy']);
    Route::get('showtimes', [ShowtimeController::class, 'index']);
    Route::get('showtimes/{showtime}', [ShowtimeController::class, 'show']);
    Route::get('/showtimes/slug/{slug}', [ShowtimeController::class, 'showBySlug']);
    Route::post('/showtimes/{id}/copy', [ShowtimeController::class, 'copyShowtime']);
    Route::get('listshowtimesdate', [ShowtimeController::class, 'listShowtimesByDate']);
    Route::post('/showtimes/preview', [ShowtimeController::class, 'previewShowtimes']);
    // Movie Reviews
    Route::post('movie-reviews', [MovieReviewController::class, 'store']);
    Route::put('movie-reviews/{movieReview}', [MovieReviewController::class, 'update']);
    Route::patch('movie-reviews/{movieReview}', [MovieReviewController::class, 'update']);
    Route::delete('movie-reviews/{movieReview}', [MovieReviewController::class, 'destroy']);

    // Combo Foods
    // Route::post('combofoods', [ComboFoodController::class, 'store'])->name('combofoods.store');
    // Route::put('combofoods/{combofood}', [ComboFoodController::class, 'update'])->name('combofoods.update');
    // Route::patch('combofoods/{combofood}', [ComboFoodController::class, 'update'])->name('combofoods.update.partial');
    // Route::delete('combofoods/{combofood}', [ComboFoodController::class, 'destroy'])->name('combofoods.destroy');

});


// Public Routes (Không cần xác thực)
Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
Route::get('branches/active', [BranchController::class, 'branchesWithCinemasActive'])->name('branches.branchesWithCinemasActive');
Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');

Route::get('cinemas', [CinemaController::class, 'index'])->name('cinemas.index');
Route::get('cinemas/{cinema}', [CinemaController::class, 'show'])->name('cinemas.show');

Route::get('showtimespage', [ShowtimeController::class, 'pageShowtime']);
Route::get('showtimemovie', [ShowtimeController::class, 'showtimeMovie']);

Route::get('foods', [FoodController::class, 'index'])->name('foods.index');
Route::get('foods/{food}', [FoodController::class, 'show'])->name('foods.show');

Route::get('ranks', [RankController::class, 'index'])->name('ranks.index');
Route::get('ranks/{rank}', [RankController::class, 'show'])->name('ranks.show');

Route::get('/seat-templates', [SeatTemplateController::class, 'index']);
Route::get('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'show']);
Route::get('seat-templates/matrix/{id}', [SeatTemplateController::class, 'getMatrixById']);
Route::get('getAll-matrix', [SeatTemplateController::class, 'getAllMatrix']);

Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);

Route::get('combos', [ComboController::class, 'index'])->name('combos.index');
Route::get('combos/{combo}', [ComboController::class, 'show'])->name('combos.show');
Route::get('combosActive', [ComboController::class, 'indexActive']);

Route::get('/type-rooms', [TypeRoomController::class, 'index']);
Route::get('/type-rooms/{typeRoom}', [TypeRoomController::class, 'show']);

Route::put('/type-rooms/{typeRoom}', [TypeRoomController::class, 'update']);

Route::patch('/type-rooms/{typeRoom}', [TypeRoomController::class, 'update']);

Route::delete('/type-rooms/{typeRoom}', [TypeRoomController::class, 'destroy']);

Route::get('/roles', [RoleController::class, 'index']);

// Voucher
Route::prefix('vouchers')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [VoucherApiController::class, 'index']); // Lấy danh sách voucher
    Route::post('/', [VoucherApiController::class, 'store']); // Tạo mới voucher
    Route::get('{id}', [VoucherApiController::class, 'show']); // Lấy chi tiết voucher
    Route::post('/apply-voucher', [VoucherApiController::class, 'applyVoucher']);
    Route::post('/remove-voucher', [VoucherApiController::class, 'removeVoucher']);
    Route::put('{id}', [VoucherApiController::class, 'update']); // Cập nhật voucher
    Route::patch('{id}', [VoucherApiController::class, 'update']); // Cập nhật voucher
    Route::delete('{id}', [VoucherApiController::class, 'destroy']); // Xóa voucher
});

//Type Seat

Route::get('/type-seats', [TypeSeatController::class, 'index']);
Route::get('/type-seats/{typeSeat}', [TypeSeatController::class, 'show']);

Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/tab', [MovieController::class, 'moviesTabPageClient']);
Route::get('/movies/{movie}', [MovieController::class, 'show']);

// Route::get('combofoods', [ComboFoodController::class, 'index'])->name('combofoods.index');
// Route::get('combofoods/{combofood}', [ComboFoodController::class, 'show'])->name('combofoods.show');

Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
Route::get('banners/active', [BannerController::class, 'getActiveBanner'])->name('banners.getActiveBanner');
Route::get('banners/{banner}', [BannerController::class, 'show'])->name('banners.show');



Route::get('/tickets/filter', [TicketController::class, 'filter']);
Route::apiResource('tickets', TicketController::class)->only(['index', 'show']);

Route::get('movie-reviews', [MovieReviewController::class, 'index']);
Route::get('movie-reviews/{movieReview}', [MovieReviewController::class, 'show']);

Route::get('contact', [ContactController::class, 'index'])->name('contact.index');
Route::get('contact/{contact}', [ContactController::class, 'show'])->name('contact.show');

// Payment Callbacks (Không cần auth:sanctum vì đây là callback từ bên thứ ba)
Route::post('/zalopay/callback', [PaymentController::class, 'zalopayCallback']);
Route::get('vnpay-return', [PaymentController::class, 'returnVnpay'])->name('vnpay.return');
Route::post('/momo-ipn', [PaymentController::class, 'paymentIpn']);
Route::get('/handleZalopayRedirect', [PaymentController::class, 'handleZaloPayRedirect'])->name('handleZaloPayRedirect');

//Doanh thu

Route::get('/revenue-by-combo', [ReportController::class, 'revenueByCombo']);//Combo
Route::get('/revenue-by-food', [ReportController::class, 'foodStats']);//food


Route::get('/overview', [OverviewController::class, 'overview']);//overview
Route::get('/seatOccupancyByDay', [OverviewController::class, 'seatOccupancyByDay']);//phần trăm đặt ghế trong 1 ngày của các suất chiếu 
Route::get('/seatOccupancyByMonth', [OverviewController::class, 'seatOccupancyByMonth']);//phần trăm đặt ghế trong 1 tháng của các suất chiếu 

//Thống kê
Route::get('/dashboard', [OverviewController::class, 'card']);// Dashboard Tổng Quan (Overview)
Route::get('/revenue-by-total', [ReportController::class, 'totalRevenue']);//Thống kê Doanh Thu (Revenue Analytics)
Route::get('/revenue-ticket-statistics', [ReportController::class,'ticketStatistics']);//Thống kê Lượt Đặt Vé (Ticket Sales Report)
Route::get('/customer', [ReportController::class,'customer']);//Thống kê Khách Hàng (Customer Insights)
Route::get('/booking-trends', [ReportController::class,'bookingTrends']);//Thống kê Xu Hướng Đặt Vé (Booking Trends)


// Reports & Overview (Thường cần auth, nhưng để public theo yêu cầu đơn giản hóa)
Route::get('/revenue-statistics', [ReportController::class, 'revenueStatistics']);

