<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Interop\Queue\ConnectionFactory;
use Interop\Queue\Context;

/**
 * Test Connection Factory
 *
 * Creates test contexts that capture messages instead of sending them.
 */
class TestConnectionFactory implements ConnectionFactory
{
    /**
     * Create context
     *
     * @return \Interop\Queue\Context
     */
    public function createContext(): Context
    {
        return new TestContext();
    }

    /**
     * Close connection
     *
     * @return void
     */
    public function close(): void
    {
    }
}
