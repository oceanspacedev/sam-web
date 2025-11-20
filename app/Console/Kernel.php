<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('data:archive')->monthly()->at('02:00');

        // ===================================
        // Outlet Lifecycle Maintenance
        // ===================================
        // Single command that runs all steps sequentially:
        // 1. Reactivate UNMAINTAIN with visits
        // 2. Cleanup UNPRODUCTIVE
        // 3. MAINTAIN → UNMAINTAIN (daily)
        // 4. UNMAINTAIN → Archive (Monday only)

        $schedule->command('outlets:maintain-lifecycle')
            ->daily()
            ->at('02:00')
            ->appendOutputTo(storage_path('logs/outlet-lifecycle.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
