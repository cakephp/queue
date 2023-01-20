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

use Cake\Mailer\Mailer;
use Cake\Mailer\Message as CakeMessage;
use Cake\Mailer\Transport\DebugTransport;
use Cake\Queue\Job\Message as QueueJobMessage;
use Cake\Queue\Job\SendMailJob;
use Cake\Queue\Queue\Processor;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;

class SendMailJobTest extends TestCase
{
    protected SendMailJob $job;

    protected CakeMessage $message;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->job = new SendMailJob();
        $this->message = (new CakeMessage())
            ->setFrom('from@example.com')
            ->setTo('to@example.com')
            ->setSubject('Sample Subject');
    }

    /**
     * Test execute method
     *
     * @return void
     */
    public function testExecute()
    {
        $job = $this->getMockBuilder(SendMailJob::class)
            ->onlyMethods(['getTransport'])
            ->getMock();
        $message = $this->createMessage(DebugTransport::class, [], $this->message);
        $emailMessage = new CakeMessage();
        $data = json_decode($message->getArgument('emailMessage'), true);
        $emailMessage->createFromArray($data);
        $transport = $this->getMockBuilder(DebugTransport::class)->getMock();
        $transport->expects($this->once())
            ->method('send')
            ->with($emailMessage)
            ->willReturn(['message' => 'test', 'headers' => []]);
        $job->expects($this->once())
            ->method('getTransport')
            ->with(DebugTransport::class, [])
            ->willReturn($transport);
        $actual = $job->execute($message);
        $this->assertSame(Processor::ACK, $actual);
    }

    /**
     * Test execute with attachments method
     *
     * @return void
     */
    public function testExecuteWithAttachments()
    {
        $emailMessage = clone $this->message;
        $emailMessage->addAttachments(['test.txt' => ROOT . 'files' . DS . 'test.txt']);
        $message = $this->createMessage(DebugTransport::class, [], $emailMessage);
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::ACK, $actual);
    }

    /**
     * Test execute method with invalid transport
     *
     * @return void
     */
    public function testExecuteInvalidTransport()
    {
        $message = $this->createMessage('WrongTransport', [], $this->message);
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
    }

    /**
     * Test execute method with unserializable message
     *
     * @return void
     */
    public function testExecuteUnserializableMessage()
    {
        $message = $this->createMessage(DebugTransport::class, [], 'unserializable');
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
    }

    public function testExecuteNoAbstractTransport()
    {
        $message = $this->createMessage(Mailer::class, [], $this->message);
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
    }

    /**
     * Create a simple message for testing.
     *
     * @return \Cake\Queue\Job\Message
     */
    protected function createMessage($transport, $config, $emailMessage): QueueJobMessage
    {
        $messageBody = [
            'class' => ['Queue\\Job\\SendMailJob', 'execute'],
            'data' => [
                'transport' => $transport,
                'config' => $config,
                'emailMessage' => json_encode($emailMessage),

            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage(json_encode($messageBody));

        return new QueueJobMessage($originalMessage, $context);
    }
}
