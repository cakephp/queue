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
namespace Queue\Test\TestCase\Consumption;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventList;
use Cake\Log\Log;
use Cake\Queue\Consumption\QueueExtension;
use Cake\Queue\Job\Message;
use Cake\Queue\QueueManager;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Enqueue\Consumption\Context\PostMessageReceived;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullContext;
use Enqueue\Null\NullMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use TestApp\WelcomeMailer;
use TestApp\WelcomeMailerListener;

/**
 * Class QueueExtensionTest
 *
 * @package Queue\Test\TestCase\Consumption
 */
class QueueExtensionTest extends TestCase
{

    /**
     * Test onPostMessageReceived would return void when result has a reason
     */
    public function testOnPostMessageReceivedShouldReturnVoidIfResultReason(): void
    {
        $logger = new NullLogger();
        $queueExtension = new QueueExtension(5, 5, $logger);
        $result = new Result(1, 'the reason is you');
        $postMessageReceived = $this->getDummyPostMessageReceived($logger, $result);
        $this->assertNull($queueExtension->onPostMessageReceived($postMessageReceived));
    }

    /**
     * Test processing interrupted by maxRuntime
     */
    public function testOnPostMessageReceivedShouldInterruptIfMaxRuntimeReached(): void
    {
        $logger = new TestLogger();
        $queueExtension = new QueueExtension(5, 1, $logger);
        sleep(1);
        $queueExtension->getEventManager()->setEventList(new EventList());
        $queueExtension->onPostMessageReceived($this->getDummyPostMessageReceived($logger));
        $this->assertEventFired('Processor.maxRuntime', $queueExtension->getEventManager());
        $this->assertTrue($logger->hasDebugThatContains('Max runtime reached, exiting'));
    }

    /**
     * Test processing interrupted by maxIterations
     */
    public function testOnPostMessageReceivedShouldInterruptIfMaxIterationsReached(): void
    {
        $logger = new TestLogger();
        $queueExtension = new QueueExtension(1, 0, $logger);
        $queueExtension->getEventManager()->setEventList(new EventList());
        $this->assertNull($queueExtension->onPostMessageReceived($this->getDummyPostMessageReceived($logger)));
        $this->assertEventFired('Processor.maxIterations', $queueExtension->getEventManager());
        $this->assertTrue($logger->hasDebugThatContains('Max iterations reached, exiting'));
    }

    protected function getDummyPostMessageReceived(LoggerInterface $logger, ?Result $result = null): PostMessageReceived
    {
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();
        $message = new NullMessage();

        return new PostMessageReceived(
            $context,
            $context->createConsumer($context->createQueue('test')),
            $message,
            $result,
            0,
            $logger
        );
    }
}
