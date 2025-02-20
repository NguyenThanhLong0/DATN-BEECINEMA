<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CinemaController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\ComboFoodController;
use App\Http\Controllers\Api\MovieReviewController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\MovieClientHomeController;
use App\Http\Controllers\Api\SeatTemplateController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\RankController;
use App\Http\Controllers\Api\TypeRoomController;
use App\Http\Controllers\Api\VoucherApiController;
use App\Http\Controllers\Api\TypeSeatController;
use App\Http\Controllers\Api\ShowtimeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});



//branchers

Route::get('branches',              [BranchController::class, 'index'])->name('branches.index');

Route::post('branches',             [BranchController::class, 'store'])->name('branches.store');

Route::get('branches/active',  [BranchController::class, 'branchesWithCinemasActive'])->name('branches.branchesWithCinemasActive');

Route::get('branches/{branch}',     [BranchController::class, 'show'])->name('branches.show');

Route::put('branches/{branch}',     [BranchController::class, 'update'])->name('branches.update');

Route::patch('branches/{branch}',   [BranchController::class, 'update'])->name('branches.update.partial');

Route::delete('branches/{branch}',  [BranchController::class, 'destroy'])->name('branches.destroy');




//cinemas

Route::get('cinemas',               [CinemaController::class, 'index'])->name('cinemas.index');

Route::post('cinemas',              [CinemaController::class, 'store'])->name('cinemas.store');

Route::get('cinemas/{cinema}',      [CinemaController::class, 'show'])->name('cinemas.show');

Route::put('cinemas/{cinema}',      [CinemaController::class, 'update'])->name('cinemas.update');
Route::patch('cinemas/{cinema}',    [CinemaController::class, 'update'])->name('cinemas.update.partial');

Route::delete('cinemas/{cinema}',   [CinemaController::class, 'destroy'])->name('cinemas.destroy');


//foods

Route::get('foods',               [FoodController::class, 'index'])->name('foods.index');

Route::post('foods',              [FoodController::class, 'store'])->name('foods.store');

Route::get('foods/{food}',      [FoodController::class, 'show'])->name('foods.show');

Route::put('foods/{food}',      [FoodController::class, 'update'])->name('foods.update');

Route::patch('/foods/{food}', [FoodController::class, 'update'])->name('foods.update.partial');;


Route::delete('foods/{food}',   [FoodController::class, 'destroy'])->name('foods.destroy');

//ranks

Route::get('ranks',               [RankController::class, 'index'])->name('ranks.index');

Route::post('ranks',              [RankController::class, 'store'])->name('ranks.store');

Route::get('ranks/{rank}',      [RankController::class, 'show'])->name('ranks.show');

Route::put('ranks/{rank}',      [RankController::class, 'update'])->name('ranks.update');

Route::patch('/ranks/{rank}', [RankController::class, 'update'])->name('ranks.update.partial');;


Route::delete('ranks/{rank}',   [RankController::class, 'destroy'])->name('ranks.destroy');

//SeatTemplate

// Route::apiResource('seat-templates', SeatTemplateController::class);

Route::get('/seat-templates', [SeatTemplateController::class, 'index']);

Route::post('/seat-templates', [SeatTemplateController::class, 'store']);

Route::get('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'show']);

Route::put('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'update']);

Route::patch('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'update']);

Route::delete('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'destroy']);

Route::patch('seat-templates/change-active/{seatTemplate}', [SeatTemplateController::class, 'changeActive']);

Route::get('seat-templates/matrix/{id}', [SeatTemplateController::class, 'getMatrixById']);

Route::get('getAll-matrix', [SeatTemplateController::class, 'getAllMatrix']);


//Post
Route::prefix('posts')->group(function () {
    Route::get('/', [PostApiController::class, 'index']); // Lấy danh sách bài viết
    Route::post('/', [PostApiController::class, 'store']); // Thêm bài viết
    Route::get('{id}', [PostApiController::class, 'show']); // Xem chi tiết bài viết
    Route::put('{id}', [PostApiController::class, 'update']); // Cập nhật bài viết
    Route::delete('{id}', [PostApiController::class, 'destroy']); // Xóa bài viết
    Route::put('{id}/toggle', [PostApiController::class, 'toggle']); // Bật/tắt trạng thái
});

//Rooms

Route::get('/rooms', [RoomController::class, 'index']);

Route::post('/rooms', [RoomController::class, 'store']);

Route::get('/rooms/{room}', [RoomController::class, 'show']);

Route::put('/rooms/{room}', [RoomController::class, 'update']);

Route::patch('/rooms/{room}', [RoomController::class, 'update']);

Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);



// Combos

Route::get('combos',               [ComboController::class, 'index'])->name('combos.index');

Route::post('combos',              [ComboController::class, 'store'])->name('combos.store');

Route::get('combos/{combo}',      [ComboController::class, 'show'])->name('combos.show');

Route::put('combos/{combo}',      [ComboController::class, 'update'])->name('combos.update');

Route::patch('combos/{combo}',    [ComboController::class, 'update'])->name('combos.update.partial');

Route::delete('combos/{combo}',   [ComboController::class, 'destroy'])->name('combos.destroy');

//Type Room

Route::get('/type-rooms', [TypeRoomController::class, 'index']);

Route::post('/type-rooms', [TypeRoomController::class, 'store']);

Route::get('/type-rooms/{typeRoom}', [TypeRoomController::class, 'show']);

Route::put('/type-rooms/{typeRoom}', [TypeRoomController::class, 'update']);

Route::patch('/type-rooms/{typeRoom}', [TypeRoomController::class, 'update']);

Route::delete('/type-rooms/{typeRoom}', [TypeRoomController::class, 'destroy']);

// Voucher
Route::prefix('vouchers')->group(function () {
    Route::get('/', [VoucherApiController::class, 'index']); // Lấy danh sách voucher
    Route::post('/create', [VoucherApiController::class, 'store']); // Tạo mới voucher
    Route::get('{id}', [VoucherApiController::class, 'show']); // Lấy chi tiết voucher
    Route::put('update/{id}', [VoucherApiController::class, 'update']); // Cập nhật voucher
    Route::delete('destroy/{id}', [VoucherApiController::class, 'destroy']); // Xóa voucher

});

//Type Seat

Route::get('/type-seats', [TypeSeatController::class, 'index']);

Route::post('/type-seats', [TypeSeatController::class, 'store']);

Route::get('/type-seats/{typeSeat}', [TypeSeatController::class, 'show']);

Route::put('/type-seats/{typeSeat}', [TypeSeatController::class, 'update']);

Route::patch('/type-seats/{typeSeat}', [TypeSeatController::class, 'update']);

Route::delete('/type-seats/{typeSeat}', [TypeSeatController::class, 'destroy']);


//Movie

Route::get('/movies', [MovieController::class, 'index']);

Route::post('/movies', [MovieController::class, 'store']);

Route::get('/movies/{movie}', [MovieController::class, 'show']);

Route::put('/movies/{movie}', [MovieController::class, 'update']);

Route::patch('/movies/{movie}', [MovieController::class, 'update']);

Route::delete('/movies/{movie}', [MovieController::class, 'destroy']);

Route::post('movies/update-active',     [MovieController::class, 'updateActive'])->name('movies.update-active');

Route::post('movies/update-hot',        [MovieController::class, 'updateHot'])->name('movies.update-hot');

// Movies client
Route::get('/moviesClientHome', [MovieClientHomeController::class, 'index']);



// combofood

// Route::get('combofoods',               [ComboFoodController::class, 'index'])->name('combofoods.index');


// Route::post('combofoods',              [ComboFoodController::class, 'store'])->name('combofoods.store');

// Route::get('combofoods/{combofood}',      [combofoodController::class, 'show'])->name('combofoods.show');

// Route::put('combofoods/{combofood}',      [combofoodController::class, 'update'])->name('combofoods.update');

// Route::patch('combofoods/{combofood}',    [combofoodController::class, 'update'])->name('combofoods.update.partial');


// Route::post('combofoods',              [ComboFoodController::class, 'store'])->name('combofoods.store');
// Route::get('combofoods/{combofood}',      [combofoodController::class, 'show'])->name('combofoods.show');
// Route::put('combofoods/{combofood}',      [combofoodController::class, 'update'])->name('combofoods.update');
// Route::patch('combofoods/{combofood}',    [combofoodController::class, 'update'])->name('combofoods.update.partial');

// Route::delete('combofoods/{combofood}',   [combofoodController::class, 'destroy'])->name('combofoods.destroy');

//user

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']); // Lấy danh sách user
    Route::get('/users/{id}', [UserController::class, 'show']); // Lấy thông tin user cụ thể
    Route::put('/users/{id}', [UserController::class, 'update']); // Cập nhật user
    Route::patch('/users/{id}', [UserController::class, 'update']); // Cập nhật user
    Route::delete('/users/{id}', [UserController::class, 'destroy']); // Xóa mềm
    Route::post('/users/{id}/restore', [UserController::class, 'restore']); // Khôi phục
    Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDelete']); // Xóa vĩnh viễn
    Route::get('/profile', [UserController::class, 'profile']); // Lấy thông tin user đang đăng nhập

});

//Đăng nhập


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])->middleware('auth:sanctum');
Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');


// banners
Route::get('banners',               [BannerController::class, 'index'])->name('banners.index');
Route::post('banners',              [BannerController::class, 'store'])->name('banners.store');

Route::get('banners/active',      [BannerController::class, 'getActiveBanner'])->name('banners.getActiveBanner');
Route::get('banners/{banner}',      [BannerController::class, 'show'])->name('banners.show');
Route::put('banners/{banner}',      [BannerController::class, 'update'])->name('banners.update');
Route::patch('banners/{banner}',    [BannerController::class, 'update'])->name('banners.update.partial');
Route::delete('banners/{banner}',   [BannerController::class, 'destroy'])->name('banners.destroy');


//Showtime

Route::get('showtimes', [ShowtimeController::class, 'index']);

Route::post('showtimes', [ShowtimeController::class, 'store']);

Route::get('showtimes/{showtime}', [ShowtimeController::class, 'show']);

Route::put('showtimes/{showtime}', [ShowtimeController::class, 'update']);

Route::patch('showtimes/{showtime}', [ShowtimeController::class, 'update']);

Route::delete('showtimes/{showtime}', [ShowtimeController::class, 'destroy']);



//Ticket
Route::apiResource('tickets', TicketController::class);


//MovieReview

Route::get('movie-reviews', [MovieReviewController::class, 'index']);

Route::post('movie-reviews', [MovieReviewController::class, 'store']);

Route::get('movie-reviews/{movieReview}', [MovieReviewController::class, 'show']);

Route::put('movie-reviews/{movieReview}', [MovieReviewController::class, 'update']);

Route::patch('movie-reviews/{movieReview}', [MovieReviewController::class, 'update']);

Route::delete('movie-reviews/{movieReview}', [MovieReviewController::class, 'destroy']);
