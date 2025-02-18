<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $email;
    public function __construct($email)
    {
        $this->email = $email;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $status = Password::sendResetLink(
                ['email' => $this->email]
            );

            if ($status !== Password::RESET_LINK_SENT) {
                Log::error("Unable to send password reset link to {$this->email}");
            }
        } catch (\Exception $e) {
            Log::error("Error while sending password reset email: " . $e->getMessage());
        }
    }
}
