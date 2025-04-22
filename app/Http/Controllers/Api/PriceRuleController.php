<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use App\Models\PriceRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PriceRuleController extends Controller
{
      public function index()
      {
          try {
              $regularSeatId = 1;
              $vipSeatId = 2;
              $doubleSeatId = 3;
      
              $rules = PriceRule::with(['cinema', 'typeRoom'])->get()->groupBy(['cinema_id', 'type_room_id']);
      
              $result = [];
      
              foreach ($rules as $cinemaId => $roomGroups) {
                  foreach ($roomGroups as $typeRoomId => $items) {
                      $dayGrouped = $items->groupBy(['day_type', 'time_slot']);
                      $dayTypes = [];
      
                      foreach ($dayGrouped as $dayType => $timeSlots) {
                          $slots = [];
      
                          foreach ($timeSlots as $timeSlot => $slotItems) {
                              $base = $slotItems->firstWhere('type_seat_id', $regularSeatId);
                              $vip = $slotItems->firstWhere('type_seat_id', $vipSeatId);
                              $double = $slotItems->firstWhere('type_seat_id', $doubleSeatId);
      
                              if (!$base) continue;
      
                              $slots[] = [
                                  'time_slot' => $timeSlot,
                                  'base_price' => $base->price,
                                  'surcharge' => [
                                      'vip' => optional($vip)->price ? ($vip->price - $base->price) : 0,
                                      'double' => optional($double)->price ? ($double->price - ($base->price * 2)) : 0,
                                  ]
                              ];
                          }
      
                          $dayTypes[] = [
                              'day_type' => $dayType,
                              'time_slots' => $slots
                          ];
                      }
      
                      $first = $items->first();
      
                      $result[] = [
                          'cinema_id' => (int)$cinemaId,
                          'cinema_name' => optional($first->cinema)->name,
                          'type_room_id' => (int)$typeRoomId,
                          'type_room_name' => optional($first->typeRoom)->name,
                          'valid_from' => $first->valid_from,
                          'valid_to' => $first->valid_to,
                          'day_types' => $dayTypes,
                      ];
                  }
              }
      
              return response()->json($result);
      
          } catch (Exception $e) {
              return response()->json([
                  'error' => 'Không thể lấy danh sách quy tắc giá',
                  'message' => $e->getMessage()
              ], 500);
          }
      }
      

      public function show(Request $request)
    {
        try {
            // Validation cho cinema_id và type_room_id
            $validated = $request->validate([
                'cinema_id' => 'required|exists:cinemas,id',
                'type_room_id' => 'nullable|exists:type_rooms,id',
            ]);

            $cinemaId = $validated['cinema_id'];
            $typeRoomId = $validated['type_room_id'];

            // Lấy các bản ghi PriceRule theo cinema_id và type_room_id
            $rules = PriceRule::with(['cinema:id,name', 'typeRoom:id,name'])
                ->where('cinema_id', $cinemaId)
                ->where('type_room_id', $typeRoomId)
                ->get();

            if ($rules->isEmpty()) {
                return response()->json(['error' => 'Không tìm thấy quy tắc giá'], 404);
            }

            $regularSeatId = 1;
            $vipSeatId = 2;
            $doubleSeatId = 3;

            // Nhóm dữ liệu theo day_type và time_slot
            $dayGrouped = $rules->groupBy(['day_type', 'time_slot']);
            $dayTypes = [];

            foreach ($dayGrouped as $dayType => $timeSlots) {
                $slots = [];

                foreach ($timeSlots as $timeSlot => $slotItems) {
                    $base = $slotItems->firstWhere('type_seat_id', $regularSeatId);
                    $vip = $slotItems->firstWhere('type_seat_id', $vipSeatId);
                    $double = $slotItems->firstWhere('type_seat_id', $doubleSeatId);

                    if (!$base) continue;

                    $slots[] = [
                        'time_slot' => $timeSlot,
                        'base_price' => $base->price,
                        'surcharge' => [
                            'vip' => optional($vip)->price ? ($vip->price - $base->price) : 0,
                            'double' => optional($double)->price ? ($double->price - $base->price) : 0,
                        ]
                    ];
                }

                $dayTypes[] = [
                    'day_type' => $dayType,
                    'time_slots' => $slots
                ];
            }

            $first = $rules->first();

            $result = [
                'cinema_id' => (int)$cinemaId,
                'cinema_name' => optional($first->cinema)->name,
                'type_room_id' => (int)$typeRoomId,
                'type_room_name' => optional($first->typeRoom)->name,
                'valid_from' => $first->valid_from,
                'valid_to' => $first->valid_to,
                'day_types' => $dayTypes,
            ];

            return response()->json($result);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Dữ liệu không hợp lệ', 'message' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => 'Đã xảy ra lỗi', 'message' => $e->getMessage()], 500);
        }
    }
  
      // Tạo một quy tắc giá mới
      public function store(Request $request)
      {
          try {
              // Validation cơ bản cho cinema_id và type_room_id
              $validated = $request->validate([
                  'cinema_id' => 'required|exists:cinemas,id',
                  'type_room_id' => 'nullable|exists:type_rooms,id',
                  'price' => 'required|array',
                  'surcharge' => 'required|array',
              ]);
  
              // Lấy dữ liệu từ request
              $prices = $request->input('price');
              $surcharges = $request->input('surcharge');
  
              // Bắt đầu transaction để đảm bảo dữ liệu nhất quán
              DB::beginTransaction();
  
              // Duyệt qua mảng prices
              foreach ($prices as $day => $timeSlots) {
                  foreach ($timeSlots as $slot => $basePrice) {
                      // Lấy phụ phí, mặc định là 0 nếu không tồn tại
                      $vipSurcharge = $surcharges[$day][$slot]['vip'] ?? 0;
                      $doubleSurcharge = $surcharges[$day][$slot]['double'] ?? 0;
  
                      // Tạo bản ghi cho ghế thường (type_seat_id = 1)
                      PriceRule::create([
                          'cinema_id' => $request->cinema_id,
                          'type_room_id' => $request->type_room_id,
                          'type_seat_id' => 1,
                          'day_type' => $day,
                          'time_slot' => $slot,
                          'price' => $basePrice,
                          'valid_from' => now(),
                      ]);
  
                      // Tạo bản ghi cho ghế VIP (type_seat_id = 2)
                      PriceRule::create([
                          'cinema_id' => $request->cinema_id,
                          'type_room_id' => $request->type_room_id,
                          'type_seat_id' => 2,
                          'day_type' => $day,
                          'time_slot' => $slot,
                          'price' => $basePrice + $vipSurcharge,
                          'valid_from' => now(),
                      ]);
  
                      // Tạo bản ghi cho ghế đôi (type_seat_id = 3)
                      PriceRule::create([
                        'cinema_id' => $request->cinema_id,
                        'type_room_id' => $request->type_room_id,
                        'type_seat_id' => 3,
                        'day_type' => $day,
                        'time_slot' => $slot,
                        'price' => ($basePrice * 2 ) + $doubleSurcharge, // <-- Đây mới đúng
                        'valid_from' => now(),
                    ]);
                  }
              }
  
              // Commit transaction
              DB::commit();
  
              return response()->json(['message' => 'Quy tắc giá đã được tạo thành công'], 201);
          } catch (Exception $e) {
              // Rollback transaction nếu có lỗi
              DB::rollBack();
              return response()->json(['error' => 'Không thể tạo quy tắc giá', 'message' => $e->getMessage()], 500);
          }
      }
  
      // Cập nhật thông tin quy tắc giá
      public function updateRulePrices(Request $request)
      {
          try {
              // Validation cơ bản cho cinema_id và type_room_id
              $validated = $request->validate([
                  'cinema_id' => 'required|exists:cinemas,id',
                  'type_room_id' => 'nullable|exists:type_rooms,id',
                  'price' => 'required|array',
                  'surcharge' => 'required|array',
              ]);
  
              // Lấy dữ liệu từ request
              $prices = $request->input('price');
              $surcharges = $request->input('surcharge');
  
              // Bắt đầu transaction
              DB::beginTransaction();
  
              // Xóa các bản ghi PriceRule hiện có liên quan đến cinema_id và type_room_id
              PriceRule::where('cinema_id', $request->cinema_id)
                  ->where('type_room_id', $request->type_room_id)
                  ->delete();
  
              // Duyệt qua mảng prices để tạo mới các bản ghi
              foreach ($prices as $day => $timeSlots) {
                  foreach ($timeSlots as $slot => $basePrice) {
                      // Lấy phụ phí, mặc định là 0 nếu không tồn tại
                      $vipSurcharge = $surcharges[$day][$slot]['vip'] ?? 0;
                      $doubleSurcharge = $surcharges[$day][$slot]['double'] ?? 0;
  
                      // Tạo bản ghi cho ghế thường (type_seat_id = 1)
                      PriceRule::create([
                          'cinema_id' => $request->cinema_id,
                          'type_room_id' => $request->type_room_id,
                          'type_seat_id' => 1,
                          'day_type' => $day,
                          'time_slot' => $slot,
                          'price' => $basePrice,
                          'valid_from' => now(),
                      ]);
  
                      // Tạo bản ghi cho ghế VIP (type_seat_id = 2)
                      PriceRule::create([
                          'cinema_id' => $request->cinema_id,
                          'type_room_id' => $request->type_room_id,
                          'type_seat_id' => 2,
                          'day_type' => $day,
                          'time_slot' => $slot,
                          'price' => $basePrice + $vipSurcharge,
                          'valid_from' => now(),
                      ]);
  
                      // Tạo bản ghi cho ghế đôi (type_seat_id = 3)
                      PriceRule::create([
                          'cinema_id' => $request->cinema_id,
                          'type_room_id' => $request->type_room_id,
                          'type_seat_id' => 3,
                          'day_type' => $day,
                          'time_slot' => $slot,
                          'price' => ($basePrice * 2) + $doubleSurcharge,
                          'valid_from' => now(),
                      ]);
                  }
              }
  
              // Commit transaction
              DB::commit();
  
              return response()->json(['message' => 'Quy tắc giá đã được cập nhật thành công'], 200);
          } catch (Exception $e) {
              // Rollback transaction nếu có lỗi
              DB::rollBack();
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

      //Lấy giá theo rạp 
      public function cinemaPrice($id)
      {
          try {
              $prices = PriceRule::where('price_rules.cinema_id', $id)
                  ->join('type_rooms', 'price_rules.type_room_id', '=', 'type_rooms.id')
                  ->join('type_seats', 'price_rules.type_seat_id', '=', 'type_seats.id')
                  ->join('cinemas', 'price_rules.cinema_id', '=', 'cinemas.id')
                  ->select(
                      'price_rules.type_room_id',
                      'type_rooms.name as room_name',
                      'cinemas.name as cinema_name',
                      'price_rules.type_seat_id',
                      'type_seats.name as seat_name',
                      'price_rules.price',
                      'price_rules.day_type',
                      'price_rules.time_slot'
                  )
                  ->get();
      
              $grouped = $prices->groupBy('type_room_id')->map(function ($items, $roomId) {
                  return [
                      'type_room_id' => $roomId,
                      'room_name' => $items->first()->room_name,
                      'cinema_name' => $items->first()->cinema_name,
                      'seats' => $items->map(function ($item) {
                          return [
                              'type_seat_id' => $item->type_seat_id,
                              'seat_name' => $item->seat_name,
                              'price' => $item->price,
                              'day_type' => $item->day_type,
                              'time_slot' => $item->time_slot,
                          ];
                      })->values()
                  ];
              })->values();
      
              return response()->json([
                  'status' => true,
                  'message' => 'Lấy dữ liệu giá vé thành công',
                  'data' => $grouped
              ], 200);
      
          } catch (\Exception $e) {
              return response()->json([
                  'status' => false,
                  'message' => 'Đã xảy ra lỗi khi lấy dữ liệu',
                  'error' => $e->getMessage()
              ], 500);
          }
      }
      
}
