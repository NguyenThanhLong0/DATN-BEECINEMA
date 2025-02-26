<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Point_history extends Model
{
    use HasFactory;
    protected $table = 'point_histories';

    protected $fillable = [
        'membership_id',
        'points',
        'type',
    ];

    /**
     * Mối quan hệ với bảng Membership.
     * Một lịch sử điểm thuộc về một membership.
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
