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
            $combos = Combo::query()->latest('id')->paginate(10);
            return response()->json([
                'message' => 'Hiển thị thành công',
                'satus' => true,
                'data' => $combos
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'lỗi, hiển thị không thành công',
                'satus' => false,
            ]);
        }
    }
    public function show($id)
    {
        try {
            $combos = Combo::query()->findOrFail($id);
            return response()->json([
                'message' => 'Chi tiết',
                'status' => true,
                'data' => $combos
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
            'name' => 'required|string|max:255|unique:combos',
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
           
            // Kiểm tra xem có file hình ảnh không
        if ($request->hasFile('img_thumbnail')) {
            // Lưu file hình ảnh vào thư mục 'combos' và lưu đường dẫn vào mảng $data
            $files_img_thumbnails = $request->file('img_thumbnail')->store('combos', 'public');
            $data['img_thumbnail'] = $files_img_thumbnails;
        }
            $combo = Combo::create($data);
            
            return response()->json([
                'message' => 'Thêm mới thành công!',
                'status' => true,
                'data' => $combo
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Thêm mới thất bại',
                'status' => false,
                'error' => $th->getMessage()
            ], 500);
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
