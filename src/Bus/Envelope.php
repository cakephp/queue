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
namespace Cake\Queue\Bus;

use ArrayObject;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\Stamp\SkippedAsDuplicateStamp;
use InvalidArgumentException;
use Throwable;

/**
 * Immutable message wrapper carrying the payload plus typed stamps.
 */
final class Envelope
{
    /**
     * Stamps grouped by their class name.
     *
     * @var array<class-string<\Cake\Queue\Bus\StampInterface>, list<\Cake\Queue\Bus\StampInterface>>
     */
    private array $stamps = [];

    /**
     * @param object $message Payload (job body array object, DTO, or any object).
     * @param array<\Cake\Queue\Bus\StampInterface> $stamps Initial stamps.
     */
    public function __construct(
        private object $message,
        array $stamps = [],
    ) {
        foreach ($stamps as $stamp) {
            if (!$stamp instanceof StampInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Envelope stamps must implement %s, `%s` given.',
                    StampInterface::class,
                    get_debug_type($stamp),
                ));
            }

            $this->stamps[$stamp::class][] = $stamp;
        }
    }

    /**
     * Ensure the value is an envelope and attach extra stamps.
     *
     * @param object $message Message or envelope.
     * @param array<\Cake\Queue\Bus\StampInterface> $stamps Stamps to add.
     * @return static
     */
    public static function wrap(object $message, array $stamps = []): static
    {
        $envelope = $message instanceof static ? $message : new self($message);

        if ($stamps === []) {
            return $envelope;
        }

        return $envelope->withStamp(...$stamps);
    }

    /**
     * Return a copy with extra stamps attached.
     *
     * Stamps are appended, never replaced: several stamps of the same
     * class may coexist, with {@see \Cake\Queue\Bus\Envelope::getLastStamp()}
     * resolving the most recent one.
     *
     * @param mixed ...$stamps Stamps to add. Every value must implement
     *   `StampInterface`; anything else throws, mirroring the constructor.
     * @return static
     * @throws \InvalidArgumentException When a value is not a stamp.
     */
    public function withStamp(mixed ...$stamps): static
    {
        $stamped = clone $this;
        foreach ($stamps as $index => $stamp) {
            if (!$stamp instanceof StampInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Envelope stamps must implement %s, `%s` given at index `%d`.',
                    StampInterface::class,
                    get_debug_type($stamp),
                    $index,
                ));
            }

            $stamped->stamps[$stamp::class][] = $stamp;
        }

        return $stamped;
    }

    /**
     * Return a copy without any stamps of the given class.
     *
     * @param class-string<\Cake\Queue\Bus\StampInterface> $stampClass Stamp class to remove.
     * @return static
     */
    public function withoutStamp(string $stampClass): static
    {
        self::assertStampClass($stampClass);

        $stamped = clone $this;
        unset($stamped->stamps[$stampClass]);

        return $stamped;
    }

    /**
     * Return a copy without stamps matching the given class or interface.
     *
     * @param class-string $type Class or interface to remove.
     * @return static
     */
    public function withoutStampOfType(string $type): static
    {
        if (!class_exists($type) && !interface_exists($type)) {
            throw new InvalidArgumentException(sprintf(
                'Stamp type `%s` must be an existing class or interface.',
                $type,
            ));
        }

        $stamped = clone $this;
        foreach ($stamped->stamps as $class => $list) {
            if ($class === $type || is_subclass_of($class, $type)) {
                unset($stamped->stamps[$class]);
            }
        }

        return $stamped;
    }

    /**
     * Get the last added stamp of the given class.
     *
     * @template TStamp of \Cake\Queue\Bus\StampInterface
     * @param class-string<TStamp> $stampClass Stamp class to look up.
     * @return TStamp|null
     */
    public function getLastStamp(string $stampClass): ?StampInterface
    {
        self::assertStampClass($stampClass);

        $list = $this->stamps[$stampClass] ?? [];
        if ($list === []) {
            return null;
        }

        /**
         * phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation.NonFullyQualifiedClassName
         * @var TStamp $last
         */
        $last = end($list);

        return $last;
    }

    /**
     * Get all stamps grouped by class.
     *
     * @return array<class-string<\Cake\Queue\Bus\StampInterface>, list<\Cake\Queue\Bus\StampInterface>>
     */
    public function getStamps(): array
    {
        return $this->stamps;
    }

    /**
     * Get stamps of the given class.
     *
     * @template TStamp of \Cake\Queue\Bus\StampInterface
     * @param class-string<TStamp> $stampClass Stamp class to look up.
     * @return list<TStamp>
     */
    public function getStamp(string $stampClass): array
    {
        self::assertStampClass($stampClass);

        /**
         * phpcs:ignore SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation.NonFullyQualifiedClassName
         * @var list<TStamp> $list
         */
        $list = $this->stamps[$stampClass] ?? [];

        return $list;
    }

    /**
     * Guard a stamp class-string lookup.
     *
     * @param class-string $stampClass Stamp class to look up.
     * @throws \InvalidArgumentException When the class does not implement `StampInterface`.
     */
    protected static function assertStampClass(string $stampClass): void
    {
        if (!is_subclass_of($stampClass, StampInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Stamp class `%s` must implement `%s`.',
                $stampClass,
                StampInterface::class,
            ));
        }
    }

    /**
     * Return a copy with a different message, keeping all stamps.
     *
     * Stamps are kept as is, including `JobMessageStamp`: replacing the
     * message of a consumed envelope leaves the old live message behind
     * in the stamp, which handlers keep reading. Swap that stamp yourself
     * when the new message needs its own context.
     *
     * @param object $message Message.
     * @return static
     */
    public function withMessage(object $message): static
    {
        $envelope = clone $this;
        $envelope->message = $message;

        return $envelope;
    }

    /**
     * Get the wrapped message.
     *
     * @return object
     */
    public function getMessage(): object
    {
        return $this->message;
    }

    /**
     * Core stamp types, keyed by their wire `type` string.
     *
     * Only machinery stamps live here, each one is produced or read by core
     * itself. App-defined stamps are registered per queue config (see
     * `BusFactory::stampTypes()`) and resolved through the merged map.
     *
     * The map doubles as a name reservation registry: entries marked
     * `NonSendableStampInterface` (`HandledStamp`, `JobMessageStamp`,
     * `ReceivedStamp`, `SentStamp`, `SkippedAsDuplicateStamp`) never travel — writers skip
     * them and the parser drops their rows — but their names stay reserved
     * so app-defined types cannot reuse them.
     *
     * @return array<string, class-string<\Cake\Queue\Bus\StampInterface>>
     */
    public static function coreStampTypes(): array
    {
        return [
            'ReceivedStamp' => ReceivedStamp::class,
            'HandledStamp' => HandledStamp::class,
            'JobMessageStamp' => JobMessageStamp::class,
            'SentStamp' => SentStamp::class,
            'SkippedAsDuplicateStamp' => SkippedAsDuplicateStamp::class,
        ];
    }

    /**
     * Merge app-defined stamp types over the core map.
     *
     * Idempotent: merging an already merged map is a no-op. Only a conflicting
     * redefinition (same `type`, different class) throws.
     *
     * @param array<string, class-string<\Cake\Queue\Bus\StampInterface>> $customTypes Short type to stamp class map.
     * @return array<string, class-string<\Cake\Queue\Bus\StampInterface>>
     * @throws \InvalidArgumentException When a key is not a string, a class does not exist,
     *   does not implement `StampInterface`, or collides with a core type.
     */
    public static function resolveStampTypes(array $customTypes): array
    {
        $coreTypes = self::coreStampTypes();
        foreach ($customTypes as $type => $class) {
            if (!is_string($type) || $type === '' || !is_string($class) || !class_exists($class)) {
                throw new InvalidArgumentException(sprintf(
                    'Stamp type `%s` must map to an existing class-string, `%s` given.',
                    is_string($type) ? $type : get_debug_type($type),
                    is_string($class) ? $class : get_debug_type($class),
                ));
            }

            $coreClass = $coreTypes[$type] ?? null;
            if ($coreClass !== $class && is_subclass_of($class, NonSendableStampInterface::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Stamp type `%s` must not map to in-memory `%s`: it never leaves the process.',
                    $type,
                    $class,
                ));
            }

            if (!is_subclass_of($class, StampInterface::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Stamp type `%s` must implement `%s`, `%s` given.',
                    $type,
                    StampInterface::class,
                    $class,
                ));
            }

            if (array_key_exists($type, $coreTypes) && $coreTypes[$type] !== $class) {
                throw new InvalidArgumentException(sprintf(
                    'Stamp type `%s` collides with a core stamp type.',
                    $type,
                ));
            }
        }

        return [...$coreTypes, ...$customTypes];
    }

    /**
     * Serialize the envelope stamps to a transport `metadata` array.
     *
     * Writes only the `stamps` list. Stamps carrying only identifiers and
     * flags travel; in-memory stamps (`NonSendableStampInterface`) are
     * skipped. `metadata` stays otherwise opaque: this method adds nothing
     * else and interprets nothing. `data` is never rewritten here.
     *
     * @return array<string, mixed>
     */
    public function toMetadataArray(): array
    {
        $stamps = [];
        foreach ($this->getStamps() as $list) {
            foreach ($list as $stamp) {
                if ($stamp instanceof NonSendableStampInterface) {
                    continue;
                }

                $stamps[] = $stamp->toArray();
            }
        }

        if ($stamps === []) {
            return [];
        }

        return ['stamps' => $stamps];
    }

    /**
     * Rebuild an envelope from a decoded transport body.
     *
     * Tolerant parse: `stamps` rows resolve through the merged core + app map
     * (`$stampTypes`, see `resolveStampTypes()`). Unknown `type` values,
     * corrupt rows and `NonSendable` types are skipped. A poison stamp never
     * kills a message whose `data` is fine. Only `data` and the surviving
     * stamps are restored; any other `metadata` keys are dropped.
     *
     * The message is an `ArrayObject` wrapping the body `data`.
     *
     * @param array<string, mixed> $body Decoded transport body.
     * @param array<string, class-string<\Cake\Queue\Bus\StampInterface>> $stampTypes App-defined stamp types.
     * @return static
     */
    public static function fromTransportBody(array $body, array $stampTypes = []): static
    {
        $types = self::resolveStampTypes($stampTypes);

        $data = $body['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $metadata = $body['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $stamps = [];
        $rows = $metadata['stamps'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $stamp = self::stampFromArray($row, $types);
                if ($stamp instanceof StampInterface) {
                    $stamps[] = $stamp;
                }
            }
        }

        return new self(new ArrayObject($data), $stamps);
    }

    /**
     * Rebuild one stamp from a serialized row.
     *
     * Unknown `type` values, `NonSendable` types and corrupt rows degrade to
     * `null` (skip).
     *
     * @param array<string, mixed> $row Serialized stamp row.
     * @param array<string, class-string<\Cake\Queue\Bus\StampInterface>> $types Type map.
     */
    private static function stampFromArray(array $row, array $types): ?StampInterface
    {
        $type = $row['type'] ?? null;
        if (!is_string($type)) {
            return null;
        }

        $class = $types[$type] ?? null;
        if ($class === null || is_subclass_of($class, NonSendableStampInterface::class, true)) {
            return null;
        }

        try {
            return $class::fromArray($row);
        } catch (Throwable) {
            return null;
        }
    }
}
