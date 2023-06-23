<?php

namespace Rashidul\EasyQL;

use Illuminate\Support\ServiceProvider;

class EasyqlServiceProvider extends ServiceProvider
{
    public function boot()
    {

        $this->configureRoutes();

        $this->publishes([
            __DIR__.'/../config/easyql.php' => config_path('easyql.php'),
        ], ['easyql-config']);
    }


    private function configureRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

}
