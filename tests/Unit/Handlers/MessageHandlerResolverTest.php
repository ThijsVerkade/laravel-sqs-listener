<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use ThijsVerkade\LaravelSqsListener\HandlerInterface;
use ThijsVerkade\LaravelSqsListener\Handlers\MessageHandlerResolver;
use Illuminate\Support\Facades\App;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\ExampleHandler;
use Tests\ExampleWithoutInterfaceHandler;
use Tests\TestCase;

#[CoversClass(MessageHandlerResolver::class)]
final class MessageHandlerResolverTest extends TestCase
{
    public function testResolveReturnsHandlerInstance(): void
    {
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:test-topic';
        $handlerClass = ExampleHandler::class;

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => $handlerClass]]);

        App::shouldReceive('make')
            ->once()
            ->with($handlerClass)
            ->andReturn(Mockery::mock(HandlerInterface::class));

        $resolver = new MessageHandlerResolver();
        $handler = $resolver->resolve($topicArn);

        $this->assertInstanceOf(HandlerInterface::class, $handler);
    }

    public function testResolveReturnsNullForNonExistingHandlerClass(): void
    {
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:non-existing-handler';

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => 'NonExistingHandler']]);

        $resolver = new MessageHandlerResolver();
        $handler = $resolver->resolve($topicArn);

        $this->assertNull($handler);
    }

    public function testResolveReturnsNullForHandlerClassWithoutInterface(): void
    {
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:test-topic-without-interface';
        $handlerClass = ExampleWithoutInterfaceHandler::class;

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => $handlerClass]]);

        $resolver = new MessageHandlerResolver();
        $handler = $resolver->resolve($topicArn);

        $this->assertNull($handler);
    }

    public function testResolveReturnsNullForNullTopicArn(): void
    {
        $resolver = new MessageHandlerResolver();
        $handler = $resolver->resolve(null);

        $this->assertNull($handler);
    }

    public function testResolveReturnsNullForNonConfiguredTopicArn(): void
    {
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:non-configured-topic';

        config(['laravel-sqs-processor.sns-topics' => []]);

        $resolver = new MessageHandlerResolver();
        $handler = $resolver->resolve($topicArn);

        $this->assertNull($handler);
    }
}
