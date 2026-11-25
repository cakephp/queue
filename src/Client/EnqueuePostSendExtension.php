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
namespace Cake\Queue\Client;

use Enqueue\Client\PostSend;
use Enqueue\Client\PostSendExtensionInterface;

/**
 * Captures the last successful produce result.
 *
 * Producer generates messageId internally, this extension only observes it.
 */
class EnqueuePostSendExtension implements PostSendExtensionInterface
{
    /**
     * Last successful post send context or null when unavailable.
     */
    protected ?PostSend $lastPostSend = null;

    /**
     * Handle post send event.
     *
     * @param \Enqueue\Client\PostSend $context Post send context.
     * @return void
     */
    public function onPostSend(PostSend $context): void
    {
        $this->lastPostSend = $context;
    }

    /**
     * Get last post send context.
     *
     * @return \Enqueue\Client\PostSend|null Last post send context or null.
     */
    public function getLastPostSend(): ?PostSend
    {
        return $this->lastPostSend;
    }

    /**
     * Clear stored context.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->lastPostSend = null;
    }
}
