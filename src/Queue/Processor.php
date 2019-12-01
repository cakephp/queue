<?php
namespace Queue\Queue;

use Cake\Event\EventDispatcherTrait;
use Cake\Log\LogTrait;
use Psr\Log\LogLevel;
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
     * The method processes messages
     *
     * @param Message $message
     * @param Context $context
     *
     * @return string|object with __toString method implemented
     */
    public function process(Message $message, Context $context)
    {
        $this->dispatchEvent('Worker.job.seen', ['message' => $message]);

        $success = false;
        $job = new JobData($message);
        if (!is_callable($job->getCallable())) {
            $this->log('Invalid callable for job. Rejecting job from queue.');
            $this->dispatchEvent('Worker.job.invalid', ['job' => $job]);
            return InteropProcessor::REJECT;
        }

        $this->dispatchEvent('Worker.job.start', ['job' => $job]);

        try {
            $response = $this->perform($job);
        } catch (Exception $e) {
            $this->log(sprintf('Job encountered exception: %s', $e->getMessage()));
            $this->dispatchEvent('Worker.job.exception', [
                'job' => $job,
                'exception' => $e,
            ]);
            return InteropProcessor::REQUEUE;
        }

        if (is_bool($response)) {
            if ($response) {
                $response = InteropProcessor::ACK;
            } else {
                $response = InteropProcessor::REQUEUE;
            }
        }

        if ($response === InteropProcessor::ACK) {
            $this->log('Job processed sucessfully', LogLevel::DEBUG);
            $this->dispatchEvent('Worker.job.success', ['job' => $job]);
            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->log('Job processed with rejection', LogLevel::DEBUG);
            $this->dispatchEvent('Worker.job.reject', ['job' => $job,]);
            return InteropProcessor::REJECT;
        }

        $this->log('Job processed with failure, requeuing', LogLevel::DEBUG);
        $this->dispatchEvent('Worker.job.failure', ['job' => $job]);
        return InteropProcessor::REQUEUE;
    }

    public function perform($job)
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
