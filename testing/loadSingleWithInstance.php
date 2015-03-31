<?php
require_once '../src/hicurl.php';
unlink('history.txt');
$hicurl=new Hicurl(['history'=>'history.txt']);
$hicurl->loadSingle('www.google.com');
$hicurl->compileHistory();
include "assets/test.htm";