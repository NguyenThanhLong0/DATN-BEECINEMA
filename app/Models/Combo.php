<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'img_thumbnail',
        'price',
        'discount_price',
        'description',
        'is_active'
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];
    // Quan hệ với bảng ComboFood, mỗi Combo có nhiều món ăn (Food) thông qua bảng pivot
    public function comboFood()
    {
        return $this->hasMany(ComboFood::class);
    }
    // Quan hệ với Food, nhiều món ăn có thể thuộc nhiều combo
    public function foods()
    {
        return $this->belongsToMany(Food::class, 'combo_food')->withPivot('quantity');
    }
}
