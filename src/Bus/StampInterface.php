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

/**
 * Marker for all stamps carried by {@see \Cake\Queue\Bus\Envelope}.
 *
 * Stamps are small immutable value objects attached to an envelope so that
 * middlewares can communicate without touching the message payload.
 * Only plain arrays travel: `toArray()` produces JSON-safe data,
 * `fromArray()` rebuilds the stamp and throws on corrupt rows. Callers parsing
 * transport bodies must catch and skip corrupt rows so that a poison stamp
 * never kills a message whose `data` is fine.
 */
interface StampInterface
{
    /**
     * Serialize the stamp to a JSON-safe array.
     *
     * The array always carries a short string `type` (never a FQCN, so classes
     * may move namespaces with an alias kept in the parser) and a per-stamp
     * integer `v` for evolution. Unknown keys must be ignored on read.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Rebuild the stamp from a serialized row.
     *
     * @param array<string, mixed> $data Serialized row.
     * @return static
     * @throws \InvalidArgumentException When the row is corrupt.
     * @throws \LogicException When the stamp cannot be rebuilt because it has
     *   no transport representation (e.g. `JobMessageStamp`, which holds a
     *   live transport context).
     */
    public static function fromArray(array $data): static;
}
