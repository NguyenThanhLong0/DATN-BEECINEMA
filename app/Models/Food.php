<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;
    

    protected $fillable = [
        'name',
        'img_thumbnail',
        'price',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    const TYPES = [
        'Đồ Ăn',
        'Nước Uống',
        'Khác'
    ];
    // Quan hệ với Combo
    public function combos()
    {
        // Mỗi món ăn có thể thuộc nhiều combo, qua bảng pivot 'combo_food'
        return $this->belongsToMany(Combo::class, 'combo_food', 'food_id', 'combo_id')
                    ->withPivot('quantity');  // Lưu số lượng món ăn trong combo
    }
}
