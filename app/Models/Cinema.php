<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;

class Cinema extends Model
{
    use HasFactory, Sluggable;

    protected $fillable = ['branch_id', 'name', 'slug', 'address', 'surcharge', 'description', 'is_active'];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    /**
         * Accessor: Định dạng surcharge theo tiền Việt Nam (VND) khi lấy ra
         */
        public function getSurchargeAttribute($value)
        {
            return number_format($value, 0, ',', '.');
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

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }
    
    public function users()
    {
        return $this->hasMany(User::class);
    }
    
        public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
