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
use Psr\Log\LogLevel;

class ExampleJob
{
    use LogTrait;

    public function perform($job)
    {
        $id = $job->data('id');
        $message = $job->data('message');

        $this->log(sprintf('%d %s', $id, $message), LogLevel::INFO);
    }
}
```

### Queue Jobs

Queue the jobs using the included `Queue\Queue\Queue` class:

```php
use Queue\Queue\Queue;

$callable = ['\App\Job\ExampleJob', 'perform'];
$arguments = ['id' => 7, 'message' => 'hi2u'];
$options = ['config' => 'default']''

Queue::push($callable, $arguments, $options);
```

Arguments:
  - `$callable`: A callable that will be invoked. This callable _must_ be valid within the context of your application. Job classes are prefered.
  - `$arguments` (optional): A json-serializable array of data that is to be made present for your job. It should be key-value pairs.
  - `$options` (optional): An array of optional data. This may contain a `queue` name or a `config` key that matches a configured Queue backend.


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
