<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BannerController extends Controller
{
    //
    public function index()
    {
        try {
            $banners = Banner::all();
            return response()->json($banners, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không lấy được dữ liệu banner!',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function show(Banner $banner)
    {
        try {
            if (!$banner) {
                return response()->json([
                    'message' => 'Banner không tồn tại!',
                ]);
            }
            return response()->json([
                'message' => 'chi tiết banner thành công!',
                'banner' => $banner
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không lấy được dữ liệu banner!',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, Banner $banner)
    {
        try {
            Log::info('Tạo mới banner:', $request->all());

            // Kiểm tra nếu images không phải mảng
            if (!$request->has('images') || !is_array($request->images)) {
                return response()->json(['message' => 'Hình ảnh phải là một mảng'], 400);
            }

            // Validate dữ liệu đầu vào
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:banners,name,' . $banner->id,
                'description' => 'nullable|string',
                'is_active' => 'required|boolean',
                'images' => 'required|array',
                'images.*' => 'required|url', // Mỗi phần tử trong images phải là URL hợp lệ
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max.string' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'array' => ':attribute phải là một mảng.',
                'url' => ':attribute phải là một URL hợp lệ.',
            ], [
                'name' => 'Tên banner',
                'description' => 'Mô tả',
                'is_active' => 'Trạng thái kích hoạt',
                'images' => 'Danh sách hình ảnh',
                'images.*' => 'URL hình ảnh',
            ]);

            // Nếu is_active = true thì cập nhật tất cả banner khác về is_active = 0
            if ($request->is_active) {
                Banner::where('is_active', 1)->update(['is_active' => 0]);
            }

            // Tạo banner mới
            $banner = Banner::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active ? 1 : 0,
                'img_thumbnail_url' => $request->images, // Laravel sẽ tự động chuyển thành JSON
            ]);

            return response()->json([
                'message' => 'Banner được tạo thành công!',
                'banner' => $banner
            ], 201);
        } catch (\Throwable $th) {
            Log::error('Lỗi khi thêm banner:', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Tạo banner thất bại!',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Banner $banner)
    {
        try {
            Log::info('Cập nhật banner:', ['id' => $banner->id, 'data' => $request->all()]);

            // Kiểm tra nếu images không phải mảng
            if ($request->has('images') && !is_array($request->images)) {
                return response()->json(['message' => 'Hình ảnh phải là một mảng'], 400);
            }

            // Nếu is_active được gửi lên và là false, không cho phép cập nhật
            if ($request->has('is_active') && !$request->is_active) {
                return response()->json(['message' => 'Không thể tắt trạng thái kích hoạt.'], 400);
            }

            // Validate dữ liệu đầu vào
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:banners,name,' . $banner->id,
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean', // Chỉ kiểm tra nếu có trong request
                'images' => 'required|array',
                'images.*' => 'required|url', // Mỗi phần tử trong images phải là URL hợp lệ
            ], [
                'required' => ':attribute không được để trống.',
                'string' => ':attribute phải là một chuỗi ký tự.',
                'max.string' => ':attribute không được vượt quá :max ký tự.',
                'boolean' => ':attribute phải là đúng hoặc sai.',
                'array' => ':attribute phải là một mảng.',
                'url' => ':attribute phải là một URL hợp lệ.',
                'unique' => ':attribute đã tồn tại, vui lòng chọn tên khác.',
            ], [
                'name' => 'Tên banner',
                'description' => 'Mô tả',
                'is_active' => 'Trạng thái kích hoạt',
                'images' => 'Danh sách hình ảnh',
                'images.*' => 'URL hình ảnh',
            ]);

            // Nếu cập nhật is_active = true, thì đặt tất cả các banner khác về false
            if ($request->is_active) {
                Banner::where('id', '!=', $banner->id)->update(['is_active' => false]);
            }

            // Cập nhật dữ liệu banner
            $banner->update([
                'name' => $request->name ?? $banner->name,
                'description' => $request->description ?? $banner->description,
                'is_active' => $request->is_active ?? $banner->is_active,
                'img_thumbnail_url' => $request->images ?? $banner->img_thumbnail_url,
            ]);

            return response()->json([
                'message' => 'Banner được cập nhật thành công!',
                'banner' => $banner
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Lỗi khi cập nhật banner:', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Cập nhật banner thất bại!',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(Banner $banner)
    {
        try {
            Log::info('Xóa banner:', ['id' => $banner->id]);
            $banner->delete();

            return response()->json([
                'message' => 'Banner đã được xóa thành công!'
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Lỗi khi xóa banner:', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Xóa banner thất bại!',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function getActiveBanner()
    {
        try {
            $banner = Banner::where('is_active', 1)->first(); // Lấy banner đầu tiên đang active

            if (!$banner) {
                return response()->json([
                    'message' => 'Không có banner nào đang hoạt động!',
                ], 404);
            }

            return response()->json([
                'message' => 'Lấy banner đang hoạt động thành công!',
                'banner' => $banner
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không lấy được dữ liệu banner!',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
