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
            $branches = Branch::query()->latest('id')->paginate(10);
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
            'slug' => 'required|string|max:255|unique:branches',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $branch = Branch::create($request->only(['name', 'slug', 'is_active']));
            return response()->json(['message' => 'Thêm mới thành công!', 'branch' => $branch], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Thêm mới thất bại!'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        try {
            return response()->json($branch);
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
            'slug' => 'nullable|string|max:255|unique:branches,slug,' . $branch->id,
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $branch->update($request->only(['name', 'slug', 'is_active']));
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
            $branch->delete();
            return response()->json(['message' => 'Xóa thành công!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Xóa thất bại!'], 500);
        }
    }
}
