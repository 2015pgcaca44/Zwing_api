<?php

namespace App\Http\Controllers\Erp;

interface ConfigInterface
{
	
    public function getApiBaseUrl();
    public function handleApiResponse($response);

}