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

            //Phim đang chiếu
            $moviesShowing = Movie::where([
                ['is_active', '1'],
                ['is_publish', '1'],
                ['release_date', '<=', $currentNow],
                ['end_date', '>=', $currentNow]
            ])
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
                ->orderBy('is_hot', 'desc')
                ->latest('id')
                ->limit(8)
                ->get();

            // Phim suất chiếu đặc biệt
            $moviesSpecial = Movie::where([
                ['is_active', '1'],
                ['is_publish', '1'],
                ['is_special', '1'],
                ['end_date', '<', $currentNow], // Phim đã hết thời gian chiếu
            ])
                ->orWhere([
                    ['is_active', '1'],
                    ['is_publish', '1'],
                    ['is_special', '1'], // chỉ lấy được phim đánh dấu đặc biệt
                    ['release_date', '>', $currentNow]
                ]) // Phim sắp chiếu
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
            ]);
        }
    }
}
