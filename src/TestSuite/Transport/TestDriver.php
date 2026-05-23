<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Enqueue\Client\Driver\GenericDriver;
use Enqueue\Client\DriverInterface;

/**
 * Test Driver
 *
 * Minimal driver implementation that uses GenericDriver internally.
 */
class TestDriver extends GenericDriver implements DriverInterface
{
}
