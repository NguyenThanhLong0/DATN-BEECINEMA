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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user(); // Lấy thông tin người dùng đang đăng nhập
            $branchId = $request->input('branch_id', $user->cinema->branch_id ?? null);
            $cinemaId = $request->input('cinema_id', $user->cinema_id ?? null);

            if (!$cinemaId) {
                return response()->json(['error' => 'Cinema not found.'], 404);
            }
            $date = $request->input('date', now()->format('Y-m-d'));
            $isActive = $request->input('is_active', null);

            Log::info('cinema_id: ' . $cinemaId);
            Log::info('date: ' . $date);

            // Query để lấy danh sách các suất chiếu
            $showtimesQuery = Showtime::where('cinema_id', $cinemaId)
                ->whereDate('date', $date);

            // Nếu có filter theo trạng thái hoạt động, thêm điều kiện
            if ($isActive !== null) {
                $showtimesQuery->where('is_active', $isActive);
            }

            $showtimes = $showtimesQuery->with(['movie', 'room', 'movieVersion'])->latest('id')->get();

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

    // /**
    //  * Store a newly created resource in storage.
    //  */
    public function store(StoreShowtimeRequest $request)
    {
        try {

            DB::transaction(function () use ($request) {
                $movieVersion = MovieVersion::find($request->movie_version_id);
                $room = Room::find($request->room_id);
                $typeRoom = TypeRoom::find($room->type_room_id);
                $movie = Movie::find($request->movie_id);
                $movieDuration = $movie->duration ?? 0;
                $cleaningTime = Showtime::CLEANINGTIME;
                $user = auth()->user();

                // Kiểm tra các suất chiếu hiện có của phòng và ngày
                $existingShowtimes = Showtime::where('room_id', $request->room_id)
                    ->where('date', $request->date)
                    ->get();

                // Tính toán thời gian bắt đầu và kết thúc của suất chiếu
                $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
                $endTime = $startTime->copy()->addMinutes($movieDuration + $cleaningTime);

                // Chuẩn bị dữ liệu để tạo suất chiếu mới
                $dataShowtimes = [
                    'cinema_id' => $request->cinema_id ?? $user->cinema_id,
                    'room_id' => $request->room_id,
                    'slug' => Showtime::generateCustomRandomString(), //random slug
                    'format' => $typeRoom->name . ' ' . $movieVersion->name,
                    'movie_version_id' => $request->movie_version_id,
                    'movie_id' => $request->movie_id,
                    'date' => $request->date,
                    'start_time' => $startTime->format('Y-m-d H:i'),
                    'end_time' => $endTime->format('Y-m-d H:i'),
                    'is_active' => $request->is_active ?? true,
                ];

                $showtime = Showtime::create($dataShowtimes);

                // Lấy danh sách các ghế trong phòng và cập nhật trạng thái và giá vé
                $seats = Seat::where('room_id', $room->id)->get();
                $seatShowtimes = [];

                foreach ($seats as $seat) {
                    $cinemaPrice = $room->cinema->surcharge;
                    $moviePrice = $movie->surcharge;
                    $typeRoomPrice = $typeRoom->surcharge;
                    $typeSeat = $seat->typeSeat->price;

                    // Tính giá vé ghế
                    $price = $cinemaPrice + $moviePrice + $typeRoomPrice + $typeSeat;
                    $status = $seat->is_active == 0 ? 'broken' : 'available';

                    $seatShowtimes[] = [
                        'showtime_id' => $showtime->id,
                        'seat_id' => $seat->id,
                        'status' => $status,
                        'price' => $price,
                    ];
                }

                // Chèn tất cả thông tin ghế vào bảng SeatShowtime
                SeatShowtime::insert($seatShowtimes);
            });

            return response()->json(['message' => 'Showtime created successfully!'], 201);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Showtime $showtime)
    {
        try {
            $showtime->load(['room.cinema', 'room.seatTemplate', 'movieVersion', 'movie', 'seats']);
            $matrixSeat = SeatTemplate::getMatrixById($showtime->room->seatTemplate->matrix_id);
            $seats = $showtime->seats;

            // Xây dựng seatMap dựa trên tọa độ ghế
            $seatMap = [];
            foreach ($seats as $seat) {
                $seatMap[$seat->coordinates_y][$seat->coordinates_x] = $seat;
            }

            // Trả về thông tin chi tiết suất chiếu
            return response()->json([
                'showtime' => $showtime,
                'matrixSeat' => $matrixSeat,
                'seatMap' => $seatMap,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateShowtimeRequest $request, Showtime $showtime)
    {
        try {
            // Tính toán thời gian bắt đầu và kết thúc của suất chiếu
            $movieVersion = MovieVersion::find($request->movie_version_id);
            $room = Room::find($request->room_id);
            $typeRoom = TypeRoom::find($room->type_room_id);
            $movie = Movie::find($request->movie_id);
            $movieDuration = $movie->duration ?? 0;
            $cleaningTime = Showtime::CLEANINGTIME;
            $user = auth()->user();

            $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
            $endTime = $startTime->copy()->addMinutes($movieDuration + $cleaningTime);

            // Chuẩn bị dữ liệu để cập nhật suất chiếu
            $dataShowtimes = [
                'room_id' => $request->room_id,
                'format' => $typeRoom->name . ' ' . $movieVersion->name,
                'movie_version_id' => $request->movie_version_id,
                'movie_id' => $request->movie_id,
                'date' => $request->date,
                'start_time' => $startTime->format('Y-m-d H:i'),
                'end_time' => $endTime->format('Y-m-d H:i'),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ];

            $showtime->update($dataShowtimes);

            return response()->json(['message' => 'Showtime updated successfully!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Showtime $showtime)
    {
        try {
            $showtime->delete();
            return response()->json(['message' => 'Showtime deleted successfully!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
