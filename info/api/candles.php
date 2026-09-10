<?php require __DIR__.'/engine.php';
$a=$_GET['pair']??'';$range=$_GET['range']??'1D';if(!validAddr($a))out(['error'=>'Invalid pair address'],400);if(!in_array($range,['1H','4H','1D','1W','1M'],true))$range='1D';
try{$d=readj(candleFile($a,$range),[]);out(['pair'=>$a,'range'=>$range,'data'=>$d,'updatedAt'=>filemtime(candleFile($a,$range))?:0]);}catch(Throwable $e){out(['pair'=>$a,'range'=>$range,'data'=>[],'error'=>'Candle data temporarily unavailable','detail'=>$e->getMessage()],503);}
