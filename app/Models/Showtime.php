<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Showtime extends Model
{
    use HasFactory;

    protected $fillable = [
        'cinema_id',
        'room_id',
        'slug',
        'format',
        'movie_version_id',
        'movie_id',
        'date',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function movieVersion()
    {
        return $this->belongsTo(MovieVersion::class);
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function seats()
    {
        return $this->belongsToMany(Seat::class, 'seat_showtimes', 'showtime_id', 'seat_id')
            ->withPivot('status', 'price', 'user_id')
            ->withTimestamps();
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    // public function ticketSeats()
    // {
    //     return $this->hasMany(TicketSeat::class);
    // }


    // public static function generateCustomRandomString()
    // {
    //     $part1 = bin2hex(random_bytes(18));
    //     $part2 = bin2hex(random_bytes(18));

    //     return substr($part1, 0, 8) . '-' . substr($part1, 8, 4) . '-' . substr($part1, 12, 4) . '-' . substr($part1, 16, 4) . '-' . substr($part1, 20)
    //         . '-' . substr($part2, 0, 8) . '-' . substr($part2, 8, 4) . '-' . substr($part2, 12, 4) . '-' . substr($part2, 16, 4) . '-' . substr($part2, 20);
    // }

    // Thời gian dọn phòng: 15p
    const CLEANINGTIME = '15';


    public static function generateCustomRandomString()
    {
        $part1 = bin2hex(random_bytes(18)); // Tạo 36 ký tự hex (18 byte)
        $part2 = bin2hex(random_bytes(18)); // Tạo 36 ký tự hex (18 byte)

        // Chia nhỏ chuỗi theo định dạng yêu cầu
        $formattedPart1 = substr($part1, 0, 8) . '-' . substr($part1, 8, 4) . '-' . substr($part1, 12, 4) . '-' . substr($part1, 16, 4) . '-' . substr($part1, 20);
        $formattedPart2 = substr($part2, 0, 8) . '-' . substr($part2, 8, 4) . '-' . substr($part2, 12, 4) . '-' . substr($part2, 16, 4) . '-' . substr($part2, 20);

        return $formattedPart1 . '-' . $formattedPart2;
    }
}
