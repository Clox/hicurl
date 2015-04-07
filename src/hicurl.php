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
		new Hi
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
	 *		<li>'retryOnNull' boolean If true then load-calls will retry requests when response-content is null.</li>
	 *		<li>'retryOnIncompleteHTML' boolean if true then load-calls will retry requests when response	doesn't end
	 *			with a HTML closing tag (That is "&lt/html&gt") with possible following whitespace.</li>
	 *		<li>'xpathValidate' array|array[]|boolean Setting for doing xpath-validation.
	 *			It expects an xpath-object in the following format:<br>
	 *			['expression'=>string,'error'=>string,'compare'=>string]
	 *			<ul>
	 *				<li>'expression' string simply be an xpath-string</li>
	 *				<li>'error' string optional description of the error if it fails</li>
	 *				<li>'compare' string An optional string specifying comparison-condition(s) the fetched value needs to meet.
	 *					Normally with xpath one could do something like '//*[@id="myNumer"]/span>5' but php's xpath doesn't
	 *					support such operations, and can only return nodes, not booleans. If any of them fail then the request
	 *					is considered failed and will be retried if it's allowed.
	 *					Some example values:
	 *					<ul>
	 *						<li>'x>5' - The value needs to be higher than 5</li>
	 *						<li>'x<=8.5' - The value needs to be lower or equal to 8.5</li>
	 *						<li>'x==99' - The value needs to be 99</li>
	 *						<li>'x>5&&x<10' - The value needs to be between 5 and 10</li>
	 *					</ul>
	 *				</li>
	 *			</ul>
	 *			This will also cause a DOMXPath-object of the name "domXpath" to be returned by the load-call,
	 *			so that the same page doesn't have to be parsed twice if some xpath-work has to be done after
	 *			retrieval&validation of the page. The value of true may also be passed which will make the xpath-object
	 *			appear in the returned array, but no xpaths will be evaluated.
	 *			If no compare-value is passed the xpath-validation will succeed as long as it matches as least 1 node.
	 *			Also, rather than passing one of these xpath-objects, an array of them may be passed.
	 *		</li>
	 *		<li>'postHeaders' array An array of headers that will be sent on POST-requests.</li>
	 *		<li>'getHeaders' array An array of headers that will be sent on GET-requests</li>
	 *		<li>'history' string
	 *			Enables saving contents/headers of request&responses in a file for later viewing. The value should be a
	 *			path to a history-file. If it doesn't yet exist it will be created, else it will be appended to.
	 *			For on the structure of history-files {@see writeHistory()}</li>
	 *	</ul>
	 * @return array The resulted settings*/
	public function settings($settings=null) {
		if (!$settings)
			return $this->settingsData;
		if (array_key_exists('history',$settings)) {//if a setting for 'history' is present
			if (!$settings['history']) {//if its null/false
				$this->historyFileObject=null;//then close old connection
			} else if (!isset($this->historyFileObject)||$settings['history']!=$this->settingsData['history']) {
				$this->historyFileObject=new SplFileObject($settings['history'],'c+');
				$this->historyFileObject->historyEmpty=!$this->historyFileObject->getSize();
			}
		}
		//merge supplied settings with current settings of instance
		return $this->settingsData=$settings+$this->settingsData;
	}
	
	/**
	 * Function that writes history to the uncompiled history-file.
	 * History-files are "uncompiled" before Hicurl::compileHistory has been called on them, which generates a
	 * history-file of cosed state.
	 * In its uncompiled state its structure is as follows:
	 *		(historyData without closing bracket and brace)+(tempHistoryData)+(sizeOfTempHistoryData)
	 * historyData is the following json-object: {"pages":[]}
	 * But in the uncompiled file it is without the closing bracket+brace as stated above. Each element in its
	 * pages-array is the following:<pre>
	 * {	"formData"://this is present if it was a POST-request, and will contain the sent form-data
	 *		,"name"://a name-string for the page that will be shown in the history-viewer. Set via historyData>name
	 *		,"parentIndex": int//may hold an pointing to an index of another page in the pages-array. This will then be
	 *			shown in the tree-view of the history-viewer. This is set up with loadSingle>historyData>id/parentId
	 *		,"customData": //contains what was passed to customData-parameter of the load method if anything
	 *		,"exchanges": [//An array of request&response pairs. Usually this will only contain one
	 *								//element but more will be added for each failed request
	 *			{
	 *				"error": false if no error, otherwise an explanation of the error
	 *				"content": the content of the page sent from the server
	 *				"headers": ...and the headers
	 *			}
	 *		...
	 *		]
	 * }
	 * </pre>
	 * @param SplFileObject $historyFileObject
	 * @param string $data A undecoded page-object, explained in the description of this method.
	 * @param array $settings
	 * @param array $historyData*/
	private static function writeHistory($historyFileObject,$data,$settings,$historyData) {
		if (!isset($historyFileObject)) {
			$historyFileObject=new SplFileObject($settings['history'],'c+');
		}
		$historyFileObject->flock(LOCK_EX);
		//check if the historyFile is empty. we don't have to check for file-existence because it will always
		//have been created by now byt simply creating the SplFileObject
		if (!$historyFileObject->fstat()['size']) {
			$dataPrefix='{"pages":[';
			$historyFileTempData=['numPages'=>0,'idIndices'=>[]];
		} else {
			$dataPrefix=',';
			$tempDataSize=Hicurl::seekHistoryFileTempData($historyFileObject);
			$historyFileTempData=json_decode($historyFileObject->fread($tempDataSize),true);
			$historyFileObject->fseek(-4-$tempDataSize, SEEK_END);
		}
		if (isset($historyData['id'])) {
			$historyFileTempData['idIndices'][$historyData['id']]=$historyFileTempData['numPages'];
		}
		if (isset($historyData['parentId'])) {
			$data['parentIndex']=$historyFileTempData['idIndices'][$historyData['parentId']];
		}
		++$historyFileTempData['numPages'];
		$historyFileObject->fwrite($dataPrefix.json_encode($data));
		$tempDataSize=$historyFileObject->fwrite(json_encode($historyFileTempData));
		$historyFileObject->fwrite(pack('N',$tempDataSize));
		$historyFileObject->flock(LOCK_UN);
	}
	
	/**
	 * Takes the file objec for a history file and seeks to the beginning of the tempData-section.
	 * @param SplFileObject $historyFileObject Uncompiled history-file
	 * @return int The bytesize of the tempData-section*/
	private static function seekHistoryFileTempData($historyFileObject) {
		$historyFileObject->fseek(-4, SEEK_END);
		$tempDataSize=unpack('N',$historyFileObject->fread(4))[1];
		$historyFileObject->fseek(-4-$tempDataSize, SEEK_END);
		return $tempDataSize;
	}
	
	/**
	 * This is the heart of Hicurl. It loads a requested url, using specified settings and returns the
	 * server-response along with some data and optionally writes all data to a history-file.
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
	 *		<li>['error'] string Description of the error in case the request failed indefinately.</li>
	 * </ul>
	 * @see loadSingleStatic()*/
	public function loadSingle($url,$formdata=null,$settings=[],$history=[]) {
		//pass the historyFileObject of this instance if it has one and it doesn't temporarily get overwritten by
		//the history-item i the $settings-argument
		$historyFileObject=null;//if the next condition fails then null will be used which will result in
		//loadSingleReal creating a new, tempoary one if histor is set in $settings
		if ($this->historyFileObject//if the instance has a fileObject and there's no history-item in the
		//$settings-argument, or if there is it's setting is the same as the one in the instance-settings
			&&(!array_key_exists('history', $settings) || $settings['history']==$this->settingsData['history'])) {
			$historyFileObject=$this->historyFileObject;//then the instance fileObject will be used
		}
			
		return Hicurl::loadSingleReal($this->curlHandler,$historyFileObject, $url, $formdata,
				($settings?$settings:[])+$this->settingsData,$history);
	}
	
	/**
	 * This is the heart of Hicurl. It loads a requested url, using specified settings and returns the
	 * server-response along with some data and optionally writes all data to a history-file.
	 * This method has a instance counterpart of the name loadSingle which works just the same.
	 * @see loadSingle()*/
	public static function loadSingleStatic($url,$formdata=null,$settings=[],$history=[]) {
		return Hicurl::loadSingleReal(curl_init(),null,$url,$formdata,
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
	 * @return array ['content' string,'headers' array,'error' string|null]*/
	private static function loadSingleReal($curlHandler,$historyFileObject,$url,$formdata,$settings,$history=null) {
		$numRetries=-1;
		curl_setopt_array($curlHandler, Hicurl::generateCurlOptions($url, $formdata));
		$output=[];
		if ($historyFileObject||!empty($settings['history'])) {//should we write history?
			//see description of writeHistory() for explanation of the history-structure
			$historyPage=[
				'formData'=>$formdata,
				'exchanges'=>[]
			];
			if ($history)//add customData and name of the $history-parameter to $historyPage
				$historyPage+=array_intersect_key($history,array_flip(['customData','name']));
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
			$error=Hicurl::parseAndValidateResult($content,$headers,$settings,$output['domXpath']);
			if (isset($historyPage)) {//are we writing history-data? this var is only set if we are
				$historyPage['exchanges'][]=[
					'content'=>$content,
					'headers'=>$headers,
					'error'=>$error
				];
			}
		} while ($error);//keep looping until $error is false
		if (isset($historyPage)) {//should we write history?
			Hicurl::writeHistory($historyFileObject, $historyPage, $settings, $history);
		}
		return $output+=[
			'content'=>$content,
			'headers'=>$headers
		];
	}
	
	/**
	 * Takes reference of content-string and utf8-encodes it if necessary and also does the validation which determines
	 * if the request was deemed successful or not.
	 * @param &string $content Content-string to be parsed and validated. Note that the input-string will be modified.
	 * @param array $headers Headers-array belongin to the content.
	 * @param array $settings The current state of settings.
	 * @param &DOMXPath $domXpath The DOMXPath-object will be set to this output-var if settings->xpathValidate is set.
	 * @return boolean|string Returns false if no error was encountered, otherwise a string describing the error.*/
	private static function parseAndValidateResult(&$content,$headers, $settings,&$domXpath) {
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
		if (isset($settings['xpathValidate'])) {//if we are to do xpath-validation
			$xpaths=$settings['xpathValidate'];//plural because there might be multiple
			$domDocument=new DOMDocument();
			$domDocument->loadHTML($content);
			$domXpath=new DOMXPath($domDocument);
			//if it's set to true then we do not do any validation, but instead just assign domXpath to return-object.
			//(domXpath is a reference)
			if ($xpaths!==true) {
				if (isset($xpaths['expression']))//if true it means a single xpath was passed
					$xpaths=[$xpaths];//then put in array for convenience of using the below loop
				foreach ($xpaths as $xpath) {
					$expression=$xpath['expression'];
					if (isset($xpath['error']))
						$error=$xpath['error'];
					else
						$error="The following xpath failed:".$expression;
					$xpathResult=$domXpath->query($expression);
					if (!$xpathResult->length||		(isset($xpath['compare'])&&
						!Hicurl::xcompare($xpath['compare'], $xpathResult->item(0)->nodeValue))) {
						return $error;//fail if xpathResult didn't get anything, or it did but compare-expression failed
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * Used for validating a value against condition(s), used by xpathValidator if settings->xpathValidate is set.
	 * @param string $expressions The condition-expression. A conditin example: "x<5".
	 *		The value needs to be less than 5. Other comparison-operators are >,<=,>= and ==. Multiple conditions can
	 *		be used by separating them with &&.
	 * @param DOMNode $x The node that the comparison-expression is evaluated against.
	 * @return boolean Returns false if any of the conditions are false, otherwise true.*/
	private static function xcompare($expressions,$x) {
		$expressions=explode("&&", $expressions);
		foreach ($expressions as $expression) {
			preg_match('/x(==|<=|>=|<|>)([\d+.]+)/',$expression,$matches);
			switch ($matches[1]) {
				case '>':
					if ($x<=$matches[2])
						return false;
				break;
				case '<':
					if ($x>=$matches[2])
						return false;
				break;
				case '<=':
					if ($x>$matches[2])
						return false;
				break;
				case '>=':
					if ($x<$matches[2])
						return false;
				break;
				case '==':
					if ($x!=$matches[2])
						return false;
			}
		}
		return true;
	}
	
	/**
	 * Compiles the history-file. This is to be done when the writing to the history-file is complete.
	 * This essentialy puts the file in a closed state, gzipping it while also optionally adding extra data.
	 * @param string|null $historyOutput A filepath-string to the file to be created. This may be omitted in which case
	 *		the output-file will be the same as the input.
	 * @param mixed $customData Anything that is json-friendly can be passed here. It will be assigned to the root of
	 *		the final, compiled json-object with the same name("customData")
	 * @return boolean Returns true for success*/
	public function compileHistory($historyOutput=null,$customData=null) {
		//$this->historyFileObject needs to be unset so that gzip can remove the input file as its supposed to.
		//and we can't save it to a local variable and then remove it from instance and call compile with local, since
		//this local variable in this function will still be holdig it then.
		$historyFilePath=$this->historyFileObject->getPathname();
		$this->historyFileObject=null;
		Hicurl::compileHistoryStatic($historyFilePath, $historyOutput,$customData);
	}
	
	/**
	 * Compiles the history-file. This is to be done when the writing to the history-file is complete.
	 * This essentialy puts the file in a closed state, gzipping it while also optionally adding extra data.
	 * @param string|SplFileObject $historyInput Either a string which is a path to a file that is to be compiled,
	 *		or a SplFileObject pointing to that file. This file will be deleted by this function, leaving you only
	 *		with its compiled state.
	 * @param string|null $historyOutput A filepath-string to the file to be created. This may be omitted in which case
	 *		the output-file will be generated in the same directory as the input-file, with the same name but with
	 *		".gz" added at the end.
	 * @param mixed $customData Anything that is json-friendly can be passed here. It will be assigned to the root of
	 *		the final, compiled json-object with the same name("customData")
	 * @return boolean Returns true for success*/
	public static function compileHistoryStatic($historyInput,$historyOutput=null,$customData=null) {
		//At this point the history should be formated as:
		//{"pages":[page1{},page2{}+tempData+tempDataSize
		//i.e.
		//{"pages":[{"exchanges":[{"content":"foobar","headers":{"http_code":200}}]}{"numPages":1,"idIndices":[]}29
		//(the outer bracket&brace aren't closed)
		$historyInput=new SplFileObject($historyInput,'c+');
		Hicurl::seekHistoryFileTempData($historyInput);
		$ending=']';
		if ($customData) {
			$ending.=',"customData":'.$customData;
		}
		$ending.='}';
		$historyInput->fwrite($ending);
		$historyInput->ftruncate($historyInput->ftell());
		
		$historyInputPath=$historyInput->getRealPath();
		$historyInput=null;//remove the reference to the file so that gzip can delete it as supposed to
		
		Hicurl::compressHistoryFile($historyInputPath,$historyOutput);
	}
	
	/**
	 * Compress input-file with gzip encoding
	 * @param string $historyFilePath The path of the input-file
	 * @param string|null $outputFile Path for the output. If omitted then the same as input is used.*/
	private static function compressHistoryFile($inputFile,$outputFile=null) {
		if (!$outputFile)
			$outputFile=$inputFile;
		//We want to use system gzip via exec(), and only if that fails fall back on php gzencode()
		//is exec() available?
		//this should also check whether exec is in ini_get('disable_functions') and whether safemode is on
		if(function_exists('exec')) {
			//if system is windows then there is no native gzip-command, which is why we cd into src-folder where
			//gzip.exe should be located, which will be used in that case. separate commands with ;
			$command='cd "'.__DIR__.'" && '//cd to same folder as this very file
					.'gzip -f -q ';//--force is for forcing overwrite if output-file already exist
			//if (!$writeToFile) $command.=' --stdout';
			$command.='"'.realpath($inputFile).'"';
			$response=exec($command, $output, $return_var);
			
			if ($return_var!==1) {//success!
				if ($outputFile!=$inputFile.'.gz') {
					//the reason why rename is used rather than passing an output-file to the gzip-call with > is that that
					//doesn't work if input and output are the same, but it works with rename.
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
	 * Generates the options that are used for the curl object.
	 * @param string $url The url for the request
	 * @param array|null $formdata If post-request then this should be an associative array of the formdata.
	 * @param array $settings Current settings-
	 * @return array Curl-options*/
	private static function generateCurlOptions($url,$formdata,$settings) {
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
			$curlOptions[CURLOPT_COOKIEFILE]=$curlOptions[CURLOPT_COOKIEJAR]=$settings->cookie;
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
		return $curlOptions;
	}
	
	/**
	 * Writes history to the output-buffer.
	 * @param string $historyPath Path of the compiled histor-file.*/
	public static function serveHistory($historyPath) {
		ini_set('zlib.output_compression','Off');
		$HTTP_ACCEPT_ENCODING = $_SERVER["HTTP_ACCEPT_ENCODING"]; 
		header('Cache-Control: max-age=29030400, public');
		if(headers_sent()) 
			$encoding = false; 
		else if(strpos($HTTP_ACCEPT_ENCODING, 'x-gzip') !== false)
			$encoding = 'x-gzip'; 
		else if(strpos($HTTP_ACCEPT_ENCODING,'gzip') !== false)
			$encoding = 'gzip'; 
		else
			$encoding = false;
		if ($encoding) {
			header('Content-Encoding: '.$encoding);
			header('Content-Type: text/plain');
			readfile($historyPath);
		} else
			echo gzdecode (file_get_contents($historyPath));
	}
}