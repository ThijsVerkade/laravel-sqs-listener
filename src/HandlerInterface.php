<?php

declare(strict_types=1);

use Illuminate\Queue\Jobs\Job;

interface HandlerInterface
{
    public function __invoke(Job $job): void;
}