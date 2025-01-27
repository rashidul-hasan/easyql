<?php

use Rashidul\EasyQL\Http\Controllers\CrudController;

Route::group([ 'prefix' => config('easyql.api_prefix'), 'middleware' => array_merge(config('easyql.middleware') ?? [], ['easyql.check.query'])], function () {
    Route::get('/crud/schema', [CrudController::class, 'schema']);
    Route::post('/crud/find-where', [CrudController::class, 'findWhere']);
    Route::resource('/crud', CrudController::class);
});
