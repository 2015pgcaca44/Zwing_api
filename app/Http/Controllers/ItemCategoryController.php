<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Model\Items\VendorItemCategoryIds;

class ItemCategoryController extends Controller
{
	private $v_id;
	public function getCategory(){
		

		$category  = VendorItemCategoryIds::where(['deleted' => 0, 'vendor_item_category_ids.v_id' => $this->v_id])
			->join('item_category', 'vendor_item_category_ids.item_category_id', 'item_category.id')
			->select('item_category.id as id', 'name', 'vendor_item_category_ids.parent_id as parent_id')
			->orderBy('vendor_item_category_ids.parent_id', 'asc')
			->get()->toArray();

		return $category ;
	}

	/*Get All Categories*/
	public function get_all_category($v_id)
	{

		$this->v_id = $v_id;

		$category  = $this->getCategory($v_id);

		//print_r($category);die;
		/*$category  = ItemCategory::select(['id','name','parent_id'])
            ->whereHas('vendor', function($query) {
                $query->where('v_id', Auth::user()->v_id);
            })
            ->where('deleted', '0')
            ->orderBy('parent_id','asc')
            ->get()->toArray();*/
		$datas =  $this->createTree($category, 0);
		$treeData =  $this->createTreeValues($datas);
		$treeData = collect($treeData)->sortBy('label');
		$treeData =  $treeData->values()->all();
		//print_r($treeData);die;
		return $treeData;
	} //End of get_all_category

	public function createTree(&$menus, $parent_id, $update = false, $level =1 )
	{
		$menu_add = [];
		foreach ($menus as $key => $menu) {

			if ($menu['parent_id'] == $parent_id) { //This is a node
				$menu_add[$menu['id']] = ['label' => $menu['name'], 'id' => $menu['id'] , 'level' => $level];
				if($update){// Update level Column 
					VendorItemCategoryIds::where(['item_category_id' => $menu['id'] ,'v_id' => $this->v_id])->update(['level' => $level ]);
				}
				unset($menus[$key]);
			} else { //
				if (isset($menu_add[$menu['parent_id']])) {
			
					$children = $this->createTree($menus, $menu['parent_id'], $update, $level + 1 );
					if (count($children) > 0) {
						unset($menu_add[$menu['parent_id']]['children']);
						$menu_add[$menu['parent_id']]['children'] = $children;
					}
				}
			}
		}
		//print_r($menus);
		return $menu_add;
	}


	public function createTreeValues(&$menus)
	{
		$newArray = [];
		foreach ($menus as $key => $menu) {
			if (isset($menu['children'])) {
				$children = $this->createTreeValues($menu['children']);
				$newArray[] = ['id' => $menu['id'], 'label' => $menu['label'], 'level' => $menu['level'], 'children' => $children];
			} else {
				$newArray[] = ['id' => $menu['id'], 'label' => $menu['label'] , 'level' => $menu['level'] ];
			}
		}

		return $newArray;
	}

	public function updateCategoryLevel($v_id){

		$this->v_id = $v_id;
		$category  = $this->getCategory();
		//Value true for updating level column
		$this->createTree($category, 0, true);
	}
}