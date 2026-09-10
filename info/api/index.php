<?php
header('Content-Type: application/json; charset=utf-8');
$action=$_GET['action']??'markets';
$map=['markets'=>'markets.php','pair'=>'pair.php','candles'=>'candles.php','activity'=>'activity.php','liquidity'=>'liquidity.php','sync'=>'sync.php'];
if(!isset($map[$action])){http_response_code(400);echo json_encode(['error'=>'Unknown action']);exit;}require __DIR__.'/'.$map[$action];
