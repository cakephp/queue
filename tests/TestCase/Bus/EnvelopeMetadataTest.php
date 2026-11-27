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

namespace Cake\Queue\Test\TestCase\Bus;

use ArrayObject;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use InvalidArgumentException;
use stdClass;
use TestApp\Bus\TenantStamp;
use TestApp\Bus\TestMessage;

class EnvelopeMetadataTest extends TestCase
{
    public function testToMetadataArrayWritesOnlyStamps(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [
            new TenantStamp('tenant:acme'),
        ]);

        $this->assertSame(
            [
                'stamps' => [
                    ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
                ],
            ],
            $envelope->toMetadataArray(),
        );
    }

    public function testToMetadataArraySkipsReceivedStamp(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [
            new ReceivedStamp('default'),
            new TenantStamp('tenant:acme'),
        ]);

        $this->assertSame(
            [
                'stamps' => [
                    ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
                ],
            ],
            $envelope->toMetadataArray(),
        );
    }

    public function testToMetadataArraySkipsNonSendableStamps(): void
    {
        $envelope = new Envelope(new TestMessage('hello'), [
            new HandledStamp('ack'),
            new JobMessageStamp($this->jobMessage()),
            new TenantStamp('tenant:acme'),
        ]);

        $metadata = $envelope->toMetadataArray();

        $this->assertSame(
            [['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme']],
            $metadata['stamps'],
        );
    }

    public function testToMetadataArrayEmpty(): void
    {
        $this->assertSame([], (new Envelope(new TestMessage('hello')))->toMetadataArray());
    }

    public function testFromTransportBodyDropsReceivedStamp(): void
    {
        $body = [
            'data' => ['userId' => 42, 'locale' => 'fr'],
            'dtoClass' => 'App\\Command\\SendWelcomeEmailCommand',
            'metadata' => [
                'tags' => ['App\\Job\\SendEmailJob', 'tenant:acme'],
                '_uniqueId' => 'uid-1',
                'stamps' => [
                    ['type' => 'ReceivedStamp', 'v' => 1, 'transport' => 'emails'],
                ],
            ],
        ];

        $envelope = Envelope::fromTransportBody($body);

        $this->assertEquals(new ArrayObject(['userId' => 42, 'locale' => 'fr']), $envelope->getMessage());
        $this->assertNull($envelope->getLastStamp(ReceivedStamp::class));
        $this->assertSame([], $envelope->getStamps());
    }

    public function testFromTransportBodyIgnoresFlatKeys(): void
    {
        $body = [
            'data' => ['id' => 1],
            'metadata' => [
                'tags' => ['App\\Job\\ExampleJob'],
                '_uniqueId' => 'uid-1',
            ],
        ];

        $envelope = Envelope::fromTransportBody($body);

        $this->assertEquals(new ArrayObject(['id' => 1]), $envelope->getMessage());
        $this->assertSame([], $envelope->getStamps());
    }

    public function testFromTransportBodySkipsUnknownTypes(): void
    {
        $body = [
            'data' => ['id' => 1],
            'metadata' => [
                'stamps' => [
                    ['type' => 'StampFromNewerDeploy', 'v' => 3, 'custom' => true],
                    ['type' => 'ReceivedStamp', 'v' => 1, 'transport' => 'kept'],
                ],
            ],
        ];

        $envelope = Envelope::fromTransportBody($body);

        $this->assertSame([], $envelope->getStamps());
    }

    public function testFromTransportBodySkipsCorruptRows(): void
    {
        $body = [
            'data' => ['id' => 1],
            'metadata' => [
                'stamps' => [
                    'not an array',
                    ['no type key' => true],
                    ['type' => 42],
                    ['type' => 'ReceivedStamp', 'transport' => 42],
                ],
            ],
        ];

        $envelope = Envelope::fromTransportBody($body);

        $this->assertEquals(new ArrayObject(['id' => 1]), $envelope->getMessage());
        $this->assertSame([], $envelope->getStamps());
    }

    public function testFromTransportBodySkipsNonSendableTypes(): void
    {
        $body = [
            'data' => ['id' => 1],
            'metadata' => [
                'stamps' => [
                    ['type' => 'HandledStamp', 'v' => 1, 'result' => 'ack'],
                    ['type' => 'JobMessageStamp', 'v' => 1],
                    ['type' => 'ReceivedStamp', 'v' => 1, 'transport' => 'kept'],
                    ['type' => 'SentStamp', 'v' => 1, 'messageId' => 'fake'],
                ],
            ],
        ];

        $envelope = Envelope::fromTransportBody($body);

        $this->assertSame([], $envelope->getStamps());
    }

    public function testFromTransportBodyToleratesNonArraySections(): void
    {
        $envelope = Envelope::fromTransportBody([
            'data' => 'corrupt',
            'metadata' => 'corrupt',
        ]);

        $this->assertEquals(new ArrayObject([]), $envelope->getMessage());
        $this->assertSame([], $envelope->getStamps());
    }

    public function testCustomStampRoundTripThroughRegistry(): void
    {
        $types = ['TenantStamp' => TenantStamp::class];

        $envelope = Envelope::fromTransportBody([
            'data' => ['id' => 1],
            'metadata' => [
                'stamps' => [
                    ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
                ],
            ],
        ], $types);

        $stamps = $envelope->getStamp(TenantStamp::class);
        $this->assertCount(1, $stamps);
        $this->assertSame('tenant:acme', $stamps[0]->getTenant());
    }

    public function testCustomStampSkippedWhenUnregistered(): void
    {
        $envelope = Envelope::fromTransportBody([
            'data' => ['id' => 1],
            'metadata' => [
                'stamps' => [
                    ['type' => 'TenantStamp', 'v' => 1, 'tenant' => 'tenant:acme'],
                ],
            ],
        ]);

        $this->assertSame([], $envelope->getStamps());
    }

    public function testResolveStampTypesRejectsCollision(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Envelope::resolveStampTypes(['ReceivedStamp' => TenantStamp::class]);
    }

    public function testResolveStampTypesRejectsNonStampClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore argument.type
        Envelope::resolveStampTypes(['Whatever' => stdClass::class]);
    }

    public function testResolveStampTypesRejectsNonSendableTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('never leaves the process');

        Envelope::resolveStampTypes(['MyStamp' => SentStamp::class]);
    }

    public function testResolveStampTypesIsIdempotent(): void
    {
        $merged = Envelope::resolveStampTypes(['TenantStamp' => TenantStamp::class]);

        $this->assertSame($merged, Envelope::resolveStampTypes($merged));
    }

    public function testResolveStampTypesRejectsMissingClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('App\\Missing\\Nope');

        // @phpstan-ignore argument.type
        Envelope::resolveStampTypes(['Whatever' => 'App\\Missing\\Nope']);
    }

    public function testDataByteIdenticalBeforeAfter(): void
    {
        $data = ['userId' => 42, 'nested' => ['a' => [1, 2, 3], 'b' => true, 'c' => null]];
        $expected = (string)json_encode($data);

        $envelope = new Envelope(new ArrayObject($data), [
            new ReceivedStamp('default'),
            new TenantStamp('tenant:acme'),
        ]);
        $body = ['data' => $data, 'metadata' => $envelope->toMetadataArray()];

        $parsed = Envelope::fromTransportBody($body, ['TenantStamp' => TenantStamp::class]);

        $parsedMessage = $parsed->getMessage();
        $this->assertInstanceOf(ArrayObject::class, $parsedMessage);
        $this->assertSame($expected, (string)json_encode($parsedMessage->getArrayCopy()));
    }

    protected function jobMessage(): Message
    {
        $context = (new NullConnectionFactory())->createContext();
        $body = (string)json_encode([
            'class' => ['App\\Job\\ExampleJob', 'execute'],
            'data' => ['id' => 1],
        ]);

        return new Message(new NullMessage($body), $context);
    }
}
