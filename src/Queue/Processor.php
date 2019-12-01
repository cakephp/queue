<?php
namespace Queue\Queue;

use Cake\Event\EventDispatcherTrait;
use Cake\Log\LogTrait;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Exception;
use Queue\Queue\JobData;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor as InteropProcessor;

class Processor implements InteropProcessor
{
    use EventDispatcherTrait;
    use LogTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Processor constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * The method processes messages
     *
     * @param Message $message
     * @param Context $context
     *
     * @return string|object with __toString method implemented
     */
    public function process(Message $message, Context $context)
    {
        $this->dispatchEvent('Processor.job.seen', ['message' => $message]);

        $success = false;
        $job = new JobData($message, $context);
        if (!is_callable($job->getCallable())) {
            $this->logger->debug('Invalid callable for job. Rejecting job from queue.');
            $this->dispatchEvent('Processor.job.invalid', ['job' => $job]);
            return InteropProcessor::REJECT;
        }

        $this->dispatchEvent('Processor.job.start', ['job' => $job]);

        try {
            $response = $this->runJob($job);
        } catch (Exception $e) {
            $this->logger->debug(sprintf('Job encountered exception: %s', $e->getMessage()));
            $this->dispatchEvent('Processor.job.exception', [
                'job' => $job,
                'exception' => $e,
            ]);
            return InteropProcessor::REQUEUE;
        }

        if ($response === null) {
            $response = InteropProcessor::ACK;
        }

        if (is_bool($response)) {
            if ($response) {
                $response = InteropProcessor::ACK;
            } else {
                $response = InteropProcessor::REQUEUE;
            }
        }

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Job processed sucessfully');
            $this->dispatchEvent('Processor.job.success', ['job' => $job]);
            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Job processed with rejection');
            $this->dispatchEvent('Processor.job.reject', ['job' => $job]);
            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Job processed with failure, requeuing');
        $this->dispatchEvent('Processor.job.failure', ['job' => $job]);
        return InteropProcessor::REQUEUE;
    }

    public function runJob($job)
    {
        $callable = $job->getCallable();

        $success = false;
        if (is_array($callable) && count($callable) == 2) {
            $className = $callable[0];
            $methodName = $callable[1];
            $instance = new $className;
            $success = $instance->$methodName($job);
        } elseif (is_string($callable)) {
            $success = call_user_func($callable, $job);
        }

        if ($success === null) {
            $success = true;
        }

        return $success;
    }
}
