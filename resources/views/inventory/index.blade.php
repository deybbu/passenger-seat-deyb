<!DOCTYPE html>
<html lang="en" data-theme="light"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#C62828"><meta name="description" content="Passenger Seat QR inventory receiving, commissary purchasing, recipe costing and audit history."><meta name="application-name" content="Passenger Seat Inventory">@include('partials.favicon')<title>QR Code Inventory Receiving &middot; Passenger Seat</title>

<link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v={{ filemtime(public_path('css/inventory.css')) }}"></head>
<body>
<header class="top">
 <div class="brand"><div class="logo" style="width:48px;height:48px;padding:2px;background:#fff"><img src="{{ asset('images/landing-logo.png') }}" alt="Passenger Seat logo" style="width:100%;height:100%;object-fit:contain"></div><div class="head-title"><h1 id="pageTitle">QR Code Inventory Receiving</h1><div class="crumb">Passenger Seat / <span id="crumb">Receive stock</span></div></div></div>
 <div class="head-actions"><span class="status-pill" id="syncStatus">Live database snapshot</span><div class="theme-wrap"><span class="theme-label" id="themeLabel">Light Mode</span><button id="themeSwitch" class="theme-toggle" role="switch" aria-checked="false" aria-label="Switch to Dark Mode"><span class="theme-knob" id="themeKnob"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg></span></button></div><div class="inventory-notify-wrap"><button class="inventory-notify-bell" id="inventoryBell" type="button" aria-label="Notifications" aria-controls="inventoryNotificationPanel" aria-expanded="false" title="Notifications"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span id="inventoryUnreadCount" class="inventory-notify-count" hidden>0</span></button><section class="inventory-notify-panel" id="inventoryNotificationPanel" aria-label="Inventory notifications" hidden><div class="inventory-notify-heading"><strong>Notifications</strong><button id="inventoryMarkAllRead" type="button">Mark all read</button></div><div class="inventory-notify-list" id="inventoryNotificationList" aria-live="polite"></div><button id="viewInventoryNotifications" class="inventory-notify-footer" type="button">View all notifications</button></section></div><div class="who staff-profile"><span class="av" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="nm"><b>{{ auth()->user()->name }}</b><small>{{ ucfirst(auth()->user()->role) }}</small></span></div><button class="logout-btn" style="order:5" id="logoutBtn" type="button" aria-label="Log out {{ auth()->user()->name }}" title="Log out"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></button></div>
</header>
<div class="logout-modal" id="inventoryLogoutModal" hidden><section class="logout-box" role="dialog" aria-modal="true" aria-labelledby="inventoryLogoutTitle"><header class="logout-head"><h2 id="inventoryLogoutTitle">Log out?</h2><button type="button" data-logout-cancel aria-label="Close">×</button></header><p>Are you sure you want to leave the inventory workspace?</p><footer><button type="button" data-logout-cancel>Cancel</button><form method="POST" action="{{ route('staff.inventory.logout') }}">@csrf<button class="confirm" type="submit">Log out</button></form></footer></section></div>
<main>
 <div class="note warn-note"><b>Live database data:</b> inventory, recipes, packages, lots, purchase orders, and activity below are loaded from the restaurant database. Purchase order drafts require manager approval before receiving.</div>
 <nav class="tabs" id="tabs" role="tablist" aria-label="Inventory sections">
  <button role="tab" data-t="receive" aria-selected="true">QR Receiving</button><button role="tab" data-t="po" aria-selected="false">Purchase Orders</button><button role="tab" data-t="costing" aria-selected="false">Recipe &amp; Food Costing</button><button role="tab" data-t="waste" aria-selected="false">Waste &amp; Variance</button><button role="tab" data-t="inventory" aria-selected="false">Inventory</button><button role="tab" data-t="history" aria-selected="false">History &amp; Audit</button>
 </nav>
 <section id="view-receive" class="panel">
  <div class="layout-2">
   <div class="card scanner"><h2>Scan incoming shipment QR code</h2><p class="subtext">Scan a QR label containing a package code or JSON metadata. Camera scanning requires browser camera permission; manual entry remains available.</p>
    <div class="cam"><video id="video" playsinline muted class="hide"></video><div class="frame" id="scanFrame"></div><div class="hint" id="scanHint">Point the camera at the shipment QR code</div></div>
    <div class="btns"><button class="btn red" id="cameraBtn">Start QR scanner</button></div>
    <form class="manual" id="manualForm" style="display:flex;gap:8px;margin-top:10px"><input id="manualCode" aria-label="QR code or manual entry code" placeholder="Enter QR code / Manual Entry Code" autocomplete="off"><button type="submit" class="btn">Look up</button></form>
    <div id="scanMessage" class="msg" role="status" aria-live="polite">Waiting for a QR code. Inventory changes only after receipt confirmation.</div>
    <div id="packageCard" class="pkg hide">
     <h3>Shipment details</h3><p class="subtext" id="scanMeta"></p>
     <div class="form-grid">
      <label>Ingredient<select id="fieldIngredient" required></select></label>
      <label>Net Weight / Quantity<div class="qty-with-unit"><input id="fieldQty" type="number" min="0.001" step="any" required aria-label="Net weight or quantity"><select class="uom-select" id="fieldQtyUnit" aria-label="Unit of measure for received quantity"><option value="kg">kg (Kilograms)</option><option value="g">g (Grams)</option><option value="L">L (Liters)</option><option value="mL">mL (Milliliters)</option><option value="pcs">pcs (Pieces)</option><option value="pack / bag">pack / bag</option><option value="can">can</option></select></div><div class="uom-factor-wrap hide" id="fieldQtyFactorWrap"><small>For 1 pack / bag, enter the amount in <b id="fieldQtyFactorBase">kg</b></small><input type="number" id="fieldQtyFactor" min="0.000001" step="any" placeholder="Base units per pack / bag" aria-label="Base units per pack or bag"></div><small class="uom-hint">Choose the quantity unit. For pack / bag, enter the base-unit amount contained in one package.</small></label>
      <label>Best Consumed Before<input id="fieldBestBefore" type="date" required></label>
      <label>Manual Entry Code<input id="fieldManualCode" required autocomplete="off"></label>
      <label>Batch / Lot Number<input id="fieldBatch"></label>
      <label>Manufacturer<input id="fieldManufacturer"></label>
      <label>Serial Number<input id="fieldSerial"></label>
      <label>Commissary / Location<select id="fieldCommissary"></select></label>
      <label class="wide">Storage Bin / Location<input id="fieldBin" placeholder="e.g. CHILLER-A / SHELF-02"></label>
      <label class="wide">Link to Purchase Order<select id="fieldPO"></select></label>
     </div>
     <div class="note" style="margin-top:10px"><b>Scan timestamp:</b> <span id="scanTimestamp">â€”</span><br><b>Expected stock after receipt:</b> <span id="afterStock">â€”</span></div>
     <div class="btns"><button class="btn red" id="confirmReceipt">Confirm receipt &amp; add stock</button><button class="btn" id="cancelScan">Cancel scan</button></div>
    </div>
   </div>
   <div class="panel">
    @if (in_array((string) auth()->user()->role, ['manager', 'admin'], true))
    <div class="card"><h2>Register package QR</h2><p class="subtext">Create a registered package and print its QR and manual entry label.</p>
     <form id="packageRegisterForm"><div class="row"><label>Ingredient<select id="registerIngredient" required></select></label><label>Package label<input id="registerLabel" maxlength="120" required placeholder="e.g. Chicken breast pack"></label></div><div class="row"><label>Package quantity<div class="qty-with-unit"><input id="registerQuantity" type="number" min="0.001" step="any" required><span id="registerUnit" class="tiny"></span></div></label><button class="btn red" type="submit">Register &amp; print label</button></div></form>
     <div id="packageRegisterMessage" class="msg" role="status" aria-live="polite"></div>
    </div>
    @endif
    <div class="card"><h2>QR receiving rules</h2><div class="line-list">
     <div class="line-row"><b>Rich metadata</b><span class="mute">SKU / ingredient, lot, expiry, serial, manufacturer</span></div>
     <div class="line-row"><b>Duplicate protection</b><span class="mute">Each confirmed QR/manual receipt code can only be received once</span></div>
     <div class="line-row"><b>Automatic scan time</b><span class="mute">Scan time and receipt time are separately logged</span></div>
     <div class="line-row"><b>Audit trail</b><span class="mute">Staff ID, quantity, commissary, bin and PO</span></div>
     <div class="line-row"><b>Near-expiry alerts</b><span class="mute">Best-consumed-before dates are checked on receipt</span></div>
    </div><p class="footer-note">Only package QR and manual entry codes registered in this restaurant are accepted. Camera scanning requires QR BarcodeDetector support; manual entry remains available.</p></div>
    <div class="card"><div class="table-top"><h2>Recent receipt activity</h2><button class="btn small" data-open="history">View audit log</button></div><div class="table-wrap"><table><thead><tr><th>Received at</th><th>Ingredient</th><th>Quantity</th><th>Commissary</th><th>Code / Batch</th></tr></thead><tbody id="recentReceipts"></tbody></table></div></div>
   </div>
  </div>
 </section>
 <section id="view-po" class="panel hide">
  <div class="card"><div class="table-top"><div><h2>Purchase orders across 7 commissaries</h2><p class="subtext">Create and track orders by commissary, supplier, ingredient, and expected quantity.</p></div><span class="badge b-neutral">7 commissary locations configured</span></div>
   <div class="row"><label>Commissary<select id="poCommissary"></select></label><label>Ingredient<select id="poIngredient"></select></label><label>Ordered quantity<div class="qty-with-unit"><input id="poQty" type="number" min="0.01" step="any" value="20" aria-label="Ordered quantity"><select class="uom-select" id="poQtyUnit" aria-label="Unit of measure for ordered quantity"><option value="kg">kg (Kilograms)</option><option value="g">g (Grams)</option><option value="L">L (Liters)</option><option value="mL">mL (Milliliters)</option><option value="pcs">pcs (Pieces)</option><option value="pack / bag">pack / bag</option><option value="can">can</option></select></div><div class="uom-factor-wrap hide" id="poQtyFactorWrap"><small>For 1 pack / bag, enter the amount in <b id="poQtyFactorBase">kg</b></small><input type="number" id="poQtyFactor" min="0.000001" step="any" placeholder="Base units per pack / bag" aria-label="Base units per pack or bag"></div></label><label>Supplier<input id="poSupplier" value="Supplier A"></label><label>Expected delivery<input id="poDue" type="date"></label><button class="btn red" id="createPO">Create draft purchase order</button></div>
   <div class="metrics" id="poMetrics"></div>
  </div>
  <div class="card"><div class="table-top"><h2>Purchase orders</h2><div class="row" style="margin:0"><select id="poFilter" aria-label="Filter commissary"><option value="">All commissaries</option></select><select id="poStatusFilter" aria-label="Filter purchase order status"><option value="">All statuses</option><option>Draft</option><option>Open</option><option>Partially received</option><option>Received</option></select></div></div>
   <div class="table-wrap"><table><thead><tr><th>PO</th><th>Commissary</th><th>Item</th><th>Ordered</th><th>Received</th><th>Supplier</th><th>Due date</th><th>Status</th><th>Action</th></tr></thead><tbody id="poBody"></tbody></table></div>
   <p class="footer-note">A QR receipt can be linked to an open PO. Confirmed receipts update the received quantity for the matching PO line, up to the order balance.</p>
  </div>
 </section>
 <section id="view-costing" class="panel hide">
  <div class="card"><div class="table-top"><div><h2>Recipe &amp; food cost</h2><p class="subtext">Cost per portion is calculated from recipe quantities Ã— current ingredient unit costs.</p></div><label style="width:135px;font-size:11px;color:var(--mute);font-weight:600">Target food cost %<input id="targetCost" type="number" min="1" max="100" step="1" value="30"></label></div>
   <div class="table-wrap"><table><thead><tr><th>Dish</th><th>Price</th><th>Cost / portion</th><th>Cost %</th><th>Margin</th><th>Suggested price</th><th>Status</th></tr></thead><tbody id="costBody"></tbody></table></div>
  </div>
  <div class="card"><div class="table-top"><h2 id="recipeTitle">Chicken adobo: ingredients</h2><b id="recipeSummary">â‚±65.00 Â· 29.5%</b></div><div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Qty per portion</th><th>Unit cost</th><th>Line cost</th><th>Action</th></tr></thead><tbody id="recipeBody"></tbody></table></div>
   <div class="row" style="margin-top:12px"><label>Ingredient<select id="addRecipeIngredient"></select></label><label>Quantity per portion<div class="qty-with-unit"><input id="addRecipeQty" type="number" min="0.001" step="any" placeholder="Qty" aria-label="Ingredient quantity per portion"><select class="uom-select" id="addRecipeQtyUnit" aria-label="Unit of measure for recipe quantity"><option value="kg">kg (Kilograms)</option><option value="g">g (Grams)</option><option value="L">L (Liters)</option><option value="mL">mL (Milliliters)</option><option value="pcs">pcs (Pieces)</option><option value="pack / bag">pack / bag</option><option value="can">can</option></select></div><div class="uom-factor-wrap hide" id="addRecipeQtyFactorWrap"><small>For 1 pack / bag, enter the amount in <b id="addRecipeQtyFactorBase">kg</b></small><input type="number" id="addRecipeQtyFactor" min="0.000001" step="any" placeholder="Base units per pack / bag" aria-label="Base units per pack or bag"></div><small class="uom-hint">Recipe quantity is saved in the ingredient's inventory unit. For pack / bag, define the base amount per pack.</small></label><button class="btn red" id="addRecipeLine">Add ingredient</button></div>
   <p class="footer-note">Recipe usage shown here comes from the restaurant menu recipes and real inventory movements.</p>
  </div>
 </section>
 <section id="view-waste" class="panel hide">
  <div class="metrics" id="wasteMetrics"></div>
  <div class="grid-2">
   <div class="card"><h2>Log food waste</h2><p class="subtext">Record discarded quantities, calculate the financial loss using current ingredient costs, and update stock automatically.</p>
    <div class="row"><label>Ingredient<select id="wasteIngredient"></select></label><label>Quantity wasted<div class="qty-with-unit"><input id="wasteQty" type="number" min="0.001" step="any" placeholder="Qty" aria-label="Quantity wasted"><select class="uom-select" id="wasteQtyUnit" aria-label="Unit of measure for waste quantity"><option value="kg">kg (Kilograms)</option><option value="g">g (Grams)</option><option value="L">L (Liters)</option><option value="mL">mL (Milliliters)</option><option value="pcs">pcs (Pieces)</option><option value="pack / bag">pack / bag</option><option value="can">can</option></select></div><div class="uom-factor-wrap hide" id="wasteQtyFactorWrap"><small>For 1 pack / bag, enter the amount in <b id="wasteQtyFactorBase">kg</b></small><input type="number" id="wasteQtyFactor" min="0.000001" step="any" placeholder="Base units per pack / bag" aria-label="Base units per pack or bag"></div><small class="uom-hint">Waste and loss value are calculated in the ingredient's inventory unit.</small></label></div>
    <div class="row"><label>Waste stage<select id="wasteStage" required></select></label><label>Reason<select id="wasteReason" required></select></label><label class="hide" id="wasteOtherReasonWrap">Other reason<input id="wasteOtherReason" maxlength="80" placeholder="Describe the waste reason"></label><label>Commissary<select id="wasteCommissary"></select></label></div>
    <div class="row"><label class="grow-2">Notes / evidence<input id="wasteNote" placeholder="Optional note, batch, or incident reference"></label><button id="saveWaste" class="btn red">Record waste &amp; cost</button></div>
   </div>
   <div class="card"><h2>Purchase planning adjusted for waste</h2><p class="subtext">Repeated waste lowers the planned demand estimate, but recommendations respect the ingredient's minimum stock level.</p><div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>7D waste qty</th><th>7D waste cost</th><th>Suggested order</th><th>Action</th></tr></thead><tbody id="buyPlanBody"></tbody></table></div><p class="footer-note">Recommendations are estimates based on this demo's usage/day values, current stock, minimum stock, and logged waste in the last seven days.</p></div>
  </div>
  <div class="card"><div class="table-top"><div><h2>Variance analysis</h2><p class="subtext">Expected stock = opening stock + receipts âˆ’ recipe usage from sales âˆ’ recorded waste. Compare that to a physical count to identify unexplained shrinkage.</p></div><button id="saveCounts" class="btn red">Save physical counts</button></div>
   <div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Expected from recipe</th><th>System stock</th><th>Physical count</th><th>Variance</th><th>Variance reason</th></tr></thead><tbody id="varianceBody"></tbody></table></div><p class="footer-note" id="varianceNote">Enter physical counts for the items being checked. Negative variance means less stock was found than the recorded transactions predict.</p>
  </div>
  <div class="card"><h2>Waste register &amp; financial impact</h2><div class="table-wrap"><table><thead><tr><th>Time</th><th>Staff</th><th>Commissary</th><th>Ingredient</th><th>Qty</th><th>Reason</th><th>Loss value</th><th>Notes</th></tr></thead><tbody id="wasteBody"></tbody></table></div>
   <h3 style="margin-top:16px">Estimated P&amp;L impact</h3><div class="metrics" id="pnlMetrics"></div><p class="footer-note">P&amp;L amounts are an estimate derived from sample menu sales, recipe costs, and recorded waste/negative unexplained variance; they are not an accounting-system posting.</p>
  </div>
 </section>
 <section id="view-inventory" class="panel hide">
  <div class="card"><h2>Inventory overview</h2><div class="metrics" id="inventoryMetrics"></div><div class="row" style="margin-top:12px"><label class="grow-2">Search ingredient<input id="inventorySearch" placeholder="Search ingredient, supplier or manual code"></label><label>Category<select id="inventoryCategory"><option value="">All categories</option></select></label><label>Status<select id="inventoryStatus"><option value="">All statuses</option><option>In stock</option><option>Low</option><option>Out of stock</option><option>Near expiry</option></select></label></div>
   <div class="table-wrap"><table><thead><tr><th>Ingredient</th><th>Category</th><th>On hand</th><th>Min stock</th><th>Usage / day</th><th>Best consumed before</th><th>Unit cost</th><th>Stock value</th><th>Status</th></tr></thead><tbody id="inventoryBody"></tbody></table></div>
  </div>
  <div class="grid-2"><div class="card"><h2>Low stock &amp; expiry alerts</h2><div id="inventoryAlerts" class="panel"></div></div><div class="card"><h2>Commissary coverage</h2><p class="subtext">This prototype maintains a shared local inventory view with transaction-level commissary tags. Separate stock balances per commissary require backend/location-specific stock ledgers.</p><div class="metrics" id="commissaryMetrics"></div></div></div>
  <div class="card"><h2>Batch tracking &amp; FIFO queue</h2><p class="subtext">Received batches retain lot number, received time, manual/QR reference, best-consumed-before date, and bin. Recipe sales and logged waste consume unexpired batches by oldest receipt time first, then earliest expiry.</p><div class="table-wrap"><table><thead><tr><th>Received at</th><th>Ingredient</th><th>Batch / Lot</th><th>QR / Manual Code</th><th>Received Qty</th><th>Remaining</th><th>Best Consumed Before</th><th>Commissary / Bin</th><th>Queue status</th></tr></thead><tbody id="batchBody"></tbody></table></div></div>
 </section>
 <section id="view-history" class="panel hide">
  <div class="card history-surface">
   <div class="history-toolbar">
    <div class="history-date-controls date-picker-wrap">
     <button class="btn small" id="datePrev" type="button" aria-label="Previous period">â€¹</button>
     <button class="btn history-date-trigger" id="dateRangeBtn" type="button" aria-expanded="false" aria-controls="datePicker"><svg viewBox="0 0 24 24" aria-hidden="true" style="fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg><span id="dateRangeLabel">This Month</span></button>
     <button class="btn small" id="dateNext" type="button" aria-label="Next period">â€º</button>
     <div id="datePicker" class="date-picker" hidden>
      <div class="date-picker-title">Select for</div>
      <div class="date-presets" id="datePresets"></div>
      <div class="range-modes" id="rangeModes"><button class="range-mode active" data-granularity="range" type="button">Range</button><button class="range-mode" data-granularity="day" type="button">Day</button><button class="range-mode" data-granularity="month" type="button">Month</button><button class="range-mode" data-granularity="year" type="button">Year</button></div>
      <div class="calendar-head"><div class="cal-nav"><button type="button" data-cal-shift="-1" aria-label="Previous calendar month">â€¹</button><span class="month-name" id="calendarMonthName"></span><button type="button" data-cal-shift="1" aria-label="Next calendar month">â€º</button></div><div class="cal-nav"><select id="calendarYear" aria-label="Calendar year"></select></div></div>
      <div class="calendar-grid" id="calendarGrid"></div>
      <div class="calendar-footer"><span class="selected-range" id="draftRangeLabel"></span><button type="button" class="btn" id="cancelDateRange">Cancel</button><button type="button" class="btn apply" id="applyDateRange">Apply</button></div>
     </div>
    </div>
    <div class="history-compare"><span>Compare to</span><select id="historyCompare" aria-label="Compare date range"><option value="previous">Previous period</option><option value="year">Previous year</option><option value="none">No comparison</option></select></div>
    <div class="history-export"><button class="btn red" id="exportToggle" type="button" aria-expanded="false" aria-controls="exportMenu">â†“ Export</button><div id="exportMenu" class="export-menu" hidden><button class="export-option" data-export="csv" type="button"><b>Raw data (CSV)</b><small>Every line item, timestamp and SKU</small></button><button class="export-option" data-export="xlsx" type="button"><b>Raw data (XLSX)</b><small>Transactions + summary sheet</small></button><button class="export-option" data-export="pdf" type="button"><b>Executive summary (PDF)</b><small>KPIs and charts, print-ready. Choose â€œSave as PDFâ€.</small></button></div></div>
   </div>
  </div>
  <div id="historyMetrics" class="history-summary"></div>
  <div class="grid-2">
   <div class="card"><div class="table-top"><div><h2>Inventory movement trend</h2><p class="subtext" id="historyTrendCaption">Audit events over the selected period</p></div></div><div id="historyChart" class="audit-chart"></div><div class="chart-legend"><span><i class="dot"></i>Inventory audit events</span><span class="mute">Y-axis: event count Â· X-axis: date</span></div></div>
   <div class="card"><h2>Movement mix</h2><p class="subtext">Share of recorded activity in the selected period.</p><div class="history-mix" id="historyMix"></div></div>
  </div>
  <div class="card history-surface"><div class="table-top"><div><h2>Inventory history &amp; audit trail</h2><p class="subtext">Filtered by the selected date range. Logs include exact timestamps, staff ID, action, quantity, location, QR/manual reference and notes.</p></div><span id="historyRowCount" class="badge b-neutral">0 entries</span></div>
   <details class="sync-settings"><summary>Offline synchronization</summary><div class="note warn-note" style="margin:10px 0">Queued actions are sent to this applicationâ€™s authenticated Laravel inventory endpoint when the connection returns. Stock changes only after the server validates and records each action.</div><div class="row"><button id="syncNow" class="btn red" type="button">Sync queued records</button><span id="syncQueueSummary" class="tiny"></span></div></details>
   <div class="history-table-tools"><label>Action filter<select id="historyFilter"><option value="">All actions</option><option>QR received</option><option>Recipe sale</option><option>Waste</option><option>Count adjustment</option><option>Physical count</option><option>Purchase order</option></select></label><label>Commissary filter<select id="historyCommissary"><option value="">All commissaries</option></select></label></div>
   <div class="table-wrap"><table><thead><tr><th>Timestamp</th><th>Staff ID</th><th>Action</th><th>Ingredient / Reference</th><th>Change</th><th>Commissary / Bin</th><th>Note</th></tr></thead><tbody id="historyBody"></tbody></table></div>
  </div>
 </section>
</main>
<div id="toast" role="status" aria-live="polite"></div>
<script>window.passengerSeatInventory = @js($inventoryBootstrap);</script>
<script>window.passengerSeatInventoryRoutes = @js(['lookup' => route('staff.inventory.receive.lookup'), 'preview' => route('staff.inventory.receive.preview'), 'confirm' => route('staff.inventory.receive.confirm'), 'packages' => route('staff.inventory.packages.store'), 'purchaseOrders' => route('staff.inventory.purchase-orders.store'), 'approvePurchaseOrder' => url('/staff/inventory/purchase-orders/__PO__/approve'), 'waste' => route('staff.inventory.waste.store'), 'counts' => route('staff.inventory.counts.store'), 'approveCount' => url('/staff/inventory/counts/__COUNT__/approve'), 'sync' => route('staff.inventory.sync')]);</script>
<script src="{{ asset('js/inventory.js') }}?v={{ filemtime(public_path('js/inventory.js')) }}"></script>
</body></html>

