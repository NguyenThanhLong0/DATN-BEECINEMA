<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('revenue:calculate-daily')->dailyAt('23:59'); // Chạy lúc 23:59 mỗi ngày
        $schedule->command('tickets:update-status')->everyTenMinutes();
        $schedule->command('points:expire-points')->everyMinute();
        $schedule->command('points:notify-expiring')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
