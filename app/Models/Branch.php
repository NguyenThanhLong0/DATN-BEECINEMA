<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active'];

    public function cinemas()
    {
        return $this->hasMany(Cinema::class);
    }
}
