<?php
/** Hicurl*/


/**
 * A class that simplifies URL requests and adds new functionality, like saving history of requests
 * with their contens/headers.
 * @property-read bool|null $isHistoryCompressed Tells if history-folder is compressed.
 *		True if it is, false if it's not and null if there is no history-folder*/
class Hicurl {
	
	/**@var array This is an array of settings which the user can define. They are set either through the
	 * class-constructor or via settings(). {@see settings()} for more info.*/
	private $settingsData;
	
	/**@var resource Curl Handler*/
	private $curlHandler;
	
	/**@var SplFileObject file-object for the history file.*/
	private $historyDataFileObject;
	
	/**@var SplFileObject file-object for customData.json(.gz)*/
	private $customDataFileObject;
	
	private $isHistoryCompressed;
	
	private $historyFolderPath;
	
	/**@var array Default settings used when creating an instance of Hicurl or calling its load-methods statically.*/
	static public $defaultSettings=[
		'acceptStatusCodes'=>[200,201,202,203,204,205,206,207,208,226,300,301,302,303,304,305,306,307,308],
		'maxFruitlessRetries'=>40,
		'fruitlessPassDelay'=>10,
		'maxRequestsPerPass'=>100,
		'retryOnNull'=>true,
		'retryOnIncompleteHTML'=>true
	];
	
	/**
	 * Constructs an instance of Hicurl with the given settings and optionally a directory to save history to.
	 * @param array $settings The same data as can be passed to settings().
	 * @param string $historyFolderPath	Path to a directory of where to save history to.
	 *		Setting it enables history-saving. All contents of requested pages along with request/response-headers
	 *		will be saved to this directory. If the specified folder doesn't exist then it will automatically be
	 *		created(recusively). When no more writing is to be done to this folder it's a good idea to call
	 *		compressHistory() on it.*/
    function __construct($settings=null,$historyFolderPath=null) {
		$this->settingsData=Hicurl::$defaultSettings;
		if ($settings)
			$this->settings($settings);
		$this->curlHandler=curl_init();
		if (isset($historyFolderPath)) {
			$this->setupHistoryFolder($historyFolderPath);
		}
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
	 *		<li>'acceptStatusCodes' int[] An array of HTTP status codes to accept. Any request with
	 *			a status code not in this list will be considered failed.</li>
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
	 *		<li>'tor' bool If true then a proxy on port 9050 will be used for the requsts.</li>
	 *	</ul>
	 * @return array The resulted settings*/
	public function settings($settings=null) {
		if ($settings) {
			//merge supplied settings with current settings of instance
			$this->settingsData=$settings+$this->settingsData;
		}
		return $this->settingsData;
	}
	
	/**
	 * Sets up the history-folder supplied to the Hicurl-constructor.
	 * It creates the folder if it doesn't already exist along with its "pages"-folder and also creates
	 * "data.json" with initial strucuture-data.
	 * @param string $historyPath The path to the history-folder.*/
	private function setupHistoryFolder($historyPath) {
		$this->historyFolderPath=$historyPath;
		if ($this->isHistoryCompressed=file_exists("$historyPath/data.json.gz")) {
			if (file_exists("$historyPath/customData.json.gz")) {
				$this->customDataFileObject=new SplFileObject("$historyPath/customData.json.gz");
			}
			return false;
		} else if (file_exists("$historyPath/customData.json")) {
				$this->customDataFileObject=new SplFileObject("$historyPath/customData.json");
		}
		if (file_exists($historyPath)&&!is_dir($historyPath)) {
			trigger_error("A path to a file was given as Hicurl history-folder. It should be set to a path to a "
				. "folder(possibly non existent).", E_USER_ERROR);
		}
		
		if (!file_exists("$historyPath/pages")) {
			mkdir("$historyPath/pages", 0777, true);
		}
		$this->historyDataFileObject=new SplFileObject("$historyPath/data.json",'c+');
		
		//lock file with non-block. if it is already locked then skip this block.
		//if it's locked then we know base-structure has been written to it anway.
		if ($this->historyDataFileObject->flock(LOCK_EX)) {
			//write base-structure if size is zero
			if ($this->historyDataFileObject->fstat()['size']==0) {
				$this->historyDataFileObject->fwrite('{"pages":[]}');
			}
			$this->historyDataFileObject->flock(LOCK_UN);
		}
		
	}
	
	/**
	 * Writes history to the history-folder. Each page-content gets its own file in the "pages"-folder,
	 * and there's also a json-file of the name "data.json" that is written to which is shared among all requests.
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
	 * @param string[] $pageContents An indexed array of content-strings for this page. Contains 1 usually but one
	 *		more is added for each failed request.
	 * @param array $pageObject A page object as in the "pages"-array described in the description of this method,
	 *			minus the "content"-element of the elements in the "exchanges"-array. Each element of the
	 *			"exchanges"-array corresponds to one content with the same index in the $pageContents-argument.
	 * @param array $historyOptions The history-options passed to the load-method.*/
	private function writeHistory($pageContents,$pageObject,$historyOptions) {
		$numExchanges=count($pageContents);
		$this->historyDataFileObject->flock(LOCK_EX);
		for ($i=0; $i<$numExchanges; ++$i) {
			if (isset($historyOptions['name'])) {
				$wantedFileName=preg_replace("([^\w\s\d\-_~,;:\[\]\(\)])", '', $historyOptions['name']);
			} else {
				$wantedFileName=time();
			}
			if ($numExchanges>1)
				$wantedFileName.="_$i";
			$fileName=$wantedFileName;
			for ($j=0; file_exists("$this->historyFolderPath/pages/$fileName"); ++$j) {       
				$fileName=$wantedFileName."($j)";
			}
			
			//locking not needed since this can only be run by one thread at a time, which is the one that holds the
			//lock of $historyDataFileObject
			file_put_contents("$this->historyFolderPath/pages/$fileName", $pageContents[$i]);
			$pageObject['exchanges'][$i]['content']=$fileName;
		}
		$this->historyDataFileObject->fseek(-2, SEEK_END);
		$size=$this->historyDataFileObject->fstat()['size'];
		$this->historyDataFileObject->fwrite(($size>12?',':'').json_encode($pageObject).']}');
		$this->historyDataFileObject->flock(LOCK_UN);
	}
	
	/**
	 * Loads a requested url, using specified settings and returns the server-response along with some data and
	 * optionally saves data to a "history-folder".
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
	 * </ul>*/
	public function loadSingle($url,$formdata=null,$settings=null,$history=null) {
		$numRetries=-1;
		$settings=($settings?:[])+$this->settingsData;
		Hicurl::setCurlOptions($url, $formdata,$settings);
		$output=[];//this is the array that will be returned
		if ($this->historyDataFileObject) {
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
			$content=curl_exec($this->curlHandler);//do the actual request. assign response-content to $content
			$headers=curl_getinfo($this->curlHandler);//get the headers too
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
			Hicurl::writeHistory($contents,$historyPage, $history);
		}
		return $output+=[
			'content'=>$content,
			'headers'=>$headers,
			'error'=>false,//(this wont overwrite an error-description if it has been written already)
			'errorCode'=>0
		];
	}
	
	
	/**
	 * Takes reference of content-string and utf8-encodes it if necessary and also does the validation which
	 * determines if the request was deemed successful or not, based on $settings.
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
		if (!in_array($headers['http_code'],$settings['acceptStatusCodes'])) {
			return "HTTP code $headers[http_code]";
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
	 * Method that does xpath-evaluations if set in settings->xpath. Also places a DOMXPATH-object in the array
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
		libxml_use_internal_errors(true);
		$domDocument->loadHTML('<meta http-equiv="content-type" content="text/html; charset=utf-8">'.$pageContent);
		libxml_use_internal_errors(false);
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
	 * Compresses the history-folder.
	 * This is to be done when writing to the history-file is complete, as it naturally puts the history in a closed
	 * state. If this method is called on an already compressed history-folder then it will simply return false.
	 * It is recommended that this is called separated from the logic that writes to the history, or in a separate
	 * thread/process since it may take a long time finish.
	 * @return bool Returns TRUE on success or FALSE on failure.*/
	public function compressHistory() {
		if ($this->isHistoryCompressed) {//looks like this folder already has been compiled
			return false;
		}
		$separator=DIRECTORY_SEPARATOR;
		$startTime=microtime(true);
		$this->historyFolderPath=realpath($this->historyFolderPath).DIRECTORY_SEPARATOR;
		if(!function_exists('exec')) {
			trigger_error("Access to system shell is currently mandatory.", E_USER_ERROR);
		}
		//remove pointer to files so that it may be accesed by cli
		$this->historyDataFileObject=null;
		$this->customDataFileObject=null;
		
		$delFileCmd=PHP_OS=="Linux"?"rm":"del";
		$delDirCmd=PHP_OS=="Linux"?"rm -rf":"rmdir/s/q";
		$command="cd \"$this->historyFolderPath\""
				." && 7z a pages.7z pages$separator*"//compress the files in the pages-folder
				." && 7z a data.json.gz data.json"//..and data.json
				//remove source files. this method is about 43% faster than php glob()+unlink()
				." && $delDirCmd pages && $delFileCmd data.json";
		if (file_exists($this->historyFolderPath.'customData.json')) //deal with customData
			$command.=" && 7z a customData.json.gz customData.json && $delFileCmd customData.json";
		exec($command,$output,$return_var);
		$timeTaken=microtime(true)-$startTime;
		if (!$return_var) {//if previous command was successful
			$pagesArchiveInfo=Hicurl::getArchiveInfo($this->historyFolderPath.'pages.7z');
			$dataArchiveInfo=Hicurl::getArchiveInfo($this->historyFolderPath."data.json.gz");
			
			$passedHours = floor($timeTaken / 3600);
			$passedMins = floor(($timeTaken - ($passedHours*3600)) / 60);
			$passedSecs = floor($timeTaken % 60);
			
			$oldSize=$pagesArchiveInfo['uncompressedSize']+$dataArchiveInfo['uncompressedSize'];
			$newSize=$pagesArchiveInfo['compressedSize']+$dataArchiveInfo['compressedSize'];
			$numFiles=$pagesArchiveInfo['numFiles']+1;
			if (file_exists($this->historyFolderPath.'customData.json.gz')) {
				$customDataArchiveInfo=Hicurl::getArchiveInfo($this->historyFolderPath.'customData.json.gz');
				$oldSize+=$customDataArchiveInfo['uncompressedSize'];
				$newSize+=$customDataArchiveInfo['compressedSize'];
				++$numFiles;
			}
			
			file_put_contents($this->historyFolderPath.'info.txt', 
				"Compiled history at ".date("D M d, Y G:i",$startTime)."\r\n"
				.$numFiles." files at a total size of "
				.Hicurl::formatBytes($oldSize)
				." compressed down to ".
					Hicurl::formatBytes($newSize)." (".round($newSize/$oldSize*100,2)."% of original)\r\n"
				."Time taken: $passedHours hours, $passedMins minutes and $passedSecs seconds"
			);
		}
		$this->isHistoryCompressed=true;
		return !$return_var;
	}
	
	
	/**
	 * 
	 * @param bool $forUpdate
	 * @return type
	 */
	public function getCustomData($forUpdate=false) {
		if (!$this->customDataFileObject) {
			$customDataPath="{$this->settingsData['history']}/customData.json";
			if (file_exists($customDataPath)) {
				$this->customDataFileObject=new SplFileObject($customDataPath,"c+");
			} else if (file_exists($customDataPath.'.gz')) {
				$this->customDataFileObject=new SplFileObject($customDataPath.'.gz',"c+");
			} else {
				return null;
			}
		}
		$this->isHistoryCompressed=$this->customDataFileObject->getExtension()=='gz';
		if ($this->isHistoryCompressed&&$forUpdate) {
			trigger_error('Can\'t get custom data from compressed history with $forUpdate set to TRUE.', E_USER_ERROR);
		}
		$this->customDataFileObject->flock($forUpdate?LOCK_EX:LOCK_SH);
		$customData=$this->customDataFileObject->fread($this->customDataFileObject->fstat()['size']);
		if ($this->isHistoryCompressed) {
			$customData=gzdecode($customData);
		}
		$customData=json_decode($customData,true);
		if (!$forUpdate) {
			$this->customDataFileObject->flock(LOCK_UN);
		}
		return $customData;
	}

	public function setCustomData($data) {
		if ($this->isHistoryCompressed) {
			trigger_error("Can't write to custom data in compressed history.", E_USER_ERROR);
		}
		$this->customDataFileObject=new SplFileObject("$this->historyFolderPath/customData.json","c+");
		$this->customDataFileObject->flock(LOCK_EX);
		$this->customDataFileObject->fwrite(json_encode($data));
		$this->customDataFileObject->flock(LOCK_UN);
	}
	
	/**
	 * Writes history to the output-stream. The url of a page that calls this method is to be passed to the
	 * JS Hicurl constructor as the second argument (dataUrl)
	 * @param string $historyFolderPath Path-string of the history-folder to be served.*/
	public static function serveHistory($historyFolderPath) {
		$cache=true;
		if (isset($_GET['getJsonList'])) {
			if ($this->isHistoryCompressed) {
				Hicurl::servePrecompressedGZ("$historyFolderPath/data.json.gz");
			} else {
				readfile("$historyFolderPath/data.json");
				$cache=false;
			}
		} else {
			$historyFolderPath=realpath($historyFolderPath);
			if ($this->isHistoryCompressed) {
				$command='7z e "'.$historyFolderPath.DIRECTORY_SEPARATOR
						."pages.7z\" \"$_GET[getPageContent]\" -so 2>7z_e_log.txt";
				system($command);
			}
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
	
	/**
	 * Gets info on an archive using 7z
	 * @param string $archivePath Path the archive
	 * @return array Returns an array with the following elements: <ul>
	 *		<li>'uncompressedSize' int Size of the contents of the archives before compression, in bytes</li>
	 *		<li>'compressedSize' int Size of the contents of the archives after compression, in bytes</li>
	 *		<li>'numFiles' int Number of files in the archive</li></ul>*/
	private static function getArchiveInfo($archivePath) {
		$archivePath=realpath($archivePath);
		exec("7z l \"$archivePath\"",$output);
		
		//needs -6 if 7za is used because it supplies some additional info at the bottom
		$archiveData=$output[count($output)-1];
		return [
			'uncompressedSize'=>(int)substr($archiveData,26,12),
			'compressedSize'=>(int)substr($archiveData,39,12),
			'numFiles'=>(int)substr($archiveData,53,24)
		];
	}
	
	
	
	/**
	 * Formats number of bytes into KB/MB/GB/TB
	 * @param int $bytes Number of bytes
	 * @param int $precision 0=B,1=KB,2=MB,3=GB,4=TB
	 * @return string $bytes converted and with KB/MB/GB/TB as suffix.*/
	private static function formatBytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 
		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
		$bytes /= (1 << (10 * $pow)); 
		return round($bytes, $precision) . ' ' . $units[$pow]; 
	}
	
	/**
	 * Generates the options that are used for the curl object and sets them to the curl-handler.
	 * @param string $url The url for the request
	 * @param array|null $formdata If post-request then this should be an associative array of the formdata.
	 * @param array $settings Current settings
	 * @return void*/
	private function setCurlOptions($url,$formdata,$settings) {
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
		curl_reset($this->curlHandler);
		
		curl_setopt_array($this->curlHandler,$curlOptions);
	}
	
	/**
	 * Method used for serving a file that already has been compressed in gz format so that the client agent may
	 * automaticallychandle the decompression.
	 * @param type $filePath Path to the file*/
	private static function servePrecompressedGZ($filePath) {
		ini_set('zlib.output_compression','Off');
		$HTTP_ACCEPT_ENCODING = $_SERVER["HTTP_ACCEPT_ENCODING"]; 
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
			readfile($filePath);
		} else
			echo gzdecode (file_get_contents($filePath));
	}
	function __get($name) {
        switch($name) {
			case 'isHistoryCompressed':
				return $this->isHistoryCompressed;
			default:
				user_error("Invalid property: " . __CLASS__ . "->$name");	
		}
    }
}
