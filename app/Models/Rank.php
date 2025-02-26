<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    use HasFactory;
    protected $table = 'ranks';

    protected $fillable = [
        'name',
        'total_spent',
        'ticket_percentage',
        'combo_percentage',
        'is_default',
    ];

    /**
     * Mối quan hệ với Membership.
     * Một rank có thể thuộc về nhiều membership.
     */
    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }
}
