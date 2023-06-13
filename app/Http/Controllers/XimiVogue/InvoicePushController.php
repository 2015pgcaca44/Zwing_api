<?php

namespace App\Http\Controllers\XimiVogue;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Ginesys\InvoicePushController as Extended_InvoicePush_Controller;

class InvoicePushController extends Extended_InvoicePush_Controller
{
	public function __construct()
	{
		$this->store_db_name = 'ximivogue';
	}
}
