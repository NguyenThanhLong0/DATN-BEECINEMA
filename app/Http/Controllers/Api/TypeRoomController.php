<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TypeRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class TypeRoomController extends Controller
{
    /**
     * Lấy danh sách các loại phòng.
     */
    public function index()
    {
        try {
            $typeRooms = TypeRoom::paginate(10);
            return response()->json($typeRooms, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Không thể lấy danh sách loại phòng!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Thêm mới loại phòng.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:type_rooms',
                'surcharge' => 'required|integer|min:0|max:1000000',
            ], [
                'name.required' => 'Vui lòng nhập tên loại phòng.',
                'name.string' => 'Tên loại phòng phải là chuỗi ký tự.',
                'name.max' => 'Tên loại phòng không được vượt quá 255 ký tự.',
                'name.unique' => 'Tên loại phòng đã tồn tại.',
                'surcharge.required' => 'Vui lòng nhập phụ phí.',
                'surcharge.integer' => 'Phụ phí phải là một số nguyên.',
                'surcharge.min' => 'Phụ phí phải lớn hơn hoặc bằng 0.',
                'surcharge.max' => 'Phụ phí không được vượt quá 1,000,000.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $typeRoom = TypeRoom::create($request->only(['name', 'surcharge']));

            return response()->json(['message' => 'Thêm mới thành công!', 'data' => $typeRoom], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi thêm mới: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Hiển thị chi tiết một loại phòng.
     */
    public function show(TypeRoom $typeRoom)
    {
        return response()->json(['data' => $typeRoom], Response::HTTP_OK);
    }

    /**
     * Cập nhật thông tin loại phòng.
     */
    public function update(Request $request, TypeRoom $typeRoom)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:type_rooms,name,' . $typeRoom->id,
                'surcharge' => 'required|integer|min:0|max:1000000',
            ], [
                'name.required' => 'Vui lòng nhập tên loại phòng.',
                'name.string' => 'Tên loại phòng phải là chuỗi ký tự.',
                'name.max' => 'Tên loại phòng không được vượt quá 255 ký tự.',
                'name.unique' => 'Tên loại phòng đã tồn tại.',
                'surcharge.required' => 'Vui lòng nhập phụ phí.',
                'surcharge.integer' => 'Phụ phí phải là một số nguyên.',
                'surcharge.min' => 'Phụ phí phải lớn hơn hoặc bằng 0.',
                'surcharge.max' => 'Phụ phí không được vượt quá 1,000,000.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $typeRoom->update($request->only(['name', 'surcharge']));

            return response()->json(['message' => 'Cập nhật thành công!', 'data' => $typeRoom], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi cập nhật: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Xóa một loại phòng.
     */
    public function destroy(TypeRoom $typeRoom)
    {
        try {
            // Kiểm tra nếu loại phòng đang được sử dụng
            if ($typeRoom->room()->exists()) {
                return response()->json(['error' => 'Không thể xóa loại phòng vì đang được sử dụng!'], Response::HTTP_BAD_REQUEST);
            }

            $typeRoom->delete();

            return response()->json(['message' => 'Xóa thành công!'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi xóa: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
