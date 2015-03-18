<?php
require_once '../src/hicurl.php';
$hicurl=new Hicurl();
$result=$hicurl->loadSingle('www.google.com');
echo $result['content'];
