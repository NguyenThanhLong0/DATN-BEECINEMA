<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'type_seat_id',
        'coordinates_x',
        'coordinates_y',
        'name',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];


    protected $dates = ['deleted_at'];
    // Quan hệ với phòng (Room)
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Quan hệ với loại ghế (TypeSeat)
    public function typeSeat()
    {
        return $this->belongsTo(TypeSeat::class);
    }

    public static function getTotalSeat($room_id, $is_active = null)
    {
        $totalSeat = 0;
        $query = Seat::where('room_id', $room_id);

        if (!is_null($is_active)) {
            $query->where('is_active', $is_active);
        }

        $seats = $query->get();

        foreach ($seats as $seat) {
            if ($seat->type_seat_id == 3) {
                $totalSeat += 2; // Ghế đôi có 2 chỗ ngồi
            } else {
                $totalSeat += 1; // Các loại ghế khác có 1 chỗ ngồi
            }
        }

        return $totalSeat;
    }
}
