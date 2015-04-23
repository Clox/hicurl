<?php
require_once '../src/hicurl.php';
unlink('data/history.tmp');
$result=Hicurl::loadSingleStatic("http://whatismyip.org",null,
	['history'=>'data/history.tmp','xpath'=>true,'tor'=>true]);
//Hicurl::compileHistoryStatic('data/history.tmp','data/history.gz');
//include "assets/test.htm";
if ($result['error'])
	echo $result['error'];
else
	echo $ip=$result['domXPath']->query('/html/body/div[2]/span/text()')->item(0)->nodeValue;