<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Queue;

use Cake\Core\ContainerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Queue\Job\JobExecutionTrait;
use Cake\Queue\Job\Message;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Default job-running processor.
 *
 * @deprecated Will be removed in 4.0. Legacy compatibility only: do not
 *   use it in new code — prefer the default bus pipeline (omit the
 *   `processor` config key) or implement `Interop\Queue\Processor` directly
 *   (see Custom Processors). Kept so existing `extends Processor`
 *   hierarchies and `'processor' => Processor::class` configs keep working;
 *   inside the consume bus such a processor runs through a transparent
 *   adapter, so its own lifecycle events and debug lines behave exactly as
 *   without the bus.
 */
class Processor implements InteropProcessor
{
    use EventDispatcherTrait;
    use JobExecutionTrait;

    protected ?LoggerInterface $logger = null;

    protected ?ContainerInterface $container = null;

    /**
     * @param \Psr\Log\LoggerInterface|null $logger Logger instance.
     * @param \Cake\Core\ContainerInterface|null $container DI container instance.
     */
    public function __construct(?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->container = $container;
    }

    /**
     * Run the job and map its outcome to a transport status.
     *
     * @param \Interop\Queue\Message $message Queue message.
     * @param \Interop\Queue\Context $context Queue context.
     * @return object|string Processing result.
     */
    public function process(QueueMessage $message, Context $context): string|object
    {
        $jobMessage = new Message($message, $context, $this->container);

        return $this->runJob($jobMessage);
    }
}
