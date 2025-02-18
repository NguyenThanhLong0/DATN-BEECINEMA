<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model {

    use HasFactory;

    protected $fillable = [
        'user_id', 'cinema_id', 'room_id', 'movie_id', 'showtime_id',
        'voucher_code', 'voucher_discount', 'payment_name', 'code',
        'total_price', 'status', 'staff', 'expiry'
    ];
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            // Nếu code chưa có, tạo code ngẫu nhiên
            if (empty($ticket->code)) {
                $ticket->code = 'TICKET-' . Str::random(8);
            }
        });
    }

    // Quan hệ với User
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function cinema() {
        return $this->belongsTo(Cinema::class);
    }

    public function room() {
        return $this->belongsTo(Room::class);
    }

    public function movie() {
        return $this->belongsTo(Movie::class);
    }

    public function showtime() {
        return $this->belongsTo(Showtime::class);
    }
}
?>

