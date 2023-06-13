<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use Illuminate\Support\Collection;

class ItemImportController extends Controller
{

	/*public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            User::create([
                'name' => $row[0],
            ]);
        }
    }*/

    public function array(array $row)
    {

        dd($row);
    }

    public function hasHeader(){
    	return true;
    }


    public function batchSize(): int
    {
        return 1000;
    }


    public function chunkSize(): int
    {
        return 1000;
    }

}