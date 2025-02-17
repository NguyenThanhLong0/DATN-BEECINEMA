<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;

class Cinema extends Model
{
    use HasFactory, sluggable;

    protected $fillable = ['branch_id', 'name', 'slug', 'address', 'surcharge', 'description', 'is_active'];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    // Quan hệ với chi nhánh (branch)
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Quan hệ với phòng chiếu (rooms)
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
