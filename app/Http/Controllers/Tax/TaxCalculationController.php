<?php

namespace App\Http\Controllers\Tax;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use Event;
use App\Store;
use Carbon\Carbon;
use App\Model\Tax\TaxRate;
use App\Model\Tax\TaxGroupPresetDetails;
use App\Model\Tax\TaxGroup;
use App\Model\GiftVoucher\GiftVoucherGroup;



class TaxCalculationController extends Controller
{
    public function __construct()
    {
       
    }
    
    public function taxCalculation($params){
        
        $data      = array();
        $qty       = 1;
        $mrp       = $params['sale_value'];
        $hsncode  = $params['hsncode']; 
        $group_id  = $params['tax_group_id'];
        $tax_type  = $params['tax_type']; 
        $v_id      = $params['v_id'];
        $gv_group_id = $params['gv_group_id'];
        $voucher_code = $params['voucher_code'];
        $invoice_type = !empty($params['invoice_type'])?$params['invoice_type']:'B2C';
        $total = 0;
        $taxable_amount= 0; 
        $tax_amount   =  0;
        $taxdisplay   =  0;
        $from_gstin   =  ''; 
        $to_gstin     =  ''; 
        $igst_flag    =  false;
        $current_date =  date('Y-m-d h:i:s');
        //$gv_group     = GiftVoucherGroup::with("groups")->first();
        $taxData = [];
        $taxInfo = [];
        $gv_group = GiftVoucherGroup::select('hsncode','tax_group_id','gv_group_id')
                                        ->where(['gv_group_id'=> $gv_group_id,'v_id'=>$v_id])->with(['groups'=>function($query) use($v_id){
                                        $query->where('v_id',$v_id);
                                        }])->first();
        if(!$gv_group->groups){
            //return group not exist
        }

        if($gv_group->groups){
                
                
            if(isset($gv_group->group) && ($current_date >= $gv_group->groups->effective_from  &&  $current_date <= $gv_group->groups->valid_upto) ){
                
                if($gv_group->groups->has_slab == '0'){
                    $grouRate = $gv_group->group;                               
                }
                if($gv_group->groups->has_slab == '1'){
               
          /*          $getSlabmrp =  $this->getTaxSlabMrpNew($mrp,$item_master->tax, $from_gstin, $to_gstin, $invoice_type);
                    if($getSlabmrp){
                        $tempmrp = $getSlabmrp;
                    }*/
                    $getSlab   = $gv_group->slab->where('amount_from','<=',$mrp)->where('amount_to','>=',$mrp)->first();
                    if($getSlab){
                        $grouRate  = $getSlab->ratemap;
                    }

                    $grouRate = $gv_group->group;    


                }//End slab condition
                
                if(isset($grouRate) && count($grouRate) > 0){
                    $taxData = [];
                    foreach ($grouRate as $item) {
                        $rateData   = TaxRate::find($item->tax_code_id);
                        $presetData = TaxGroupPresetDetails::find($item->tg_preset_detail_id);     
                        $taxData[$presetData->preset_name]  = $rateData->rate;
                    }                       
                }
                if($qty > 0){

                    $taxInfo = [];
                    if($tax_type == 'Exclusive'){

                        $sumAllTax = array_sum($taxData);

                        foreach ($taxData as $key => $value) {
                           //$taxAmt  =  $mrp / ( 100 + $sumAllTax) * $value;
                           $taxAmt  =  $this->calculatePercentageAmt($value,$mrp);
                           //$taxInfo[$key.'_amt']  = round($taxAmt,2);
                           $taxInfo[$key.'_amt']  = $taxAmt;
                           

                        }
                        $tax_amount  = array_sum($taxInfo);
                        $tax_amount  = $this->formatValue($tax_amount);
                        $taxable_amount = floatval($mrp);// - floatval($tax_amount);
                        $taxable_amount = $this->formatValue($taxable_amount);
                        $total          = $taxable_amount + $tax_amount;
                        $tax_name       = $gv_group->groups->name;
                        
                    }else{
                    
                        $sumAllTax = array_sum($taxData);
                        
                        foreach ($taxData as $key => $value) {

                           $taxAmt  =  $mrp / ( 100 + $sumAllTax) * $value;
                           
                           //2.0654761904762
                           //$taxInfo[$key.'_amt']  = round($taxAmt,2);
                           $taxInfo[$key.'_amt']  = $taxAmt;
                        }
                        //print_r($taxInfo);
                        
                        $tax_amount  = array_sum($taxInfo);
                        $tax_amount  = $this->formatValue($tax_amount);
                        //dd($tax_amount);
                        $taxable_amount = floatval($mrp) - floatval($tax_amount);
                        $taxable_amount = $this->formatValue($taxable_amount);
                        $total          = $taxable_amount + $tax_amount;
                        $tax_name       = $gv_group->groups->name;
                        
                 
                    }
                }

            }else{

                $taxData['CGST']  = 0;
                $taxData['SGST']  = 0;
                $taxInfo['CGST_amt']  = 0;
                $taxInfo['SGST_amt']  = 0; 
                $sumAllTax = array_sum($taxData);
                $tax_name  = 'Tax ';
            }

            $taxable_amount = $total - $tax_amount; 
            $tax_amount     = $total - $taxable_amount;
            $taxdisplay     = $sumAllTax;
            
        }else{
            $taxData['CGST']  = 0;
            $taxData['SGST']  = 0;
            $taxInfo['CGST_amt']  = 0;
            $taxInfo['SGST_amt']  = 0; 
            $sumAllTax = array_sum($taxData);
            $tax_name  = 'Tax ';

        }
        if(count($taxData) > 0){
          foreach($taxData as $key => $val){
           $data[$key]  = $val;
          }
        }
        if(count($taxInfo) > 0){
         foreach($taxInfo as $key => $val){
          $data[$key]  = $val;
         }
        }
        $data['hsn']     = $hsncode;
        $data['voucher_code'] = $voucher_code;
        $data['gv_group_id'] = $gv_group_id;
        $data['netamt']  = $mrp;  //$mrp * $qty,
        $data['taxable'] = (string)$taxable_amount;
        $data['tax']     = (string)$tax_amount;
        $data['total']   = (string)$total;
        $data['tax_name']= $tax_name.' '.$taxdisplay.'%';
        $data['total_tax_per'] = $sumAllTax;
        $data['tax_type']= $tax_type=="Exclusive"?'EXC':'INC';
        

        return $data ;                                    


    }
    
    /*public function taxCalculationByHsncode($params){

        $store_id  = $params['store_id']; 
        $v_id      = $params['v_id'];
        $hsncode = $params['hsncode'];
        $gv_group_id = $params['gv_group_id'];
        $voucher_code = $params['voucher_code'];
        $mrp       = $params['sale_value'];
        $tax_type  = $params['tax_type']; 

        $group_data = DB::table('tax_hsn_cat')->select('cat_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('hsncode', $hsncode)->where('deleted_by', '0')->first();
        if(isset($group_data)){
        
            $exists = TaxGroup::where('v_id',$v_id)->where('id',$group_data->cat_id)->whereNull('deleted_at')->exists();
            if($exists){

                GiftVoucherGroup::where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->update(['tax_group_id' => $group_data->cat_id]);
                $params['tax_group_id']=$group_data->cat_id;
              return  $this->taxCalculationByGroup($params);
            }else{

            }
        }else{
            $data['voucher_code'] = $voucher_code;
            $data['gv_group_id'] = $gv_group_id;
            $data['netamt']  = $mrp;  //$mrp * $qty,
            $data['taxable'] = $mrp;
            $data['tax']     = 0;
            $data['total']   = $mrp;
            $data['tax_name']= '';
            $data['total_tax_per'] = '';
            $data['tax_type']= $tax_type;

            return $data ; 
        }

    }*/
    private function calculatePercentageAmt($percentage,$amount){
        if(isset($percentage)  && isset($amount)){
            $result = ($percentage / 100) * $amount;
            return $result;
            //return round($result,2);
        }
    }

    public function formatValue($value)
    {
        if (is_float($value) && $value != '0.00') {
            $tax = explode(".", $value);
            if (count($tax) == 1) {
                $strlen = 1;
            } else {
                $strlen = strlen($tax[1]);
            }
            if ($strlen == 2 || $strlen == 1) {
                return (float)$value;
            } else {
                $strlen = $strlen - 2;
                return (float)substr($value, 0, -$strlen);
            }
        } else {
            return $value;
        }
    }
    

}
