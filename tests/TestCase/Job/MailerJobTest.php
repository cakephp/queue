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
namespace Queue\Test\TestCase\Job;

use Cake\Mailer\Exception\MissingMailerException;
use Cake\Mailer\Mailer;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Queue\Job\MailerJob;
use Queue\Job\Message;
use Queue\Queue\Processor;

class MailerJobTest extends TestCase
{
    /**
     * @var \Cake\Mailer\Mailer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $mailer;
    /**
     * @var \Queue\Job\MailerJob|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $job;
    protected $mailerConfig;
    protected $headers;
    protected $args;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->mailerConfig = ['from' => 'me@example.org', 'transport' => 'my_custom'];
        $this->headers = ['X-Mycustom' => 'a.value'];
        $this->args = ['username' => 'joe.doe', 'first_name' => 'Joe', 'last_name' => 'Doe'];

        $this->mailer = $this->getMockBuilder(Mailer::class)
            ->setMethods(['send'])
            ->getMock();

        $this->job = $this->getMockBuilder(MailerJob::class)
            ->setMethods(['getMailer'])
            ->getMock();
    }

    /**
     * Test execute method
     *
     * @return void
     */
    public function testExecute()
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo('welcome'),
                $this->equalTo($this->args),
                $this->equalTo($this->headers)
            )
            ->willReturn(['Message sent']);

        $this->job->expects($this->once())
            ->method('getMailer')
            ->with(
                $this->equalTo('SampleTest'),
                $this->equalTo($this->mailerConfig)
            )->willReturn($this->mailer);

        $message = $this->createMessage();
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::ACK, $actual);
    }

    /**
     * Test execute method when MissingMailerException is thrown
     *
     * @return void
     */
    public function testExecuteMissingMailerException()
    {
        $this->mailer->expects($this->never())
            ->method('send');

        $this->job->expects($this->once())
            ->method('getMailer')
            ->with(
                $this->equalTo('SampleTest'),
                $this->equalTo($this->mailerConfig)
            )->willThrowException(new MissingMailerException('Missing mailer for testExecuteMissingMailerException'));

        $message = $this->createMessage();
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
    }

    /**
     * Test execute method when BadMethodCallException is thrown
     *
     * @return void
     */
    public function testExecuteBadMethodCallException()
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo('welcome'),
                $this->equalTo($this->args),
                $this->equalTo($this->headers)
            )
            ->willThrowException(new \BadMethodCallException('Welcome is not a valid method'));

        $this->job->expects($this->once())
            ->method('getMailer')
            ->with(
                $this->equalTo('SampleTest'),
                $this->equalTo($this->mailerConfig)
            )->willReturn($this->mailer);

        $message = $this->createMessage();
        $actual = $this->job->execute($message);
        $this->assertSame(Processor::REJECT, $actual);
    }

    /**
     * Create a simple message for testing.
     *
     * @return \Queue\Job\Message
     */
    protected function createMessage(): Message
    {
        $messageBody = [
            "queue" => "default",
            "class" => ["Queue\\Job\\EventJob", "execute"],
            "args" => [
                [
                    "mailerName" => "SampleTest",
                    "mailerConfig" => $this->mailerConfig,
                    "action" => 'welcome',
                    "args" => $this->args,
                    'headers' => $this->headers,
                ],
            ],
        ];
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage(json_encode($messageBody));
        $message = new Message($originalMessage, $context);

        return $message;
    }
}
