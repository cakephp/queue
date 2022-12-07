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
namespace Cake\Queue\Listener;

use Cake\Event\EventListenerInterface;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Queue\QueueManager;
use Psr\Log\LoggerInterface;
use RuntimeException;

class FailedJobsListener implements EventListenerInterface
{
    use LocatorAwareTrait;

    /**
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Consumption.LimitAttemptsExtension.failed' => 'storeFailedJob',
        ];
    }

    /**
     * @param object $event EventInterface.
     * @return void
     */
    public function storeFailedJob($event): void
    {
        /** @var \Cake\Queue\Job\Message $jobMessage */
        $jobMessage = $event->getSubject();

        [$class, $method] = $jobMessage->getTarget();

        $originalMessage = $jobMessage->getOriginalMessage();

        $originalMessageBody = json_decode($originalMessage->getBody(), true);

        ['data' => $data, 'requeueOptions' => $requeueOptions] = $originalMessageBody;

        $config = QueueManager::getConfig($requeueOptions['config']);

        if (!($config['storeFailedJobs'] ?? false)) {
            return;
        }

        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');

        $failedJob = $failedJobsTable->newEntity([
            'class' => $class,
            'method' => $method,
            'data' => json_encode($data),
            'config' => $requeueOptions['config'],
            'priority' => $requeueOptions['priority'],
            'queue' => $requeueOptions['queue'],
            'exception' => $event->getData('exception'),
        ]);

        try {
            $failedJobsTable->saveOrFail($failedJob);
        /** @phpstan-ignore-next-line */
        } catch (PersistenceFailedException $e) {
            $logger = $event->getData('logger');

            if (!$logger) {
                throw new RuntimeException(
                    sprintf('`logger` was not defined on %s event.', $event->getName()),
                    0,
                    $e
                );
            }

            if (!($logger instanceof LoggerInterface)) {
                throw new RuntimeException(
                    sprintf('`logger` is not an instance of `LoggerInterface` on %s event.', $event->getName()),
                    0,
                    $e
                );
            }

            $logger->error((string)$e);
        }
    }
}
