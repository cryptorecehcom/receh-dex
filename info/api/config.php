<?php
declare(strict_types=1);
const RPCS = [
  'https://bsc-rpc.publicnode.com',
  'https://bsc-dataseed1.defibit.io',
  'https://bsc-dataseed1.ninicoin.io',
  'https://bsc-dataseed.bnbchain.org'
];
const FACTORY='0x8E9556415124b6C726D5C3610d25c24Be8AC2304';
const WBNB='0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c';
const USDT='0x55d398326f99059fF775485246999027B3197955';
const USDC='0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d';
const RECEH='0x4c9C431Fa7fD104c0E7230d20E1623E62019A1C5';
const MEGAH='0xc55d416476CFC6e879948eD5a5F4461c43Af45Aa';
const WBNB_USDT_PAIR='0x16b9a82891338f9bA80E2D6970FddA79D1eb0daE';
const EXPLORER='https://bscscan.com';
const DEX='https://dex.cryptoreceh.com/';
const ROOT=__DIR__.'/../data';
const MAX_MARKETS=5000;
const MARKET_TTL=60;
const PAIR_TTL=15;
const HISTORY_DAYS=30;
const INITIAL_BLOCKS=10000; // bounded first sync; subsequent visits advance the shared JSON cursor
const LOG_CHUNK=100;
const RECENT_SCAN_BLOCKS=800;
const BACKFILL_BLOCKS_PER_SYNC=300;
const CONFIRMATIONS=3;
const ORACLE_MIN_LIQ_USD=100.0;
const SYNC_TOPIC='0x1c411e9a96e071241c2f21f7726b17ae89e3cab4c78be50e062b03a9fffbbad1';
const SWAP_TOPIC='0xd78ad95fa46c994b6551d0da85fc275fe613ce37657fb8d5e3d130840159d822';
const MINT_TOPIC='0x4c209b5fc8ad50758f13e2e1088ba56a560dff690a1c6fef26394f4c03821c4f';
const BURN_TOPIC='0xdccd412f0b1252819cb1fd330b93224ca42612892bb3f4f789976e6d81936496';
