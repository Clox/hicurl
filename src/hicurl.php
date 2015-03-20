<?php
/**
 * A class that simplifies URL requests. Rather than working with CURL and its settings directly this
 * class maks it easy by using simple syntax for the most used settings.
 * But more importantly this class also allows for easily saving the history of the requests&responses
 * with their contens/headers.*/
class Hicurl {
	/**@var array This is an array of settings which the user can define. They are set either through the
	 * class-constructor or via settings(). {@see settings()} for more info.*/
	private $settingsData;
	
	/**@var resource Curl Handler*/
	private $curlHandler;
	
	/**@var resource file handler for the history file.*/
	private $historyFileHandler;
	
	private static $historyStructureStart='{"pages":[';
	/**@var array Default settings used when creating an instance of calling load-methods statically.
	 * The default settings are merged with the settings passed to load-method or contructor. The defaults are:<br>
	 * 'maxFruitlessRetries'=>40,<br>'fruitlessPassDelay'=>10,<br>'maxRequestsPerPass'=>100,<br>'flags'=>1|2*/
	static public $defaultSettings=[
		'maxFruitlessRetries'=>40,
		'fruitlessPassDelay'=>10,
		'maxRequestsPerPass'=>100,
		'flags'=>3,
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
	 * <li>'flags' int An int with bit-flags.<ol>
	 *		<li>First bit (1) Will make load-calls retry requests where response-content is null.</li>
	 *		<li>Second bit(2) Will make load-calls retry requests where response doesn't end
	 *			with the closing tag for the html (That is "&lt/html&gt") with possible following whitespace.</li></ol>
	 * <li>'postHeaders' array An array of headers that will be sent on POST-requests.</li>
	 * <li>'getHeaders' array An array of headers that will be sent on GET-requests</li>
	 * <li>'history' string
	 *		Enables saving contents/headers of request&responses in a file for later viewing. The value can be:<ul>
	 *		<li>a path to a history-file. If it doesn't yet exist it will be created, else it will be appended to.</li>
	 *		<li>or a path to a directory in which case the path-string should end with a slash (/). In this case a
	 *		history-file will automatically be created in this directory. This can be useful during multithreading
	 *		and avoids one thread having to wait for another for writing to the history-file since each thread gets
	 *		its own if this setting is applied to each thread.</li></ul>
	 *		Regardless of which alternative is used, compileHistory() is then to be used to finalize the
	 *		history-writing.
	 *		For on the structure of history-files {@see writeHistory()}
	 *		</li></ul>
	 * @return array The resulted settings
	 */
	public function settings($settings=null) {
		if (!$settings)
			return $this->settingsData;
		if (array_key_exists('history',$settings)) {//if a setting for 'history' is present
			//if a history-file already is open and new history-value is null or not same as old
			if ($this->historyFileHandler&&$settings['history']!=$this->settingsData['history']) {
				fclose($this->historyFileHandler);//then close old connection
			}
			if (!empty($settings['history'])) {//if non empty
				$this->historyFileHandler=fopen($settings['history'],'a+');//then open/create file
				if (!file_exists($settings['history'])||!filesize($settings['history'])) {
					fwrite($this->historyFileHandler, Hicurl::$historyStructureStart);
				}
			}
		}
		//merge supplied settings with current settings of instance
		return $this->settingsData=$settings+$this->settingsData;
	}
	
	/**Function that writes history to the uncompiled history-file.
	 * History-files are "uncompiled" when they're being written to, until compileHistory() has been called on it,
	 * which finalizes it. In its uncompiled state it is formed like a json-array without the closing bracket. The
	 * elements are comma-separated json-objects representing "pages". All pages, including the last are for simplicity
	 * followed by commas. Their structure look like the following:<pre>
	 * {	formData:this is present if it was a POST-request, and will contain the sent form-data
	 *		,"name":a name-string for the page that will be shown in the history-viwer. Set via historyData>name
	 *		,"id": mixed//a unique id may be be set, which can then be used to refer to this page as parent of it
	 *		,"parentId": mixed//another page may be set as parent by setting its id in historyData>parentId. It will
	 *			//then show in the tree-view of the history-viewer
	 *		,"customData": contains what was passed to customData-parameter of the load method if anything
	 *		,"exchanges": [//An array of requests&responses pairs. Usually this will only contain one
	 *								//element but more will be added for each failed request
	 *			{
	 *				"error": this will be present if this request failed, and it will contain a description of the error
	 *				"content": the content of the page passed from te server
	 *				"headers": ...and the headers
	 *			}
	 *		]
	 * }
	 * </pre>
	 * @param string $data A undecoded page-object, explained in the description of this method.
	 * @param array $settings*/
	
	private static function writeHistory($historyFileHandler,$data,$settings) {
		$data=json_encode($data).',';
		if ($historyFileHandler) {
			fwrite($historyFileHandler, $data);
		} else {
			if (!file_exists($settings['history'])||!filesize($settings['history'])) {
				$data=Hicurl::$historyStructureStart.$data;
			}
			file_put_contents($settings['history'], $data, FILE_APPEND);
		}
	}
	/**This is the heart of Hicurl. It loads a requested url, using specified settings and returns the
	 * server-response along with some data, and optionally writes all data to a history-file.
	 * This method has a static method counterpart called loadSingleStatic which works just the same.
	 * @param string $url A URL-string to load
	 * @param string[] $formdata If this is null then the request is sent as GET. Otherwise it is sent as POST using
	 *		this array as formdata where key=name and value=value. It may be an empty array in which case the request
	 *		will stil be sent as POST butwith no formdata.
	 * @param array $settings Optional parameter of settings that will be merged with the settings of the instance for
	 *		the duration of this call only.
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
		$historyFileHandler;
		if ($this->historyFileHandler
			&&(!array_key_exists('history', $settings) || $settings['history']==$this->settingsData['history'])) {
			$historyFileHandler=$this->historyFileHandler;
		}
			
		return Hicurl::loadSingleReal($this->curlHandler,$historyFileHandler, $url, $formdata,
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
	 * * @see loadSingle* ]*/
	public static function loadSingleStatic($url,$formdata=null,$settings=[],$historyData=[]) {
		return Hicurl::loadSingleReal(curl_init(),null,$url,$formdata,$settings+Hicurl::$defaultSettings,$historyData);
	}
	private static function loadSingleReal($curlHandler,$historyFileHandler,$url,$formdata,$settings,$historyData) {
		curl_setopt_array($curlHandler, Hicurl::generateCurlOptions($url, $formdata));
		$numRetries=-1;
		if ($historyFileHandler||!empty($settings['history'])) {//should we write history?
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
			$content=curl_exec($curlHandler);
			$headers=curl_getinfo($curlHandler);
			$error=Hicurl::parseAndValidateResult($content,$headers,$settings);
			if (isset($historyPage)) {//are we writing history-data? this var is only set if we do
				$historyPage['exchanges'][]=[
					'content'=>$content,
					'headers'=>$headers,
					'error'=>$error
				];
			}
		} while ($error);//keep looping until $error is false
		if (isset($historyPage)) {//should we write history?
			Hicurl::writeHistory($historyFileHandler,$historyPage, $settings);
		}
		$output=[
			'content'=>$content,
			'headers'=>$headers
		];
		return $output;
	}
	
	private static function parseAndValidateResult(&$content,$headers, $settings) {
		if (ord($content[0])==0x1f && ord($content[1])==0x8b) {
			$content=gzdecode($content);
		}
		//utf8 is needed to correctly json-decode
		//can't blindly utf8-encode or data will be corrupted if it already was utf8 encoded.
		if (strpos($headers['content_type'],'utf-8')===false) {
			$content=utf8_encode($content);
		}
		if ($headers['http_code']==404) {
			return 'http code 404';
		}
		if ($settings['flags']&1&&$content===null) {
			return 'null content';
		}
		if ($settings['flags']&2&&!preg_match("/<\/html>\s*$/",$content)) {	
			return 'cut off html';
		}
		return false;
	}
	/**Compiles the history-file. This is to be done when the writing to the history-file is complete.
	 * This essentialy puts the file in a close state, gzipping it while also optionally adding extra data.
	 * @param array $customData This can optionally be set to include extra data. It should be an
	 *		associative or indexed array and can contain anything that is JSON-friendly.
	 * @return bool Returns true for success.*/
	public function compileHistory($customData) {
		return Hicurl::compileHistoryReal($this->historyFileHandler, $historyOutput);
	}
	/**Compiles history.
	 * The data is structured as:<pre>
	 * {//<-outermost object
	 *		"customData":array//customData
	 *		,"pages":array//array of
	 * }
	 * </pre>
	 * 
	 * @param type $historyInput
	 * @param type $historyOutput
	 * @param type $customData
	 * @return boolean
	 */
	private static function compileHistoryReal($historyInput,$historyOutput,$customData) {
		
		//Set memory_limit to a high number or there might be a problem holding all page-contents in memory which is
		//needed in order to gzip efficiently.
		$memoryLimit=ini_get('memory_limit');//save old to be able to revert
		ini_set('memory_limit', '512M');
		
		
		
		foreach ($historyFiles as $page) {
			if (!isset($output))
				$output='[';
			else
				$output.=',';
			$output.=file_get_contents($page['file']);
		}
		$output=gzencode($output);
		ini_set('memory_limit', $memoryLimit);//revert to old
		if (isset($saveToFileName))
			file_put_contents($saveToFileName,$output);
		else
			return $output;
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
					array_merge($failedIndices,  array_keys($handles));
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