<?php

namespace App\Providers;

use App\Events\VerifyEmail;
use App\Listeners\VerifyEmailNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\UserRegistered::class => [
            \App\Listeners\SendVerificationEmail::class,
        ],
        // toi mới thêm ở đây
        \App\Events\SeatHold::class => [],
        \App\Events\SeatRelease::class => [],
        \App\Events\SeatStatusChange::class => [],
        \App\Events\SeatSold::class => [],
        \App\Events\ChangeSeat::class => [],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
