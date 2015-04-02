<?php
require_once '../src/hicurl.php';
unlink('data/history.txt');
$hicurl=new Hicurl(['history'=>'data/history.txt']);
$hicurl->loadSingle('www.google.com');
$hicurl->compileHistory();
include "assets/test.htm";