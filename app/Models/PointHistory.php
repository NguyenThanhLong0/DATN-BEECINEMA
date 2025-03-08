<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'membership_id',
        'points',
        'type',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
        'processed' => 'boolean'
    ];

    const POINTS_ACCUMULATED = 'Tích điểm'; // Tích điểm
    const POINTS_SPENT = 'Tiêu điểm';             // Tiêu điểm
    const POINTS_EXPIRY = 'Hết hạn';            // Hết hạn

    const POINT_EXPIRY_DURATION = 6; // Đơn vị là tháng tính từ ngày tích điểm



    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
