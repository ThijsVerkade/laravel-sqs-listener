<?php

declare(strict_types=1);

namespace Tests;

use ThijsVerkade\LaravelSqsListener\HandlerInterface;
use Illuminate\Queue\Jobs\Job;
use Override;

final class ExampleHandler implements HandlerInterface
{
    #[Override]
    public function __invoke(Job $job): void
    {
    }
}
