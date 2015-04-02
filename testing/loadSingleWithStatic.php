<?php
require_once '../src/hicurl.php';
unlink('data/history.txt');
Hicurl::loadSingleStatic('www.google.com',null,['history'=>'data/history.txt']);
Hicurl::compileHistoryStatic('data/history.txt');
include "assets/test.htm";