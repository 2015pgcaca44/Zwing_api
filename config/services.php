<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
    /*** @author: Shweta T * Date: 18/06/2020 */
    'phonePe' => [
        'xProviderId' => 'M2401563246873249082352',
        'saltIndex' => 1,
        'saltKey' => "8289e078-be0b-484d-ae60-052f117f8deb"
    ],
     /*** @author: Shweta T * Date: 23/07/2020 */
     'amazon' => [
        'client_id' => 'amzn1.application-oa2-client.684b19c06c734b8aa1fc07cc0a33b885', //IAM user credentials  
        'client_secret' => "e4adf10d3b9ecb1f479bc42c1811ee6136024e138ad637af009ec209814735d7",
        'locationId' => "72bafe4a-f4e0-4105-9c2a-6956c3abc3f6",  //Sandbox testing  

        'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID'),
        'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
        'AWS_REGION' => env('AWS_REGION'),
        'ROLE_ARN' => env('ROLE_ARN'),
    ],
];