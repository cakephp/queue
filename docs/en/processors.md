# Custom Processors

Queue connections can use a custom processor class by setting the `processor` option in the queue configuration. The class must implement `Interop\Queue\Processor`.

```php
'Queue' => [
    'default' => [
        'url' => 'redis://localhost:6379',
        'queue' => 'default',
    ],
    'timed' => [
        'url' => 'redis://localhost:6379',
        'queue' => 'timed',
        'processor' => \App\Queue\TimedProcessor::class,
    ],
],
```

Example processor. The shared `Cake\Queue\Job\JobExecutionTrait` carries
the whole job flow (callable resolution, `Processor.message.*` events,
`null` → `ACK` mapping, exception → requeue), so a custom processor is
only construction plus delegation:

```php
<?php
declare(strict_types=1);

namespace App\Queue;

use Cake\Core\ContainerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Job\JobExecutionTrait;
use Cake\Queue\Job\Message;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TimedProcessor implements InteropProcessor
{
    use EventDispatcherTrait;
    use JobExecutionTrait;

    protected LoggerInterface $logger;

    protected ?ContainerInterface $container = null;

    public function __construct(?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->container = $container;
    }

    public function process(QueueMessage $message, Context $context): string|object
    {
        $jobMessage = new Message($message, $context, $this->container);

        return $this->runJob($jobMessage);
    }
}
```

If no processor is configured, the worker consumes through the default bus
pipeline with `Cake\Queue\Bus\Middleware\HandlerMiddleware` as the terminal.

The worker command's `--processor` option is separate from the config `processor` key:

- Config `processor` selects the PHP class used to handle messages.
- `--processor` sets the processor name used for Enqueue topic binding.

Examples:

```bash
bin/cake queue worker --config=timed
```

```bash
bin/cake queue worker --config=timed --processor=my-topic-processor
```
