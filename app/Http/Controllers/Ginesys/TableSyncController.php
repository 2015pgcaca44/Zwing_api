<?php

namespace App\Http\Controllers\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TableSyncController extends Controller
{
	
	public function latestInvoiceId(Request $request) {
        
        return response()->json(['status' => 'fail', 'message' => 'Not Implemented'], 200);
     }
}