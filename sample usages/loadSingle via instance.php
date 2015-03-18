<?php
require_once '../src/hicurl.php';
$hicurl=new Hicurl();
$result=$hicurl::loadSingleStatic('www.google.com');
echo $result['content'];
