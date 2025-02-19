<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;

class Movie extends Model
{
    use HasFactory, Sluggable;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'img_thumbnail',
        'description',
        'director',
        'cast',
        'rating',
        'duration',
        'release_date',
        'end_date',
        'trailer_url',
        'surcharge',
        'surcharge_desc',
        'is_active',
        'is_hot',
        'is_publish',
        'is_special'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_hot' => 'boolean',
        'is_publish' => 'boolean',
        'is_special' => 'boolean',
    ];

    const VERSIONS = [
        ['id' => 1, 'name' => 'Phụ Đề'],
        ['id' => 2, 'name' => 'Lồng Tiếng'],
        ['id' => 3, 'name' => 'Thuyết Minh'],
    ];

    const RATINGS = [
        ['id' => 1, 'name' => 'P', 'description' => 'Phim được phổ biến rộng rãi đến mọi đối tượng.'],
        ['id' => 2, 'name' => 'T13', 'description' => 'Phim được phổ biến đến khán giả từ đủ 13 tuổi trở lên.'],
        ['id' => 3, 'name' => 'T16', 'description' => 'Phim được phổ biến đến khán giả từ đủ 16 tuổi trở lên.'],
        ['id' => 4, 'name' => 'T18', 'description' => 'Phim được phổ biến đến khán giả từ đủ 18 tuổi trở lên.'],
        ['id' => 5, 'name' => 'K', 'description' => 'Phim được phổ biến đến khán giả dưới 13 tuổi và có người bảo hộ đi cùng.'],
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public static function getRatingByName($name)
    {
        return collect(self::RATINGS)->firstWhere('name', $name);
    }

    public function movieVersions()
    {
        return $this->hasMany(MovieVersion::class);
    }

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }

    public static function getImageTagRating($rating)
    {
        switch ($rating) {
            case 'P':
                return asset('images/tags/tag-p.png');
            case 'K':
                return asset('images/tags/tag-k.png');
            case 'T13':
                return asset('images/tags/tag-t13.png');
            case 'T16':
                return asset('images/tags/tag-t16.png');
            case 'T18':
                return asset('images/tags/tag-t18.png');
            default:
                return null;
        }
    }
}
