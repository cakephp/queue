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

/**
 * Marker that the dispatch was skipped as a duplicate unique job.
 *
 * In-memory only: attached by the dispatch bus instead of a `SentStamp`
 * when the unique check short-circuits the pipeline, read back by
 * `QueueManager::push()` as a skipped `DispatchResult`. A finished dispatch
 * carrying neither this stamp nor a `SentStamp` was swallowed by user middleware.
 */
final class SkippedAsDuplicateStamp implements NonSendableStampInterface
{
    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'type' => 'SkippedAsDuplicateStamp',
            'v' => 1,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self();
    }
}
