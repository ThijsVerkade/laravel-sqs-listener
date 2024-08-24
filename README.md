# Laravel SQS Listener
This is a Laravel package that provides an SQS listener command for Laravel applications.

## Installation
You can install the package via composer:

```bash
composer require thijs.verkade/laravel-sqs-listener
```

## Usage

After installing the package, run the following command:

```bash
php artisan vendor:publish --provider="ThijsVerkade\LaravelSqsListener\SqsListenerServiceProvider"
```

This command will create the necessary configurations for the package to function properly.

You can specify the SNS topics that the SQS listener command should listen to by configuring
the `laravel-sqs-processor.sns-topics` value. This configuration should be an associative array where the keys are the
SNS topic ARNs and the values are the corresponding handler classes. For example:

```php
'laravel-sqs-processor' => [
    'sns-topics' => [
        'arn:aws:sns:us-east-1:123456789012:MyTopic' => App\Handlers\MyTopicHandler::class,
        'arn:aws:sns:us-east-1:123456789012:AnotherTopic' => App\Handlers\AnotherTopicHandler::class,
    ],
],
```

In this example, when a message is received from the `MyTopic` SNS topic, the `App\Handlers\MyTopicHandler` class will
handle the message. Similarly, messages from the `AnotherTopic` SNS topic will be handled by
the `App\Handlers\AnotherTopicHandler` class. Each handler class should implement
the `ThijsVerkade\LaravelSqsListener\HandlerInterface` and
accept an `Illuminate\Contracts\Queue\Job` parameter in its `__invoke` method. This `Job` parameter represents the job
that was popped from the queue.

```php
<?php

declare(strict_types=1);

use ThijsVerkade\LaravelSqsListener\HandlerInterface;
use Illuminate\Queue\Jobs\Job;

class MyTopicHandler implements HandlerInterface
{
    public function __invoke(Job $job): void
    {
        // Handle the job...
    }
}
```

To schedule the SQS listener command, you can use Laravel's built-in scheduler. For example:

```php
$schedule->command(\ThijsVerkade\LaravelSqsListener\SqsListenerCommand::class)
->everyMinute()
->withoutOverlapping()
->environments('prod', 'stg', 'dev')
->timezone(new DateTimeZone('Europe/Amsterdam'))
->runInBackground()
->sendOutputTo('/proc/1/fd/1');
```

## License
The Laravel SQS Listener is open-sourced software licensed under the MIT license.