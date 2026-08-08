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
 * @since         2.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Test\TestCase\Dto;

use ArgumentCountError;
use Cake\Queue\Dto\DtoManager;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use TestApp\Dto\InvalidDto;
use TestApp\Dto\JsonSerializableDto;
use TestApp\Dto\OrderDto;
use TestApp\Dto\OrderItemDto;
use TestApp\Dto\ScalarJsonSerializableDto;
use TestApp\Dto\UserDto;

class DtoManagerTest extends TestCase
{
    /**
     * Test that plain arrays pass through unchanged.
     *
     * @return void
     */
    public function testSerializeArray()
    {
        $data = ['id' => 1, 'nested' => ['a' => 'b']];

        $this->assertSame($data, DtoManager::serialize($data));
    }

    /**
     * Test that a plain object is serialized from its public properties.
     *
     * @return void
     */
    public function testSerializeObject()
    {
        $object = new class (1, 'Acme') {
            public function __construct(
                public int $id,
                public string $name,
            ) {
            }
        };

        $this->assertSame(['id' => 1, 'name' => 'Acme'], DtoManager::serialize($object));
    }

    /**
     * Test that JsonSerializable DTOs use their jsonSerialize() output.
     *
     * @return void
     */
    public function testSerializeJsonSerializable()
    {
        $dto = new JsonSerializableDto(1, 'acme');

        $this->assertSame(['id' => 1, 'label' => 'ACME'], DtoManager::serialize($dto));
    }

    /**
     * Test that a JsonSerializable DTO returning a non-array/non-object value
     * from jsonSerialize() throws a clear exception instead of an unrelated
     * TypeError from get_object_vars().
     *
     * @return void
     */
    public function testSerializeJsonSerializableReturningScalarThrows()
    {
        $dto = new ScalarJsonSerializableDto('not-an-array');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be serialized into an array');

        DtoManager::serialize($dto);
    }

    /**
     * Test that nested objects are recursively converted to arrays.
     *
     * @return void
     */
    public function testSerializeNestedObjects()
    {
        $dto = new OrderDto(7, 'Acme', [new OrderItemDto('SKU-1', 2)]);

        $this->assertSame([
            'id' => 7,
            'customer' => 'Acme',
            'items' => [
                ['sku' => 'SKU-1', 'quantity' => 2],
            ],
        ], DtoManager::serialize($dto));
    }

    /**
     * Test hydration using a createFromArray() factory method.
     *
     * @return void
     */
    public function testDeserializeWithCreateFromArray()
    {
        $dto = DtoManager::deserialize([
            'id' => 3,
            'username' => 'markstory',
        ], UserDto::class);

        $this->assertInstanceOf(UserDto::class, $dto);
        $this->assertSame(3, $dto->id);
        $this->assertSame('markstory', $dto->username);
    }

    /**
     * Test hydration of a plain DTO using constructor reflection.
     *
     * @return void
     */
    public function testDeserializeWithReflection()
    {
        $dto = DtoManager::deserialize([
            'id' => 7,
            'customer' => 'Acme',
            'items' => [
                ['sku' => 'SKU-1', 'quantity' => 2],
            ],
        ], OrderDto::class);

        $this->assertInstanceOf(OrderDto::class, $dto);
        $this->assertSame(7, $dto->id);
        $this->assertSame('Acme', $dto->customer);
        $this->assertCount(1, $dto->items);
        $this->assertInstanceOf(OrderItemDto::class, $dto->items[0]);
        $this->assertSame('SKU-1', $dto->items[0]->sku);
        $this->assertSame(2, $dto->items[0]->quantity);
    }

    /**
     * Test that a non-existent DTO class throws.
     *
     * @return void
     */
    public function testDeserializeNonExistentClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        DtoManager::deserialize(['id' => 1], 'TestApp\Dto\DoesNotExist');
    }

    /**
     * Test that a DTO with no factory and an incompatible constructor throws.
     *
     * @return void
     */
    public function testDeserializeIncompatibleDto()
    {
        $this->expectException(ArgumentCountError::class);

        DtoManager::deserialize(['id' => 1], InvalidDto::class);
    }
}
