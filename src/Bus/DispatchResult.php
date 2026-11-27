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

use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\Stamp\SkippedAsDuplicateStamp;
use LogicException;

/**
 * Dispatch outcome returned by `QueueManager::push()`.
 *
 * Replaces the ambiguous `?string` contract: transports that assign no
 * message identity and skipped duplicates both used to resolve to `null`.
 * A swallowed dispatch (no `SentStamp`, no `SkippedAsDuplicateStamp`) is
 * not represented here at all — `push()` throws instead.
 *
 * When both stamps are present (only possible via user middleware), the
 * `SentStamp` wins: the message was sent.
 */
final readonly class DispatchResult
{
    /**
     * @param string|null $messageId Transport message identity, if the transport assigns one.
     * @param bool $skippedAsDuplicate Whether the dispatch was skipped as a duplicate unique job.
     */
    public function __construct(
        public ?string $messageId = null,
        public bool $skippedAsDuplicate = false,
    ) {
    }

    /**
     * Build the dispatch outcome from a finished dispatch envelope.
     *
     * A `SentStamp` always wins over a `SkippedAsDuplicateStamp`: both can
     * only coexist when user middleware stamped the envelope by hand.
     *
     * @param \Cake\Queue\Bus\Envelope $envelope Finished dispatch envelope.
     * @throws \LogicException When the envelope carries neither stamp (swallowed dispatch).
     */
    public static function fromEnvelope(Envelope $envelope): self
    {
        $sent = $envelope->getLastStamp(SentStamp::class);
        if ($sent instanceof SentStamp) {
            return new self($sent->getMessageId());
        }

        if ($envelope->getLastStamp(SkippedAsDuplicateStamp::class) instanceof SkippedAsDuplicateStamp) {
            return new self(null, true);
        }

        throw new LogicException(
            'Dispatch pipeline produced no result: dispatch middleware must call ' .
            '`$stack->next()` or the dispatch was swallowed before sending.',
        );
    }

    /**
     * Whether the job was sent to the transport.
     */
    public function isSent(): bool
    {
        return !$this->skippedAsDuplicate;
    }

    /**
     * Whether the dispatch was skipped as a duplicate unique job.
     */
    public function isSkipped(): bool
    {
        return $this->skippedAsDuplicate;
    }
}
