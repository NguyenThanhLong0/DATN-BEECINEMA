<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovieReview;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Exception;

class MovieReviewController extends Controller
{
    public function __construct()
    {
        // Chỉ các phương thức store, update, destroy yêu cầu xác thực
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        try {
            $reviews = MovieReview::with(['movie', 'user'])->get();
            return response()->json($reviews);
        } catch (Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi lấy dữ liệu.'], 500);
        }
    }

    // Tạo đánh giá phim mới

    public function store(Request $request)
    {
        try {
            $request->validate([
                'movie_id' => 'required|exists:movies,id',
                'rating' => 'required|integer|min:1|max:10',
                'description' => 'required|string',
            ]);

            $userId = auth()->id();

            // Kiểm tra xem người dùng đã xem phim này chưa
            $hasWatched = Ticket::where('user_id', $userId)
                ->where('movie_id', $request->movie_id)
                ->where('status', 'đã xuất vé')  // Chỉ tính những vé đã được xuất
                ->exists();

            if (!$hasWatched) {
                return response()->json(['error' => 'Bạn phải xem phim này trước khi đánh giá.'], 403);
            }

            $review = MovieReview::create([
                'movie_id' => $request->movie_id,
                'user_id' => $userId,
                'rating' => $request->rating,
                'description' => $request->description,
            ]);

            return response()->json([
                'message' => 'Đánh giá đã được thêm thành công!',
                'review' => $review
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function show(MovieReview $movieReview)
    {
        try {
            return response()->json($movieReview->load(['movie', 'user']));
        } catch (Exception $e) {
            return response()->json(['error' => 'Không tìm thấy đánh giá này.'], 404);
        }
    }

    // Cập nhật đánh giá phim
    public function update(Request $request, MovieReview $movieReview)
    {
        try {
            $request->validate([
                'rating' => 'integer|min:1|max:10',
                'description' => 'string',
            ]);

            $movieReview->update($request->all());

            return response()->json([
                'message' => 'Đánh giá đã được cập nhật thành công!',
                'review' => $movieReview
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi cập nhật đánh giá.'], 500);
        }
    }

    // Xóa đánh giá phim
    public function destroy(MovieReview $movieReview)
    {
        try {
            $movieReview->delete();
            return response()->json([
                'message' => 'Đánh giá đã được xóa thành công!'
            ], 204);
        } catch (Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi xóa đánh giá.'], 500);
        }
    }
}
