<?php
require_once '../src/hicurl.php';
if (!isset($_GET['getHistory'])) {
	unlink('history.txt');
	$hicurl=new Hicurl(['history'=>'history.txt']);
	$hicurl->loadSingle('www.google.com');
	$hicurl->compileHistory();
	?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
		"http://www.w3.org/TR/html4/strict.dtd">
		<html lang="en">
		  <head>
			<meta http-equiv="content-type" content="text/html; charset=utf-8">
<!--			<link rel="stylesheet" type="text/css" href="style.css">-->
			<script src="https://code.jquery.com/jquery-2.1.3.min.js"></script><!--Hicurl requires jquery-->
			<script src="../../src/hicurl.js"></script>
			<script src="loadSingle.js"></script>
		  </head>
		  <body>
		  </body>
		</html>
	<?php
} else {
	Hicurl::echoHistory('history.txt');
}