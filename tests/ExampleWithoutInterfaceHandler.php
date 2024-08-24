<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Queue\Jobs\Job;

final class ExampleWithoutInterfaceHandler
{
    public function __invoke(Job $job): void
    {
    }
}
