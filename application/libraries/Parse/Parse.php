<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Parse {
	public function __construct() {

	}

	public function ParseObject($className) {
		include_once('lib/parseObject.php');
		return new parseObject($className);
	}

	public function ParseUser() {
		include_once('lib/parseUser.php');
		return new parseUser();
	}

	public function ParseQuery($className) {
		include_once('lib/parseQuery.php');
		return new parseQuery($className);
	}

	public function ParsePush($globalMsg) {
		include_once('lib/parsePush.php');
		return new parsePush($globalMsg);
	}

	public function ParseGeoPoint($lat,$long) {
		include_once('lib/parseGeoPoint.php');
		return new parseGeoPoint($lat,$long);
	}
}