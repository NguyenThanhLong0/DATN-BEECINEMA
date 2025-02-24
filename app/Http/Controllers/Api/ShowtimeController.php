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

            if ($cinemaId) {
                $showtimesQuery->where('cinema_id', $cinemaId);
            }

            if ($branchId) {
                $showtimesQuery->whereHas('cinema', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                });
            }

            if ($roomId) {
                $showtimesQuery->where('room_id', $roomId);
            }

            if ($isActive !== null) {
                $showtimesQuery->where('is_active', $isActive);
            }

            $showtimes = $showtimesQuery->with(['movie', 'room', 'movieVersion', 'room.seats'])->latest('id')->get();

            // Thêm totalSeats và remainingSeats vào từng showtime
            $showtimes->transform(function ($showtime) {
                $totalSeats = $showtime->room->seats()->where('is_active', 1)->count(); // Tổng ghế hoạt động

                $bookedSeats = SeatShowtime::where('showtime_id', $showtime->id)
                    ->where('status', '!=', 'available')
                    ->count(); // Ghế đã đặt

                $remainingSeats = $totalSeats - $bookedSeats; // Ghế còn trống

                $showtime->totalSeats = $totalSeats;
                $showtime->remainingSeats = $remainingSeats;

                return $showtime;
            });

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

            // Xây dựng seatMap với cấu trúc mong muốn
            $seatMap = [];

            foreach ($seats as $seat) {
                $row = $seat->coordinates_y; // Lấy hàng (A, B, C, ...)
                if (!isset($seatMap[$row])) {
                    $seatMap[$row] = [
                        'row' => $row,
                        'seats' => []
                    ];
                }

                $seatMap[$row]['seats'][] = [
                    'id' => $seat->id,
                    'room_id' => $seat->room_id,
                    'type_seat_id' => $seat->type_seat_id,
                    'coordinates_x' => $seat->coordinates_x,
                    'coordinates_y' => $seat->coordinates_y,
                    'name' => $seat->name,
                    'is_active' => (bool) $seat->is_active,
                    'created_at' => $seat->created_at,
                    'updated_at' => $seat->updated_at,
                    'pivot' => [
                        'showtime_id' => $showtime->id,
                        'seat_id' => $seat->id,
                        'status' => $seat->pivot->status ?? 'available',
                        'price' => $seat->pivot->price ?? null,
                        'user_id' => $seat->pivot->user_id ?? null,
                        'created_at' => $seat->pivot->created_at ?? null,
                        'updated_at' => $seat->pivot->updated_at ?? null
                    ]
                ];
            }

            // Chuyển seatMap từ dạng associative array sang dạng indexed array
            $seatMap = array_values($seatMap);

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


    public function showBySlug($slug)
{
    try {
        $showtime = Showtime::where('slug', $slug)
            ->with(['room.cinema', 'room.seatTemplate', 'movieVersion', 'movie', 'seats'])
            ->firstOrFail(); // Nếu không tìm thấy sẽ tự động trả về 404

        $totalSeats = $showtime->room->seats()->where('is_active', 1)->count();
        $bookedSeats = SeatShowtime::where('showtime_id', $showtime->id)
            ->where('status', '!=', 'available')
            ->count();
        $remainingSeats = $totalSeats - $bookedSeats;
        $matrixSeat = SeatTemplate::getMatrixById($showtime->room->seatTemplate->matrix_id);

        $seatMap = [];
        foreach ($showtime->seats as $seat) {
            $row = $seat->coordinates_y;
            if (!isset($seatMap[$row])) {
                $seatMap[$row] = ['row' => $row, 'seats' => []];
            }
            $seatMap[$row]['seats'][] = [
                'id' => $seat->id,
                'room_id' => $seat->room_id,
                'type_seat_id' => $seat->type_seat_id,
                'coordinates_x' => $seat->coordinates_x,
                'coordinates_y' => $seat->coordinates_y,
                'name' => $seat->name,
                'is_active' => (bool) $seat->is_active,
                'pivot' => [
                    'showtime_id' => $showtime->id,
                    'seat_id' => $seat->id,
                    'status' => $seat->pivot->status ?? 'available',
                    'price' => $seat->pivot->price ?? null,
                ]
            ];
        }

        return response()->json([
            'showtime' => $showtime,
            'matrixSeat' => $matrixSeat,
            'seatMap' => array_values($seatMap),
            'totalSeats' => $totalSeats,
            'remainingSeats' => $remainingSeats,
            'bookedSeats' => $bookedSeats,
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Showtime not found'], 404);
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



    public function pageShowtime(Request $request)
    {
        try {
            // Lấy cinema_id và branch_id từ query string hoặc session
            $cinemaId = $request->query('cinema_id', session('cinema_id'));
            $branchId = $request->query('branch_id', null);

            if (!$cinemaId) {
                return response()->json(['message' => 'Cần có Cinema ID.'], 400);
            }

            // Tìm rạp chiếu phim từ cơ sở dữ liệu
            $cinema = Cinema::where('id', $cinemaId)->firstOrFail();

            // Thời gian hiện tại (cộng 10 phút nếu không phải admin)
            $now = now()->addMinutes(10);
            if (Auth::check() && Auth::user()->type == 'admin') {
                $now = now(); // Admin lấy full dữ liệu
            }

            // Lấy suất chiếu trong 7 ngày tới, có phim đang active
            $showtimesQuery = Showtime::with(['movie' => function ($query) {
                $query->where('is_active', 1); // Chỉ lấy phim đang hoạt động
            }, 'room'])
                ->where([
                    ['cinema_id', $cinemaId],
                    ['is_active', 1],
                    ['start_time', '>', $now] // Chỉ lấy suất chiếu tương lai
                ])
                ->whereBetween('date', [now()->format('Y-m-d'), now()->addDays(7)->format('Y-m-d')]) // Chỉ lấy 7 ngày tới
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Tạo ánh xạ cho các ngày trong tuần
            $dayNames = [
                'Sunday' => 'CN',
                'Monday' => 'T2',
                'Tuesday' => 'T3',
                'Wednesday' => 'T4',
                'Thursday' => 'T5',
                'Friday' => 'T6',
                'Saturday' => 'T7'
            ];

            // Khởi tạo danh sách showtimes
            $showtimes = [];

            foreach ($showtimesQuery as $showtime) {
                $movie = $showtime->movie;
                $room = $showtime->room;
                $format = $showtime->format; // Ví dụ: "2D Phụ Đề", "3D Lồng Tiếng"
                $dateKey = $showtime->date;
                $movieKey = $movie->id;

                // Nếu ngày chưa tồn tại trong danh sách showtimes, khởi tạo
                if (!isset($showtimes[$dateKey])) {
                    // Lấy tên ngày trong tuần bằng tiếng Anh
                    $dayOfWeek = Carbon::parse($dateKey)->format('l');
                    // Định dạng lại ngày theo d/m - T2 (hoặc các ngày khác)
                    $dayLabel = Carbon::parse($dateKey)->format('d/m') . ' - ' . $dayNames[$dayOfWeek];

                    $showtimes[$dateKey] = [
                        "date" => $dateKey,
                        "day_label" => $dayLabel,
                        "movies" => []
                    ];
                }

                // Nếu phim chưa tồn tại trong ngày đó, thêm mới
                if (!isset($showtimes[$dateKey]["movies"][$movieKey])) {
                    $showtimes[$dateKey]["movies"][$movieKey] = [
                        "id" => $movie->id,
                        "name" => $movie->name,
                        "slug" => $movie->slug,
                        "category" => $movie->category,
                        "img_thumbnail" => $movie->img_thumbnail,
                        "description" => $movie->description,
                        "director" => $movie->director,
                        "cast" => $movie->cast,
                        "duration" => $movie->duration,
                        "rating" => $movie->rating,
                        "release_date" => $movie->release_date,
                        "end_date" => $movie->end_date,
                        "trailer_url" => $movie->trailer_url,
                        "surcharge" => $movie->surcharge,
                        "surcharge_desc" => null,
                        "is_active" => $movie->is_active,
                        "is_hot" => $movie->is_hot,
                        "is_special" => $movie->is_special,
                        "is_publish" => $movie->is_publish,
                        "showtimes" => []
                    ];
                }

                // Thêm suất chiếu vào showtimes theo format
                $showtimes[$dateKey]["movies"][$movieKey]["showtimes"][$format][] = [
                    "id" => $showtime->id,
                    "start_time" => Carbon::parse($showtime->start_time)->format('H:i'),
                    "end_time" => Carbon::parse($showtime->end_time)->format('H:i'),
                    "slug" => $showtime->slug,
                    "price" => null,
                    "slug" => $showtime->slug,
                    "created_at" => $showtime->created_at,
                    "updated_at" => $showtime->updated_at,
                    "room" => [
                        "id" => $room->id,
                        "branch_id" => $room->branch_id,
                        "cinema_id" => $room->cinema_id,
                        "type_room_id" => $room->type_room_id,
                        "seat_template_id" => $room->seat_template_id,
                        "name" => $room->name,
                        "is_active" => $room->is_active,
                        "is_publish" => $room->is_publish,
                        "created_at" => $room->created_at,
                        "updated_at" => $room->updated_at,
                    ]
                ];
            }

            // Chuyển danh sách phim và suất chiếu thành mảng
            $formattedShowtimes = array_values($showtimes);
            foreach ($formattedShowtimes as &$date) {
                $date["movies"] = array_values($date["movies"]);
            }

            return response()->json([
                "dates" => [
                    [
                        "cinema" => [
                            "id" => $cinema->id,
                            "branch_id" => $cinema->branch_id,
                            "name" => $cinema->name,
                            "slug" => $cinema->slug,
                            "surcharge" => $cinema->surcharge,
                            "address" => $cinema->address,
                            "description" => $cinema->description,
                            "is_active" => $cinema->is_active,
                            "created_at" => $cinema->created_at,
                            "updated_at" => $cinema->updated_at
                        ],
                        "showtimes" => $formattedShowtimes
                    ]
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "error" => "Không thể lấy thông tin suất chiếu",
                "message" => $th->getMessage()
            ], 500);
        }
    }


    // public function showtimeMovie(Request $request)
    // {
    //     try {
    //         // Lấy movie_id từ query string hoặc session
    //         $movieId = $request->query('movie_id', session('movie_id'));

    //         if (!$movieId) {
    //             return response()->json(['message' => 'Movie ID is required.'], 400);
    //         }

    //         $now = Carbon::now();

    //         // Lấy danh sách suất chiếu của phim theo ngày, có áp dụng điều kiện hiển thị
    //         $showtimesQuery = Showtime::with(['room.cinema'])
    //             ->where('movie_id', $movieId)
    //             ->where('is_active', 1) // Chỉ lấy suất chiếu đang hoạt động
    //             ->whereHas('movie', function ($query) {
    //                 $query->where('is_publish', 1); // Chỉ lấy phim đã được phát hành
    //             })
    //             ->where('start_time', '>', $now) // Chỉ lấy suất chiếu trong tương lai
    //             ->orderBy('date')
    //             ->orderBy('start_time')
    //             ->get();

    //         // Khởi tạo danh sách showtimes theo ngày
    //         $showtimesByDate = [];

    //         foreach ($showtimesQuery as $showtime) {
    //             $dateKey = $showtime->date;
    //             $dayLabel = Carbon::parse($dateKey)->format('d/m - D');
    //             $format = $showtime->format;

    //             // Nếu ngày chưa tồn tại, tạo mới
    //             if (!isset($showtimesByDate[$dateKey])) {
    //                 $showtimesByDate[$dateKey] = [
    //                     "day_id" => "day" . Carbon::parse($dateKey)->dayOfYear,
    //                     "date_label" => $dayLabel,
    //                     "showtimes" => []
    //                 ];
    //             }

    //             // Nếu định dạng suất chiếu chưa tồn tại, tạo mới
    //             if (!isset($showtimesByDate[$dateKey]["showtimes"][$format])) {
    //                 $showtimesByDate[$dateKey]["showtimes"][$format] = [];
    //             }

    //             // Thêm suất chiếu vào danh sách
    //             $showtimesByDate[$dateKey]["showtimes"][$format][] = [
    //                 "id" => $showtime->id,
    //                 "cinema_id" => $showtime->room->cinema_id,
    //                 "room_id" => $showtime->room_id,
    //                 "slug" => $showtime->slug,
    //                 "format" => $showtime->format,
    //                 "movie_version_id" => $showtime->movie_version_id,
    //                 "movie_id" => $showtime->movie_id,
    //                 "date" => $showtime->date,
    //                 "start_time" => $showtime->start_time,
    //                 "end_time" => $showtime->end_time,
    //                 "is_active" => $showtime->is_active,
    //                 "created_at" => $showtime->created_at,
    //                 "updated_at" => $showtime->updated_at
    //             ];
    //         }

    //         // Chuyển danh sách showtimes thành array
    //         $formattedShowtimes = array_values($showtimesByDate);

    //         return response()->json(["showtimes" => $formattedShowtimes], 200);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             "error" => "Unable to fetch showtime data",
    //             "message" => $th->getMessage()
    //         ], 500);
    //     }
    // }


    // public function showtimeMovie(Request $request)
    // {
    //     try {
    //         // Lấy movie_id và cinema_id từ query string hoặc session
    //         $movieId = $request->query('movie_id', session('movie_id'));
    //         $cinemaId = $request->query('cinema_id', session('cinema_id'));

    //         if (!$movieId) {
    //             return response()->json(['message' => 'Movie ID is required.'], 400);
    //         }

    //         // Thời gian hiện tại (có thể có múi giờ)
    //         $now = Carbon::now('Asia/Ho_Chi_Minh');

    //         // Kiểm tra xem người dùng có phải admin không
    //         $isAdmin = auth()->user() && auth()->user()->role;

    //         // Lấy danh sách suất chiếu của phim theo ngày, có áp dụng điều kiện hiển thị
    //         $showtimesQuery = Showtime::with(['room.cinema'])
    //             ->where('movie_id', $movieId)
    //             ->where('is_active', 1) // Chỉ lấy suất chiếu đang hoạt động
    //             ->whereHas('movie', function ($query) {
    //                 $query->where('is_publish', 1); // Chỉ lấy phim đã được phát hành
    //             })
    //             ->when($cinemaId, function ($query) use ($cinemaId) {
    //                 // Nếu có cinema_id, lọc theo cinema_id
    //                 $query->where('cinema_id', $cinemaId);
    //             })
    //             ->when(!$isAdmin, function ($query) use ($now) {
    //                 // Nếu không phải admin, chỉ lấy các suất chiếu trong vòng 7 ngày tới
    //                 $query->where('start_time', '>', $now)
    //                     ->where('start_time', '<', $now->copy()->addDays(7));
    //             })
    //             ->when($isAdmin, function ($query) use ($now) {
    //                 // Nếu là admin, lấy tất cả suất chiếu trong quá khứ và tương lai
    //                 $query->where('start_time', '>=', $now->copy()->subDays(365));
    //             })
    //             ->orderBy('date')
    //             ->orderBy('start_time')
    //             ->get();

    //         // Tạo ánh xạ cho các ngày trong tuần
    //         $dayNames = [
    //             'Sunday' => 'CN',
    //             'Monday' => 'T2',
    //             'Tuesday' => 'T3',
    //             'Wednesday' => 'T4',
    //             'Thursday' => 'T5',
    //             'Friday' => 'T6',
    //             'Saturday' => 'T7'
    //         ];

    //         // Khởi tạo danh sách showtimes
    //         $showtimesByDate = [];

    //         foreach ($showtimesQuery as $showtime) {
    //             $dateKey = $showtime->date;
    //             $dayOfWeek = Carbon::parse($dateKey)->format('l'); // Lấy tên ngày trong tuần bằng tiếng Anh
    //             $dayLabel = Carbon::parse($dateKey)->format('d/m') . ' - ' . $dayNames[$dayOfWeek]; // Định dạng ngày theo d/m - T2

    //             $format = $showtime->format;

    //             // Nếu ngày chưa tồn tại, tạo mới
    //             if (!isset($showtimesByDate[$dateKey])) {
    //                 $showtimesByDate[$dateKey] = [
    //                     "day_id" => "day" . Carbon::parse($dateKey)->dayOfYear,
    //                     "date_label" => $dayLabel, // Ánh xạ ngày từ d/m - T2
    //                     "showtimes" => []
    //                 ];
    //             }

    //             // Nếu định dạng suất chiếu chưa tồn tại, tạo mới
    //             if (!isset($showtimesByDate[$dateKey]["showtimes"][$format])) {
    //                 $showtimesByDate[$dateKey]["showtimes"][$format] = [];
    //             }

    //             // Thêm suất chiếu vào danh sách
    //             $showtimesByDate[$dateKey]["showtimes"][$format][] = [
    //                 "id" => $showtime->id,
    //                 "cinema_id" => $showtime->room->cinema_id,
    //                 "room_id" => $showtime->room_id,
    //                 "slug" => $showtime->slug,
    //                 "format" => $showtime->format,
    //                 "movie_version_id" => $showtime->movie_version_id,
    //                 "movie_id" => $showtime->movie_id,
    //                 "date" => $showtime->date,
    //                 "start_time" => $showtime->start_time,
    //                 "end_time" => $showtime->end_time,
    //                 "is_active" => $showtime->is_active,
    //                 "created_at" => $showtime->created_at,
    //                 "updated_at" => $showtime->updated_at
    //             ];
    //         }

    //         // Chuyển danh sách showtimes thành array
    //         $formattedShowtimes = array_values($showtimesByDate);

    //         return response()->json(["showtimes" => $formattedShowtimes], 200);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             "error" => "Unable to fetch showtime data",
    //             "message" => $th->getMessage()
    //         ], 500);
    //     }
    // }


    public function showtimeMovie(Request $request)
    {
        try {
            // Lấy movie_id và cinema_id từ query string hoặc session
            $movieId = $request->query('movie_id', session('movie_id'));
            $cinemaId = $request->query('cinema_id', session('cinema_id'));

            if (!$movieId) {
                return response()->json(['message' => 'Movie ID is required.'], 400);
            }

            // Thời gian hiện tại (có thể có múi giờ)
            $now = Carbon::now('Asia/Ho_Chi_Minh');  // Sử dụng múi giờ Việt Nam

            // Kiểm tra xem người dùng có phải admin không
            $isAdmin = auth()->user() && auth()->user()->role == 'admin';
            $isMember = auth()->user() && auth()->user()->role == 'member';

            // Lấy danh sách suất chiếu của phim theo ngày, có áp dụng điều kiện hiển thị
            $showtimesQuery = Showtime::with(['room.cinema'])
                ->where('movie_id', $movieId)
                ->where('is_active', 1) // Chỉ lấy suất chiếu đang hoạt động
                ->whereHas('movie', function ($query) {
                    $query->where('is_publish', 1); // Chỉ lấy phim đã được phát hành
                })
                ->when($cinemaId, function ($query) use ($cinemaId) {
                    // Nếu có cinema_id, lọc theo cinema_id
                    $query->where('cinema_id', $cinemaId);
                })
                ->when(!$isAdmin && $isMember, function ($query) use ($now) {
                    // Nếu là member, chỉ lấy các suất chiếu trong tương lai (sau giờ hiện tại)
                    $query->where('start_time', '>', $now)
                        ->where('start_time', '<', $now->copy()->addDays(7)); // Chỉ suất chiếu trong 7 ngày tới
                })
                ->when($isAdmin, function ($query) use ($now) {
                    // Nếu là admin, lấy tất cả suất chiếu trong quá khứ và tương lai, nhưng kiểm tra các suất chiếu chưa qua giờ
                    $query->where('start_time', '>=', $now->copy()->subDays(365)) // Lấy các suất chiếu trong vòng 1 năm
                        ->where('start_time', '>', $now);  // Đảm bảo chỉ lấy các suất chiếu chưa qua giờ
                })
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Tạo ánh xạ cho các ngày trong tuần
            $dayNames = [
                'Sunday' => 'CN',
                'Monday' => 'T2',
                'Tuesday' => 'T3',
                'Wednesday' => 'T4',
                'Thursday' => 'T5',
                'Friday' => 'T6',
                'Saturday' => 'T7'
            ];

            // Khởi tạo danh sách showtimes
            $showtimesByDate = [];

            foreach ($showtimesQuery as $showtime) {
                $dateKey = $showtime->date;
                $dayOfWeek = Carbon::parse($dateKey)->format('l'); // Lấy tên ngày trong tuần bằng tiếng Anh
                $dayLabel = Carbon::parse($dateKey)->format('d/m') . ' - ' . $dayNames[$dayOfWeek];
                $format = $showtime->format;

                // Nếu ngày chưa tồn tại, tạo mới
                if (!isset($showtimesByDate[$dateKey])) {
                    $showtimesByDate[$dateKey] = [
                        "day_id" => "day" . Carbon::parse($dateKey)->dayOfYear,
                        "date_label" => $dayLabel, // Ánh xạ ngày từ d/m - T2
                        "showtimes" => []
                    ];
                }

                // Nếu định dạng suất chiếu chưa tồn tại, tạo mới
                if (!isset($showtimesByDate[$dateKey]["showtimes"][$format])) {
                    $showtimesByDate[$dateKey]["showtimes"][$format] = [];
                }

                // Thêm suất chiếu vào danh sách
                $showtimesByDate[$dateKey]["showtimes"][$format][] = [
                    "id" => $showtime->id,
                    "cinema_id" => $showtime->room->cinema_id,
                    "room_id" => $showtime->room_id,
                    "slug" => $showtime->slug,
                    "format" => $showtime->format,
                    "movie_version_id" => $showtime->movie_version_id,
                    "movie_id" => $showtime->movie_id,
                    "date" => $showtime->date,
                    "start_time" => $showtime->start_time,
                    "end_time" => $showtime->end_time,
                    "is_active" => $showtime->is_active,
                    "created_at" => $showtime->created_at,
                    "updated_at" => $showtime->updated_at
                ];
            }

            // Chuyển danh sách showtimes thành array
            $formattedShowtimes = array_values($showtimesByDate);

            return response()->json(["showtimes" => $formattedShowtimes], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "error" => "Unable to fetch showtime data",
                "message" => $th->getMessage()
            ], 500);
        }
    }
}
