<?php
require_once '../src/hicurl.php';
unlink('data/history.tmp');
$hicurl=new Hicurl(['history'=>'data/history.tmp']);
$hicurl->loadSingle('www.google.com');
$hicurl->compileHistory('data/history.gz',null,true);
include "assets/test.htm";