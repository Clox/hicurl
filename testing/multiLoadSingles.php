<?php
require_once '../src/hicurl.php';
unlink('data/history.tmp');
$hicurl=new Hicurl(['history'=>'data/history.tmp']);
$hicurl->loadSingle('www.google.com',null,null,['name'=>'Google']);
$hicurl->loadSingle('www.facebook.com',null,null,['name'=>'Facebook']);
$hicurl->compileHistory('data/history.gz');
include "assets/test.htm";