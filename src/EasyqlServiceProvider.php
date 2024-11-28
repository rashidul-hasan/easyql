<?php

namespace Rashidul\EasyQL;

use Illuminate\Support\ServiceProvider;
use Rashidul\EasyQL\Commands\ClearSchemaCacheCommand;
use Rashidul\EasyQL\Http\Middleware\CheckQueryString;

class EasyqlServiceProvider extends ServiceProvider
{
    public function boot()
    {

        $this->registerMiddleware();
        $this->configureRoutes();

        $this->publishes([
            __DIR__.'/../config/easyql.php' => config_path('easyql.php'),
        ], ['easyql-config']);

        $this->configureCommands();
    }


    private function configureRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    protected function registerMiddleware()
    {
        // Register the middleware with Laravel
        $this->app['router']->aliasMiddleware('easyql.check.query', CheckQueryString::class);
    }

    private function configureCommands(): void
    {
        $this->commands([
            ClearSchemaCacheCommand::class
        ]);
    }
}
