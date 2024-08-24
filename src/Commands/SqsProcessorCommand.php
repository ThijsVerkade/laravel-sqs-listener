<?php

declare(strict_types=1);

namespace ThijsVerkade\LaravelSqsListener\Commands;

use ThijsVerkade\LaravelSqsListener\Exceptions\JobProcessingException;
use ThijsVerkade\LaravelSqsListener\Handlers\JobProcessor;
use ThijsVerkade\LaravelSqsListener\Services\SqsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class SqsProcessorCommand extends Command
{
    protected $signature = 'queue:sqs-processor';

    protected $description = 'Process SQS messages from SQS queue';

    private int $messagesProcessed = 0;

    private int $messagesFailed = 0;

    private bool $shouldKeepRunning = true;

    public function __construct(
        private readonly SqsService $sqsService,
        private readonly JobProcessor $jobProcessor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->logInfo('Command Start');

        while ($this->shouldKeepRunning) {
            $sqsJobs = $this->sqsService->receiveMessages();

            if ($sqsJobs === []) {
                $this->shouldKeepRunning = false;
                continue;
            }

            foreach ($sqsJobs as $job) {
                try {
                    $this->jobProcessor->process($job);
                    ++$this->messagesProcessed;
                } catch (JobProcessingException $e) {
                    $this->logError($e->getMessage(), $e->getContext());
                    ++$this->messagesFailed;
                }
            }

            $this->sqsService->deleteProcessedMessages();
        }

        $this->logInfo('Command End', [
            'messagesProcessed' => $this->messagesProcessed,
            'messagesFailed' => $this->messagesFailed,
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        $timestamp = now()->toDateTimeString();
        $context = array_merge(['time' => $timestamp, 'command' => $this->signature], $context);
        Log::info($message, $context);
        $this->info(sprintf('%s [%s] %s', $message, $this->signature, $timestamp));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logError(string $message, array $context = []): void
    {
        $timestamp = now()->toDateTimeString();
        $context = array_merge(['time' => $timestamp, 'command' => $this->signature], $context);
        Log::error($message, $context);
        $this->error($message);
    }
}
