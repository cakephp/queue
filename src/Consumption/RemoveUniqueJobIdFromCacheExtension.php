<?php
declare(strict_types=1);

namespace Cake\Queue\Consumption;

use Cake\Cache\Cache;
use Cake\Queue\Job\Message;
use Cake\Queue\QueueManager;
use Enqueue\Consumption\Context\MessageResult;
use Enqueue\Consumption\MessageResultExtensionInterface;

class RemoveUniqueJobIdFromCacheExtension implements MessageResultExtensionInterface
{
    /**
     * Cache engine name.
     *
     * @var string
     */
    protected $cache;

    /**
     * @param string $cache Cache engine name.
     * @return void
     */
    public function __construct(string $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param \Enqueue\Consumption\Context\MessageResult $context The result of the message after it was processed.
     * @return void
     */
    public function onResult(MessageResult $context): void
    {
        $message = $context->getMessage();

        $jobMessage = new Message($message, $context->getContext());

        /** @psalm-var class-string $class */
        [$class, $method] = $jobMessage->getTarget();

        if (empty($class::$shouldBeUnique)) {
            return;
        }

        $data = $jobMessage->getArgument();

        $uniqueId = QueueManager::getUniqueId($class, $method, $data);

        Cache::delete($uniqueId, $this->cache);
    }
}
