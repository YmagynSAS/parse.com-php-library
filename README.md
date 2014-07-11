SETUP
=========================

**Instructions** Create a new config file called parse.php and place it in the application/config directory of your application.

### sample of parse.php ###

Fill in the appropriate keys. An exception will be thrown if they are not set.

```
<?php

$config['app_id'] = '';
$config['master_key'] = '';
$config['rest_key'] = '';
$config['parse_url'] = 'https://api.parse.com/1/';

?>


```



EXAMPLES
=========================

### Using ParseObject ###

```
      $this->load->library('parse');
      $testObj = $this->parse->ParseObject('testObj');
      $testObj->myObject = "It works !"
      $testObj->save();
```

### Using ParseObject Pointer ###
```
      $testObj = $this->parse->ParseObject('testObj');
  		$testObj->myObject = "It works !";
  		$testObj->save();
  
  		$user = $this->parse->parseObject('testUser');
  		$user->test = $testObj;
  		$user->save();
```

### Using ParseObject Relations ###
```
    $videos = [];
		$tags = ['tag1', 'tag2', 'tag3', 'tag4'];
		for ($i = 0; $i < 5; $i++) {
			$video = $this->parse->parseObject('Videos');
			$video->title = "My video";
			$video->url = "http://google.com";
			$video->tags = $tags;
			$video->save();
			$videos[] = $video;
		}

		$user = $this->parse->parseObject('User');
		$user->video = $video;
		$user->videos = $videos;
		$user->save();

		$userRet = $this->parse->parseObject('User')->addIncludes('video')->get($user->data['objectId'])->linkRelation('videos');
		print_r($userRet);
```
