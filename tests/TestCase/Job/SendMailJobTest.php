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

use Cake\Mailer\Transport\DebugTransport;
use Cake\Queue\Job\Message;
use Cake\Queue\Job\SendMailJob;
use Cake\Queue\Queue\Processor;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;

class SendMailJobTest extends TestCase
{
    /**
     * @var \Cake\Queue\Job\SendMailJob
     */
    protected $job;

    /**
     * @var \Cake\Mailer\Message
     */
    protected $message;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->job = new SendMailJob();
        $this->message = (new \Cake\Mailer\Message())
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
        $message = $this->createMessage(DebugTransport::class, [], $this->message);
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

    /**
     * Create a simple message for testing.
     *
     * @return \Cake\Queue\Job\Message
     */
    protected function createMessage($transport, $config, $emailMessage): Message
    {
        $messageBody = [
            'class' => ['Queue\\Job\\SendMailJob', 'execute'],
            'data' => [
                'transport' => $transport,
                'config' => $config,
                'emailMessage' => serialize($emailMessage),

            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($originalMessage, $context);

        return $message;
    }
}
