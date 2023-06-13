<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SolariumController extends Controller
{
    protected $client;

    // public function __construct(\Solarium\Client $client)
    // {
    //     $this->client = $client;
    // }

    public function __construct()
    {

        $this->client = new \Solarium\Client(config('solarium'));
    }

    public function ping()
    {
        // create a ping query
        $ping = $this->client->createPing();

        // execute the ping query
        try {
            $result = $this->client->ping($ping);
            // $data = $result->getData();
            return response()->json('OK');
        } catch (\Solarium\Exception $e) {
            return response()->json('ERROR', 500);
        }
    }

    public function create(){

        $update = $this->client->createUpdate();
        
        $docs = [];

        //Assigning Data
        $doc = $update->createDocument();
        $doc->id = '004';
        $doc->name = 'balbaor';
        $doc->age = '25';
        $doc->designation = 'JR.Programmer';
        $doc->location = 'Gurgoan';

        $docs[] = $doc;

        $update->addDocuments($docs);

        $update->addCommit();
        $result = $this->client->update($update);

        $status = $result->getStatus();
        //$result->getQueryTime();

        dd($status);

    }

    public function update($params){

        $this->create($params);
    }

    public function delete(){
        $update = $this->client->createUpdate();

        // add the delete query and a commit command to the update query
        $update->addDeleteQuery('id:004');
        $update->addCommit();

        // this executes the query and returns the result
        $result = $this->client->update($update);

        dd($result);

    }

    public function select(){
        /*
        $select = array(
            'query'         => '*:*',
            'start'         => 0,
            'rows'          => 10,
            'fields'        => array('id','name','price'),
            'sort'          => array('price' => 'asc'),
            'filterquery' => array(
                'maxprice' => array(
                    'query' => 'price:[1 TO 300]'
                ),
            ),
            'component' => array(
                'facetset' => array(
                    'facet' => array(
                        // notice this config uses an inline key value, instead of array key like the filterquery
                        array('type' => 'field', 'key' => 'stock', 'field' => 'inStock'),
                    )
                ),
            ),
        );

        // get a select query instance based on the config
        $query = $this->client->createSelect($select);
        */

        // get a select query instance
        $query = $this->client->createSelect();

        // apply settings using the API
        $query->setQuery('*:*');
        $query->setStart(0)->setRows(10);
        // $query->setFields(array('id','name','price'));
        // $query->addSort('price', $query::SORT_ASC);

        // create a filterquery using the API
        // $fq = $query->createFilterQuery('maxprice')->setQuery('price:[1 TO 300]');

        // create a facet field instance and set options using the API
        // $facetSet = $query->getFacetSet();
        // $facet = $facetSet->createFacetField('stock')->setField('inStock');


        // this executes the query and returns the result
        $resultset = $this->client->select($query);
        foreach($resultset as $docs){
            dd($docs);
        }

        // display the total number of documents found by solr
        echo 'NumFound: '.$resultset->getNumFound();

        /*$facet = $resultset->getFacetSet()->getFacet('stock');
        foreach ($facet as $value => $count) {
            echo $value . ' [' . $count . ']<br/>';
        }*/


    }


}
