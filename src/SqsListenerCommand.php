<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class SqsListenerCommand extends Command
{
    protected $signature = 'queue:listen-sqs';
    protected $description = 'Listen to the SQS queue';

    public function __construct(private readonly QueueManager $queueManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting SQS listener command');
        Log::info('Starting SQS listener command', [
            'command' => $this->signature,
        ]);

        $snsTopics = Config::get('queue.sns-topics');
        if ($snsTopics === null) {
            $this->info('No SNS topics available');
            Log::info('No SNS topics available', [
                'snsTopics' => $snsTopics,
                'command' => $this->signature
            ]);
            return Command::FAILURE;
        }

        $size = $this->queueManager->size();
        $this->info('Initial queue size: ' . $size);
        Log::info('Initial queue size', [
            'queueSize' => $size,
            'command' => $this->signature
        ]);

        while ($size > 0) {
            $job = $this->queueManager->pop();
            if ($job === null) {
                $this->info('No SQS messages available');
                Log::info('No SQS messages available', ['command' => $this->signature]);
                continue;
            }

            /** @var array{
             *     Type: string,
             *     MessageId: string,
             *     TopicArn: string,
             * }|null $payload
             */
            $payload = $job->payload();

            if ($payload === null) {
                $this->info(sprintf('SQS message payload is not available jobId:%s', $job->getJobId()));
                Log::error('SQS message payload is not available', [
                    'jobId' => $job->getJobId(),
                    'command' => $this->signature
                ]);
                continue;
            }

            $topicArn = $payload['TopicArn'] ?? null;

            if ($topicArn && isset($snsTopics[$topicArn])) {
                $handlerClass = $snsTopics[$topicArn];

                $handler = App::make($handlerClass);
                Log::info('Executing handler', [
                    'messageId' => $payload['MessageId'] ?? null,
                    'topicArn' => $topicArn,
                    'command' => $this->signature
                ]);

                $handler($job);
                $this->info('Remaining queue size: ' . $size);
            }

            $size--;
        }

        Log::info('Finished SQS listener command', ['command' => $this->signature]);
        return Command::SUCCESS;
    }
}