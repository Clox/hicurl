<?php
require_once '../src/hicurl.php';
unlink('data/history.txt');
$hicurl=new Hicurl(['history'=>'data/history.txt']);
$hicurl->loadSingle('www.google.com',null,null,['name'=>'Google']);
$hicurl->loadSingle('www.facebook.com',null,null,['name'=>'Facebook']);
$hicurl->compileHistory();
include "assets/test.htm";