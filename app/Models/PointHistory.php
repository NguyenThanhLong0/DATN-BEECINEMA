<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'membership_id',
        'ticket_id',
        'points',
        'remaining_points',
        'type',
        'expired_at',
    ];

    protected $casts = [
        'processed' => 'boolean'
    ];

    const POINTS_ACCUMULATED = 'Tích điểm'; // Tích điểm
    const POINTS_SPENT = 'Trừ điểm';             // Tiêu điểm
    const POINTS_EXPIRY = 'Hết hạn';            // Hết hạn

    const POINT_EXPIRY_DURATION = 1; // Đơn vị là tháng tính từ ngày tích điểm



    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
