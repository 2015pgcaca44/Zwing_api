<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Items\VendorItems;
use App\ItemCategory;
use App\Model\Items\VendorSku;
use App\Item;
use Schema;
use DB;
use Log;
use App\Store;
use App\Model\Cluster\Cluster;
use App\Model\Tax\TaxCategory;
use App\Model\Tax\TaxGroup;
use App\Model\Tax\TaxRateGroupMapping;
use App\Model\Tax\TaxRate;
use App\Model\SupplyPriceBook\SupplyPriceBook;
use App\Model\SupplyPriceBook\SupplyPriceBookHeaderLevel;
use App\Model\SupplyPriceBook\SupplyPriceBookDetailedLevel;
use App\Model\SupplyPriceBook\SupplyPriceBookAllocation;
use App\Model\Items\VendorItemPriceMapping;
use App\Model\Items\ItemPrices;
use App\Http\Controllers\CloudPos\CartController;
use App\LastInwardPrice;
use App\Model\Supplier\SupplierAddress;
use App\Model\Charges\ChargeRates;
use App\Model\Charges\ChargeGroup;
use App\Model\Charges\ChargeRateGroupMapping;
use App\Model\Items\VendorSkuDetailBarcode;


class SupplyPriceBookController extends Controller
{
 	public function getPriceFormSPB(Request $request) {

        $this->validate($request, [
            'v_id'                  => 'required',
            'source_node_type'      => 'required',
            'source_node_id'        => 'required',
            'destination_node_type' => 'required',
            'destination_node_id'   => 'required',
            'barcode'               => 'required',
            'quantity'              => 'required|numeric',

        ]);

            $v_id      = $request->v_id; 
            $barcode  = $request->barcode;
            $transaction_type  = $request->transaction_type;
            $source_node_type  = $request->source_node_type;
            $source_node_id  = $request->source_node_id;
            $destination_node_type  = $request->destination_node_type;
            $destination_node_id  = $request->destination_node_id;
            $qty  = $request->quantity;
            $valid_to = date('y-m-d');
            $data=[];
            $data['base_supply_price']=0;  
            $data['discount']=0;
            $data['discount_details']='';
            $data['charge']=0;
            $data['charge_details']=0;
            $taxType['tax']=0;
            $taxType['name']='';
            $data['tax_details']=$taxType;
            $data['total_supply_price']=0;
            if($source_node_type=="Store"){
               $gstFirst= Store::select('gst')->where('v_id',$v_id)->where('store_id',$source_node_id)->first();
               $gstFirst=is_null($gstFirst)?'':substr($gstFirst->gst, 0, 2);
            }elseif($source_node_type=="Supplier"){
                $gstFirst= SupplierAddress::select('gstin')->where('supplier_id',$source_node_id)->first();
                $gstFirst=is_null($gstFirst)?'':substr($gstFirst->gstin, 0, 2);
                
            }
            if($destination_node_type=="Store"){
               $gstSecound= Store::select('gst')->where('v_id',$v_id)->where('store_id',$destination_node_id)->first();
               $gstSecound=is_null($gstSecound)?'':substr($gstSecound->gst, 0, 2); 
            }elseif($destination_node_type=="Supplier"){
                $gstSecound= SupplierAddress::select('gstin')->where('supplier_id',$destination_node_id)->first();
                $gstSecound=is_null($gstSecound)?'':substr($gstSecound->gstin, 0, 2);
            }
            
            if($gstFirst!=$gstSecound && $gstFirst !='' && $gstSecound !='' ){
                $igstTax=true;
            }else{
                $igstTax= false;
            }
            
            $checkSPBAllocation = SupplyPriceBookAllocation::leftJoin('supply_price_book','supply_price_book.spb_id','spb_allocation.spb_id')
                                                            ->select('spb_allocation.spb_id')
                                                            ->where('spb_allocation.v_id',$v_id)
                                                            ->where('spb_allocation.first_node_type',$source_node_type)
                                                            ->where('spb_allocation.first_node_id',$source_node_id)
                                                            ->where('spb_allocation.secound_node_type',$destination_node_type)
                                                            ->where('spb_allocation.secound_node_id',$destination_node_id)
                                                            ->where('spb_allocation.valid_to','>=',$valid_to)
                                                            ->WhereNull('supply_price_book.deleted_at')
                                                            ->Where('supply_price_book.status','1')
                                                            ->orderBy('spb_allocation_id','DESC')
                                                            ->first();
                                                                        
           // $dataFinal=[];  
            //dd($checkSPBAllocation);                                              
            if (is_null($checkSPBAllocation)) {

                $lastInwardParam=['destination_site_id'=>$destination_node_id,'v_id'=>$v_id,'barcode'=>$barcode,'destination_site_type'=>$destination_node_type              ,'source_site_type'=>$source_node_type,'source_site_id'=>$source_node_id];
                return $this->getPriceFromLastInward($lastInwardParam,$qty);
                  
                $spbAllocation = SupplyPriceBookAllocation::where('v_id',$v_id)->where('valid_to','>=',$valid_to)->get();
             }else{
                
                $spbData=SupplyPriceBook::select('spb_type')->where('spb_id',$checkSPBAllocation->spb_id)->where('status','1')->first();



                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();

                $getItemId = null;
                if($bar){
                    $getItemId=VendorSku::select('item_id','hsn_code','vendor_sku_detail_id')->where('v_id',$v_id)->where('vendor_sku_detail_id',$bar->vendor_sku_detail_id)->first();
                    $getItemId->barcode = $bar->barcode;

                }

 
                if(is_null($getItemId)) {
                    return response()->json(['status' => 'error','data'=>$data, 'message' => 'This barcode not exist for this vendor'], 200);
                }
                //check spb type 
                if($spbData->spb_type=="Header_Level" && !empty($getItemId)){

                    $spbHeaderLevelData=SupplyPriceBookHeaderLevel::where('spb_id',$checkSPBAllocation->spb_id)->first();
                    $priceId = VendorItemPriceMapping::select('item_price_id')
                                                    ->where('item_id',$getItemId->item_id)
                                                    ->where('v_id',$v_id)
                                                    ->orderBy('vendor_item_price_mapping.id','desc')
                                                    ->first()->item_price_id;
                    $priceData = ItemPrices::find($priceId);
                    if($spbHeaderLevelData->spb_category=="Custom" && !empty($priceData) ){
                        
                        return $this->calculateCustomPrice($priceData,$spbHeaderLevelData,$getItemId,$qty,$igstTax);
                        
                    }else{

                        $lastInwardParam=['destination_site_id'=>$destination_node_id,'v_id'=>$v_id,'barcode'=>$barcode,'destination_site_type'=>               $destination_node_type,'source_site_type'=>$source_node_type,'source_site_id'=>$source_node_id];
                        return $this->getPriceFromLastInward($lastInwardParam,$qty);

                    }

                }elseif($spbData->spb_type=="Detailed_Level"){
                    //check barcode in detailed level and get price
                    //data check in detailed level in 3 heraricy 1st barcode level 2nd is category 3rd is department

                    $levelOne=SupplyPriceBookDetailedLevel::where('spb_id',$checkSPBAllocation->spb_id)->where('barcode',$barcode)->first();
                    if(!empty($levelOne)){
                        $result= $this->detailedLevelOne($levelOne,$qty,$igstTax);
                        if($result=='0'){
                            
                            return response()->json(['status' => 'error','data'=>$data, 'message' => 'barcode not exist for this source or destination type'], 200);
                        }else{
                            return $result;
                        }
                    }else{
                        //dd($result->getdata()->data);
                        $levelTwo=SupplyPriceBookDetailedLevel::where('spb_id',$checkSPBAllocation->spb_id)->whereNull('barcode')->whereNotNull('category_list')->get();
                        
                        if(count($levelTwo)>0){
                            
                            foreach ($levelTwo as $key => $levelTwoData) {
                                
                                $result=$this->detailedLevelTwo($levelTwoData,$qty,$barcode,$igstTax);
                                if($result !='0'){
                                    return $result;
                                }
                                
                            }

                            $levelThree=SupplyPriceBookDetailedLevel::where('spb_id',$checkSPBAllocation->spb_id)->whereNull('barcode')->whereNull('category_list')->get();
                            foreach ($levelThree as $key => $value) {
                                $result= $this->detailedLevelThree($value->spb_detailed_id,$value->department_id,$v_id,$qty,$barcode,$igstTax);
                                if($result !='0'){
                                    return $result;
                                }
                            }
                            $lastInwardParam=['destination_site_id'=>$destination_node_id,'v_id'=>$v_id,'barcode'=>$barcode,'destination_site_type'=>               $destination_node_type,'source_site_type'=>$source_node_type,'source_site_id'=>$source_node_id];
                             return $this->getPriceFromLastInward($lastInwardParam,$qty);
                            
                        }else{
                            
                            $levelThree=SupplyPriceBookDetailedLevel::where('spb_id',$checkSPBAllocation->spb_id)->whereNull('barcode')->whereNull('category_list')->get();
                            foreach ($levelThree as $key => $value) {

                                $result= $this->detailedLevelThree($value->spb_detailed_id,$value->department_id,$v_id,$qty,$barcode,$igstTax);
                                if($result !='0'){
                                    return $result;
                                }
                            }
                            $lastInwardParam=['destination_site_id'=>$destination_node_id,'v_id'=>$v_id,'barcode'=>$barcode,'destination_site_type'=>               $destination_node_type,'source_site_type'=>$source_node_type,'source_site_id'=>$source_node_id];
                             return $this->getPriceFromLastInward($lastInwardParam,$qty);

                        }
                    }
                    
                }
             }   
                    

        
    }

    public function getPriceFromLastInward($params,$qty) {
        
        $last_inward_price= LastInwardPrice::where('v_id',$params['v_id'])
                                          ->where('barcode',$params['barcode'])
                                          ->where('destination_site_id',$params['destination_site_id'])
                                          ->where('destination_site_type',$params['destination_site_type'])
                                          ->where('source_site_type',$params['source_site_type'])
                                          ->where('source_site_id',$params['source_site_id'])
                                          ->orderBy('id','DESC')
                                          ->first();
        if(is_null($last_inward_price)){
            $data=[];
            $data['base_supply_price']=0;  
            $data['discount']=0;
            $data['discount_details']='';
            $data['charge']=0;
            $data['charge_details']=0;
            $taxType['cgst_rate']='0.0';
            $taxType['cgst_amt']='0.0';
            $taxType['sgst_rate']='0.0';
            $taxType['sgst_amt']='0.0';
            $taxType['igst_rate']='0.0';
            $taxType['igst_amt']='0.0';
            $taxType['cess_rate']='0';
            $taxType['cess_amt']='0';
            $taxType['taxable']='0.0';
            $taxType['hsn_code']='';
            $taxType['tax']     ='0';
            $data['tax_details']=$taxType;
            $data['total_supply_price']=0;
            return response()->json(['status' => 'error','data'=>$data, 'message' => 'price not available for this source or destination type'], 200);
        }else{
            $supplyPrice=$last_inward_price->supply_price;
            $data['base_supply_price']=$last_inward_price->supply_price;
            $data['quantity']=$qty;
            if($qty==0){
                $data['base_supply_price']=$supplyPrice; 
                $data['discount']=0;
                $data['discount_details']='';
                $data['charge']=0;
                $data['charge_details']=0;
                $taxType['cgst_rate']='0.0';
                $taxType['cgst_amt']='0.0';
                $taxType['sgst_rate']='0.0';
                $taxType['sgst_amt']='0.0';
                $taxType['igst_rate']='0.0';
                $taxType['igst_amt']='0.0';
                $taxType['cess_rate']='0';
                $taxType['cess_amt']='0';
                $taxType['taxable']='0.0';
                $taxType['hsn_code']='';
                $taxType['tax']     ='0';
                $data['tax_details']=$taxType;
                $data['total_supply_price']=0;  

            return response()->json(['status' => 'success','data'=>$data, 'message' => 'price get from last inward table'], 200);
            }                                   
            $tax_details=json_decode($last_inward_price->tax_details);
            
            $discount=round($last_inward_price->discount*$qty,2);
            $data['discount']=$discount;
            $data['discount_details']=$last_inward_price->discount_details;
            $charge=round($last_inward_price->charge*$qty,2);
            $data['charge']=$charge;
            $data['charge_details']=$last_inward_price->charge_details;
            $tax=round($last_inward_price->tax*$qty,2);
            //$taxType['taxable']=$supplyPrice;
            $taxType['tax']=$tax;
            $taxType['cgst_rate']=isset($tax_details->cgst)?$tax_details->cgst:0;
            $taxType['cgst_amt']=isset($tax_details->cgstamt)?$tax_details->cgstamt:0;
            $taxType['sgst_rate']=isset($tax_details->sgst)?$tax_details->sgst:0;
            $taxType['sgst_amt']=isset($tax_details->sgstamt)?$tax_details->sgstamt:0;
            $taxType['igst_rate']=isset($tax_details->igst)?$tax_details->igst:0;
            $taxType['igst_amt']=isset($tax_details->igstamt)?$tax_details->igstamt:0;
            $taxType['cess_rate']=isset($tax_details->cess)?$tax_details->cess:0;
            $taxType['cess_amt']=isset($tax_details->cessamt)?$tax_details->cessamt:0;
            $taxType['hsn_code']=isset($tax_details->hsn)?$tax_details->hsn:0;
            $taxType['taxable']=isset($tax_details->taxable)?$tax_details->taxable:0;
            $data['tax_details']=$taxType;
            $supplyPrice=$supplyPrice-$last_inward_price->discount;
            $supplyPrice=$supplyPrice+$last_inward_price->tax;
            $supplyPrice=$supplyPrice+$last_inward_price->charge;
            $data['total_supply_price']=round($supplyPrice*$qty,2);  
           //return $data;
            return response()->json(['status' => 'success','data'=>$data, 'message' => 'price get from last inward table'], 200);
        }

    }   

    public function detailedLevelTwo($levelTwodata,$qty,$barcode,$igstTax) {

        $catCode=json_decode($levelTwodata->category_list)[0]->code;
        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $levelTwodata->v_id)->where('barcode', $barcode)->first();
        $getItemData = null;
        if($bar){
            $getItemData=VendorSku::select('item_id','hsn_code','vendor_sku_detail_id')->where('v_id',$levelTwodata->v_id)->where('cat_code_1',$catCode)
                ->where('department_id',$levelTwodata->department_id)->where('vendor_sku_detail_id',$bar->vendor_sku_detail_id)->first();
            $getItemData->barcode = $bar->barcode;
        }
                              
        if(!empty($getItemData)){

            $priceId = VendorItemPriceMapping::select('item_price_id')
                                    ->where('item_id',$getItemData->item_id)
                                    ->where('v_id',$levelTwodata->v_id)
                                    ->orderBy('vendor_item_price_mapping.id','desc')
                                    ->first()->item_price_id;
            $priceData = ItemPrices::find($priceId);

            return $this->calculateCustomPrice($priceData,$levelTwodata,$getItemData,$qty,$igstTax); 


        }else{
            return '0'; 
        }


    }

    public function detailedLevelThree($id,$department_id,$v_id,$qty,$barcode,$igstTax) {

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $getItemData = null;
        if($bar){
            $getItemData=VendorSku::select('item_id','hsn_code','vendor_sku_detail_id')->where('v_id',$v_id)
                ->where('department_id',$department_id)->where('vendor_sku_detail_id',$bar->vendor_sku_detail_id)->first();
            if($getItemData){
                $getItemData->barcode = $bar->barcode;
            }

        }
                             
        if(!empty($getItemData)){

            $levelThree=SupplyPriceBookDetailedLevel::where('spb_detailed_id',$id)->first();
            $priceId = VendorItemPriceMapping::select('item_price_id')
                                    ->where('item_id',$getItemData->item_id)
                                    ->where('v_id',$levelThree->v_id)
                                    ->orderBy('vendor_item_price_mapping.id','desc')
                                    ->first()->item_price_id;
            $priceData = ItemPrices::find($priceId);
            
            return $this->calculateCustomPrice($priceData,$levelThree,$getItemData,$qty,$igstTax);

        }else{
            return '0';
        }


    }

    public function detailedLevelOne($levelOnedata,$qty,$igstTax) {
        
        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $levelOnedata->v_id)->where('barcode', $levelOnedata->barcode)->first();
        $getItemData = null;
        if($bar){
            $getItemData=VendorSku::select('item_id','hsn_code','vendor_sku_detail_id')->where('v_id',$levelOnedata->v_id)->where('department_id',$levelOnedata->department_id)->where('vendor_sku_detail_id',$bar->vendor_sku_detail_id)->first();
            $getItemData->barcode = $bar->barcode;
        }

        if(!empty($getItemData)){

            $priceId = VendorItemPriceMapping::select('item_price_id')
                                    ->where('item_id',$getItemData->item_id)
                                    ->where('v_id',$levelOnedata->v_id)
                                    ->orderBy('vendor_item_price_mapping.id','desc')
                                    ->first()->item_price_id;
            $priceData = ItemPrices::find($priceId);

            return $this->calculateCustomPrice($priceData,$levelOnedata,$getItemData,$qty,$igstTax);

        }else{
            return '0';
        }


    } 

    public function getIgstTax($taxId,$igstTax) {

        $taxRateId= TaxRateGroupMapping::select('tax_code_id')->where('tax_group_id',$taxId)->where('type','IGST')->first();
        if(is_null($taxRateId)){
            return 0;
        }else{
        $taxRate= TaxRate::select('rate')->where('id',$taxRateId->tax_code_id)->first()->rate;
        return $taxRate;
        }
        

    }
    public function getNormalTax($taxId,$igstTax) {

        $taxRateId= TaxRateGroupMapping::leftJoin('tax_rates','tax_rates.id','tax_rate_group_mapping.tax_code_id')
                                       ->select('tax_code_id','type','rate')->where('tax_group_id',$taxId)->where('type','!=','IGST')->get();
        $rateId=collect($taxRateId)->pluck('tax_code_id');
        $taxRate= TaxRate::select('rate','name')->whereIn('id',$rateId)->get();
        $rateSum=$taxRate->sum('rate');

        foreach ($taxRateId as $key => $value) {
                    
                if($value->type == 'CGST'){
                     $taxData['cgst_rate']=$value->rate;
                }
                if($value->type == 'SGST'){
                     $taxData['sgst_rate']=$value->rate;
                 }
                    
        }
        /*foreach ($taxRate as $key => $value) {
                    $taxData[$value->name]=$value->rate;
                }*/        
        $taxData['rate']=$rateSum;
        
        return $taxData;

    }
    public function getCharge($chargeId,$price) {

        $chargeRateId= ChargeRateGroupMapping::select('charge_rate_id')->where('charge_group_id',$chargeId)->get();
        $rateId=collect($chargeRateId)->pluck('charge_rate_id');
        $taxRate= ChargeRates::select('rate','type')->whereIn('id',$rateId)->where('deleted_by','0')->get();
        $rateSumPersent=$taxRate->where('type','P')->sum('rate');
        $rateSumAmount=$taxRate->where('type','A')->sum('rate');
        $chargeP=0;
        $chargeA=0;
        $totalCharge=0;
        if(count($taxRate)==0){
            return 0;

        }   
        if($rateSumPersent>0){
            $chargeP = round(($rateSumPersent/100)*$price,2);
        }
        if($rateSumAmount>0){  
            $chargeA=$rateSumAmount;
        }
        $totalCharge=$chargeP+$chargeA;
        return $totalCharge;
           
    }
    public function calculateCustomPrice($priceData,$spbHeaderLevelData,$getItemData,$qty,$igstTax){

        $priceType=$spbHeaderLevelData->base_price_type;
        //get mrp or rsp price
        $first_price=($priceType=="RSP")?$priceData->rsp:$priceData->mrp;
        $markup_value = ($spbHeaderLevelData->factor / 100) * $first_price;
        //echo $first_price;
        //dd($markup_value);
        $calculate_price=$spbHeaderLevelData->behaviour=="Markup"?$markup_value+$first_price:$first_price-$markup_value;
        $data['base_supply_price']=$calculate_price;
        $data['quantity']=$qty;
        if($qty==0){
                $data['base_supply_price']=$calculate_price; 
                $data['discount']=0;
                $data['discount_details']='';
                $data['charge']=0;
                $data['charge_details']=0;
                $taxType['tax']=0;
                $taxType['cgst_rate']='0.0';
                $taxType['cgst_amt']='0.0';
                $taxType['sgst_rate']='0.0';
                $taxType['sgst_amt']='0.0';
                $taxType['igst_rate']='0.0';
                $taxType['igst_amt']='0.0';
                $taxType['hsn_code']='';
                $data['tax_details']=$taxType;
                $data['total_supply_price']=0;

            return response()->json(['status' => 'success','data'=>$data, 'message' => 'price from SPB '], 200); 
        }
        //this price is after markup or markdown
        
        if($spbHeaderLevelData->discount_type=="Percentage"){
            $discount_price = ($spbHeaderLevelData->discount_value/100)*$calculate_price;
            $data['discount']=round($discount_price,2)*$qty;
            $calculate_price=$calculate_price-$discount_price;
            $data['discount_details']=$spbHeaderLevelData->discount_value.' Percentage';
        }else{
            $data['discount']=$spbHeaderLevelData->discount_value*$qty;
            $calculate_price=$calculate_price-$spbHeaderLevelData->discount_value;
            $data['discount_details']=$spbHeaderLevelData->discount_value.' Amount';
        } 

        if($spbHeaderLevelData->tax_type=="Custom"){ 
                
            /*$taxGroup= TaxGroup::where('id',$spbHeaderLevelData->tax_code)->with('taxRate')->first();
            $taxrate=$taxGroup->taxRate;
            foreach ($taxrate as $key => $value) {
                    $taxType[$value->name]=$value->rate;
                }
                $rateSum=$taxrate->sum('rate');*/
            $igstRate=$this->getIgstTax($spbHeaderLevelData->tax_code,$igstTax);
            
            if($igstTax==true){
                if($igstRate==0){
                    $data['base_supply_price']=0;   
                    $data['discount']=0;
                    $data['discount_details']='';
                    $data['charge']=0;
                    $data['charge_details']=0;
                    $taxType['cgst_rate']='0.0';
                    $taxType['cgst_amt']='0.0';
                    $taxType['sgst_rate']='0.0';
                    $taxType['sgst_amt']='0.0';
                    $taxType['igst_rate']='0.0';
                    $taxType['igst_amt']='0.0';
                    $taxType['taxable']='0.0';
                    $taxType['hsn_code']='';
                    $data['tax_details']=$taxType;
                    $data['total_supply_price']=0;
                    return response()->json(['status' => 'error','data'=>$data, 'message' => 'Igst tax rate not available for this tax group please create IGST tax rate'], 200);
                }
                $rateSum=(float)$igstRate;
                $taxType['cgst_rate']='0.0';
                $taxType['cgst_amt']='0.0';
                $taxType['sgst_rate']='0.0';
                $taxType['sgst_amt']='0.0';
                $taxType['igst_rate']=$igstRate;
                $taxType['igst_amt']='0.0';
                
            }else{
                $taxType=$this->getNormalTax($spbHeaderLevelData->tax_code,$igstTax);
                $rateSum=$taxType['rate'];
                unset($taxType['rate']);
                $taxType['sgst_amt']='0.0';
                $taxType['cgst_amt']='0.0';
                $taxType['cess_rate']='0.0';
                $taxType['cess_amt']='0.0';
                $taxType['igst_rate']='0.0';
                $taxType['igst_amt']='0.0';
            }
            
            //$data['tax_details']=$taxType;
            $taxPrice = round(($rateSum / 100) * $calculate_price,2);

            $taxType['taxable']=$calculate_price;
            $calculate_price=$taxPrice+$calculate_price;
            $taxType['tax']=$taxPrice*$qty;
            $taxType['tax_type']='EXC';
            $taxType['hsn_code']=$getItemData->hsn_code;
            $data['tax_details']=$taxType;
           // $data['final_price']=round($calculate_price*$qty,2);
            
            //return response()->json(['status' => 'sucess','data'=>$data, 'message' => 'price from SPB with custom tax'], 200);
 
        }else{
            
            $taxRequest = new \Illuminate\Http\Request();
            
            $taxRequest->merge([
                                'hsn_code'=>$getItemData->hsn_code,
                                'v_id'=>$spbHeaderLevelData->v_id,
                                'barcode'=>$getItemData->barcode,
                                'tax_type'=>'EXC',
                                's_price'=>$calculate_price,
                                'qty'=>$qty
                             ]);
            $cart =  new    CartController;
            $taxData=$cart->taxCalApi($taxRequest)->getData()->data;
            
            $taxType['cgst_rate']   = !empty($taxData->cgst)?$taxData->cgst:'0.0';
            $taxType['sgst_rate']   = !empty($taxData->sgst)?$taxData->sgst:'0.0';
            $taxType['igst_rate']   = !empty($taxData->igst)?$taxData->igst:'0.0'; 
            $taxType['sgst_amt']    = !empty($taxData->sgstamt)?$taxData->sgstamt:'0.0';
            $taxType['cgst_amt']    = !empty($taxData->cgstamt)?$taxData->cgstamt:'0.0';
            $taxType['cess_rate']   = !empty($taxData->cess)?$taxData->cess:'0.0';
            $taxType['cess_amt']    = !empty($taxData->cessamt)?$taxData->cessamt:'0.0';
            $taxType['igst_amt']    = !empty($taxData->igstamt)?$taxData->igstamt:'0.0';
            $taxType['taxable']=$taxData->taxable;
            $taxType['tax']=$taxData->tax*$qty;
            $taxType['tax_name']=$taxData->tax_name;
            $taxType['tax_type']=$taxData->tax_type;
            $taxType['hsn_code']=$getItemData->hsn_code;
            $data['tax_details']=$taxType;
            $calculate_price=$taxData->total>0?$taxData->total:$calculate_price;
           
        }

        $charge=$this->getCharge($spbHeaderLevelData->charge_code,$calculate_price);
        if($charge==0 && $spbHeaderLevelData->charge_code !=''){
            $data['base_supply_price']=0;  
            $data['discount']=0;
            $data['discount_details']='';
            $data['charge']=0;
            $data['charge_details']=0;
            $taxType['tax']=0;
            $taxType['name']='';
            $taxType['hsn_code']='';
            $data['tax_details']=$taxType;
            $data['total_supply_price']=0;
            return response()->json(['status' => 'error','data'=>$data, 'message' => 'Charge rate not available for this vendor'], 200);
        }else{
            $calculate_price=$charge+$calculate_price;
            $data['charge']=$charge*$qty;
            $data['charge_details']='';
            $data['total_supply_price']=round($calculate_price*$qty,2);
            return response()->json(['status' => 'success','data'=>$data, 'message' => 'price from SPB '], 200);  
        }
        
    }
 	
       
}
