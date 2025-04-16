<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\URL;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */public function boot(): void
{
    $this->registerPolicies();

    // Cập nhật URL xác minh email
    VerifyEmail::createUrlUsing(function ($notifiable) {
        // URL backend để xác minh email
        $backendUrl = config('app.url', 'http://datn-beecinema.test'); 
        // URL frontend để chuyển hướng sau khi xác minh email thành công
        $frontendUrl = config('app.frontend_url');
        
        // Trả về URL xác minh email từ backend, kèm với redirect_to
        return "{$backendUrl}/verify/email/{$notifiable->getKey()}/" . sha1($notifiable->getEmailForVerification()) . "?redirect_to={$frontendUrl}/email-verified";
    });
}

}
