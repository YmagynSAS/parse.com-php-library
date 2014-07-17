<?php

include_once('parse.php');

class parseUser extends parseRestClient {

	public $authData;

	private function pointer($name, $value) {
		if (is_array($value)) {
			$relation = new StdClass();
			$relation->__op = "AddRelation";
			foreach ($value as $k => $v) {
				if (is_object($v) && is_a($v, 'parseObject') && isset($v->data['objectId']) && isset($v->_className)) {
					$relation->objects[] = array("__type" => "Pointer", "className" => $v->_className, "objectId" => $v->data['objectId']);
				}
				else {
					$this->data[$name] = $value;
					return $this;
				}
			}
			$this->data[$name] = $relation;
		}
		else if (is_object($value) && is_a($value, 'parseObject') && isset($value->data['objectId']) && isset($value->_className)) {
			$this->data[$name] = array("__type" => "Pointer", "className" => $value->_className, "objectId" => $value->data['objectId']);
		}
		else if (is_object($value) && is_a($value, 'parseUser') && isset($value->data['objectId'])) {
			$this->data[$name] = array("__type" => "Pointer", "className" => '_User', "objectId" => $value->data['objectId']);
		}
		else {
			$this->data[$name] = $value;
		}

		return $this;
	}

	public function __set($name, $value) {
		if (is_object($value) || is_array($value)) {
			$this->pointer($name, $value);
		}
		else if ($name != '_className') {
			$this->data[$name] = $value;
		}
	}

	public function signup($username='', $password=''){
		if($username != '' && $password != ''){
			$this->username = $username;
			$this->password = $password;
		}

		if($this->data['username'] != '' && $this->data['password'] != ''){
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
			$this->data['username'] = $username;
			$this->data['password'] = $password;
		}

		if(!empty($this->data['username']) || !empty($this->data['password'])	){
			$request = $this->request(array(
				'method' => 'GET',
	    		'requestUrl' => 'login',
		    	'data' => array(
		    		'password' => $this->data['password'],
		    		'username' => $this->data['username']
		    	)
			));

			foreach ($request as $key => $value) {
				$this->data[$key] = $value;
			}

	    	return $this;
		}
		else{
			$this->throwError('username and password field are required for the login method');
		}
	
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
			$request = $this->request(array(
				'method' => 'GET',
	    		'requestUrl' => 'users/'.$objectId,
			));
			
	    	return $request;			
			
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
				if (isset($this->data[$value]))
					unset($this->data[$value]);
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

	
}

?>