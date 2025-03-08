<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'rank_id',
        'code',
        'points',
        'total_spent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rank()
    {
        return $this->belongsTo(Rank::class);
    }
    public function pointHistories()
    {
        return $this->hasMany(PointHistory::class);
    }
}
