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
namespace Cake\Queue\Job;

use BadMethodCallException;
use Cake\Mailer\Exception\MissingMailerException;
use Cake\Mailer\MailerAwareTrait;
use Interop\Queue\Processor;

class MailerJob implements JobInterface
{
    use MailerAwareTrait;

    /**
     * Constructs and dispatches the event from a job message
     *
     * @param \Cake\Queue\Job\Message $message job message
     * @return string
     */
    public function execute(Message $message): ?string
    {
        $mailerName = $message->getArgument('mailerName');
        $mailerConfig = $message->getArgument('mailerConfig');
        $action = $message->getArgument('action');
        $args = $message->getArgument('args', []);
        $headers = $message->getArgument('headers', []);

        try {
            $mailer = $this->getMailer($mailerName, $mailerConfig);
        } catch (MissingMailerException $e) {
            return Processor::REJECT;
        }

        try {
            $mailer->send($action, $args, $headers);
        } catch (BadMethodCallException $e) {
            return Processor::REJECT;
        }

        return Processor::ACK;
    }
}
