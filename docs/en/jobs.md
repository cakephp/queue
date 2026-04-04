# Defining and Queueing Jobs

## Defining Jobs

Jobs are classes that implement `Cake\Queue\Job\JobInterface`. They receive a `Cake\Queue\Job\Message` instance and return one of the `Interop\Queue\Processor` status constants.

```php
<?php
declare(strict_types=1);

namespace App\Job;

use Cake\Log\LogTrait;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class ExampleJob implements JobInterface
{
    use LogTrait;

    public static $maxAttempts = 3;

    public static $shouldBeUnique = false;

    public function execute(Message $message): ?string
    {
        $id = $message->getArgument('id');
        $data = $message->getArgument('data');

        $this->log(sprintf('%d %s', $id, $data));

        return Processor::ACK;
    }
}
```

Job classes can use constructor injection in the same way as controllers or commands.

## Message Access

The `Message` object exposes:

- `getArgument($key = null, $default = null)` to fetch the full payload or a nested value using `Hash::get()` notation.
- `getContext()` to access the original queue context.
- `getOriginalMessage()` to access the broker-specific message object.
- `getParsedBody()` to inspect the parsed queue body.

## Return Values

A job may return:

- `Processor::ACK` when processing succeeded and the message should be removed.
- `Processor::REJECT` when processing failed permanently and the message should be removed.
- `Processor::REQUEUE` when the job should be retried later.
- `null`, which is treated as `Processor::ACK`.

Returning any other value is treated as a failure and results in the message being requeued.

## Job Properties

- `maxAttempts` limits how many times a job can be retried after an exception or explicit `Processor::REQUEUE`. If unset, the worker's `--max-attempts` option applies. If neither is set, retries are unlimited.
- `shouldBeUnique` allows only one queued copy of the same job class, method, and payload. Duplicate pushes are ignored. This requires `uniqueCache` in the queue configuration.

## Queueing Jobs

Use `Cake\Queue\QueueManager` to publish a job:

```php
use App\Job\ExampleJob;
use Cake\Queue\QueueManager;

$data = ['id' => 7, 'is_premium' => true];
$options = ['config' => 'default'];

QueueManager::push(ExampleJob::class, $data, $options);
```

Arguments:

- The first argument is the job class name.
- The second argument is an optional JSON-serializable payload array.
- The third argument is an optional array of queueing options.

Supported options:

- `config`: queue config name. Defaults to `default`.
- `delay`: delay processing by a number of seconds. Broker support varies.
- `expires`: expire the message after a number of seconds if it has not been consumed.
- `priority`: one of `\Enqueue\Client\MessagePriority::VERY_LOW`, `LOW`, `NORMAL`, `HIGH`, or `VERY_HIGH`.
- `queue`: queue name to use. Defaults to the configured queue, then `default`.
