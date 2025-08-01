<?php

declare(strict_types=1);

namespace PerSeo\ErrorRenderer\Test\Mock;

use Psr\Container\ContainerInterface;

class ContainerMock implements ContainerInterface
{
    private array $services = [];

    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new \RuntimeException("Service $id not found");
        }
        
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }
}