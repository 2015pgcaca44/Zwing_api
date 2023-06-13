<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Items\ItemVariantAttributes;
use App\Items\ItemVariantAttributeValues;
use App\Items\VendorItem;
use App\Model\Item\UomConversions;
use App\VendorSetting;
use Illuminate\Http\Request;
use App\Item;
use Auth;
use DB;
use App\Events\DataFetchCurl;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use App\LoyaltyLogs;

class DataFetchingApi extends Controller
{
    public function __construct()
    {
        
        $this->v_id = 27;
    	$this->productconfig  = new ProductconfigController;
    }

    public function curlRequest($value='')
    {
    	# code...
    }

     public function dataFetchRequest()
    {
    	$dataCollection = array();
    	$curl = curl_init();
    	// Creating an array for request
	       curl_setopt_array($curl, array(
	       CURLOPT_URL => "http://14.143.181.141:88/WSVistaWebClient/RESTData.svc/concession-items?cinemaId=889",
	       CURLOPT_RETURNTRANSFER => true,
	       CURLOPT_ENCODING => "",
	       CURLOPT_MAXREDIRS => 10,
	       CURLOPT_TIMEOUT => 30000,
	       CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	       CURLOPT_CUSTOMREQUEST => "GET",
           CURLOPT_HEADER => false,
	       CURLOPT_HTTPHEADER => array(
		           // Set here requred headers
		           "accept: */*",
		           "accept-language: en-US,en;q=0.8",
		           "content-type: application/json",
		       ),
		   ));

		   $response = curl_exec($curl); // Execeuting curl request
           $httpCode = curl_getinfo($curl);
           $Log = [

                'v_id'      => $this->v_id,
                'status'    => $httpCode['http_code'],
                'request'   => $httpCode['url'],
                'response'  => $response,
                'type'      => 'GET'
           ];
           $this->callAPI($Log);
		   $data = json_decode($response,true);

		   foreach ($data['ConcessionItems'] as $itemKeys => $itemValues) {
			   	// Check if the Items has alternate items (Ex: Softdrink=> Alternate[Lemonade, Cola, Orange])
			 	if(!empty($itemValues['AlternateItems'])){
			 		
			 		// check items here;
			 	}else{
			 		
			 		
			 		// exit here
			 	}
                $price = $itemValues['PriceInCents']/100;
			 $array_data = array(
			 	'name'=>$itemValues['Description'],
			 	'auto_sku' => true,
                'v_id' => $this->v_id,
                'department'=> '{"name":"bikaner"}',
			 	'brand' => '{"name":"bikaner"}',
                'category' => ['{"name":"bun","code":40}'],
			 	'description'=> $itemValues['Description'],
			 	'image' => '{}',
			 	'mrp' => $price,
			 	'product_attributes'=> array(array('attributes'=>'sweet','deleted'=>false,'value'=>'ras malai','type'=>'text')),
			 	'rsp'=> $price,
			 	'short_desc'=> $itemValues['Description'],
			 	'special_price'=> $price,
			 	'uom'=> '{"selling":null,"purchase":null,"factor":1}',
			 	'uom_conversion_id'=> '{"name":"Each-Each-1","id":27,"help_text":"1 Each = 1 Each"}',
			 	'variant'=> ['{"attributes":{"name":"default","id":1},"variant_value":[{"name":"default","code":1}]}'],
			 	'variant_products'=> ['{"vrname":"default","vrbarcode":"'.$itemValues['Id'].'","vrqty":0,"sku":"","hsn_code":null,"tax_group_id":null,"variant_prices":[{"id":null,"mrp":"'.$price.'","rsp":"'.$price.'","special_price":"'.$price.'"}]}']
			 );
			
			$data = array_push($dataCollection, $array_data);
		   		
		   }
		   return $dataCollection;
		   
        }

        public function callAPI($params)
        {
            
            LoyaltyLogs::create([
                'v_id'      => $this->v_id,
                'status'    => $params['status'],
                'request'   => $params['request'],
                'response'  => $params['response'],
                'type'      => 'easeMyRetail'
            ]);
        }

     public function createProduct(request $request)
        {
        		$json_data = $this->dataFetchRequest();
                foreach ($json_data as $key => $value) {
                    $params = [
                        'v_id' => $value['v_id'],

                    ];
                    $request->request->add([
                    'name' => $value['name'],
                    'auto_sku' => $value['auto_sku'],
                    'v_id' => $value['v_id'],
                    'brand' => $value['brand'],
                    'department' => $value['department'],
                    'description' => $value['description'],
                    'category' => $value['category'],
                    'image' => $value['image'],
                    'mrp' => $value['mrp'],
                    'product_attributes' => $value['product_attributes'],
                    'rsp' => $value['rsp'],
                    'short_desc' => $value['short_desc'],
                    'special_price' => $value['special_price'],
                    'uom' => $value['uom'],
                    'uom_conversion_id' => $value['uom_conversion_id'],
                    'variant' => $value['variant'],
                    'variant_products' => $value['variant_products'],
                ]);
                    $data_send = json_encode($request);
                     $this->create($request);
                }
        }

	public function index(){
        if(isset(Auth::user()->store_id) && isset(Auth::user()->vendor_id) && isset(Auth::user()->employee_code)){
            $is_store_logged_in = "store_logged_in";
        }else{
            $is_store_logged_in = "vendor_logged_in";
        }
       
        return view('product.view',['is_store_login'=> $is_store_logged_in]);
//		return view('product.view'
//            , [ 'product' => $product,'filter_text'=>$filter_text]
//        );
	}//End of index

    public function allItems(Request $request) {
        $product = Item::whereHas('vendor', function($query) {
                $query->where('v_id', $this->vendor_id());
            })
            ->where('deleted', '0');
        return zwDataTable($request, $product);
    }

	public function add(){
		$title   = 'Add';
        $this->addDefaultVariantAttribute();
        $this->addDefaultVariantValue();
		return view('product.add',['title'=>$title]); //,'category'=>$category

	}//End of add


    public function import(){
        return view('product.import');
    }

    public function exportCSV() {
        $productSettings = json_decode(VendorSetting::select('settings')->where('v_id', Auth::user()->v_id)
            ->where('name', 'product')->first()->settings);
        if(!empty($productSettings->max_item_variant_attribute) && $productSettings->max_item_variant_attribute != 0) {
            $headers = array(
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=product_template.csv",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            );

            $columns = array(
                'name', 'category', 'short_description', 'long_description', 'brand', 'department', 'image_path', 'sku_type',
                'has_batch', 'has_serial',
                'purchase_uom_code', 'selling_uom_code', 'uom_factor',
                'attributes', 'attribute_value', 'attribute_value_type',
                'variant_barcode', 'variant_sku', 'variant_hsn_code', 'variant_mrp', 'variant_rsp', 'variant_special_price'
            );

            for($i = 1; $i <= $productSettings->max_item_variant_attribute; ++$i) {
                $columns[] = 'variant_attribute_'.$i;
                $columns[] = 'variant_attribute_value_'.$i;
            }

            $callback = function() use ($columns)
            {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        } else {
            return response()->json([
                'message' => "Please Update Maximum Item Variant Attribute",
                'status' => 'error',
                'errors' => [
                    'max_attribute' => [
                        'Update maximum item variant attribute'
                    ]
                ]
            ], 422);
        }
    }

	public function extra(){
		$title   = 'Add';
		/*$vendorFetch = VendorAuth::find(Auth::id());*/
		return view('product.extra',['title'=>$title]);
	}

	public function edit($id){
		$title    = 'Edit';
        $this->addDefaultVariantAttribute();
        $this->addDefaultVariantValue();
 		return view('product.add',['title'=>$title, 'id' => $id]);
	}//End of edit

    public function view($id){
        return view('product.view-item',['id'=>$id]);
    }

    public function addDefaultVariantAttribute() {
	    $default = ItemVariantAttributes::where('name', 'default')->first();
	    if(!$default) {
	        $default = ItemVariantAttributes::create([
	            'name' => 'default',
                'code' => 'def6301'
            ]);
        }
    }

    public function addDefaultVariantValue() {
        $default = ItemVariantAttributeValues::where('value', 'default')->first();
        if(!$default) {
            $default = ItemVariantAttributeValues::create([
                'value' => 'default',
            ]);
        }
    }

    public function getVariantAttributeId($attribute) {
        if(isset($attribute['id'])) {
            $item_attribute = ItemVariantAttributes::find($attribute['id']);
        } else {
            $item_attribute = ItemVariantAttributes::where('name', $attribute['name'])->first();
            if(!$item_attribute) {
                $item_attribute = new ItemVariantAttributes;
                $item_attribute->name = $attribute['name'];
                $item_attribute->code = $attribute['code'];
                $item_attribute->save();
            }
        }
        return $item_attribute->id;
    }

    public function cartesian($input) {
        $result = array(array());

        foreach ($input as $ind => $valueArr) {
            $append = array();
            $value = json_decode($valueArr,true);
            foreach($result as $key => $product) {
                foreach($value['variant_value'] as $item) {
                    $attribute_id = $this->getVariantAttributeId($value['attributes']);
                    $product[$attribute_id] = $item['code'];
                    $append[] = $product;
                }
            }

            $result = $append;
        }

//        dd($result);

        $res2 = array();
        foreach ($result as $key => $value) {
            $str = '';
            foreach ($value as $a => $item) {
                $str = $str.ItemVariantAttributeValues::select('value')->find($item)['value'].'-';
            }
            $res2[substr($str, 0, -1)] = $value;
        }

        return $res2;
    }

	public function create(Request $req){
	    $request = json_decode(json_encode($req->all()));
        // $req->validate(Item::$rules);
        $product = $this->addOrEditProductInfo($request);
        if ($product instanceof \Symfony\Component\HttpFoundation\Response) {
            return $product;
        }


        /*Image upload*/
        // if($req->has('image') && ($req->image != 'undefined')) {
        //     $path 				  =  "/product/".$request->v_id."/";
        //     $imageuploder  		  =  new UploadimageController();
        //     $image 				  =  $imageuploder->uploadimage($req,$path);
        //     $attribute            = 'Default';
        //     $attribute_value      = $image['path'];
        //     $this->productconfig->addMediaAttribute($attribute,$attribute_value,$product->id);
        // }
        /*Image upload end*/

        return response()->json(['id' => $product->id], 200);
	}

	public function addOrEditProductInfo($request) {
        DB::beginTransaction();
	    try {
            if (isset($request->category) && gettype($request->category) == 'string') {
                $request->category = json_decode($request->category, true);
            }
            if (isset($request->product_attributes) && gettype($request->product_attributes) == 'string') {
                $request->product_attributes = json_decode($request->product_attributes, true);
            }
            if (isset($request->variant) && gettype($request->variant) == 'string') {
                $request->variant = json_decode($request->variant, true);
            }
            if (isset($request->variant_products) && gettype($request->variant_products) == 'string') {
                $request->variant_products = json_decode($request->variant_products, true);
            }

            // Attribute Validation
            foreach ($request->product_attributes as $index => $product_attribute) {
                $product_attribute = json_decode(json_encode($product_attribute), true);
                if (!@$product_attribute['attributes'] &&
                    !@$product_attribute['value']) {
                    continue;
                } else if (!$product_attribute['attributes'] || !$product_attribute['value']) {
                    return response()->json([
                        'message' => "Missing product attribute or value",
                        'status' => 'error',
                        'errors' => [
                            'product_attributes' => [
                                $index => [
                                    'Attribute information is required'
                                ]
                            ]
                        ]
                    ], 422);
                }
            }

            // Variant Validation
//        foreach ($request->variant as $index => $variant) {
//            print(json_encode($variant)); die;
//        }


            $id = null;
            if (isset($request->id)) {
                $id = $request->id;
            }

            // Product Department

            if (gettype($request->department) == 'string') {
                $department_id = $this->productconfig->getDepartmentId(json_decode($request->department)->name);
            } else {
                $department_id = $this->productconfig->getDepartmentId($request->department->name);
            }

            if ($id) {
                $product = Item::find($id);
            } else {
                $product = Item::where('name', $request->name)
                    ->where('department_id', $department_id)
                    ->whereHas('vendor', function ($query) use($request) {
                        $query->where('v_id', $request->v_id);
                    })
                    ->first();
                if (!$product) {
                    $product = new Item;
                }
                    

            }
            $product->name = $request->name;
            $product->short_description = $request->short_desc;
            $product->long_description = $request->description;
            if (empty($request->auto_sku) || !$request->auto_sku) {
//            $product->sku   = $request->custom_sku;
                $request->auto_sku = false;
                $product->sku = 'custom';
            } else {
                $product->sku = 'auto';
            }
//            print(isset($request->is_tax_inclusive)?'EMP': 'NOT'); die;
//            if(!empty($request->is_tax_inclusive)) {
//            }
            //$product->tax_type = !empty($request->is_tax_exclusive)? (($request->is_tax_exclusive)? 'EXC': 'INC'): 'INC';

            $tax_type = !empty($request->is_tax_exclusive)? (($request->is_tax_exclusive)? 'EXC': 'INC'): 'INC';
            $product->tax_type = $tax_type;

            $vendorItem = VendorItem::where('v_id',$request->v_id)->where('item_id', $product->id)->first();
            $vendorItem->tax_type = $tax_type;
            $vendorItem->save();

            $product->mrp = !empty($request->mrp) ? $request->mrp : null;
            $product->rsp = !empty($request->rsp) ? $request->rsp : null;
            $product->hsn_code = !empty($request->hsn_code) ? json_decode($request->hsn_code)->hsn_code : null;
            $product->tax_group_id = !empty($request->tax_group_id) ? json_decode($request->tax_group_id)->id : null;
            $product->special_price = !empty($request->special_price) ? $request->special_price : null;
            $product->has_batch = !empty($request->has_batch) ? ($request->has_batch ? 1 : 0) : 0;
            $product->has_serial = !empty($request->has_serial) ? ($request->has_serial ? 1 : 0) : 0;
            $product->department_id = $department_id;
            $product->deleted = 0;
            if (gettype($request->brand) == 'string') {
                $product->brand_id = $this->productconfig->getBrandId(json_decode($request->brand)->name);
            } else {
                $product->brand_id = $this->productconfig->getBrandId($request->brand->name);
            }
            if (isset($request->new_uom)) {
                $uom = (gettype($request->department) == 'string') ? json_decode($request->uom) : $request->uom;

                $uom_conversion = json_decode($this->productconfig->createUomConversion($uom->purchase, $uom->selling, $uom->factor));
                if ($uom_conversion->status == 'error') {
                    return response()->json($uom_conversion, 422);
                }
//            dd($uom_conversion);
                $product->uom_conversion_id = $uom_conversion->id;

            } else {
                if (!isset($request->uom_conversion_id)) {
                    return response()->json([
                        'message' => "Unit of measurement required",
                        'status' => 'error',
                        'errors' => [
                            'uom_conversion_id' => [
                                'Unit conversion required'
                            ]
                        ]
                    ], 422);
                }
                $conversion_id = json_decode($request->uom_conversion_id);
                if (isset($conversion_id->id)) {
                    $product->uom_conversion_id = $conversion_id->id;
                } else {
                    return response()->json([
                        'message' => "Invalid or missing Unit of measurement",
                        'status' => 'error',
                        'errors' => [
                            'uom_conversion_id' => [
                                'Invalid Unit conversion'
                            ]
                        ]
                    ], 422);
                }
            }
            $product->save();
            $this->productconfig->variantAttributes($request, $product->id);
            $this->productconfig->vendorItemMapping($request, $product, $request->v_id);
            $this->productconfig->vendorCatgory($request, $product->id);



            $variantCombinationResult = json_decode($this->productconfig->variantCombination($request, $product->id, $request->auto_sku));
            if ($variantCombinationResult->status == 'error') {
                return response()->json($variantCombinationResult, 422);
            }

            $this->productconfig->addproductattribute($request, $product->id);
            $this->productconfig->addVariantAttributeValueMatrixMapping($this->cartesian($request->variant), $product->id);
            DB::commit();
            return $product;
        } catch(Exception $e) {
            DB::rollback();
            exit;
        }
    }
}
