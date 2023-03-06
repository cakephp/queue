<?php
declare(strict_types=1);

namespace Cake\Queue\Consumption;

use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Job\Message;
use Enqueue\Consumption\Context\MessageResult;
use Enqueue\Consumption\MessageResultExtensionInterface;
use Enqueue\Consumption\Result;
use Interop\Queue\Processor;

class LimitAttemptsExtension implements MessageResultExtensionInterface
{
    use EventDispatcherTrait;

    /**
     * The property key used to set the number of times a message was attempted.
     *
     * @var string
     */
    public const ATTEMPTS_PROPERTY = 'attempts';

    /**
     * The maximum number of times a job may be attempted. $maxAttempts defined on a
     * Job will override this value.
     *
     * @var int|null
     */
    protected $maxAttempts;

    /**
     * @param int|null $maxAttempts The maximum number of times a job may be attempted.
     * @return void
     */
    public function __construct(?int $maxAttempts = null)
    {
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * @param \Enqueue\Consumption\Context\MessageResult $context The result of the message after it was processed.
     * @return void
     */
    public function onResult(MessageResult $context): void
    {
        if ($context->getResult() != Processor::REQUEUE) {
            return;
        }

        $message = $context->getMessage();

        $jobMessage = new Message($message, $context->getContext());

        $maxAttempts = $jobMessage->getMaxAttempts() ?? $this->maxAttempts;

        if ($maxAttempts === null) {
            return;
        }

        $attemptNumber = $message->getProperty(self::ATTEMPTS_PROPERTY, 0) + 1;

        if ($attemptNumber >= $maxAttempts) {
            $context->changeResult(
                Result::reject(sprintf('The maximum number of %d allowed attempts was reached.', $maxAttempts))
            );

            $exception = (string)$message->getProperty('jobException');

            $this->dispatchEvent(
                'Consumption.LimitAttemptsExtension.failed',
                ['exception' => $exception, 'logger' => $context->getLogger()],
                $jobMessage
            );

            return;
        }

        $newMessage = clone $message;
        $newMessage->setProperty(self::ATTEMPTS_PROPERTY, $attemptNumber);

        $queueContext = $context->getContext();
        $producer = $queueContext->createProducer();
        $consumer = $context->getConsumer();
        $producer->send($consumer->getQueue(), $newMessage);

        $context->changeResult(
            Result::reject('A copy of the message was sent with an incremented attempt count.')
        );
    }
}
