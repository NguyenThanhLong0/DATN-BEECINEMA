<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovieVersion extends Model
{
    use HasFactory;
    protected $table = 'movie_versions';
    protected $fillable = [
        'movie_id',
        'name'
    ];
    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }
    public function showtime()
    {
        return $this->hasOne(Showtime::class);
    }
}
