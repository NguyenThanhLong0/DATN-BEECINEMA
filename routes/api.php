<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CinemaController;
use App\Http\Controllers\Api\SeatTemplateController;
use App\Http\Controllers\Api\PostApiController;
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