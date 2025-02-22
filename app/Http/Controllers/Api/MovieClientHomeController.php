<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\Request;

class MovieClientHomeController extends Controller
{
    //
    // movie client
    public function index()
    {
        try {

            $currentNow = now();

            // Phim đang chiếu
            $moviesShowing = Movie::where([
                ['is_active', '1'],
                ['is_publish', '1'],
                ['release_date', '<=', $currentNow],
                ['end_date', '>=', $currentNow]
            ])
                ->withCount(['showtimes' => function ($query) use ($currentNow) {
                    $query->where('is_active', 1)
                        ->where('start_time', '>', $currentNow);
                }])
                ->having('showtimes_count', '>', 0) // Chỉ lấy phim có suất chiếu hợp lệ
                ->orderBy('is_hot', 'desc')
                ->latest('id')
                ->limit(8)
                ->get();
            // Phim sắp chiếu
            $moviesUpcoming = Movie::where([
                ['is_active', '1'],
                ['is_publish', '1'],
                ['release_date', '>', $currentNow]
            ])
                ->withCount(['showtimes' => function ($query) use ($currentNow) {
                    $query->where('is_active', 1)
                        ->where('start_time', '>', $currentNow);
                }])
                ->having('showtimes_count', '>', 0) // Chỉ lấy phim có suất chiếu hợp lệ
                ->orderBy('is_hot', 'desc')
                ->latest('id')
                ->limit(8)
                ->get();

            // Phim suất chiếu đặc biệt (Chỉ lấy phim có suất chiếu hợp lệ)
            $moviesSpecial = Movie::where([
                ['is_active', '1'],
                ['is_publish', '1'],
                ['is_special', '1']
            ])
                ->where(function ($query) use ($currentNow) {
                    $query->where('end_date', '<', $currentNow) // Phim đã hết thời gian chiếu
                        ->orWhere('release_date', '>', $currentNow); // Hoặc phim sắp chiếu
                })
                ->withCount(['showtimes' => function ($query) use ($currentNow) {
                    $query->where('is_active', 1)
                        ->where('start_time', '>', $currentNow);
                }])
                ->having('showtimes_count', '>', 0) // Chỉ lấy phim có suất chiếu hợp lệ
                ->orderBy('is_hot', 'desc')
                ->latest('id')
                ->limit(8)
                ->get();
            // Trả về 3 danh sách phim dưới dạng JSON.
            return response()->json([
                'message' => 'hiển thị thành công!',
                'status' => true,
                'moviesShowing' => $moviesShowing,
                'moviesUpcoming' => $moviesUpcoming,
                'moviesSpecial' => $moviesSpecial
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'hiển thị không thất bại!',
                'status' => false,
            ], 500);
        }
    }
}
