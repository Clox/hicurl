<?php
require_once '../src/hicurl.php';
$result=Hicurl::loadSingle('www.google.com');
echo $result['content'];