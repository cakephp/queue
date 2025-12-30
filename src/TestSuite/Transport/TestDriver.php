<?php
declare(strict_types=1);

namespace Cake\Queue\TestSuite\Transport;

use Enqueue\Client\Config;
use Enqueue\Client\Driver\GenericDriver;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\RouteCollection;
use Interop\Queue\Context;

/**
 * Test Driver
 *
 * Minimal driver implementation that uses GenericDriver internally.
 */
class TestDriver extends GenericDriver implements DriverInterface
{
    /**
     * Constructor
     *
     * @param \Interop\Queue\Context $context Context
     * @param \Enqueue\Client\Config $config Client config
     * @param \Enqueue\Client\RouteCollection $routes Route collection
     */
    public function __construct(Context $context, Config $config, RouteCollection $routes)
    {
        parent::__construct($context, $config, $routes);
    }
}
