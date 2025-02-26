<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;
    protected $table = 'memberships';

    protected $fillable = [
        'user_id',
        'rank_id',
        'code',
        'points',
        'total_spent',
    ];

    /**
     * Mối quan hệ với bảng User.
     * Một membership thuộc về một user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mối quan hệ với bảng Rank.
     * Một membership thuộc về một rank.
     */
    public function rank()
    {
        return $this->belongsTo(Rank::class);
    }
}
