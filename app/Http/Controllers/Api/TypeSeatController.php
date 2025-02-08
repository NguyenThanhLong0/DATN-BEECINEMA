<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TypeSeat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class TypeSeatController extends Controller
{
    /**
     * Lấy danh sách các loại ghế.
     */
    public function index()
    {
        try {
            $typeSeats = TypeSeat::paginate(10);
            return response()->json(['data' => $typeSeats], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Không thể lấy danh sách loại ghế!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Thêm mới loại ghế.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:type_seats',
                'price' => 'required|integer|min:0',
            ], [
                'name.required' => 'Vui lòng nhập tên loại ghế.',
                'name.string' => 'Tên loại ghế phải là chuỗi ký tự.',
                'name.max' => 'Tên loại ghế không được vượt quá 255 ký tự.',
                'name.unique' => 'Tên loại ghế đã tồn tại.',
                'price.required' => 'Vui lòng nhập giá.',
                'price.integer' => 'Giá phải là một số nguyên.',
                'price.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $typeSeat = TypeSeat::create($request->only(['name', 'price']));

            return response()->json(['message' => 'Thêm mới thành công!', 'data' => $typeSeat], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi thêm mới: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Hiển thị chi tiết một loại ghế.
     */
    public function show(TypeSeat $typeSeat)
    {
        try {
            return response()->json(['data' => $typeSeat], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Loại ghế không tồn tại!'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Cập nhật thông tin loại ghế.
     */
    public function update(Request $request, TypeSeat $typeSeat)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:type_seats,name,' . $typeSeat->id,
                'price' => 'required|integer|min:0',
            ], [
                'name.required' => 'Vui lòng nhập tên loại ghế.',
                'name.string' => 'Tên loại ghế phải là chuỗi ký tự.',
                'name.max' => 'Tên loại ghế không được vượt quá 255 ký tự.',
                'name.unique' => 'Tên loại ghế đã tồn tại.',
                'price.required' => 'Vui lòng nhập giá.',
                'price.integer' => 'Giá phải là một số nguyên.',
                'price.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $typeSeat->update($request->only(['name', 'price']));

            return response()->json(['message' => 'Cập nhật thành công!', 'data' => $typeSeat], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi cập nhật: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Xóa một loại ghế.
     */
    public function destroy(TypeSeat $typeSeat)
    {
        try {
            $typeSeat->delete();
            return response()->json(['message' => 'Xóa thành công!'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Có lỗi xảy ra khi xóa: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
