<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

final class SqsListenerProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SqsListenerCommand::class,
            ]);
        }

        $this->app->booted(function () {
            $this->schedule($this->app->make(Schedule::class));
        });
    }

    protected function schedule(Schedule $schedule): void
    {
        if (Config::get('queue.sqs.schedule_listener')) {
            $schedule->command(SqsListenerCommand::class)
                ->everyMinute()
                ->withoutOverlapping()
                ->environments('prod', 'stg', 'dev')
                ->timezone(new DateTimeZone('Europe/Amsterdam'))
                ->runInBackground()
                ->sendOutputTo('/proc/1/fd/1');
        }
    }
}