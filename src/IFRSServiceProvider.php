<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS;

use IFRS\Context\EntityContext;
use IFRS\Context\EntityResolver;
use IFRS\Context\NullEntityResolver;
use IFRS\Context\StackEntityContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class IFRSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ifrs.php', 'ifrs');

        $this->app->bind(EntityResolver::class, function (Container $app): EntityResolver {
            $resolverClass = $app['config']->get(
                'ifrs.entity_context.resolver',
                NullEntityResolver::class
            );

            if (!is_string($resolverClass) || !is_a($resolverClass, EntityResolver::class, true)) {
                throw new \InvalidArgumentException(
                    'Configured IFRS entity resolver must implement ' . EntityResolver::class . '.'
                );
            }

            return $app->make($resolverClass);
        });

        $this->app->scoped(EntityContext::class, function (Container $app): EntityContext {
            return new StackEntityContext($app->make(EntityResolver::class));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/ifrs.php' => app()->configPath('ifrs.php'),
        ]);

		
		if (config('ifrs.load_migrations', true)) {
			$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
		}

		if ($this->app->runningInConsole() && config('ifrs.load_factories', true)) {
			$this->loadFactoriesFrom(__DIR__ . '/../database/factories');
		}				
    }
}
