<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComboController extends Controller
{
    //
    public function index()
    {
        try {
            // Truy vấn dữ liệu Combo và liên kết với Food
            $combos = Combo::with('foods')->latest('id')->get();

            // Nếu không có combo nào, trả về thông báo lỗi
            if ($combos->isEmpty()) {
                return response()->json(['message' => 'No combos found.'], 404);
            }

            // Xử lý và định dạng dữ liệu theo yêu cầu
            $result = $combos->map(function ($combo) {
                return [
                    'id' => $combo->id,
                    'name' => $combo->name,
                    'price' => $combo->price, // Nếu có discount, dùng discount_price, nếu không dùng price
                    'discount_price' => $combo->discount_price,
                    'description' => $combo->description,
                    'is_active' => $combo->is_active,
                    'img_thumbnail' => $combo->img_thumbnail,
                    'combo_foods' => $combo->foods->map(function ($food) {
                        $total_price = $food->price * $food->pivot->quantity; // Tính tổng giá theo số lượng
                        return [
                            'id' => $food->id,
                            'name' => $food->name,
                            'img_thumbnail' => $food->img_thumbnail,
                            'price' => $food->price,
                            'type' => $food->type,
                            'description' => $food->description,
                            'is_active' => $food->is_active,
                            'quantity' => $food->pivot->quantity, // Lấy quantity từ bảng pivot
                            'total_price' => $total_price
                        ];
                    }),
                ];
            });

            return response()->json([
                'message' => 'Hiển thị thành công!',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            // Xử lý lỗi chung khác
            return response()->json(['error' => 'hiển thị không thành công.'], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:combos,name',
                'price' => 'required|numeric',
                'discount_price' => 'nullable|numeric',
                'description' => 'nullable|string',
                'is_active' => 'required|boolean',
                'img_thumbnail' => 'nullable|url|max:255',
                'combo_foods' => 'required|array', // Mảng chứa thông tin các món ăn
                'combo_foods.*.id' => 'required|exists:food,id', // Kiểm tra id của food có tồn tại trong bảng foods
                'combo_foods.*.quantity' => 'required|numeric|min:1', // Kiểm tra quantity
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'array' => ':attribute phải là một mảng.',
                'url' => ':attribute phải là một URL hợp lệ.',
                'numeric' => ':attribute phải là một số.',
                'exists' => ':attribute không tồn tại trong hệ thống.',
                'min' => ':attribute phải có giá trị ít nhất là :min.',
                'unique' => ':attribute đã tồn tại trong hệ thống.'
            ], [
                'name' => 'Tên combo',
                'price' => 'Giá combo',
                'discount_price' => 'Giá giảm',
                'description' => 'Mô tả',
                'is_active' => 'Trạng thái kích hoạt',
                'img_thumbnail' => 'Hình ảnh đại diện',
                'combo_foods' => 'Danh sách món ăn',
                'combo_foods.*.id' => 'Món ăn',
                'combo_foods.*.quantity' => 'Số lượng món ăn',
            ]);

            // Tạo mới combo
            $combo = Combo::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'img_thumbnail' => $validated['img_thumbnail'] ?? null,
            ]);

            // Liên kết các món ăn với combo thông qua bảng pivot
            $combo->foods()->attach(
                collect($validated['combo_foods'])->mapWithKeys(function ($food) {
                    return [$food['id'] => ['quantity' => $food['quantity']]]; // Thêm quantity vào bảng pivot
                })
            );

            // Lấy danh sách món ăn có trong combo kèm theo quantity và total_price
            $comboFoods = $combo->foods()->get()->map(function ($food) use ($validated) {
                $quantity = collect($validated['combo_foods'])->firstWhere('id', $food->id)['quantity'];
                return [
                    'id' => $food->id,
                    'name' => $food->name,
                    'img_thumbnail' => $food->img_thumbnail,
                    'price' => $food->price,
                    'type' => $food->type,
                    'description' => $food->description,
                    'is_active' => $food->is_active,
                    'quantity' => $quantity,
                    'total_price' => $food->price * $quantity,
                ];
            });

            // Trả về phản hồi JSON đầy đủ
            return response()->json([
                'message' => 'Combo được tạo thành công!',
                'data' => [
                    'id' => $combo->id,
                    'name' => $combo->name,
                    'price' => $combo->price,
                    'discount_price' => $combo->discount_price,
                    'description' => $combo->description,
                    'is_active' => $combo->is_active,
                    'img_thumbnail' => $combo->img_thumbnail,
                    'combo_foods' => $comboFoods,
                ]
            ], 201);
        } catch (\Exception $e) {
            // Xử lý lỗi chung
            return response()->json([
                'message' => 'Đã xảy ra lỗi không mong muốn.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function update(Request $request, Combo $combo)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:combos,name,' . $combo->id,
                'price' => 'required|numeric',
                'discount_price' => 'nullable|numeric',
                'description' => 'nullable|string',
                'is_active' => 'required|boolean',
                'img_thumbnail' => 'nullable|url|max:255',
                'combo_foods' => 'required|array', // Mảng chứa thông tin các món ăn
                'combo_foods.*.id' => 'required|exists:food,id', // Kiểm tra id của food có tồn tại trong bảng foods
                'combo_foods.*.quantity' => 'required|numeric|min:1', // Kiểm tra quantity
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'array' => ':attribute phải là một mảng.',
                'url' => ':attribute phải là một URL hợp lệ.',
                'numeric' => ':attribute phải là một số.',
                'exists' => ':attribute không tồn tại trong hệ thống.',
                'min' => ':attribute phải có giá trị ít nhất là :min.',
                'filled' => ':attribute không được để trống hoặc rỗng.',
                'unique' => ':attribute đã tồn tại trong hệ thống.',
            ], [
                'name' => 'Tên combo',
                'price' => 'Giá combo',
                'discount_price' => 'Giá giảm',
                'description' => 'Mô tả',
                'is_active' => 'Trạng thái kích hoạt',
                'img_thumbnail' => 'Hình ảnh đại diện',
                'combo_foods' => 'Danh sách món ăn',
                'combo_foods.*.id' => 'Món ăn',
                'combo_foods.*.quantity' => 'Số lượng món ăn',
            ]);

            // Cập nhật thông tin combo
            $combo->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'img_thumbnail' => $validated['img_thumbnail'] ?? null,
            ]);

            // Cập nhật danh sách món ăn trong combo
            $combo->foods()->sync(
                collect($validated['combo_foods'])->mapWithKeys(function ($food) {
                    return [$food['id'] => ['quantity' => $food['quantity']]]; // Cập nhật quantity vào bảng pivot
                })
            );

            // Lấy danh sách combo_foods sau khi cập nhật
            $comboFoods = $combo->foods()->get()->map(function ($food) use ($validated) {
                $quantity = collect($validated['combo_foods'])->firstWhere('id', $food->id)['quantity'];
                return [
                    'id' => $food->id,
                    'name' => $food->name,
                    'img_thumbnail' => $food->img_thumbnail,
                    'price' => $food->price,
                    'type' => $food->type,
                    'description' => $food->description,
                    'is_active' => $food->is_active,
                    'quantity' => $quantity,
                    'total_price' => $food->price * $quantity,
                ];
            });

            // Trả về phản hồi JSON đầy đủ
            return response()->json([
                'message' => 'Combo đã được cập nhật thành công!',
                'data' => [
                    'id' => $combo->id,
                    'name' => $combo->name,
                    'price' => $combo->price,
                    'discount_price' => $combo->discount_price,
                    'description' => $combo->description,
                    'is_active' => $combo->is_active,
                    'img_thumbnail' => $combo->img_thumbnail,
                    'combo_foods' => $comboFoods,
                ]
            ], 200);
        } catch (\Exception $e) {
            // Xử lý lỗi chung
            return response()->json([
                'message' => 'Đã xảy ra lỗi không mong muốn.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy(Combo $combo)
    {
        try {
            // Xóa các liên kết với bảng pivot trước khi xóa combo
            $combo->foods()->detach();

            // Xóa combo
            $combo->delete();

            // Trả về phản hồi JSON
            return response()->json([
                'message' => 'Xóa thành công!'
            ], 200);
        } catch (\Exception $e) {
            // Xử lý lỗi chung
            return response()->json(['error' => 'Xóa không thành công.'], 500);
        }
    }
}
