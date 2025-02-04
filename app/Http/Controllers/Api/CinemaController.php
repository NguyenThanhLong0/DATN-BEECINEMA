<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cinema;
use Illuminate\Support\Facades\Validator;

class CinemaController extends Controller
{
    /**
     * Display a listing of cinemas.
     */
    public function index()
    {
        try {
            $cinemas = Cinema::with('branch')->get();
            return response()->json($cinemas);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Không thể lấy danh sách rạp!'], 500);
        }
    }

    /**
     * Store a newly created cinema in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255|unique:cinemas',
            'slug' => 'required|string|max:255|unique:cinemas',
            'address' => 'required|string',
            'surcharge' => 'nullable|numeric',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $cinema = Cinema::create($request->all());
            return response()->json(['message' => 'Thêm mới thành công!', 'cinema' => $cinema], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Thêm mới thất bại!', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Display the specified cinema.
     */
    public function show(Cinema $cinema)
    {
        try {
            return response()->json($cinema);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Không thể lấy thông tin rạp!'], 500);
        }
    }

    /**
     * Update the specified cinema in storage.
     */
    public function update(Request $request, Cinema $cinema)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'name' => 'nullable|string|max:255|unique:cinemas,name,' . $cinema->id,
            'slug' => 'nullable|string|max:255|unique:cinemas,slug,' . $cinema->id,
            'address' => 'nullable|string',
            'surcharge' => 'nullable|numeric',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $cinema->update($request->all());
            return response()->json(['message' => 'Sửa thành công!', 'cinema' => $cinema], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Sửa thất bại!', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Remove the specified cinema from storage.
     */
    public function destroy(Cinema $cinema)
    {
        try {
            $cinema->delete();
            return response()->json(['message' => 'Xóa thành công!'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Xóa thất bại!', 'error' => $th->getMessage()], 500);
        }
    }
}
