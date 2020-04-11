<?php
require_once '../src/hicurl.php';
unlink('data/history.tmp');
$hicurl=new Hicurl(['history'=>'data/history']);
$hicurl->loadSingle('www.google.com');
//$hicurl->compileHistory('data/history.gz');
//include "assets/test.htm";