<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShowtimeRequest;
use App\Http\Requests\Api\UpdateShowtimeRequest;
use App\Models\Branch;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\MovieVersion;
use App\Models\Room;
use App\Models\Seat;
use App\Models\SeatShowtime;
use App\Models\SeatTemplate;
use App\Models\Showtime;
use App\Models\TypeRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShowtimeController extends Controller
{
    public function __construct()
    {
        // Yêu cầu xác thực bằng Sanctum cho tất cả các phương thức trong controller này
        $this->middleware('auth:sanctum');
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $user = Auth::user();
    //         $branchId = $request->input('branch_id', $user->cinema->branch_id ?? null);
    //         $cinemaId = $request->input('cinema_id', $user->cinema_id ?? null);
    //         $roomId = $request->input('room_id', null);
    //         $date = $request->input('date', now()->format('Y-m-d'));
    //         $isActive = $request->input('is_active', null);

    //         if (!$cinemaId) {
    //             return response()->json(['error' => 'Cinema not found.'], 404);
    //         }

    //         // Query để lấy danh sách các suất chiếu
    //         $showtimesQuery = Showtime::where('cinema_id', $cinemaId)
    //             ->whereDate('date', $date);

    //         // Nếu có branch_id thì lọc theo branch
    //         if ($branchId) {
    //             $showtimesQuery->whereHas('cinema', function ($query) use ($branchId) {
    //                 $query->where('branch_id', $branchId);
    //             });
    //         }

    //         // Nếu có room_id thì lọc theo phòng
    //         if ($roomId) {
    //             $showtimesQuery->where('room_id', $roomId);
    //         }

    //         // Nếu có filter theo trạng thái hoạt động
    //         if ($isActive !== null) {
    //             $showtimesQuery->where('is_active', $isActive);
    //         }

    //         $showtimes = $showtimesQuery->with(['movie', 'room', 'movieVersion'])
    //             ->latest('id')->get();

    //         return response()->json([
    //             'showtimes' => $showtimes,
    //         ], 200);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'message' => 'Unable to fetch showtimes',
    //             'error' => $th->getMessage(),
    //         ], 500);
    //     }
    // }


    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $branchId = $request->input('branch_id', $user->cinema->branch_id ?? null);
            $cinemaId = $request->input('cinema_id', $user->cinema_id ?? null);
            $roomId = $request->input('room_id', null);
            $date = $request->input('date', now()->format('Y-m-d'));
            $isActive = $request->input('is_active', null);

            // Nếu cinema_id là null, không lọc theo cinema_id
            $showtimesQuery = Showtime::whereDate('date', $date);

            // Nếu có cinema_id thì thêm điều kiện lọc theo cinema_id
            if ($cinemaId) {
                $showtimesQuery->where('cinema_id', $cinemaId);
            }

            // Nếu có branch_id thì lọc theo branch
            if ($branchId) {
                $showtimesQuery->whereHas('cinema', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                });
            }

            // Nếu có room_id thì lọc theo phòng
            if ($roomId) {
                $showtimesQuery->where('room_id', $roomId);
            }

            // Nếu có filter theo trạng thái hoạt động
            if ($isActive !== null) {
                $showtimesQuery->where('is_active', $isActive);
            }

            $showtimes = $showtimesQuery->with(['movie', 'room', 'movieVersion'])
                ->latest('id')->get();

            return response()->json([
                'showtimes' => $showtimes,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Unable to fetch showtimes',
                'error' => $th->getMessage(),
            ], 500);
        }
    }



    // public function store(StoreShowtimeRequest $request)
    // {
    //     try {
    //         $createdShowtimes = [];

    //         DB::transaction(function () use ($request, &$createdShowtimes) {
    //             $movie = Movie::find($request->movie_id);
    //             if (!$movie) {
    //                 throw new \Exception("Movie not found.");
    //             }

    //             $movieDuration = $movie->duration ?? 0;
    //             $cleaningTime = Showtime::CLEANINGTIME;

    //             $room = Room::find($request->room_id);
    //             if (!$room) {
    //                 throw new \Exception("Room not found.");
    //             }

    //             $typeRoom = TypeRoom::find($room->type_room_id);
    //             if (!$typeRoom) {
    //                 throw new \Exception("Room type not found.");
    //             }

    //             $movieVersion = MovieVersion::find($request->movie_version_id);
    //             if (!$movieVersion) {
    //                 throw new \Exception("Movie version not found.");
    //             }

    //             $user = auth()->user();
    //             $cinemaId = $request->cinema_id ?? $user->cinema_id;
    //             $date = $request->date;

    //             Log::info('Dữ liệu Movie Version:', ['id' => $request->movie_version_id, 'version' => $movieVersion]);

    //             $format = trim(($typeRoom ? $typeRoom->name : 'Không xác định') . ' ' . ($movieVersion ? $movieVersion->name : 'Không xác định'));
    //             Log::info('Định dạng suất chiếu được tạo: ' . $format);

    //             if (filter_var($request->input('auto_generate_showtimes'), FILTER_VALIDATE_BOOLEAN) === true) {
    //                 $startHour = Carbon::parse($date . ' ' . $request->start_hour);
    //                 $endHour = Carbon::parse($date . ' ' . $request->end_hour);

    //                 $existingShowtimes = Showtime::where('room_id', $room->id)
    //                     ->where('date', $date)
    //                     ->orderBy('start_time')
    //                     ->get();

    //                 $currentStartTime = $startHour;

    //                 while ($currentStartTime->copy()->addMinutes($movieDuration + $cleaningTime)->lt($endHour)) {
    //                     $currentEndTime = $currentStartTime->copy()->addMinutes($movieDuration + $cleaningTime);

    //                     $isOverlapping = false;
    //                     foreach ($existingShowtimes as $showtime) {
    //                         $existingStart = Carbon::parse($showtime->start_time);
    //                         $existingEnd = Carbon::parse($showtime->end_time);

    //                         if ($currentStartTime->lt($existingEnd) && $currentEndTime->gt($existingStart)) {
    //                             $isOverlapping = true;
    //                             break;
    //                         }
    //                     }

    //                     if (!$isOverlapping) {
    //                         $newShowtime = Showtime::create([
    //                             'cinema_id' => $cinemaId,
    //                             'room_id' => $room->id,
    //                             'slug' => Showtime::generateCustomRandomString(),
    //                             'format' => $format,
    //                             'movie_version_id' => $request->movie_version_id,
    //                             'movie_id' => $request->movie_id,
    //                             'date' => $date,
    //                             'start_time' => $currentStartTime->format('Y-m-d H:i'),
    //                             'end_time' => $currentEndTime->format('Y-m-d H:i'),
    //                             'is_active' => true,
    //                         ]);
    //                         $createdShowtimes[] = $newShowtime;
    //                     }

    //                     $currentStartTime = $currentEndTime;
    //                 }
    //             } else {

    //                 $showtimes = $request->input('showtimes');

    //                 // $showtimes = json_decode($showtimesInput, true);

    //                 // if (!is_array($showtimes)) {
    //                 //     throw new \Exception("Danh sách suất chiếu không hợp lệ.");
    //                 // }

    //                 foreach ($showtimes as $showtimeData) {
    //                     $startTime = Carbon::parse($date . ' ' . $showtimeData['start_time']);
    //                     $endTime = $startTime->copy()->addMinutes($movieDuration + $cleaningTime);

    //                     $existingShowtimes = Showtime::where('room_id', $room->id)
    //                         ->where('date', $date)
    //                         ->get();

    //                     foreach ($existingShowtimes as $existing) {
    //                         $existingStart = Carbon::parse($existing->start_time);
    //                         $existingEnd = Carbon::parse($existing->end_time);

    //                         if ($startTime->lt($existingEnd) && $endTime->gt($existingStart)) {
    //                             throw new \Exception("Suất chiếu bị trùng với suất chiếu từ {$existingStart->format('H:i')} - {$existingEnd->format('H:i')}");
    //                         }
    //                     }

    //                     $newShowtime = Showtime::create([
    //                         'cinema_id' => $cinemaId,
    //                         'room_id' => $room->id,
    //                         'slug' => Showtime::generateCustomRandomString(),
    //                         'format' => ($typeRoom ? $typeRoom->name : 'Unknown') . ' ' . ($movieVersion ? $movieVersion->name : 'Unknown'),
    //                         'movie_version_id' => $request->movie_version_id,
    //                         'movie_id' => $request->movie_id,
    //                         'date' => $date,
    //                         'start_time' => $startTime->format('Y-m-d H:i'),
    //                         'end_time' => $endTime->format('Y-m-d H:i'),
    //                         'is_active' => true,
    //                     ]);
    //                     $createdShowtimes[] = $newShowtime;
    //                 }
    //             }
    //         });

    //         return response()->json([
    //             'message' => 'Thêm suất chiếu thành công!',
    //             'showtimes' => $createdShowtimes
    //         ], 201);
    //     } catch (\Throwable $th) {
    //         return response()->json(['error' => $th->getMessage()], 500);
    //     }
    // }



    public function store(StoreShowtimeRequest $request)
    {
        try {
            $createdShowtimes = [];

            DB::transaction(function () use ($request, &$createdShowtimes) {
                $movie = Movie::find($request->movie_id);
                if (!$movie) {
                    throw new \Exception("Movie not found.");
                }

                $movieDuration = $movie->duration ?? 0;
                $cleaningTime = Showtime::CLEANINGTIME;

                $room = Room::find($request->room_id);
                if (!$room) {
                    throw new \Exception("Room not found.");
                }

                $typeRoom = TypeRoom::find($room->type_room_id);
                if (!$typeRoom) {
                    throw new \Exception("Room type not found.");
                }

                $movieVersion = MovieVersion::find($request->movie_version_id);
                if (!$movieVersion) {
                    throw new \Exception("Movie version not found.");
                }

                $user = auth()->user();
                $cinemaId = $request->cinema_id ?? $user->cinema_id;
                $date = $request->date;

                Log::info('Dữ liệu Movie Version:', ['id' => $request->movie_version_id, 'version' => $movieVersion]);

                $format = trim(($typeRoom ? $typeRoom->name : 'Không xác định') . ' ' . ($movieVersion ? $movieVersion->name : 'Không xác định'));
                Log::info('Định dạng suất chiếu được tạo: ' . $format);

                if (filter_var($request->input('auto_generate_showtimes'), FILTER_VALIDATE_BOOLEAN) === true) {
                    $startHour = Carbon::parse($date . ' ' . $request->start_hour);
                    $endHour = Carbon::parse($date . ' ' . $request->end_hour);

                    $existingShowtimes = Showtime::where('room_id', $room->id)
                        ->where('date', $date)
                        ->orderBy('start_time')
                        ->get();

                    $currentStartTime = $startHour;

                    while ($currentStartTime->copy()->addMinutes($movieDuration + $cleaningTime)->lt($endHour)) {
                        $currentEndTime = $currentStartTime->copy()->addMinutes($movieDuration + $cleaningTime);

                        $isOverlapping = false;
                        foreach ($existingShowtimes as $showtime) {
                            $existingStart = Carbon::parse($showtime->start_time);
                            $existingEnd = Carbon::parse($showtime->end_time);

                            if ($currentStartTime->lt($existingEnd) && $currentEndTime->gt($existingStart)) {
                                $isOverlapping = true;
                                break;
                            }
                        }

                        if (!$isOverlapping) {
                            $newShowtime = Showtime::create([
                                'cinema_id' => $cinemaId,
                                'room_id' => $room->id,
                                'slug' => Showtime::generateCustomRandomString(),
                                'format' => $format,
                                'movie_version_id' => $request->movie_version_id,
                                'movie_id' => $request->movie_id,
                                'date' => $date,
                                'start_time' => $currentStartTime->format('Y-m-d H:i'),
                                'end_time' => $currentEndTime->format('Y-m-d H:i'),
                                'is_active' => true,
                            ]);

                            // Thêm ghế vào bảng `seat_showtime`
                            $seats = Seat::where('room_id', $room->id)->get();
                            foreach ($seats as $seat) {
                                SeatShowtime::create([
                                    'showtime_id' => $newShowtime->id,
                                    'seat_id' => $seat->id,
                                    'status' => 'available', // Đặt trạng thái mặc định là "available"
                                ]);
                            }

                            $createdShowtimes[] = $newShowtime;
                        }

                        $currentStartTime = $currentEndTime;
                    }
                } else {
                    $showtimes = $request->input('showtimes');

                    foreach ($showtimes as $showtimeData) {
                        $startTime = Carbon::parse($date . ' ' . $showtimeData['start_time']);
                        $endTime = $startTime->copy()->addMinutes($movieDuration + $cleaningTime);

                        $existingShowtimes = Showtime::where('room_id', $room->id)
                            ->where('date', $date)
                            ->get();

                        foreach ($existingShowtimes as $existing) {
                            $existingStart = Carbon::parse($existing->start_time);
                            $existingEnd = Carbon::parse($existing->end_time);

                            if ($startTime->lt($existingEnd) && $endTime->gt($existingStart)) {
                                throw new \Exception("Suất chiếu bị trùng với suất chiếu từ {$existingStart->format('H:i')} - {$existingEnd->format('H:i')}");
                            }
                        }

                        $newShowtime = Showtime::create([
                            'cinema_id' => $cinemaId,
                            'room_id' => $room->id,
                            'slug' => Showtime::generateCustomRandomString(),
                            'format' => ($typeRoom ? $typeRoom->name : 'Unknown') . ' ' . ($movieVersion ? $movieVersion->name : 'Unknown'),
                            'movie_version_id' => $request->movie_version_id,
                            'movie_id' => $request->movie_id,
                            'date' => $date,
                            'start_time' => $startTime->format('Y-m-d H:i'),
                            'end_time' => $endTime->format('Y-m-d H:i'),
                            'is_active' => true,
                        ]);

                        // Thêm ghế vào bảng `seat_showtime`
                        $seats = Seat::where('room_id', $room->id)->get();
                        foreach ($seats as $seat) {
                            SeatShowtime::create([
                                'showtime_id' => $newShowtime->id,
                                'seat_id' => $seat->id,
                                'status' => 'available', // Đặt trạng thái mặc định là "available"
                            ]);
                        }

                        $createdShowtimes[] = $newShowtime;
                    }
                }
            });

            return response()->json([
                'message' => 'Thêm suất chiếu thành công!',
                'showtimes' => $createdShowtimes
            ], 201);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function show(Showtime $showtime)
    {
        try {
            // Tải thông tin liên quan đến suất chiếu
            $showtime->load(['room.cinema', 'room.seatTemplate', 'movieVersion', 'movie', 'seats']);

            // Kiểm tra tổng số ghế trong phòng chiếu
            $totalSeats = $showtime->room->seats()->where('is_active', 1)->count(); // Tính tổng số ghế hoạt động

            // Lấy số ghế đã đặt cho suất chiếu này
            $bookedSeats = SeatShowtime::where('showtime_id', $showtime->id)
                ->where('status', '!=', 'available')
                ->count();

            // Tính số ghế còn lại
            $remainingSeats = $totalSeats - $bookedSeats;

            // Lấy matrixSeat cho phòng chiếu
            $matrixSeat = SeatTemplate::getMatrixById($showtime->room->seatTemplate->matrix_id);

            // Lấy thông tin các ghế cho suất chiếu
            $seats = $showtime->seats;
            $seatMap = [];

            // Xây dựng seatMap dựa trên tọa độ ghế
            foreach ($seats as $seat) {
                $seatMap[$seat->coordinates_y][$seat->coordinates_x] = $seat;
            }

            return response()->json([
                'showtime' => $showtime,
                'matrixSeat' => $matrixSeat,
                'seatMap' => $seatMap,
                'totalSeats' => $totalSeats,
                'remainingSeats' => $remainingSeats,
                'bookedSeats' => $bookedSeats,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    // public function update(UpdateShowtimeRequest $request, Showtime $showtime)
    // {
    //     try {
    //         // Tính toán thời gian bắt đầu và kết thúc của suất chiếu
    //         $movieVersion = MovieVersion::find($request->movie_version_id);
    //         $room = Room::find($request->room_id);
    //         $typeRoom = TypeRoom::find($room->type_room_id);
    //         $movie = Movie::find($request->movie_id);
    //         $movieDuration = $movie->duration ?? 0;
    //         $cleaningTime = Showtime::CLEANINGTIME;
    //         $user = auth()->user();

    //         $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
    //         $endTime = $startTime->copy()->addMinutes($movieDuration + $cleaningTime);

    //         // Chuẩn bị dữ liệu để cập nhật suất chiếu
    //         $dataShowtimes = [
    //             'cinema_id' => $request->cinema_id ?? $showtime->cinema_id,
    //             'room_id' => $request->room_id,
    //             // 'format' => $typeRoom->name . ' ' . $movieVersion->name,
    //             'format' => ($typeRoom ? $typeRoom->name : 'Unknown') . ' ' . ($movieVersion ? $movieVersion->name : 'Unknown'),
    //             'movie_version_id' => $request->movie_version_id,
    //             'movie_id' => $request->movie_id,
    //             'date' => $request->date,
    //             'start_time' => $startTime->format('Y-m-d H:i'),
    //             'end_time' => $endTime->format('Y-m-d H:i'),
    //             // 'is_active' => $request->has('is_active') ? 1 : 0,
    //             'is_active' => $request->has('is_active') ? $request->input('is_active', false) : $showtime->is_active, // Giữ giá trị hiện tại nếu không có trong request
    //         ];

    //         $showtime->update($dataShowtimes);

    //         return response()->json(['message' => 'Cập nhật xuất chiếu thành công!'], 200);
    //     } catch (\Throwable $th) {
    //         return response()->json(['error' => $th->getMessage()], 500);
    //     }
    // }

    /**
     * Remove the specified resource from storage.
     */

    public function update(UpdateShowtimeRequest $request, Showtime $showtime)
    {
        try {
            // Kiểm tra thông tin movie, room, typeRoom, movieVersion
            $movieVersion = MovieVersion::find($request->movie_version_id);
            $room = Room::find($request->room_id);
            $typeRoom = TypeRoom::find($room->type_room_id);
            $movie = Movie::find($request->movie_id);
            $movieDuration = $movie->duration ?? 0;
            $cleaningTime = Showtime::CLEANINGTIME;

            if (!$movie || !$room || !$typeRoom || !$movieVersion) {
                return response()->json(['error' => 'Phim, phòng hoặc phiên bản phim không hợp lệ.'], 400);
            }

            // Tính toán lại thời gian suất chiếu
            $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
            $endTime = $startTime->copy()->addMinutes($movieDuration + $cleaningTime);

            DB::transaction(function () use ($request, $showtime, $room, $typeRoom, $movieVersion, $startTime, $endTime) {
                // Cập nhật suất chiếu
                $showtime->update([
                    'cinema_id' => $request->cinema_id ?? $showtime->cinema_id,
                    'room_id' => $request->room_id,
                    'format' => ($typeRoom ? $typeRoom->name : 'Unknown') . ' ' . ($movieVersion ? $movieVersion->name : 'Unknown'),
                    'movie_version_id' => $request->movie_version_id,
                    'movie_id' => $request->movie_id,
                    'date' => $request->date,
                    'start_time' => $startTime->format('Y-m-d H:i'),
                    'end_time' => $endTime->format('Y-m-d H:i'),
                    'is_active' => $request->has('is_active') ? $request->input('is_active', false) : $showtime->is_active,
                ]);

                // Nếu thay đổi phòng chiếu, cần cập nhật danh sách ghế trong `seat_showtime`
                if ($showtime->wasChanged('room_id')) {
                    // Xóa ghế cũ khỏi bảng `seat_showtime`
                    SeatShowtime::where('showtime_id', $showtime->id)->delete();

                    // Lấy danh sách ghế mới theo `room_id`
                    $seats = Seat::where('room_id', $room->id)->get();

                    // Thêm lại vào `seat_showtime`
                    foreach ($seats as $seat) {
                        SeatShowtime::create([
                            'showtime_id' => $showtime->id,
                            'seat_id' => $seat->id,
                            'status' => 'available',
                        ]);
                    }
                }
            });

            return response()->json(['message' => 'Cập nhật suất chiếu thành công!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Lỗi cập nhật: ' . $th->getMessage()], 500);
        }
    }

    public function destroy(Showtime $showtime)
    {
        try {
            $timeNow = now();

            // Kiểm tra xem suất chiếu đã diễn ra chưa
            if ($timeNow->greaterThan($showtime->start_time)) {
                return response()->json(['error' => 'Không thể xóa! Suất chiếu đã diễn ra.'], 400);
            }

            // Kiểm tra nếu có ghế đã đặt (không phải "available")
            $hasBookedSeats = SeatShowtime::where('showtime_id', $showtime->id)
                ->where('status', '!=', 'available')
                ->exists();

            if ($hasBookedSeats) {
                return response()->json(['error' => 'Không thể xóa! Suất chiếu này đã có người đặt vé.'], 400);
            }

            DB::transaction(function () use ($showtime) {
                // Xóa tất cả ghế trong `seat_showtime`
                SeatShowtime::where('showtime_id', $showtime->id)->delete();

                // Xóa suất chiếu
                $showtime->delete();
            });

            return response()->json(['message' => 'Xóa suất chiếu thành công!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Lỗi xóa: ' . $th->getMessage()], 500);
        }
    }
}
