# Queue plugin for CakePHP

Experimental queue plugin for CakePHP using queue-interop.

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org).

The recommended way to install composer packages is:

```shell
composer require josegonzalez/queue
```

Install the transport you wish to use. For a list of available transports, see [this page](https://php-enqueue.github.io/transport). The example below is for pure-php redis:

```shell
composer require enqueue/redis predis/predis:^1
```

Ensure that the plugin is loaded in your `src/Application.php` file, within the `Application::bootstrap()` function:

```php
$this->addPlugin('Queue');
```

### Configuration

The following configuration should be present in your app.php:

```php
$config = [
    'Queue' => [
        'default' => [
              // A DSN for your configured backend. No default
              'url' => 'redis:'

              // The queue that will be used for sending messages. default: default
              'queue' => 'default',

              // The name of a configured logger, default: debug
              'logger' => 'debug',

              // The name of an event listener class to associate with the worker
              'listener' => 'App\Listener\WorkerListener',
        ]
    ]
];
```

## Usage

### Defining Jobs

Create a Job class:

```php
<?php
// src/Job/ExampleJob.php
namespace App\Job;

use Cake\Log\LogTrait;
use Interop\Queue\Processor;
use Psr\Log\LogLevel;
use Queue\Queue\JobData;

class ExampleJob
{
    use LogTrait;

    public function execute(JobData $job)
    {
        $id = $job->getData('id');
        $message = $job->getData('message');

        $this->log(sprintf('%d %s', $id, $message), LogLevel::INFO);

        return Processor::ACK;
    }
}
```

The passed JobData object has the following methods:

- `getData($key = null, $default = null)`: Can return the entire passed dataset or a value based on a `Hash::get()` notation key.
- `getMessage`: Returns the original message object.
- `getParsedBody`: Returns the parsed message body.

A job _may_ return any of the following values:

- `Processor::ACK`: Use this constant when the job is processed successfully. The message will be removed from the queue.
- `Processor::REJECT`: Use this constant when the job could not be processed. The message will be removed from the queue.
- `Processor::REQUEUE`: Use this constant when the message is not valid or could not be processed right now but we can try again later. The original message is removed from the queue but a copy is published to the queue again.

The job _may_ also return a boolean or null value. These are mapped as follows:

- `null`: `Processor::ACK`
- `true`: `Processor::ACK`
- `false`: `Processor::REJECT`

Finally, the original message as well as the processed body are available via accessing the `JobData` object.

### Queue Jobs

Queue the jobs using the included `Queue\Queue\Queue` class:

```php
use Queue\Queue\Queue;

$callable = ['\App\Job\ExampleJob', 'execute'];
$arguments = ['id' => 7, 'message' => 'hi2u'];
$options = ['config' => 'default'];

Queue::push($callable, $arguments, $options);
```

Arguments:
  - `$callable`: A callable that will be invoked. This callable _must_ be valid within the context of your application. Job classes are prefered.
  - `$arguments` (optional): A json-serializable array of data that is to be made present for your job. It should be key-value pairs.
  - `$options` (optional): An array of optional data for message queueing.

The following keys are valid for use within the `options` array:

- `config`:
  - default: default
  - description: A queue config name
  - type: string
- `delay`:
  - default: `null`
  - description: Time - in integer seconds - to delay message, after which it will be processed. Not all message brokers accept this.
  - type: integer
- `expires_at`:
  - default: `null`
  - description: Time - in integer seconds - after which the message expires. The message will be removed from the queue if this time is exceeded and it has not been consumed.
  - type: integer
- `priority`:
  - default: `null`
  - type: constant
  - valid values:
    - `\Enqueue\Client\MessagePriority::VERY_LOW`
    - `\Enqueue\Client\MessagePriority::LOW`
    - `\Enqueue\Client\MessagePriority::NORMAL`
    - `\Enqueue\Client\MessagePriority::HIGH`
    - `\Enqueue\Client\MessagePriority::VERY_HIGH`
- `queue`:
  - default: from queue `config` array or string `default` if empty
  - description: The name of a queue to use
  - type: string  

### Run the worker

Once a job is queued, you may run a worker via the included `worker` shell:

```shell
bin/cake worker
```

This shell can take a few different options:

- `--config` (default: default): Name of a queue config to use
- `--queue` (default: default): Name of queue to bind to
- `--logger` (default: stdout): Name of a configured logger
- `--max-iterations` (default: 0): Number of max iterations to run
- `--max-runtime` (default: 0): Seconds for max runtime

### Worker Events

The worker shell may invoke the events during normal execution. These events may be listened to by the associated `listener` in the Queue config.

- `Processor.job.exception`
  - description: Dispatched when a job throws an exception.
  - arguments: `job` and `exception`
- `Processor.job.invalid`'
  - description: Dispatched when a job has an invalid callable.
  - arguments: `job`
- `Processor.job.reject`'
  - description: Dispatched when a job completes and is to be rejected.
  - arguments: `job`
- `Processor.job.success`'
  - description: Dispatched when a job completes and is to be acknowledged.
  - arguments: `job`
- `Processor.maxIterations`
  - description: Dispatched when the max number of iterations is reached.
- `Processor.maxRuntime`
  - description: Dispatched when the max runtime is reached.
- `Processor.job.failure`'
  - description: Dispatched when a job completes and is to be requeued.
  - arguments: `job`
- `Processor.job.seen`'
  - description: Dispatched when a message is seen.
  - arguments: `job`
- `Processor.job.start`'
  - description: Dispatched before a job is started.
  - arguments: `job`
