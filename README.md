# Hicurl
A library that aims in making cURL-requests a piece of cake while also allowing for saving the fetched pages along with headers and postdata to files for later viewing.
It essentially is a PHP wrapper class for cURL, and a javascript "class" that displays the saved data nicely.


## Getting Started
###Simple Usage
To use Hicurl on the PHP side you simply need to include hicurl.php inside the src folder:

````php
require_once 'hicurl/src/hicurl.php';
````

Then you can do something like the following to begin loading some pages:

````php
$hicurl=new Hicurl();
$google=$hicurl->loadSingle('www.google.com');
The $google-variable will now hold an associative array looking like:  
[
    'content'=>page-content  
    ,'headers'=>all the received headers  
    ,'error'=>false for success, otherwise a description of the error
]
````
It is also easy sending post-data. If an object is passed to the second parameter the request will be sent as POST with that data:

````php
require_once 'hicurl/src/hicurl.php';
$hicurl=new Hicurl();
$hicurl->loadSingle('http://www.htmlcodetutorial.com/cgi-bin/mycgi.pl',['foo'=>'bar']);
````
###Settings

There's a bunch of settings that can be used.
These can be passed or like this:
````php
$hicurl=new Hicurl(['cookie'=>'cookies/cookie.txt']);  
````
Any calls made with that instance will now save cookies in and send cookies from that file.
Settings can be changed after instance-creation:  
````php
$hicurl->settings(['cookie'=>'cookies/muffin.txt']);
````
Lastly, they may also be passed to the load call in which case they will be merged with the settings of the instance during that call only:
````php
$hicurl->loadSingle('www.google.com',null,['cookie'=>'cookies/macarone.txt']);
````
###Static usage
Most methods also have static counterparts which can be convenient if only a single call is to be made:
````php
$result=Hicurl->loadSingleStatic($url,$postData,['cookie'=>'cake.txt']);
````
It's equivalent without hicurl would be something along the lines of:
````php
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, true);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
curl_setopt($ch,CURLINFO_HEADER_OUT,true);
curl_setopt($ch,CURLOPT_COOKIEFILE],'cake.txt');
curl_setopt($ch,CURLOPT_COOKIEJAR]'cake.txt');
$postDataString="";
foreach ($postData as $key=>$value) {
    $postDataString.=urlencode($key)."=".urlencode($value)."&";
}
curl_setopt($ch,CURLOPT_POSTFIELDS, trim($string, ","));
$result=['content'=>curl_exec($ch),'headers'=>curl_getinfo($ch)];
curl_close($ch);
````
"Whew!"
###Saving history
Saving data is easy too. For starters we need one additional setting:
````php
//set a history output filepath. This setting will be used for subsequent load-calls and will make them save data to that file.
$hicurl->settings(['history'=>'history/myHistoryFile']);
//load a couple pages. they will be saved to myHistoryFile
$hicurl->loadSingle("www.google.com");
$hicurl->loadSingle("www.facebook.com");
//When we are done writing to the file we need to "compile it".
//This puts it in a closed state, and also compresses it.
//This is the state it is sent to the client in.
$hicurl->compileHistory()
````
###Sending history
There's some easy job to be done for sending the data to the client. Here's one way of doing it:


*getHistory.php*
````php
require_once 'hicurl/src/hicurl.php';
Hicurl::serveHistory('history/myHistoryFile');
````
That's what we need for sending the data. Though simply loading this page in the browser wont help much, so we will need to do the following...
###Viewing history
To be able to view the data in a meaningful way we will use the javascript hicurl-class.
We need to load some files to use it and then write a tiny bit more code.
````html
    <!--This is the js "class" used for viewing the history-->
    <script src="hicurl/src/hicurl.js"></script>
    <!--...it needs this css-file to display things correctly-->
    <link rel="stylesheet" type="text/css" href="hicurl/src/hicurl.css">
    <!--It also required jQuery-->
    <script src="hicurl/src/libs/jquery-1.11.2.min.js"></script>
    <!--.And jquery easy UI-->
    <script src="hicurl/src/libs/jquery.easyui.min.js"></script>
    <!--...along with it's css-file-->
    <link rel="stylesheet" type="text/css" href="../../src/libs/easyui.css">
    <!--This can optionally be included and will make html-code display better-->
    <script src="hicurl/src/libs/beautify-html.js"></script>
    <!--Now for initiating the class-->
    <script>
    //it expects a DOM-element in its first parameter, where everything will be rendered unto.
    //As second parameter we will need to pass a url-string where it will fetch the history-data from.
    hicurl=new Hicurl(document.body,"getMyHistory.php");
    //We will need to create that page, e.g. "getHistory.php" in this case,  and make it send the data.
    </script>
````