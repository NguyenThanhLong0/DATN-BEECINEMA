<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail, ShouldQueue
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    const TYPE_ADMIN = 'admin';
    const TYPE_MANAGER = 'manager';
    const TYPE_MEMBER = 'member';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'email_verified_at',
        'phone',
        'gender',
        'birthday',
        'avatar',
        'address',
        'cinema_id',
        'delete_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function IsAdmin()
    {
        return $this->role == self::TYPE_ADMIN;
    }
    public function IsManager()
    {
        return $this->role == self::TYPE_MANAGER;
    }
    public function IsMember()
    {
        return $this->role == self::TYPE_MEMBER;
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }
    public function userVouchers()
    {
        return $this->hasMany(UserVoucher::class, 'user_id');
    }
    public function membership()
    {
        return $this->hasOne(Membership::class);
    }
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail());
    }
    public function getCurrentRankAttribute()
    {
        // Lấy rank cao nhất mà user có thể đạt được dựa trên total_spent
        $membership = Membership::where('total_spent', '<=', $this->total_spent)
            ->orderBy('total_spent', 'desc')
            ->first();

        // Nếu không có rank nào phù hợp, trả về rank thấp nhất
        if (!$membership) {
            $membership = Membership::orderBy('total_spent', 'asc')->first();
        }

        return $membership ? $membership->name : 'Member';
    }
}
