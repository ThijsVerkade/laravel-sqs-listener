<?php

declare(strict_types=1);

namespace ThijsVerkade\LaravelSqsListener\Services;

use Aws\Sqs\SqsClient;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use InvalidArgumentException;

class SqsService
{
    private readonly SqsQueue $sqsQueue;

    private readonly SqsClient $sqsClient;

    /**
     * @var array<array<string, mixed>>
     */
    private array $processedMessages = [];

    public function __construct(QueueManager $queueManager)
    {
        $queue = $queueManager->connection('sqs');

        if (!$queue instanceof SqsQueue) {
            throw new InvalidArgumentException('The queue connection is not an instance of SqsQueue');
        }

        $this->sqsQueue = $queue;
        $this->sqsClient = $this->sqsQueue->getSqs();
    }

    /**
     * @return SqsJob[]
     */
    public function receiveMessages(): array
    {
        /** @var array{
         *     Messages: array<int, array<int, string>>|null
         * } $response
         */
        $response = $this->sqsClient->receiveMessage([
            'QueueUrl' => $queue = $this->sqsQueue->getQueue(null),
            'AttributeNames' => ['ApproximateReceiveCount'],
            'MaxNumberOfMessages' => 10,
        ]);

        $messages = $response['Messages'] ?? [];
        return array_map(fn($message) => new SqsJob(
            $this->sqsQueue->getContainer(),
            $this->sqsClient,
            $message,
            $this->sqsQueue->getConnectionName(),
            $queue
        ), $messages);
    }

    public function deleteProcessedMessages(): void
    {
        if ($this->processedMessages === []) {
            return;
        }

        $this->sqsClient->deleteMessageBatch([
            'QueueUrl' => $this->sqsQueue->getQueue(null),
            'Entries' => $this->processedMessages,
        ]);

        $this->processedMessages = [];
    }

    /**
     * @param array<string, mixed> $message
     */
    public function addProcessedMessage(array $message): void
    {
        $this->processedMessages[] = $message;
    }
}
