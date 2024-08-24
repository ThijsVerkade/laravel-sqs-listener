<?php

declare(strict_types=1);

namespace ThijsVerkade\LaravelSqsListener\Handlers;

use ThijsVerkade\LaravelSqsListener\Exceptions\JobProcessingException;
use ThijsVerkade\LaravelSqsListener\HandlerInterface;
use ThijsVerkade\LaravelSqsListener\Services\SqsService;
use Illuminate\Queue\Jobs\SqsJob;

class JobProcessor
{
    public function __construct(
        private readonly SqsService $sqsService,
        private readonly MessageHandlerResolver $resolver
    ) {
    }

    public function process(SqsJob $job): void
    {
        $jobInfo = $job->getSqsJob();
        $payload = $job->payload();

        if ($payload === []) {
            throw new JobProcessingException(
                'Message payload is not available',
                ['jobId' => $job->getJobId(), 'messageId' => $jobInfo['MessageId']]
            );
        }

        $handler = $this->resolver->resolve($payload['TopicArn'] ?? null);

        if ($handler instanceof HandlerInterface) {
            $handler($job);
        }

        $this->sqsService->addProcessedMessage([
            'Id' => $jobInfo['MessageId'],
            'ReceiptHandle' => $jobInfo['ReceiptHandle'],
        ]);
    }
}
