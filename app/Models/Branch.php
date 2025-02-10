<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;


class Branch extends Model
{
    use HasFactory, Sluggable;

    protected $fillable = ['name', 'slug', 'is_active'];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function cinemas()
    {
        return $this->hasMany(Cinema::class);
    }
}
