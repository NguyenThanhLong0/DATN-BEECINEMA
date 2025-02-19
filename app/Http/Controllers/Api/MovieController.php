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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class MovieController extends Controller
{
    const PATH_UPLOAD = 'movies';

    /**
     * Hiển thị danh sách các bộ phim.
     */
    public function index(Request $request)
    {
        try {
//             $selectedTab = $request->get('tab', 'publish');
//             $moviesQuery = Movie::query();

//             if ($selectedTab === 'publish') {
//                 $moviesQuery->where('is_publish', 1);
//             } elseif ($selectedTab === 'unpublish') {
//                 $moviesQuery->where('is_publish', 0);
//             }

//             $movies = $moviesQuery->latest()->paginate(10);

            // Sử dụng vòng lặp 
          
            $movies = Movie::latest()->paginate(10);
            foreach ($movies as $movie) {
                $movie->movieVersions = $movie->movieVersions()->pluck('name')->toArray();
            }

            return response()->json($movies);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Không thể lấy danh sách phim'], 500);
        }
    }




    public function store(StoreMovieRequest $request)
    {
        try {
            // Ghi log dữ liệu đầu vào
            Log::info('Movie Store Request:', $request->all());

            DB::transaction(function () use ($request, &$movie) {
                // Chuẩn bị dữ liệu phim
                $dataMovie = $request->only([
                    'name',
                    'category',
                    'description',
                    'director',
                    'cast',
                    'rating',
                    'duration',
                    'release_date',
                    'end_date',
                    'trailer_url',
                    'surcharge',
                    'surcharge_desc',
                    'img_thumbnail'
                ]);

                // Cài đặt trạng thái
                $dataMovie['is_active'] = $request->has('is_active') ? 1 : 0;
                $dataMovie['is_hot'] = $request->has('is_hot') ? 1 : 0;
                $dataMovie['is_special'] = $request->has('is_special') ? 1 : 0;
                $dataMovie['is_publish'] = $request->action === 'publish' ? 1 : 0;


                // Tạo phim mới
                $movie = Movie::create($dataMovie);
                Log::debug($movie);
                // Thêm các phiên bản của bộ phim nếu có
                if ($request->has('versions')) {
                    $movieVersions = collect($request->versions)->map(function ($version) use ($movie) {
                        return [
                            'movie_id' => $movie->id,
                            'name' => $version
                        ];
                    });
                    MovieVersion::insert($movieVersions->toArray());
                }
            });

            return response()->json([
                'message' => 'Thêm phim mới thành công!',
                'movie' => $movie->fresh()
            ], 201);
        } catch (\Throwable $th) {
            Log::error('Lỗi thêm phim:', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Thêm phim thất bại!',
                'error' => $th->getMessage(),
                'trace' => $th->getTrace()
            ], 500);
        }
    }

    /**
     * Hiển thị chi tiết của một bộ phim.
     */
    public function show(Movie $movie)
    {
        try {
            // Lấy các phiên bản phim (lấy tất thì dùng cái dưới)
            $movieVersions = $movie->movieVersions()->pluck('name')->all();
            // $movieVersions = $movie->load('movieVersions');

            // Lấy các đánh giá của bộ phim
            // $movieReviews = $movie->movieReview()->get();
            // $totalReviews = $movieReviews->count();
            // $averageRating = $totalReviews > 0 ? $movieReviews->avg('rating') : 0;

            // // Đếm số sao của từng mức đánh giá
            // $starCounts = [];
            // for ($i = 1; $i <= 10; $i++) {
            //     $starCounts[$i] = $movieReviews->where('rating', $i)->count();
            // }
            $movie->movieVersions = $movieVersions;
            // Trả về thông tin chi tiết bộ phim
            return response()->json([
                'movie' => $movie
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
            // Lấy dữ liệu từ request
            $dataMovie = $request->only([
                'name',
                'category',
                'description',
                'director',
                'cast',
                'rating',
                'img_thumbnail',
                'trailer_url',
                'surcharge',
                'surcharge_desc',
                'is_active',
                'is_hot',
                'is_special',
                'is_publish'
            ]);

            // Không cho phép cập nhật duration, start_date, end_date nếu phim đã xuất bản
            if (!$movie->is_publish) {
                $dataMovie['duration'] = $request->duration;
                $dataMovie['release_date'] = $request->release_date;
                $dataMovie['end_date'] = $request->end_date;
            } else {
                Log::warning("Cập nhật bị từ chối: Không thể chỉnh sửa thời lượng, ngày bắt đầu và kết thúc khi phim đã xuất bản.");
            }

            // Kiểm tra nếu name thay đổi, đặt slug = null để Sluggable tự động tạo lại
            if ($request->has('name') && $request->name !== $movie->getOriginal('name')) {
                $dataMovie['slug'] = null;
            }

            // Nếu nhấn nút xuất bản, cập nhật trạng thái is_publish
            if ($request->action === 'publish') {
                $dataMovie['is_publish'] = 1;
            }

            // Cập nhật dữ liệu phim
            $movie->update($dataMovie);

            // Không cho phép cập nhật versions nếu phim đã xuất bản
            if (!$movie->is_publish) {
                $movie->movieVersions()->delete(); // Xóa phiên bản cũ
                foreach ($request->versions ?? [] as $version) {
                    MovieVersion::create([
                        'movie_id' => $movie->id,
                        'name' => $version
                    ]);
                }
            } else {
                Log::warning("Cập nhật bị từ chối: Không thể chỉnh sửa versions khi phim đã xuất bản.");
            }

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


    public function destroy(Movie $movie)
    {
        try {

            if (!$movie->is_publish) {
                $movie->delete();
                return response()->json(['message' => 'Xóa phim thành công!'], 200);
            }
            return response()->json(['message' => 'Phim đã được xuất bản, không thể xóa!'], 400);

            // thêm showtime thì dùng cái dưới
            // if (!$movie->is_publish || $movie->showtimes()->doesntExist()) {
            //     $movie->delete();
            //     return response()->json(['message' => 'Xóa phim thành công!'], 200);
            // }
            // return response()->json(['message' => 'Phim đã được xuất bản & có suất chiếu, không thể xóa!'], 400);
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

    public function updateActive(Request $request)
    {
        try {
            $movie = Movie::findOrFail($request->id);

            $movie->is_active = $request->is_active;
            $movie->save();

            return response()->json(['success' => true, 'message' => 'Cập nhật thành công.', 'data' => $movie]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại.']);
        }
    }

    public function updateHot(Request $request)
    {
        try {
            $movie = Movie::findOrFail($request->id);

            $movie->is_hot = $request->is_hot;
            $movie->save();

            return response()->json(['success' => true, 'message' => 'Cập nhật thành công.', 'data' => $movie]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại.']);
        }
    }
    // movie client
    public function moviesClientHome()
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
            ['release_date', '>', $currentNow]]) // Phim sắp chiếu
        ->orderBy('is_hot', 'desc')
        ->latest('id')
        ->limit(8)
        ->get();

        // Trả về 3 danh sách phim dưới dạng JSON
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
