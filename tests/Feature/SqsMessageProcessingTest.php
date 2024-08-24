<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aws\Result;
use Aws\Sqs\SqsClient;
use ThijsVerkade\LaravelSqsListener\Commands\SqsProcessorCommand;
use ThijsVerkade\LaravelSqsListener\Handlers\JobProcessor;
use ThijsVerkade\LaravelSqsListener\Handlers\MessageHandlerResolver;
use ThijsVerkade\LaravelSqsListener\Services\SqsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use Mockery;
use Override;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\ExampleHandler;
use Tests\ExampleWithoutInterfaceHandler;
use Tests\TestCase;

final class SqsMessageProcessingTest extends TestCase
{
    private SqsQueue $sqsQueue;

    private SqsClient $sqsClient;

    private SqsProcessorCommand $command;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $queueManager = $this->createMock(QueueManager::class);
        $this->sqsQueue = $this->createMock(SqsQueue::class);
        $this->sqsClient = Mockery::mock(SqsClient::class);

        $queueManager->method('connection')->willReturn($this->sqsQueue);
        $this->sqsQueue->method('getSqs')->willReturn($this->sqsClient);
        $this->sqsQueue->method('getContainer')->willReturn(new Container());

        $sqsService = new SqsService($queueManager);
        $resolver = new MessageHandlerResolver();
        $jobProcessor = new JobProcessor($sqsService, $resolver);

        $this->command = new SqsProcessorCommand($sqsService, $jobProcessor);
    }

    public function testProcessableSqsMessageWithHandler(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $message = ['key' => 'value'];
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:test-topic';
        $messageId = 'test-message-id';
        $receiptHandle = 'test-receipt-handle';
        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('receiveMessage')
            ->andReturn(
                [
                    'Messages' => [
                        $this->getSqsPayload(
                            $messageId,
                            json_encode($message, JSON_THROW_ON_ERROR),
                            $receiptHandle,
                            $topicArn
                        )
                    ]
                ],
                ['Messages' => []]
            );

        $sqsClient->allows('deleteMessageBatch')
            ->with([
                'QueueUrl' => $this->sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => ExampleHandler::class]]);

        // Symfony Console setup
        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        // Command execution
        $this->command->setOutput($application);
        $result = $this->command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    public function testProcessableSqsMessageWithoutHandler(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $message = ['key' => 'value'];
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:test-topic';
        $messageId = 'test-message-id';
        $receiptHandle = 'test-receipt-handle';

        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('receiveMessage')
            ->andReturn(
                [
                    'Messages' => [
                        $this->getSqsPayload(
                            $messageId,
                            json_encode($message, JSON_THROW_ON_ERROR),
                            $receiptHandle,
                            $topicArn
                        )
                    ]
                ],
                ['Messages' => []]
            );
        $sqsClient->allows('deleteMessageBatch')
            ->with([
                'QueueUrl' => $this->sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        config(['laravel-sqs-processor.sns-topics' => []]);

        // Symfony Console setup
        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        // Command execution
        $this->command->setOutput($application);
        $result = $this->command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    public function testProcessableSqsMessageWithInvalidHandler(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $message = ['key' => 'value'];
        $topicArn = 'arn:aws:sns:eu-central-1:000000000000:test-topic';
        $messageId = 'test-message-id';
        $receiptHandle = 'test-receipt-handle';

        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('receiveMessage')
            ->andReturn(
                [
                    'Messages' => [
                        $this->getSqsPayload(
                            $messageId,
                            json_encode($message, JSON_THROW_ON_ERROR),
                            $receiptHandle,
                            $topicArn
                        )
                    ]
                ],
                ['Messages' => []]
            );

        $sqsClient->allows('deleteMessageBatch')
            ->with([
                'QueueUrl' => $this->sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => ExampleWithoutInterfaceHandler::class]]);

        // Symfony Console setup
        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        // Command execution
        $this->command->setOutput($application);
        $result = $this->command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    /**
     * @return array{
     *     MessageId: string,
     *     ReceiptHandle: string,
     *     Body: string
     * }
     */
    private function getSqsPayload(
        string $messageId,
        string $message,
        string $receiptHandle,
        string $topicArn,
    ): array {
        return [
            'MessageId' => $messageId,
            'ReceiptHandle' => $receiptHandle,
            'Body' => json_encode([
                'Type' => 'Notification',
                'MessageId' => $messageId,
                'SequenceNumber' => '10000000000000049000',
                'TopicArn' => $topicArn,
                'Message' => $message,
                'Timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
                'UnsubscribeURL' => 'https://sns.eu-central-1.amazonaws.com/?Action=Unsubscribe',
            ], JSON_THROW_ON_ERROR),
        ];
    }
}
