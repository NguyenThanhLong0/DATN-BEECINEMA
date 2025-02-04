<?php

use App\Http\Controllers\Api\BranchController;
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
