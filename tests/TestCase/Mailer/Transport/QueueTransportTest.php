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

use Cake\Mailer\Message;
use Cake\Mailer\Transport\SmtpTransport;
use Cake\Queue\Mailer\Transport\QueueTransport;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;

class QueueTransportTest extends TestCase
{
    private $fsQueuePath = TMP . DS . 'queue';

    private function getFsQueueUrl(): string
    {
        return 'file:///' . $this->fsQueuePath;
    }

    private function getFsQueueFile(): string
    {
        return $this->getFsQueueUrl() . DS . 'enqueue.app.default';
    }

    /**
     * Test send
     *
     * @return void
     */
    public function testSend()
    {
        QueueManager::setConfig('default', [
            'queue' => 'default',
            'url' => $this->getFsQueueUrl(),
        ]);
        $message = (new Message())
            ->setFrom('from@example.com')
            ->setTo('to@example.com')
            ->setSubject('Sample Subject');

        $transport = new QueueTransport();

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

        $fsQueueFile = $this->getFsQueueFile();
        $this->assertFileExists($fsQueueFile);

        $content = file_get_contents($fsQueueFile);
        $this->assertStringContainsString('MailTransport', $content);

        QueueManager::drop('default');
    }

    /**
     * Test send custom transport
     *
     * @return void
     */
    public function testSendCustomTransport()
    {
        QueueManager::setConfig('default', [
            'queue' => 'default',
            'url' => $this->getFsQueueUrl(),
        ]);
        $message = (new Message());

        $transport = new QueueTransport([
            'transport' => SmtpTransport::class,
            'config' => [
                'host' => 'mail.example.com',
            ],
        ]);
        $transport->send($message);

        $fsQueueFile = $this->getFsQueueFile();
        $this->assertFileExists($fsQueueFile);

        $content = file_get_contents($fsQueueFile);
        $this->assertStringContainsString('SmtpTransport', $content);
        $this->assertStringContainsString('"config\":{\"host\":\"mail.example.com\"}', $content);

        QueueManager::drop('default');
    }

    /**
     * Test send backwards compatibility transport config
     *
     * @return void
     */
    public function testSendBcTransport()
    {
        QueueManager::setConfig('default', [
            'queue' => 'default',
            'url' => $this->getFsQueueUrl(),
        ]);
        $message = (new Message());

        $transport = new QueueTransport([
            'transport' => SmtpTransport::class,
            'host' => 'mail.example.com',
        ]);
        $transport->send($message);

        $fsQueueFile = $this->getFsQueueFile();
        $this->assertFileExists($fsQueueFile);

        $content = file_get_contents($fsQueueFile);
        $this->assertStringContainsString('SmtpTransport', $content);
        $this->assertStringContainsString('"host\":\"mail.example.com\"', $content);

        QueueManager::drop('default');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $fsQueueFile = $this->getFsQueueFile();
        if (file_exists($fsQueueFile)) {
            unlink($fsQueueFile);
        }
    }
}
