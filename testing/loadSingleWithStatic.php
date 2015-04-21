<?php
require_once '../src/hicurl.php';
unlink('data/history.tmp');
Hicurl::loadSingleStatic('www.google.com',null,['history'=>'data/history.tmp']);
Hicurl::compileHistoryStatic('data/history.tmp','data/history.gz');
include "assets/test.htm";