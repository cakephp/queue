# Installation and Configuration

## Installation

Install the plugin with Composer:

```bash
composer require cakephp/queue
```

Install the transport package for the broker you want to use. For the list of supported transports, see [php-enqueue transport docs](https://php-enqueue.github.io/transport/). For example, a Redis setup can use:

```bash
composer require enqueue/redis predis/predis:^3
```

Load the plugin in your application's `bootstrap()` method:

```php
$this->addPlugin('Cake/Queue');
```

## Configuration

Define one or more queue connections in `config/app.php`:

```php
'Queue' => [
    'default' => [
        // DSN or transport config array. Required.
        'url' => 'redis://myusername:mypassword@example.com:1000',

        // Queue name used for publishing and consuming messages.
        'queue' => 'default',

        // Optional logger configured through CakePHP's Log class.
        'logger' => 'stdout',

        // Optional listener class attached to worker processor events.
        'listener' => \App\Listener\WorkerListener::class,

        // Optional processor class. Defaults to Cake\Queue\Queue\Processor.
        'processor' => \App\Queue\CustomProcessor::class,

        // Sleep time in milliseconds when no messages are available.
        'receiveTimeout' => 10000,

        // Persist jobs that exceed their allowed retry count.
        'storeFailedJobs' => true,

        // Cache used to track unique jobs.
        'uniqueCache' => [
            'engine' => 'File',
        ],
    ],
],
```

The `Queue` config key may contain multiple named connections. Each connection can point to a different backend or queue topology.

## Failed Job Storage

When `storeFailedJobs` is enabled, install the migrations plugin and create the `queue_failed_jobs` table:

```bash
composer require cakephp/migrations:^3.1
```

```bash
bin/cake migrations migrate --plugin Cake/Queue
```

## Unique Jobs

If a job sets `public static $shouldBeUnique = true`, you must configure `uniqueCache`. The cache duration should be longer than the maximum time the job might remain queued, or duplicate jobs may become possible.
