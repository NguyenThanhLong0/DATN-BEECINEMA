<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
            
class FoodController extends Controller
{
    //
    public function index()
    {
        try {
            $foods = Food::query()->latest('id')->paginate(10);
            return response()->json(
                 $foods
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'lỗi',
                'satus' => false,
            ]);
        }
    }

    public function show($id)
    {
        try {
            $foods = Food::query()->findOrFail($id);
            return response()->json([
                'message' => 'Chi tiết',
                'status' => true,
                'data' => $foods
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không tìm thấy bản ghi nào',
                'status' => false
            ]);
        }
    }


    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:food,name',  // Kiểm tra tên món ăn yêu cầu, chuỗi, và duy nhất
                'price' => 'required|numeric|min:0',  // Kiểm tra giá món ăn phải là số và không nhỏ hơn 0
                'img_thumbnail' => 'nullable|url|max:255',  // Hình ảnh đại diện phải là URL hợp lệ và tối đa 255 ký tự
                'type' => 'nullable|string',  // Loại món ăn có thể để trống, nếu có phải là chuỗi
                'description' => 'nullable|string',  // Mô tả món ăn có thể để trống, nếu có phải là chuỗi
                'is_active' => 'nullable|boolean',  // Trạng thái kích hoạt có thể để trống, nếu có phải là boolean
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'url' => ':attribute phải là một URL hợp lệ.',
                'numeric' => ':attribute phải là một số.',
                'min' => ':attribute phải có giá trị ít nhất là :min.',
                'unique' => ':attribute đã tồn tại trong hệ thống.',
            ], [
                'name' => 'Tên món ăn',
                'price' => 'Giá món ăn',
                'img_thumbnail' => 'Hình ảnh đại diện',
                'type' => 'Loại món ăn',
                'description' => 'Mô tả',
                'is_active' => 'Trạng thái kích hoạt',
            ]);
            // Cập nhật hoặc lưu dữ liệu
            $food = Food::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'img_thumbnail' => $validated['img_thumbnail'] ?? null,
                'type' => $validated['type'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? false, // Mặc định false nếu không có giá trị
            ]);

            return response()->json([
                'message' => 'Food thêm thành công!',
                'data' => $food,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Thêm mới thất bại',
                'status' => false,
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function update(Request $request, Food $food)
    {
        try {
            // Xác thực dữ liệu
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:food,name,' . $food->id,  // Kiểm tra tên món ăn yêu cầu, chuỗi, và duy nhất (loại trừ bản ghi hiện tại)
                'price' => 'required|numeric|min:0',  // Kiểm tra giá món ăn phải là số và không nhỏ hơn 0
                'img_thumbnail' => 'nullable|url|max:255',  // Hình ảnh đại diện phải là URL hợp lệ và tối đa 255 ký tự
                'type' => 'nullable|string',  // Loại món ăn có thể để trống, nếu có phải là chuỗi
                'description' => 'nullable|string',  // Mô tả món ăn có thể để trống, nếu có phải là chuỗi
                'is_active' => 'nullable|boolean',  // Trạng thái kích hoạt có thể để trống, nếu có phải là boolean
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'url' => ':attribute phải là một URL hợp lệ.',
                'numeric' => ':attribute phải là một số.',
                'min' => ':attribute phải có giá trị ít nhất là :min.',
                'unique' => ':attribute đã tồn tại trong hệ thống.',
            ], [
                'name' => 'Tên món ăn',
                'price' => 'Giá món ăn',
                'img_thumbnail' => 'Hình ảnh đại diện',
                'type' => 'Loại món ăn',
                'description' => 'Mô tả',
                'is_active' => 'Trạng thái kích hoạt',
            ]);
            
            // Cập nhật dữ liệu
            $food->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'img_thumbnail' => $validated['img_thumbnail'] ?? $food->img_thumbnail, // Giữ giá trị cũ nếu không có giá trị mới
                'type' => $validated['type'] ?? $food->type, // Giữ giá trị cũ nếu không có giá trị mới
                'description' => $validated['description'] ?? $food->description, // Giữ giá trị cũ nếu không có giá trị mới
                'is_active' => $validated['is_active'] ?? $food->is_active, // Giữ giá trị cũ nếu không có giá trị mới
            ]);
    
            return response()->json([
                'message' => 'Food cập nhật thành công!',
                'data' => $food,
            ], 200);
    
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Cập nhật thất bại',
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    

    public function destroy(Food $food): JsonResponse
    {
        try {
            $hasActiveCombo = $food->combos()
                ->where('is_active', true)
                ->exists();

            if ($hasActiveCombo) {
                return response()->json([
                    'message' => 'Không thể xóa món ăn vì nó thuộc combo đang hoạt động!',
                    'status' => false
                ], 422);
            }


            $food->delete();

            return response()->json([
                'message' => 'Xóa thành công!',
                'status' => true
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Xóa thất bại!',
                'status' => false,
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
