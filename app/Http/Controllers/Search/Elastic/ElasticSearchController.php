<?php

namespace App\Http\Controllers\Search\Elastic;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Controllers\Search\SearchInterface;

class ElasticSearchController implements SearchInterface
{
	protected $config;
	protected $params;
	protected $responseFormat = 'Object'; //Object(PHP Object) or JSON
	protected $query = null;
	protected $per_page = null;
    protected $page = 1;
    public $mapResponseFunction = null;
   
	public function __construct(array $config = null, array $params = null){
		$this->config = $config;
		$this->params = $params;
	}

	public function search(){

		return $this;
	}
	public function getConnection(){

	}

	public function select(){
		$args = func_get_args();
		$this->query['_source'] = $args;
		return $this;
	}

	public function where($col,$operator, $val =null){

		$data = null;
		if($val == null){
			$data = $operator;
		}else{
			$data = $val;
		}

		// dd($operator);
		if($operator == 'like' || $operator == 'LIKE'){
			$condition = [ 'wildcard' => [ $col => str_replace("%","*",$data) ] ];
		}else{
			$condition = [ 'match' => [ $col => $data ] ];
		}

		if(isset($this->query['query']['bool']['should']) ){

			array_push ($this->query['query']['bool']['should'] , $condition);
		}else{
			$this->query['query']['bool']['should'] = [ $condition ];
		}

		return $this;
	}

	public function rawQuery($query = null){
		$query = str_replace(array("\n", "\r" , "\t"), '', $query);
		if(empty($query) ){
			$finalQuery['query'] = ['match_all' => (object)[] ];
		}else{
			$query = json_decode($query);
			$finalQuery['query'] = $query;
		}

		$this->query = $finalQuery;

		// dd($this->query);

		return $this;
	}

	public function getValidFields($fields){

		if($this->params['fields'] == null ){
			return $fields;
		}else if($this->params['fields'][0] == '*'){
			return $fields;
		}else{

			foreach($fields as $key => $value){
				if(in_array($key, $this->params['fields'])){

				}else{
					unset($fields[$key]);
				}
			}
			return $fields;
		}
	}

	public function singleRecord(){
		$search = $this->params['this'];
		$attributes = $search->getAttributes();
		$attributes = $this->getValidFields($attributes);

		$customFields = $this->customFields($search);
		$attributes = array_merge( $attributes  , $customFields );

		return $attributes;
	}


	public function mapResponse($func){
		$this->mapResponseFunction = $func;
		return $this;
	}

	public function get(){

		$request = [
			'url' => $this->config['host'].'/'.$this->params['indexes'].'/'.$this->params['indexes_type'] .'/_search',
			'method' => 'GET'];

		$data = null;
		if( $this->query ){
			if($this->per_page > 0){

				$this->query['from'] = (1 - $this->page) * $this->per_page;
				$this->query['size'] = $this->per_page;
			}
			$data = json_encode($this->query);
		}
		// dd($data);

		if($data){
			$request['data'] = $data;
			$request['header'] = [ 'Content-Type:application/json'];
		}
		// dd($request);
		
		// $response = new ApiCallerController($request);
		// $response = $response->call();

		$response = $this->apiCaller($request);
		// dd($response);

		return $this->responseFormatter($response, 'data');
	}

	public function paginate($per_page = 10, $page = 1){

		$this->per_page = $per_page;
		$this->page = $page;
		return $this->get();
	}

	public function create($datas = null){

		$streamData  = '';
		foreach($datas as $data){
			$id = $this->params['searchId'];
			$data = $this->getValidFields($data);
			$streamData .= '{ "index":{ "_id" : "'.$data->$id.'" } }'."\r\n";
			$streamData .= json_encode($data)."\r\n";
		}

		$request = [
			'url' => $this->config['host'].'/'.$this->params['indexes'].'/'.$this->params['indexes_type'].'/_bulk',
			'method' => 'POST'];
		

		if($data){
			$request['data'] = $streamData;
			$request['header'] = [ 'Content-Type:application/json'];
		}

		$response = $this->apiCaller($request);
		return $this->responseFormatter($response);
	}

	public function customFields($data){
		$customFields = [];
		//adding custom Fileds
		if($this->params['custom_fields']){
			foreach($this->params['custom_fields'] as $ckey => $cvalue){
				$customFields[$ckey] = $data->$cvalue;
			}

		}
		return $customFields;
	}

	public function save(){
		$record = $this->singleRecord();
		$request = [
			'url' => $this->config['host'].'/'.$this->params['indexes'].'/'.$this->params['indexes_type'].'/'.$this->params['searchId'],
			'method' => 'POST'];

		$data = json_encode($this->singleRecord() );
		

		if($data){

			$customFields = $this->customFields($data);

			$data = array_merge( $data->toArray()  , $customFields );

			$request['data'] = $data;
			$request['header'] = [ 'Content-Type:application/json'];
		}

		$response = $this->apiCaller($request);
		return $this->responseFormatter($response);
		// dd($response);
	}


	public function saveAll(){

		$finalRepsonse = [];
		$request = [
			'url' => $this->config['host'].'/'.$this->params['indexes'].'/'.$this->params['indexes_type'].'/_bulk',
			'method' => 'POST'];

		
		$model = $this->params['this'];

		$skip =0; $take=10;
		$loop = true;
		while($loop){
			$alldata = $model->select(implode(",",$this->params['fields']))->orderBy($this->params['searchId'])->skip($skip)->take($take)->get();
			if(!$alldata->isEmpty()){
				$streamData  = '';
				foreach($alldata as $data){

					$customFields = $this->customFields($data);

					$id = $this->params['searchId'];
					// $data = $this->getValidFields($data);
					$streamData .= '{ "index":{ "_id" : "'.$data->$id.'" } }'."\r\n";

					$data = array_merge( $data->toArray()  , $customFields );
					$streamData .= json_encode($data)."\r\n";
				}

				// dd($streamData);
				
				if($data){
					$request['data'] = $streamData;
					$request['header'] = [ 'Content-Type:application/json'];
				}

				$response = $this->apiCaller($request);
				// dd($response);
				$finalRepsonse[] = $this->responseFormatter($response);

			}else{
				$loop = false;
			}
			$skip += $take;

		}
		

		return $finalRepsonse;
	}

	public function delete(){
		$request = [
			'url' => $this->config['host'].'/'.$this->params['indexes'].'/'.$this->params['indexes_type'] .'/_delete_by_query',
			'method' => 'POST'];

		$data = null;
		if( $this->query ){
			$data = json_encode( $this->query );
		}

		// dd($data);

		if($data){
			$request['data'] = $data;
			$request['header'] = [ 'Content-Type:application/json'];
		}

		$response = $this->apiCaller($request);
		return $this->responseFormatter($response);
	}

	public function update($record = null){
		$request = [
			'url' => $this->config['host'].'/'.$this->params['indexes'].'/'.$this->params['indexes_type'] .'/_update_by_query',
			'method' => 'POST'];

		if($record){
			$record = $this->getValidFields($record);
		}else{
			$record = $this->singleRecord() ;
		}

		if(empty($record) ){

			return 'No Record to update';
		}else{
			$updateFields = '';
			foreach ($record as $key => $value) {
				if($updateFields != ''){
					$updateFields .= ';';
				}
				$updateFields .='ctx._source.'.$key.'='.$value;
			}
		}


		$data = null;
		if( $this->query ){
			$data = $this->query;
			$data['script']['source'] = $updateFields;

			$data = json_encode( $data );
		}

		// dd($data);

		if($data){
			$request['data'] = $data;
			$request['header'] = [ 'Content-Type:application/json'];
		}

		$response = $this->apiCaller($request);
		return $this->responseFormatter($response);
		// dd($response);
	}


	public function apiCaller($params){

		$ch = curl_init( $params['url'] );
		# Setup request to send json via POST.
		if(isset($params['data']) ){
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $params['data'] );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $params['header'] );
		}
		# Return response instead of printing.
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		# Send request.
		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		#handling Error
		if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            $error_msg = 'Error in exceuting third party api: '.$error_msg;
            return ['header_status'=>$httpcode,'body'=> $error_msg];
            // die('There is an error in api call');
        }
		curl_close($ch);
		# Print response.
		
		return ['header_status'=>$httpcode,'body'=>$result];


		/*$data = [];
		$response = new ApiCallerController([
			'url' => $this->config['host'].'/'.$params['url'],
			'method' => $params['method'],
			'data' => $data
		 ]
		);

		return $response->call();*/

	}


	public function responseFormatter($response, $return_type = 'status'){
		
		if($return_type == 'status'){
			if( $response['header_status'] == 200 || $response['header_status'] == 201 ){
				return ['status' => 'success' ,'message' => 'Operation done Successfully'];
			}else{
				return ['status' => 'fail' ,'message' => $response['body'] ];
			}
		}else{

			if($this->responseFormat == 'Object'){
				$res = json_decode($response['body']);
				$data=[];
				if(isset($res->hits->hits)){
					$data['total'] = $res->hits->total->value;
					foreach($res->hits->hits as $record){
						
						if($this->mapResponseFunction){
							$anynfunc = $this->mapResponseFunction;
							$data['data'][] = $anynfunc($record->_source);

						}else{
							$data['data'][] = $record->_source;
						}
					}
					$res = $data;
					//$res = $res->hits->hits;
				}

				return $res;
			}else {
				return $response['body'];
			}
		}

	}

}