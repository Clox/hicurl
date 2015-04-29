<?php
require_once '../src/hicurl.php';
$hicurl=new Hicurl(['history'=>'data/history']);
$hicurl->loadSingle('www.google.com',null,null,['name'=>'Google']);
$hicurl->loadSingle('www.facebook.com',null,null,['name'=>'Facebook']);
//$hicurl->compileHistory('data/history.gz');
include "assets/test.htm";