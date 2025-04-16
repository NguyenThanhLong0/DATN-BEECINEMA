<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceRule;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PriceRuleController extends Controller
{
      // Lấy tất cả các quy tắc giá
      public function index()
      {
          try {
              $priceRules = PriceRule::with(['cinema:id,name', 'typeRoom:id,name', 'typeSeat:id,name',])->get();
              return response()->json($priceRules);
          } catch (Exception $e) {
              return response()->json(['error' => 'Không thể lấy danh sách quy tắc giá', 'message' => $e->getMessage()], 500);
          }
      }
  
      // Lấy thông tin một quy tắc giá theo ID
      public function show($id)
      {
          try {
              $priceRule = PriceRule::with(['cinema:id,name', 'typeRoom:id,name', 'typeSeat:id,name',])->findOrFail($id);
              return response()->json($priceRule);
          } catch (ModelNotFoundException $e) {
              return response()->json(['error' => 'Không tìm thấy quy tắc giá', 'message' => $e->getMessage()], 404);
          } catch (Exception $e) {
              return response()->json(['error' => 'Đã xảy ra lỗi', 'message' => $e->getMessage()], 500);
          }
      }
  
      // Tạo một quy tắc giá mới
      public function store(Request $request)
      {
          try {
              $validated = $request->validate([
                  'cinema_id' => 'required|exists:cinemas,id',
                  'type_room_id' => 'nullable|exists:type_rooms,id',
                  'type_seat_id' => 'required|exists:type_seats,id',
                  'day_type' => 'required|string|max:255',
                  'time_slot' => 'nullable|string|max:255',
                  'price' => 'required|integer',
                  'valid_from' => 'required|date',
                  'valid_to' => 'nullable|date',
              ]);
  
              $priceRule = PriceRule::create($validated);
  
              return response()->json($priceRule, 201);
          } catch (Exception $e) {
              return response()->json(['error' => 'Không thể tạo quy tắc giá', 'message' => $e->getMessage()], 500);
          }
      }
  
      // Cập nhật thông tin quy tắc giá
      public function update(Request $request, $id)
      {
          try {
              $priceRule = PriceRule::findOrFail($id);
  
              $validated = $request->validate([
                  'cinema_id' => 'required|exists:cinemas,id',
                  'type_room_id' => 'nullable|exists:type_rooms,id',
                  'type_seat_id' => 'required|exists:type_seats,id',
                  'day_type' => 'required|string|max:255',
                  'time_slot' => 'required|string|max:255',
                  'price' => 'required|integer',
                  'valid_from' => 'required|date',
                  'valid_to' => 'nullable|date',
              ]);
  
              $priceRule->update($validated);
  
              return response()->json($priceRule);
          } catch (ModelNotFoundException $e) {
              return response()->json(['error' => 'Không tìm thấy quy tắc giá', 'message' => $e->getMessage()], 404);
          } catch (Exception $e) {
              return response()->json(['error' => 'Không thể cập nhật quy tắc giá', 'message' => $e->getMessage()], 500);
          }
      }
  
      // Xóa một quy tắc giá
      public function destroy($id)
      {
          try {
              $priceRule = PriceRule::findOrFail($id);
              $priceRule->delete();
  
              return response()->json(['message' => 'Quy tắc giá đã được xóa thành công']);
          } catch (ModelNotFoundException $e) {
              return response()->json(['error' => 'Không tìm thấy quy tắc giá', 'message' => $e->getMessage()], 404);
          } catch (Exception $e) {
              return response()->json(['error' => 'Không thể xóa quy tắc giá', 'message' => $e->getMessage()], 500);
          }
      }
}
