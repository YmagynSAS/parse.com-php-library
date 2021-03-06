<?php

include_once('parse.php');

if (!class_exists('parseUser')) {
	include_once('parseUser.php');
}

if (!class_exists('parseObject')) {
	include_once('parseObject.php');
}

class parseQuery extends parseRestClient {
	private $_limit = 100;
	private $_skip = 0;
	private $_count = 0;
	private $_order = array();
	public $_query = array();
	private $_include = array();
	private $_relations = array();
	private $_className = '';

	public function __construct($class=''){
		if ($class == "users" || $class == "User" || is_a($class, 'parseUser')) {
			$this->_requestUrl = "users";
			$this->_className = "_User";
		}
		else if ($class == 'installation') {
			$this->_requestUrl = $class;
			$this->_className = "_Installation";
		}
		elseif ($class != '') {
			$this->_requestUrl = 'classes/'.$class;
			$this->_className = $class;
		}
		else
			$this->throwError('include the className when creating a parseQuery');

		parent::__construct();
	}

	public function includePointer() {
		foreach (func_get_args() as $param) {
	        $this->_include[] = $param;
	    }

	    return $this;
	}

	public function includeRelations() {
		foreach (func_get_args() as $param) {
	        $this->_relations[] = $param;
	    }

	    return $this;
	}

	public function find(){
		$urlParams = array();

        if (!empty($this->_include))
            $urlParams['include'] = implode(',',$this->_include);
        if (!empty($this->_order))
            $urlParams['order'] = implode(',',$this->_order);
        if (!empty($this->_limit) || $this->_limit == 0)
            $urlParams['limit'] = $this->_limit;
        if (!empty($this->_skip))
            $urlParams['skip'] = $this->_skip;
        if (!empty($this->_query))
            $urlParams['where'] = json_encode( $this->_query );
        if($this->_count == 1)
            $urlParams['count'] = '1';

        $request = $this->request(array(
            'method' => 'GET',
            'requestUrl' => $this->_requestUrl,
            'urlParams' => $urlParams,
        ));

        $arr = [];
		if ($this->_className != "_User" && $this->_requestUrl != "_Installation") {
			$object = new parseObject($this->_requestUrl);
			foreach ($request->results as $obj) {
				$objParsed = $object->stdToParse($this->_className, $obj);
				if (!empty($this->_relations)) {
					foreach ($this->_relations as $relation) {
						$objParsed->linkRelation($relation);
					}
				}
				$arr[] = $objParsed;
			}
		}
		else if ($this->_className == "_User") {
			foreach ($request->results as $obj) {
				$user = new parseUser();

				foreach ($obj as $key => $value) {
					if (is_object($value) && isset($value->className) && $value->className != "_User" && $value->__type == "Pointer")
						$user->{$key} = $user->stdToParse($value->className, $value);
					else
						$user->{$key} = $value;
				}
				if (!empty($this->_relations)) {
					foreach ($this->_relations as $relation) {
						$user->linkRelation($relation);
					}
				}
				$arr[] = $user;
			}
		}

		$res = new StdClass();
		$res->count = ($this->_count == 1 ? (isset($request->count) ? $request->count : count($arr)) : count($arr));
		$res->results = $arr;
		return $res;
	}

	//setting this to 1 by default since you'd typically only call this function if you were wanting to turn it on
	public function setCount($bool=1) {
		if(is_bool($bool)){
			$this->_count = $bool;
		}
		else{
			$this->throwError('setCount requires a boolean paremeter');
		}
		return $this;
	}

	public function whereOr($key,$value){
        if(isset($key) && isset($value)){
            if(is_array($value)){
                $params = [];
                foreach($value as $val)
                {
                    $param = new stdClass();
                    $param->{$key} = $val;
                    $params[] = $param;
                }
                $this->_query['$or'] = $params;
            }
            else{
                $this->throwError('$value must be an array to check through');
            }
        }
        else{
            $this->throwError('the $key and $value parameters must be set when setting a "where" query method');
        }
        return $this;
    }

	public function getCount(){
		$this->_count = 1;
		$this->_limit = 0;
		return $this->find();
	}

	public function setLimit($int){
		if ($int >= 1 && $int <= 1000){
			$this->_limit = $int;
		}
		else{
			$this->throwError('parse requires the limit parameter be between 1 and 1000');
		}
		return $this;
	}

	public function setSkip($int){
		$this->_skip = $int;
		return $this;
	}

	public function orderBy($field){
		if(!empty($field)){
			$this->_order[] = $field;
		}
		return $this;
	}

	public function orderByAscending($value){
		if(is_string($value)){
			$this->_order[] = $value;
		}
		else{
			$this->throwError('the order parameter on a query must be a string');
		}
		return $this;
	}

	public function orderByDescending($value){
		if(is_string($value)){
			$this->_order[] = '-'.$value;
		}
		else{
			$this->throwError('the order parameter on parseQuery must be a string');
		}
		return $this;
	}
	
	public function whereInclude($value){
		if(is_string($value)){
			$this->_include[] = $value;
		}
		else{
			$this->throwError('the include parameter on parseQuery must be a string');
		}
		return $this;
	}

	public function where($key,$value){
		return $this->whereEqualTo($key,$value);
	}

	public function whereEqualTo($key,$value){
		if(isset($key) && isset($value)){
			if (is_object($value) && is_a($value, 'parseObject') && isset($value->data->objectId) && isset($value->_className))
				$value = array("__type" => "Pointer", "className" => $value->_className, "objectId" => $value->data->objectId);

			else if (is_object($value) && is_a($value, 'parseUser') && isset($value->data->objectId))
				$value = array("__type" => "Pointer", "className" => '_User', "objectId" => $value->data->objectId);

			$this->_query[$key] = $value;
		}
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereNotEqualTo($key,$value){
		if(isset($key) && isset($value)){
			if (is_object($value) && is_a($value, 'parseObject') && isset($value->data->objectId) && isset($value->_className))
				$value = array("__type" => "Pointer", "className" => $value->_className, "objectId" => $value->data->objectId);

			else if (is_object($value) && is_a($value, 'parseUser') && isset($value->data->objectId))
				$value = array("__type" => "Pointer", "className" => '_User', "objectId" => $value->data->objectId);

			$this->_query[$key] = array(
				'$ne' => $value
			);
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}


	public function whereGreaterThan($key,$value){
		if(isset($key) && isset($value)){
			$this->_query[$key] = array(
				'$gt' => $value
			);
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereLessThan($key,$value){
		if(isset($key) && isset($value)){
			$this->_query[$key] = array(
				'$lt' => $value
			);
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereGreaterThanOrEqualTo($key,$value){
		if(isset($key) && isset($value)){
			$this->_query[$key] = array(
				'$gte' => $value
			);
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereLessThanOrEqualTo($key,$value){
		if(isset($key) && isset($value)){
			$this->_query[$key] = array(
				'$lte' => $value
			);
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereAll($key,$value){
		if(isset($key) && isset($value)){
			if(is_array($value)){
				$this->_query[$key] = array(
					'$all' => $value
				);		
			}
			else{
				$this->throwError('$value must be an array to check through');		
			}
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}


	public function whereContainedIn($key,$value){
		if(isset($key) && isset($value)){
			if(is_array($value)){
				$this->_query[$key] = array(
					'$in' => $value
				);		
			}
			else{
				$this->throwError('$value must be an array to check through');		
			}
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereNotContainedIn($key,$value){
		if(isset($key) && isset($value)){
			if(is_array($value)){
				$this->_query[$key] = array(
					'$nin' => $value
				);		
			}
			else{
				$this->throwError('$value must be an array to check through');		
			}
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function whereExists($key){
		if(isset($key)){
			$this->_query[$key] = array(
				'$exists' => true
			);
		}
		return $this;
	}

	public function whereDoesNotExist($key){
		if(isset($key)){
			$this->_query[$key] = array(
				'$exists' => false
			);
		}
		return $this;
	}

	public function whereRegex($key,$value,$options='') {
		if(isset($key) && isset($value)){
			$this->_query[$key] = array(
				'$regex' => $value
			);

			if(!empty($options)){
				$this->_query[$key]['$options'] = $options;
			}
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}

	public function wherePointer($key, $className, $objectId){
		if(isset($key) && isset($className)){
			$this->_query[$key] = $this->dataType('pointer', array($className, $objectId));
		}	
		else{
			$this->throwError('the $key and $className parameters must be set when setting a "where" pointer query method');		
		}
		return $this;
	}

    public function whereInQuery($key, $className, $inQuery){
        if(isset($key) && isset($className)){
            $this->_query[$key] = array(
                '$inQuery' => array('where' => $inQuery->_query, 'className' => $className)
            );
        }
        else{
            $this->throwError('the $key and $value parameters must be set when setting a "where" query method');
        }

    }

	public function whereNotInQuery($key,$className,$inQuery){
		if(isset($key) && isset($className)){
			$this->_query[$key] = array(
				'$notInQuery' => $inQuery,
				'className' => $className
			);
		}	
		else{
			$this->throwError('the $key and $value parameters must be set when setting a "where" query method');		
		}
		return $this;
	}
}

?>
