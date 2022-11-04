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
 * @since         0.1.9
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Job;

use Cake\Log\Log;
use Cake\Mailer\AbstractTransport;
use Cake\Queue\Queue\Processor;

/**
 * SendMailJob class to be used by QueueTransport to enqueue emails
 */
class SendMailJob implements JobInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Message $message): ?string
    {
        $result = false;
        try {
            $transportClassName = $message->getArgument('transport');
            $config = $message->getArgument('config', []);
            /** @var \Cake\Mailer\AbstractTransport $transport */
            $transport = $this->getTransport($transportClassName, $config);

            $emailMessage = unserialize($message->getArgument('emailMessage'));
            $result = $transport->send($emailMessage);
        } catch (\Exception $e) {
            Log::error(sprintf('An error has occurred processing message: %s', $e->getMessage()));
        } finally {
            if (!$result) {
                return Processor::REJECT;
            }
        }

        return Processor::ACK;
    }

    /**
     * Initialize transport
     *
     * @param string $transportClassName Transport class name
     * @param array $config Transport config
     * @return \Cake\Mailer\AbstractTransport
     * @throws \InvalidArgumentException if empty transport class name, class does not exist or send method is not defined for class
     */
    protected function getTransport(string $transportClassName, array $config): AbstractTransport
    {
        if (
            empty($transportClassName) ||
            !class_exists($transportClassName) ||
            !method_exists($transportClassName, 'send')
        ) {
            throw new \InvalidArgumentException(sprintf('Transport class name is not valid: %s', $transportClassName));
        }

        $transport = new $transportClassName($config);

        if (!($transport instanceof AbstractTransport)) {
            throw new \InvalidArgumentException('Provided class does not extend AbstractTransport.');
        }

        return $transport;
    }
}
