<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'title',
        'description',
        'start_date',
        'end_date',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'quantity',
        'is_active',
        'used_count',
        'per_user_limit',
    ];

    protected $casts=[
        'is_active'=>'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_vouchers')
            ->withPivot('usage_count')
            ->withTimestamps();
    }
    public function user_vouchers()
{
    return $this->hasMany(UserVoucher::class);
}

}
