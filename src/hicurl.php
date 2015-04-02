<?php
/**
 * A class that simplifies URL requests. Rather than working with CURL and its settings directly this
 * class maks it easy by using simple syntax for the most common settings.
 * But more importantly this class also allows for easily saving the history of the requests&responses
 * with their contens/headers.*/
class Hicurl {
	/**@var array This is an array of settings which the user can define. They are set either through the
	 * class-constructor or via settings(). {@see settings()} for more info.*/
	private $settingsData;
	
	/**@var resource Curl Handler*/
	private $curlHandler;
	
	/**@var SplFileObject file-object for the history file.*/
	private $historyFileObject;
	
	/**@var array Default settings used when creating an instance of calling load-methods statically.
	 * The default settings are merged with the settings passed to load-method or contructor. The defaults are:<br>
	 * 'maxFruitlessRetries'=>40,<br>'fruitlessPassDelay'=>10,<br>'maxRequestsPerPass'=>100,<br>'flags'=>1|2*/
	static public $defaultSettings=[
		'maxFruitlessRetries'=>40,
		'fruitlessPassDelay'=>10,
		'maxRequestsPerPass'=>100,
		'retryOnNull'=>true,
		'retryOnIncompleteHTML'=>true
	];
	
	/**@param type $settings The same data as can be passed to settings().
	 * @see settings()*/
    function __construct($settings=[]) {
		$this->settingsData=Hicurl::$defaultSettings;
		if ($settings)
			$this->settings($settings);
		$this->curlHandler=curl_init();
    }
	/** @var mixed[] This method is used for changing settings after having constructed an instance. The settings
	 * passed to this method will be merged with the current settings. To remove a setting, set it to null. Besides
	 * calling this method the settings can also temporarily be changed during one load-call by passing the settings
	 * there as an argument, same merge-rules apply.
	 * @param array|null $settings If this argument is ommited then no settings are changed, and the method only
	 * returns the current settings.
	 * Otherwise an array with a combination of the following settings should be passed:<ul>
	 * <li>'maxFruitlessRetries' int Defaults to 40. Max amount of retries before a load-function gives up. For
	 *		loadSingle this is simply the number of retries for that url. For loadMulti it is the max number of
	 *		consecutive retries for the same group of requests.</li>
	 * <li>'fruitlessPassDelay' int Defaults to 10. Number of seconds that the script should sleep for when for
	 *		singleLoad a request fails, or for multiLoad a whole group fails.</li>
	 * <li>'maxRequestsPerPass' int	Defaults to 100. Used for loadMulti(). If the number of urls passed to loadMulti()
	 *		is less than this value then it will be split into groups at the size of this.</li>
	 * <li>'cookie'	string If this is set to a path to a file then cookies will be saved to that file, and those cookies
	 *		will also be sent on each request. Ex: dirname(__FILE__).'/cookie.txt</li>
	 * <li>'retryOnNull' boolean If true then load-calls will retry requests when response-content is null.</li>
	 * <li>'retryOnIncompleteHTML' boolean if true then load-calls will retry requests when response	doesn't end
	 *		with a HTML closing tag (That is "&lt/html&gt") with possible following whitespace.</li>
	 * <li>'xpathValidate' string|string[]|array|boolean An xpath-string or an array of them may be passed here. These
	 *		will be tried on the downloaded page. All of them have to evaluate to true or else the page-request will
	 *		be retried. This will also cause a DOMXPath-object of the name "domXpath" to be returned by the load-call,
	 *		so that the same page doesn't have to be parsed twice if some xpath-work has to be done after
	 *		retrieval&validation of the page. The value of true may also be passed which will make the xpath-object
	 *		appear in the returned array, but no paths will be evaluated.
	 *		Also, rather than passing strings for xpaths, arrays of the following structure may be passed instead:
	 *		['expression'=>xpathString,'error'=>errorDescription]
	 *		This has the advantage of describing the error with a custom string.
	 * <li>'postHeaders' array An array of headers that will be sent on POST-requests.</li>
	 * <li>'getHeaders' array An array of headers that will be sent on GET-requests</li>
	 * <li>'history' string
	 *		Enables saving contents/headers of request&responses in a file for later viewing. The value should be a
	 *		path to a history-file. If it doesn't yet exist it will be created, else it will be appended to.
	 *		For on the structure of history-files {@see writeHistory()}
	 *		</li></ul>
	 * @return array The resulted settings
	 */
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
	
	/**Function that writes history to the uncompiled history-file.
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
		if (!file_exists($settings['history'])||!filesize($settings['history'])) {
			$dataPrefix='{"pages":[';
			$historyFileTempData=['numPages'=>0,'idIndices'=>[]];
		} else {
			$dataPrefix=',';
			$tempDataSize=Hicurl::seekHistoryFileTempData($historyFileObject);
			$historyFileTempData=$historyFileObject->fread($tempDataSize);
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
		$headerSize=$historyFileObject->fwrite($historyFileTempData);
		$historyFileObject->fwrite(pack('N',$headerSize));
		$historyFileObject->flock(LOCK_UN);
	}
	private static function seekHistoryFileTempData($historyFileObject) {
		$historyFileObject->fseek(-4, SEEK_END);
		$tempDataSize=unpack('N',$historyFileObject->fread(4))[1];
		$historyFileObject->fseek(-4-$tempDataSize, SEEK_END);
		return $tempDataSize;
	}
	/**This is the heart of Hicurl. It loads a requested url, using specified settings and returns the
	 * server-response along with some data, and optionally writes all data to a history-file.
	 * This method has a static method counterpart called loadSingleStatic which works just the same.
	 * @param string $url A URL-string to load
	 * @param string[] $formdata If this is null then the request is sent as GET. Otherwise it is sent as POST using
	 *		this array as formdata where key=name and value=value. It may be an empty array in which case the request
	 *		will stil be sent as POST butwith no formdata.
	 * @param array $settings Optional parameter of settings that will be merged with the settings of the instance for
	 *		the duration of this call only. See Hicurl->settings() for explenation on the settings. The settings that
	 *		may be passed to that function are identical to those that may be passed to this.
	 * @param array $historyData Associative array for various settings used for the history-writing in case
	 *		settings['history'] is set. The following are valid settings for it:<pre>[
	 *			'name'=> string//a name for the historypage that will be visible in the history-viewer
	 *			,'id'=> mixed//a id may be set here which then can be used to refer to this page as a parent of another
	 *			,'parentId'=> mixed//the id of another page may be set here to refer to it as the parent of this page
	 *		]</pre>
	 * @return array An array in the form of: [
	 *		['content'] string The content of the requested url. In case the request had to be retried, this will only
	 *			contain the final content. Will be set to null if request failed indefinately.
	 *		['headers'] array The headers of the final content. Will be set to null if request failed indefinately.
	 *		['historyFileName'] string This will be present if settings['saveDataDir'] is set and will be the filename
	 *			with path of the generated file.
	 *		['error'] string In case the request failed indefinately this will be set to a string explaining the error.
	 * ]
	 * @see loadSingleStatic*/
	public function loadSingle($url,$formdata=null,$settings=null,$historyName=[],$historyCustomData=[]) {
		$historyFileObject;
		if ($this->historyFileObject
			&&(!array_key_exists('history', $settings) || $settings['history']==$this->settingsData['history'])) {
			$historyFileObject=$this->historyFileObject;
		}
			
		return Hicurl::loadSingleReal($this->curlHandler,$historyFileObject, $url, $formdata,
				($settings?$settings:[])+$this->settingsData,$historyName, $historyCustomData);
	}
	/**This is the heart of Hicurl. It loads a requested url, using specified settings and returns the
	 * server-response along with some data, and optionally writes all data to a history-file.
	 * This method has a instance method counterpart called loadSingle which works just the same.
	 * @param string $url A URL-string to load
	 * @param string[] $formdata If this is null then the request is sent as GET. Otherwise it is sent as POST using
	 *		this array as formdata where key=name and value=value. It may be an empty array in which case the request
	 *		will stil be sent as POST butwith no formdata.
	 * @param array $settings Optional parameter of settings that will be merged with the default settings
	 *		HiCurl::defaultSettings and then used for this reqest. {@see settings()}
	 * @param array $historyData Associative array for various settings used for the history-writing.
	 *		This is only used if settings['history'] is set. The following are valid settings for it:<pre>[
	 *			'name'=> string//a name for the historypage that will be visible in the history-viewer
	 *			,'id'=> mixed//a id may be set here which then can be used to refer to this page as a parent of another
	 *			,'parentId'=> mixed//the id of another page may be set here to refer to it as the parent of this page
	 *		]</pre>
	 * @return array An array in the form of: [
	 *		['content'] string The content of the requested url. In case the request had to be retried, this will only
	 *			contain the final content. Will be set to null if request failed indefinately.
	 *		['headers'] array The headers of the final content. Will be set to null if request failed indefinately.
	 *		['historyFileName'] string This will be present if settings['saveDataDir'] is set and will be the filename
	 *			with path of the generated file.
	 *		['error'] string In case the request failed indefinately this will be set to a string explaining the error.
	 * * @see loadSingle* ]*/
	public static function loadSingleStatic($url,$formdata=null,$settings=[],$historyData=[]) {
		return Hicurl::loadSingleReal(curl_init(),null,$url,$formdata,$settings+Hicurl::$defaultSettings,$historyData);
	}
	private static function loadSingleReal($curlHandler,$historyFileObject,$url,$formdata,$settings,$historyData) {
		curl_setopt_array($curlHandler, Hicurl::generateCurlOptions($url, $formdata));
		$numRetries=-1;
		$output=[];
		if ($historyFileObject||!empty($settings['history'])) {//should we write history?
			//see description of writeHistory() for explanation of the history-structure
			$historyPage=[
				'formData'=>$formdata,
				'exchanges'=>[]
			]+$historyData;
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
			if (isset($historyPage)) {//are we writing history-data? this var is only set if we do
				$historyPage['exchanges'][]=[
					'content'=>$content,
					'headers'=>$headers,
					'error'=>$error
				];
			}
		} while ($error);//keep looping until $error is false
		if (isset($historyPage)) {//should we write history?
			Hicurl::writeHistory($historyFileObject, $historyPage, $settings, $historyData);
		}
		return $output+=[
			'content'=>$content,
			'headers'=>$headers
		];
	}
	
	private static function parseAndValidateResult(&$content,$headers, $settings,&$domXpath) {
		if (ord($content[0])==0x1f && ord($content[1])==0x8b) {
			$content=gzdecode($content);
		}
		//utf8 is needed to correctly json-decode
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
		if (isset($settings['xpathValidate'])) {
			$xpaths=$settings['xpathValidate'];
			$domDocument = new DOMDocument();
			$domDocument->loadHTML($content);
			$domXpath=new DOMXPath($domDocument);
			if (xpaths!==true) {
				if (gettype($xpaths)=='string'||isset($xpaths['xpath']))//if true it means a single xpath was passed
					$xpaths=[$xpaths];//then put in array for convenience of using the below loop
				foreach ($xpaths as $xpath) {
					if (gettype($xpath)=='string') {
						$expression=$xpath;
						$errorDescription="The following xpath-validation failed: ".$expression;
					} else {
						$expression=$xpath['expression'];
						$errorDescription=$xpath['error'];
					}
					if (!(boolean)$domXpath->query($expression)) {
						return $errorDescription;
					}
				}
			}
		}
		return false;
	}
	
	/**Compiles the history-file. This is to be done when the writing to the history-file is complete.
	 * This essentialy puts the file in a closed state, gzipping it while also optionally adding extra data.
	 * @param string|null $historyOutput A filepath-string to the file to be created. This may be omitted in which case
	 *		the output-file will be generated in the same directory as the input-file, with the same name but with
	 *		".gz" added at the end.
	 * @param mixed $customData Anything that is json-friendly can be passed here. It will be assigned to the root of
	 *		the final, compiled json-object with the same name("customData")
	 * @return boolean Returns true for success*/
	public function compileHistory($historyOutput=null,$customData=null) {
		$historyInput=$this->historyFileObject;
		$this->historyFileObject=null;
		Hicurl::compileHistoryStatic($historyInput, $historyOutput,$customData);
	}
	
	/**Compiles the history-file. This is to be done when the writing to the history-file is complete.
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
		if (gettype($historyInput)=="string")
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
	
	/**Compress input-file with gzip encoding
	 * @param string $historyFilePath*/
	private static function compressHistoryFile($inputFile,$outputFile=null,$writeToFile=true) {
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
			if ($outputFile) {
				//the reason why rename is used rather than passing an output-file to the gzip-call with > is that that
				//doesn't work if input and output are the same, but it works with rename.
				rename ($inputFile.'.gz', $outputFile);
			}
			$response=exec($command, $output, $return_var);
			$a=unlink ($inputFile);
			if ($return_var==0)//success!
				return true;
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
	function loadMulti($urls,$postvars) {
		if (!is_array($urls)) {
			$urls=[$urls];
		}
		$mh=curl_multi_init();
		$handles=[];
		$output=[];
		$failedIndices=[];
		$passNumber=0;
		//if ($flags|8)
		//	fwrite($logfile,date('H:i:s T ')."Starting to download ".count($urls)." $generalName.\r\n");
		$numRetries=0;
		 /** @var int count of how many consecutive retry passes where no pages were successfully downloaded*/
		do {
			++$passNumber;
//			if ($flags|8) {
//				fwrite($logfile,date('H:i:s T ')."Pass $passNumber start with "
//						.min([$this->maxRequestsPerPass,count($urls)+count($handles)])." downloads. "
//						.(count($urls)+count($handles))." requests remaining.\r\n");
//			}
			foreach ($urls as $urlIndex=>$url) {
				if (count($handles)==$this->maxRequestsPerPass)
					break;
				$fruitlessRetries=0;
				$curlOptions=[
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_SSL_VERIFYPEER => false,
					//CURLINFO_HEADER_OUT => true,
					//CURLOPT_VERBOSE=>true,
					//CURLOPT_PROXY=>'127.0.0.1:8888'
				];
				if (isset($this->cookie)) {
					$curlOptions[CURLOPT_COOKIEFILE]=$curlOptions[CURLOPT_COOKIEJAR]=$this->cookie;
				}
				if (isset($postvars)) {
					$curlOptions[CURLOPT_POST]=true;
					foreach ($postvars as $key => $value) {
						$params[] = $key . '=' . urlencode($value);
					}
					$curlOptions[CURLOPT_POSTFIELDS]=implode('&', $params);
					/*
					$curlOptions[CURLOPT_POSTFIELDS]='';
					foreach($postvars as $key=>$value) {
						$curlOptions[CURLOPT_POSTFIELDS] .= $key . "=" . $value . "&";
					}
					 */
					if (isset($this->postHeaders)) {
						$curlOptions[CURLOPT_HTTPHEADER]=$this->postHeaders;//10023
					}
				} else if (isset($this->getHeaders)) {
					$curlOptions[CURLOPT_HTTPHEADER]=$this->getHeaders;//10023
				}
				
				curl_setopt_array($handles[$urlIndex]=curl_init(), $curlOptions);
				curl_multi_add_handle($mh, $handles[$urlIndex]);
				unset ($urls[$urlIndex]);
			}
			do {//snippet from http://php.net/manual/en/function.curl-multi-exec.php#113002
				curl_multi_exec($mh, $running);
				curl_multi_select($mh);
			} while ($running > 0);

			foreach ($handles as $key=>$handle) {
				$content=curl_multi_getcontent($handle);
				if (ord($content[0])==0x1f && ord($content[1])==0x8b) {
					$content=gzdecode($content);
				}
				$header=curl_getinfo($handle);
				if (strpos($header['content_type'],'utf-8')===false) {
					$content=utf8_encode($content);
				}
//			$output['responses'][$key][]=
//				writeLogFile(
//					json_encode(
//						[
//							'content'=>$content,
//							'headerInfo'=>$header
//						]
//					)
//				,"","swap/");
//				if (json_last_error())
//					trigger_error("Error json-encoding downloaded page:".  json_last_error_msg(), E_USER_ERROR);
				if (($header['http_code']==404&&$reason="http_code 404")
				||($this->flags&1&&$content===null&&$reason="null content")
				||($this->flags&2&&!preg_match("/<\/html>\s*$/",$content)&&$reason="cut off HTML")) {	
					if ($this->flags|8) {
						$logText="Will have to retry download of <a href=".
								curl_getinfo($handles[$key],CURLINFO_EFFECTIVE_URL).">({$key})";
						if (isset($specificNames[$key])) {
							 if (gettype($specificNames[$key])=="string")
								$logText.='"'.$specificNames[$key].'"';
							 else
								$logText.='"'.$specificNames[$key]["name"].'"';
						}
//						fwrite($logfile,date('H:i:s T ')
//						.$logText."</a> because of $reason.\r\n");
					}
					//Will have to retry this request. Remove and re-add the handle in the multihandler to enable that.
					curl_multi_remove_handle($mh, $handle);
					curl_multi_add_handle($mh, $handle);
					++$numRetries;
				} else {
					$output['contents'][$key]=$content;
					$output['headers'][$key]=$header;
					curl_multi_remove_handle($mh, $handle);
					curl_close($handle);
					unset ($handles[$key]);
					$fruitlessRetries=-1;
				}
			}
			if (++$fruitlessRetries>0) {//if this was a fruitless pass
				if ($fruitlessRetries==$this->maxFruitlessRetries||!$this->maxFruitlessRetries) {
//					fwrite($logfile,date('H:i:s T ')."Max amount of download-retries reached for this set."
//							. "Leaving ".count($handles)." request(s) unfinished.\r\n");
					foreach ($handles as $handle) {
						curl_multi_remove_handle($mh, $handle);
						curl_close($handle);
					}
					array_merge($failedIndices,array_keys($handles));
					$handles=[];
					continue;
				}
//				if ($this->flags|8)
//					fwrite($logfile,date('H:i:s T ')."No requests were successful on this download-pass. Sleeping for "
//						.$fruitlessPassDelay." seconds then trying again.\r\n");
			   sleep($this->fruitlessPassDelay);
			}
		} while (!empty($handles)||!empty($urls));
//		if ($this->flags|8)
//			fwrite($logfile,date('H:i:s T ')
//					."Finished downloading $generalName with $numRetries retry-passes.\r\n");
		$output['numRetries']=$numRetries;
		$output['failedIndices']=$failedIndices;
		return $output;
	}
	
	/**
	 * 
	 * @param string $historyPath
	 */
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

/**
 * Thread-safe filecreation.
 * @param string $dir Path to directory where the file should be created
 * @param string $prefix Prefix that will be prepended to the generated filename
 * @param string $suffix Suffix that will be appended ot the generated filename, commonly fileextension
 * @param string $content An optional string that will be inserted into the generated file upon creation.
 * @return string[] An array with a length of 2 where the first element is the path plus the filename of the generated
 *		file, and the second element is only the filename.
 */
function createFile($dir,$prefix='temp',$suffix='.log',$content=NULL) {
	while (!($handle=@fopen($filepathname=$dir.($filename=$prefix.uniqid().$suffix),'x')));
	if (isset($content)) {
		fwrite($handle,$content);
	}
	fclose($handle);
	return [$filepathname,$filename];
}