<?php
declare(strict_types=1);

namespace TestApp;

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
    }

    public function services(ContainerInterface $container): void
    {
        $container->add(TestService::class);
        $container->add(LogToDebugWithServiceJob::class)->addArgument(TestService::class);
    }
}
