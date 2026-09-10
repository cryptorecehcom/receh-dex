<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=5, stale-while-revalidate=30');

function out($data,int $code=200):never { http_response_code($code); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
function low(string $s):string{return strtolower($s);} function validAddr(string $a):bool{return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/',$a);} function zeroAddr(string $a):bool{return preg_match('/^0x0{40}$/i',$a)===1;}
function ensureDirs():void{foreach([ROOT,ROOT.'/pairs',ROOT.'/candles',ROOT.'/activity',ROOT.'/liquidity',ROOT.'/meta'] as $d)if(!is_dir($d))@mkdir($d,0755,true);}
function readj(string $f,$fallback=[]){if(!is_file($f))return $fallback;$d=json_decode((string)@file_get_contents($f),true);return is_array($d)?$d:$fallback;}
function writej(string $f,$data):void{ensureDirs();$dir=dirname($f);if(!is_dir($dir))@mkdir($dir,0755,true);$tmp=$f.'.tmp.'.bin2hex(random_bytes(4));$json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if(@file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('Cannot write data file');if(!@rename($tmp,$f)){@unlink($tmp);throw new RuntimeException('Cannot commit data file');}}
function lockFile(string $file):?array{$h=@fopen($file,'c+');if(!$h)return null;if(!@flock($h,LOCK_EX|LOCK_NB)){fclose($h);return null;}return [$h];} function unlockFile(array $l):void{@flock($l[0],LOCK_UN);@fclose($l[0]);}
function rpc(string $method,array $params){$payload=json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>$params]);$last='RPC unavailable';foreach(RPCS as $url){try{if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18]);$raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false||$http>=500)throw new RuntimeException($err?:'HTTP '.$http);}else{$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>$payload,'timeout'=>18]]);$raw=@file_get_contents($url,false,$ctx);if($raw===false)throw new RuntimeException('HTTP request failed');}$d=json_decode((string)$raw,true);if(isset($d['error']))throw new RuntimeException($d['error']['message']??'RPC error');if(array_key_exists('result',$d))return $d['result'];$last='Invalid RPC response';}catch(Throwable $e){$last=$e->getMessage();continue;}}throw new RuntimeException($last);}
function rpcTry(string $method,array $params){try{return rpc($method,$params);}catch(Throwable $e){return null;}}
function rpcBatch(array $calls):array{
  if(!$calls)return [];
  $payload=[];$map=[];$id=1000;
  foreach($calls as $i=>$c){$id++;$payload[]=['jsonrpc'=>'2.0','id'=>$id,'method'=>$c[0],'params'=>$c[1]];$map[$id]=$i;}
  $last='RPC batch unavailable';
  foreach(RPCS as $url){try{
    $raw='';
    if(function_exists('curl_init')){
      $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18]);$raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false||$http>=500)throw new RuntimeException($err?:'HTTP '.$http);
    }else{$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>json_encode($payload),'timeout'=>18]]);$raw=@file_get_contents($url,false,$ctx);if($raw===false)throw new RuntimeException('HTTP request failed');}
    $rows=json_decode((string)$raw,true);if(!is_array($rows))throw new RuntimeException('Invalid RPC batch response');$out=[];
    foreach($rows as $row){$rid=$row['id']??null;if($rid===null||!isset($map[$rid]))continue;$i=$map[$rid];if(isset($row['error']))$out[$i]=null;else $out[$i]=$row['result']??null;}
    return $out;
  }catch(Throwable $e){$last=$e->getMessage();continue;}}
  throw new RuntimeException($last);
}

function hxint(string $h):int{if($h==='')return 0;$h=preg_replace('/^0x/i','',$h);if(strlen($h)>15)return (int)hexdec(substr($h,-15));return (int)hexdec($h);}
function hxnum(string $h):float{$h=preg_replace('/^0x/i','',$h);if($h==='')return 0.0;$v=0.0;for($i=0,$n=strlen($h);$i<$n;$i+=8)$v=$v*4294967296.0+hexdec(substr($h,$i,8));return $v;}
function word(string $h,int $i):string{$h=preg_replace('/^0x/i','',$h);return substr($h,$i*64,64);}
function addrWord(string $h):string{return '0x'.substr(word($h,0),-40);} function pad32(string $a):string{return str_pad(preg_replace('/^0x/i','',$a),64,'0',STR_PAD_LEFT);}
function selector(string $s):string{static $m=['allPairsLength()'=>'574f2ba3','allPairs(uint256)'=>'1e3dd18b','getPair(address,address)'=>'e6a43905','token0()'=>'0dfe1681','token1()'=>'d21220a7','getReserves()'=>'0902f1ac','decimals()'=>'313ce567','symbol()'=>'95d89b41','name()'=>'06fdde03'];return '0x'.($m[$s]??'');}
function call(string $to,string $data){return rpc('eth_call',[['to'=>$to,'data'=>$data],'latest']);}
function callAddr(string $to,string $sig,string $arg=''):string{return addrWord(call($to,selector($sig).$arg));}
function callUint(string $to,string $sig,string $arg=''):int{return hxint(call($to,selector($sig).$arg));}
function decodeString(string $hex):string{$h=preg_replace('/^0x/i','',$hex);if(strlen($h)<64)return ''; $off=hxint('0x'.substr($h,0,64));$pos=$off*2;if($pos+64<=strlen($h)){$len=hxint('0x'.substr($h,$pos,64));$raw=substr($h,$pos+64,$len*2);if($raw!==false&&$raw!==''){ $bin=@hex2bin($raw);if($bin!==false)return trim($bin);}} if(strlen($h)>=64){$bin=@hex2bin(substr($h,0,64));if($bin!==false)return trim(rtrim($bin,"\0"));}return '';}
function tokenMeta(string $a):array{static $cache=[];$k=low($a);if(isset($cache[$k]))return $cache[$k];$builtin=[low(WBNB)=>['symbol'=>'WBNB','name'=>'Wrapped BNB','decimals'=>18,'logoURI'=>'https://raw.githubusercontent.com/trustwallet/assets/master/blockchains/smartchain/assets/0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c/logo.png'],low(USDT)=>['symbol'=>'USDT','name'=>'Tether USD','decimals'=>18,'logoURI'=>'https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/USDT.webp'],low(USDC)=>['symbol'=>'USDC','name'=>'USD Coin','decimals'=>18,'logoURI'=>'https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/USDC.webp'],low(RECEH)=>['symbol'=>'RECEH','name'=>'RECEH','decimals'=>18,'logoURI'=>'https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/RECEH.webp'],low(MEGAH)=>['symbol'=>'MEGAH','name'=>'Megah Digital Asset','decimals'=>18,'logoURI'=>'https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/MEGAH.webp']];
  $b=$builtin[$k]??null;$dec=$b['decimals']??18;$sym=$b['symbol']??'';$name=$b['name']??'';$logo=$b['logoURI']??'';try{$dec=callUint($a,'decimals()');}catch(Throwable $e){} try{$sym=decodeString(call($a,selector('symbol()')))?:$sym?:'TOKEN';}catch(Throwable $e){$sym=$sym?:'TOKEN';} try{$name=decodeString(call($a,selector('name()')))?:$name?:$sym;}catch(Throwable $e){$name=$name?:$sym;} $cache[$k]=['address'=>$a,'symbol'=>$sym,'name'=>$name,'decimals'=>$dec,'logoURI'=>$logo];return $cache[$k];}
function pairMeta(string $pair):array{$t0=callAddr($pair,'token0()');$t1=callAddr($pair,'token1()');return ['address'=>$pair,'token0'=>tokenMeta($t0),'token1'=>tokenMeta($t1)];}
function human(string $hex,int $dec):float{$v=hxnum($hex);return $dec? $v/pow(10,$dec):$v;}
function reserves(string $pair,array $meta):array{$r=call($pair,selector('getReserves()'));return ['r0'=>human(substr($r,2,64),$meta['token0']['decimals']),'r1'=>human(substr($r,66,64),$meta['token1']['decimals']),'ts'=>hxint('0x'.substr($r,130,64)),'raw0'=>'0x'.substr($r,2,64),'raw1'=>'0x'.substr($r,66,64)];}
function pairOf(string $a,string $b):?string{try{$p=callAddr(FACTORY,'getPair(address,address)',pad32($a).pad32($b));return zeroAddr($p)?null:$p;}catch(Throwable $e){return null;}}
function logsChunk(string $pair,int $from,int $to,array $topics):array{
  if($from>$to)return [];
  $f=['address'=>$pair,'fromBlock'=>'0x'.dechex($from),'toBlock'=>'0x'.dechex($to),'topics'=>[$topics]];
  return rpc('eth_getLogs',[$f])??[];
}
function getLogs(string $pair,int $from,int $to,array $topics):array{
  if($from>$to)return [];
  $out=[];
  foreach($topics as $topic){
    for($s=$from;$s<=$to;$s+=LOG_CHUNK){
      $e=min($to,$s+LOG_CHUNK-1);
      $out=array_merge($out,logsChunk($pair,$s,$e,[$topic]));
    }
  }
  $seen=[];$uniq=[];
  foreach($out as $log){
    $key=($log['transactionHash']??'').':'.($log['logIndex']??'');
    if($key!==':'&&!isset($seen[$key])){$seen[$key]=1;$uniq[]=$log;}
  }
  usort($uniq,function($a,$b){$x=hxint($a['blockNumber']??'0x0');$y=hxint($b['blockNumber']??'0x0');return $x<=>$y ?: hxint($a['logIndex']??'0x0')<=>hxint($b['logIndex']??'0x0');});
  return $uniq;
}
function scanEventsSafe(string $pair,int $from,int $to):array{
  if($from>$to)return ['events'=>[],'lastBlock'=>$from-1,'complete'=>true,'error'=>null];
  $topics=[SYNC_TOPIC,SWAP_TOPIC,MINT_TOPIC,BURN_TOPIC];
  $events=[];$cursor=$from;$errors=[];
  while($cursor<=$to){
    $chunkEnd=min($to,$cursor+LOG_CHUNK-1);
    try{
      $logs=getLogs($pair,$cursor,$chunkEnd,$topics);
      $events=array_merge($events,$logs);
      $cursor=$chunkEnd+1;
    }catch(Throwable $e){
      $errors[]=$e->getMessage();
      break;
    }
  }
  return ['events'=>$events,'lastBlock'=>$cursor-1,'complete'=>$cursor>$to,'error'=>$errors?implode(' | ',array_unique($errors)):null];
}
function blockTs(string $block):int{static $c=[];$k=low($block);if(isset($c[$k]))return $c[$k];$b=rpc('eth_getBlockByNumber',[$block,false]);return $c[$k]=hxint($b['timestamp']??'0x0');}
function blockTsMany(array $blocks):array{
  static $c=[];$need=[];$out=[];
  foreach($blocks as $block){$k=low($block);if(isset($c[$k])){$out[$k]=$c[$k];}else{$need[$k]=$block;}}
  if($need){try{$calls=[];foreach($need as $block)$calls[]=['eth_getBlockByNumber',[$block,false]];$rows=rpcBatch($calls);$i=0;foreach($need as $k=>$block){$b=$rows[$i++]??null;$c[$k]=is_array($b)?hxint($b['timestamp']??'0x0'):0;$out[$k]=$c[$k];}}catch(Throwable $e){foreach($need as $k=>$block)$out[$k]=$c[$k]=blockTs($block);}}
  return $out;
}
function usdRef(string $token,int $depth=0):?float{static $memo=[];$k=low($token);if(isset($memo[$k]))return $memo[$k];if($k===low(USDT)||$k===low(USDC))return $memo[$k]=1.0;if($k===low(WBNB))return $memo[$k]=wbnbUsd();if($depth>1)return null;
  $best=null;$bestL=0.0;foreach([USDT,USDC] as $st){$p=pairOf($token,$st);if(!$p)continue;try{$m=pairMeta($p);$r=reserves($p,$m);$is0=low($m['token0']['address'])===$k;$x=$is0?$r['r0']:$r['r1'];$u=$is0?$r['r1']:$r['r0'];$liq=2*$u;if($x>0&&$u>0&&$liq>=ORACLE_MIN_LIQ_USD&&$liq>$bestL){$best=$u/$x;$bestL=$liq;}}catch(Throwable $e){}}
  if($best!==null)return $memo[$k]=$best;$p=pairOf($token,WBNB);$wusd=wbnbUsd();if($p&&$wusd){try{$m=pairMeta($p);$r=reserves($p,$m);$is0=low($m['token0']['address'])===$k;$x=$is0?$r['r0']:$r['r1'];$w=$is0?$r['r1']:$r['r0'];$liq=2*$w*$wusd;if($x>0&&$w>0&&$liq>=ORACLE_MIN_LIQ_USD)return $memo[$k]=($w/$x)*$wusd;}catch(Throwable $e){}}return $memo[$k]=null;}
function wbnbUsd():?float{static $v=false,$set=false;if($set)return $v;$set=true;try{$m=pairMeta(WBNB_USDT_PAIR);$r=reserves(WBNB_USDT_PAIR,$m);$a0=low($m['token0']['address']);$a1=low($m['token1']['address']);if($a0===low(WBNB)&&$a1===low(USDT)&&$r['r0']>0)return $v=$r['r1']/$r['r0'];if($a1===low(WBNB)&&$a0===low(USDT)&&$r['r1']>0)return $v=$r['r0']/$r['r1'];}catch(Throwable $e){}return null;}
function priceForPair(array $m,array $r):array{$a0=low($m['token0']['address']);$a1=low($m['token1']['address']);$s0=in_array($a0,[low(USDT),low(USDC)],true);$s1=in_array($a1,[low(USDT),low(USDC)],true);$u0=usdRef($m['token0']['address']);$u1=usdRef($m['token1']['address']);$ratio=$r['r0']>0&&$r['r1']>0?$r['r1']/$r['r0']:null;$price=null;$liq=null;
  if($s0&&!$s1&&$ratio!==null)$price=1/$ratio;elseif($s1&&!$s0&&$ratio!==null)$price=$ratio;elseif($s0&&$s1)$price=1;elseif($u1!==null&&$ratio!==null)$price=$ratio*$u1;elseif($u0!==null&&$u1!==null)$price=$u0;elseif($u0!==null&&$ratio!==null){$u1=$u0/$ratio;$price=$u0;}
  if($s0&&$r['r0']>0)$liq=2*$r['r0'];elseif($s1&&$r['r1']>0)$liq=2*$r['r1'];elseif($u0!==null&&$u0>0&&$r['r0']>0)$liq=2*$r['r0']*$u0;elseif($u1!==null&&$u1>0&&$r['r1']>0)$liq=2*$r['r1']*$u1;
  return ['pairRatio'=>$ratio,'priceUsd'=>$price,'liquidityUsd'=>$liq,'usd0'=>$u0,'usd1'=>$u1];}
function eventDecode(array $log,string $type):array{$d=preg_replace('/^0x/i','',$log['data']??'');$w=[];for($i=0;$i<strlen($d);$i+=64)$w[]=substr($d,$i,64);if($type==='SYNC')return ['r0'=>$w[0]??str_repeat('0',64),'r1'=>$w[1]??str_repeat('0',64)];if($type==='SWAP')return ['a0i'=>$w[0]??str_repeat('0',64),'a1i'=>$w[1]??str_repeat('0',64),'a0o'=>$w[2]??str_repeat('0',64),'a1o'=>$w[3]??str_repeat('0',64)];if($type==='MINT'||$type==='BURN')return ['a0'=>$w[0]??str_repeat('0',64),'a1'=>$w[1]??str_repeat('0',64)];return [];}
function typeOf(string $topic):?string{$t=low($topic);return $t===SYNC_TOPIC?'SYNC':($t===SWAP_TOPIC?'SWAP':($t===MINT_TOPIC?'MINT':($t===BURN_TOPIC?'BURN':null)));}
function pairFile(string $a):string{return ROOT.'/pairs/'.low($a).'.json';} function activityFile(string $a):string{return ROOT.'/activity/'.low($a).'.json';} function candleFile(string $a,string $range):string{return ROOT.'/candles/'.low($a).'/'.$range.'.json';}
function bucketSize(string $range):int{return ['1H'=>300,'4H'=>900,'1D'=>3600,'1W'=>14400,'1M'=>86400][$range]??3600;}
function aggregateCandles(array $syncs,string $range):array{$size=bucketSize($range);$out=[];foreach($syncs as $x){$t=(int)$x['timestamp'];$p=(float)$x['price'];if($p<=0)continue;$b=intdiv($t,$size)*$size;$k=(string)$b;if(!isset($out[$k]))$out[$k]=['time'=>$b,'open'=>$p,'high'=>$p,'low'=>$p,'close'=>$p];else{$out[$k]['high']=max($out[$k]['high'],$p);$out[$k]['low']=min($out[$k]['low'],$p);$out[$k]['close']=$p;}}ksort($out,SORT_NUMERIC);return array_values($out);}
function currentPairSnapshot(string $address):array{
  ensureDirs();
  $meta=pairMeta($address);
  $canonical=pairOf($meta['token0']['address'],$meta['token1']['address']);
  if(!$canonical||low($canonical)!==low($address)) throw new RuntimeException('Pair is not a canonical RECEH DEX factory pair');
  $latest=max(0,hxint(rpc('eth_blockNumber',[]))-CONFIRMATIONS);
  $r=reserves($address,$meta);
  $old=readj(pairFile($address),[]);
  $calc=priceForPair($meta,$r);
  $metrics=$old['metrics']??[];
  $state=$old?:['schema'=>1,'address'=>$address,'token0'=>$meta['token0'],'token1'=>$meta['token1'],'events'=>[],'firstBlock'=>max(0,$latest-INITIAL_BLOCKS),'lastBlock'=>max(0,$latest-INITIAL_BLOCKS-1)];
  $state['token0']=$meta['token0'];$state['token1']=$meta['token1'];$state['block']=$latest;
  $state['reserves']=['r0'=>$r['r0'],'r1'=>$r['r1'],'ts'=>$r['ts'],'raw0'=>$r['raw0'],'raw1'=>$r['raw1']];
  $state['metrics']=['priceUsd'=>$calc['priceUsd'],'pairRatio'=>$calc['pairRatio'],'liquidityUsd'=>$calc['liquidityUsd'],'volume24hUsd'=>$metrics['volume24hUsd']??0,'transactions24h'=>$metrics['transactions24h']??0,'usd0'=>$calc['usd0'],'usd1'=>$calc['usd1']];
  $state['updatedAt']=time();
  writej(pairFile($address),$state);
  return $state;
}
function syncPair(string $address,bool $force=false):array{ensureDirs();$file=pairFile($address);$old=readj($file,null);$now=time();if(!$force&&$old&&isset($old['updatedAt'])&&$now-(int)$old['updatedAt']<PAIR_TTL)return $old;$lock=lockFile(ROOT.'/locks_'.low($address).'.lock');if(!$lock){return $old?:throw new RuntimeException('Pair refresh is busy and no cached pair exists');}try{
  $meta=pairMeta($address);$canonical=pairOf($meta['token0']['address'],$meta['token1']['address']);if(!$canonical||low($canonical)!==low($address))throw new RuntimeException('Pair is not a canonical RECEH DEX factory pair');$latest=max(0,hxint(rpc('eth_blockNumber',[]))-CONFIRMATIONS);$r=reserves($address,$meta);$state=$old?:['schema'=>1,'address'=>$address,'token0'=>$meta['token0'],'token1'=>$meta['token1'],'events'=>[],'firstBlock'=>max(0,$latest-INITIAL_BLOCKS),'lastBlock'=>max(0,$latest-INITIAL_BLOCKS-1)];$from=(int)($state['lastBlock']??$state['firstBlock']-1)+1;
  /*
     Keep two cursors:
     - lastBlock: historical backfill cursor
     - recentLastBlock: rolling recent window cursor
     This guarantees a new swap is visible immediately even while the
     shared JSON is still backfilling older history.
  */
  $events=$state['events']??[];
  $existing=[];foreach($events as $e)$existing[$e['key']??'']=1;

  $recentFrom=max((int)($state['firstBlock']??0),$latest-RECENT_SCAN_BLOCKS);
  $recent=scanEventsSafe($address,$recentFrom,$latest);
  $scanError=$recent['error']??null;
  $rawLogs=$recent['events']??[];

  /* Continue historical backfill in small bounded increments. */
  $historyFrom=(int)($state['lastBlock']??((int)($state['firstBlock']??$recentFrom)-1))+1;
  $historyTo=min($latest,$historyFrom+BACKFILL_BLOCKS_PER_SYNC-1);
  if($historyFrom<=$historyTo && $historyFrom<$recentFrom){
    $hist=scanEventsSafe($address,$historyFrom,$historyTo);
    $rawLogs=array_merge($rawLogs,$hist['events']??[]);
    if($hist['complete'])$state['lastBlock']=$historyTo;
    elseif(isset($hist['lastBlock']))$state['lastBlock']=$hist['lastBlock'];
    if($hist['error'])$scanError=$scanError?($scanError.' | '.$hist['error']):$hist['error'];
  }

  $blocks=[];
  foreach($rawLogs as $log)$blocks[$log['blockNumber']??'0x0']=$log['blockNumber']??'0x0';
  $timestamps=blockTsMany(array_values($blocks));
  foreach($rawLogs as $log){
    $type=typeOf($log['topics'][0]??'');if(!$type)continue;
    $key=($log['transactionHash']??'').':'.($log['logIndex']??'');if(isset($existing[$key]))continue;
    $bk=$log['blockNumber']??'0x0';$ts=(int)($timestamps[low($bk)]??0);
    if($ts<=0){try{$ts=blockTs($bk);}catch(Throwable $ignore){$ts=0;}}
    $ev=eventDecode($log,$type);
    $events[]=['key'=>$key,'type'=>$type,'block'=>hxint($bk),'timestamp'=>$ts,'hash'=>$log['transactionHash']??'','logIndex'=>hxint($log['logIndex']??'0x0'),'data'=>$ev];
    $existing[$key]=1;
  }
  usort($events,fn($a,$b)=>($a['block']<=>$b['block'])?:($a['logIndex']<=>$b['logIndex']));
  $state['events']=$events;
  $state['recentLastBlock']=$latest;
  if($scanError)$state['syncError']=$scanError;else unset($state['syncError']);
  $cut=time()-HISTORY_DAYS*86400;$state['events']=array_values(array_filter($state['events']??[],fn($e)=>((int)($e['timestamp']??0)===0)||((int)$e['timestamp']>=$cut)));$calc=priceForPair($meta,$r);$syncs=[];$acts=[];$volume24=0.0;$tx24=0;$stable0=in_array(low($meta['token0']['address']),[low(USDT),low(USDC)],true);$stable1=in_array(low($meta['token1']['address']),[low(USDT),low(USDC)],true);foreach($state['events'] as $e){$t=(int)$e['timestamp'];if($e['type']==='SYNC'){ $x=human('0x'.$e['data']['r0'],$meta['token0']['decimals']);$y=human('0x'.$e['data']['r1'],$meta['token1']['decimals']);if($x>0&&$y>0){$raw=$y/$x;
      /* Stable pairs are represented in USD. Non-stable pairs MUST use the
         exact token0/token1 ratio from Sync; requiring a USD oracle here made
         WBNB/MEGAH candles disappear whenever the oracle was unavailable. */
      $p=$stable1?$raw:($stable0?1/$raw:$raw);
      if($p>0)$syncs[]=['timestamp'=>$t,'block'=>$e['block'],'price'=>$p];}}
    elseif($e['type']==='SWAP'){if($t>=time()-86400){$d=$e['data'];$a0=human('0x'.$d['a0i'],$meta['token0']['decimals']);$a1=human('0x'.$d['a1i'],$meta['token1']['decimals']);$o0=human('0x'.$d['a0o'],$meta['token0']['decimals']);$o1=human('0x'.$d['a1o'],$meta['token1']['decimals']);$v=0.0;if($stable0)$v=$a0>0?$a0:$o0;elseif($stable1)$v=$a1>0?$a1:$o1;elseif($calc['usd0']!==null&&$a0>0)$v=$a0*$calc['usd0'];elseif($calc['usd1']!==null&&$a1>0)$v=$a1*$calc['usd1'];$volume24+=$v;$tx24++;$parts=[];if($a0>0)$parts[]='+' . fmt($a0) . ' '.$meta['token0']['symbol'];if($a1>0)$parts[]='+' . fmt($a1) . ' '.$meta['token1']['symbol'];if($o0>0)$parts[]='→ '.fmt($o0).' '.$meta['token0']['symbol'];if($o1>0)$parts[]='→ '.fmt($o1).' '.$meta['token1']['symbol'];$acts[]=['type'=>'SWAP','timestamp'=>$t,'block'=>$e['block'],'hash'=>$e['hash'],'text'=>implode(' ',$parts),'volumeUsd'=>$v];}}
    elseif($e['type']==='MINT'||$e['type']==='BURN'){if($t>=time()-86400){$d=$e['data'];$a0=human('0x'.$d['a0'],$meta['token0']['decimals']);$a1=human('0x'.$d['a1'],$meta['token1']['decimals']);$acts[]=['type'=>$e['type']==='MINT'?'ADD LIQUIDITY':'REMOVE LIQUIDITY','timestamp'=>$t,'block'=>$e['block'],'hash'=>$e['hash'],'text'=>fmt($a0).' '.$meta['token0']['symbol'].' + '.fmt($a1).' '.$meta['token1']['symbol']];}}}
  $state['updatedAt']=$now;$state['block']=$latest;$state['reserves']=['r0'=>$r['r0'],'r1'=>$r['r1'],'ts'=>$r['ts'],'raw0'=>$r['raw0'],'raw1'=>$r['raw1']];$state['metrics']=['priceUsd'=>$calc['priceUsd'],'pairRatio'=>$calc['pairRatio'],'liquidityUsd'=>$calc['liquidityUsd'],'volume24hUsd'=>$volume24,'transactions24h'=>$tx24,'usd0'=>$calc['usd0'],'usd1'=>$calc['usd1']];$state['syncHistory']=$syncs;writej($file,$state);writej(activityFile($address),array_values(array_reverse($acts)));foreach(['1H','4H','1D','1W','1M'] as $range)writej(candleFile($address,$range),aggregateCandles($syncs,$range));return $state;
 }finally{unlockFile($lock);}}
function refreshMarkets(bool $force=false):array{ensureDirs();$file=ROOT.'/markets.json';$old=readj($file,['schema'=>1,'updatedAt'=>0,'block'=>0,'markets'=>[]]);if(!$force&&($old['updatedAt']??0)&&time()-(int)$old['updatedAt']<MARKET_TTL)return $old;$lock=lockFile(ROOT.'/markets.lock');if(!$lock)return $old;try{$latest=max(0,hxint(rpc('eth_blockNumber',[]))-CONFIRMATIONS);$len=callUint(FACTORY,'allPairsLength()');$start=max(0,$len-MAX_MARKETS);$markets=[];$tokens=[];for($i=$start;$i<$len;$i++){try{$p=callAddr(FACTORY,'allPairs(uint256)',pad32(dechex($i)));$m=pairMeta($p);$r=reserves($p,$m);if($r['r0']<=0||$r['r1']<=0)continue;$calc=priceForPair($m,$r);$cached=readj(pairFile($p),[]);$metrics=$cached['metrics']??[];$markets[]=['index'=>$i,'address'=>$p,'token0'=>$m['token0'],'token1'=>$m['token1'],'reserves'=>['r0'=>$r['r0'],'r1'=>$r['r1'],'ts'=>$r['ts']],'pairRatio'=>$calc['pairRatio'],'priceUsd'=>$calc['priceUsd'],'liquidityUsd'=>$calc['liquidityUsd'],'volume24hUsd'=>$metrics['volume24hUsd']??0,'transactions24h'=>$metrics['transactions24h']??0,'updatedAt'=>$now=time()];}catch(Throwable $e){continue;}}$out=['schema'=>2,'updatedAt'=>time(),'block'=>$latest,'pairCount'=>$len,'markets'=>$markets,'source'=>'BSC_FACTORY_ONCHAIN'];writej($file,$out);return $out;}finally{unlockFile($lock);}}
function fmt(float $v,int $d=6):string{return rtrim(rtrim(number_format($v,$d,'.',''),'0'),'.');}
