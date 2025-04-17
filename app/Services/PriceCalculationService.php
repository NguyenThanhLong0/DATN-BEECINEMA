<?php

namespace App\Services;

use App\Models\Showtime;
use App\Models\Seat;
use App\Models\PriceRule;
use App\Models\SpecialDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PriceCalculationService
{
    public function calculatePrice(Showtime $showtime, Seat $seat): int
    {
        $startTime = Carbon::parse($showtime->start_time);
        $cinemaId = $showtime->cinema_id;
        $roomTypeId = $showtime->room->type_room_id; // Lấy từ room.type_room_id
        $seatTypeId = $seat->type_seat_id;
        $timeSlot = $this->determineTimeSlot($startTime);
        // Log::info('--- Dữ liệu tính giá ---', [
        //     'startTime' => $startTime->toDateTimeString(),
        //     'cinemaId' => $cinemaId,
        //     'roomTypeId' => $roomTypeId,
        //     'seatTypeId' => $seatTypeId,
        //     'timeSlot' => $timeSlot,
        // ]);
        // Tính giá
        $price = $this->determinePrice($cinemaId, $roomTypeId, $seatTypeId, $startTime, $timeSlot);

        // Nếu không tìm thấy giá từ PriceRules, dùng giá mặc định từ type_seat
        $price = $price ?? ($seat->typeSeat->price ?? 0);

        // Cộng phụ phí từ rạp (nếu có)
        $surcharge = $showtime->cinema->surcharge ?? 0;
        $price += $surcharge;

        return $price;
    }

    private function determinePrice($cinemaId, $roomTypeId, $seatTypeId, Carbon $date, $timeSlot): ?int
    {
        // Kiểm tra ngày đặc biệt
        $specialDay = $this->checkSpecialDay($date);
        if ($specialDay) {
            $dayType = $specialDay->type;
            // Log::info("du lieuj special day",['special day' =>$dayType]);

            return $this->getPriceFromRules($cinemaId, $roomTypeId, $seatTypeId, $dayType, $timeSlot);
        }

        // Nếu không phải ngày đặc biệt, kiểm tra ngày thường/cuối tuần
        $dayType = $this->determineDayType($date);
        return $this->getPriceFromRules($cinemaId, $roomTypeId, $seatTypeId, $dayType, $timeSlot);
    }

    private function checkSpecialDay(Carbon $date): ?SpecialDay
    {
        return SpecialDay::where('special_date', $date->toDateString())->first();
    }

    private function determineDayType(Carbon $date): string
    {
        $dayOfWeek = $date->dayOfWeek;
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            return 'Weekend';
        }
        return 'Weekday';
    }

    private function determineTimeSlot(Carbon $date): string
    {
        $hour = $date->hour;
        if ($hour >= 7 && $hour < 18) return 'sáng';
        if ($hour >= 18 && $hour < 24) return 'tối';
        return 'Late';
    }

    // private function getPriceFromRules($cinemaId, $roomTypeId, $seatTypeId, $dayType, $timeSlot)
    // {
    //     $rule = PriceRule::where('cinema_id', $cinemaId)
    //         ->where(function ($query) use ($roomTypeId) {
    //             $query->where('type_room_id', $roomTypeId)
    //                   ->orWhereNull('type_room_id');
    //         })
    //         ->where('type_seat_id', $seatTypeId)
    //         ->where('day_type', $dayType)
    //         ->where('time_slot', $timeSlot)
    //         ->whereDate('valid_from', '<=', now()->toDateString())
    //         ->where(function ($query) {
    //             $query->whereNull('valid_to')
    //                   ->orWhereDate('valid_to', '>=', now()->toDateString()); // Sửa dòng này
    //         })
    //         ->orderBy('updated_at', 'desc')
    //         ->first();
    
    //     Log::info("rule", ['price' => $rule]);
    
    //     return $rule ? $rule->price : null;
    // }
    

    private function getPriceFromRules($cinemaId, $roomTypeId, $seatTypeId, $dayType, $timeSlot)
    {
        // Sử dụng Carbon để lấy ngày hiện tại theo định dạng chuẩn
        $currentDate = Carbon::today(); // Lấy ngày hiện tại theo định dạng 'Y-m-d'
    
        // Log dữ liệu đầu vào trước khi truy vấn
        // Log::info("Price Rule Search", [
        //     'cinema_id' => $cinemaId,
        //     'roomTypeId' => $roomTypeId,
        //     'seatTypeId' => $seatTypeId,
        //     'dayType' => $dayType,
        //     'timeSlot' => $timeSlot,
        //     'currentDate' => $currentDate->toDateString()
        // ]);
    
        $rule = PriceRule::where('cinema_id', $cinemaId)
            ->where(function ($query) use ($roomTypeId) {
                $query->where('type_room_id', $roomTypeId)
                      ->orWhereNull('type_room_id');  // Điều kiện cho phòng có hoặc không có loại phòng
            })
            ->where('type_seat_id', $seatTypeId)  // Kiểm tra loại ghế
            ->where('day_type', $dayType)  // Kiểm tra loại ngày (ví dụ: "Ngày lễ")
            ->where('time_slot', $timeSlot)  // Kiểm tra khung giờ
            ->whereDate('valid_from', '<=', $currentDate) // So sánh ngày bắt đầu
            ->where(function ($query) use ($currentDate) {
                // Kiểm tra nếu ngày kết thúc là NULL hoặc lớn hơn hoặc bằng ngày hiện tại
                $query->whereNull('valid_to')
                      ->orWhereDate('valid_to', '>=', $currentDate);
            })
            ->orderBy('updated_at', 'desc') // Lấy quy tắc giá mới nhất
            ->first();
    
        // Log kết quả tìm kiếm quy tắc
        Log::info("rule", ['price' => $rule]);
    
        return $rule ? $rule->price : null;  // Trả về giá nếu tìm thấy quy tắc, ngược lại trả về null
    }
    

}