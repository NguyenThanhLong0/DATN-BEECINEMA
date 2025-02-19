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
        'start_date_time',
        'end_date_time',
        'discount',
        'quantity',
        'is_active',
        'limit',
        'type',
    ];

    protected $casts=[
        'type'=>'boolean',
        'is_active'=>'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_vouchers')
            ->withPivot('usage_count')
            ->withTimestamps();
    }

}
