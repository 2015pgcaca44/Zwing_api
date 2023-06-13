<?php

namespace App\Http\Controllers\Search;

use App\Organisation;
use DB;

trait SearchableTrait
{

	public function getSearchClient(){
		$search = app(SearchController::class);
		$searchConfig = $search->getConfig();

		if($searchConfig['default'] == 'elastic'){
			$params['indexes'] = $this->getIndexName();
			$params['indexes_type'] = $this->getIndexType();
			$params['fields'] = $this->getFields();
			$params['custom_fields'] = $this->customFields;
			$params['searchId'] = $this->getSearchId();
			$params['this'] = $this;

			return new Elastic\ElasticSearchController($searchConfig['connections'][$searchConfig['default']] , $params);
		}

	}
	public function search(){

		return $this->getSearchClient()->search();

	}

	public function getIndexName(){

		#$organisation= new Organisation; 
  		#$organisation = $organisation->setConnection('mysql')->where('id',$v_id)->first();
  		
		return strtolower(DB::connection()->getDatabaseName() );
	}

	public function getIndexType(){

		if(isset($this->documentType) && $this->documentType != null){
			return $this->documentType;
		}else{
			return $this->table;
		}
	}

	public function getFields(){

		if(isset($this->searchable) && $this->searchable != null){
			return $this->searchable;
		}else{
			return $this->fillable;
		}
	}


	public function getSearchId(){

		if(isset($this->searchId) && $this->searchId != null){
			return $this->searchId;
		}else if(isset($this->primaryKey) && $this->primaryKey != null){
			return $this->primaryKey;
		}else{
			return 'id';
		}
	}

	public function getCustomFields(){

		if(isset($this->searchId) && $this->searchId != null){

			return $this->customFields;
		}else{
			return null;
		}
	}

}