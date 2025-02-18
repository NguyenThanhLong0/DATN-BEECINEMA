<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'description','img_thumbnail_url' ,'is_active'];

    protected $casts = [
        'img_thumbnail_url' => 'array', // Laravel sẽ tự động chuyển JSON thành mảng
        'is_active' => 'boolean'
    ];


}
