<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'total_spent',
        'ticket_percentage',
        'combo_percentage',
        'is_default'
    ];
    protected $casts = [
        'is_default' => 'boolean'
    ];
    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }
}
