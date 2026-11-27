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

use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\MessageBus;
use Cake\Queue\Bus\Middleware\SendMiddleware;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Test\TestCase\QueueTestTrait;
use Cake\Queue\TestSuite\QueueTrait;
use Cake\TestSuite\TestCase;
use LogicException;
use RuntimeException;
use stdClass;
use TestApp\Bus\TenantStamp;
use TestApp\Job\LogToDebugJob;

class SendMiddlewareTest extends TestCase
{
    use QueueTestTrait;
    use QueueTrait;

    public function testSendsDispatchAndAttachesSentStamp(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);
        $finished = $bus->handle(new Envelope($this->dispatch()));

        $sent = $finished->getLastStamp(SentStamp::class);
        $this->assertNotNull($sent);
    }

    public function testBodyCarriesStamps(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);
        $bus->handle(new Envelope(
            $this->dispatch(),
            [new TenantStamp('tenant:acme')],
        ));

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $this->assertCount(1, $jobs);
        $metadata = $jobs[0]['metadata'];
        $this->assertSame(
            [
                ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
            ],
            $metadata['stamps'],
        );
    }

    public function testTransportOptionsApplied(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);
        $bus->handle(new Envelope(new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 1],
            null,
            'default',
            'default',
            30,
            3600,
            'high',
        )));

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $this->assertCount(1, $jobs);
        $this->assertSame(30, $jobs[0]['options']['delay']);
        $this->assertSame(3600, $jobs[0]['options']['expires']);
        $this->assertSame('high', $jobs[0]['options']['priority']);
    }

    public function testLegacyMetadataPassesThroughVerbatim(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);
        $bus->handle(new Envelope(
            $this->dispatch(['custom' => 'kept', 'tags' => ['flat-tag']]),
            [new TenantStamp('tenant:acme')],
        ));

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $metadata = $jobs[0]['metadata'];
        $this->assertSame('kept', $metadata['custom']);
        $this->assertSame(['flat-tag'], $metadata['tags']);
        $this->assertSame(
            [['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme']],
            $metadata['stamps'],
        );
    }

    public function testNoMetadataKeyWhenEmpty(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);
        $bus->handle(new Envelope($this->dispatch()));

        $jobs = $this->getQueuedJobsByClass(LogToDebugJob::class);
        $this->assertSame([], $jobs[0]['metadata']);
    }

    public function testReceivedEnvelopesAreNotResent(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);
        $envelope = (new Envelope($this->dispatch()))->withStamp(new ReceivedStamp('default'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('refuses envelopes stamped');

        try {
            $bus->handle($envelope);
            $this->fail('SendMiddleware must refuse envelopes stamped ReceivedStamp.');
        } finally {
            $this->assertCount(0, $this->getQueuedJobsByClass(LogToDebugJob::class));
        }
    }

    public function testRequiresDispatchMessage(): void
    {
        $bus = new MessageBus([new SendMiddleware()]);

        $this->expectException(RuntimeException::class);

        $bus->handle(new Envelope(new stdClass()));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    protected function dispatch(array $metadata = []): Dispatch
    {
        return new Dispatch(
            LogToDebugJob::class,
            'execute',
            ['id' => 1],
            null,
            'default',
            'default',
            null,
            null,
            null,
            $metadata,
        );
    }
}
