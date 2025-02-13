<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMovieRequest;
use App\Http\Requests\Api\UpdateMovieRequest;
use App\Models\Movie;
use App\Models\MovieVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    const PATH_UPLOAD = 'movies';

    /**
     * Hiển thị danh sách các bộ phim.
     */
    public function index(Request $request)
    {
        try {
            // Lấy danh sách phim, lọc theo tab đã chọn
            $selectedTab = $request->get('tab', 'publish');
            $moviesQuery = Movie::query();

            // Lọc theo trạng thái xuất bản
            if ($selectedTab === 'publish') {
                $moviesQuery->where('is_publish', 1);
            } elseif ($selectedTab === 'unpublish') {
                $moviesQuery->where('is_publish', 0);
            }

            // Phân trang kết quả
            $movies = $moviesQuery->latest()->paginate(10);

            return response()->json($movies);
        } catch (\Throwable $th) {
            // Trả về lỗi nếu không thể lấy danh sách phim
            return response()->json(['message' => 'Không thể lấy danh sách phim'], 500);
        }
    }

    
    //  * Tạo mới một bộ phim.
     
    // public function store(StoreMovieRequest $request)
    // {
    //     try {
    //         // Lấy dữ liệu từ request và chuẩn bị dữ liệu cho movie
    //         $dataMovie = $request->only([
    //             'name',
    //             'category',
    //             'description',
    //             'director',
    //             'cast',
    //             'rating',
    //             'duration',
    //             'release_date',
    //             'end_date',
    //             'trailer_url',
    //             'surcharge',
    //             'surcharge_desc'
    //         ]);

    //         // Cài đặt các trường boolean
    //         $dataMovie['is_active'] = $request->has('is_active') ? 1 : 0;
    //         $dataMovie['is_hot'] = $request->has('is_hot') ? 1 : 0;

    //         // Sinh slug tự động từ tên phim
    //         // $dataMovie['slug'] = (new Movie)->getSlugFromName($request->name);

    //         // Kiểm tra xem có muốn xuất bản ngay không
    //         if ($request->action === 'publish') {
    //             $dataMovie['is_publish'] = 1;
    //         }

    //         // Thực hiện giao dịch để đảm bảo tính toàn vẹn dữ liệu
    //         DB::transaction(function () use ($request, $dataMovie) {
    //             // Xử lý ảnh thumbnail nếu có
    //             if ($request->hasFile('img_thumbnail')) {
    //                 $dataMovie['img_thumbnail'] = Storage::put(self::PATH_UPLOAD, $request->file('img_thumbnail'));
    //             }

    //             // Tạo phim mới
    //             $movie = Movie::create($dataMovie);

    //             // Thêm các phiên bản của bộ phim
    //             foreach ($request->versions ?? [] as $version) {
    //                 MovieVersion::create([
    //                     'movie_id' => $movie->id,
    //                     'name' => $version
    //                 ]);
    //             }
    //         });

    //         return response()->json(['message' => 'Thêm phim mới thành công!', 'movie' => $dataMovie], 201);
    //     } catch (\Throwable $th) {
    //         // Trả về lỗi nếu thêm phim thất bại
    //         return response()->json(['message' => 'Thêm phim thất bại!', 'error' => $th->getMessage()], 500);
    //     }
    // }

    public function store(StoreMovieRequest $request)
    {
        try {
            // Chuẩn bị dữ liệu phim
            $dataMovie = $request->only([
                'name', 'category', 'description', 'director', 'cast', 'rating',
                'duration', 'release_date', 'end_date', 'trailer_url', 'surcharge',
                'surcharge_desc', 'img_thumbnail'
            ]);

            // Cài đặt trạng thái
            $dataMovie['is_active'] = $request->has('is_active') ? 1 : 0;
            $dataMovie['is_hot'] = $request->has('is_hot') ? 1 : 0;
            $dataMovie['is_publish'] = $request->action === 'publish' ? 1 : 0;

            // Sinh slug tự động từ tên phim
            // $dataMovie['slug'] = Str::slug($request->name);

            DB::transaction(function () use ($request, $dataMovie) {
                // Tạo phim mới
                $movie = Movie::create($dataMovie);

                // Thêm các phiên bản của bộ phim
                foreach ($request->versions ?? [] as $version) {
                    MovieVersion::create([
                        'movie_id' => $movie->id,
                        'name' => $version
                    ]);
                }
            });

            return response()->json(['message' => 'Thêm phim mới thành công!', 'movie' => $dataMovie], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Thêm phim thất bại!', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Hiển thị chi tiết của một bộ phim.
     */
    public function show(Movie $movie)
    {
        try {
            // Lấy các phiên bản phim
            $movieVersions = $movie->movieVersions()->pluck('name')->all();

            // Lấy các đánh giá của bộ phim
            // $movieReviews = $movie->movieReview()->get();
            // $totalReviews = $movieReviews->count();
            // $averageRating = $totalReviews > 0 ? $movieReviews->avg('rating') : 0;

            // // Đếm số sao của từng mức đánh giá
            // $starCounts = [];
            // for ($i = 1; $i <= 10; $i++) {
            //     $starCounts[$i] = $movieReviews->where('rating', $i)->count();
            // }

            // Trả về thông tin chi tiết bộ phim
            return response()->json([
                'movie' => $movie,
                'movieVersions' => $movieVersions,
                // 'totalReviews' => $totalReviews,
                // 'averageRating' => $averageRating,
                // 'starCounts' => $starCounts
            ]);
        } catch (\Throwable $th) {
            // Trả về lỗi nếu không thể lấy thông tin phim
            return response()->json(['message' => 'Không thể lấy thông tin phim!'], 500);
        }
    }

    /**
     * Cập nhật thông tin của một bộ phim.
     */


    public function update(UpdateMovieRequest $request, Movie $movie)
    {
        try {
            // Lưu lại đường dẫn của ảnh hiện tại trước khi cập nhật
            $oldImage = $movie->img_thumbnail;
    
            // Chuẩn bị dữ liệu cập nhật từ request (loại trừ ảnh)
            $dataMovie = $request->except('img_thumbnail');
    
            // Kiểm tra nếu name thay đổi, đặt slug = null để Sluggable tự động tạo lại
            if ($request->has('name') && $request->name !== $movie->getOriginal('name')) {
                $dataMovie['slug'] = null;
            }
    
            // Kiểm tra trạng thái xuất bản
            if ($request->action === 'publish') {
                $dataMovie['is_publish'] = 1;
            }
    
            DB::transaction(function () use ($dataMovie, $request, $movie, $oldImage) {
                // Nếu có ảnh mới, xử lý ảnh mới trước khi cập nhật dữ liệu
                if ($request->hasFile('img_thumbnail')) {
                    // Lưu ảnh mới vào Storage
                    $newImage = Storage::put(self::PATH_UPLOAD, $request->file('img_thumbnail'));
    
                    if ($newImage) {
                        // Nếu ảnh mới lưu thành công, cập nhật đường dẫn ảnh mới vào dữ liệu
                        $dataMovie['img_thumbnail'] = $newImage;
    
                        // Xóa ảnh cũ nếu tồn tại
                        if ($oldImage && Storage::exists($oldImage)) {
                            Storage::delete($oldImage);
                        }
                    }
                }
    
                // Cập nhật dữ liệu phim
                $movie->update($dataMovie);
    
                // Cập nhật các phiên bản phim
                $movie->movieVersions()->delete();
                foreach ($request->versions ?? [] as $version) {
                    MovieVersion::create([
                        'movie_id' => $movie->id,
                        'name' => $version
                    ]);
                }
            });
    
            return response()->json([
                'message' => 'Cập nhật phim thành công!',
                'movie' => $movie->fresh()
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Cập nhật phim thất bại!',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    

    /**
     * Xóa một bộ phim.
     */
    public function destroy(Movie $movie)
    {
        try {
            if (!$movie->is_publish || $movie->showtimes()->doesntExist()) {
                $movie->delete();
                return response()->json(['message' => 'Xóa phim thành công!'], 200);
            }

            return response()->json(['message' => 'Phim đã được xuất bản & có suất chiếu, không thể xóa!'], 400);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Xóa phim thất bại!', 'error' => $th->getMessage()], 500);
        }
    }
    

    /**
     * Cập nhật tab đã chọn.
     */
    public function selectedTab(Request $request)
    {
        // Lưu tab đã chọn vào session
        $tabKey = $request->tab_key;
        session(['movies.selected_tab' => $tabKey]);

        // Trả về phản hồi xác nhận
        return response()->json(['message' => 'Tab saved', 'tab' => $tabKey]);
    }
}
