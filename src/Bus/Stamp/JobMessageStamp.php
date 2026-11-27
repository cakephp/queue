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
use Cake\Queue\Job\Message;
use LogicException;

/**
 * The consumed job message carried inside the envelope.
 *
 * In-memory only: the message wraps a live transport context, so it can
 * never be serialized. Writers skip this stamp; the parser never produces
 * it (it is attached by the consume pipeline itself).
 */
final class JobMessageStamp implements NonSendableStampInterface
{
    /**
     * @param \Cake\Queue\Job\Message $message The consumed job message.
     */
    public function __construct(
        private readonly Message $message,
    ) {
    }

    /**
     * Get the job message.
     *
     * @return \Cake\Queue\Job\Message
     */
    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * Always throws: the stamp holds a live transport context and can never
     * be serialized.
     *
     * @return array<string, mixed>
     * @throws \LogicException Always.
     */
    public function toArray(): array
    {
        throw new LogicException('JobMessageStamp holds a live transport context and cannot be serialized.');
    }

    /**
     * Always throws: the stamp holds a live transport context and can never
     * be rebuilt from transport data (the consume pipeline attaches it itself).
     *
     * @param array<string, mixed> $data Serialized row.
     * @throws \LogicException Always.
     */
    public static function fromArray(array $data): static
    {
        throw new LogicException('JobMessageStamp holds a live transport context and cannot be unserialized.');
    }
}
