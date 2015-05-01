<?php
/** Hicurl*/


/**
 * A class that simplifies URL requests. Rather than working with CURL and its settings directly this
 * class maks it easy by using simple syntax for the most common settings.
 * But more importantly this class also allows for easily saving the history of the requests&responses
 * with their contens/headers.*/
class Hicurl {
	
	/**
	 * @var array This is an array of settings which the user can define. They are set either through the
	 * class-constructor or via settings(). {@see settings()} for more info.*/
	private $settingsData;
	
	/**
	 * @var resource Curl Handler*/
	private $curlHandler;
	
	/**
	 * @var SplFileObject file-object for the history file.*/
	private $historyFileObject;
	
	/**
	 * @var array Default settings used when creating an instance of Hicurl or calling its load-methods statically.*/
	static public $defaultSettings=[
		'maxFruitlessRetries'=>40,
		'fruitlessPassDelay'=>10,
		'maxRequestsPerPass'=>100,
		'retryOnNull'=>true,
		'retryOnIncompleteHTML'=>true
	];
	
	/**
	 * Constructs an instance of Hicurl with the given settings.
	 * @see Hicurl::loadSingleStatic() blah
	 * @see loadSingleStatic() blah
	 * @see loadSingleStatic blah
	 * @param array $settings The same data as can be passed to settings().*/
    function __construct($settings=[]) {
		$this->settingsData=Hicurl::$defaultSettings;
		if ($settings)
			$this->settings($settings);
		$this->curlHandler=curl_init();
    }
	
	/**
	 * This method is used for changing settings after having constructed an instance. The settings
	 * passed to this method will be merged with the current settings. To remove a setting, set it to null. Besides
	 * calling this method the settings can also temporarily be changed during one load-call by passing the settings
	 * there as an argument, same merge-rules apply.
	 * @param array|null $settings If this argument is ommited then no settings are changed, and the method only
	 * returns the current settings.
	 * Otherwise an array with a combination of the following settings should be passed:
	 *	<ul>
	 *		<li>'maxFruitlessRetries' int Defaults to 40. Max amount of retries before a load-function gives up. For
	 *			loadSingle this is simply the number of retries for that url. For loadMulti it is the max number of
	 *			consecutive retries for the same group of requests.</li>
	 *		<li>'fruitlessPassDelay' int Defaults to 10. Number of seconds that the script should sleep for when for
	 *			singleLoad a request fails, or for multiLoad a whole group fails.</li>
	 *		<li>'maxRequestsPerPass' int	Defaults to 100. Used for loadMulti(). If the number of urls passed to loadMulti()
	 *			is less than this value then it will be split into groups at the size of this.</li>
	 *		<li>'cookie'	string If this is set to a path to a file then cookies will be saved to that file, and those cookies
	 *			will also be sent on each request. Ex: dirname(__FILE__).'/cookie.txt</li>
	 *		<li>'retryOnNull' bool If true then load-calls will retry requests when response-content is null.</li>
	 *		<li>'retryOnIncompleteHTML' bool if true then load-calls will retry requests when response	doesn't end
	 *			with a HTML closing tag (That is "&lt/html&gt") with possible following whitespace.</li>
	 *		<li>'xpath' string|string[]|true An xpath-expression that needs to evaluate to true or return at least 1
	 *			node otherwise the request will be deemed unsuccessfull. Rather than a single xpath-expression an array
	 *			of them may be passed in which case all of them need to evaluate to true for the request to be
	 *			successfull. This setting will also place a DOMXPATH-object with key "domXPath" in the array returned
	 *			by the load-call. A value of true may also be passed in which case the DOMXPATH-object will be
	 *			generated, but no xpath-evaluation will take place.
	 *		</li>
	 *		<li>'postHeaders' array An array of headers that will be sent on POST-requests.</li>
	 *		<li>'getHeaders' array An array of headers that will be sent on GET-requests</li>
	 *		<li>'history' string Path to a directory of where to save history to.<br>
	 *			Setting this option enables history-saving. All contents of requested pages along with
	 *			request/response-headers willbe saved to this directory. If the specified folder doesn't exist then it
	 *			will automatically be created(recusively). When done with the directory it should be compiled into a
	 *			single file using {@see compile()}.
	 *		</li>
	 *		<li>'tor' bool If true then a proxy on port 9050 will be used for the requsts.</li>
	 *	</ul>
	 * @return array The resulted settings*/
	public function settings($settings=null) {
		if ($settings) {
			if (array_key_exists('history',$settings)) {//if a setting for 'history' is present
				$historyPath=$settings['history'];
				if (!$historyPath) {//if its null/false
					$this->historyFileObject=null;//then close old connection

					//else if it is non null/false and that it isn't equal to what's already set
				} else if (!isset($this->historyFileObject)||$historyPath!=$this->$historyPath) {
					$this->historyFileObject=Hicurl::setupHistoryFolder($historyPath);
				}
			}
			//merge supplied settings with current settings of instance
			$this->settingsData=$settings+$this->settingsData;
		}
		return $this->settingsData;
	}
	private static function setupHistoryFolder($historyPath) {
		if (!is_dir($historyPath)) {//if the specified folder does not exist
			if (file_exists($historyPath)) {//or it does exist but it is not actually a folder but a file
				trigger_error("Hicurl history option is set to a file. It should be set to a path to a "
					. "folder which may or may not exist.", E_USER_ERROR);
			}
			mkdir($historyPath, 0777, true);
		}
		$historyDataFileObject=new SplFileObject("$historyPath/data.json",'c+');
		$historyDataFileObject->flock(LOCK_EX);
		if ($historyDataFileObject->fstat()['size']==0) {
			$historyDataFileObject->fwrite('{"pages":[]}');
		}
		mkdir("$historyPath/pages");
		$historyDataFileObject->flock(LOCK_UN);
		return $historyDataFileObject;
	}
	/**
	 * Writes history to a history-directory. Each page-content gets its own file, and there's also a json-file of the
	 * name "data.json" that is shared among all requests.
	 * The structure of "data.json" is as follows:
	 * <ul>
	 *		<li>["pages"] array An array where each page (eg. each call to load() gets its own element)
	 *			<ul>
	 *				<li>["formData"] object An object of the form-data if any that was used in the case of
	 *						a POST request.</li>
	 *				<li>["name"] string A name of the page that will be shown in the history-viewer. Set via the
	 *						"name"-element of the historyData-parameter of load().</li>
	 *				<li>["parentIndex"] int May hold an index-integer pointing of another page in the pages-array,
	 *						specifying it as a parent-request of this one. This will then be reflected in the tree-view
	 *						of the history-viewer. This is set up using "id"&"parentId"-elements  of the
	 *						historyData-parameter of load().</li>
	 *				<li>["customData"] mixed Contains what was passed as the "customData"-element of the
	 *						historyData-parameter of load() if anything.</li>
	 *				<li>["exchanges"] array Array of request&response pairs. Usually this will only contain
	 *					one element but more will be added for each failed request.
	 *					<ul>
	 *						<li>["error"] false|string Is false if no error, otherwise an explanation-string
	 *							of the error.</li>
	 *						<li>["content"] string The name of the file containing the content that the
	 *								server responded with.</li>
	 *						<li>["headers"] object ...and the headers.</li>
	 *					</ul>
	 *				</li>
	 *			</ul>
	 *		</li>
	 *		<li>["customData"]</li>
	 * </ul>
	 * @param SplFileObject $historyDataFileObject The file-object of the "data.json"-file in $historyDirectory.
	 * @param string[] $pageContents An indexed array of content-strings for this page. Contains 1 usually but more when
	 *		requests fail.
	 * @param array $pageObject A page object as in the "pages"-array described in the description of this method,
	 *			minus the "content"-element of the elements in the "exchanges"-array. Each element of the
	 *			"exchanges"-array corresponds to one content with the same index in the $pageContents-argument.
	 * @param array $settings
	 * @param array $historyOptions*/
	private static function writeHistory($historyDataFileObject,$pageContents,$pageObject,$settings,$historyOptions) {
		
		$historyDirectory=$settings['history'];
		//$historyDataFileObject->rewind();
		
		//$historyData=json_decode($historyDataFileObject->fread($historyDataFileObject->fstat()['size']),true);
		$numExchanges=count($pageContents);
		$historyDataFileObject->flock(LOCK_EX);
		for ($i=0; $i<$numExchanges; ++$i) {
			if (isset($historyOptions['name'])) {
				$wantedFileName=preg_replace("([^\w\s\d\-_~,;:\[\]\(\)])", '', $historyOptions['name']);
			} else {
				$wantedFileName=time();
			}
			if ($numExchanges>1)
				$wantedFileName.="_$i";
			$fileName=$wantedFileName;
			for ($j=0; file_exists($historyDirectory.$fileName); ++$j) {       
				$fileName=$wantedFileName."($j)";
			}
			
			//locking not needed since this can only be run by one thread at a time, which is the one that holds the
			//lock of $historyDataFileObject
			file_put_contents("$historyDirectory/$fileName", $pageContents[$i]);
			$pageObject['exchanges'][$i]['content']=$fileName;
		}		
		$historyDataFileObject->fseek(-2, SEEK_END);
		$size=$historyDataFileObject->fstat()['size'];
		$historyDataFileObject->fwrite(($size>12?',':'').json_encode($pageObject).']}');
		$historyDataFileObject->flock(LOCK_UN);
	}
	
	/**
	 * Loads a requested url, using specified settings and returns the server-response along with some data and
	 * optionally saves data to a "history-folder".
	 * This method has a static counterpart of the name loadSingleStatic which works just the same.
	 * @param string $url A URL-string to load
	 * @param array|null $formdata If this is null then the request is sent as GET. Otherwise it is sent as POST using
	 *		this array as formdata where key=name and value=value. It may be an empty array in which case the request
	 *		will stil be sent as POST butwith no formdata.
	 * @param array $settings Optional parameter of settings that will be merged with the settings of the instance for
	 *		the duration of this call only. See Hicurl->settings() for explenation on the settings. The settings that
	 *		may be passed to that function are identical to those that may be passed to this.
	 * @param array $history Associative array of various settings used for the history-writing which will only be
	 *		used if settings['history'] is set. The following are valid settings for it:
	 *	<ul>
	 *		<li>['name'] string a name for the historypage that will be visible in the history-viewer.</li>
	 *		<li>['id'] String|Integer|Float An id may be set here which then can be used to refer to this page
	 *			as a parent of another.</li>
	 *		<li>['parentId'] String|Integer|Float The id of another page may be set here to refer to it as the
	 *			parent of this page.</li>
	 *		<li>['customData'] mixed This may be set to anything that is json-friendly. It will be assigned to the
	 *			root of the page-object in the final, compiled json-object with the same name e.g. 'customData'.</li>
	 *	</ul>
	 * @return array An associative array with the following elements:
	 * <ul>
	 *		<li>['content'] string The content of the requested url. In case the request had to be retried, this will
	 *			only contain the final content. Will be set to null if request failed indefinately.</li>
	 *		<li>['headers'] array The headers that go with the above content. Null if request failed indefinately.</li>
	 *		<li>['error'] string|false Description-string of the error in case the request failed indefinately, or
	 *			false if it succeeded. Like the other values, this only applies to the last requested page in case
	 *			of failures and retries.</li>
	 *		<li>['errorCode'] int An error-code integer that goes with ['error']. Like the other values, this only
	 *			applies to the last requested page in case of failures and retries.
	 *			<br>Possible values are:<ul>
	 *			<li>1: Tor-proxy related error</li>
	 *			</ul>
	 *		</li>
	 * </ul>
	 * @see loadSingleStatic()*/
	public function loadSingle($url,$formdata=null,$settings=[],$history=[]) {
		//We want to pass the historyFileObject of this instance to loadSingleReal if it has one, given it doesn't
		//(temporarily) get overwritten by the history-item in the $settings-argument of this function.
		$historyFileObject=null;//if the next condition fails then null will be passed as history-argument
		if ($this->historyFileObject//if the instance has a historyFileObject and there's no history-item in the
		//$settings-argument, or if there is and its setting is the same as the one in settings of the instance
			&&(!array_key_exists('history', $settings) || $settings['history']==$this->settingsData['history'])) {
			$historyFileObject=$this->historyFileObject;//...then the instance fileObject will be used
		}
			
		return Hicurl::loadSingleReal($this->curlHandler,$historyFileObject, $url, $formdata,
				($settings?$settings:[])+$this->settingsData,$history);
	}
	
	/**
	 * Loads a requested url, using specified settings and returns the server-response along with some data and
	 * optionally saves data to a "history-folder".
	 * This method has a instance counterpart of the name loadSingle which works just the same.
	 * @see loadSingle()*/
	public static function loadSingleStatic($url,$formdata=null,$settings=[],$history=[]) {
		$historyFileObject=null;
		if (isset($settings['history']))
				$historyFileObject=Hicurl::setupHistoryFolder($settings['history']);
		return Hicurl::loadSingleReal(curl_init(),$historyFileObject,$url,$formdata,
				($settings?$settings:[])+Hicurl::$defaultSettings,$history);
	}
	
	/**
	 * The method which both loadSingle and loadSingleStatic calls, and which is the "real" loading-function
	 * @param resource $curlHandler
	 * @param SplFileObject|null $historyFileObject The instance method loadSingle may pass its existing fileObject
	 * @param string $url Url to load, obviously
	 * @param array|null $formdata If null request is sent as GET, otherwise POST and with the postdata of this argument
	 * @param array $settings See parameter $settings of loadSingle()
	 * @param array $history See parameter $history of loadSingle()
	 * @return array ['content' string,'headers' array,'error' string|null,'errorCode']*/
	private static function loadSingleReal($curlHandler,$historyFileObject,$url,$formdata,$settings,$history=null) {
		$numRetries=-1;
		Hicurl::setCurlOptions($curlHandler,$url, $formdata,$settings);
		$output=[];//this is the array that will be returned
		if ($historyFileObject||!empty($settings['history'])) {//should we write history?
			//see description of writeHistory() for explanation of the history-structure
			$historyPage=[
				'formData'=>$formdata,
				'exchanges'=>[]
			];
			if ($history)//add customData and name of the $history-parameter to $historyPage
				$historyPage+=array_intersect_key($history,array_flip(['customData','name']));
			$contents=[];
		}
		do {
			if (++$numRetries) {
				if ($numRetries==$settings['maxFruitlessRetries']) {
					$content=$headers=null;
					$output['error']=$error;
					break;
				}
				sleep($settings['fruitlessPassDelay']);
			}
			$content=curl_exec($curlHandler);//do the actual request. assign response-content to $content
			$headers=curl_getinfo($curlHandler);//get the headers too
			if ($headers['http_code']==0&&!empty($settings['tor'])) {
				$output['errorCode']=1;
				$output['error']="Unable to connect via tor-proxy.\nIs Tor installed and configured correctly?";
				$content=$headers=null;
				break;
			}
			$error=Hicurl::parseAndValidateResult($content,$headers,$settings,$output);
			if (isset($historyPage)) {//are we writing history-data? this var is only set if we are
				$historyPage['exchanges'][]=[
					'headers'=>$headers,
					'error'=>$error,
				];
				$contents[]=$content;
			}
		} while ($error);//keep looping until $error is false
		if (isset($historyPage)) {//should we write history?
			Hicurl::writeHistory($historyFileObject, $contents,$historyPage, $settings, $history);
		}
		return $output+=[
			'content'=>$content,
			'headers'=>$headers,
			'error'=>false,//(this wont overwrite an error-description if it has been written already)
			'errorCode'=>0
		];
	}
	
	/**
	 * Takes reference of content-string and utf8-encodes it if necessary and also does the validation which determines
	 * if the request was deemed successful or not.
	 * @param &string $content Content-string to be parsed and validated. Note that the input-string will be modified.
	 * @param array $headers Headers-array belongin to the content.
	 * @param array $settings The current state of settings.
	 * @param &DOMXPath $domXpath The DOMXPath-object will be set to this out argument if settings->xpath is set.
	 * @return bool|string Returns false if no error was encountered, otherwise a string describing the error.*/
	private static function parseAndValidateResult(&$content,$headers, $settings,&$outputArray) {
		if (ord($content[0])==0x1f && ord($content[1])==0x8b) {
			$content=gzdecode($content);
		}
		//utf8 is needed to json-decode correctly
		//can't blindly utf8-encode or data will be corrupted if it already was utf8 encoded.
		if (strpos($headers['content_type'],'utf-8')===false) {
			$content=utf8_encode($content);
		}
		if ($headers['http_code']==404) {
			return 'HTTP code 404';
		}
		if ($settings['retryOnNull']&&$content===null) {
			return 'Null content';
		}
		if ($settings['retryOnIncompleteHTML']&&!preg_match("/<\/html>\s*$/",$content)) {	
			return 'Cut off HTML';
		}
		if (!empty($settings['xpath'])) {
			return Hicurl::xPathEvaluate($settings['xpath'],$content,$outputArray);
		}
		return false;
	}
	
	/**
	 * Method that does xpath-evaluations if set in settings->xpath. Also placed a DOMXPATH-object in the array
	 * returned by the load-call.
	 * @param array $xpath The value of $settings['xpath']
	 * @param string $pageContent The content of the page
	 * @param array $outputArray A reference to the array which is to be returned by the load-call. This is so that
	 *		the DOMXPATH-object can be placed in it for the convinience of the user, and so that it wont have to be
	 *		generated twice if any xpath work is to be done on the content returned by the load-call.
	 * @return false|string $outputArray Returns error string on failure, e.g. if not all xpaths evaluate to true.
	 * Otherwise it returns false for no error.*/
	private static function xPathEvaluate($xpath,$pageContent,&$outputArray) {
		$domDocument=new DOMDocument();
		//Slap on this meta-tag which sets encoding to utf-8. Otherwise utf8 content gets corrupted. This doesn't seem
		//to ever do any damage to pages that are not utf8 encoded or already have this tag...
		$domDocument->loadHTML('<meta http-equiv="content-type" content="text/html; charset=utf-8">'.$pageContent);
		$domXpath=$outputArray['domXPath']=new DOMXPath($domDocument);
		//if it's set to true then we do not do any validation, but instead just assign domXpath to return-object.
		if ($xpath!==true) {
			if (!is_array($xpath))
				$xpath=[$xpath];//put in array for convenience of using the below loop
			foreach ($xpath as $expression) {
				$xpathResult=$domXpath->evaluate($expression);
				if ($xpathResult===false||($xpathResult instanceof \DOMNodeList &&$xpathResult->length==0))
					return "Xpath failure\nThe following xpath failed:'$expression'";
			}
		}
		return false;
	}
	
	/**
	 * Compiles and compresses the specified history-folder. This is to be done when writing to the history-file is
	 * complete, as it puts the history in a closed state.
	 * @param string $historyFolderPath Path-string of the history-folder to be compiled.
	 * @param mixed $customData Anything that is json-friendly can be passed here. It will be assigned to the root of
	 *		the history-json-object, which resides i the history-archive by the name "data.json".
	 * @return bool Returns TRUE on success or FALSE on failure.*/
	public function compileHistory($customData=null) {
		$this->historyFileObject=null;//so that it can be deleted
		return Hicurl::compileHistoryStatic($this->settingsData['history'],$customData);
	}
	
	/**
	 * Compiles and compresses the specified history-folder. This is to be done when writing to the history-file is
	 * complete, as it puts the history in a closed state.
	 * @param string $historyFolderPath Path-string of the history-folder to be compiled.
	 * @param mixed $customData Anything that is json-friendly can be passed here. It will be assigned to the root of
	 *		the history-json-object, which resides i the history-archive by the name "data.json".
	 * @return bool Returns TRUE on success or FALSE on failure.*/
	public static function compileHistoryStatic($historyFolderPath,$customData=null) {
		$outputFile=realpath($historyFolderPath).DIRECTORY_SEPARATOR.'data.7z';
		if(!function_exists('exec')) {
			trigger_error("Access to system shell is currently mandatory.", E_USER_ERROR);
		}
		foreach (["data.json","pages"] as $compileFile) {
			$compileFiles[]=realpath($historyFolderPath.DIRECTORY_SEPARATOR.$compileFile);
		}
		$command='cd "'.__DIR__.'"'//cd to same folder as this very file
				." && 7za a \"$outputFile\" "
				.'"'.implode('" "',$compileFiles).'"';
		exec($command,$output,$return_var);
		if (!$return_var) {

			foreach ($compileFiles as $toBeDeleted) {
				if(is_dir($toBeDeleted)) {
					$commands[]="rmdir /s/q \"$toBeDeleted\"";
				} else {
					$commands[]="del /f/s/q \"$toBeDeleted\"";
				}
			}
			$command=implode(' && ',$commands);
			exec($command,$output,$return_var);
			return !$return_var;
		}
	}
	
	/**
	 * Compress input-file with gzip encoding
	 * @param string $historyFilePath The path of the input-file
	 * @param string|null $outputFile Path for the output. If omitted then the same as input is used.*/
	private static function compressHistoryFile($inputFile,$outputFile) {
		if (!$outputFile)
			$outputFile=$inputFile;
		//We want to use system gzip via exec(), and only if that fails fall back on php gzencode()
		//is exec() available?
		//this should also check whether exec is in ini_get('disable_functions') and whether safemode is on
		if(function_exists('exec')) {
			
			//if input-file has extension gz already, even if it isn't compressed then gzip refuses to compress it
			if (pathinfo($inputFile, PATHINFO_EXTENSION)=='gz') {
				rename($inputFile, $inputFile=substr($inputFile,0,-3));
			}
			
			//if system is windows then there is no native gzip-command, which is why we cd into src-folder where
			//gzip.exe should be located, which will be used in that case. separate commands with ;
			$command='cd "'.__DIR__.'" && '//cd to same folder as this very file
					.'gzip --force --quiet ';//--force is for forcing overwrite if output-file already exist
			//if (!$writeToFile) $command.=' --stdout';
			$command.='"'.realpath($inputFile).'"';
			$response=exec($command, $output, $return_var);
			
			if ($return_var!==1) {//success!
				if ($outputFile!=$inputFile.'.gz') {
					//the reason why rename is used rather than passing an output-file to the gzip-call with > is that
					//it doesn't work if input and output are the same, but it works with rename.
					rename ($inputFile.'.gz', $outputFile);
				}
				return true;
			}
		}
		//Hopefully the block above ended this function with a return statement, but otherwise fall back on below code
		
		//Set memory_limit to a high number or there might be a problem holding all page-contents in memory which is
		//needed in order to gzip efficiently.
		$memoryLimit=ini_get('memory_limit');//save old to be able to revert
		ini_set('memory_limit', -1);
		file_put_contents($outputFile,gzencode(file_get_contents($inputFile)));
		ini_set('memory_limit', $memoryLimit);//revert back to old
		return true;
	}
	
	/**
	 * Generates the options that are used for the curl object and sets them to the curl-handler.
	 * @param resource $curlHandler The curl-handler that the options should be set to
	 * @param string $url The url for the request
	 * @param array|null $formdata If post-request then this should be an associative array of the formdata.
	 * @param array $settings Current settings
	 * @return void*/
	private static function setCurlOptions($curlHandler,$url,$formdata,$settings) {
		$curlOptions=[
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLINFO_HEADER_OUT => true,
			//CURLOPT_VERBOSE=>true,
			//CURLOPT_PROXY=>'127.0.0.1:8888'
		];
		if (!empty($settings['cookie'])) {
			$curlOptions[CURLOPT_COOKIEFILE]=$curlOptions[CURLOPT_COOKIEJAR]=$settings['cookie'];
		}
		if (!empty($settings['tor'])) {
			$curlOptions[CURLOPT_PROXY]='127.0.0.1:9050';
			$curlOptions[CURLOPT_PROXYTYPE]=CURLPROXY_SOCKS5;
		}
		if (isset($formdata)) {
			$curlOptions[CURLOPT_POST]=true;
			foreach ($formdata as $key => $value) {
				$params[] = $key . '=' . urlencode($value);
			}
			$curlOptions[CURLOPT_POSTFIELDS]=implode('&', $params);
			if (!empty($settings['postHeaders'])) {
				$curlOptions[CURLOPT_HTTPHEADER]=$settings['postHeaders'];//10023
			}
		} else if (!empty($settings['getHeaders'])) {
			$curlOptions[CURLOPT_HTTPHEADER]=$settings['getHeaders'];//10023
		}
		//By resetting the settings like this it suffices to write settings to $curlOptions depending on what is
		//*set* in $settings. We don't have to negate effects of settings that are *not set* in $setting
		curl_reset($curlHandler);
		
		curl_setopt_array($curlHandler,$curlOptions);
	}
	
	/**
	 * Writes history to the output-buffer.
	 * @param string $historyFolderPath Path-string of the history-folder to be served.*/
	public static function serveHistory($historyFolderPath) {
		
		
		if (isset($_GET['getJsonList'])) {
			$fileToServe="data.json";
		} else {
			$fileToServe='pages'.DIRECTORY_SEPARATOR.$_GET['getPageContent'];
		}
		
		
		$historyFolderPath=realpath($historyFolderPath);
		if (file_exists($historyFolderPath.'/data.7z')) {//is history-folder compiled?
			$cache=true;
			$command='cd "'.__DIR__.'"'//cd to same folder as this very file
			.' && 7za e "'.$historyFolderPath.DIRECTORY_SEPARATOR.'data.7z" "'.$fileToServe.'" -so 2>7za_e_log.txt';
			//echo $response=exec($command, $output, $return_var);
			$lastLine=system($command);
			//echo $response=exec($command, $output, $return_var);
		} else {//serve uncompiled history
			$cache=isset($_GET['getPageContent']);
		}
		
		if ($cache) {
			if ($cache===true)
				$cache=31536000;
			header("Cache-Control: max-age=$cache, public");
		} else {
			header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
			header("Pragma: no-cache"); //HTTP 1.0
			header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		}
	}
}