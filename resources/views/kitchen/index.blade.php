<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.favicon')
<title>Passenger Seat Kitchen</title>
<link rel="stylesheet" href="{{ asset('css/kitchen.css') }}?v={{ filemtime(public_path('css/kitchen.css')) }}"></head>
<body data-orders-url="{{ route('staff.kitchen.orders.data') }}" data-inventory-url="{{ route('staff.kitchen.inventory.data') }}" data-waste-url="{{ route('staff.kitchen.waste.data') }}" data-waste-store-url="{{ route('staff.kitchen.waste.store') }}" data-logout-url="{{ route('staff.logout') }}" data-staff-name="{{ auth()->user()->name }}" data-staff-role="{{ ucfirst(auth()->user()->role) }}" data-login-url="{{ route('staff.login') }}">
<header>
 <div class="lg" style="width:48px;height:48px;background:#fff;border:1px solid var(--bd);overflow:hidden"><img src="{{ asset('images/landing-logo.png') }}" alt="Passenger Seat logo" style="width:100%;height:100%;object-fit:contain"></div>
 <div class="ht"><b>Kitchen</b><small>Passenger Seat</small></div>
 <div class="tabs" role="tablist"><button role="tab" class="on" data-v="orders" aria-selected="true">Orders <span class="cnt" id="c-o">0</span></button><button role="tab" data-v="inv" aria-selected="false">Inventory <span class="cnt" id="c-i">0</span></button><button role="tab" data-v="waste" aria-selected="false">Food waste <span class="cnt" id="c-w">0</span></button></div>
 <span class="clk" id="clk"></span>
 <button class="theme-toggle" id="theme" type="button" role="switch" aria-checked="false" aria-label="Switch to dark mode" title="Switch to dark mode" data-mode="light"><span class="theme-knob"><svg class="i sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/></svg><svg class="i moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></span></button>
 <div class="me"><div class="staff-profile"><div class="av" aria-hidden="true"></div><span class="profile-copy"><b id="staffName"></b><small id="staffRole"></small></span></div><button class="logout-btn" id="out" type="button" aria-label="Log out" title="Log out"><svg class="i" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></button></div>
</header>
<div class="tick" id="tick" hidden role="alert"><svg class="i" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg><span id="tt"></span><button data-v="inv">Open stock</button></div>
<div class="wrap">
 <section id="v-orders">
  <div class="note">Sample orders for layout. When a customer sends a new order to the kitchen, recipe ingredients are deducted from stock automatically.</div>
  <div class="bar" id="flt"></div>
  <div class="cols" id="cols"></div>
 </section>
 <section id="v-inv" hidden>
  <div class="g2">
   <div class="stack">
    <div class="card"><h2>Ingredient stock</h2><div id="stock"></div></div>
    <div class="card"><h2>Automatic stock deduction</h2><p style="margin:0 0 12px;color:var(--tx2);font-size:14px">When a customer sends an order to the kitchen, the system deducts every configured item recipe from stock automatically. No manual deduction is needed.</p><div id="rcp"></div></div>
   </div>
   <div class="stack">
    <div class="card"><h2>Menu availability (automatic)</h2><p style="margin:0 0 8px;color:var(--tx2);font-size:14px">An item shows Not available on the public menu as soon as any ingredient in its recipe runs out.</p><div id="mn"></div></div>
    <div class="card"><h2>Inventory log (automatic)</h2><div id="logs"></div></div>
   </div>
  </div>
 </section>
 <section id="v-waste" hidden>
  <div class="note">Logging waste instantly deducts the quantity from inventory stock and updates the loss chart.</div>
  <div class="g2 gw">
   <div class="stack">
    <div class="card"><h2>Log waste</h2>
     <div class="wf">
      <div><label for="w-item">Item</label><select id="w-item"></select></div>
      <div><label for="w-qty">Qty</label><div class="qw"><input id="w-qty" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0"><select id="w-unit" aria-label="Unit of measurement"></select></div><span class="hint" id="w-av"></span></div>
      <div><label for="w-stage">Stage</label><select id="w-stage"></select></div>
      <div><label for="w-reason">Reason</label><select id="w-reason"></select><span class="hint" id="w-oth"></span></div>
      <div><label for="w-by">Logged by</label><select id="w-by"></select></div>
     </div>
     <div class="prev" id="w-prev" aria-live="polite"></div>
     <div class="er" id="w-err" role="alert"></div>
     <button class="btn p" data-a="waste">Log waste</button>
    </div>
    <div class="card">
     <div class="lh"><h2>Waste log</h2><div><label for="w-range" style="position:absolute;left:-9999px">Date range</label><select id="w-range"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="week">This week</option><option value="month">This month</option><option value="all">All time</option></select></div></div>
     <div class="tw"><table><thead><tr><th>Time</th><th>Item</th><th class="num">Qty</th><th>Stage</th><th>Reason</th><th>Logged by</th><th class="num">Cost lost</th></tr></thead><tbody id="w-rows"></tbody><tfoot id="w-foot"></tfoot></table></div>
    </div>
   </div>
   <div class="stack">
    <div class="card"><h2>Loss by stage</h2><p class="sub" id="w-sub"></p><div id="w-chart"></div></div>
    <div class="card"><h2>Near expiry</h2><div id="w-exp"></div></div>
   </div>
  </div>
 </section>
</div>
<div class="modal" id="w-modal" hidden><div class="mbox" role="dialog" aria-modal="true" aria-labelledby="m-t"><h2 id="m-t">Other reason</h2><p>Please type the specific reason for this waste.</p><label for="m-in" style="margin-top:4px">Specific reason</label><input id="m-in" maxlength="80" autocomplete="off" placeholder="e.g. Fridge power outage"><div class="er" id="m-err" role="alert"></div><div class="bar"><button class="btn" data-a="ocancel" type="button">Cancel</button><button class="btn p" data-a="osave" type="button">Save reason</button></div></div></div>
<div class="logout-modal" id="logoutModal" hidden><section class="logout-box" role="dialog" aria-modal="true" aria-labelledby="logoutTitle"><div class="logout-head"><h2 id="logoutTitle">Log out?</h2><button type="button" data-logout-cancel aria-label="Close">×</button></div><p>Are you sure you want to leave the kitchen workspace?</p><footer><button type="button" data-logout-cancel>Cancel</button><button class="confirm" id="confirmLogout" type="button">Log out</button></footer></section></div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/kitchen.js') }}?v={{ filemtime(public_path('js/kitchen.js')) }}"></script></body></html>

