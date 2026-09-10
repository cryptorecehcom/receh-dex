<?php require __DIR__.'/engine.php';
$a=$_GET['pair']??($_GET['address']??''); if(!validAddr($a)) out(['error'=>'Invalid pair address'],400);
try{
  // Pair page must render from the current on-chain snapshot immediately.
  // Historical logs/candles/activity are indexed separately by sync.php.
  $d=currentPairSnapshot($a); out($d);
}catch(Throwable $e){
  $cached=readj(pairFile($a),null); if($cached) out($cached);
  out(['error'=>'Pair data temporarily unavailable','detail'=>$e->getMessage()],503);
}
