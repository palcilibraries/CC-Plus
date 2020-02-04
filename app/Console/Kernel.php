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
      Commands\SushiBatchCommand::class,
      Commands\C5TestCommand::class,
      Commands\SushiBatchCommand::class,
      Commands\SushiQNightly::class,
      Commands\SushiQWorker::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    /*
     * Example scheduler setup: A nightly process and 2 queue workers for each of 2 consortia
     */
        // $schedule->command('ccplus:nightly')->daily();

      /* Sushi Queue workers:
       * The *_QW2 workers have a 5-second startup delay to prevent the _QW1 and _QW2 processes
       * from trying to grab the same job when they start.
       * Syntax:
       *   ccplus:sushiqw  consortium-ID-or-Key [Process-Identifier] [startup-delay]
       */
        $schedule->command('ccplus:sushiqw 1 Conso1_QW1')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/ingests.log');
        $schedule->command('ccplus:sushiqw 2 Conso2_QW1')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/ingests.log');
        $schedule->command('ccplus:sushiqw 1 Conso1_QW2 5')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/ingests.log');
        $schedule->command('ccplus:sushiqw 2 Conso2_QW2 5')->runInBackground()->everyTenMinutes()->withoutOverlapping()
                                              ->appendOutputTo('/var/log/ccplus/ingests.log');
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
