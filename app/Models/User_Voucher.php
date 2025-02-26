<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_Voucher extends Model
{
    use HasFactory;
    protected $table = 'user_voucher';

    protected $fillable = [
        'user_id',
        'voucher_id',
        'usage_count'
    ];

    /**
     * Mối quan hệ với bảng User.
     * Một user_voucher thuộc về một user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mối quan hệ với bảng Voucher.
     * Một user_voucher thuộc về một voucher.
     */
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
