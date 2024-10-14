<?php

use Rashidul\EasyQL\Http\Controllers\CrudController;

Route::group([ 'prefix' => config('easyql.api_prefix'), 'middleware' => config('easyql.middleware')], function () {
    Route::get('/crud/schema', [CrudController::class, 'schema']);
    Route::post('/crud/find-where', [CrudController::class, 'findWhere']);
    Route::resource('/crud', CrudController::class);
});
