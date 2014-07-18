<?php

include_once('parse.php');
include_once('parseQuery.php');

if (!class_exists('parseObject')) {
	include_once('parseObject.php');
}

class parseUser extends parseRestClient {
	public $_includes = array();
	public $authData;

	function __construct() {
		$this->data = new StdClass();
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
		if ($this->data == null)
			$this->data = new StdClass();

		if (is_object($value) || is_array($value)) {
			$this->pointer($name, $value);
		}
		else if ($name != '_className') {
			$this->data->{$name} = $value;
		}
	}

	public function signup($username='', $password=''){
		if($username != '' && $password != ''){
			if (is_null($this->data))
				$this->data = new StdClass();

			$this->data->username = $username;
			$this->data->password = $password;
		}

		if($this->data->username != '' && $this->data->username != ''){
			$request = $this->request(array(
				'method' => 'POST',
	    		'requestUrl' => 'users',
				'data' => $this->data
			));
			
	    	return $request;
			
		}
		else{
			$this->throwError('username and password fields are required for the signup method');
		}
	}

	public function login($username='', $password='') {
		if($username != '' && $password != ''){
			if (is_null($this->data))
				$this->data = new StdClass();
			$this->data->username = $username;
			$this->data->password = $password;
		}

		if(!empty($this->data->username) || !empty($this->data->password)	){
			$urlParams = [];
			if(!empty($this->_includes)){
				$urlParams['include'] = implode(',', $this->_includes);
			}

			$request = $this->request(array(
				'method' => 'GET',
	    		'requestUrl' => 'login',
		    	'data' => array(
		    		'password' => $this->data->password,
		    		'username' => $this->data->username,
		    	)
			));

			foreach ($request as $key => $value) {
				if (is_object($value) && isset($value->className) && $value->className != "_User" && $value->__type == "Pointer")
					$this->data->{$key} = $this->stdToParse($value->className, $value);
				else
					$this->data->{$key} = $value;
			}

	    	return $this;
		}
		else{
			$this->throwError('username and password field are required for the login method');
		}
	
	}

	private function getRelation($key, $relation) {
		$relatedTo = [
			'object' => [
				'__type' => 'Pointer',
				'className' => '_User',
				'objectId' => $this->data->objectId
			],
			'key' => $key
		];
		$query = new parseQuery($relation->className);
		$query->where('$relatedTo', $relatedTo);
		$resp = $query->find();
		return $resp;
	}

	public function linkRelation($key, $include_relation = FALSE) {
		if (!isset($this->data->{$key}))
			$this->throwError($key . " don't exist");
		$value = $this->data->{$key};
		if (!isset($value->__type) || (isset($value->__type) && $value->__type != "Relation"))
			$this->throwError($key . " is not a relation");
			
		$resp = $this->getRelation($key, $value);
		$arr = [];
		foreach ($resp->results as $res) {
			$arr[] = $this->stdToParse($value->className, $res, $include_relation);
		}
		$this->data->{$key} = $arr;
		return $this;
	}

	public function addToRelation($name, parseObject $value) {
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
	}

	private function stdToParse($class, $obj, $include_relation = FALSE) {
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

	public function socialLogin(){
		if(!empty($this->authData)){
			$request = $this->request( array(
				'method' => 'POST',
				'requestUrl' => 'users',
				'data' => array(
					'authData' => $this->authData
				)
			));
			return $request;
		}
		else{
			$this->throwError('authArray must be set use addAuthData method');
		}
	}

	public function get($objectId){
		if($objectId != ''){
			$urlParams = [];
			if(!empty($this->_includes)){
				$urlParams['include'] = implode(',', $this->_includes);
			}

			$request = $this->request(array(
				'method' => 'GET',
	    		'requestUrl' => 'users/'.$objectId,
	    		'urlParams' => $urlParams
			));
			
			if ($this->data == null)
				$this->data = new StdClass();

	    	foreach ($request as $key => $value) {
				$this->data->{$key} = $value;
			}

	    	return $this;			
		}
		else{
			$this->throwError('objectId is required for the get method');
		}
		
	}

	//TODO: should make the parseUser contruct accept the objectId and update and delete would only require the sessionToken
	public function update($objectId, $sessionToken){
		if(!empty($objectId) || !empty($sessionToken)){

			$clean = ['sessionToken', 'createdAt', 'objectId', 'updatedAt'];
			foreach ($clean as $value) {
				if (isset($this->data->{$value}))
					unset($this->data->{$value});
			}

			$request = $this->request(array(
				'method' => 'PUT',
				'requestUrl' => 'users/'.$objectId,
	    		'sessionToken' => $sessionToken,
				'data' => $this->data
			));
			
	    	return $request;			
		}
		else{
			$this->throwError('objectId and sessionToken are required for the update method');
		}
		
	}

	public function delete($objectId,$sessionToken){
		if(!empty($objectId) || !empty($sessionToken)){
			$request = $this->request(array(
				'method' => 'DELETE',
				'requestUrl' => 'users/'.$objectId,
	    		'sessionToken' => $sessionToken
			));
			
	    	return $request;			
		}
		else{
			$this->throwError('objectId and sessionToken are required for the delete method');
		}
		
	}
	
	public function addAuthData($authArray){
		if(is_array($authArray)){			
			$this->authData[$authArray['type']] = $authArray['authData'];
		}
		else{
			$this->throwError('authArray must be an array containing a type key and a authData key in the addAuthData method');
		}
	}

	public function linkAccounts($objectId,$sessionToken){
		if(!empty($objectId) || !empty($sessionToken)){
			$request = $this->request( array(
				'method' => 'PUT',
				'requestUrl' => 'users/'.$objectId,
				'sessionToken' => $sessionToken,
				'data' => array(
					'authData' => $this->authData
				)
			));

			return $request;
		}
		else{
			$this->throwError('objectId and sessionToken are required for the linkAccounts method');
		}		
	}

	public function unlinkAccount($objectId,$sessionToken,$type){
		$linkedAccount[$type] = null;

		if(!empty($objectId) || !empty($sessionToken)){
			$request = $this->request( array(
				'method' => 'PUT',
				'requestUrl' => 'users/'.$objectId,
				'sessionToken' => $sessionToken,
				'data' => array(
					'authData' => $linkedAccount
				)
			));

			return $request;
		}
		else{
			$this->throwError('objectId and sessionToken are required for the linkAccounts method');
		}		

	}

	public function requestPasswordReset($email){
		if(!empty($email)){
			$this->email - $email;
			$request = $this->request(array(
			'method' => 'POST',
			'requestUrl' => 'requestPasswordReset',
			'email' => $email,
			'data' => $this->data
			));

			return $request;
		}
		else{
			$this->throwError('email is required for the requestPasswordReset method');
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