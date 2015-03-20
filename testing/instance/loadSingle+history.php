<?php
require_once '../../src/hicurl.php';
$hicurl=new Hicurl(['history'=>'history.txt']);
$result=$hicurl->loadSingle('www.google.com');
echo $result['content'];
