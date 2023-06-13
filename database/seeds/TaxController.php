<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\Tax\TaxRate;
use App\Model\Tax\TaxGroup;
use App\Model\Tax\TaxRateGroupMapping;
use App\Model\Tax\TaxCategory;
use App\Model\Tax\TaxCategorySlab;
use App\Model\Tax\TaxHsnCat;
use App\Model\Tax\HsnCode;
use App\Model\Tax\TaxGroupPreset;
use App\Model\Tax\TaxGroupPresetDetails;
use App\State;
use Auth;
use DB;

class TaxController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth:web');
  }

  // Datatable
  public function taxRateList(Request $request)
  {
    if ($request->ajax()) {
      $where    = array('v_id'=>Auth::user()->v_id,'deleted_by'=>0);
      $tax_rate = TaxRate::where($where);
      return zwDataTable($request, $tax_rate);
    }
  }//End of taxRateList

  public function addTaxRate(Request $request)
  {
    if($request->ajax()) {
     if(empty($request->id)){
      $this->validate($request, [
      'name'  => 'required',
      'rate'  => 'required',
      'code'  => 'unique:tax_rates'
      ]);
    $CtaxRate = TaxRate::where('v_id',Auth::user()->v_id)->where('name',trim($request->name))->whereNull('deleted_at')->where('deleted_by','0')->first();
    
    if($CtaxRate){
      return response()->json([
      'message' => 'The given data was invalid.',
      'errors' => [
      'name' => [
      'The name has already been taken.'
            ]
          ]
        ],422);
      }
    }else{

      $this->validate($request, [
      'name'  => 'required',    //|unique:tax_rates,name,'.$request->id
      'rate'  => 'required',
      'code'  => 'unique:tax_rates,code,'.$request->id
      ]);

      $CtaxRate = TaxRate::where('v_id',Auth::user()->v_id)->where('id','<>',$request->id)->where('name',trim($request->name))->first();
        if($CtaxRate){
        return response()->json([
        'message' => 'The given data was invalid.',
        'errors' => [
        'name' => [
        'The name has already been taken.'
        ]
        ]
        ],422);
        }
      }
      $data = array('name'  => $request->name,
      'rate'  => $request->rate,
      'code'  => $request->code,
      'v_id'  => Auth::user()->v_id);
      if(empty($request->id)){
        $taxrate = TaxRate::create($data);
      }else{
       $taxrate = TaxRate::where('id', $request->id)->update($data);
      }
    }
  }//End of addTaxRate

  public function taxRateDetails(Request $request, $id)
  {
    if ($request->ajax()) {
      return TaxRate::find($id);
    }
  }//End of taxRateDetails

  public function getRate(){
    $where    = array('v_id'=>Auth::user()->v_id,'deleted_by'=>0);
    return TaxRate::where($where)->get();
  }//End of getRate

  /*Tax Group Start*/

  public function addTaxGroup(Request $request){
    if($request->ajax()) {
      if(empty($request->id)){
        $this->validate($request, [
        'name'  => 'required',    //|unique:tax_group
        'code'  => 'unique:tax_group'
        ]);
      }else{
      $this->validate($request, [
      'name'  => 'required',    //|unique:tax_group,name,'.$request->id
      'code'  => 'unique:tax_group,code,'.$request->id
      ]);
      }
      $data = array('name'  => $request->name,
      'code'  => $request->code,
      'v_id'  => Auth::user()->v_id);
      if(empty($request->id)){
        $taxgroup = TaxGroup::create($data);
      }else{
        $taxgroup = TaxGroup::where('id', $request->id)->update($data);
        $taxgroup = TaxGroup::find($request->id);
      }
      //$rate   = json_decode($request->rate,true);
      $this->groupRateMapping($request,$taxgroup->id); 
      //$rateGroupMapping = TaxRateGroupMapping::where('tax_group_id',$taxgroup->id)->where('tax_code_id',$request->code);
    }
  }// End of addTaxGroup


  public function groupRateMapping($request,$group_id){
    if($request->rate){
    TaxRateGroupMapping::where('tax_group_id',$group_id)->delete();
      foreach($request->rate as $fetch){

      $data  = array('tax_group_id'=>$group_id,'tax_code_id'=>$fetch['rate'],'trade_type'=>$fetch['trade_type']);
      TaxRateGroupMapping::create($data);
      /*$groupmapping = TaxRateGroupMapping::where('tax_group_id',$group_id)->where('tax_code_id',$fetch['rate'])->first();
      if(!$groupmapping){
      TaxRateGroupMapping::create($data);
      }else{
      TaxRateGroupMapping::where('id',$groupmapping->id)->update($data);
      }*/
      }
    }
  }//End of groupRateMapping

 ## Datatable  ##
  public function groupList(Request $request){
    if ($request->ajax()) {
      $where    = array('v_id'=>Auth::user()->v_id,'deleted_by'=>0);
      $tax_group = TaxGroup::where($where) ;
      return zwDataTable($request, $tax_group);
    }
  }//End of groupList

  public function groupdetails(Request $request,$id){
    if ($request->ajax()) {
      $taxgroup =  TaxGroup::with(['taxRate' => function($query){
      $query->select(DB::raw('tax_rate_group_mapping.tax_code_id,tax_rate_group_mapping.trade_type, tax_rates.name as name'));
      }])->find($id);
      return $taxgroup;
      // print_r($taxgroup);die;
    }
  }//End of groupdetails

        /* Tax Category*/
  public function addTaxCategory(Request $request){

    $v_id     = Auth::user()->v_id; 
    if($request->ajax()) {

      ###############################################
      #######      Begin Transaction       ##########
      # This function is add group and category both#
      # #############################################
      DB::beginTransaction();
      try{
        $validate = array('name'=>'required');
        if($request->slab == 'NO'){
          $validate['tax.CGST']  = 'required';
          $validate['tax.SGST']  = 'required';
          //$validate['tax.IGST']  = 'required';
        }else{
          $slabcount  = count($request->slab_value); 
          if($slabcount > 0){
            $slabcount = $slabcount-1;
          }
          for($i=0;$i <=$slabcount;$i++){
            $validate["slab_value.".$i.".tax.CGST"] = 'required' ;
            $validate["slab_value.".$i.".tax.SGST"] = 'required' ;
          }
        }
        if(empty($request->id)){
          $this->validate($request, $validate);
          $CtaxGrp = TaxGroup::where('v_id',Auth::user()->v_id)->where('name',trim($request->name))->first();
          if($CtaxGrp){
            return response()->json([
              'message' => 'The given data was invalid.',
              'errors' => [
              'name' => [
              'The name has already been taken.'
                    ]
                  ]
              ],422);
          }
        }else{
          $this->validate($request,  $validate);
          $CtaxGrp = TaxGroup::where('v_id',Auth::user()->v_id)->where('id','<>',$request->id)->where('name',trim($request->name))->first();
          if($CtaxGrp){
            return response()->json([
              'message' => 'The given data was invalid.',
              'errors' => [
              'name' => [
                'The name has already been taken.'
                         ]
                        ]
                ],422);
           }
          }
        // print_r($rate);die;
        $groupdata = array('name'  => $request->name,
        'code'  => $request->code,
        'v_id'  => $v_id);
        /*First Group Add*/
        if(empty($request->id)){
          $taxgroup = TaxGroup::create($groupdata);
        }else{
          $taxgroup = TaxGroup::where('id', $request->id)->update($groupdata);
          $taxgroup = TaxGroup::find($request->id);
        }
        //$this->groupRateMap($request,$taxgroup->id); 
        if($request->slab == 'NO'){
        //$this->groupRateMap($request->tax,$taxgroup->id);
        $s[0] = array('amount_from'=>0,'amount_to'=>0,'tax_group_id'=>$taxgroup->id,'tax'=>$request->tax);
          $request->slab_value = $s;
        }
        $category_name = 'Cat_'.$taxgroup->name;
        $category_code = 'Cat-'.$v_id.$taxgroup->id;
        $data          = array('name'          => $category_name,
        'code'          => $category_code,
        'slab'          => $request->slab,
        'applicable_on' => $request->applicable_on,
        'v_id'          => $v_id);
        if(empty($request->id)){
         $taxCategory = TaxCategory::create($data);
        }else{
         TaxCategory::where('id', $request->id)->update($data);
         $taxCategory = TaxCategory::find($request->id);
        }
        $this->addTaxSlab($request->slab_value,$taxgroup->id,$taxCategory->id);
        DB::commit();
      }catch(Exception $e){
      DB::rollback();
      exit;
      }
    }
  }//End of addTaxCategory

  public function addTaxCategory_old(Request $request){

  if(!empty($request->tax_group_id)  &&  $request->slab == 'NO'){
  $s[0]  = array('amount_from'=>0,'amount_to'=>0,'tax_group_id'=>$request->tax_group_id);
  $request->slab_value = $s;
  }

  if($request->ajax()) {
  if(empty($request->id)){
  $this->validate($request, [
  'name'  => 'required|unique:tax_category',
  //'hsncode'=> 'required|unique:tax_category',
  // 'tax_group_id'  => 'required',
  //'code'  => 'unique:tax_category'
  ]);
  // $taxgroupid = $request->tax_group_id['code'];
  }else{
  $this->validate($request, [
  'name'  => 'required|unique:tax_category,name,'.$request->id,
  'code'  => 'unique:tax_category,code,'.$request->id
  ]);
  // $taxgroupid = $request->tax_group_id[0]['code'];
  }
  $data = array('name'          => $request->name,
  'hsncode'       => $request->hsncode,
  'code'          => $request->code,
  'slab'          => $request->slab,
  'applicable_on' => $request->applicable_on,
  'v_id'          => Auth::user()->v_id);
  if(empty($request->id)){
  $taxrate = TaxCategory::create($data);
  }else{
   TaxCategory::where('id', $request->id)->update($data);
   $taxrate = TaxCategory::find($request->id);
  }
  //echo $taxrate; die;
  //print_r($request->slab_value);die;

  $this->addTaxSlab($request->slab_value,$taxrate->id);
  }
  }//End of addTaxCategory


  private function addTaxSlab($request,$taxgrpid,$cat_id){
    if(!empty($request)){
      $taxslab = TaxCategorySlab::select('id')->where('tax_cat_id',$cat_id)->get()->pluck('id'); //->delete()
      TaxRateGroupMapping::whereIn('tax_slab_id',$taxslab)->delete();
      //$taxslab->ratemap->delete();
      //$taxslab->delete();
      TaxCategorySlab::where('tax_cat_id',$cat_id)->delete();
      //TaxRateGroupMapping::where(['tax_group_id'=>$group_id])->->delete();
        foreach ($request as $key => $value) {
          //print_r($value->tax);
          //$taxgrpid  = json_decode($value['tax_group_id'],true); 
          //$taxgrpid = (@$value['tax_group_id']['code'] == '')?$value['tax_group_id'][0]['code']:$value['tax_group_id']['code'];
          $data = array('tax_group_id'  => $taxgrpid,
          'amount_from'   => $value['amount_from'],
          'amount_to'     => $value['amount_to'],
          'tax_cat_id'    => $cat_id);
          $slab = TaxCategorySlab::create($data);

          if(isset($value['tax'])){
            $this->groupRateMap($value['tax'],$taxgrpid,$slab->id);
          }
        }
    }
  }//End of addTaxSlab


  public function groupRateMap($tax,$group_id,$slab_id){
    if($tax){
      //TaxRateGroupMapping::where(['tax_group_id'=>$group_id,'tax_slab_id' => $slab_id])->delete();
      foreach ($tax as $key => $value) { 
        if($value != ''){
          if($key == 'CGST' || $key == 'SGST' || $key == 'IGST'){
            $state  = 'INTRA_STATE';
          }else{
             $state  = 'INTER_STATE';
            }
            $data  = array('tax_group_id'=>$group_id,
            'tax_slab_id'=>$slab_id,
            'tax_code_id'=>$value,
            'trade_type'=>$state,
            'type' => $key );
            TaxRateGroupMapping::create($data);
          }
      }
    }
  }//End of groupRateMap

  public function taxCategoryList(Request $request){
    if ($request->ajax()) {
      $where    = array('v_id'=>Auth::user()->v_id,'deleted_by'=>0);
      $tax_category = TaxCategory::where($where);
      return zwDataTable($request, $tax_category);
    }
  }//End of taxCategoryList

  public function taxCategoryDetail(Request $request, $id){
    if ($request->ajax()) {

      // $taxCategory =  TaxCategory::with(['slabvalue' => function($query){
      //     $query->select(DB::raw('tax_category_slab.tax_group_id as tax_group_id, tax_category_slab.amount_from as amount_from,tax_category_slab.amount_to as amount_to'));
      // }])->find($id);

      $taxCategory = TaxCategory::with(['slabvalue'=>function($query){
        $query->with('ratemap');
      }])->where('id',$id)->first();//->find($id);//;

      //print_r($taxCategory->slabvalue[0]->group->mapping->tax_code_id);
      //$taxCategory = collect($taxCategory);
      $taxCategory->slabvalue->each(function($item,$key){
        $item->ratemap->map(function($item1,$key1) use($item)  {
          if($item1->type == 'CGST') {
            $item->cgst = $item1->tax_code_id; //$item1->rate->rate;
          } 
          if($item1->type == 'SGST') {
            $item->sgst = $item1->tax_code_id; //$item1->rate->rate;
          } 
          if($item1->type == 'IGST') {
            $item->igst = $item1->tax_code_id; //$item1->rate->rate;
          } 
          if($item1->type == 'CESS') {
            $item->cess = $item1->tax_code_id; //$item1->rate->rate;
          } 
        });
        return $item ;  
      }); 
      // print_r($data);die;
      //echo $taxCategory->id;
      //print_r($taxCategory  );die;
      return $taxCategory;
    }
  }//End of taxCategoryDetail

  public function getGroupList() {
    $where    = array(
    'v_id' => Auth::user()->v_id,
    'deleted_by' => 0
    );
    return TaxGroup::select('id', 'name')
    ->where($where)
    ->get();
  }//End of getGroupList

  public function getGroup() {
    $where    = array(
    'v_id' => Auth::user()->v_id,
    'deleted_by' => 0
    );
    return TaxGroup::where($where)->get();
  }//End of getGroup


/*Hsn code start */
  public function addHsnCode(Request $request){
     // dd($request);
    if($request->ajax()) {

      if(@$request->hsncode['code']){
        $hsn_code  = $request->hsncode['code'];//dd("if",$hsn_code);
      }
      else{
        $hsn_code  = $request->hsncode[1]['name'];//dd("else",$hsn_code);
      }
      // dd($hsn_code);
      if(empty($request->id)){
        $this->validate($request, [
          'hsncode'  => 'required',
          ]);
          
        }else{
          $this->validate($request, [
            'hsncode'  => 'required'
            ]);
            
          }
          /*if hsn doesn't exists create*/
          if(isset($request->hsncode)){
            $hsn = isset($request->hsncode[1]['name']) ? $request->hsncode[1]['name'] : isset($request->hsncode['code']); 
            $checkHsn = HsnCode::where('hsncode',$hsn)->exists();
            /*if(!$checkHsn){
              DB::table('hsncode')->insert([
                ['hsncode' => $hsn_code, 'description' => $request->description,'country_code'=>'IN'],
                ]);
              }else{
                return response()->json([
                  'status' => 'error',
                  'msg' => 'Hsncode already exists'
                ],422);
              }*/
            }
            $getCate  = TaxCategorySlab::where('tax_group_id',$request->category)->first();
            $data = array('hsncode'  => $hsn_code,
                      'cat_id'   => $getCate->tax_group_id,
                      'description'  => $request->description,
                      'v_id'     => Auth::user()->v_id);
            // dd($data);
            if(empty($request->id)){
              $checkhsn = array('hsncode'  => $hsn_code,
              'v_id'     => Auth::user()->v_id);
              if(TaxHsnCat::where($checkhsn)->first()){
              return response()->json([
              'status' => 'error',
              'msg' => 'Hsncode is already tagged with other tax Group'
              ],422);
              }
              // dd($data);
              $taxrate = TaxHsnCat::create($data);
            }else{
              // dd($data);
              $taxrate = TaxHsnCat::where('id', $request->id)->update($data);
            }
            
        }
    }//End of addHsnCode
  
  public function mapTaxHsn($params){
    
    $v_id    = $params['v_id'];
    $tax_id  = $params['tax_id'];
    $hsn     = $params['hsn'];
    if(!empty($hsn) && !empty($tax_id)){  
      $getCate  = TaxCategorySlab::where('tax_group_id',$tax_id)->first();
      if($getCate){
        $where   = array('v_id'=>$v_id,'cat_id'=>$getCate->tax_cat_id,'hsncode'=>$hsn);
        $taxrate = TaxHsnCat::where($where)->first();
        if(!$taxrate){
          $taxrate  = new TaxHsnCat;
        }
        $taxrate->hsncode = $params['hsn'];
        $taxrate->cat_id  = $getCate->tax_cat_id;
        $taxrate->v_id    = $v_id;
        $taxrate->save();
      }
    }
  }//End of mapTaxHsn
  
  public function hsncodelist(Request $request){
    if ($request->ajax()) {
      $where    = array('tax_hsn_cat.v_id'=>Auth::user()->v_id,'tax_hsn_cat.deleted_by'=>0);
      $tax_rate = TaxHsnCat::leftjoin('tax_group','tax_group.id','=','tax_hsn_cat.cat_id')->where($where);
      return zwDataTable($request, $tax_rate);
    }
  }
  
  public function getCategory(){
    $where    = array('v_id'=>Auth::user()->v_id,'deleted_by'=>0);
    return TaxCategory::where($where)->get();
  }
  
  public function hsnDetails(Request $request, $id){
    if ($request->ajax()) {
      /*$taxgroup =  TaxHsnCat::with(['taxRate' => function($query){
        $query->select(DB::raw('tax_rate_group_mapping.tax_code_id, tax_rates.name as name'));
      }])->find($id);*/
      $taxhsn   = TaxHsnCat::find($id);
      return $taxhsn;
      // print_r($taxgroup);die;
    }
  }
  
  public function getHsncode(){
    $v_id     = Auth::user()->v_id;
    $store_id = Auth::user()->store_id;
    $vu_id    = Auth::user()->id;
    $params   = array('v_id' => $v_id,'store_id'=>$store_id,'vu_id'=>$vu_id);
    $getCurrencyDetail = getCurrencyDetail($params);
    return HsnCode::where('country_code',$getCurrencyDetail['country_code'])->get();
  }


  public function delete(Request $request){
    if($request->type == 'TAX_RATE'){

      $checkRateExist = TaxRateGroupMapping::where('tax_code_id',$request->id)->count();
      if($checkRateExist > 0){
        return response()->json(['status'=>'error','message' => 'Tax rate is tagged with tax group.'],422);
      }else{
      $deleteRow   = TaxRate::find($request->id);
      $msg         = 'Tax Rate';
     } 

    }if($request->type == 'TAX_GROUP'){
      $deleteRow   = TaxGroup::find($request->id);
      $deleteCat   = TaxCategory::find($request->id);
      $msg         = 'Tax Group';

    }if($request->type == 'TAX_CATEGORY'){
      $deleteRow   = TaxCategory::find($request->id);
      $msg         = 'Tax Category';
    }
    if($request->type == 'TAX_HSN'){
      $deleteRow   = TaxHsnCat::find($request->id);
      $msg         = 'Tax Hsn';
      $deleteRow->delete();
    }else{
      $deleteRow->deleted_by =  Auth::user()->id;
      $deleteRow->deleted_at = date('Y-m-d H:i:s');
      $deleteRow->save();
    $deleteCat = '';
    if($deleteCat){
      $deleteCat->deleted_by =  Auth::user()->id;
      $deleteCat->deleted_at = date('Y-m-d H:i:s');
      $deleteCat->save();
    }

    }
    return response()->json([ 'res' => 'success','title'=>$msg.' Deleted','message' =>  $msg.' Deleted Successfully.','response_type'=>$msg], 200);    
  }//End of Delete

  
  public function getTaxType(){
    $v_id     = Auth::user()->v_id;
    $store_id = Auth::user()->store_id;
    $vu_id    = Auth::user()->id;
    $params   = array('v_id' => $v_id,'store_id'=>$store_id,'vu_id'=>$vu_id);
    $getCurrencyDetail = getCurrencyDetail($params);
    if($getCurrencyDetail['code'] == 'INR'){
      $getCurrencyDetail['tax_type'] = 'GST';
    }else{
      $getCurrencyDetail['tax_type'] = 'VAT';
    }
    $state  = State::where('country_id',$getCurrencyDetail['country_id'])->get();
    return array('type'=>$getCurrencyDetail,'state'=>$state);
                                        
  }//End of getTaxType

  public function getTaxLabel(){

    $v_id     = Auth::user()->v_id;
    $store_id = Auth::user()->store_id;
    $vu_id    = Auth::user()->id;
    $params   = array('v_id' => $v_id,'store_id'=>$store_id,'vu_id'=>$vu_id);
    $getCurrencyDetail = getCurrencyDetail($params);
    if($getCurrencyDetail['country_code'] == 'CA'){
      $data['tax_label_1'] = 'GST';
      $data['tax_label_2'] = 'PST';
      $data['tax_label_3'] = '';
      $data['tax_label_4'] = '';
    }else{
      $data['tax_label_1'] = 'CGST';
      $data['tax_label_2'] = 'SGST';
      $data['tax_label_3'] = 'IGST';
      $data['tax_label_4'] = 'CESS';
    }
    return $data;
  }//End of getTaxLabel


  ############ Dynamic(New) Tax #############


  public function getPresetList(Request $request){
    $v_id      = Auth::user()->v_id;
    //stag_zwing
    //env('DB_DATABASE')
    $presets   = TaxGroupPreset::leftJoin('test_zwing.states','states.id','tax_group_preset.region_id')->with('details')->select('tax_group_preset.id','preset_group_name as name','states.name as location')->where('v_id',$v_id)->orderBy('preset_group_name','ASC')->get();
    return $presets;
  }//End of getPresetList


  public function addTaxPreset(Request $request){



        $id                 = $request->id;
        $preset_group_name  = $request->preset_group_name;
        $is_region_specific = $request->is_region_specific;
        $region_id          = $request->region_id;
        if(!empty($region_id)){
          $region_id        = $region_id['id'];
        }else{
          $region_id        = 0;
        }
        $presetDetails      = $request->taxName;
        $vu_id              = Auth::user()->id;
        $v_id               = Auth::user()->v_id; 
        $status             = empty($request->status)?'1':$request->status;
        $this->validate($request, [
          'preset_group_name'  => 'required|unique:tax_group_preset'
        ]);

        if(count($presetDetails) > 0){
          foreach ($presetDetails as $taxname) {
            foreach ($taxname as $item) {
               if(empty(trim($item['tax']))){
                return response()->json(['message' => 'Tax name cannot be empty.'],422);               
               }else if (preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', trim($item['tax'])) ){
                return response()->json(['message' => 'Do not use any special charactor in tax name.'],422);
               }
            }
          }
        }else{
         return response()->json(['status'=>'error','message' => 'Tax name is mandatory.'],422);
        }
    DB::beginTransaction();
    try{
        $data  = array('preset_group_name'=>$preset_group_name,'is_region_specific'=>$is_region_specific,'region_id'=>$region_id,'status'=>$status,'v_id'=>$v_id);
        if(empty($id)){
          $data['created_by']  = $vu_id;
          $groupPreset = TaxGroupPreset::create($data);
        }else{
          $data['updated_by']  = $vu_id;
          $groupPreset = TaxGroupPreset::where('id',$id)->update($data);
        }
        foreach ($presetDetails as $taxname) {
         foreach ($taxname as $item) {
           if(!empty(trim($item['tax']))){
            $params = array('preset_id'=>$groupPreset->id,'preset_name'=>trim($item['tax']), 'status'=>'1');
            $this->addTaxPresetDetails($params);
           }
         }
        }
        DB::commit();
      }catch(Exception $e){
        DB::rollback();
        exit;
      }
      if(!empty($groupPreset)){

        return response()->json(['status'=>'success','message' => 'Tax preset added successfully.'],200);
      }else{
        return response()->json(['status'=>'error','message' => 'Tax preset not created.Please try againg.'],422);
      }
    }//End of addTaxPreset


    public function addTaxPresetDetails($params){

        //$id         = empty($params['id'])?'':$params['id'];
        $preset_id  = $params['preset_id'];
        $preset_name= $params['preset_name'];
        $status     = $params['status'];
        $data       = array('preset_name'=>$preset_name,'preset_id'=>$preset_id,'status'=>$status);
        $existsPresetDetail = TaxGroupPresetDetails::where(['preset_name'=>$preset_name,'preset_id'=>$preset_id])->first();
        if(empty($existsPresetDetail)){
          $presetDetails  = TaxGroupPresetDetails::create($data);
        }else{
          $presetDetails  = TaxGroupPreset::where('id',$existsPresetDetail->id)->update($data);
        }
        return $presetDetails;
    }//End of addTaxPresetDetails


   public function saveTaxGroup(Request $request){
      $v_id     = Auth::user()->v_id; 
      $vu_id     = Auth::user()->id; 
      if($request->ajax()) {

      //dd($request->slab_value);
      ###############################################
      #######      Begin Transaction       ##########
      # This function is add group and category both#
      # #############################################
      //DB::beginTransaction();

      //print_r($request->slab_value);
      try{
        $validate = array('name'=>'required','effective_date'=>'required','valid_upto'=>'required','code'=>'required');
        if($request->slab == 'NO'){
         $has_slab = '0';
        }else{
         $has_slab = '1';
        }
        if(empty($request->id)){
          $this->validate($request, $validate);
          $CtaxGrp = TaxGroup::where('v_id',Auth::user()->v_id)->where('name',trim($request->name))->first();
          if($CtaxGrp){
            return response()->json([
              'message' => 'The given data was invalid.',
              'errors' => [
              'name' => [
              'The name has already been taken.'
                    ]
                  ]
              ],422);
          }
        }else{
          $this->validate($request,  $validate);
          $CtaxGrp = TaxGroup::where('v_id',Auth::user()->v_id)->where('id','<>',$request->id)->where('name',trim($request->name))->first();
          if($CtaxGrp){
            return response()->json([
              'message' => 'The given data was invalid.',
              'errors' => [
              'name' => [
                'The name has already been taken.'
                         ]
                        ]
                ],422);
           }
          }
        // print_r($rate);die;
        $groupdata = array('name'  => $request->name,
                          'code'  => $request->code,
                          'v_id'  => $v_id,
                          'tax_group_preset_id'  => $request->preset_id,
                          'has_slab'             => $has_slab,
                          'applicable_on'        => $request->applicable_on,
                          'effective_from'       => $request->effective_date,
                          'valid_upto'           => $request->valid_upto,
                          'created_by'           => $vu_id
                          );
        /*First Group Add*/
        if(empty($request->id)){
          $taxgroup = TaxGroup::create($groupdata);
        }else{
          $groupdata['updated_by']  = $vu_id;
          $taxgroup = TaxGroup::where('id', $request->id)->update($groupdata);
          $taxgroup = TaxGroup::find($request->id);
        }
        //$this->groupRateMap($request,$taxgroup->id); 
        if($request->slab == 'NO'){
        //$request->slab_value = $s;
        }
         //print_r($request->slab_value);die;
        $this->addTaxSlabNew($request->slab_value,$taxgroup->id);
        return response()->json(['status'=>'success','message' => 'Tax group added successfully.'],200);
        //DB::commit();
      }catch(Exception $e){
     // DB::rollback();
      exit;
      }
    }
   }//End of saveTaxGroup

   public function addTaxSlabNew($request,$taxgrpid){
    if(!empty($request)){
      $taxslab = TaxCategorySlab::select('id')->where('tax_group_id',$taxgrpid)->get()->pluck('id'); //->delete()
        TaxRateGroupMapping::where('tax_group_id',$taxgrpid)->delete();
        TaxCategorySlab::where('tax_group_id',$taxgrpid)->delete();
        foreach ($request as $key => $value) {

          if(count($value['tax']) >0){
          $data = array('tax_group_id'  => $taxgrpid,
                  'amount_from'   => empty($value['amount_from'])?'0':$value['amount_from'],
                  'amount_to'     => empty($value['amount_to'])?'0':$value['amount_to'],
                  'status'        => '1');
          $slab = TaxCategorySlab::create($data);

          $this->groupRateMapNew($value['tax'],$taxgrpid,$slab->id);
          }

          //print_r($value['tax']);

          /*if(isset($value['tax'])){
            $this->groupRateMapNew($value['tax'],$taxgrpid);
          }*/
        }
    }
   }//End of addTaxSlabNew

   public function groupRateMapNew($request,$group_id,$slab){
    if($request){
    //TaxRateGroupMapping::where('tax_group_id',$group_id)->delete();
      foreach($request as $prs_det_id=> $fetch){
       if($fetch){

        $CheckTaxPreset = TaxGroup::join('tax_group_preset_details','tax_group_preset_details.preset_id','tax_group.tax_group_preset_id')->where('tax_group.id',$group_id)->where('tax_group_preset_details.id',$prs_det_id)->first();
        if(!empty($CheckTaxPreset)){
          $data  = array('tax_group_id'=>$group_id,'tax_code_id'=>$fetch,'tax_slab_id'=>$slab,'trade_type'=>'INTRA_STATE','type'=>'CGST','tg_preset_detail_id'=>$prs_det_id);
          TaxRateGroupMapping::create($data);
        }

        
        }
      }
    }
   }//End of groupRateMapNew

   public function groupDetailsNew(Request $request,$id){
    if($request){
      $group   = TaxGroup::find($id);
     

               //,'tax_slab.amount_from','tax_slab.amount_to'

        //dd($details);die;


      $slab  = TaxCategorySlab::where('tax_group_id',$group->id)->select('id','amount_from','amount_to')->orderBy('id','asc')->get();
      $slab_value = $slab->map(function($items) use($group)  {

         $details = TaxRateGroupMapping::where('tax_rate_group_mapping.tax_group_id',$group->id)
               ->where('tax_rate_group_mapping.tax_slab_id',$items->id)
               ->join('tax_rates','tax_rates.id','tax_rate_group_mapping.tax_code_id')
               ->join('tax_group_preset_details','tax_group_preset_details.id','tax_rate_group_mapping.tg_preset_detail_id')
               //->join('tax_slab','tax_slab.tax_group_id','tax_rate_group_mapping.tax_group_id')
               ->select('tax_group_preset_details.id as tgpd_id','tax_group_preset_details.preset_name as tgpd_name','tax_rates.id as rate_id','tax_rates.name as rate_name','tax_rates.rate as rate')
               ->get();

        $items->details = $details;
        return $items;
      });

      $group->slab           = $slab_value;
      $group->effective_from = date('Y-m-d',strtotime($group->effective_from));
      $group->valid_upto     = date('Y-m-d',strtotime($group->valid_upto));
      return $group;
    }
   }//End of groupDetailsNew




   
  
}
                                    