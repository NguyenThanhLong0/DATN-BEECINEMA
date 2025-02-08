<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FoodController extends Controller
{
    //
    public function index()
    {
        try {
            $foods = Food::query()->latest('id')->paginate(10);
            return response()->json([
                'message' => 'Hiển thị thành công',
                'satus' => true,
                'data' => $foods
            ]);
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
        $data = $request->all();
        
        // Validate input fields
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255|unique:foods,name',
            'price' => 'required|numeric|min:0',
            'img_thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
           
            // Kiểm tra xem có file hình ảnh không
        if ($request->hasFile('img_thumbnail')) {
            // Lưu file hình ảnh vào thư mục 'foods' và lưu đường dẫn vào mảng $data
            $files_img_thumbnails = $request->file('img_thumbnail')->store('foods', 'public');
            $data['img_thumbnail'] = $files_img_thumbnails;
        }
            $food = Food::create($data);
            
            return response()->json([
                'message' => 'Thêm mới thành công!',
                'status' => true,
                'data' => $food
            ]);
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
        $data = $request->all();
        $validator = Validator::make($data, [
            'name' => 'nullable|string|max:255|unique:foods,name,' . $food->id,
            'price' => 'nullable|numeric|min:0',
            'img_thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        try {
            $data = $request->except('img_thumbnail');
            $img_thumbnail_old = $food->img_thumbnail;
            $data['img_thumbnail'] = $img_thumbnail_old;
        
            // Kiểm tra nếu có file ảnh mới
            if ($request->hasFile('img_thumbnail')) {
                // Xóa ảnh cũ nếu tồn tại
            if ($img_thumbnail_old && Storage::disk('public')->exists($img_thumbnail_old)) {
                Storage::disk('public')->delete($img_thumbnail_old);
            }
        
                // Lưu ảnh mới vào thư mục 'foods'
                $files_img_thumbnails = $request->file('img_thumbnail')->store('foods', 'public');

                $data['img_thumbnail'] = $files_img_thumbnails;
            }
    
            $food->update($data);
    
            return response()->json([
                'message' => 'Sửa thành công!',
                'status' => true,
                'data' => $food
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Sửa thất bại!',
                'status' => false,
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(Food $food)
    {
        try {
              // Kiểm tra nếu có ảnh và xóa nó
        if ($food->img_thumbnail && Storage::disk('public')->exists($food->img_thumbnail)) {
            try {
                Storage::disk('public')->delete($food->img_thumbnail);
            } catch (\Exception $e) {
                Log::error('Lỗi khi xóa ảnh trong quá trình xóa food: ' . $e->getMessage());
            }
        }

            // Delete the food record
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
