<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use DB;
use App\Aisle;
use App\AisleSection;
use App\AisleSectionProduct;
use App\AisleEmptyProduct;
use App\Product;

class AisleSectionController extends Controller
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
        $section_barcode = $request->section_barcode;
        $section = [];
    

        $aisleSection  = DB::table('aisle_sections as asec')
                        ->select('asec.id as aisle_section_id','asec.code','a.number')
                        ->join('aisles as a', 'a.id' , 'asec.aisle_id')
                        ->where('asec.barcode', $section_barcode)
                        ->where('a.v_id',$v_id)->where('a.store_id',$store_id)
                        ->first();

    
        if($aisleSection ){

            $section['code'] = $aisleSection->number.$aisleSection->code;
            $section['aisle_section_id'] = $aisleSection->aisle_section_id;

            $products = $this->getProducts($aisleSection->aisle_section_id);
            $section['product'] = $products;

            if($products->isEmpty()){

                return ['status' => 'success', 'message' => 'No Product found'  , 'data' => $section ];
            }else{

                return ['staus' => 'success' , 'data' => $section ];
            }
        }else{

            return ['status' => 'fail', 'message' => 'No record found' ];

        }

    }


    public function addProduct(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $section_barcode = $request->section_barcode;
        $product_barcode = $request->product_barcode;
        $vu_id = Auth::user()->vu_id;

        $aisleSection = AisleSection::where('barcode', $section_barcode )->where('v_id', $v_id)
                    ->where('store_id', $store_id)->first();

        $products = $aisleSection->products()->where('barcode' , $product_barcode)->first();

       // dd( $products );
        if($products){
            return ['status' => 'fail' , 'message' => 'Product already exists'];
        }else{

            $section = $aisleSection->products()->create([
                'barcode' => $product_barcode,
                'vendor_user_id' => $vu_id
            ]);

            return $this->show($request);
        }

    }



    public function addProductInfo(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        //$section_barcode = $request->section_barcode;
        $aisle_code = $request->aisle_code;
        $product_barcode = $request->product_barcode;
        $vu_id = Auth::user()->vu_id;

        $manufacturing_date = $request->manufacturing_date;
        $expiring_type = $request->expiring_type;
        $expiring_date = $request->expiring_date;
        $best_before = $request->best_before;
        $remind_before = $request->remind_before;
        $rows = $request->rows;        
        $columns = $request->columns;

        if($request->has('aisle_code')){
            $aisle_code = $request->aisle_code;
            $number =  substr($aisle_code , 0,2);
            $section_code = substr($aisle_code , 2);

            $aisleSection = Aisle::where('number', $number)->where('v_id', $v_id)->where('store_id', $store_id)->first()->sections()->where('code', $section_code)->first();

        }

        if($request->has('section_barcode')){
            $aisleSection = AisleSection::where('barcode', $section_barcode )->where('v_id', $v_id)->where('store_id', $store_id)->first();

        }
       


        
        $product = $aisleSection->products()->where('barcode' , $product_barcode)->first();
        //dd($product);

        $product->info()->create([
            'manufacturing_date' => $manufacturing_date,
            'expiring_type' => $expiring_type,
            'expiring_date' => $expiring_date,
            'best_before' => $best_before,
            'remind_before' => $remind_before,
            'rows' => $rows,
            'columns' => $columns,

        ]);


        return ['status' => 'success', 'message' => 'Data has been Added successfully'];



    }


    public function getProductDetail(Request $request){

        $product_barcode =  $request->product_barcode;
        $v_id =  $request->v_id;
        $store_id =  $request->store_id;

        $product = Product::select('name','barcode')
                    ->where('v_id', $v_id)
                    ->where('store_id', $store_id)
                    ->where('barcode', $product_barcode)
                    ->first();

        if($product){
            return ['status' => 'success', 'data' => $product];
        }else{
            return ['status' => 'fail', 'message' => 'No product found'];
        }


    }


    public function getEmptyProduct(Request $request){
        $v_id =  $request->v_id;
        $store_id =  $request->store_id;

       
        $emptyProduct =  AisleEmptyProduct::where('v_id', $v_id)
                        ->where('store_id', $store_id)
                        ->with('product:name,barcode')
                        ->get();

        
        return ['status' =>'success', 'data' => $emptyProduct ];
    }



    public function addEmptyProduct(Request $request){
        $v_id =  $request->v_id;
        $store_id =  $request->store_id;
        $product_barcode =  $request->product_barcode;
        $vu_id = Auth::user()->vu_id;

        $emptyProduct =  new AisleEmptyProduct;

        $emptyProduct->vendor_user_id = $vu_id;
        $emptyProduct->v_id = $v_id;
        $emptyProduct->store_id = $store_id;
        $emptyProduct->barcode = $product_barcode;

        $emptyProduct->save();

        return $this->getEmptyProduct($request);


    }

}
