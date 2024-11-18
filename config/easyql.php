<?php

// config for rashidul/Easyql
return [
    'api_prefix' => 'easyql',

    //base namespace for your model classes
    'model_namespace' => 'App\\Models',

    'middleware' => [],

    //do not perform CRUD operations on these models
    'restricted' => [
        //'User',
    ],

    'model_path' => app_path('Models/*.php')
];
