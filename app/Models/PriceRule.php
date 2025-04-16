<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceRule extends Model
{
    use HasFactory;
    protected $fillable = [
        'cinema_id',
        'type_room_id',
        'type_seat_id',
        'day_type',
        'time_slot',
        'price',
        'valid_from',
        'valid_to',
    ];

    // Relationships (nếu có các model tương ứng)
    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function typeRoom()
    {
        return $this->belongsTo(TypeRoom::class, 'type_room_id');
    }

    public function typeseat()
    {
        return $this->belongsTo(TypeSeat::class,'type_seat_id');
    }
}
