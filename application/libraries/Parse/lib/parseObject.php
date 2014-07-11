<?php

include_once('parse.php');
include_once('parseQuery.php');

class parseObject extends parseRestClient{
	public $_includes = array();
	public $_className = '';

	public function __construct($class=''){
		if($class != ''){
			$this->_className = $class;
		}
		else{
			$this->throwError('include the className when creating a parseObject');
		}

		parent::__construct();
	}

	public function pointer($name, $value) {
		if (is_array($value)) {
			$relation = new StdClass();
			$relation->__op = "AddRelation";
			foreach ($value as $k => $v) {
				if (is_object($v) && is_a($v, 'parseObject') && isset($v->data['objectId']) && isset($v->_className)) {
					$relation->objects[] = array("__type" => "Pointer", "className" => $v->_className, "objectId" => $v->data['objectId']);
				}
			}
			$this->data[$name] = $relation;
		}

		if (is_object($value) && is_a($value, 'parseObject') && isset($value->data['objectId']) && isset($value->_className)) {
			$this->data[$name] = array("__type" => "Pointer", "className" => $value->_className, "objectId" => $value->data['objectId']);
		}
	}

	public function __set($name, $value) {
		if($name != '_className') {
			$this->data[$name] = $value;
		}
	}

	public function save() {
		if(count($this->data) > 0 && $this->_className != ''){
			try {
				$request = $this->request(array(
					'method' => 'POST',
					'requestUrl' => 'classes/'.$this->_className,
					'data' => $this->data,
				));
			} catch (Exception $e) {
				die("An error occured while you trying to save your object:" . $e->getMessage());
			}

			$this->data['objectId'] = $request->objectId;
			$this->data['createdAt'] = $request->createdAt;
			return $request;
		}
	}

	private function getRelation($key, $relation) {
		$relatedTo = [
			'object' => [
				'__type' => 'Pointer',
				'className' => $this->_className,
				'objectId' => $this->data['objectId']
			],
			'key' => $key
		];
		$query = new parseQuery($relation->className);
		$query->where('$relatedTo', $relatedTo);
		$resp = $query->find();
		return $resp;
	}

	public function linkRelation($key, $include_relation = FALSE) {
		if (!isset($this->data[$key]))
			$this->throwError($key . " don't exist");
		$value = $this->data[$key];
		if (!isset($value->__type) || (isset($value->__type) && $value->__type != "Relation"))
			$this->throwError($key . " is not a relation");
			
		$resp = $this->getRelation($key, $value);
		$arr = [];
		foreach ($resp->results as $res) {
			$arr[] = $this->stdToParse($value->className, $res, $include_relation);
		}
		$this->data[$key] = $arr;
		return $this;
	}

	private function stdToParse($class, $obj, $include_relation = FALSE) {
		$objRet = new parseObject($class);

		foreach ($obj as $key => $value) {
			if (is_object($value) && isset($value->__type) && $value->__type == "Object") {
				$className = $value->className;
				unset($value->className);
				$objRet->{$key} = $this->stdToParse($className, $value, $include_relation);
			}
			else if (is_object($value) && isset($value->__type) && $value->__type == "Relation" && $include_relation) {
				$resp = $this->getRelation($key, $value);
				$arr = [];
				foreach ($resp->results as $res) {
					$arr[] = $this->stdToParse($value->className, $res, $include_relation);
				}
				$objRet->{$key} = $arr;
			}
			else {
				$objRet->{$key} = $value;
			}
		}
		return $objRet;
	}

	public function get($id, $include_relation = FALSE){
		if($this->_className != '' || !empty($id)) {
			$urlParams = [];
			if(!empty($this->_includes)){
				$urlParams['include'] = implode(',', $this->_includes);
			}

			try {
				$request = $this->request(array(
					'method' => 'GET',
					'requestUrl' => 'classes/'.$this->_className.'/'.$id,
					'urlParams' => $urlParams
				));
			} catch (Exception $e) {
				die("An error occured while you trying to get an object:" . $e->getMessage());
			}

			$this->data['objectId'] = $id;
			return $this->stdToParse($this->_className, $request, $include_relation);

			return $request;
		}
	}

	public function update($id){
		if($this->_className != '' || !empty($id)){
			$request = $this->request(array(
				'method' => 'PUT',
				'requestUrl' => 'classes/'.$this->_className.'/'.$id,
				'data' => $this->data,
			));

			return $request;
		}
	}

	public function increment($field,$amount){
		$this->data[$field] = $this->dataType('increment', $amount);
	}

	public function decrement($id){
		$this->data[$field] = $this->dataType('decrement', $amount);
	}

	public function delete($id){
		if($this->_className != '' || !empty($id)){
			$request = $this->request(array(
				'method' => 'DELETE',
				'requestUrl' => 'classes/'.$this->_className.'/'.$id
			));

			return $request;
		}		
	}

	public function addIncludes($name){
		foreach (func_get_args() as $param) {
	        $this->_includes[] = $param;
	    }
	}
}

?>