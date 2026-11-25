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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Test\TestCase\Client;

use Cake\Queue\Client\EnqueuePostSendExtension;
use Cake\TestSuite\TestCase;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\Message;
use Enqueue\Client\PostSend;
use Enqueue\Client\ProducerInterface;
use Interop\Queue\Destination;
use Interop\Queue\Message as TransportMessage;

class EnqueuePostSendExtensionTest extends TestCase
{
    public function testInitialStateIsNull(): void
    {
        $extension = new EnqueuePostSendExtension();
        $this->assertNull($extension->getLastPostSend());
    }

    public function testOnPostSendStoresContext(): void
    {
        $extension = new EnqueuePostSendExtension();
        $postSend = $this->makePostSend();
        $extension->onPostSend($postSend);
        $this->assertSame($postSend, $extension->getLastPostSend());
    }

    public function testClearResetsState(): void
    {
        $extension = new EnqueuePostSendExtension();
        $extension->onPostSend($this->makePostSend());
        $extension->clear();
        $this->assertNull($extension->getLastPostSend());
    }

    /**
     * @return \Enqueue\Client\PostSend
     */
    protected function makePostSend(): PostSend
    {
        $message = new Message('body');
        $message->setMessageId('test-id');

        /** @var \Enqueue\Client\ProducerInterface $producer */
        $producer = $this->createStub(ProducerInterface::class);
        /** @var \Enqueue\Client\DriverInterface $driver */
        $driver = $this->createStub(DriverInterface::class);
        /** @var \Interop\Queue\Destination $destination */
        $destination = $this->createStub(Destination::class);
        /** @var \Interop\Queue\Message $transportMessage */
        $transportMessage = $this->createStub(TransportMessage::class);

        return new PostSend($message, $producer, $driver, $destination, $transportMessage);
    }
}
