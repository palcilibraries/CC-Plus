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
        // $schedule->command('ccplus:sushiqw 1 Conso1_QW1')->runInBackground()->everyTenMinutes()->withoutOverlapping()
        //                                       ->appendOutputTo('/var/log/ccplus/ingests.log');
        // $schedule->command('ccplus:sushiqw 1 Conso1_QW2')->runInBackground()->everyTenMinutes()->withoutOverlapping()
        //                                       ->appendOutputTo('/var/log/ccplus/ingests.log');
        // $schedule->command('ccplus:sushiqw 2 Conso2_QW1')->runInBackground()->everyTenMinutes()->withoutOverlapping()
        //                                       ->appendOutputTo('/var/log/ccplus/ingests.log');
        // $schedule->command('ccplus:sushiqw 2 Conso2_QW2')->runInBackground()->everyTenMinutes()->withoutOverlapping()
        //                                       ->appendOutputTo('/var/log/ccplus/ingests.log');
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
