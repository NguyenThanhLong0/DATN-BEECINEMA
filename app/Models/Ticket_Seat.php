<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket_Seat extends Model
{
    use HasFactory;
    protected $table = 'ticket_seats';

    protected $fillable = [
        'ticket_id',
        'seat_id',
        'price'
    ];

    /**
     * Mối quan hệ với bảng Ticket.
     * Một ticket_seat thuộc về một ticket.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Mối quan hệ với bảng Seat.
     * Một ticket_seat thuộc về một seat.
     */
    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }
}
