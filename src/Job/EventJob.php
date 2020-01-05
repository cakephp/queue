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
namespace Queue\Job;

use Cake\Event\EventManager;
use Interop\Queue\Processor;

class EventJob implements JobInterface
{
    /**
     * Constructs and dispatches the event from a job message
     *
     * @param \Queue\Job\Message $message job message
     * @return string
     */
    public function execute(Message $message): ?string
    {
        $eventClass = $message->getArgument('className');
        $eventName = $message->getArgument('eventName');
        $data = $message->getArgument('data', []);
        if (!class_exists($eventClass)) {
            return Processor::REJECT;
        }

        /** @var \Cake\Event\EventInterface $event */
        $event = new $eventClass($eventName, null, $data);
        EventManager::instance()->dispatch($event);
        if ($event->isStopped()) {
            return Processor::REJECT;
        }

        if (!empty($event->getResult()['return'])) {
            return $event->getResult()['return'];
        }

        return Processor::ACK;
    }
}
