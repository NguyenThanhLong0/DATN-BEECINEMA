<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomVerifyEmail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }
    protected function verificationUrl($notifiable)
    {
        return url(config('app.url') . 'verify/email/' . $notifiable->getKey() . '/' . sha1($notifiable->getEmailForVerification()));
    }
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Xác minh địa chỉ email')
            ->line('Vui lòng xác minh địa chỉ email của bạn bằng cách nhấp vào liên kết dưới đây:')
            ->action('Xác minh email', $this->verificationUrl($notifiable))
            ->line('Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!');
    
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
