<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpecialDat;
use App\Models\SpecialDay;
use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class SpecialDayController extends Controller
{
 // Lấy tất cả các ngày đặc biệt
 public function index()
 {
     try {
         $specialDays = SpecialDay::all();
         return response()->json($specialDays);
     } catch (Exception $e) {
         return response()->json(['error' => 'Không thể lấy danh sách ngày đặc biệt', 'message' => $e->getMessage()], 500);
     }
 }

 // Lấy thông tin một ngày đặc biệt theo ID
 public function show($id)
 {
     try {
         $specialDay = SpecialDay::findOrFail($id);
         return response()->json($specialDay);
     } catch (ModelNotFoundException $e) {
         return response()->json(['error' => 'Không tìm thấy ngày đặc biệt', 'message' => $e->getMessage()], 404);
     } catch (Exception $e) {
         return response()->json(['error' => 'Đã xảy ra lỗi', 'message' => $e->getMessage()], 500);
     }
 }

 // Tạo một ngày đặc biệt mới
 public function store(Request $request)
 {
     try {
         $validated = $request->validate([
             'special_date' => 'required|date|unique:special_days,special_date',
             'name' => 'required|string|max:255',
             'type' => 'required|string|max:255',
         ]);

         $specialDay = SpecialDay::create($validated);

         return response()->json($specialDay, 201);
     } catch (Exception $e) {
         return response()->json(['error' => 'Không thể tạo ngày đặc biệt', 'message' => $e->getMessage()], 500);
     }
 }

 // Cập nhật thông tin ngày đặc biệt
 public function update(Request $request, $id)
 {
     try {
         $specialDay = SpecialDay::findOrFail($id);

         $validated = $request->validate([
             'special_date' => 'required|date',
             'name' => 'required|string|max:255',
             'type' => 'required|string|max:255',
         ]);

         $specialDay->update($validated);

         return response()->json($specialDay);
     } catch (ModelNotFoundException $e) {
         return response()->json(['error' => 'Không tìm thấy ngày đặc biệt', 'message' => $e->getMessage()], 404);
     } catch (Exception $e) {
         return response()->json(['error' => 'Không thể cập nhật ngày đặc biệt', 'message' => $e->getMessage()], 500);
     }
 }

 // Xóa một ngày đặc biệt
 public function destroy($id)
 {
     try {
         $specialDay = SpecialDay::findOrFail($id);
         $specialDay->delete();

         return response()->json(['message' => 'Ngày đặc biệt đã được xóa thành công']);
     } catch (ModelNotFoundException $e) {
         return response()->json(['error' => 'Không tìm thấy ngày đặc biệt', 'message' => $e->getMessage()], 404);
     } catch (Exception $e) {
         return response()->json(['error' => 'Không thể xóa ngày đặc biệt', 'message' => $e->getMessage()], 500);
     }
 }
}
