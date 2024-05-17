# Laravel SQS Listener
This is a Laravel package that provides an SQS listener command for Laravel applications.

## Installation
You can install the package via composer:

```bash
composer require thijs.verkade/laravel-sqs-listener
```

## Usage
After installing the package, you can schedule the SQS listener command in your Laravel application:

```php
$schedule->command(\ThijsVerkade\LaravelSqsListener\SqsListenerCommand::class)
->everyMinute()
->withoutOverlapping()
->environments('prod', 'stg', 'dev')
->timezone(new DateTimeZone('Europe/Amsterdam'))
->runInBackground()
->sendOutputTo('/proc/1/fd/1');
```
You can control the scheduling and logging of the SQS listener command via the `queue.sqs.schedule_listener` and `queue.logging_enabled` configuration values respectively.

Additionally, you can specify the SNS topics that the SQS listener command should listen to via the `queue.sns-topics` configuration value. This should be an associative array where the keys are the SNS topic ARNs and the values are the corresponding handler classes. For example:

```php
'queue' => [
    'sqs' => [
        'schedule_listener' => true,
        'sns-topics' => [
            'arn:aws:sns:us-east-1:123456789012:MyTopic' => App\Handlers\MyTopicHandler::class,
            'arn:aws:sns:us-east-1:123456789012:AnotherTopic' => App\Handlers\AnotherTopicHandler::class,
        ],
    ],
],
```

In this example, when a message is received from the `MyTopic` SNS topic, the `App\Handlers\MyTopicHandler` class will handle the message. Similarly, messages from the `AnotherTopic` SNS topic will be handled by the `App\Handlers\AnotherTopicHandler` class. Each handler class should implement the `SqsListenerHandlerInterface` and accept an `Illuminate\Contracts\Queue\Job` parameter in its `__invoke` method. This `Job` parameter represents the job that was popped from the queue.

```php
<?php

declare(strict_types=1);

use Illuminate\Queue\Jobs\Job;

class MyTopicHandler implements SqsListenerHandlerInterface
{
    public function __invoke(Job $job): void
    {
        // Handle the job...
    }
}
```

## Configuration
The package uses the following environment variables:

- `QUEUE_SQS_SCHEDULE_LISTENER`: Whether to schedule the SQS listener command (default: `true`).
- `QUEUE_LOGGING_ENABLED`: Whether to enable logging for the SQS listener command (default: `true`).

### Overwriting Configuration
If you need to overwrite the default configuration, follow these steps:

1. Publish the package's configuration file to your application's `config` directory using the `vendor:publish` command:
```bash
php artisan vendor:publish --provider="ThijsVerkade\LaravelSqsListener\SqsListenerServiceProvider"
```
2. After running this command, a new `queue.php` file should be in your application's `config` directory. You can modify this file to customize the behavior of the SQS listener.  
3. Remember to back up your existing `queue.php` file before running the `vendor:publish` command, as it will be overwritten if the package provides its own `queue.php` file.  
4. If the package doesn't provide a `queue.php` file, you'll need to manually add the necessary configuration values to your existing `queue.php` file.  
5. After making changes, run `php artisan config:cache` to clear the configuration cache and ensure your changes take effect

## License
The Laravel SQS Listener is open-sourced software licensed under the MIT license.