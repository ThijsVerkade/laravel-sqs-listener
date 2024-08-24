<?php

declare(strict_types=1);

namespace ThijsVerkade\LaravelSqsListener\Exceptions;

use Exception;

class JobProcessingException extends Exception
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
