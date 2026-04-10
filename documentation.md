# RECEH DEX — Documentation

## Overview
RECEH DEX is a fully decentralized exchange (DEX) operating on **BNB Smart Chain** and **Riche Chain**, built using **AMM (Automated Market Maker) V2** architecture. It enables token swaps, liquidity provision, real-time analytics, and permissionless token listings.

This documentation explains how the protocol works, how users can trade, how developers can integrate the router, and how project owners can list tokens.

---

## Core Features

### 🔹 AMM V2 Architecture
- Constant product formula: `x * y = k`
- Direct smart contract interaction  
- Fast and deterministic pricing  

### 🔹 Multi-Chain Support
- BNB Smart Chain
- Riche Chain
- CoreDAO

### 🔹 Permissionless Listing
Anyone can list any token pair without central approval.

### 🔹 Real-Time Analytics
- Price charts  
- Volume  
- Liquidity metrics  
- Transaction feed  

### 🔹 Liquidity Pools
Earn trading fees by contributing to liquidity pools (LP).

---

## Contract Addresses

### **BNB Smart Chain**
- **Factory:** `0x8E9556415124b6C726D5C3610d25c24Be8AC2304`  
- **Router:** `0xA131F04149CFA29b3f05d361EA807e737C9b1D95`  
- **Wrapped BNB (WBNB):** `0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c`  
- **Example Pair:** USDT/RECEH

---

## How RECEH DEX Works

### 🔹 AMM Constant Product Formula
RECEH DEX uses the Uniswap V2 formula:

```
x * y = k
```

Where:  
- `x` = token A liquidity  
- `y` = token B liquidity  
- `k` = constant  

Prices adjust automatically based on pool balance.

---

## Fee Structure

| Component      | Percentage |
|----------------|------------|
| Trading Fee    | 0.25%      |
| LP Share       | 0.17%      |
| Protocol Fee   | 0.08% or Disabled |

Modify these numbers if your configuration differs.

---

## Getting Started

### 🟦 Connecting a Wallet
Supported wallets:
- MetaMask  
- Trust Wallet  
- Core Wallet  
- Bitget Wallet  
- WalletConnect clients  

Steps:
1. Visit: `https://dex.cryptoreceh.com/`
2. Click **Connect Wallet**
3. Select your wallet
4. Choose **BNB Chain** or **Riche Chain** network

---

## Swapping Tokens

### How to Swap
1. Open the **Swap** page  
2. Select tokens  
3. Enter amount  
4. Adjust slippage if necessary  
5. Approve token (first time)  
6. Confirm transaction  

### Example (Developer – JavaScript)
```javascript
const amounts = await router.getAmountsOut(
    amountIn,
    [TOKEN_IN, TOKEN_OUT]
);
```

---

## Providing Liquidity

### Adding Liquidity
1. Go to **Liquidity**
2. Select pair or create a new one  
3. Deposit equal-value amounts  
4. Approve tokens  
5. Confirm  

You will receive **LP Tokens**, representing your pool share.

### Removing Liquidity
1. Open **Your Liquidity**  
2. Select position  
3. Choose amount  
4. Confirm removal  

---

## Creating Trading Pairs
Anyone can create a pair.

Steps:  
1. Go to: `https://dex.cryptoreceh.com/`  
2. Select token A + token B  
3. Provide initial liquidity  
4. Approve & confirm  

A new pair contract will auto-deploy.

---

## Listing Tokens on RECEH DEX

### Requirements
- Token must follow **BEP-20 (BSC)** or **ERC-20 (RicheChain)**  
- Contract must be verified  
- Liquidity must be added by the project owner  

### Optional Submission
Projects may request UI/Analytics listing.

**Listing Request:**  
Email → **support@cryptoreceh.com**

---

## Analytics Dashboard
Provides:
- Live price charts  
- 24h volume  
- Liquidity metrics  
- Transaction feed  
- Token info  

Access: `https://dex.cryptoreceh.com/info/`

---

## Developer Documentation

### 🔹 Router Functions

#### Swap
```javascript
router.swapExactTokensForTokens(
  amountIn,
  amountOutMin,
  [TOKEN_IN, TOKEN_OUT],
  userAddress,
  deadline
);
```

#### Add Liquidity
```javascript
router.addLiquidity(
  tokenA,
  tokenB,
  amountA,
  amountB,
  0,
  0,
  userAddress,
  deadline
);
```

### 🔹 Factory Functions

#### Create Pair
```javascript
factory.createPair(tokenA, tokenB);
```

#### Get Pair
```javascript
factory.getPair(tokenA, tokenB);
```

---

## Security Considerations
- Smart contracts should be audited  
- Users control their own funds  
- AMM trading carries risks:  
  - impermanent loss  
  - volatility  
- No centralized control over user balances  

---

## Roadmap
- Liquidity farming  
- Staking  
- Governance  
- Cross-chain swaps  
- Launchpad  
- Mobile DEX App  

---

## Contact Information
- Website: https://cryptoreceh.com  
- DEX App: https://dex.cryptoreceh.com/
- Pair Info: https://cryptoreceh.com/dex/info/ 
- Telegram: https://t.me/cryptorecehcom  
- X/Twitter: https://x.com/cryptorecehcom  
- Email: support@cryptoreceh.com

- DEX Logo: https://raw.githubusercontent.com/cryptorecehcom/receh-dex/refs/heads/main/images/receh-logo.png

---

## Legal Disclaimer
RECEH DEX is a decentralized protocol.  
Users interact at their own risk.  
There are no guarantees regarding token value, project legitimacy, or market performance.  
No funds are stored or controlled by the team.

