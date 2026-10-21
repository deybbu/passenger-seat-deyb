
/* ---------- Config ---------- */
const CONFIG = {
  orderEndpoint: window.PS_ORDER_ENDPOINT,
  sessionEndpoint: window.PS_SESSION_ENDPOINT,
  billEndpoint: window.PS_BILL_ENDPOINT,
  billCancelEndpoint: window.PS_BILL_CANCEL_ENDPOINT
};

/* ---------- Menu data from the database ---------- */
const MENU = window.PS_MENU;
const PHOTOS = window.PS_PHOTOS;
const DETAILS = window.PS_DETAILS;
const INITIAL_MENU_ITEM_IDS = window.PS_MENU_ITEMS.map(item => Number(item.id));
const MENU_ITEMS_BY_NAME = new Map(window.PS_MENU_ITEMS.map(item => [item.name, item]));
const detail = it => MENU[cat][0] === "Boodle Flights"
  ? ["A shared boodle spread. " + it[3] + " people.", it[1].split(/, | and /).map(s => s.trim())]
  : DETAILS[it[0]] || ["", []];

/* ---------- Helpers & state ---------- */
const $ = s => document.querySelector(s);
const peso = n => "₱" + n.toLocaleString("en-PH");
const code = it => it[0].replace(/[^A-Za-z]/g, "").slice(0, 3).toUpperCase();
const optsOf = it => Array.isArray(it[2]) ? it[2] : [["", it[2]]];
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
const STEP = 90; // px of page scroll per dish
const DESK = matchMedia("(min-width: 900px)"); // desktop/website layout, otherwise phone layout
let confirmed = null, notesVal = "";

const ls = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} } };
const table = String(window.PS_TABLE_NUMBER);
const tableBtn = $("#tableNo");
const showTable = () => tableBtn.textContent = "Table " + table;
showTable();
let mop = null;
let session = { table, orders: [], bill: null };
try {
  const x = JSON.parse(ls.get("ps_session") || "null");
  if (x && x.table === table && Array.isArray(x.orders)) session = x;
} catch (e) {}
const saveSession = () => ls.set("ps_session", JSON.stringify(session));
const paymentLabel = method => ({ cash: "Cash", gcash: "GCash", card: "Card" }[method] || method);
const paymentMethodKey = method => ({ Cash: "cash", GCash: "gcash", Card: "card" }[method]);
const sessionFromServer = snapshot => {
  if (!snapshot) return { table, status: null, orderCount: 0, total: 0, orders: [], bill: null };
  return {
    table,
    status: snapshot.status,
    orderCount: Number(snapshot.order_count),
    total: Number(snapshot.total),
    orders: snapshot.orders.map(order => ({
      ref: order.reference,
      batch: order.batch_number,
      status: order.status,
      items: order.items.map(item => ({
        name: item.name,
        quantity: item.quantity,
        variant: item.variant,
        modifiers: item.modifiers
      })),
      total: Number(order.total)
    })),
    bill: snapshot.status === "bill_requested" ? {
      ref: "",
      method: paymentLabel(snapshot.requested_payment_method),
      total: Number(snapshot.total)
    } : null
  };
};
const orderedTotal = () => Number.isFinite(Number(session.total))
  ? Number(session.total)
  : session.orders.reduce((s, o) => s + o.total, 0);
async function readResponse(response) {
  const data = await response.json();
  if (!response.ok) {
    const validationMessages = data.errors ? Object.values(data.errors).flat().join("\n") : "";
    const error = new Error(validationMessages || data.message || `Request failed (${response.status}).`);
    error.status = response.status;
    throw error;
  }
  return data;
}
let sessionRefreshInFlight = false, sessionSyncWarningShown = false, billSubmitting = false;
async function refreshSession() {
  if (sessionRefreshInFlight) return;
  sessionRefreshInFlight = true;
  try {
    const response = await fetch(CONFIG.sessionEndpoint, { headers: { "Accept": "application/json" } });
    const data = await readResponse(response);
    if (!Array.isArray(data.available_item_ids) || data.available_item_ids.some(id => !Number.isInteger(id))) {
      throw new Error("The menu availability response has an invalid shape.");
    }
    const availableIds = [...data.available_item_ids].sort((a, b) => a - b);
    const currentIds = [...INITIAL_MENU_ITEM_IDS].sort((a, b) => a - b);
    if (availableIds.length !== currentIds.length || availableIds.some((id, index) => id !== currentIds[index])) {
      window.location.reload();
      return;
    }
    session = sessionFromServer(data.session);
    saveSession();
    sessionSyncWarningShown = false;
    renderBar();
    if (document.body.classList.contains("open")) renderSheet();
  } catch (err) {
    console.error("Unable to refresh this table's running bill.", err);
    if (!sessionSyncWarningShown) {
      alert(err.message || "We couldn't refresh this table's running bill. Check your connection.");
      sessionSyncWarningShown = true;
    }
  } finally {
    sessionRefreshInFlight = false;
  }
}
async function post(url, payload) {
  const csrf = document.querySelector('meta[name="csrf-token"]');
  const r = await fetch(url, { method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json", ...(csrf ? { "X-CSRF-TOKEN": csrf.content } : {}) }, body: JSON.stringify(payload) });
  return readResponse(r);
}
function billBlock() {
  const n = session.orderCount ?? session.orders.length; if (!n) return "";
  return `<div class="bill" id="billsec"><h4>Finished eating?</h4>
    <p class="bsum">${n} order${n > 1 ? "s" : ""} sent · <b>${peso(orderedTotal())}</b></p>
    <div class="mop" role="radiogroup" aria-label="How will you pay?">${["Cash", "GCash", "Card"].map(m => `<button role="radio" aria-checked="${mop === m}" class="${mop === m ? "on" : ""}" data-mop="${m}">${m}</button>`).join("")}</div>
    <button class="add ink" data-bill style="width:100%;margin-top:12px;justify-content:center" ${mop ? "" : "disabled"}>Request the bill</button>
    <p class="bnote">${mop ? "Our crew will bring your bill. You chose to pay by " + mop + "." : "Choose how you'd like to pay."} Senior and PWD discounts are applied by the cashier.</p></div>`;
}

let cart = [];
try { cart = JSON.parse(ls.get("ps_cart") || "[]"); } catch (e) {}
const saveCart = () => ls.set("ps_cart", JSON.stringify(cart));
if (!Array.isArray(cart)) cart = [];
const savedCart = cart;
cart = savedCart.map(line => {
  if (!line || typeof line !== "object" || typeof line.name !== "string" || typeof line.opt !== "string") return null;
  const menuItem = MENU_ITEMS_BY_NAME.get(line.name);
  if (!menuItem) return null;
  const variant = menuItem.variants.find(option => option.name === line.opt);
  if (menuItem.variants.length && !variant) return null;
  return {
    ...line,
    k: `${menuItem.id}|${variant ? variant.id : ""}`,
    menu_item_id: menuItem.id,
    variant_id: variant ? variant.id : null,
    modifier_option_ids: []
  };
}).filter(Boolean);
if (cart.length !== savedCart.length) {
  alert("Some saved dishes are no longer available and were removed from your order.");
  saveCart();
}

let cat = 0, items = [], N = 0, step = 45, cur = 0, tgt = 0, active = -1, opt = 0, qty = 1, raf = 0;
let scrollLocked = false, scrollLockTimer = 0, touchStart = null;

function stepDish(direction) {
  if (!direction || N < 2 || scrollLocked) return;
  const current = clamp(Math.round(scrollY / STEP), 0, N - 1);
  const next = clamp(current + direction, 0, N - 1);
  if (next === current) return;
  scrollLocked = true;
  window.scrollTo({ top: next * STEP, behavior: reduce ? "auto" : "smooth" });
  scrollLockTimer = setTimeout(() => { scrollLocked = false; scrollLockTimer = 0; }, reduce ? 160 : 500);
}

/* ---------- Categories ---------- */
$("#pills").innerHTML = MENU.map((c, i) => `<button class="pill" data-c="${i}">${c[0]}</button>`).join("");
$("#pills").onclick = e => { const b = e.target.closest(".pill"); if (b) build(+b.dataset.c); };

function build(c) {
  cat = c; items = MENU[c][1]; N = items.length; step = Math.min(45, 360 / N);
  clearTimeout(scrollLockTimer); scrollLocked = false; touchStart = null;
  $("#track").style.height = `calc(100svh + ${(N - 1) * STEP}px)`;
  $("#snaps").innerHTML = items.map((_, i) => `<i style="top:${i * STEP}px"></i>`).join("");
  $("#wheel").prepend($(".notch"));
  $("#ring").innerHTML = items.map((it, i) => `<button class="node" data-i="${i}" aria-label="${it[0]}"${PHOTOS[it[0]] ? ` style="background-image:url(${PHOTOS[it[0]]});color:transparent"` : ""}>${code(it)}</button>`).join("");
  document.querySelectorAll(".pill").forEach((p, i) => { p.classList.toggle("on", i === c); if (i === c) p.scrollIntoView({ inline: "center", block: "nearest", behavior: "smooth" }); });
  $("#wheel").classList.toggle("boodle", MENU[c][0] === "Boodle Flights");
  window.scrollTo(0, 0); cur = tgt = 0; active = -1; setActive(0); frame();
}
$("#ring").onclick = e => { const n = e.target.closest(".node"); if (n) window.scrollTo({ top: n.dataset.i * STEP, behavior: reduce ? "auto" : "smooth" }); };

/* ---------- Rotating plate wheel ---------- */
function frame() {
  const wh = $("#wheel"), bd = wh.classList.contains("boodle"), R = wh.clientWidth / 2 - 30;
  cur += (tgt - cur) * (reduce ? 1 : 0.14);
  if (Math.abs(tgt - cur) < 0.002) cur = tgt;
  [...$("#ring").children].forEach((n, i) => {
    const r = (i - cur) * step * Math.PI / 180, k = Math.max(0, 1 - Math.abs(i - cur));
    n.style.transform = bd
      ? `translate(-50%,-50%) translate(${(i - cur) * 72}px,${wh.clientHeight * 0.36}px) scale(${1 + 0.3 * k})`
      : `translate(-50%,-50%) translate(${R * Math.sin(r)}px,${-R * Math.cos(r)}px) scale(${1 + 0.3 * k})`;
    n.classList.toggle("on", i === active);
  });
  $("#rim").style.transform = bd ? "none" : `rotate(${-cur * step}deg)`;
  raf = cur !== tgt ? requestAnimationFrame(frame) : 0;
}
addEventListener("scroll", () => {
  tgt = clamp(scrollY / STEP, 0, N - 1);
  setActive(Math.round(tgt));
  if (!raf) raf = requestAnimationFrame(frame);
}, { passive: true });
addEventListener("wheel", e => {
  if (e.ctrlKey || e.shiftKey || Math.abs(e.deltaY) <= Math.abs(e.deltaX) ||
      e.target.closest(".sheet,.scrim")) return;
  e.preventDefault();
  stepDish(Math.sign(e.deltaY));
}, { passive: false });
addEventListener("touchstart", e => {
  const target = e.target;
  touchStart = e.touches.length === 1 && !target.closest(".sheet,.scrim")
    ? { x: e.touches[0].clientX, y: e.touches[0].clientY, horizontalMenu: !!target.closest(".pills") } : null;
}, { passive: true });
addEventListener("touchmove", e => {
  if (!touchStart || e.touches.length !== 1) return;
  const touch = e.touches[0], deltaX = touchStart.x - touch.clientX, deltaY = touchStart.y - touch.clientY;
  if (touchStart.horizontalMenu && Math.abs(deltaX) > Math.abs(deltaY)) { touchStart = null; return; }
  if (Math.abs(deltaY) > 8) e.preventDefault();
}, { passive: false });
addEventListener("touchend", e => {
  if (!touchStart || !e.changedTouches.length) { touchStart = null; return; }
  const distance = touchStart.y - e.changedTouches[0].clientY;
  touchStart = null;
  if (Math.abs(distance) > 32) stepDish(Math.sign(distance));
}, { passive: true });
addEventListener("touchcancel", () => { touchStart = null; }, { passive: true });
addEventListener("resize", () => frame());

/* ---------- Dish details ---------- */
function setActive(i) {
  if (i === active) return;
  active = i;
  const notch = $(".notch"), selected = $("#ring").children[i];
  if (notch && selected) selected.append(notch);
  opt = 0; qty = 1; renderInfo();
}
function renderInfo() {
  const it = items[active], o = optsOf(it), multi = Array.isArray(it[2]);
  $("#code").textContent = code(it);
  $("#catName").textContent = $("#wheel").classList.contains("boodle") ? it[3] : MENU[cat][0];
  const pl = $("#plate"); const ph = PHOTOS[it[0]];
  pl.classList.toggle("img", !!ph); pl.style.backgroundImage = ph ? `url(${ph})` : "";
  $("#info").innerHTML = `<h2>${it[0]}</h2>
    ${it[1] && it[1].length <= 40 ? `<div class="sub">${it[1]}</div>` : ""}
    ${detail(it)[0] ? `<p class="desc">${detail(it)[0]}</p>` : ""}
    ${detail(it)[1].length ? `<div class="ing"><b>Ingredients</b>${detail(it)[1].map(x => `<span>${x}</span>`).join("")}</div>` : ""}
    ${multi ? `<div class="chips">${o.map((x, j) => `<button class="chip ${j === opt ? "on" : ""}" data-o="${j}">${x[0]}</button>`).join("")}</div>` : ""}
    <div class="buy"><div class="qty"><button data-q="-1" aria-label="Less">–</button><span>${qty}</span><button data-q="1" aria-label="More">+</button></div>
    <button class="add" data-add><span>Add to order</span><span>${peso(o[opt][1] * qty)}</span></button></div>`;
}
$("#info").onclick = e => {
  const t = e.target.closest("button"); if (!t) return;
  if (t.dataset.o) opt = +t.dataset.o;
  else if (t.dataset.q) qty = Math.max(1, qty + +t.dataset.q);
  else if ("add" in t.dataset) return addToCart();
  renderInfo();
};

/* ---------- Cart ---------- */
function addToCart() {
  const it = items[active], o = optsOf(it)[opt], menuItem = MENU_ITEMS_BY_NAME.get(it[0]);
  const variant = menuItem?.variants.find(option => option.name === o[0]);
  if (!menuItem || (menuItem.variants.length && !variant)) {
    alert("This dish or size is no longer available. Refresh the page and try again.");
    return;
  }
  const k = `${menuItem.id}|${variant ? variant.id : ""}`;
  const line = cart.find(l => l.k === k);
  line ? line.qty += qty : cart.push({
    k,
    menu_item_id: menuItem.id,
    variant_id: variant ? variant.id : null,
    modifier_option_ids: [],
    name: it[0],
    code: code(it),
    opt: o[0] || it[1],
    price: o[1],
    qty
  });
  qty = 1; saveCart(); renderInfo(); renderBar();
  $("#basket").classList.remove("bump"); void $("#basket").offsetWidth; $("#basket").classList.add("bump");
}
const count = () => cart.reduce((s, l) => s + l.qty, 0);
const total = () => cart.reduce((s, l) => s + l.qty * l.price, 0);
function renderBar() {
  $("#basket").classList.toggle("has", count() > 0);
  $("#billbtn").classList.toggle("has", !!session.bill);
  if (DESK.matches) renderSheet();
  $("#barCount").textContent = count(); $("#barCount").hidden = !count();
  $("#basket").title = count() + " in your basket · " + peso(total());
}
function renderSheet(d) {
  if (d) confirmed = d;
  const done = confirmed;
  const s = $("#sheet");
  if (session.bill) {
    s.innerHTML = `<h3>Bill requested <button data-close aria-label="Close" style="font-size:28px">×</button></h3>
    <div class="done"><p>Table ${table}</p><b>${peso(session.bill.total)}</b><p>Paying by <b>${session.bill.method}</b></p><p style="color:var(--mute);font-size:14px">Our crew is on the way with your bill.${session.bill.ref ? ` Reference ${session.bill.ref}` : ""}</p></div>
    ${session.orders.map(o => `<div class="line"><div class="code">#${o.batch}</div><div>Order ${o.batch}<em>${o.items.map(i => i.quantity + "× " + i.name).join(", ")}</em></div><div>${peso(o.total)}</div></div>`).join("")}
    <button class="add ink" data-cancelbill style="width:100%;margin-top:14px;justify-content:center">Not yet, I'd like to order more</button>
    <button class="linkbtn" data-reset>New guests? Start a new table session</button>`;
    return;
  }
  if (done) { s.innerHTML = `<div class="done"><p>Order sent to the kitchen for ${table ? "table " + table : "your table"}</p><b>${done}</b><p style="color:var(--mute);font-size:14px">Show this reference number to our crew if needed.</p></div><button class="add" data-close style="width:100%;justify-content:center">Back to menu</button>${billBlock()}`; return; }
  s.innerHTML = `<h3>Your order <button data-close aria-label="Close" style="font-size:28px">×</button></h3>
  ${cart.length ? cart.map((l, i) => `<div class="line"><div class="code">${l.code}</div><div>${l.name}<em>${l.opt}</em>${peso(l.price)}</div>
    <div class="qty"><button data-l="${i}" data-d="-1">–</button><span>${l.qty}</span><button data-l="${i}" data-d="1">+</button></div></div>`).join("")
    : `<p style="margin-top:16px;color:var(--mute)">Nothing here yet. Spin the plate and pick a dish.</p>`}
  <textarea id="notes" rows="2" placeholder="Special instructions (e.g. no onions, extra rice)">${notesVal}</textarea>
  <div class="row"><span>Total</span><span>${peso(total())}</span></div>
  <button class="add" data-place style="width:100%;margin-top:14px;justify-content:center" ${cart.length ? "" : "disabled"}>Place order</button>${billBlock()}`;
}
$("#billbtn").onclick = () => {
  if (!(session.orderCount ?? session.orders.length) && !session.bill) { alert("Place an order first, then you can request the bill here."); return; }
  confirmed = null; renderSheet(); document.body.classList.add("open");
  setTimeout(() => { const b = $("#billsec"); if (b) b.scrollIntoView({ block: "center", behavior: reduce ? "auto" : "smooth" }); }, 60);
};
$("#basket").onclick = () => { renderSheet(); document.body.classList.add("open"); };
$("#scrim").onclick = () => { confirmed = null; document.body.classList.remove("open"); };
$("#sheet").onclick = async e => {
  const t = e.target.closest("button"); if (!t) return;
  if (t.dataset.mop) { mop = t.dataset.mop; renderSheet(); const b = $("#billsec"); if (b) b.scrollIntoView({ block: "nearest" }); }
  else if ("bill" in t.dataset) {
    if (!mop || !(session.orderCount ?? session.orders.length) || billSubmitting) return;
    billSubmitting = true;
    try {
      const result = await post(CONFIG.billEndpoint, { payment_method: paymentMethodKey(mop) });
      if (!result.session) throw new Error("The bill request was received, but the bill status could not be refreshed.");
      session = sessionFromServer(result.session);
      saveSession(); renderBar(); renderSheet();
    } catch (err) {
      alert(err.message || "We couldn't send your request. Check your connection and try again.");
    } finally {
      billSubmitting = false;
    }
  }
  else if ("cancelbill" in t.dataset) {
    if (billSubmitting) return;
    billSubmitting = true;
    try {
      const result = await post(CONFIG.billCancelEndpoint, {});
      if (!result.session) throw new Error("The bill request was cancelled, but the table status could not be refreshed.");
      session = sessionFromServer(result.session);
      mop = null;
      saveSession(); renderBar(); renderSheet();
    } catch (err) {
      alert(err.message || "We couldn't cancel your request. Check your connection and try again.");
    } finally {
      billSubmitting = false;
    }
  }
  else if ("reset" in t.dataset) {
    if (confirm("Start a new table session? The current orders will be cleared from this phone.")) { session = { table, orders: [], bill: null }; mop = null; saveSession(); renderBar(); renderSheet(); }
  }
  else if ("close" in t.dataset) { confirmed = null; document.body.classList.remove("open"); if (DESK.matches) renderSheet(); }
  else if (t.dataset.l) { const l = cart[+t.dataset.l]; l.qty += +t.dataset.d; if (l.qty < 1) cart.splice(+t.dataset.l, 1); saveCart(); renderBar(); renderSheet(); }
  else if ("place" in t.dataset) {
    if (orderSubmitting) return;
    if (session.status === "bill_requested") {
      alert("A bill has already been requested for this table. Cancel the request before ordering again.");
      return;
    }
    if (!CONFIG.orderEndpoint) {
      alert("Ordering is temporarily unavailable. Please ask a staff member for help.");
      return;
    }
    orderSubmitting = true;
    try {
      const payload = {
        idempotency_key: pendingOrderKey || (pendingOrderKey = newIdempotencyKey()),
        special_instructions: $("#notes").value,
        items: cart.map(line => ({
          menu_item_id: line.menu_item_id,
          ...(line.variant_id ? { variant_id: line.variant_id } : {}),
          modifier_option_ids: line.modifier_option_ids,
          quantity: line.qty
        }))
      };
      const result = await post(CONFIG.orderEndpoint, payload);
      if (result.session) {
        session = sessionFromServer(result.session);
        saveSession();
      } else {
        await refreshSession();
        if (!session.orders.some(order => order.ref === result.reference)) {
          throw new Error("The order was accepted, but the running bill could not be refreshed. Please try again.");
        }
      }
      pendingOrderKey = null;
      cart = []; notesVal = ""; saveCart(); renderBar(); renderSheet(result.reference);
    } catch (err) {
      alert(err.message || "We couldn't send your order. Check your connection and try again.");
    } finally {
      orderSubmitting = false;
    }
  }
};

$("#sheet").addEventListener("input", e => { if (e.target.id === "notes") notesVal = e.target.value; });
function placeSheet() { // desktop: pin the order panel inside the stage's right-hand column
  const s = $("#sheet");
  if (!DESK.matches) { s.style.right = s.style.top = s.style.bottom = ""; return; }
  const r = $(".stage").getBoundingClientRect(), h = $("header").getBoundingClientRect();
  s.style.right = (document.documentElement.clientWidth - r.right + 28) + "px";
  s.style.top = (h.bottom + 8) + "px";
  s.style.bottom = (innerHeight - r.bottom + 24) + "px";
}
addEventListener("resize", placeSheet);
DESK.addEventListener("change", () => { document.body.classList.remove("open"); renderSheet(); placeSheet(); frame(); });

/* ---------- Init + PWA ---------- */
let orderSubmitting = false, pendingOrderKey = null;
function newIdempotencyKey() {
  if (crypto.randomUUID) return crypto.randomUUID();
  if (!crypto.getRandomValues) throw new Error("Secure order submission is not available in this browser.");
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = [...bytes].map(byte => byte.toString(16).padStart(2, "0")).join("");
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
renderBar(); build(0); placeSheet(); if (document.fonts) document.fonts.ready.then(placeSheet);
refreshSession();
setInterval(refreshSession, 15000);
