<?php

declare(strict_types=1);

namespace PerSeo\ErrorRenderer;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Error\AbstractErrorRenderer;
use Throwable;
use Psr\Log\LoggerInterface;

class JsonError extends AbstractErrorRenderer
{	
    /** @var App<ContainerInterface> */
    private App $app;
	
    private LoggerInterface $logger;

    /**
     * @param App<ContainerInterface> $app
     */
    public function __construct(App $app, LoggerInterface $logger)
    {
        $this->app = $app;
        $this->logger = $logger;
    }

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $this->logger->error($exception);
        $data = [
            'debug' => $displayErrorDetails,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'basepath' => (string) $this->app->getBasePath()
        ];
        return (string) json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
