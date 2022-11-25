<?php
declare(strict_types=1);

namespace Cake\Queue\Queue;

use Cake\Core\ContainerInterface;

trait ServicesTrait
{
    /**
     * @var \Cake\Core\ContainerInterface
     */
    protected $container;

    /**
     * @param string $id Classname or identifier of the service you want to retrieve
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @return mixed
     */
    protected function getService(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * @param \Cake\Core\ContainerInterface $container DI container instance
     * @return void
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
