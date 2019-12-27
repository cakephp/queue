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
              // A DSN for your configured backend. default: null
              'url' => 'redis:'

              // The queue that will be used for sending messages. default: default
              'queue' => 'default',

              // The name of a configured logger, default: null
              'logger' => 'stdout',

              // The name of an event listener class to associate with the worker
              'listener' => \App\Listener\WorkerListener::class,
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
use Queue\Job\Message;

class ExampleJob implements JobInterface
{
    use LogTrait;

    public function execute(Message $message): ?string
    {
        $id = $message->getArgument('id');
        $data = $message->getArgument('data');

        $this->log(sprintf('%d %s', $id, $data));

        return Processor::ACK;
    }
}
```

The passed `Message` object has the following methods:

- `getArgument($key = null, $default = null)`: Can return the entire passed dataset or a value based on a `Hash::get()` notation key.
- `getContext()`: Returns the original context object.
- `getOriginalMessage()`: Returns the original queue message object.
- `getParsedBody()`: Returns the parsed queue message body.

A job _may_ return any of the following values:

- `Processor::ACK`: Use this constant when the job is processed successfully. The message will be removed from the queue.
- `Processor::REJECT`: Use this constant when the job could not be processed. The message will be removed from the queue.
- `Processor::REQUEUE`: Use this constant when the message is not valid or could not be processed right now but we can try again later. The original message is removed from the queue but a copy is published to the queue again.

The job _may_ also return a null value, which is interpreted as `Processor::ACK`. Failure to respond with a valid type will result in an interperted job failure and requeue of the job.

### Queue Jobs

Queue the jobs using the included `Queue\Queue\QueueManager` class:

```php
use App\Job\ExampleJob;
use Queue\Queue\QueueManager;

$callable = [ExampleJob::class, 'execute'];
$arguments = ['id' => 7, 'data' => 'hi2u'];
$options = ['config' => 'default'];

QueueManager::push($callable, $arguments, $options);
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

#### Queueing Events

CakePHP Event classes may also be queued.

```php
use Queue\Queue\QueueManager;

QueueManager::pushEvent('Model.Order.afterPlace', ['data' => 'val']));
```

Arguments:
  - `$eventName`: The name of the event.
  - `$data` (optional): A json-serializable array of data that is to be made present for your event. It should be key-value pairs.
  - `$options` (optional): An array of optional data for message queueing.

Other than the options available for `QueueManager::push()`, the following options are additionally available for use within the `options` array:

- `eventClass`:
  - default: `Cake\Event\Event::class`
  - description: A string representing the fully namespaced class name of the event to instantiate.
  - type: string

When processed, queued events are not attached to a given subject, and are dispatched using the global event manager. It is recommended that callbacks for these events are associated with the global event manager in the `App\Application::bootstrapCli()` method. This will avoid the overhead of associating callbacks for every web request.

```php
<?php
namespace App;
use Cake\Event\Event;
use Cake\Event\EventManager;

class Application extends BaseApplication
{
    // ... other logic here ...
 
    protected function bootstrapCli()
    {
      // ... other logic here ...
      EventManager::instance()->on('Model.Order.afterPlace', function (Event $event) {
          // handle event here
      });
    }
}
```

Another method that can be used to decrease logic in the Application class may be to associate one or more [listener classes](https://book.cakephp.org/4/en/core-libraries/events.html#registering-listeners) to the global event manager.

If an event is stopped, this is interpreted as as a `Processor::REJECT`. The return value will otherwise default to `Processor::ACK`, but may be overriden by setting the `return` key on the event result.

```php
// A listener callback
public function doSomething($event)
{
    // ...
    $event->setResult(['return' => Processor::REQUEUE] + $this->result());
}
```

Results and other state are not persisted across multiple invocations of the same event.

### Run the worker

Once a job is queued, you may run a worker via the included `worker` shell:

```shell
bin/cake worker
```

This shell can take a few different options:

- `--config` (default: default): Name of a queue config to use
- `--queue` (default: default): Name of queue to bind to
- `--logger` (default: `stdout`): Name of a configured logger
- `--max-iterations` (default: `null`): Number of max iterations to run
- `--max-runtime` (default: `null`): Seconds for max runtime

### Worker Events

The worker shell may invoke the events during normal execution. These events may be listened to by the associated `listener` in the Queue config.

- `Processor.job.exception`:
  - description: Dispatched when a job throws an exception.
  - arguments: `job` and `exception`
- `Processor.job.invalid`:
  - description: Dispatched when a job has an invalid callable.
  - arguments: `job`
- `Processor.job.reject`:
  - description: Dispatched when a job completes and is to be rejected.
  - arguments: `job`
- `Processor.job.success`:
  - description: Dispatched when a job completes and is to be acknowledged.
  - arguments: `job`
- `Processor.maxIterations`:
  - description: Dispatched when the max number of iterations is reached.
- `Processor.maxRuntime`:
  - description: Dispatched when the max runtime is reached.
- `Processor.job.failure`:
  - description: Dispatched when a job completes and is to be requeued.
  - arguments: `job`
- `Processor.job.seen`:
  - description: Dispatched when a message is seen.
  - arguments: `job`
- `Processor.job.start`:
  - description: Dispatched before a job is started.
  - arguments: `job`
