<?php

include_once('parse.php');
include_once('parseQuery.php');

if (!class_exists('parseUser')) {
	include_once('parseUser.php');
}

class parseObject extends parseRestClient {
	public $_includes = array();
	public $_className = '';

	public function __construct($class=''){
		$this->data = new StdClass();
		if($class != ''){
			$this->_className = $class;
		}
		else{
			$this->throwError('include the className when creating a parseObject');
		}

		parent::__construct();
	}

	private function pointer($name, $value) {
		if (is_array($value)) {
			$relation = new StdClass();
			$relation->__op = "AddRelation";
			foreach ($value as $k => $v) {
				if (is_object($v) && is_a($v, 'parseObject') && isset($v->data->objectId) && isset($v->_className)) {
					$relation->objects[] = array("__type" => "Pointer", "className" => $v->_className, "objectId" => $v->data->objectId);
				}
				else if (is_object($v) && is_a($v, 'parseUser') && isset($v->data->objectId)) {
					$relation->objects[] = array("__type" => "Pointer", "className" => '_User', "objectId" => $v->data->objectId);
				}
				else {
					$this->data->{$name} = $value;
					return $this;
				}
			}
			$this->data->{$name} = $relation;
		}
		else if (is_object($value) && is_a($value, 'parseObject') && isset($value->data->objectId) && isset($value->_className)) {
			$this->data->{$name} = array("__type" => "Pointer", "className" => $value->_className, "objectId" => $value->data->objectId);
		}
		else if (is_object($value) && is_a($value, 'parseUser') && isset($value->data->objectId)) {
			$this->data->{$name} = array("__type" => "Pointer", "className" => '_User', "objectId" => $value->data->objectId);
		}
		else {
			$this->data->{$name} = $value;
		}

		return $this;
	}

	public function __set($name, $value) {
		if ($name == "data")
			$this->throwError('You can\'t set `data`, use $obj->setData() instead', 403);
		if ($this->data == null)
			$this->data = new StdClass();

		if (is_object($value) || is_array($value)) {
			$this->pointer($name, $value);
		}
		else if ($name != '_className') {
			$this->data->{$name} = $value;
		}
	}

	public function setData(StdClass $data) {
		if ($this->data == null)
			$this->data = new StdClass();

		foreach ($data as $key => $value) {
			if (is_object($value) || is_array($value)) {
				$this->pointer($key, $value);
			}
			else if ($key != '_className') {
				$this->data->{$key} = $value;
			}
		}
	}

	public function save() {
		if (isset($this->data->objectId)) {
			return $this->update($this->data->objectId);
		}
		else {
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

				$this->data->objectId = $request->objectId;
				$this->data->createdAt = $request->createdAt;
				return $request;
			}
		}
	}

	private function getRelation($key, $relation) {
		$relatedTo = [
			'object' => [
				'__type' => 'Pointer',
				'className' => $this->_className,
				'objectId' => $this->data->objectId
			],
			'key' => $key
		];
		$query = new parseQuery($relation->className);
		$query->where('$relatedTo', $relatedTo);
		$resp = $query->find();
		return $resp;
	}

	public function addToRelation($name, parseRestClient $value) {
		if (!isset($this->data->{$name})) {
			$this->pointer($name, [$value]);
		}
		else {
			if (is_object($this->data->{$name}) && isset($this->data->{$name}->__type) && $this->data->{$name}->__type == "Relation") {
				$this->linkRelation($name);
				$this->addToRelation($name, $value);
			}
			else {
				$relation = $this->data->{$name};
				$relation[] = $value;
				$this->pointer($name, $relation);
			}
		}
		return $this;
	}

	public function linkRelation($key, $include_relation = FALSE) {
		if (!isset($this->data->{$key}))
			$this->throwError($key . " don't exist");
		$value = $this->data->{$key};
		if (!isset($value->__type) || (isset($value->__type) && $value->__type != "Relation"))
			$this->throwError($key . " is not a relation");
			
		$resp = $this->getRelation($key, $value);
		$arr = [];
		if (isset($resp->results))
			$result = $resp->results;
		else
			$result = $resp;
		foreach ($result as $res) {
			$arr[] = $this->stdToParse($value->className, $res, $include_relation);
		}
		$this->data->{$key} = $arr;
		return $this;
	}

	public function stdToParse($class, $obj, $include_relation = FALSE) {
		$objRet = new parseObject($class);

		foreach ($obj as $key => $value) {
			if (is_object($value) && isset($value->__type) && $value->__type == "Object") {
				$className = $value->className;
				unset($value->className);
				$objRet->data->{$key} = $this->stdToParse($className, $value, $include_relation);
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
		if (empty($id) || $id == null)
			$this->throwError('Id cannot be empty or null', 1);
		if($this->_className != '' || !empty($id)) {
			$urlParams = [];
			if(!empty($this->_includes)) {
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

			if ($this->data == null)
				$this->data = new StdClass();
			$this->data->objectId = $id;
			$this->data = $this->stdToParse($this->_className, $request, $include_relation)->data;
			return $this;
		}
	}

	public function update($id){
		if($this->_className != '' || !empty($id)){

			unset($this->data->objectId);
			unset($this->data->createdAt);
			$request = $this->request(array(
				'method' => 'PUT',
				'requestUrl' => 'classes/'.$this->_className.'/'.$id,
				'data' => $this->data,
			));

			return $request;
		}
	}

	public function increment($field,$amount){
		$this->data->{$field} = $this->dataType('increment', $amount);
		return $this;
	}

	public function decrement($id){
		$this->data->{$field} = $this->dataType('decrement', $amount);
		return $this;
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

	    return $this;
	}
}

?>