<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboFood;
use App\Models\Food;
use Illuminate\Http\Request;

class ComboFoodController extends Controller
{
   

    public function index()
    {
        try {
            // Lấy danh sách combo kèm theo danh sách food trong combo
            $combos = Combo::with('comboFood.food')->latest('id')->get();

            // Chuyển đổi dữ liệu combo để hiển thị danh sách food trong combo
            $formattedCombos = $combos->map(function ($combo) {
                $foods = [];

                foreach ($combo->comboFood as $comboFood) {
                    $foodId = $comboFood->food->id;

                    // Nếu món ăn đã tồn tại trong danh sách, tăng số lượng
                    if (isset($foods[$foodId])) {
                        $foods[$foodId]['quantity'] += $comboFood->quantity;
                    } else {
                        // Nếu chưa tồn tại, thêm vào danh sách
                        $foods[$foodId] = [
                            'food' => [
                                'id' => $comboFood->food->id,
                                'name' => $comboFood->food->name,
                                'price' => $comboFood->food->price,
                                'img_thumbnail' => $comboFood->food->img_thumbnail,
                                'type' => $comboFood->food->type,
                                'description' => $comboFood->food->description,
                                'is_active' => $comboFood->food->is_active,
                            ],
                            'quantity' => $comboFood->quantity
                        ];
                    }
                }

                return [
                    'id' => $combo->id,
                    'name' => $combo->name,
                    'img_thumbnail' => $combo->img_thumbnail,
                    'price' => $combo->price,
                    'discount_price' => $combo->discount_price,
                    'description' => $combo->description,
                    'foods' => array_values($foods), // Chuyển mảng kết hợp thành danh sách chỉ mục
                    'is_active' => $combo->is_active
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'combos' => $formattedCombos
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy danh sách combo!',
                'error' => $th->getMessage()
            ], 500);
        }
    }

public function store(Request $request)
{
    try {
        // Validate dữ liệu đầu vào
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:combos,name',
            'img_thumbnail' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'foods' => 'required|array|min:1', // Danh sách món ăn phải có ít nhất 1 món
            'foods.*.food_id' => 'required|exists:food,id', // ID của món ăn
            'foods.*.quantity' => 'required|integer|min:1', // Số lượng món ăn phải >= 1
        ], [
            'name.required' => 'Tên combo không được để trống.',
            'name.unique' => 'Tên combo đã tồn tại, vui lòng chọn tên khác.',
            'name.max' => 'Tên combo không được vượt quá 255 ký tự.',
            'price.required' => 'Giá không được để trống.',
            'price.numeric' => 'Giá phải là số.',
            'price.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            'discount_price.numeric' => 'Giá giảm phải là số.',
            'discount_price.min' => 'Giá giảm không được nhỏ hơn 0.',
            'is_active.required' => 'Trạng thái là bắt buộc.',
            'is_active.boolean' => 'Trạng thái phải là đúng hoặc sai.',
            'foods.required' => 'Danh sách món ăn không được để trống.',
            'foods.min' => 'Cần ít nhất một món ăn trong combo.',
            'foods.*.food_id.required' => 'Món ăn không được để trống.',
            'foods.*.food_id.exists' => 'Món ăn không hợp lệ.',
            'foods.*.quantity.required' => 'Số lượng món ăn không được để trống.',
            'foods.*.quantity.integer' => 'Số lượng món ăn phải là số nguyên.',
            'foods.*.quantity.min' => 'Số lượng món ăn phải lớn hơn hoặc bằng 1.',
        ]);

        // Tạo combo mới
        $combo = Combo::create([
            'name' => $validatedData['name'],
            'img_thumbnail' => $validatedData['img_thumbnail'] ?? null,
            'price' => $validatedData['price'],
            'discount_price' => $validatedData['discount_price'] ?? null,
            'description' => $validatedData['description'] ?? null,
            'is_active' => $validatedData['is_active'],
        ]);

        // Chuẩn bị danh sách món ăn với cộng dồn quantity nếu `food_id` trùng
        $foods = [];
        foreach ($validatedData['foods'] as $foodItem) {
            $foodId = $foodItem['food_id'];

            if (isset($foods[$foodId])) {
                $foods[$foodId]['quantity'] += $foodItem['quantity'];
            } else {
                $foods[$foodId] = [
                    'combo_id' => $combo->id,
                    'food_id' => $foodId,
                    'quantity' => $foodItem['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }


        // Chèn dữ liệu vào combo_foods bằng insert để tăng hiệu suất
        ComboFood::insert($foods);

        // Lấy lại combo với danh sách món ăn
        $combo->load('comboFood.food');

        // Chuyển đổi dữ liệu combo để hiển thị danh sách food trong combo
        $formattedFoods = $combo->comboFood->map(function ($comboFood) {
            return [
                'food' => [
                    'id' => $comboFood->food->id,
                    'name' => $comboFood->food->name,
                    'price' => $comboFood->food->price,
                    'img_thumbnail' => $comboFood->food->img_thumbnail,
                    'type' => $comboFood->food->type,
                    'description' => $comboFood->food->description,
                    'is_active' => $comboFood->food->is_active,
                ],
                'quantity' => $comboFood->quantity
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Tạo combo thành công!',
            'data' => [
                'id' => $combo->id,
                'name' => $combo->name,
                'img_thumbnail' => $combo->img_thumbnail,
                'price' => $combo->price,
                'discount_price' => $combo->discount_price,
                'description' => $combo->description,
                'foods' => $formattedFoods, // Định dạng danh sách món ăn
                'is_active' => $combo->is_active
            ]
        ], 201);
    } catch (\Throwable $th) {
        return response()->json([
            'success' => false,
            'message' => 'Không thể tạo combo!',
            'error' => $th->getMessage(),
        ], 500);
    }
}

public function update(Request $request, $id)
{
    try {
        // Kiểm tra xem combo có tồn tại không
        $combo = Combo::find($id);

        if (!$combo) {
            return response()->json([
                'success' => false,
                'message' => 'Combo không tồn tại!'
            ], 404);
        }

        // Validate dữ liệu đầu vào
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:combos,name,' . $id,
            'img_thumbnail' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'foods' => 'required|array|min:1', // Danh sách món ăn phải có ít nhất 1 món
            'foods.*.food_id' => 'required|exists:food,id', // ID của món ăn
            'foods.*.quantity' => 'required|integer|min:1', // Số lượng món ăn phải >= 1
        ], [
            'name.required' => 'Tên combo không được để trống.',
            'name.unique' => 'Tên combo đã tồn tại, vui lòng chọn tên khác.',
            'name.max' => 'Tên combo không được vượt quá 255 ký tự.',
            'price.required' => 'Giá không được để trống.',
            'price.numeric' => 'Giá phải là số.',
            'price.min' => 'Giá phải lớn hơn hoặc bằng 0.',
            'discount_price.numeric' => 'Giá giảm phải là số.',
            'discount_price.min' => 'Giá giảm không được nhỏ hơn 0.',
            'is_active.required' => 'Trạng thái là bắt buộc.',
            'is_active.boolean' => 'Trạng thái phải là đúng hoặc sai.',
            'foods.required' => 'Danh sách món ăn không được để trống.',
            'foods.min' => 'Cần ít nhất một món ăn trong combo.',
            'foods.*.food_id.required' => 'Món ăn không được để trống.',
            'foods.*.food_id.exists' => 'Món ăn không hợp lệ.',
            'foods.*.quantity.required' => 'Số lượng món ăn không được để trống.',
            'foods.*.quantity.integer' => 'Số lượng món ăn phải là số nguyên.',
            'foods.*.quantity.min' => 'Số lượng món ăn phải lớn hơn hoặc bằng 1.',
        ]);

        // Cập nhật thông tin combo
        $combo->update([
            'name' => $validatedData['name'],
            'img_thumbnail' => $validatedData['img_thumbnail'] ?? $combo->img_thumbnail,
            'price' => $validatedData['price'],
            'discount_price' => $validatedData['discount_price'] ?? $combo->discount_price,
            'description' => $validatedData['description'] ?? $combo->description,
            'is_active' => $validatedData['is_active'],
        ]);

        // Xóa toàn bộ món ăn cũ của combo
        ComboFood::where('combo_id', $id)->delete();

        // Chuẩn bị danh sách món ăn mới với cộng dồn quantity nếu `food_id` trùng
        $foods = [];
        foreach ($validatedData['foods'] as $foodItem) {
            $foodId = $foodItem['food_id'];

            if (isset($foods[$foodId])) {
                $foods[$foodId]['quantity'] += $foodItem['quantity'];
            } else {
                $foods[$foodId] = [
                    'combo_id' => $combo->id,
                    'food_id' => $foodId,
                    'quantity' => $foodItem['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Chèn dữ liệu mới vào combo_foods
        ComboFood::insert(array_values($foods));

        // Lấy lại combo với danh sách món ăn sau khi cập nhật
        $combo->load('comboFood.food');

        // Chuyển đổi dữ liệu combo để hiển thị danh sách food trong combo
        $formattedFoods = $combo->comboFood->map(function ($comboFood) {
            return [
                'food' => [
                    'id' => $comboFood->food->id,
                    'name' => $comboFood->food->name,
                    'price' => $comboFood->food->price,
                    'img_thumbnail' => $comboFood->food->img_thumbnail,
                    'type' => $comboFood->food->type,
                    'description' => $comboFood->food->description,
                    'is_active' => $comboFood->food->is_active,
                ],
                'quantity' => $comboFood->quantity
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật combo thành công!',
            'data' => [
                'id' => $combo->id,
                'name' => $combo->name,
                'img_thumbnail' => $combo->img_thumbnail,
                'price' => $combo->price,
                'discount_price' => $combo->discount_price,
                'description' => $combo->description,
                'foods' => $formattedFoods, // Định dạng danh sách món ăn
                'is_active' => $combo->is_active
            ]
        ], 200);
    } catch (\Throwable $th) {
        return response()->json([
            'success' => false,
            'message' => 'Không thể cập nhật combo!',
            'error' => $th->getMessage()
        ], 500);
    }
}



}
