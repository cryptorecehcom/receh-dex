<?php require __DIR__.'/engine.php';
$a=$_GET['pair']??'';try{
 if($a!==''){if(!validAddr($a))out(['error'=>'Invalid pair address'],400);$d=syncPair($a,true);$events=$d['events']??[];$counts=['SYNC'=>0,'SWAP'=>0,'MINT'=>0,'BURN'=>0];foreach($events as $e){$t=$e['type']??'';if(isset($counts[$t]))$counts[$t]++;}$files=[];foreach(['1H','4H','1D','1W','1M'] as $r){$f=candleFile($a,$r);$files[$r]=is_file($f)?count(readj($f,[])):0;}out(['ok'=>true,'pair'=>$d,'diagnostics'=>['events'=>$counts,'candles'=>$files,'lastBlock'=>$d['lastBlock']??0,'recentLastBlock'=>$d['recentLastBlock']??0,'firstBlock'=>$d['firstBlock']??0,'syncError'=>$d['syncError']??null]]);}
 $d=refreshMarkets(true);out(['ok'=>true,'markets'=>$d]);
}catch(Throwable $e){out(['ok'=>false,'error'=>$e->getMessage()],503);}
