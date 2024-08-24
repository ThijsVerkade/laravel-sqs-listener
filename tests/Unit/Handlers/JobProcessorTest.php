<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use ThijsVerkade\LaravelSqsListener\Exceptions\JobProcessingException;
use ThijsVerkade\LaravelSqsListener\HandlerInterface;
use ThijsVerkade\LaravelSqsListener\Handlers\JobProcessor;
use ThijsVerkade\LaravelSqsListener\Handlers\MessageHandlerResolver;
use ThijsVerkade\LaravelSqsListener\Services\SqsService;
use Illuminate\Queue\Jobs\SqsJob;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JobProcessor::class)]
final class JobProcessorTest extends TestCase
{
    private SqsService $sqsService;

    private MessageHandlerResolver $resolver;

    private JobProcessor $jobProcessor;

    private SqsJob $job;

    public function testProcessThrowsExceptionForEmptyPayload(): void
    {
        $this->expectExceptionObject(
            new JobProcessingException(
                'Message payload is not available',
                ['jobId' => 'test-job-id', 'messageId' => 'test-message-id', 'topicArn' => 'test-topic-arn']
            )
        );
        $this->expectException(JobProcessingException::class);
        $this->expectExceptionMessage('Message payload is not available');

        /** @var SqsJob|Mockery\MockInterface $job */
        $job = $this->job;
        $job->allows('payload')->andReturn([]);

        $this->jobProcessor->process($this->job);
    }

    public function testProcessThrowsExceptionForMissingHandler(): void
    {
        $this->expectNotToPerformAssertions();

        /** @var SqsJob|Mockery\MockInterface $job */
        $job = $this->job;
        $job->allows('payload')->andReturn(['TopicArn' => 'test-topic-arn']);

        /** @var MessageHandlerResolver|Mockery\MockInterface $resolver */
        $resolver = $this->resolver;
        $resolver->expects('resolve')->with('test-topic-arn')->andReturn(null);

        /** @var SqsService|Mockery\MockInterface $sqsService */
        $sqsService = $this->sqsService;
        $sqsService->expects('addProcessedMessage')->with([
            'Id' => 'test-message-id',
            'ReceiptHandle' => 'test-receipt-handle'
        ]);

        $this->jobProcessor->process($job);
    }

    public function testProcessExecutesValidHandler(): void
    {
        $this->expectNotToPerformAssertions();

        $payload = [
            'TopicArn' => 'test-topic-arn',
            'MessageId' => 'test-message-id'
        ];
        $handler = Mockery::mock(HandlerInterface::class);
        /** @var SqsJob|Mockery\MockInterface $job */
        $job = $this->job;
        $job->allows('payload')->andReturn($payload);
        /** @var MessageHandlerResolver|Mockery\MockInterface $resolver */
        $resolver = $this->resolver;
        $resolver->expects('resolve')->with('test-topic-arn')->andReturn($handler);

        $handler->expects('__invoke')->with($job);

        /** @var SqsService|Mockery\MockInterface $sqsService */
        $sqsService = $this->sqsService;
        $sqsService->expects('addProcessedMessage')->with([
            'Id' => 'test-message-id',
            'ReceiptHandle' => 'test-receipt-handle'
        ]);

        $this->jobProcessor->process($job);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->sqsService = Mockery::mock(SqsService::class);
        $this->resolver = Mockery::mock(MessageHandlerResolver::class);
        $this->jobProcessor = new JobProcessor($this->sqsService, $this->resolver);

        $this->job = Mockery::mock(SqsJob::class);
        $this->job->allows('getSqsJob')->andReturn([
            'MessageId' => 'test-message-id',
            'ReceiptHandle' => 'test-receipt-handle'
        ]);
        $this->job->allows('getJobId')->andReturn('test-job-id');
    }
}
