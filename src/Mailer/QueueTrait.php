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
namespace Cake\Queue\Mailer;

use Cake\Mailer\Exception\MissingActionException;
use Queue\Job\MailerJob;
use Queue\QueueManager;

/**
 * Provides functionality for queuing actions from mailer classes.
 */
trait QueueTrait
{
    /**
     * Pushes a mailer action onto the queue.
     *
     * @param string $action The name of the mailer action to trigger.
     * @param array $args Arguments to pass to the triggered mailer action.
     * @param array $headers Headers to set.
     * @param array $options an array of options for publishing the job
     * @return void
     * @throws \Cake\Mailer\Exception\MissingActionException
     */
    public function push(string $action, array $args = [], array $headers = [], array $options = []): void
    {
        if (!method_exists($this, $action)) {
            throw new MissingActionException([
                'mailer' => $this->getName() . 'Mailer',
                'action' => $action,
            ]);
        }

        QueueManager::push([MailerJob::class, 'execute'], [
            'mailerConfig' => $options['mailerConfig'] ?? null,
            'mailerName' => self::class,
            'action' => $action,
            'args' => $args,
            'headers' => $headers,
        ], $options);
    }
}
