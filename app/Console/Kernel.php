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
        // Process subscription renewals daily at 2 AM
        $schedule->command('subscriptions:process-renewals')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // Process cancellation requests daily at 3 AM
        $schedule->command('cancellations:process-requests')
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->runInBackground();
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