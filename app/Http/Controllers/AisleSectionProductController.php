<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use DB;
use App\AisleSection;
use App\AisleSectionProduct;
use App\Product;

class AisleSectionProductController extends Controller
{
    protected $productTableName = '';

    public function __construct(){
        
        $product = new Product;
        $this->productTableName = $product->table;
    }


    public function getProducts($aisle_section_id){

        $products  = DB::table('aisle_section_products as asp')
            ->select('asp.barcode','p.name')
            ->join('products as p', 'p.barcode' , 'asp.barcode')
            ->where('asp.aisle_section_id', $aisle_section_id)
            ->get();


        return $products;


    }

    public function show(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $product_barcode = $request->product_barcode;
        $section = [];
    

         AisleSectionProduct::find()

    
        $section['code'] = $aisleSection->number.$aisleSection->code;
        $section['aisle_section_id'] = $aisleSection->aisle_section_id;

        $products = $this->getProducts($aisleSection->aisle_section_id);
        $section['product'] = $products;

        if($products->isEmpty()){

            return ['status' => 'fail', 'message' => 'No record found' ];
        }else{

            return ['staus' => 'success' , 'data' => $section ];
        }
        

    }


    public function addProduct(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $section_barcode = $request->section_barcode;
        $product_barcode = $request->product_barcode;
        $vu_id = Auth::user()->vu_id;

        $section = AisleSection::where('barcode',$section_barcode)->first()->products()->create([
            'barcode' => $product_barcode,
            'vendor_user_id' => $vu_id
        ]);

        return $this->show($request);

    }

}
