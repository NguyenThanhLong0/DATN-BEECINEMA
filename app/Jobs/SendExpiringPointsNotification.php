<?php

namespace App\Jobs;

use App\Mail\PointsExpiring;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendExpiringPointsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $points;
    protected $expiredAt;
    /**
     * Create a new job instance.
     */
    public function __construct(User $user, $points, $expiredAt)
    {
        $this->user = $user;
        $this->points = $points;
        $this->expiredAt = $expiredAt;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new PointsExpiring($this->points, $this->expiredAt));
    }
}
