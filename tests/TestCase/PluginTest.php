<?php
declare(strict_types=1);

namespace Cake\Queue\Test\TestCase;

use Cake\Core\Configure;
use Cake\Queue\QueueManager;
use Cake\Queue\QueuePlugin;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use TestApp\Application;

class PluginTest extends TestCase
{
    /**
     * Test Plugin bootstrap with no config
     *
     * @return void
     */
    public function testBootstrapNoConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `Queue` configuration key, please check the CakePHP Queue documentation to complete the plugin setup');
        Configure::delete('Queue');
        $plugin = new QueuePlugin();
        $app = $this->createStub(Application::class);
        $plugin->bootstrap($app);
    }

    /**
     * Test Plugin bootstrap with config
     *
     * @return void
     */
    public function testBootstrapWithConfig(): void
    {
        $queueConfig = [
            'url' => 'null:',
            'queue' => 'default',
            'logger' => 'stdout',
        ];
        Configure::write('Queue', ['default' => $queueConfig]);
        $plugin = new QueuePlugin();
        $app = $this->createStub(Application::class);
        $plugin->bootstrap($app);
        $queueConfig['url'] = [
            'transport' => 'null:',
            'client' => [
                'router_topic' => 'default',
                'router_queue' => 'default',
                'default_queue' => 'default',
            ],
        ];
        $this->assertEquals($queueConfig, QueueManager::getConfig('default'));
    }
}
