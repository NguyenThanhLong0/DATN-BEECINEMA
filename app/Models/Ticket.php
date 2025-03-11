<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{

    use HasFactory;

    protected $fillable = [

        'user_id',
        'cinema_id',
        'room_id',
        'movie_id',
        'showtime_id',
        'voucher_code',
        'voucher_discount',
        'payment_name',
        'code',
        'total_price',
        'status',
        'staff',
        'expiry',
        'point',
        'point_discount',
        'rank_at_booking'

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
    //quan hệ với ticket_seat
    public function seats()
    {
        return $this->hasMany(Ticket_Seat::class, 'ticket_id');
    }
    //mới thêm tối qua
    public function ticketSeats()
    {
        return $this->hasMany(Ticket_Seat::class, 'ticket_id');
    }
    //  Quan hệ với bảng `ticket_combos`
    public function combos()
    {
        return $this->hasMany(Ticket_Combo::class, 'ticket_id');
    }

    // Quan hệ với User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }


    // tính tổng giá trị sau khi trừ điểm giảm giá
    public function getFinalPriceAttribute()
    {
        return max(0, $this->total_price - $this->point_discount);
    }
    // lấy ra rank hiện tại theo user
    public function getCurrentRankAttribute()
    {
        return $this->user ? $this->user->current_rank : 'Member';
    }
    //  Quan hệ với bảng `vouchers`
    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_code', 'code');
    }

    public static function generateTicketCode()
    {
        // Lấy thời gian hiện tại theo định dạng yyyymmddHis (NămThángNgàyGiờPhútGiây)
        return now()->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis');
    }
    public function ticketCombos()
    {
        return $this->hasMany(Ticket_Combo::class, 'ticket_id', 'id');
    }

}
?>
