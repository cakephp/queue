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
namespace Cake\Queue\Bus\Stamp;

use Cake\Queue\Bus\NonSendableStampInterface;
use InvalidArgumentException;

/**
 * Result of handling the envelope.
 *
 * In-memory only: handler results may be objects (such as an Enqueue result
 * for RPC) that have no transport representation, so writers skip this stamp.
 * String results round-trip verbatim; object results degrade to their class
 * name and are never restored.
 */
final class HandledStamp implements NonSendableStampInterface
{
    /**
     * @param object|string $result Handler result.
     */
    public function __construct(
        private readonly string|object $result,
    ) {
    }

    /**
     * Get the handler result.
     */
    public function getResult(): string|object
    {
        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'type' => 'HandledStamp',
            'v' => 1,
            'result' => is_string($this->result) ? $this->result : $this->result::class,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        if (!isset($data['result']) || !is_string($data['result']) || $data['result'] === '') {
            throw new InvalidArgumentException('HandledStamp requires a non-empty string `result`.');
        }

        return new self($data['result']);
    }
}
