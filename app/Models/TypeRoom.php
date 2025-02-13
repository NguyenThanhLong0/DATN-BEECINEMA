<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'surcharge'
    ];

    public function room()
    {
        return $this->hasMany(Room::class);
    }
}
