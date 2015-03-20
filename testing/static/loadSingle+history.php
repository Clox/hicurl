<?php
require_once '../../src/hicurl.php';
$result=Hicurl::loadSingleStatic('www.google.com',null,['history'=>'history.txt']);
echo $result['content'];