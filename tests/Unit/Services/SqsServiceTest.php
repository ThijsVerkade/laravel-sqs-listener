<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Aws\Result;
use Aws\Sqs\SqsClient;
use ThijsVerkade\LaravelSqsListener\Services\SqsService;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SqsService::class)]
final class SqsServiceTest extends TestCase
{
    private QueueManager $queueManager;

    private SqsClient $sqsClient;

    public function testReceiveMessagesReturnsSqsJobs(): void
    {
        $messageId = 'test-message-id';
        $receiptHandle = 'test-receipt-handle';
        $body = json_encode(['key' => 'value'], JSON_THROW_ON_ERROR);

        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('receiveMessage')
            ->andReturn([
                'Messages' => [
                    [
                        'MessageId' => $messageId,
                        'ReceiptHandle' => $receiptHandle,
                        'Body' => $body,
                    ]
                ]
            ]);

        $sqsService = new SqsService($this->queueManager);

        $jobs = $sqsService->receiveMessages();

        $this->assertCount(1, $jobs);
        $this->assertInstanceOf(SqsJob::class, $jobs[0]);
        $this->assertSame($messageId, $jobs[0]->getJobId());
    }

    public function testReceiveMessagesReturnsEmptyArrayWhenNoMessages(): void
    {
        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('receiveMessage')->andReturn(['Messages' => []]);

        $sqsService = new SqsService($this->queueManager);

        $jobs = $sqsService->receiveMessages();

        $this->assertEmpty($jobs);
    }

    public function testDeleteProcessedMessagesLogsInfoAndClearsProcessedMessages(): void
    {
        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('deleteMessageBatch')->andReturn(new Result([]));

        $sqsService = new SqsService($this->queueManager);

        $message = [
            'Id' => 'test-message-id',
            'ReceiptHandle' => 'test-receipt-handle'
        ];
        $sqsService->addProcessedMessage($message);

        $sqsService->deleteProcessedMessages();

        $this->assertEmpty($this->getPrivateProperty($sqsService, 'processedMessages'));
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflector = new ReflectionClass($object);
        return $reflector->getProperty($propertyName)->getValue($object);
    }

    public function testDeleteProcessedMessagesDoesNothingWhenNoProcessedMessages(): void
    {
        /** @var SqsClient|Mockery\MockInterface $sqsClient */
        $sqsClient = $this->sqsClient;
        $sqsClient->allows('deleteMessageBatch')->never();

        $sqsService = new SqsService($this->queueManager);
        $sqsService->deleteProcessedMessages();

        $this->assertEmpty($this->getPrivateProperty($sqsService, 'processedMessages'));
    }

    public function testAddProcessedMessageAddsMessageToArray(): void
    {
        $sqsService = new SqsService($this->queueManager);

        $message = [
            'Id' => 'test-message-id',
            'ReceiptHandle' => 'test-receipt-handle'
        ];
        $sqsService->addProcessedMessage($message);

        /** @var array<string, string>[] $processedMessages */
        $processedMessages = $this->getPrivateProperty($sqsService, 'processedMessages');

        $this->assertCount(1, $processedMessages);
        $this->assertSame($message, $processedMessages[0]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queueManager = $this->createMock(QueueManager::class);
        $sqsQueue = $this->createMock(SqsQueue::class);
        $this->sqsClient = Mockery::mock(SqsClient::class);

        $this->queueManager->method('connection')->willReturn($sqsQueue);
        $sqsQueue->method('getSqs')->willReturn($this->sqsClient);
        $sqsQueue->method('getContainer')->willReturn(new Container());
    }
}
