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
    public function index(){
        try {
            // Truy vấn dữ liệu Combo và liên kết với Food
            $combos = Combo::with('foods')->get();

            // Nếu không có combo nào, trả về thông báo lỗi
            if ($combos->isEmpty()) {
                return response()->json(['message' => 'No combos found.'], 404);
            }

            // Xử lý và định dạng dữ liệu theo yêu cầu
            $result = $combos->map(function ($combo) {
                return [
                    'id' =>$combo->id,
                    'name' => $combo->name,
                    'price' => $combo->price , // Nếu có discount, dùng discount_price, nếu không dùng price
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

            return response()->json($result);
       
        } catch (\Exception $e) {
            // Xử lý lỗi chung khác
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric',
                'discount_price' => 'nullable|numeric',
                'description' => 'nullable|string',
                'is_active' => 'required|boolean',
                'img_thumbnail' => 'nullable|string|max:255',
                'combo_foods' => 'required|array', // Mảng chứa thông tin các món ăn
                'combo_foods.*.id' => 'required|exists:food,id', // Kiểm tra id của food có tồn tại trong bảng foods
                'combo_foods.*.quantity' => 'required|numeric|min:1', // Kiểm tra quantity
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

            return response()->json(['message' => 'Combo created successfully!', 'combo' => $combo], 201);

        } catch (\Exception $e) {
            // Xử lý lỗi chung
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    public function update(Request $request, Combo $combo)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255|unique:combos,name,' . $combo->id,
            'img_thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        try {
            $data = $request->except('img_thumbnail');
            $img_thumbnail_old = $combo->img_thumbnail;
            $data['img_thumbnail'] = $img_thumbnail_old;
        
            // Kiểm tra nếu có file ảnh mới
            if ($request->hasFile('img_thumbnail')) {
                // Xóa ảnh cũ nếu tồn tại
            if ($img_thumbnail_old && Storage::disk('public')->exists($img_thumbnail_old)) {
                Storage::disk('public')->delete($img_thumbnail_old);
            }
        
                // Lưu ảnh mới vào thư mục 'combos'
                $files_img_thumbnails = $request->file('img_thumbnail')->store('combos', 'public');

                $data['img_thumbnail'] = $files_img_thumbnails;
            }
    
            $combo->update($data);
    
            return response()->json([
                'message' => 'Sửa thành công!',
                'status' => true,
                'data' => $combo
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Sửa thất bại!',
                'status' => false,
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(Combo $combo)
    {
        try {
              // Kiểm tra nếu có ảnh và xóa nó
        if ($combo->img_thumbnail && Storage::disk('public')->exists($combo->img_thumbnail)) {
            try {
                Storage::disk('public')->delete($combo->img_thumbnail);
            } catch (\Exception $e) {
                Log::error('Lỗi khi xóa ảnh trong quá trình xóa com$combo: ' . $e->getMessage());
            }
        }
            // Delete the com$combo record
            $combo->delete();
            return response()->json([
                'message' => 'Xóa thành công!',
                'status' => true
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Xóa thất bại!',
                'status' => false,
                // 'error' => $th->getMessage()
            ], 500);
        }
    }
}
