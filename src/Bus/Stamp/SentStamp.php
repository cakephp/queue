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
 * Transport message identity assigned on send.
 *
 * In-memory only: added by the dispatch bus after publishing, read back by
 * `QueueManager::push()` as the message identity of the returned
 * `DispatchResult` (`null` when the transport assigns no identity). A missing `SentStamp` means the dispatch was either
 * skipped as a duplicate (`SkippedAsDuplicateStamp`) or swallowed, the
 * latter throwing in `QueueManager::push()`.
 */
final class SentStamp implements NonSendableStampInterface
{
    /**
     * @param string|null $messageId Transport message identity, if the message was sent.
     */
    public function __construct(
        private readonly ?string $messageId = null,
    ) {
    }

    /**
     * Get the transport message identity, if the message was sent.
     */
    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'type' => 'SentStamp',
            'v' => 1,
            'messageId' => $this->messageId,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $messageId = $data['messageId'] ?? null;
        if ($messageId !== null && (!is_string($messageId) || $messageId === '')) {
            throw new InvalidArgumentException('SentStamp requires a string or null `messageId`.');
        }

        return new self($messageId);
    }
}
