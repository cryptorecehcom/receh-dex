# RECEH DEX PairInfo — BSC Shared JSON

Upload the contents of `info/` to `/info/` on the existing RECEH DEX host.

## Architecture
- No MySQL.
- Browser never writes price/liquidity/volume/candle values.
- PHP reads BNB Smart Chain, validates the factory pair, and writes shared JSON atomically.
- Every visitor can refresh the same server-side JSON.
- Per-pair lock prevents duplicate indexers; cached data is returned while another request is refreshing.

## Persistent files
- `data/markets.json`
- `data/pairs/<pair>.json`
- `data/candles/<pair>/{1H,4H,1D,1W,1M}.json`
- `data/activity/<pair>.json`
- `data/liquidity/total.json`

## Price rules
Stable pairs use the stablecoin side. WBNB valuation uses the canonical WBNB/USDT pair. Other USD values are shown only when a deterministic direct stable/WBNB reference exists. Otherwise the UI shows the exact pair ratio or `—` instead of inventing a USD value.

## Volume
Only BSC Swap events. No random/fake volume.

## Candles
Candles are aggregated from confirmed Sync events and persisted as JSON. They are not stored in browser localStorage and are not generated independently per user.
