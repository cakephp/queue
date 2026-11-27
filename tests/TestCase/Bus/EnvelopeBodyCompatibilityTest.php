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
use Cake\Queue\Dto\DtoManager;
use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Context;
use TestApp\Bus\TenantStamp;
use TestApp\Dto\OrderDto;
use TestApp\Dto\UserDto;
use TestApp\Job\LogToDebugJob;

class EnvelopeBodyCompatibilityTest extends TestCase
{
    public function testArrayPayloadRoundTrip(): void
    {
        $data = ['id' => 1];

        $body = $this->dispatchBody([LogToDebugJob::class, 'execute'], $data, [
            new TenantStamp('tenant:acme'),
        ]);

        $envelope = Envelope::fromTransportBody($body, ['TenantStamp' => TenantStamp::class]);
        $this->assertEquals(new ArrayObject($data), $envelope->getMessage());
        $this->assertSame('tenant:acme', $envelope->getStamp(TenantStamp::class)[0]->getTenant());

        $message = $this->legacyMessage($body);
        $this->assertSame([LogToDebugJob::class, 'execute'], $message->getTarget());
        $this->assertSame($data, $message->getArgument());
    }

    public function testDtoPayloadRoundTrip(): void
    {
        $data = DtoManager::serialize(new OrderDto(7, 'Acme Corp'));

        $body = $this->dispatchBody([LogToDebugJob::class, 'execute'], $data, []);
        $body['dtoClass'] = OrderDto::class;

        $envelope = Envelope::fromTransportBody($body);
        $this->assertEquals(new ArrayObject($data), $envelope->getMessage());

        $message = $this->legacyMessage($body);
        $dto = $message->getDto(OrderDto::class);
        $this->assertInstanceOf(OrderDto::class, $dto);
        $this->assertSame(7, $dto->id);
        $this->assertSame('Acme Corp', $dto->customer);
        $this->assertSame(OrderDto::class, $message->getDtoClass());
    }

    public function testCommandPayloadRoundTrip(): void
    {
        $data = DtoManager::serialize(new UserDto(42, 'amy'));

        $body = $this->dispatchBody([LogToDebugJob::class, 'execute'], $data, [
            new TenantStamp('tenant:acme'),
        ]);
        $body['dtoClass'] = UserDto::class;
        $body['metadata']['tags'] = [LogToDebugJob::class, 'tenant:acme'];

        $envelope = Envelope::fromTransportBody($body, ['TenantStamp' => TenantStamp::class]);
        $this->assertEquals(new ArrayObject($data), $envelope->getMessage());

        $message = $this->legacyMessage($body);
        $command = $message->getDto(UserDto::class);
        $this->assertInstanceOf(UserDto::class, $command);
        $this->assertSame(42, $command->id);
        $this->assertSame('amy', $command->username);
        $this->assertSame(
            [LogToDebugJob::class, 'tenant:acme'],
            $message->getMetadata()['tags'],
        );
    }

    public function testOldBodyToNewEnvelope(): void
    {
        $body = [
            'class' => [LogToDebugJob::class, 'execute'],
            'args' => [['id' => 1]],
            'data' => ['id' => 1],
            'metadata' => [
                'tags' => [LogToDebugJob::class],
                '_uniqueId' => 'uid-1',
            ],
        ];

        $envelope = Envelope::fromTransportBody($body);

        $this->assertEquals(new ArrayObject(['id' => 1]), $envelope->getMessage());
        $this->assertSame([], $envelope->getStamps());
    }

    public function testNewBodyReadByLegacyAccessors(): void
    {
        $body = $this->dispatchBody([LogToDebugJob::class, 'execute'], ['id' => 1], [
            new TenantStamp('tenant:acme'),
        ]);

        $message = $this->legacyMessage($body);

        $this->assertSame([LogToDebugJob::class, 'execute'], $message->getTarget());
        $this->assertSame(['id' => 1], $message->getArgument());
        $this->assertNotEmpty($message->getMetadata()['stamps']);
    }

    /**
     * @param array{class-string, string} $target
     * @param array<string, mixed> $data
     * @param array<\Cake\Queue\Bus\StampInterface> $stamps
     * @return array<string, mixed>
     */
    protected function dispatchBody(array $target, array $data, array $stamps): array
    {
        $envelope = new Envelope(new ArrayObject($data), $stamps);

        return [
            'class' => $target,
            'args' => [$data],
            'data' => $data,
            'requeueOptions' => [
                'config' => 'default',
                'priority' => null,
                'queue' => 'default',
            ],
            'metadata' => $envelope->toMetadataArray(),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function legacyMessage(array $body): Message
    {
        $context = $this->context();

        return new Message(new NullMessage((string)json_encode($body)), $context);
    }

    protected function context(): Context
    {
        return (new NullConnectionFactory())->createContext();
    }
}
