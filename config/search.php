<?php 

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Driver
    |--------------------------------------------------------------------------
    |
    | The Laravel queue API supports a variety of back-ends via an unified
    | API, giving you convenient access to each back-end using the same
    | syntax for each one. Here you may set the default queue driver.
    |
    | Supported: "solr", "elastic"
    |
    */

    'default' => env('SEARCH_DRIVER', 'elastic'),


    /*
    |--------------------------------------------------------------------------
    | Search Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    */

    'connections' => [
        
        'elastic' => [
            'host' => env('ELASTICSEARCH_HOST', 'localhost'),
            'port' => env('ELASTICSEARCH_PORT', '9200'),
            'schema' => env('ELASTICSEARCH_SCHEME', 'http'),
            'username' => env('ELASTICSEARCH_USER', ''),
            'password' => env('ELASTICSEARCH_PASS', ''),
        ],
        'solr' => [
            'host' => env('SOLR_HOST', '127.0.0.1'),
            'port' => env('SOLR_PORT', '8983'),
            'path' => env('SOLR_PATH', '/'),
            'core' => env('SOLR_CORE', 'products')
            // For Solr Cloud you need to provide a collection instead of core:
            // 'collection' => 'techproducts',
        ]

    ]


];


?>