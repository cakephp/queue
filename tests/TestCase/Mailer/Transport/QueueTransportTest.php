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
namespace Cake\Queue\Test\TestCase\Job;

use Cake\Queue\Mailer\Transport\QueueTransport;
use Cake\TestSuite\TestCase;

class QueueTransportTest extends TestCase
{
    /**
     * Test send
     *
     * @return void
     */
    public function testSend()
    {
        $message = (new \Cake\Mailer\Message())
            ->setFrom('from@example.com')
            ->setTo('to@example.com')
            ->setSubject('Sample Subject');

        $transport = $this
            ->getMockBuilder(QueueTransport::class)->onlyMethods(['enqueueJob'])
            ->getMock();
        $expectedData = [
            'transport' => 'Cake\Mailer\Transport\MailTransport',
            'config' => [
                'options' => [],
                'transport' => 'Cake\Mailer\Transport\MailTransport',
            ],
            'emailMessage' => serialize($message),
        ];
        $expectedOptions = [];
        $transport->expects($this->once())
            ->method('enqueueJob')
            ->with($expectedData, $expectedOptions);
        $result = $transport->send($message);

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

        $expected = ['headers' => $headers, 'message' => 'Message has been enqueued'];
        $this->assertEquals($expected, $result);
    }
}
