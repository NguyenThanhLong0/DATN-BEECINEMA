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
    public function index()
    {
        return response()->json(Room::with(['branch', 'cinema', 'typeRoom', 'seatTemplate'])->paginate(10));
    }

    public function show(Room $room)
    {
        return response()->json($room->load(['branch', 'cinema', 'typeRoom', 'seatTemplate']));
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
                $seatStructureArray = json_decode($seatTemplate->seat_structure, true);

                // Kiểm tra nếu dữ liệu là string thì mới giải mã JSON
                // $seatStructureArray = is_string($seatTemplate->seat_structure)
                //     ? json_decode($seatTemplate->seat_structure, true)
                //     : $seatTemplate->seat_structure;

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
                        ->where('id', '!=', $room->id); // Bỏ qua phòng hiện tại đang cập nhật
                }),
            ],
        ];
        if (!$request->has('branch_id')) {
            $request->merge(['branch_id' => $room->branch_id]);
        }
        if (!$request->has('cinema_id')) {
            $request->merge(['cinema_id' => $room->cinema_id]);
        }
        if (!$room->is_publish) {
            // Thêm các rule này nếu phòng chưa publish

            $rules['branch_id'] = 'required|exists:branches,id';
            $rules['cinema_id'] = 'required|exists:cinemas,id';


            $rules['type_room_id'] = 'required|exists:type_rooms,id';
            $rules['seat_template_id'] = 'required|exists:seat_templates,id';
        }

        // Thông báo lỗi tùy chỉnh
        $messages = [
            'name.required' => 'Vui lòng nhập tên phòng chiếu.',
            'name.unique' => 'Tên phòng đã tồn tại trong rạp.',
            'branch_id.required' => "Vui lòng chọn chi nhánh.",
            'branch_id.exists' => 'Chi nhánh bạn chọn không hợp lệ.',
            'cinema_id.required' => "Vui lòng chọn rạp chiếu.",
            'cinema_id.exists' => 'Rạp chiếu phim bạn chọn không hợp lệ.',
            'type_room_id.required' => "Vui lòng chọn loại phòng.",
            'type_room_id.exists' => 'Loại phòng chiếu bạn chọn không hợp lệ.',
            'seat_template_id.required' => "Vui lòng chọn mẫu sơ đồ ghế",
            'seat_template_id.exists' => 'Mẫu sơ đồ ghế không hợp lệ.'
        ];

        // Thực hiện validate
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        }

        try {
            DB::transaction(function () use ($request, $room) {
                // Nếu phòng đã publish, chỉ cho phép cập nhật tên
                if ($room->is_publish) {
                    $room->update([
                        'name' => $request->name, // Chỉ cập nhật tên
                    ]);
                } else {
                    $room->update([
                        'branch_id' => $request->branch_id,
                        'cinema_id' => $request->cinema_id,
                        'type_room_id' => $request->type_room_id,
                        'name' => $request->name,
                        'seat_template_id' => $request->seat_template_id,
                        'is_active' => $request->input('is_active', $room->is_active), 
                        'is_publish' => $request->input('is_publish', $room->is_publish),
                    ]);

                    Seat::where('room_id', $room->id)->delete();

                    $seatTemplate = SeatTemplate::findOrFail($request->seat_template_id);

                    // Chuyển đổi seat_structure từ JSON object thành array
                    $seatStructureArray = json_decode($seatTemplate->seat_structure, true);
                    // Tạo mảng để lưu trữ các ghế
                    $dataSeats = [];

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
                }
            });
            session()->flash('success', 'Thao tác thành công!');
            return response()->json([
                'message' => "Cập nhật thành công",
                'room' => $room,
            ], Response::HTTP_OK); // 200

        } catch (\Throwable $th) {
            session()->flash('error', 'Đã sảy ra lỗi!');
            return response()->json([
                'error' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500
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


    public function updateActive(Request $request)
    {
        try {
            $room = Room::findOrFail($request->id);
            if ($room->is_publish) {
                $room->update([
                    'is_active' => $request->is_active
                ]);
                return response()->json(['success' => true, 'message' => 'Cập nhật trạng thái thành công.', 'data' => $room]);
            } else {
                // Nếu template chưa được publish, trả về thông báo lỗi
                return response()->json(['success' => false, 'message' => 'Template chưa được publish.']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại.']);
        }
    }
}
