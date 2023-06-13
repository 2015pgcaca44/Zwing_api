<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Items\ItemBrand;
use App\Items\ItemDepartment;
use App\Items\ItemPrices;
use App\Items\VendorItemPriceMapping;
use App\Model\Item\Uom;
use App\Model\Item\UomConversions;
use Illuminate\Http\Request;
use App\ItemAttributes;
use App\ItemAttributesValues;
use App\itemAttributeValueMapping;

use App\Items\ItemMediaAttributes;
use App\Items\ItemMediaAttributeValues;
use App\ItemcategoryIds;

/*Vendor Model*/

use App\Items\VendorItems;
use App\Items\VendorItemAttributes;
use App\Items\VendorItemAttributeValueMapping;
use App\Items\VendorItemCategoryIds;
use App\Items\VendorItemCategoryMapping;
use App\Items\VendorItemMediaAttribute;
use App\Items\VendorItemMediaAttributeValueMapping;


/*Variants*/
use App\Items\ItemVariantAttributes;
use App\Items\ItemVariantAttributeValues;
use App\Items\VendorItemVariantAttributes;
use App\Items\VendorItemVariantAttributeValueMapping;
use App\Items\VendorItemVariantAttributeValueMatrixMapping;
use App\Items\VendorSkuDetails;

use Auth;

class ProductconfigController extends Controller
{
	public function __construct()
	{
	   // $this->middleware('auth:web');
	}

	public function vendor_id(){
 		return 27;
 	}

    public function helo(){
    	return "Hello sanjeev";
    }


    /* # Add Product attribute 
	   # Add Product Value 
	   # Mapping attribute, value with item_id
	*/
    public function addproductattribute($request,$item_id){
		//print_r($request->product_attributes); die;
		if($request->product_attributes){
            $request->product_attributes = json_decode(json_encode($request->product_attributes), true);

            foreach($request->product_attributes as $fetch){
				if(!empty($fetch['attributes'])){
					$pattribute    = ItemAttributes::where('name',$fetch['attributes'])->first(); 
					if(!$pattribute){
						$pattribute       = new ItemAttributes;
						$pattribute->name =  $fetch['attributes'];
						$pattribute->save();
					}
					$this->vendorItemAttribute($pattribute->id,$this->vendor_id());  //Mapping attribute with vendor

					$pattributeval    = ItemAttributesValues::where('value',$fetch['value'])
                        ->where('type', $fetch['type'])
                        ->first();
					if(!$pattributeval){
						$pattributeval 	  = new ItemAttributesValues;
						$pattributeval->value =  $fetch["value"];
						$pattributeval->type =  $fetch["type"];
						$pattributeval->save();
					}

					/*$pattvalmapping          = itemAttributeValueMapping::where('item_id',$item_id)->where('item_attribute_id',$pattribute->id)->where('item_attribute_value_id',$pattributeval->id)->first();
					if(!$pattvalmapping){
						$pattvalmapping			 = new itemAttributeValueMapping;
						$pattvalmapping->item_id = $item_id;
						$pattvalmapping->item_attribute_id  = $pattribute->id;
						$pattvalmapping->item_attribute_value_id =  $pattributeval->id;
						$pattvalmapping->save();
					}*/
//					echo($fetch['id']);
					if(isset($fetch['id'])) {
//					    echo("Here");
                         VendorItemAttributeValueMapping::find($fetch['id'])->delete();
                    }
                    if(!$fetch['deleted']) {
//                        echo(json_encode($fetch));
                        $this->vendorItemAttributeValue($item_id, $pattribute->id, $pattributeval->id, $this->vendor_id());
                    }
//                    echo(json_encode($fetch));
                }
			}
		}
	}
	//End of addproductattribute


	/* #Vendor Category mapping 
	   #Vendor Category mapping with item*/

	public function vendorCatgory($request,$item_id){
		if($request->category){
            $request->category = json_decode(json_encode($request->category), true);

            VendorItemCategoryMapping::where('v_id',$this->vendor_id())->where('item_id',$item_id)->delete();
			foreach($request->category as $fetching) {
				$fetch = json_decode($fetching,true);
				$vendorCategory  = VendorItemCategoryIds::where('v_id',$this->vendor_id())->where('item_category_id',$fetch['code'])->first();
				if(!$vendorCategory) {
					$vendorCategory 		= new VendorItemCategoryIds;
					$vendorCategory->v_id 	= $this->vendor_id();
					$vendorCategory->item_category_id 	= $fetch['code'];
					$vendorCategory->save();
				}

				$vendorCategoryMap =  VendorItemCategoryMapping::where('v_id',$this->vendor_id())->where('item_id',$item_id)->where('item_category_id',$fetch['code'])->first();

//				echo(json_encode($fetch));
				if(!$vendorCategoryMap) {
//				    echo "Here";
					$vendorCategoryMap 				= new VendorItemCategoryMapping;
					$vendorCategoryMap->v_id 		= $this->vendor_id();
					$vendorCategoryMap->item_id 	= $item_id;
					$vendorCategoryMap->item_category_id 	= $fetch['code'];
					$vendorCategoryMap->save();
				}

				/*$itemCategory = ItemcategoryIds::where('item_category_id',$fetch['code'])->where('item_id',$item_id)->first();
				if(!$itemCategory){
					$itemCategory = new ItemcategoryIds;
					$itemCategory->item_category_id = $fetch['code'];
					$itemCategory->item_id = $item_id;
					$itemCategory->save();
				}*/
			}
		}
	}//
	/*End of this function*/


	/*Mapping product with vendor*/
	public function vendorItemMapping($product,$vendor_id){

		$vendoritem  =  VendorItems::where('item_id',$product->id)->where('v_id',$vendor_id)->first();
		if(!$vendoritem){
			$vendoritem  		 =  new VendorItems;
			$vendoritem->v_id    = $vendor_id;
			$vendoritem->item_id = $product->id;
			$vendoritem->short_description = $product->short_description;
			$vendoritem->long_description = $product->long_description;
			$vendoritem->mrp = $product->mrp;
			$vendoritem->rsp = $product->rsp;
			$vendoritem->special_price = $product->special_price;
			$vendoritem->deleted = $product->deleted;
			$vendoritem->sku     = $product->sku;
			$vendoritem->item_code = (int)$vendor_id.''.$product->id;
			$vendoritem->hsn_code = $product->hsn_code;
			$vendoritem->uom_conversion_id = $product->uom_conversion_id;
			$vendoritem->department_id = $product->department_id;
			$vendoritem->brand_id      = $product->brand_id;
			$vendoritem->has_batch = $product->has_batch;
			$vendoritem->has_serial =  $product->has_serial;
			$vendoritem->tax_group_id =  $product->tax_group_id;
			$vendoritem->ref_item_code = $request->ref_item_code;
			if(isset($request->tax_type)){
			$vendoritem->tax_type = $request->tax_type;
			}
			$vendoritem->save();
		}else{
			$vendoritem->sku     = $product->sku;
			$vendoritem->item_code = (int)$vendor_id.''.$product->id;
			$vendoritem->short_description = $product->short_description;
			$vendoritem->long_description = $product->long_description;
			$vendoritem->mrp = $product->mrp;
			$vendoritem->rsp = $product->rsp;
			$vendoritem->special_price = $product->special_price;
			$vendoritem->deleted = $product->deleted;
			$vendoritem->hsn_code = $product->hsn_code;
			$vendoritem->uom_conversion_id = $product->uom_conversion_id;
			$vendoritem->department_id = $product->department_id;
			$vendoritem->brand_id      = $product->brand_id;
			$vendoritem->has_batch = $product->has_batch;
			$vendoritem->has_serial =  $product->has_serial;
			$vendoritem->tax_group_id =  $product->tax_group_id;
			$vendoritem->ref_item_code = $request->ref_item_code;
			if(isset($request->tax_type)){
				$vendoritem->tax_type = $request->tax_type;
			} 
 			$vendoritem->save();
		}
	}
	/*End of function*/

	/*Mapping attribute with vendor which use vendor*/
	public function vendorItemAttribute($item_attribute_id,$vendor_id){
		$vendorattribute  = VendorItemAttributes::where('v_id',$vendor_id)->where('item_attribute_id',$item_attribute_id)->first();
		if(!$vendorattribute){
			$vendorattribute  = new VendorItemAttributes;
			$vendorattribute->item_attribute_id  =  $item_attribute_id;
			$vendorattribute->v_id = $vendor_id;
			$vendorattribute->save();
		}
	}
	/*End of function*/

	/*Mapping attribute,value with vendor */
	public function vendorItemAttributeValue($item_id, $item_attribute_id, $item_attribute_value_id, $vendor_id)
	{
		$vendorAttributValue  = VendorItemAttributeValueMapping::where('item_id',$item_id)
            ->where('item_attribute_id',$item_attribute_id)
            ->where('item_attribute_value_id',$item_attribute_value_id)
            ->where('v_id',$vendor_id)
            ->first();
		if(!$vendorAttributValue){
			$vendorAttributValue  					= new VendorItemAttributeValueMapping;
			$vendorAttributValue->v_id 				=  $vendor_id;
			$vendorAttributValue->item_id  			=  $item_id;
			$vendorAttributValue->item_attribute_id =  $item_attribute_id;
			$vendorAttributValue->item_attribute_value_id = $item_attribute_value_id;
			$vendorAttributValue->save();
		}
	}
	//End of function

	/*  Veriant Attribute value Add
		Veriant Attribute mapping with vendor
		Veriant Attribute, value , item_id mapping with vendor

	*/

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

	public function variantAttributes($request,$item_id){
		if($request->variant){
            $request->variant = json_decode(json_encode($request->variant), true);
            VendorItemVariantAttributeValueMapping::where('v_id',$this->vendor_id())->where('item_id',$item_id)->delete();
			foreach($request->variant as $fetch){
				$json = json_decode($fetch,true);
				if(@$json['attributes'] && count(@$json['variant_value']) > 0){
					$item_variant_attribute_id = $this->getVariantAttributeId($json['attributes']);

//					dd($item_variant_attribute_id);
					
					/*Vendor attribute mapping */
					$vendorVariantAttribute   = VendorItemVariantAttributes::where('v_id',$this->vendor_id())
                        ->where('item_variant_attribute_id',$item_variant_attribute_id)
                        ->first();

					if(!$vendorVariantAttribute){
						$vendorVariantAttribute       = new VendorItemVariantAttributes;
						$vendorVariantAttribute->v_id = $this->vendor_id();
						$vendorVariantAttribute->item_variant_attribute_id = $item_variant_attribute_id;
						$vendorVariantAttribute->save();
					}

					foreach ($json['variant_value'] as $item) {
						/* Add Variant attribute value*/
						$ItemVariantAttributesValue 	= ItemVariantAttributeValues::where('value',$item['name'])->first();
						if(!$ItemVariantAttributesValue){
							$ItemVariantAttributesValue  = new ItemVariantAttributeValues;
							$ItemVariantAttributesValue->value = $item['name'];
							$ItemVariantAttributesValue->save();
						}
						
						/*Mapping with vendor, item_id, attribute_id, value_id,*/

						$VendorItemVariantAttributesValue  = VendorItemVariantAttributeValueMapping::where('v_id',$this->vendor_id())->where('item_id',$item_id)->where('item_variant_attribute_id',$item_variant_attribute_id)->where('item_variant_attribute_value_id',$ItemVariantAttributesValue->id)->first();
						
						if(!$VendorItemVariantAttributesValue){
							$VendorItemVariantAttributesValue          = new VendorItemVariantAttributeValueMapping;
							$VendorItemVariantAttributesValue->v_id    = $this->vendor_id();
							$VendorItemVariantAttributesValue->item_id = $item_id;
							$VendorItemVariantAttributesValue->item_variant_attribute_id 	   = $item_variant_attribute_id;
							$VendorItemVariantAttributesValue->item_variant_attribute_value_id = $ItemVariantAttributesValue->id;
							$VendorItemVariantAttributesValue->save();
						}
					}
					
				}					 

			}
		}
	} //End of veriantAttributes



	/*Variant Combination */
	public function variantCombination($request, $item_id, $auto_sku){
		// dd($request->variant_products);
		if($request->variant_products) {
		    if(!$auto_sku) {
                if (count(collect($request->variant_products)->unique('sku')) != count($request->variant_products)) {
                    return json_encode([
                        'message' => "Variant SKU should be unique",
                        'status' => 'error',
                    ], 409);
                }

                $request->variant_products = json_decode(json_encode($request->variant_products), true);

                foreach ($request->variant_products as $fetch) {
                    if (VendorSkuDetails::where('v_id', $this->vendor_id())
                        ->where('item_id', '!=', $item_id)
                        ->where('sku', $fetch['sku'])
                        ->first()) {
                        return json_encode([
                            'message' => "Variant SKU should be unique",
                            'status' => 'error',
                        ], 409);
                    }
                }
            }

            $request->variant_products = json_decode(json_encode($request->variant_products), true);

            foreach($request->variant_products as $fetching){
            	$fetch = json_decode($fetching,true);
                $vrcombination  = $fetch['vrname'];
				$barcode        = $fetch['vrbarcode'];
				$qty            = $fetch['vrqty'];
				if($auto_sku) {
				    $sku = $item_id.'-'.$fetch['vrname'];
                } else {
                    $sku = $fetch['sku'];
                }
				$hsn_code       = $fetch['hsn_code'];

                $tax_group_id = !empty($fetch['tax_group_id'])? $fetch['tax_group_id']['id']: null;

                if($hsn_code) $hsn_code = $hsn_code['hsn_code'];
				$prices         = $fetch['variant_prices'];
				$VendorSku   	=  VendorSkuDetails::where('v_id',$this->vendor_id())
                    ->where('item_id',$item_id)
                    ->where('variant_combi', $vrcombination)->first();
				if(!$VendorSku){
					$VendorSku   		=  new VendorSkuDetails;
					$VendorSku->v_id  	= $this->vendor_id();
					$VendorSku->item_id = $item_id;
					$VendorSku->barcode = $barcode;
					$VendorSku->qty     = $qty;
					$VendorSku->variant_combi = $vrcombination;
					$VendorSku->sku     = $sku;
					$VendorSku->hsn_code     = $hsn_code;
					$VendorSku->tax_group_id     = $tax_group_id;
                    if(isset($fetch['variant_prices'])) {
                        $this->addVariantPrice($this->vendor_id(), $item_id, $vrcombination, $prices);
                    }
                    $VendorSku->save();
				} else {
					$VendorSku->item_id 	  = $item_id;
					$VendorSku->variant_combi = $vrcombination;
					$VendorSku->qty     	  = $qty;
                    $VendorSku->sku     = $sku;
                    $VendorSku->hsn_code     = $hsn_code;
                    $VendorSku->sku     = $sku;
                    $VendorSku->hsn_code     = $hsn_code;
                    $VendorSku->tax_group_id     = $tax_group_id;
                    if(isset($fetch['variant_prices'])) {
                        $this->addVariantPrice($this->vendor_id(), $item_id, $vrcombination, $prices);
                    }
					$VendorSku->save();
				}
			}

            return json_encode([
                'message' => "Variant added successfully",
                'status' => 'success',
            ], 409);
		}
	}//End of variantCombination

    public function addVariantPrice($v_id, $item_id, $combination, $prices) {
	    VendorItemPriceMapping::where('v_id', $v_id)
            ->where('item_id', $item_id)
            ->where('variant_combi', $combination)
            ->delete();
        foreach ($prices as $price) {
//            dd($price);
            $itemPrice = ItemPrices::select('id')->where('mrp', '=', $price['mrp'])
                ->where('rsp', '=', $price['rsp'])
                ->where('special_price', '=', $price['special_price'])
                ->first();
//            dd($itemPrice);

            if(!$itemPrice) {
//                dd("HEE");
                $itemPrice = new ItemPrices;
                $itemPrice->mrp = $price['mrp'];
                $itemPrice->rsp = $price['rsp'];
                $itemPrice->special_price = $price['special_price'];
                $itemPrice->save();
            }
//            dd(($itemPrice));
            $priceMapping = new VendorItemPriceMapping;
            $priceMapping->v_id = $v_id;
            $priceMapping->item_id = $item_id;
            $priceMapping->variant_combi = $combination;
            $priceMapping->item_price_id = $itemPrice->id;
            $priceMapping->save();
	    }
    }

 	/* #Add Media for item
 	   #Add Media value for item (Eg. image/video path)
 	   #Media Attribute mapping with vendor
 	   #Media Attribute, Value mapping with vendor
 	*/
 	public function addMediaAttribute($attribute,$attribute_value,$item_id){

 		if($attribute){
 			$itemMediaAttribute = ItemMediaAttributes::where('name',$attribute)->first();
 			if(!$itemMediaAttribute){
 				$itemMediaAttribute 	  = new ItemMediaAttributes;
 				$itemMediaAttribute->name = $attribute;
 				$itemMediaAttribute->save();
 			}
 			 #Add Media value for item (Eg. image/video path)
 			$itemMediaAttributeValue = ItemMediaAttributeValues::where('value',$attribute_value)->first();
 			if(!$itemMediaAttributeValue){
 				$itemMediaAttributeValue 		= new ItemMediaAttributeValues;
 				$itemMediaAttributeValue->value = $attribute_value;
 				$itemMediaAttributeValue->save();
 			}

 			#Media Attribute mapping with vendor
 			$vendorMediaAttribute   = VendorItemMediaAttribute::where('v_id',$this->vendor_id())->where('item_media_attribute_id',$itemMediaAttribute->id)->first();
 			if(!$vendorMediaAttribute){
 				$vendorMediaAttribute = new VendorItemMediaAttribute;
 				$vendorMediaAttribute->v_id = $this->vendor_id();
 				$vendorMediaAttribute->item_media_attribute_id = $itemMediaAttribute->id;
 				$itemMediaAttribute->save();
 			}

 			#Media Attribute, Value mapping with vendor
 			$vendorMediaMapping = VendorItemMediaAttributeValueMapping::where('v_id',$this->vendor_id())->where('item_id',$item_id)->where('item_media_attribute_id',$itemMediaAttribute->id)->where('item_media_attribute_value_id',$itemMediaAttributeValue->id)->first();
 			if(!$vendorMediaMapping){
 				$vendorMediaMapping       = new VendorItemMediaAttributeValueMapping;
 				$vendorMediaMapping->v_id = $this->vendor_id();
 				$vendorMediaMapping->item_id = $item_id;
 				$vendorMediaMapping->item_media_attribute_id = $itemMediaAttribute->id;
 				$vendorMediaMapping->item_media_attribute_value_id = $itemMediaAttributeValue->id;
 				$vendorMediaMapping->save();
 			}
 		}
 	}//End of addMediaAttribute

    public function addVariantAttributeValueMatrixMapping($matrix, $item_id) {
 	    foreach ($matrix as $key => $value) {
 	        $mat = VendorItemVariantAttributeValueMatrixMapping::where('v_id', $this->vendor_id())
                ->where('item_id', $item_id)
                ->where('variant_combi', $key)->first();
 	        if(!$mat) {
                foreach ($value as $attr => $name) {
                    $mat = new VendorItemVariantAttributeValueMatrixMapping;
                    $mat->v_id = $this->vendor_id();
                    $mat->item_id = $item_id;
                    $mat->variant_combi = $key;
                    $mat->item_variant_attribute_id = $attr;
                    $mat->item_variant_attribute_value_id = $name;
                    $mat->save();
 	            }
            }
        }
    }

    public function getDepartmentId($department_name) {
        $department = ItemDepartment::where('name', $department_name)->first();
        if(!$department) {
            $department = new ItemDepartment;
            $department->name = $department_name;
            $department->save();
        }
        return $department->id;
    }

    public function getBrandId($brand_name) {
        $brand = ItemBrand::where('name', $brand_name)->first();
        if(!$brand) {
            $brand = new ItemBrand();
            $brand->name = $brand_name;
            $brand->save();
        }

        return $brand->id;
    }

    public function getUomId($name) {
// 	    dd(isset($name->name));
 	    if($name && isset($name->name) && isset($name->code)) {
            $uom = Uom::where('name', $name->name)
                ->where('code', $name->code)
                ->first();
            if(!$uom) {
                if(Uom::where('code', $name->code)->first()) {
                    return json_encode([
                        'message' => "Code already taken",
                        'status' => 'error',
                        'errors' => [
                            'new_uom_code' => [
                                'Code '.$name->code.' already taken'
                            ]
                        ]
                    ], 422);
                }
                $uom = Uom::create([
                    'name' => $name->name,
                    'code' => $name->code
                ]);
            }
            return json_encode([
                'id' => $uom->id,
                'message' => "UOM Found",
                'status' => 'success'
            ], 200);
        } else {
            return json_encode([
                'message' => "Purchase or Selling UOM is missing",
                'status' => 'error',
                'errors' => [
                    'new_uom' => [
                        'Purchase and selling uom is required'
                    ]
                ]
            ], 422);
        }
    }

    public function createUomConversion($purchase, $selling, $factor) {
        $purchase_id = json_decode($this->getUomId($purchase));
        if($purchase_id->status == 'error') {
            return json_encode($purchase_id);
        }

        $selling_id = json_decode($this->getUomId($selling));
        if($selling_id->status == 'error') {
            return json_encode($selling_id);
        }

        if($factor <= 0) {
            return json_encode([
                'message' => "Invalid Unit of measurement factor",
                'status' => 'error',
                'errors' => [
                    'uom_factor' => [
                        'Invalid Unit of measurement factor'
                    ]
                ]
            ], 422);
        }

//        dd($factor);

        $conversion = UomConversions::where('v_id', Auth::user()->v_id)
            ->where('purchase_uom_id', $purchase_id->id)
            ->where('sell_uom_id', $selling_id->id)
            ->where('factor', $factor)
            ->first();
        if(!$conversion) {
            $conversion = UomConversions::create([
                'v_id' => Auth::user()->v_id,
                'purchase_uom_id' => $purchase_id->id,
                'sell_uom_id' => $selling_id->id,
                'factor'    => $factor
            ]);
        }

        return json_encode([
            'id' => $conversion->id,
            'message' => "UOM Conversion ID",
            'status' => 'success'
        ], 200);
    }

}//End of class
