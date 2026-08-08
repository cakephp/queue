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
namespace Cake\Queue\Dto;

use Cake\ORM\ResultSetFactory;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Serializes DTO objects for queue transport and hydrates them back on the
 * receiving side.
 *
 * Hydration reuses the CakePHP 5.4 DTO support via `ResultSetFactory::getDtoHydrator()`,
 * which handles both a static `createFromArray($data, $nested)` factory method
 * (cakephp-dto style) and plain DTOs mapped through `Cake\ORM\DtoMapper` (constructor
 * parameters, nested DTO type-hints and the `#[CollectionOf]` attribute).
 */
class DtoManager
{
    /**
     * Serialize a DTO (or array) into an array suitable for queue transport.
     *
     * Nested objects are converted to arrays so the resulting data only contains
     * JSON-encodable scalars.
     *
     * @param array<string, mixed>|object $data Data or DTO object to serialize.
     * @return array<string, mixed> Serialized data.
     */
    public static function serialize(array|object $data): array
    {
        if ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException(
                'DTO data could not be serialized into an array. `jsonSerialize()` must return an array or object.',
            );
        }

        return self::toScalarArray($data);
    }

    /**
     * Hydrate queue data back into a DTO instance.
     *
     * @param array<string, mixed> $data Serialized data.
     * @param class-string $dtoClass DTO class name.
     * @return object Hydrated DTO instance.
     * @throws \InvalidArgumentException When the DTO class does not exist.
     */
    public static function deserialize(array $data, string $dtoClass): object
    {
        if (!class_exists($dtoClass)) {
            throw new InvalidArgumentException(sprintf('DTO class `%s` does not exist.', $dtoClass));
        }

        return (new ResultSetFactory())->hydrateDto($data, $dtoClass);
    }

    /**
     * Recursively convert any nested objects into arrays.
     *
     * @param array<string, mixed> $data The data to convert.
     * @return array<string, mixed> The converted data.
     */
    protected static function toScalarArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $value = $value instanceof JsonSerializable
                    ? $value->jsonSerialize()
                    : get_object_vars($value);
            }

            if (is_array($value)) {
                $data[$key] = self::toScalarArray($value);
            }
        }

        return $data;
    }
}
