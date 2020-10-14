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
      Commands\ConsortiumCommand::class,
      Commands\C5TestCommand::class,
      Commands\SushiBatchCommand::class,
      Commands\SushiQLoader::class,
      Commands\SushiQWorker::class,
      Commands\DataArchiveCommand::class,
      Commands\DataPurgeCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    /*----------------------------------------------------------------------------------
     * Example scheduler setup for a 2-consortia system. Each consortium uses a daily
     * queue-loading process (:sushiloader) and 2 queue worker processes that execute
     * every ten minutes (and then exit if there's nothing to do.)
     *
     * Note that the *_QW2 workers have a 5-second startup delay to prevent the _QW1 and
     * _QW2 processes from trying to grab the same job when they start.
     * Syntax:
     *   ccplus:sushiqw  consortium-ID-or-Key [Process-Identifier] [startup-delay]
     *----------------------------------------------------------------------------------
     */
      /*
       * Consortium #1
       */
        $schedule->command('ccplus:sushiloader 1')->daily();
        $schedule->command('ccplus:sushiqw 1 Conso1_QW1')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/harvests.log');
        $schedule->command('ccplus:sushiqw 1 Conso1_QW2 5')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/harvests.log');
      /*
       * Consortium #2
       */
        $schedule->command('ccplus:sushiloader 2')->daily();
        $schedule->command('ccplus:sushiqw 2 Conso2_QW1')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/harvests.log');
        $schedule->command('ccplus:sushiqw 2 Conso2_QW2 5')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/harvests.log');
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
