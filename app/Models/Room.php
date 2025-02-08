<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'cinema_id',
        'type_room_id',
        'seat_template_id',
        'name',
        'is_active',
        'is_publish'
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'is_publish' => 'boolean',
    ];

    const SCOPE_REGULAR = ['min' => 3, 'default' => 4, 'max' => 5];
    const SCOPE_DOUBLE = ['min' => 0, 'default' => 0, 'max' => 2];
    public function seatTemplate()
    {
        return $this->belongsTo(SeatTemplate::class);
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function typeRoom()
    {
        return $this->belongsTo(TypeRoom::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }
}
