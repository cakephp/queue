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

namespace Cake\Queue\Test\TestCase\Bus\Middleware;

use Cake\Log\Engine\ArrayLog;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\Queue\Bus\Middleware\LoggingMiddleware;
use Cake\Queue\Bus\PhaseEnum;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\TestSuite\TestCase;
use TestApp\Bus\TestMessage;

class LoggingMiddlewareTest extends TestCase
{
    public function testLogsDispatchPhase(): void
    {
        $logger = new ArrayLog();
        $bus = new MessageBus([new LoggingMiddleware($logger)]);

        $bus->handle(new Envelope(new TestMessage('hello')));

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString(PhaseEnum::Dispatch->value . ' phase', $logs[0]);
        $this->assertStringContainsString(TestMessage::class, $logs[0]);
    }

    public function testLogsConsumePhase(): void
    {
        $logger = new ArrayLog();
        $bus = new MessageBus([new LoggingMiddleware($logger)]);

        $envelope = (new Envelope(new TestMessage('hello')))
            ->withStamp(new ReceivedStamp('default'));
        $bus->handle($envelope);

        $logs = $logger->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString(PhaseEnum::Consume->value . ' phase', $logs[0]);
    }

    public function testPassesEnvelopeThroughUnchanged(): void
    {
        $bus = new MessageBus([new LoggingMiddleware()]);
        $envelope = new Envelope(new TestMessage('hello'));

        $this->assertSame($envelope, $bus->handle($envelope));
    }
}
