<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Seat;
use App\Models\SeatTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class RoomController extends Controller
{


    //     public function index()
    // {
    //     try {
    //         $rooms = Room::with([
    //             'branch',
    //             'cinema',
    //             'typeRoom',
    //             'seatTemplate'
    //         ])->paginate(10);

    //         foreach ($rooms as $room) {
    //             // Nếu có `matrix_id`, lấy ma trận ghế từ `SeatTemplate`
    //             if ($room->seatTemplate && $room->seatTemplate->matrix_id) {
    //                 $room->seatTemplate->matrix_id = SeatTemplate::getMatrixById((int)$room->seatTemplate->matrix_id);
    //             }

    //             // **Lấy danh sách ghế của phòng**
    //             $seats = Seat::where('room_id', $room->id)->get();

    //             // **Tính tổng số ghế, số ghế hỏng & số ghế hoạt động**
    //             $totalSeats = $seats->count();
    //             $brokenSeats = $seats->where('is_active', 0)->count();
    //             $activeSeats = $totalSeats - $brokenSeats;

    //             // **Tạo seat map**
    //             $seatMap = [];
    //             foreach ($seats as $seat) {
    //                 $row = $seat->coordinates_y; // Lấy hàng ghế (ví dụ: "A", "B", ...)
    //                 $col = $seat->coordinates_x; // Lấy số ghế (ví dụ: "1", "2", ...)

    //                 if (!isset($seatMap[$row])) {
    //                     $seatMap[$row] = [];
    //                 }

    //                 $seatMap[$row][$col] = [
    //                     'id' => $seat->id,
    //                     'room_id' => $seat->room_id,
    //                     'type_seat_id' => $seat->type_seat_id,
    //                     'name' => $seat->name,
    //                     'is_active' => $seat->is_active,
    //                     'coordinates_x' => $seat->coordinates_x,
    //                     'coordinates_y' => $seat->coordinates_y,
    //                     'created_at' => $seat->created_at,
    //                     'updated_at' => $seat->updated_at,
    //                 ];
    //             }

    //             // **Gán thêm thông tin vào `room`**
    //             $room->totalSeats = $totalSeats;
    //             $room->activeSeats = $activeSeats;
    //             $room->brokenSeats = $brokenSeats;
    //             $room->seatMap = $seatMap;
    //         }

    //         return response()->json($rooms);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'message' => 'Không thể lấy danh sách phòng!',
    //             'error' => $th->getMessage(),
    //         ], 500);
    //     }
    // }


    public function index()
    {
        try {
            $rooms = Room::with([
                'branch',
                'cinema',
                'typeRoom',
                'seatTemplate'
            ])->paginate(10);

            foreach ($rooms as $room) {
                // Nếu có `matrix_id`, lấy dữ liệu từ `SeatTemplate`
                if ($room->seatTemplate && $room->seatTemplate->matrix_id) {
                    $room->seatTemplate->matrix_id = SeatTemplate::getMatrixById((int)$room->seatTemplate->matrix_id);
                }

                // Lấy danh sách ghế của phòng
                $seats = Seat::where('room_id', $room->id)->get();

                // Tính tổng số ghế, số ghế hỏng, số ghế hoạt động
                $totalSeats = $seats->count();
                $brokenSeats = $seats->where('is_active', 0)->count();
                $activeSeats = $totalSeats - $brokenSeats;

                // Tạo seat map dạng JSON
                $seatMap = [];

                foreach ($seats as $seat) {
                    $row = $seat->coordinates_y; // Hàng ghế (A, B, C, ...)
                    $col = $seat->coordinates_x; // Cột ghế (1, 2, 3, ...)

                    // Nếu hàng chưa tồn tại trong seatMap thì khởi tạo
                    if (!isset($seatMap[$row])) {
                        $seatMap[$row] = [
                            'row' => $row,
                            'seats' => []
                        ];
                    }

                    // Tạo thông tin ghế
                    $seatData = [
                        'id' => $seat->id,
                        'room_id' => $seat->room_id,
                        'type_seat_id' => $seat->type_seat_id,
                        'name' => $seat->name,
                        'is_active' => $seat->is_active,
                        'coordinates_x' => $col,
                        'coordinates_y' => $row,
                        'created_at' => $seat->created_at,
                        'updated_at' => $seat->updated_at,
                    ];

                    // Thêm ghế vào hàng
                    $seatMap[$row]['seats'][] = $seatData;

                    // Nếu là ghế đôi, thêm ghế bên cạnh
                    if ($seat->type_seat_id == 3) {
                        $seatData['coordinates_x'] = $col + 1; // Ghế bên cạnh
                        $seatMap[$row]['seats'][] = $seatData;
                    }
                }

                // Chuyển thành mảng tuần tự
                $seatMap = array_values($seatMap);

                // Gán thêm thông tin vào `room`
                $room->totalSeats = $totalSeats;
                $room->activeSeats = $activeSeats;
                $room->brokenSeats = $brokenSeats;
                $room->seatMap = $seatMap;
            }

            return response()->json($rooms);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không thể lấy danh sách phòng!',
                'error' => $th->getMessage(),
            ], 500);
        }
    }


    public function show(Room $room)
    {
        try {
            $room->load(['branch', 'cinema', 'typeRoom', 'seatTemplate']);

            // Nếu có template ghế, lấy matrix_id
            if ($room->seatTemplate) {
                $room->seatTemplate->matrix_id = SeatTemplate::getMatrixById($room->seatTemplate->matrix_id);
            }

            // Lấy danh sách ghế thuộc phòng
            $seats = Seat::where('room_id', $room->id)->get();

            // Tính tổng số ghế, ghế hỏng & ghế hoạt động
            $totalSeats = $seats->count();
            $brokenSeats = $seats->where('is_active', 0)->count();
            $activeSeats = $totalSeats - $brokenSeats;

            // Định dạng seat map
            // Tạo seat map dạng JSON
            $seatMap = [];

            foreach ($seats as $seat) {
                $row = $seat->coordinates_y; // Hàng ghế (A, B, C, ...)
                $col = $seat->coordinates_x; // Cột ghế (1, 2, 3, ...)

                // Nếu hàng chưa tồn tại trong seatMap thì khởi tạo
                if (!isset($seatMap[$row])) {
                    $seatMap[$row] = [
                        'row' => $row,
                        'seats' => []
                    ];
                }

                // Tạo thông tin ghế
                $seatData = [
                    'id' => $seat->id,
                    'room_id' => $seat->room_id,
                    'type_seat_id' => $seat->type_seat_id,
                    'name' => $seat->name,
                    'is_active' => $seat->is_active,
                    'coordinates_x' => $col,
                    'coordinates_y' => $row,
                    'created_at' => $seat->created_at,
                    'updated_at' => $seat->updated_at,
                ];

                // Thêm ghế vào hàng
                $seatMap[$row]['seats'][] = $seatData;

                // Nếu là ghế đôi, thêm ghế bên cạnh
                if ($seat->type_seat_id == 3) {
                    $seatData['coordinates_x'] = $col + 1; // Ghế bên cạnh
                    $seatMap[$row]['seats'][] = $seatData;
                }
            }

            // Chuyển thành mảng tuần tự
            $seatMap = array_values($seatMap);

            return response()->json([
                'room' => $room,
                'totalSeats' => $totalSeats,
                'activeSeats' => $activeSeats,
                'brokenSeats' => $brokenSeats,
                'seatMap' => $seatMap,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không thể lấy thông tin phòng!',
                'error' => $th->getMessage(),
            ], 500);
        }
    }


    public function store(Request $request)
    {
        $rules = [
            'type_room_id' => 'required|exists:type_rooms,id',
            'name' => [
                'required',
                'string',
                Rule::unique('rooms')->where(function ($query) use ($request) {
                    return $query->where('cinema_id', $request->cinema_id);
                }),
            ],
            'seat_template_id' => 'required|exists:seat_templates,id',
        ];


        if (empty(Auth::user()->cinema_id)) {
            $rules['branch_id'] = 'required|exists:branches,id';
            $rules['cinema_id'] = 'required|exists:cinemas,id';
        }

        // Khởi tạo Validator với các quy tắc đã được cấu hình
        $validator = Validator::make($request->all(), $rules, [
            'name.required' => 'Vui lòng nhập tên phòng chiếu.',
            'name.unique' => 'Tên phòng đã tồn tại trong rạp.',
            'branch_id.required' => "Vui lòng chọn chi nhánh.",
            'branch_id.exists' => 'Chi nhánh bạn chọn không hợp lệ.',
            'cinema_id.required' => "Vui lòng chọn rạp chiếu.",
            'cinema_id.exists' => 'Rạp chiếu phim bạn chọn không hợp lệ.',
            'type_room_id.required' => "Vui lòng chọn loại phòng.",
            'type_room_id.exists' => 'Loại phòng chiếu bạn chọn không hợp lệ.',
            'seat_template_id.required' => "Vui lòng chọn mẫu sơ đồ ghế.",
            'seat_template_id.exists' => 'Mẫu sơ đồ ghế không hợp lệ.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        }

        try {
            $room = DB::transaction(function () use ($request) {
                $dataRoom = [
                    'branch_id' => isset($request->branch_id) ? $request->branch_id : Auth::user()->cinema->branch_id,
                    'cinema_id' => isset($request->cinema_id) ? $request->cinema_id : Auth::user()->cinema_id,
                    'type_room_id' => $request->type_room_id,
                    'name' => $request->name,
                    'seat_template_id' => $request->seat_template_id,
                    'is_active' => $request->input('is_active', false),
                    'is_publish' => $request->input('is_publish', false),
                ];
                $room = Room::create($dataRoom);

                $seatTemplate = SeatTemplate::findOrFail($request->seat_template_id);

                // Chuyển đổi seat_structure từ JSON object thành array
                // $seatStructureArray = json_decode($seatTemplate->seat_structure, true);


                // Kiểm tra nếu seat_structure đã là mảng thì giữ nguyên, nếu là string thì decode

                // Kiểm tra nếu dữ liệu là string thì mới giải mã JSON

                $seatStructureArray = is_string($seatTemplate->seat_structure)
                    ? json_decode($seatTemplate->seat_structure, true)
                    : $seatTemplate->seat_structure;

                // Tạo mảng để lưu trữ các ghế
                $dataSeats = [];

                // Kiểm tra nếu không phải là mảng
                // if (!is_array($seatStructureArray)) {
                //     return response()->json(['error' => 'Dữ liệu seat_structure không hợp lệ!'], 422);
                // }

                // Lặp qua từng ghế trong seat_structure
                foreach ($seatStructureArray as $seat) {
                    $name = $seat['coordinates_y'] . $seat['coordinates_x'];

                    // Nếu là ghế đôi thì thêm tên ghế thứ hai
                    if ($seat['type_seat_id'] == 3) {
                        $name .= ', ' . $seat['coordinates_y'] . ($seat['coordinates_x'] + 1);
                    }

                    $dataSeats[] = [
                        'coordinates_x' => $seat['coordinates_x'],
                        'coordinates_y' => $seat['coordinates_y'],
                        'name' => $name,
                        'type_seat_id' => $seat['type_seat_id'],
                        'room_id' => $room->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Chèn ghế vào bảng seats
                Seat::insert($dataSeats);

                return $room;
            });


            return response()->json([
                'message' => "Thao tác thành công",
                'room' => $room,
            ], Response::HTTP_CREATED); // 201

        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500
        }
    }

    public function update(Request $request, Room $room)
    {
        $rules = [
            'name' => [
                'required',
                'string',
                Rule::unique('rooms')->where(function ($query) use ($request, $room) {
                    return $query->where('cinema_id', $request->cinema_id)
                        ->where('id', '!=', $room->id);
                }),
            ],
        ];

        // Validate input
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::transaction(function () use ($request, $room) {
                // Handle "publish" action
                if ($request->action == "publish" && !$room->is_publish) {
                    $room->update([
                        'is_publish' => 1,
                        'is_active' => 1,
                    ]);
                } else {
                    // Update room's is_active status
                    $room->update([
                        'name' => $request->name,
                        'is_active' => isset($request->is_active) ? 1 : 0,
                    ]);
                }

                // Update seat status if seats are provided in the request
                if (!empty($request->seats)) {
                    foreach ($request->seats as $seatData) {
                        Seat::where('id', $seatData['id'])->update([
                            'is_active' => $seatData['is_active'],
                        ]);
                    }
                }
            });

            // Flash success message
            session()->flash('success', 'Cập nhật thành công!');

            return response()->json([
                'message' => "Cập nhật thành công",
                'room' => $room->fresh(), // Get updated room
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            // Flash error message
            session()->flash('error', 'Đã xảy ra lỗi!');

            return response()->json([
                'error' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function destroy(Room $room)
    {
        try {
            DB::transaction(function () use ($room) {
                // Kiểm tra xem phòng có liên quan đến suất chiếu không
                // if ($room->showtimes()->exists()) {
                //     throw new \Exception('Không thể xóa phòng chiếu vì có suất chiếu đang sử dụng.');
                // }

                // Xóa toàn bộ ghế thuộc phòng
                Seat::where('room_id', $room->id)->delete();

                // Xóa phòng chiếu
                $room->delete();
            });

            return response()->json(['message' => 'Xóa phòng chiếu thành công!'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Xóa thất bại: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
