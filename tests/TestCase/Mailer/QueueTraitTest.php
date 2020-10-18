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
namespace Queue\Test\TestCase\Mailer;

use Cake\Mailer\Exception\MissingActionException;
use Cake\Queue\Mailer\QueueTrait;
use Cake\Queue\QueueManager;
use Cake\TestSuite\TestCase;

class QueueTraitTest extends TestCase
{
    /**
     * Test that a MissingActionException is being thrown when
     * the push action is not found on the object with the QueueTrait
     *
     * @return @void
     */
    public function testQueueTraitTestThrowsMissingActionException()
    {
        $queue = $this->getMockForTrait(
            QueueTrait::class,
            [],
            'GenericMailer',
            true,
            true,
            true,
            ['getName']
        );

        try {
            $queue->push('nonExistentFunction');
        } catch (MissingActionException $e) {
            $this->assertInstanceOf(MissingActionException::class, $e);
        }
    }

    /**
     * Test that QueueTrait calls push
     *
     * @runInSeparateProcess
     * @return @void
     */
    public function testQueueTraitCallsPush()
    {
        $queue = $this->getMockForTrait(
            QueueTrait::class,
            [],
            'GenericMailer',
            true,
            true,
            true,
            ['getName']
        );

        QueueManager::setConfig('default', [
            'queue' => 'default',
            'url' => 'null:',
        ]);

        $this->assertEmpty($queue->push('push'));
    }
}
