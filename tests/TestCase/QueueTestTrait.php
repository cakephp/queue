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
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */

namespace Cake\Queue\Test\TestCase;

use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Queue\QueueManager;
use PHPUnit\Framework\Attributes\After;

/**
 * Trait providing utilities for queue testing
 *
 * Provides:
 * - Configuration cleanup for QueueManager, Cache, and Log
 * - Debug log assertion helpers
 */
trait QueueTestTrait
{
    /**
     * Clean up QueueManager, Cache, and Log configurations after each test
     *
     * This is automatically called after each test via the #[After] attribute.
     * It drops all QueueManager configs and their associated cache configs,
     * and resets all log configurations.
     *
     * @return void
     */
    #[After]
    public function cleanupQueueManagerConfigs(): void
    {
        // Drop all QueueManager configurations and their associated caches
        foreach (QueueManager::configured() as $config) {
            // Drop associated cache config if it exists
            $queueConfig = QueueManager::getConfig($config);
            if ($queueConfig && isset($queueConfig['uniqueCacheKey'])) {
                $cacheKey = $queueConfig['uniqueCacheKey'];
                if (Cache::configured()) {
                    Cache::drop($cacheKey);
                }
            }

            QueueManager::drop($config);
        }

        // Reset log configurations
        Log::reset();
    }

    /**
     * Assert that a message was found in debug logs
     *
     * @param string $expected The message to search for in logs
     * @return void
     */
    protected function assertDebugLogContains($expected): void
    {
        $found = $this->debugLogCount($expected);

        $this->assertGreaterThanOrEqual(1, $found, sprintf('Did not find `%s` in logs.', $expected));
    }

    /**
     * Assert that a message was found exactly N times in debug logs
     *
     * @param string $expected The message to search for in logs
     * @param int $times The exact number of times the message should appear
     * @return void
     */
    protected function assertDebugLogContainsExactly($expected, $times): void
    {
        $found = $this->debugLogCount($expected);

        $this->assertSame($times, $found, sprintf('Did not find `%s` exactly %d times in logs.', $expected, $times));
    }

    /**
     * Count occurrences of a message in debug logs
     *
     * @param string $search The message to search for
     * @return int The number of times the message was found
     */
    protected function debugLogCount($search): int
    {
        $log = Log::engine('debug');
        $found = 0;
        foreach ($log->read() as $line) {
            if (strpos($line, $search) !== false) {
                $found++;
            }
        }

        return $found;
    }
}
