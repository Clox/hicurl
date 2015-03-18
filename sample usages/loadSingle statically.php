<?php
require_once '../src/hicurl.php';
$result=Hicurl::loadSingleStatic('www.google.com');
echo $result['content'];