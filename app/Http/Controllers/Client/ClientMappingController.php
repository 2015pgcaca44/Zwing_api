<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use DB;
use App\Model\Client\ClientVendorMapping;
use App\Model\Client\ClientStoreMapping;
use App\Model\Client\ClientBrandMapping;



class ClientMappingController extends Controller
{

    public function __construct(){
    
        
    }

    public function getZwingVendorId($params){

        $id = null;
        $client = ClientVendorMapping::where('client_id',$params['client_id'])->where('client_vendor_code' , $params['client_vendor_code'])->first();

        if($client){
            $id = $client->v_id;
        }
        
        return $id;
    }


    public function getZwingStoreId($params){

        $id = null;
        $client = ClientStoreMapping::where('client_id',$params['client_id'])->where('client_store_code' , $params['client_store_code'])->first();

        if($client){
            $id = $client->store_id;
        }
        
        return $id;
    }

   public function getZwingItemBrandId($params){
        $id = null;
        $client = ClientItemBrandMapping::where('client_id',$params['client_id'])->where('client_brand_code' , $params['client_brand_code'])->first();

        if($client){
            $id = $client->brand_id;
        }
        
        return $id;
   }


}