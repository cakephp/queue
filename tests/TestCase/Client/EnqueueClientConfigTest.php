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
namespace Cake\Queue\Test\TestCase\Client;

use Cake\Queue\Client\EnqueueClientConfig;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class EnqueueClientConfigTest extends TestCase
{
    public function testNormalizeNullDefaultsToNullTransport(): void
    {
        $normalized = EnqueueClientConfig::normalize(null);
        $this->assertSame(['dsn' => 'null:'], $normalized['transport']);
        $this->assertSame('default', $normalized['client']['router_queue']);
        $this->assertTrue($normalized['extensions']['reply_extension']);
    }

    public function testNormalizeStringDsn(): void
    {
        $normalized = EnqueueClientConfig::normalize('null:');
        $this->assertSame(['dsn' => 'null:'], $normalized['transport']);
        $this->assertSame('enqueue', $normalized['client']['prefix']);
        $this->assertSame('.', $normalized['client']['separator']);
        $this->assertSame('app', $normalized['client']['app_name']);
    }

    public function testNormalizeArrayWithClientTrue(): void
    {
        $normalized = EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => true]);
        $this->assertSame(['dsn' => 'null:'], $normalized['transport']);
        $this->assertSame('default', $normalized['client']['default_queue']);
    }

    public function testNormalizeTransportExtraKeysPassthrough(): void
    {
        $normalized = EnqueueClientConfig::normalize(['transport' => ['dsn' => 'null:', 'foo' => 'bar']]);
        $this->assertSame('bar', $normalized['transport']['foo']);
    }

    public function testNormalizeClientMerge(): void
    {
        $normalized = EnqueueClientConfig::normalize([
            'transport' => 'null:',
            'client' => ['router_queue' => 'billing', 'prefix' => 'cake'],
        ]);
        $this->assertSame('billing', $normalized['client']['router_queue']);
        $this->assertSame('cake', $normalized['client']['prefix']);
        $this->assertSame('default', $normalized['client']['router_topic']);
    }

    public function testNormalizeExtensionsDefaultsAndOverrides(): void
    {
        $defaults = EnqueueClientConfig::normalize('null:');
        $this->assertSame(function_exists('pcntl_signal_dispatch'), $defaults['extensions']['signal_extension']);

        $normalized = EnqueueClientConfig::normalize([
            'transport' => 'null:',
            'extensions' => ['signal_extension' => false, 'reply_extension' => false],
        ]);
        $this->assertFalse($normalized['extensions']['signal_extension']);
        $this->assertFalse($normalized['extensions']['reply_extension']);
    }

    public function testNormalizeTransportRequiresDsn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => ['foo' => 'bar']]);
    }

    public function testNormalizeClientFalseThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => false]);
    }

    public function testNormalizeUnknownTopLevelKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'transprot' => 'null:']);
    }

    public function testNormalizeUnknownClientKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => ['router_qeue' => 'billing']]);
    }

    public function testNormalizeUnknownExtensionKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'extensions' => ['signal' => true]]);
    }

    public function testNormalizeClientRejectsEmptyRouterQueue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => ['router_queue' => '  ']]);
    }

    public function testNormalizeClientRejectsNonStringPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => ['prefix' => 123]]);
    }

    public function testNormalizeClientRejectsNegativeRedeliveredDelayTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => ['redelivered_delay_time' => -1]]);
    }

    public function testNormalizeClientRejectsNonIntegerRedeliveredDelayTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => ['redelivered_delay_time' => '5']]);
    }

    public function testNormalizeClientRejectsNonArrayDriverOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'client' => ['driver_options' => 'foo']]);
    }

    public function testNormalizeExtensionsAcceptsStringBooleans(): void
    {
        $normalized = EnqueueClientConfig::normalize([
            'transport' => 'null:',
            'extensions' => ['signal_extension' => 'false', 'reply_extension' => 'true'],
        ]);
        $this->assertFalse($normalized['extensions']['signal_extension']);
        $this->assertTrue($normalized['extensions']['reply_extension']);
    }

    public function testNormalizeExtensionsRejectsUnrecognizedValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnqueueClientConfig::normalize(['transport' => 'null:', 'extensions' => ['reply_extension' => 'maybe']]);
    }

    public function testRouterProcessorConstant(): void
    {
        $this->assertSame('enqueue.client.router_processor', EnqueueClientConfig::ROUTER_PROCESSOR);
    }
}
