<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Aws\Result;
use Aws\Sqs\SqsClient;
use ThijsVerkade\LaravelSqsListener\Commands\SqsProcessorCommand;
use ThijsVerkade\LaravelSqsListener\Exceptions\JobProcessingException;
use ThijsVerkade\LaravelSqsListener\Handlers\JobProcessor;
use ThijsVerkade\LaravelSqsListener\Services\SqsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\ExampleHandler;
use Tests\ExampleWithoutInterfaceHandler;
use Tests\TestCase;

#[CoversClass(SqsProcessorCommand::class)]
final class SqsProcessorCommandTest extends TestCase
{
    public function testReturnsSuccessWithProcessableSqsMessageWithoutHandler(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $sqsClient = Mockery::mock(SqsClient::class);
        $jobProcessor = $this->createMock(JobProcessor::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('sqs')
            ->willReturn($sqsQueue);

        $sqsQueue->expects($this->once())
            ->method('getSqs')
            ->willReturn($sqsClient);

        $sqsQueue->expects($this->once())
            ->method('getContainer')
            ->willReturn(new Container());

        $message = ['key' => 'value'];
        $topicArn = sprintf('arn:aws:sns:eu-central-1:000000000000:%s', $this->faker->word);

        $messageId = $this->faker->uuid();
        $receiptHandle = $this->faker->uuid();

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
                'QueueUrl' => $sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        $jobProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(SqsJob::class))
            ->willThrowException(
                new JobProcessingException('No handler found', [
                    'jobId' => $messageId,
                    'messageId' => $messageId
                ])
            );

        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        $sqsService = $this->getSqsServiceMock($queueManager, $sqsQueue, $sqsClient);
        $command = new SqsProcessorCommand($sqsService, $jobProcessor);
        $command->setOutput($application);

        $result = $command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            'No handler found',
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
                'Timestamp' => $this->faker->dateTime()->format('Y-m-d\TH:i:s\Z'),
                'UnsubscribeURL' => 'https://sns.eu-central-1.amazonaws.com/?Action=Unsubscribe',
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function getSqsServiceMock(QueueManager $queueManager, SqsQueue $sqsQueue, SqsClient $sqsClient): SqsService
    {
        $sqsService = $this->getMockBuilder(SqsService::class)
            ->setConstructorArgs([$queueManager])
            ->onlyMethods(['receiveMessages', 'deleteProcessedMessages', 'addProcessedMessage'])
            ->getMock();


        $sqsService
            ->method('receiveMessages')
            ->willReturnCallback(static function () use ($sqsQueue, $sqsClient) {
                /** @var array{
                 *     Messages: array<int, array<int, string>>|null
                 * } $response
                 */
                $response = $sqsClient->receiveMessage([
                    'QueueUrl' => $sqsQueue->getQueue(null),
                    'AttributeNames' => ['ApproximateReceiveCount'],
                    'MaxNumberOfMessages' => 10,
                ]);
                $messages = $response['Messages'] ?? [];
                return array_map(static fn($message) => new SqsJob(
                    $sqsQueue->getContainer(),
                    $sqsClient,
                    $message,
                    $sqsQueue->getConnectionName(),
                    $sqsQueue->getQueue(null)
                ), $messages);
            });

        return $sqsService;
    }

    public function testReturnsSuccessWithProcessableSqsMessageWithHandler(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $sqsClient = Mockery::mock(SqsClient::class);
        $jobProcessor = $this->createMock(JobProcessor::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('sqs')
            ->willReturn($sqsQueue);

        $sqsQueue->expects($this->once())
            ->method('getSqs')
            ->willReturn($sqsClient);

        $sqsQueue->expects($this->once())
            ->method('getContainer')
            ->willReturn(new Container());

        $message = ['key' => 'value'];
        $topicArn = sprintf('arn:aws:sns:eu-central-1:000000000000:%s', $this->faker->word);

        $messageId = $this->faker->uuid();
        $receiptHandle = $this->faker->uuid();

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
                'QueueUrl' => $sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => ExampleHandler::class]]);

        $jobProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(SqsJob::class));

        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        $sqsService = $this->getSqsServiceMock($queueManager, $sqsQueue, $sqsClient);
        $command = new SqsProcessorCommand($sqsService, $jobProcessor);
        $command->setOutput($application);

        $result = $command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    public function testReturnsSuccessWithProcessableSqsMessageWithHandlerThatDoesNotImplementHandlerInterface(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $sqsClient = Mockery::mock(SqsClient::class);
        $jobProcessor = $this->createMock(JobProcessor::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('sqs')
            ->willReturn($sqsQueue);

        $sqsQueue->expects($this->once())
            ->method('getSqs')
            ->willReturn($sqsClient);

        $sqsQueue->expects($this->once())
            ->method('getContainer')
            ->willReturn(new Container());

        $message = ['key' => 'value'];
        $topicArn = sprintf('arn:aws:sns:eu-central-1:000000000000:%s', $this->faker->word);

        $messageId = $this->faker->uuid();
        $receiptHandle = $this->faker->uuid();

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
                'QueueUrl' => $sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => ExampleWithoutInterfaceHandler::class]]);

        $jobProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(SqsJob::class))
            ->willThrowException(
                new JobProcessingException(
                    'Handler is not instance of HandlerInterface',
                    [
                        'jobId' => $messageId,
                        'messageId' => $messageId,
                        'topicArn' => $topicArn,
                        'handlerClass' => ExampleWithoutInterfaceHandler::class
                    ]
                )
            );

        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        $sqsService = $this->getSqsServiceMock($queueManager, $sqsQueue, $sqsClient);
        $command = new SqsProcessorCommand($sqsService, $jobProcessor);
        $command->setOutput($application);

        $result = $command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            'Handler is not instance of HandlerInterface',
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    public function testReturnsSuccessWithProcessableSqsMessageWithHandlerThatDoesNotExist(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $sqsClient = Mockery::mock(SqsClient::class);
        $jobProcessor = $this->createMock(JobProcessor::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('sqs')
            ->willReturn($sqsQueue);

        $sqsQueue->expects($this->once())
            ->method('getSqs')
            ->willReturn($sqsClient);

        $sqsQueue->expects($this->once())
            ->method('getContainer')
            ->willReturn(new Container());

        $message = ['key' => 'value'];
        $topicArn = sprintf('arn:aws:sns:eu-central-1:000000000000:%s', $this->faker->word);

        $messageId = $this->faker->uuid();
        $receiptHandle = $this->faker->uuid();

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
                'QueueUrl' => $sqsQueue->getQueue(null),
                'Entries' => [
                    [
                        'Id' => $messageId,
                        'ReceiptHandle' => $receiptHandle
                    ]
                ]
            ])
            ->andReturn(new Result([]));

        config(['laravel-sqs-processor.sns-topics' => [$topicArn => 'does-not-exist']]);

        $jobProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(SqsJob::class))
            ->willThrowException(
                new JobProcessingException(
                    'Handler class not found',
                    [
                        'jobId' => $messageId,
                        'messageId' => $messageId,
                        'topicArn' => $topicArn,
                        'handlerClass' => 'does-not-exist'
                    ]
                )
            );

        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        $sqsService = $this->getSqsServiceMock($queueManager, $sqsQueue, $sqsClient);
        $command = new SqsProcessorCommand($sqsService, $jobProcessor);
        $command->setOutput($application);

        $result = $command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            'Handler class not found',
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    public function testReturnsSuccessWithUnprocessableSqsMessage(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $sqsClient = Mockery::mock(SqsClient::class);
        $jobProcessor = $this->createMock(JobProcessor::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('sqs')
            ->willReturn($sqsQueue);

        $sqsQueue->expects($this->once())
            ->method('getSqs')
            ->willReturn($sqsClient);

        $sqsQueue->expects($this->once())
            ->method('getContainer')
            ->willReturn(new Container());

        $sqsClient->allows('receiveMessage')
            ->andReturn(
                [
                    'Messages' => [
                        [
                            'MessageId' => $messageId = $this->faker->uuid(),
                            'ReceiptHandle' => $this->faker->uuid(),
                            'Body' => ''
                        ]
                    ]
                ],
                ['Messages' => []]
            );

        $jobProcessor->expects($this->once())
            ->method('process')
            ->with($this->isInstanceOf(SqsJob::class))
            ->willThrowException(
                new JobProcessingException(
                    'Message payload is not available',
                    ['jobId' => $messageId, 'messageId' => $messageId]
                )
            );

        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        $sqsService = $this->getSqsServiceMock($queueManager, $sqsQueue, $sqsClient);
        $command = new SqsProcessorCommand($sqsService, $jobProcessor);
        $command->setOutput($application);

        $result = $command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            'Message payload is not available',
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }

    public function testReturnsSuccessWithNoReceivedSqsMessages(): void
    {
        Carbon::setTestNow(Carbon::parse($testDate = $this->faker->dateTime()));

        $queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $sqsClient = Mockery::mock(SqsClient::class);
        $jobProcessor = $this->createMock(JobProcessor::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('sqs')
            ->willReturn($sqsQueue);

        $sqsQueue->expects($this->once())
            ->method('getSqs')
            ->willReturn($sqsClient);

        $sqsClient->allows('receiveMessage')
            ->andReturn(
                ['Messages' => []]
            );

        $input = new ArgvInput();
        $output = new BufferedOutput();
        $application = new OutputStyle($input, $output);

        $sqsService = $this->getSqsServiceMock($queueManager, $sqsQueue, $sqsClient);
        $command = new SqsProcessorCommand($sqsService, $jobProcessor);
        $command->setOutput($application);

        $result = $command->handle();

        $outputArray = explode("\n", $output->fetch());

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([
            sprintf('Command Start [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
            sprintf('Command End [queue:sqs-processor] %s', $testDate->format('Y-m-d H:i:s')),
        ], array_filter($outputArray));
    }
}
