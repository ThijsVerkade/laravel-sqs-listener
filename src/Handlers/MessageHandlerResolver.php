<?php

declare(strict_types=1);

namespace ThijsVerkade\LaravelSqsListener\Handlers;

use ThijsVerkade\LaravelSqsListener\HandlerInterface;
use Illuminate\Support\Facades\App;
use ReflectionClass;

class MessageHandlerResolver
{
    public function resolve(?string $topicArn): ?HandlerInterface
    {
        /** @var array<string, string> $snsTopics */
        $snsTopics = config('laravel-sqs-processor.sns-topics', []);

        if (!$topicArn || !isset($snsTopics[$topicArn])) {
            return null;
        }

        $handlerClass = $snsTopics[$topicArn];

        if (!class_exists($handlerClass)) {
            return null;
        }

        if (!in_array(HandlerInterface::class, class_implements($handlerClass), true)) {
            return null;
        }

        /** @var HandlerInterface */
        return App::make($handlerClass);
    }
}
