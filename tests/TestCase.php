<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;

abstract class TestCase extends BaseTestCase
{
    use WithFaker;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
    }
}
