<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Branch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Eager load cinemas cho tất cả branches
            $branches = Branch::with('cinemas')->latest('id')->paginate(10);

            return response()->json($branches);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Không thể lấy danh sách chi nhánh!'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:branches',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $branch = Branch::create($request->only(['name', 'is_active']));
            return response()->json(['message' => 'Thêm mới thành công!', 'branch' => $branch], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Thêm mới thất bại!', 'error' => $th->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    /**
     * Display the specified resource along with cinemas.
     */
    public function show(Branch $branch)
    {
        try {
            // Eager load cinemas for the branch
            $branchData = $branch->load('cinemas'); // Eager load cinemas

            // Trả về chi nhánh và danh sách các rạp chiếu liên quan
            return response()->json([
                'branch' => $branchData,
                // 'cinemas' => $branchData->cinemas
            ]);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Không thể lấy thông tin chi nhánh!'], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */

    public function update(Request $request, Branch $branch)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255|unique:branches,name,' . $branch->id,
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $branch->fill($request->only(['name', 'is_active'])); // Gán giá trị mới
            if ($request->has('name') && $request->name !== $branch->getOriginal('name')) {
                $branch->slug = null; // Đặt lại slug để Sluggable tự tạo lại
            }
            $branch->save(); // Lưu lại model với slug mới

            return response()->json(['message' => 'Sửa thành công!', 'branch' => $branch], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Sửa thất bại!'], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        try {
            // Kiểm tra xem chi nhánh có rạp chiếu nào không
            if ($branch->cinemas()->count() > 0) {
                return response()->json(['message' => 'Không thể xóa chi nhánh đã có rạp chiếu!'], 422);
            }

            // Kiểm tra trạng thái của chi nhánh
            if ($branch->is_active !== 0) {
                return response()->json(['message' => 'Chỉ có thể xóa các chi nhánh không hoạt động!'], 422);
            }

            // Tiến hành xóa chi nhánh
            $branch->delete();
            return response()->json(['message' => 'Xóa thành công!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Xóa thất bại!', 'error' => $th->getMessage()], 500);
        }
    }

    public function branchesWithCinemasActive()
{
    try {
        // Lấy danh sách branch đang hoạt động và cinemas đang hoạt động của mỗi branch
        $branches = Branch::where('is_active', 1) // Chỉ lấy branch đang hoạt động
            ->with(['cinemas' => function ($query) {
                $query->where('is_active', 1); // Chỉ lấy cinema đang hoạt động
            }])
            ->get(); // Lấy danh sách

        return response()->json([
            'branches' => $branches,
        ]);
    } catch (\Throwable $th) {
        return response()->json(['message' => 'Không thể lấy danh sách chi nhánh!'], 500);
    }
}
}
