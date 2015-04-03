<?php
require_once '../src/hicurl.php';
unlink('data/history.txt');
$hicurl=new Hicurl(['history'=>'data/history.txt']);
$hicurl->loadSingle('https://www.random.org/integers/?num=1&min=1&max=100&col=1&base=10&format=html&rnd=new',null,
	[
		'xpathValidate'=>[
			'expression'=>'//*[@id="invisible"]/pre/text()',
			'compare'=>'x>75'
		],
		'maxFruitlessRetries'=>20,
		'fruitlessPassDelay'=>0,	
	]);
$hicurl->compileHistory();
include "assets/test.htm";