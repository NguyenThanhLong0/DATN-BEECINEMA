<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CinemaController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\SeatTemplateController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\TypeRoomController;
use App\Http\Controllers\Api\VoucherApiController;
use App\Http\Controllers\Api\TypeSeatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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


//branchers

Route::get('branches',              [BranchController::class, 'index'])->name('branches.index');

Route::post('branches',             [BranchController::class, 'store'])->name('branches.store');

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

//SeatTemplate

// Route::apiResource('seat-templates', SeatTemplateController::class);

Route::get('/seat-templates', [SeatTemplateController::class, 'index']);

Route::post('/seat-templates', [SeatTemplateController::class, 'store']);

Route::get('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'show']);

Route::put('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'update']);

Route::patch('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'update']);

Route::delete('/seat-templates/{seatTemplate}', [SeatTemplateController::class, 'destroy']);

Route::patch('seat-templates/change-active/{seatTemplate}', [SeatTemplateController::class, 'changeActive']);


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

Route::patch('rooms/update-active/{room}',      [RoomController::class, 'updateActive']);
// cập nhật is_active thì nhập cả id = ...; is_active = ...; _method = patch


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
    Route::post('/', [VoucherApiController::class, 'store']); // Tạo mới voucher
    Route::get('{id}', [VoucherApiController::class, 'show']); // Lấy chi tiết voucher
    Route::put('{id}', [VoucherApiController::class, 'update']); // Cập nhật voucher
    Route::delete('{id}', [VoucherApiController::class, 'destroy']); // Xóa voucher

});

//Type Seat

Route::get('/type-seats', [TypeSeatController::class, 'index']);

Route::post('/type-seats', [TypeSeatController::class, 'store']);

Route::get('/type-seats/{typeSeat}', [TypeSeatController::class, 'show']);

Route::put('/type-seats/{typeSeat}', [TypeSeatController::class, 'update']);

Route::patch('/type-seats/{typeSeat}', [TypeSeatController::class, 'update']);

Route::delete('/type-seats/{typeSeat}', [TypeSeatController::class, 'destroy']);

