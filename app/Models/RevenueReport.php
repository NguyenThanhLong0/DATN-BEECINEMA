<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RevenueReport extends Model
{
    use HasFactory;
    protected $fillable = [
        'date',
        'total_movie_revenue',
        'total_combo_revenue',
        'total_revenue',
    ];
}
