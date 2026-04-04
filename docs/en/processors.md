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

Example processor:

```php
<?php
declare(strict_types=1);

namespace App\Queue;

use Cake\Core\ContainerInterface;
use Cake\Queue\Job\Message;
use Cake\Queue\Queue\Processor;
use Enqueue\Consumption\Result;
use Error;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class TimedProcessor extends Processor
{
    public function __construct(?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        parent::__construct($logger, $container);
    }

    public function process(QueueMessage $message, Context $context): string|object
    {
        $this->dispatchEvent('Processor.message.seen', ['queueMessage' => $message]);

        $jobMessage = new Message($message, $context, $this->container);
        try {
            $jobMessage->getCallable();
        } catch (RuntimeException | Error $e) {
            $this->logger->debug('Invalid callable for message. Rejecting message from queue.');
            $this->dispatchEvent('Processor.message.invalid', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $startTime = microtime(true) * 1000;
        $this->dispatchEvent('Processor.message.start', ['message' => $jobMessage]);

        try {
            $response = $this->processMessage($jobMessage);
        } catch (Throwable $e) {
            $message->setProperty('jobException', $e);

            $this->logger->debug(sprintf('Message encountered exception: %s', $e->getMessage()));
            $this->dispatchEvent('Processor.message.exception', [
                'message' => $jobMessage,
                'exception' => $e,
                'duration' => (int)((microtime(true) * 1000) - $startTime),
            ]);

            return Result::requeue('Exception occurred while processing message');
        }

        $duration = (int)((microtime(true) * 1000) - $startTime);

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Message processed successfully');
            $this->dispatchEvent('Processor.message.success', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Message processed with rejection');
            $this->dispatchEvent('Processor.message.reject', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Message processed with failure, requeuing');
        $this->dispatchEvent('Processor.message.failure', [
            'message' => $jobMessage,
            'duration' => $duration,
        ]);

        return InteropProcessor::REQUEUE;
    }
}
```

If no processor is configured, the plugin uses `Cake\Queue\Queue\Processor`.

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
