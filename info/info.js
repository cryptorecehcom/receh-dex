(() => {
  "use strict";
  const CFG = {
    chainId: 56,
    explorer: "https://bscscan.com",
    dex: "https://dex.cryptoreceh.com/",
    factory: "0x8E9556415124b6C726D5C3610d25c24Be8AC2304",
    wbnb: "0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c",
    usdt: "0x55d398326f99059fF775485246999027B3197955",
    usdc: "0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d",
    receh: "0x4c9C431Fa7fD104c0E7230d20E1623E62019A1C5",
    megah: "0xc55d416476CFC6e879948eD5a5F4461c43Af45Aa",
  };
  const E = ethers;
  const BUILTIN_LOGOS = {
    [CFG.wbnb.toLowerCase()]:
      "https://raw.githubusercontent.com/trustwallet/assets/master/blockchains/smartchain/assets/0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c/logo.png",
    [CFG.usdt.toLowerCase()]:
      "https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/USDT.webp",
    [CFG.usdc.toLowerCase()]:
      "https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/USDC.webp",
    [CFG.receh.toLowerCase()]:
      "https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/RECEH.webp",
    [CFG.megah.toLowerCase()]:
      "https://raw.githubusercontent.com/recehdex/token-logo/refs/heads/main/MEGAH.webp",
  };
  let pairs = [],
    projects = {},
    selected = null,
    currentRange = "1D",
    chartInstance = null,
    chartResizeObserver = null,
    liveTimer = null,
    marketTimer = null,
    chartRenderSeq = 0;
  const $ = (id) => document.getElementById(id),
    low = (x) => String(x ?? "").toLowerCase(),
    short = (a) => (a ? `${a.slice(0, 6)}…${a.slice(-4)}` : "—"),
    esc = (s) =>
      String(s ?? "").replace(
        /[&<>'"]/g,
        (c) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "'": "&#39;",
            '"': "&quot;",
          })[c],
      ),
    finite = (x) => Number.isFinite(Number(x));
  const num = (x, d = 12) => {
    const n = Number(x);
    if (!Number.isFinite(n)) return "—";
    return n.toLocaleString(undefined, {
      maximumFractionDigits: d,
      useGrouping: true,
    });
  };
  const usd = (x) => {
    const n = Number(x);
    if (!Number.isFinite(n)) return "—";
    if (n >= 1e9) return "$" + num(n / 1e9, 2) + "B";
    if (n >= 1e6) return "$" + num(n / 1e6, 2) + "M";
    if (n >= 1e3) return "$" + num(n / 1e3, 2) + "K";
    if (n >= 1) return "$" + num(n, 6);
    if (n >= 0.01) return "$" + num(n, 8);
    if (n > 0) return "$" + n.toFixed(12).replace(/0+$/, "").replace(/\.$/, "");
    return "$0";
  };
  function api(path) {
    return fetch("api/index.php?" + path, { cache: "no-store" }).then(
      async (r) => {
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.error || `HTTP ${r.status}`);
        return d;
      },
    );
  }
  function setProgress(t, s, p) {
    if ($("bootText")) $("bootText").textContent = t;
    if ($("bootSub")) $("bootSub").textContent = s;
    if ($("bootProgress"))
      $("bootProgress").style.width = Math.max(4, Math.min(100, p)) + "%";
  }
  function hideBoot() {
    document.body.classList.add("ready");
    setTimeout(() => $("bootScreen")?.remove(), 450);
  }
  function tokenLogo(t) {
    return t?.logoURI || t?.logo || BUILTIN_LOGOS[low(t?.address)] || "";
  }
  function img(t, size = "mini") {
    const src = tokenLogo(t),
      sym = esc(t?.symbol || "?"),
      large = size === "large",
      fallback = `<span class="fallbackLogo ${large ? "large" : ""}">${sym.slice(0, 1)}</span>`;
    return `<span class="logoWrap ${large ? "large" : ""}">${src ? `<img class="tokenLogo ${large ? "large" : ""}" src="${esc(src)}" alt="${sym}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">` : ""}${src ? fallback : fallback}</span>`;
  }
  function pairLabel(p) {
    return `${p?.token0?.symbol || "?"} / ${p?.token1?.symbol || "?"}`;
  }
  function matches(p, q) {
    return (
      q === "all" || [p.token0?.symbol, p.token1?.symbol].map(low).includes(q)
    );
  }
  async function loadProjects() {
    try {
      const r = await fetch("projects.json", { cache: "no-store" });
      const d = await r.json();
      projects = {};
      Object.entries(d || {}).forEach(
        ([k, v]) => (projects[low(k)] = { ...v }),
      );
    } catch {
      projects = {};
    }
  }
  function projectHTML(m, t) {
    const contract = m.contract || t?.address,
      logo = m.logo || tokenLogo(t),
      link = (k, l, h) =>
        h
          ? `<a class="projectLink ${k}" href="${esc(h)}" target="_blank" rel="noopener noreferrer"><span class="linkIcon">${k === "x" ? "𝕏" : k === "telegram" ? "➤" : "◎"}</span><span>${esc(l)}</span><span class="linkArrow">↗</span></a>`
          : "";
    return `<div class="projectTop">${logo ? `<span class="projectLogo"><img src="${esc(logo)}" alt="${esc(m.symbol || t?.symbol || "")}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='grid'"><span class="fallbackLogo large" style="display:none">${esc((m.symbol || t?.symbol || "?").slice(0, 1))}</span></span>` : img(t, "large")}<div><h3>${esc(m.name || t?.name || t?.symbol || "Project")}</h3><span>${esc(m.symbol || t?.symbol || "")}</span></div></div><p>${esc(m.description || "")}</p><div class="projectFacts"><div><span>CONTRACT</span><a href="${CFG.explorer}/token/${esc(contract)}" target="_blank" rel="noopener noreferrer">${esc(short(contract))} ↗</a></div><div><span>NETWORK</span><strong>BNB Smart Chain · BEP-20</strong></div><div><span>VERIFICATION</span><strong class="verifiedText">${m.verified ? "Verified source information" : "Not verified"}</strong></div></div><div class="projectLinks">${link("website", "Website", m.website)}${link("x", "X", m.x)}${link("telegram", "Telegram", m.telegram)}</div>`;
  }
  function genericTokenHTML(t) {
    return `<div class="projectTop">${img(t, "large")}<div><h3>${esc(t?.name || t?.symbol || "Token")}</h3><span>${esc(t?.symbol || "")}</span></div></div><p>On-chain token metadata from the BNB Smart Chain pair index. No verified project profile has been supplied for this token.</p><div class="projectFacts"><div><span>CONTRACT</span><a href="${CFG.explorer}/token/${esc(t?.address || "")}" target="_blank" rel="noopener">${esc(short(t?.address))} ↗</a></div><div><span>NETWORK</span><strong>BNB Smart Chain · BEP-20</strong></div><div><span>VERIFICATION</span><strong>Not verified</strong></div></div>`;
  }
  function chooseProject(p) {
    return projects[low(p.token0.address)]
      ? [projects[low(p.token0.address)], p.token0]
      : projects[low(p.token1.address)]
        ? [projects[low(p.token1.address)], p.token1]
        : [null, p.token0];
  }
  function tradeUrl(input, output) {
    return `${CFG.dex}?inputCurrency=${encodeURIComponent(input.address)}&outputCurrency=${encodeURIComponent(output.address)}`;
  }
  function chooseTrade(p, meta) {
    let base = meta?.address
      ? { address: meta.address, symbol: meta.symbol }
      : [CFG.usdt, CFG.usdc, CFG.wbnb].map(low).includes(low(p.token0.address))
        ? p.token1
        : p.token0;
    let quote =
      low(base.address) === low(CFG.receh)
        ? low(p.token0.address) === low(CFG.wbnb)
          ? p.token0
          : p.token1
        : [CFG.usdt, CFG.usdc, CFG.wbnb]
              .map(low)
              .includes(low(p.token0.address))
          ? p.token0
          : p.token1;
    return { base, quote };
  }
  async function loadMarkets() {
    setProgress("Loading markets", "Reading shared BSC on-chain JSON…", 35);
    const d = await api("action=markets");
    pairs = Array.isArray(d.markets) ? d.markets : [];
    $("updated").textContent = d.updatedAt
      ? "SHARED ON-CHAIN DATA · " +
        new Date(d.updatedAt * 1000).toLocaleTimeString()
      : "INDEX READY";
    setProgress(
      "Markets ready",
      `${pairs.length} markets from the shared server index`,
      100,
    );
    return d;
  }
  function renderMarkets() {
    const q = low($("search").value.trim()),
      filter =
        document.querySelector("#filters button.active")?.dataset.q || "all",
      sort = $("sort").value;
    let a = pairs.filter(
      (p) =>
        matches(p, filter) &&
        (!q ||
          low(pairLabel(p)).includes(q) ||
          low(p.token0.address).includes(q) ||
          low(p.token1.address).includes(q)),
    );
    a.sort((x, y) =>
      sort === "price"
        ? (Number(y.priceUsd) || 0) - (Number(x.priceUsd) || 0)
        : sort === "volume"
          ? (Number(y.volume24hUsd) || 0) - (Number(x.volume24hUsd) || 0)
          : (Number(y.liquidityUsd) || 0) - (Number(x.liquidityUsd) || 0),
    );
    $("marketStatus").textContent =
      `${a.length} market${a.length === 1 ? "" : "s"} · ${pairs.length} total`;
    $("marketList").innerHTML = a.length
      ? a
          .map((p) => {
            const price = finite(p.priceUsd)
              ? usd(p.priceUsd)
              : finite(p.pairRatio)
                ? num(p.pairRatio, 14)
                : "—";
            return `<button class="market-row" type="button" data-pair="${esc(p.address)}"><span class="pairCell"><span class="miniLogos">${img(p.token0)}${img(p.token1)}</span><span><strong>${esc(pairLabel(p))}</strong><small>${esc(short(p.address))}</small></span></span><span class="marketPrice">${price}</span><span>${finite(p.liquidityUsd) ? usd(p.liquidityUsd) : "—"}</span><span>${Number(p.volume24hUsd) > 0 ? usd(p.volume24hUsd) : "—"}</span></button>`;
          })
          .join("")
      : '<div class="empty">No markets are currently available from the shared BSC index.</div>';
  }
  async function openDetail(address) {
    $("marketsView").classList.add("hidden");
    $("detailView").classList.remove("hidden");
    $("detailLoading").classList.remove("hidden");
    $("detailContent").classList.add("hidden");
    if (marketTimer) clearInterval(marketTimer);
    $("detailLoading").innerHTML =
      '<div class="spinner"></div><strong>Loading market</strong><span>Reading current pair state…</span>';
    try {
      const d = await api("action=pair&address=" + encodeURIComponent(address));
      selected = d;
      pairs = [...pairs.filter((p) => low(p.address) !== low(address)), d];
      history.replaceState(null, "", "?pair=" + encodeURIComponent(address));
      renderDetail(d);
      startLive();
      await Promise.all([renderChart(d), renderActivity(d)]);
      $("detailLoading").classList.add("hidden");
      $("detailContent").classList.remove("hidden");
    } catch (e) {
      console.error(e);
      $("detailLoading").innerHTML =
        `<strong>Unable to load pair data.</strong><span>${esc(e.message)}</span>`;
    }
  }
  function renderDetail(p) {
    const [meta, projectToken] = chooseProject(p),
      m = meta || null;
    $("pairName").textContent = pairLabel(p);
    $("pairAddresses").textContent = `${p.token0.name} · ${p.token1.name}`;
    $("pairLogos").innerHTML = img(p.token0, "large") + img(p.token1, "large");
    $("verifiedBadge").classList.toggle("hidden", !m?.verified);
    $("detailPrice").textContent = finite(p.metrics?.priceUsd)
      ? usd(p.metrics.priceUsd)
      : "—";
    $("detailChange").textContent = "SHARED ON-CHAIN DATA";
    $("sPrice").textContent = finite(p.metrics?.priceUsd)
      ? usd(p.metrics.priceUsd)
      : "—";
    $("sLiquidity").textContent = finite(p.metrics?.liquidityUsd)
      ? usd(p.metrics.liquidityUsd)
      : "—";
    $("sVolume").textContent =
      Number(p.metrics?.volume24hUsd) > 0 ? usd(p.metrics.volume24hUsd) : "—";
    $("sTxns").textContent =
      Number(p.metrics?.transactions24h) > 0
        ? num(p.metrics.transactions24h, 0)
        : "0";
    $("reserves").innerHTML =
      `<div><span>${esc(p.token0.symbol)}</span><strong>${num(p.reserves?.r0, 8)}</strong></div><div><span>${esc(p.token1.symbol)}</span><strong>${num(p.reserves?.r1, 8)}</strong></div>`;
    $("pairLink").href = CFG.explorer + "/address/" + p.address;
    $("pairLink").textContent = short(p.address);
    $("project").innerHTML = m
      ? projectHTML(m, projectToken)
      : genericTokenHTML(projectToken);
    const t = chooseTrade(p, m);
    $("buyBtn").textContent = `BUY ${t.base.symbol}`;
    $("sellBtn").textContent = `SELL ${t.base.symbol}`;
    $("buyBtn").href = tradeUrl(t.quote, t.base);
    $("sellBtn").href = tradeUrl(t.base, t.quote);
  }
  function chartOptions() {
    return {
      autoSize: true,
      layout: {
        background: { type: "solid", color: "transparent" },
        textColor: "#9aa7b8",
        fontFamily: "JetBrains Mono",
      },
      grid: {
        vertLines: { color: "rgba(255,255,255,.035)" },
        horzLines: { color: "rgba(255,255,255,.035)" },
      },
      rightPriceScale: {
        borderColor: "rgba(255,255,255,.08)",
        scaleMargins: { top: 0.08, bottom: 0.12 },
      },
      timeScale: {
        borderColor: "rgba(255,255,255,.08)",
        timeVisible: true,
        secondsVisible: false,
      },
      crosshair: { mode: 1 },
      handleScroll: {
        mouseWheel: true,
        pressedMouseMove: true,
        horzTouchDrag: true,
        vertTouchDrag: false,
      },
      handleScale: {
        axisPressedMouseMove: true,
        mouseWheel: true,
        pinch: true,
      },
    };
  }
  async function renderChart(p) {
    const seq = ++chartRenderSeq,
      el = $("chart");
    if (chartResizeObserver) {
      chartResizeObserver.disconnect();
      chartResizeObserver = null;
    }
    if (chartInstance) {
      try {
        chartInstance.remove();
      } catch {}
      chartInstance = null;
    }
    el.innerHTML = "";
    try {
      const d = await api(
        "action=candles&pair=" +
          encodeURIComponent(p.address) +
          "&range=" +
          currentRange,
      );
      if (seq !== chartRenderSeq || selected !== p) return;
      const data = Array.isArray(d.data)
        ? d.data.filter(
            (x) =>
              [x.time, x.open, x.high, x.low, x.close].every(finite) &&
              Number(x.open) > 0 &&
              Number(x.high) > 0 &&
              Number(x.low) > 0 &&
              Number(x.close) > 0,
          )
        : [];
      const maxAbs = data.reduce(
        (m, x) => Math.max(m, Math.abs(Number(x.high) || 0)),
        0,
      );
      const precision =
        maxAbs > 1000
          ? 2
          : maxAbs > 100
            ? 3
            : maxAbs > 1
              ? 5
              : maxAbs > 0.01
                ? 8
                : 12;
      const chart = LightweightCharts.createChart(el, chartOptions());
      if (seq !== chartRenderSeq || selected !== p) {
        try {
          chart.remove();
        } catch {}
        return;
      }
      chartInstance = chart;
      const series = chart.addCandlestickSeries({
        upColor: "#19d7a3",
        downColor: "#ff5577",
        borderUpColor: "#19d7a3",
        borderDownColor: "#ff5577",
        wickUpColor: "#19d7a3",
        wickDownColor: "#ff5577",
        priceFormat: {
          type: "price",
          precision,
          minMove: Math.pow(10, -precision),
        },
      });
      series.setData(data);
      if (data.length) chart.timeScale().fitContent();
      $("chartNote").textContent =
        `${data.length} candles · ${currentRange} · exact token0/token1 ratio from confirmed BSC Sync events`;
      chartResizeObserver = new ResizeObserver(() => {
        if (seq === chartRenderSeq && chartInstance === chart) {
          try {
            chart.resize(el.clientWidth, Math.max(300, el.clientHeight));
          } catch {}
        }
      });
      chartResizeObserver.observe(el);
    } catch (e) {
      if (seq === chartRenderSeq)
        $("chartNote").textContent =
          "Shared candle JSON is temporarily unavailable.";
      console.debug("chart render skipped", e);
    }
  }
  async function renderActivity(p) {
    $("activity").innerHTML =
      '<div class="loadingLine"><span class="spinner small"></span>Loading shared on-chain activity…</div>';
    try {
      const d = await api(
          "action=activity&pair=" +
            encodeURIComponent(p.address) +
            "&range=" +
            currentRange,
        ),
        ev = Array.isArray(d.data) ? d.data : [];
      if (!ev.length) {
        $("activity").innerHTML =
          '<div class="empty">No confirmed pair events in the selected window.</div>';
        return;
      }
      $("activity").innerHTML = ev
        .map(
          (x) =>
            `<div class="activityRow"><span class="eventBadge ${x.type === "SWAP" ? "swap" : x.type === "ADD LIQUIDITY" ? "add" : "remove"}">${esc(x.type)}</span><span class="eventText">${esc(x.text)}</span><span class="eventTime">${esc(relative(Number(x.timestamp) * 1000))}</span><a href="${CFG.explorer}/tx/${esc(x.hash)}" target="_blank" rel="noopener">↗</a></div>`,
        )
        .join("");
    } catch (e) {
      $("activity").innerHTML =
        '<div class="empty">Shared activity data is temporarily unavailable.</div>';
    }
  }
  function relative(ms) {
    const s = Math.max(0, (Date.now() - ms) / 1000);
    if (s < 60) return Math.floor(s) + "s ago";
    if (s < 3600) return Math.floor(s / 60) + "m ago";
    if (s < 86400) return Math.floor(s / 3600) + "h ago";
    return Math.floor(s / 86400) + "d ago";
  }
  async function liveRefresh() {
    if (!selected) return;
    try {
      const d = await api(
        "action=pair&address=" + encodeURIComponent(selected.address),
      );
      selected = d;
      renderDetail(d);
    } catch (e) {
      console.debug("shared refresh skipped", e);
    }
  }
  let syncBusy = false;
  async function backgroundSync() {
    if (!selected || syncBusy) return;
    syncBusy = true;
    try {
      await api("action=sync&pair=" + encodeURIComponent(selected.address));
      const d = await api(
        "action=pair&address=" + encodeURIComponent(selected.address),
      );
      selected = d;
      renderDetail(d);
      await Promise.all([renderChart(d), renderActivity(d)]);
    } catch (e) {
      console.debug("shared index refresh skipped", e);
    } finally {
      syncBusy = false;
    }
  }
  function startLive() {
    if (liveTimer) clearInterval(liveTimer);
    setTimeout(backgroundSync, 200);
    liveTimer = setInterval(backgroundSync, 10000);
  }
  async function renderLiquidityHistory() {
    const el = $("liquidityChart");
    if (!el || !window.LightweightCharts) return;
    try {
      const d = await api("action=liquidity");
      const a = Array.isArray(d.data)
        ? d.data.filter((x) => finite(x.timestamp) && finite(x.liquidity))
        : [];
      if (!a.length) {
        $("liquidityNote").textContent =
          "No shared liquidity history has been accumulated yet.";
        return;
      }
      const c = LightweightCharts.createChart(el, {
        autoSize: true,
        layout: {
          background: { type: "solid", color: "transparent" },
          textColor: "#9aa7b8",
          fontFamily: "JetBrains Mono",
        },
        grid: {
          vertLines: { color: "rgba(255,255,255,.035)" },
          horzLines: { color: "rgba(255,255,255,.035)" },
        },
        timeScale: { timeVisible: true, secondsVisible: false },
      });
      const line = c.addLineSeries({ color: "#00c8ff", lineWidth: 2 });
      line.setData(
        a
          .sort((x, y) => x.timestamp - y.timestamp)
          .map((x) => ({
            time: Number(x.timestamp),
            value: Number(x.liquidity),
          })),
      );
      c.timeScale().fitContent();
      new ResizeObserver(() => c.resize(el.clientWidth, 260)).observe(el);
      $("liquidityNote").textContent =
        `${a.length} shared observations · latest ${usd(a[a.length - 1].liquidity)}`;
    } catch (e) {
      $("liquidityNote").textContent =
        "Shared liquidity history is temporarily unavailable.";
    }
  }
  function showMarkets() {
    if (liveTimer) clearInterval(liveTimer);
    selected = null;
    history.replaceState(null, "", location.pathname);
    $("detailView").classList.add("hidden");
    $("marketsView").classList.remove("hidden");
    renderMarkets();
    loadMarkets()
      .then((d) => {
        renderMarkets();
        renderLiquidityHistory();
      })
      .catch(() => {});
    if (marketTimer) clearInterval(marketTimer);
    marketTimer = setInterval(
      () =>
        loadMarkets()
          .then(renderMarkets)
          .catch(() => {}),
      30000,
    );
  }
  $("backBtn").onclick = showMarkets;
  $("search").oninput = renderMarkets;
  $("sort").onchange = renderMarkets;
  document.querySelectorAll("#filters button").forEach(
    (b) =>
      (b.onclick = () => {
        document
          .querySelectorAll("#filters button")
          .forEach((x) => x.classList.remove("active"));
        b.classList.add("active");
        renderMarkets();
      }),
  );
  document.querySelectorAll("#ranges button").forEach(
    (b) =>
      (b.onclick = async () => {
        document
          .querySelectorAll("#ranges button")
          .forEach((x) => x.classList.remove("active"));
        b.classList.add("active");
        currentRange = b.dataset.range;
        if (selected) {
          await renderChart(selected);
          await renderActivity(selected);
        }
      }),
  );
  document.addEventListener("click", (e) => {
    const row = e.target.closest(".market-row");
    if (row) openDetail(row.dataset.pair);
  });
  (async () => {
    try {
      await loadProjects();
      await loadMarkets();
      renderMarkets();
      renderLiquidityHistory();
      setProgress("Markets ready", "Shared server JSON · BNB Smart Chain", 100);
      const p = new URLSearchParams(location.search).get("pair");
      hideBoot();
      if (p && E.isAddress(p)) await openDetail(p);
      marketTimer = setInterval(
        () =>
          loadMarkets()
            .then(renderMarkets)
            .catch(() => {}),
        30000,
      );
    } catch (e) {
      console.error(e);
      $("marketStatus").textContent = "Shared index warming up";
      $("marketList").innerHTML =
        '<div class="empty">The shared BSC index is warming up. Open a pair or refresh shortly.</div>';
      hideBoot();
    }
  })();
})();
