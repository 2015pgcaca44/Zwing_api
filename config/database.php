<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDO Fetch Style
    |--------------------------------------------------------------------------
    |
    | By default, database results will be returned as instances of the PHP
    | stdClass object; however, you may desire to retrieve records in an
    | array format for simplicity. Here you can tweak the fetch style.
    |
    */

    'fetch' => PDO::FETCH_CLASS,

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        // 'testing' => [
        //     'driver' => 'sqlite',
        //     'database' => ':memory:',
        // ],

        // 'sqlite' => [
        //     'driver'   => 'sqlite',
        //     'database' => env('DB_DATABASE', base_path('database/database.sqlite')),
        //     'prefix'   => env('DB_PREFIX', ''),
        // ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST'),
            'port'      => env('DB_PORT'),
            'database'  => env('DB_DATABASE'),
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            //'charset'   => env('DB_CHARSET', 'utf8'),
            //'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
            //'prefix'    => env('DB_PREFIX', ''),
            //'timezone'  => env('DB_TIMEZONE', '+00:00'),
            'strict'    => env('DB_STRICT_MODE', false),
            'options'   => [PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                 \PDO::ATTR_EMULATE_PREPARES => true
            ]
        ],


        'mysql1' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST1'),
            'port'      => env('DB_PORT1'),
            'database'  => env('DB_DATABASE1'),
            'username'  => env('DB_USERNAME1'),
            'password'  => env('DB_PASSWORD1'),
            //'charset'   => env('DB_CHARSET', 'utf8'),
            //'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
            //'prefix'    => env('DB_PREFIX', ''),
            //'timezone'  => env('DB_TIMEZONE', '+00:00'),
            //'strict'    => env('DB_STRICT_MODE', false),
        ],
        'mysql2' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST2'),
            'port'      => env('DB_PORT2'),
            'database'  => env('DB_DATABASE2'),
            'username'  => env('DB_USERNAME2'),
            'password'  => env('DB_PASSWORD2'),
            //'charset'   => env('DB_CHARSET', 'utf8'),
            //'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
            //'prefix'    => env('DB_PREFIX', ''),
            //'timezone'  => env('DB_TIMEZONE', '+00:00'),
            //'strict'    => env('DB_STRICT_MODE', false),
        ],

        'dynamic' => [
            'driver'    => 'mysql',
            'host'      => env('DYB_HOST'),
            'port'      => env('DYB_PORT'),
            'database'  => env('DYB_DATABASE'),
            'username'  => env('DYB_USERNAME'),
            'password'  => env('DYB_PASSWORD'),
            'options'   => [PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                 \PDO::ATTR_EMULATE_PREPARES => true
            ]
        ],

        'mongodb' => array(
            'driver'   => 'mongodb',
            'host'      => env('DB_HOST3', 'localhost'),
            'port'      => env('DB_PORT3', 27017),
            'database'  => env('DB_DATABASE3', 'zwing'),
            // 'username'  => env('DB_USERNAME3', ''),
            // 'password'  => env('DB_PASSWORD3', ''),
            'strict'    => env('DB_STRICT_MODE', false),
            'options' => array(
                'db' => 'zwing' // sets the authentication database required by mongo 3
            )
        ),

        // 'pgsql' => [
        //     'driver'   => 'pgsql',
        //     'host'     => env('DB_HOST', 'localhost'),
        //     'port'     => env('DB_PORT', 5432),
        //     'database' => env('DB_DATABASE', 'forge'),
        //     'username' => env('DB_USERNAME', 'forge'),
        //     'password' => env('DB_PASSWORD', ''),
        //     'charset'  => env('DB_CHARSET', 'utf8'),
        //     'prefix'   => env('DB_PREFIX', ''),
        //     'schema'   => env('DB_SCHEMA', 'public'),
        // ],

        // 'sqlsrv' => [
        //     'driver'   => 'sqlsrv',
        //     'host'     => env('DB_HOST', 'localhost'),
        //     'database' => env('DB_DATABASE', 'forge'),
        //     'username' => env('DB_USERNAME', 'forge'),
        //     'password' => env('DB_PASSWORD', ''),
        //     'charset'  => env('DB_CHARSET', 'utf8'),
        //     'prefix'   => env('DB_PREFIX', ''),
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => 'predis',

        'cluster' => env('REDIS_CLUSTER', false),

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DATABASE', 0),
            'password' => env('REDIS_PASSWORD', null),
        ],

        'options' => [
            'parameters' => ['password' => env('REDIS_PASSWORD', null)],
        ],

    ],

];
