<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Auth;
use App\Agents;
class AgentController extends Controller
{
  public function __construct()
    {
        $this->middleware('auth');
    }

  public function getAgentList(Request $request){
     $v_id =$request->v_id;
     $list=Agents::select('id','agent_name')
           ->where('v_id',$v_id)->get();
      $data = $list->toArray();  
     if($data==null){

      return response()->json(['status' => 'Agent_list_not_found', 'message' => 'Agent List Not Found'], 200);

     }else{
     
     return response()->json(['status' => 'agent_list', 'message' => 'Agent List', 'data' =>$list], 200);
     }
  }
}
