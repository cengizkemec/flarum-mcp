<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp;

use CengizKemec\FlarumMcp\Api\Controller\McpController;
use CengizKemec\FlarumMcp\Mcp\ApprovalService;
use CengizKemec\FlarumMcp\Mcp\AuditLogger;
use CengizKemec\FlarumMcp\Mcp\Authenticator;
use CengizKemec\FlarumMcp\Mcp\BuiltInPrompts;
use CengizKemec\FlarumMcp\Mcp\BuiltInResources;
use CengizKemec\FlarumMcp\Mcp\BuiltInTools;
use CengizKemec\FlarumMcp\Mcp\FlarumDataService;
use CengizKemec\FlarumMcp\Mcp\PermissionRegistry;
use CengizKemec\FlarumMcp\Mcp\PromptRegistry;
use CengizKemec\FlarumMcp\Mcp\RateLimiter;
use CengizKemec\FlarumMcp\Mcp\ResourceRegistry;
use CengizKemec\FlarumMcp\Mcp\ServerFactory;
use CengizKemec\FlarumMcp\Mcp\TokenService;
use CengizKemec\FlarumMcp\Mcp\ToolExecutor;
use CengizKemec\FlarumMcp\Mcp\ToolRegistry;
use CengizKemec\FlarumMcp\Permission\PermissionService;
use CengizKemec\FlarumMcp\Skill\RemoteFetcher;
use CengizKemec\FlarumMcp\Skill\SkillManager;
use CengizKemec\FlarumMcp\Skill\SkillParser;
use CengizKemec\FlarumMcp\Skill\SkillRegistry;
use CengizKemec\FlarumMcp\Skill\ZipInstaller;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Http\RouteCollection;
use Flarum\Http\RouteHandlerFactory;
use Illuminate\Contracts\Container\Container;

class ServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PermissionRegistry::class, fn (): PermissionRegistry => new PermissionRegistry());
        $this->container->singleton(ToolRegistry::class, fn (): ToolRegistry => new ToolRegistry());
        $this->container->singleton(ResourceRegistry::class, fn (): ResourceRegistry => new ResourceRegistry());
        $this->container->singleton(PromptRegistry::class, fn (): PromptRegistry => new PromptRegistry());

        $this->container->singleton(TokenService::class);
        $this->container->singleton(PermissionService::class);
        $this->container->singleton(AuditLogger::class);
        $this->container->singleton(RateLimiter::class);
        $this->container->singleton(Authenticator::class);
        $this->container->singleton(ApprovalService::class);
        $this->container->singleton(FlarumDataService::class);
        $this->container->singleton(ToolExecutor::class);
        $this->container->singleton(SkillParser::class);
        $this->container->singleton(RemoteFetcher::class);
        $this->container->singleton(ZipInstaller::class);
        $this->container->singleton(SkillRegistry::class);
        $this->container->singleton(SkillManager::class);
        $this->container->singleton(ServerFactory::class);

        BuiltInTools::register($this->container->make(ToolRegistry::class));
        BuiltInResources::register($this->container->make(ResourceRegistry::class));
        BuiltInPrompts::register($this->container->make(PromptRegistry::class));

        $this->registerOptionsRoute();
    }

    private function registerOptionsRoute(): void
    {
        $add = function (RouteCollection $routes, Container $container): void {
            if (isset($routes->getRoutes()['cengizkemec-mcp.mcp.options'])) {
                return;
            }

            /** @var RouteHandlerFactory $factory */
            $factory = $container->make(RouteHandlerFactory::class);

            $routes->addRoute(
                'OPTIONS',
                '/mcp',
                'cengizkemec-mcp.mcp.options',
                $factory->toController(McpController::class)
            );
        };

        if ($this->container->bound('flarum.forum.routes')) {
            // Do not resolve the collection here: extension route extenders
            // register their own `resolving` callbacks after this provider.
        }

        $this->container->resolving('flarum.forum.routes', function (RouteCollection $routes, Container $container) use ($add): void {
            $add($routes, $container);
        });
    }
}
