<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Queue\Test\test_app\src\Job\LogToDebugWithServiceJob;
use Cake\Routing\RouteBuilder;

class Application extends BaseApplication
{
    public function middleware(MiddlewareQueue $middleware): MiddlewareQueue
    {
        return $middleware;
    }

    public function routes(RouteBuilder $routes): void
    {
    }

    public function bootstrap(): void
    {
        $this->addPlugin('Cake/Queue');
        $this->addPlugin('Bake');

        // Only set default Queue configuration if no Queue config exists at all
        // This allows tests to fully control configuration
        if (!Configure::check('Queue')) {
            Configure::write('Queue', [
                'default' => [
                    'url' => 'null:',
                    'queue' => 'default',
                    'logger' => 'stdout',
                    'subprocess' => [
                        'enabled' => false,
                        'timeout' => 30,
                        'command' => 'php ' . dirname(__DIR__, 2) . '/bin/cake.php queue subprocess_runner',
                    ],
                ],
            ]);
        }
    }

    public function services(ContainerInterface $container): void
    {
        $container->add(TestService::class);
        $container->add(LogToDebugWithServiceJob::class)->addArgument(TestService::class);
    }
}
