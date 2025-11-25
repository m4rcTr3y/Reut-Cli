<?php
declare(strict_types=1);

namespace Reut\Router;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

final class ReuteRoute
{
    private App $app;
    private ?RouteCollectorProxy $group;
    private string $groupPrefix;
    private string $groupLabel;
    private static ?bool $docsEnabled = null;

    private function __construct(App $app, ?RouteCollectorProxy $group = null, string $prefix = '', string $label = '')
    {
        $this->app = $app;
        $this->group = $group;
        $this->groupPrefix = $prefix;
        $this->groupLabel = $label;
        self::bootDocsFlag();
    }

    public static function use(App $app): self
    {
        return new self($app);
    }

    public function group(string $prefix, string $label, callable $callback): void
    {
        $this->app->group($prefix, function (RouteCollectorProxy $group) use ($callback, $prefix, $label) {
            $helper = new self($this->app, $group, $this->normalizePrefix($prefix), $label);
            $callback($helper);
        });
    }

    public function get(string $path, callable $handler, string $description = '', bool $requiresAuth = false): void
    {
        $this->register('get', $path, $handler, $description, $requiresAuth);
    }

    public function post(string $path, callable $handler, string $description = '', bool $requiresAuth = false): void
    {
        $this->register('post', $path, $handler, $description, $requiresAuth);
    }

    public function put(string $path, callable $handler, string $description = '', bool $requiresAuth = false): void
    {
        $this->register('put', $path, $handler, $description, $requiresAuth);
    }

    public function patch(string $path, callable $handler, string $description = '', bool $requiresAuth = false): void
    {
        $this->register('patch', $path, $handler, $description, $requiresAuth);
    }

    public function delete(string $path, callable $handler, string $description = '', bool $requiresAuth = false): void
    {
        $this->register('delete', $path, $handler, $description, $requiresAuth);
    }

    private function register(
        string $method,
        string $path,
        callable $handler,
        string $description,
        bool $requiresAuth
    ): void {
        $target = $this->group ?? $this->app;
        if (!method_exists($target, $method)) {
            throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
        }

        $target->{$method}($path, $handler);

        if (self::$docsEnabled) {
            DocsRegistry::add([
                'group' => $this->groupLabel,
                'method' => $method,
                'path' => $this->group ? $this->groupPrefix . $path : $path,
                'description' => $description,
                'requiresAuth' => $requiresAuth,
            ]);
        }
    }

    private static function bootDocsFlag(): void
    {
        if (self::$docsEnabled === null) {
            $flag = $_ENV['REUT_DOCS_ENABLED'] ?? 'true';
            self::$docsEnabled = strtolower((string)$flag) !== 'false';
        }
    }

    private function normalizePrefix(string $prefix): string
    {
        return rtrim($prefix, '/') ?: '';
    }
}

