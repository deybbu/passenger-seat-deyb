<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Passenger Seat Restaurant</title>
<meta name="description" content="Passenger Seat is an airplane-themed restaurant on the 3rd Floor of SM City San Mateo, Rizal. Boodle fights, private events and reservations. Open daily 10 AM to 9 PM.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/landing-design.css') }}">
</head>
<body>

<nav id="nav"><div class="wrap nv">
  <a href="#home" class="logo" aria-label="Passenger Seat home"><img class="lg" alt="Passenger Seat logo" src="{{ asset('images/landing-logo.png') }}"></a>
  <div class="links" id="links"><a href="#menu">Menu</a><a href="#story">Our Story</a><a href="#experience">Experience</a><a href="#reserve">Events</a><a href="#contact">Contact</a></div>
  <div class="act"><button class="btn hs" id="theme" aria-label="Toggle dark mode">Dark mode</button><a class="btn red" href="#reserve">Reserve</a><button class="btn burger" id="burger" aria-label="Menu" aria-expanded="false">&#9776;</button></div>
</div></nav>
<main>
<header class="hero" id="home" role="img" aria-label="Airplane-style dining cabin with cream seats and curved ceiling lights">
  <div>
    <div class="stars" aria-hidden="true">&#9733; &#9733; &#9733; &#9733; &#9733;</div>
    <div class="kick">Your table is boarding</div>
    <h1>Passenger Seat<span class="script">Fly and dine with us</span></h1>
    <p>Your destination for every celebration. Your next food trip starts right here. No plane ticket needed, just take a seat.</p>
    <div class="cta"><a class="btn red" href="#menu">Explore our menu</a><a class="btn" href="#reserve">Plan an event</a></div>
    <p class="open"><span class="live" aria-hidden="true"></span>Open daily 10:00 AM &ndash; 9:00 PM &middot; 3rd Floor, SM City San Mateo</p>
  </div>
  
</header>

<section id="menu"><div class="wrap">
<div class="vt" role="group" aria-label="Menu layout"><button type="button" data-v="show" aria-pressed="true">Showcase</button><button type="button" data-v="cards" aria-pressed="false">Card grid</button></div>
<div class="mg" id="vShow">
  <div class="dish"><img alt="Featured dish placeholder" id="big"><div class="dc"><h3>Boodle fight</h3><b id="dn"></b><p id="dd"></p></div></div>
  <div>
    <div class="ey">The menu</div><h2>Boodle fights</h2>
    <div class="list" id="list" role="list" aria-label="Boodle fight types"></div>
    <p class="note">Sample boodle fight types and generated photos. Replace with your actual set names, photos and prices from the menu database.</p>
  </div>
</div>
<div id="vCards" hidden>
  <div style="text-align:center"><div class="ey">The menu</div><h2>Boodle fights</h2><p class="note" style="margin-top:8px">Sample boodle fight types and generated photos. Replace with your actual set names, photos and prices.</p></div>
  <div class="cg2" id="cg2"></div>
  <div class="fr"><div><div class="ey">Feel the taste</div><h3 class="h3 big">Share the whole spread</h3><p class="fp">A boodle fight is a Filipino communal feast. Rice, grilled favorites and sides are laid out on banana leaves for everyone at the table to share.</p><a class="btn red" href="#reserve">Plan a boodle night</a></div><div class="plate"><img alt="" id="pl1"></div></div>
  <div class="fr rev"><div class="plate"><img alt="" id="pl2"></div><div><div class="ey">How it works</div><h3 class="h3 big">Gather, dig in, enjoy</h3><p class="fp">Everyone gathers around the spread and eats together. Ask our team about set sizes and options for your group.</p><a class="btn red" href="#reserve">Check availability</a></div></div>
</div>
</div></section>

<section class="soft" id="story"><div class="wrap">
  <div class="ey">Our story</div><h2>A journey, one shared plate at a time</h2>
  <div class="story" id="experience">
    <div class="card"><h3>Cabin-style seating</h3><p>Curved lights, sky-view windows and cream seats turn every table into a window seat.</p></div>
    <div class="card"><h3>Order from your seat</h3><p>Scan the QR code at your table and the kitchen gets your order right away.</p></div>
    <div class="card"><h3>Celebrations on board</h3><p>Birthdays, reunions and company dinners, with room for the whole group.</p></div>
  </div>
</div></section>

<section id="gallery"><div class="wrap"><div class="ey">The cabin</div><h2>Take your seat</h2><p style="color:var(--mute);max-width:520px">Curved lights, sky-view windows and cream seats. Step on board and see where your table is.</p>
  <div class="strip"><div class="ph" role="img" aria-label="Curved ceiling arches with warm lights" style="background-position:12% 30%;background-size:330%"></div><div class="ph" role="img" aria-label="Sky-view windows along the cabin wall" style="background-position:96% 62%;background-size:340%"></div><div class="ph" role="img" aria-label="The Passenger Seat sign at the end of the cabin" style="background-position:50% 52%;background-size:330%"></div><div class="ph" role="img" aria-label="Rows of cream seats with wooden tables" style="background-position:8% 92%;background-size:300%"></div></div>
  <p class="note">More photos of our food and guests coming soon.</p></div></section>

<section class="cta2"><div><h2>Indulge in an unforgettable dining journey</h2><p>Reserve your table today!</p><a class="btn white" href="#reserve">Reserve now</a></div></section>

<section id="reserve"><div class="wrap">
  <div class="ey">Events &amp; private dining</div><h2>Check event availability</h2>
  <p style="color:var(--mute);max-width:520px">Birthdays, christenings, corporate events and whole-restaurant rental. Pick an available date, then tell us about your event.</p>
  <div class="evg">
    <div class="card"><h3 class="h3">1. Event availability calendar</h3>
      <div class="legend" aria-label="Legend"><span><i class="dot a"></i>Available</span><span><i class="dot p"></i>Pending</span><span><i class="dot b"></i>Booked</span></div>
      <div class="cn"><button type="button" class="btn sm" id="pm" aria-label="Previous month">&lsaquo;</button><b id="mt"></b><button type="button" class="btn sm" id="nm" aria-label="Next month">&rsaquo;</button></div>
      <div class="cgrid" id="cg"></div>
      <p class="note">Sample calendar only. Actual availability must come from the restaurant's reservation records.</p>
      <label>Selected event date *<input id="sd" readonly placeholder="Select a date on the calendar"><span class="err" id="sd-e"></span></label>
    </div>
    <form id="form" novalidate>
      <h3 class="h3">2. Contact information</h3>
      <div class="two"><label>First name *<input id="fn" autocomplete="given-name"><span class="err" id="fn-e"></span></label><label>Last name *<input id="ln" autocomplete="family-name"><span class="err" id="ln-e"></span></label></div>
      <label>Email address *<input id="em" type="email" autocomplete="email"><span class="err" id="em-e"></span></label>
      <label>Mobile number *<input id="mb" inputmode="tel" autocomplete="tel"><span class="err" id="mb-e"></span></label>
      <h3 class="h3">3. Event requirements</h3>
      <label>Event type *<select id="et"><option value="">Select event type</option><option>Birthday</option><option>Wedding reception</option><option>Corporate event</option><option>Christening / Baptism</option><option>Reunion</option><option>Whole-restaurant rental</option><option>Other</option></select><span class="err" id="et-e"></span></label>
      <div class="two"><label>Preferred time *<select id="tm"><option value="">Select preferred time</option><option>Lunch</option><option>Afternoon</option><option>Dinner</option><option>Evening</option></select><span class="err" id="tm-e"></span></label>
      <label>Number of guests (pax) *<input id="px" type="number" min="1"><span class="err" id="px-e"></span></label></div>
      <label>Preferred package / budget<select id="pk"><option>Not sure yet</option><option>Boodle fight package</option><option>Set menu</option><option>Whole-restaurant rental</option><option>Custom, discuss with manager</option></select></label>
      <label>Food preferences / dietary restrictions<input id="fp"></label>
      <label>Special requests / message<textarea id="ms" rows="3"></textarea></label>
      <p class="note">Submitting an inquiry does not automatically confirm your event booking.</p>
      <button class="btn red" type="submit">Submit inquiry</button>
    </form>
    <div class="card" id="done" hidden role="status"><h3 class="h3">Inquiry received</h3><p style="color:var(--mute)">Our team will review your request. Your date is not confirmed yet.</p><div class="sum" id="sum"></div><button class="btn red" type="button" id="again">Send another inquiry</button></div>
  </div>
</div></section>

</main>
<footer id="contact"><div class="wrap fg"><div><img class="lg" alt="Passenger Seat logo" src="{{ asset('images/landing-logo.png') }}" style="height:64px;margin-bottom:10px"><small class="info"><span><b>Address</b><br>3rd Floor, SM City San Mateo, Gen. Luna Ave, San Mateo, Rizal, Philippines</span><span><b>Opening hours</b><br>10:00 AM &ndash; 9:00 PM daily</span><span><b>Contact</b><br>Available via our official Facebook page</span></small></div><a class="btn white" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=Passenger+Seat+SM+City+San+Mateo">Open in Google Maps</a></div></footer>
<nav class="sb" aria-label="Quick actions"><a class="btn" href="#menu">Order</a><a class="btn red" href="#reserve">Reserve</a></nav>
<div id="toast" role="status"></div>

@php
    $landingMenuData = isset($featuredItems) && $featuredItems->isNotEmpty()
        ? $featuredItems->map(fn($item) => [
            'n' => $item->name,
            'd' => $item->description ?? '',
            'photo' => $item->image_path ? asset($item->image_path) : null,
        ])->values()
        : null;
@endphp
@if($landingMenuData)
<script>
window.LANDING_MENU = @json($landingMenuData);
</script>
@endif

<script>
window.EVENT_AVAILABILITY = @json($availability ?? []);
</script>

<script src="{{ asset('js/landing-design.js') }}"></script>
</body>
</html>
