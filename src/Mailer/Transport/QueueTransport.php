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
namespace Cake\Queue\Mailer\Transport;

use Cake\Mailer\Message;
use Cake\Mailer\Transport\MailTransport;
use Cake\Queue\Job\SendMailJob;
use Cake\Queue\QueueManager;

class QueueTransport extends \Cake\Mailer\AbstractTransport
{
    /**
     * Default config for this class
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [
        'options' => [],
        'transport' => MailTransport::class,
    ];

    /**
     * @inheritDoc
     */
    public function send(Message $message): array
    {
        $data = $this->prepareData($message);
        $options = $this->getConfig('options');
        $this->enqueueJob($data, $options);

        $headers = $message->getHeadersString(
            [
                'from',
                'to',
                'subject',
                'sender',
                'replyTo',
                'readReceipt',
                'returnPath',
                'cc',
                'bcc',
            ]
        );

        return ['headers' => $headers, 'message' => 'Message has been enqueued'];
    }

    /**
     * Add job to queue
     *
     * @param array $data Data to be sent to job
     * @param array $options Job options
     * @return void
     */
    protected function enqueueJob(array $data, array $options): void
    {
        QueueManager::push(
            [SendMailJob::class, 'execute'],
            $data,
            $options
        );
    }

    /**
     * Prepare data for job
     *
     * @param \Cake\Mailer\Message $message Email message
     * @return array
     */
    protected function prepareData(Message $message): array
    {
        return [
            'transport' => $this->getConfig('transport'),
            'config' => $this->getConfig(),
            'emailMessage' => json_encode($message),
        ];
    }
}
