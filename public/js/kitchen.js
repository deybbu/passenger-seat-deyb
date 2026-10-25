
let serverOffset=0;const $=s=>document.querySelector(s),M=60000,N=()=>Date.now()+serverOffset,ORDERS_URL=document.body.dataset.ordersUrl,INVENTORY_URL=document.body.dataset.inventoryUrl,WASTE_URL=document.body.dataset.wasteUrl,WASTE_STORE_URL=document.body.dataset.wasteStoreUrl;
function toast(m){const t=$("#toast");t.textContent=m;t.classList.add("on");clearTimeout(toast.t);toast.t=setTimeout(()=>t.classList.remove("on"),2600)}
const logoutDialog=$("#logoutModal"),logoutButton=$("#out"),logoutConfirm=$("#confirmLogout");
$("#staffName").textContent=document.body.dataset.staffName||"Kitchen Staff";
$("#staffRole").textContent=document.body.dataset.staffRole||"Kitchen";
$(".staff-profile .av").textContent=($("#staffName").textContent.trim().charAt(0)||"K").toUpperCase();
logoutButton?.addEventListener("click",()=>{logoutDialog.hidden=false;logoutConfirm.focus()});
logoutDialog?.querySelectorAll("[data-logout-cancel]").forEach(button=>button.addEventListener("click",()=>{logoutDialog.hidden=true;logoutButton.focus()}));
logoutDialog?.addEventListener("click",event=>{if(event.target===logoutDialog){logoutDialog.hidden=true;logoutButton.focus()}});
document.addEventListener("keydown",event=>{if(event.key==="Escape"&&logoutDialog&&!logoutDialog.hidden){logoutDialog.hidden=true;logoutButton.focus()}});
logoutConfirm?.addEventListener("click",async()=>{logoutConfirm.disabled=true;try{const response=await fetch(document.body.dataset.logoutUrl,{method:"POST",credentials:"same-origin",headers:{Accept:"text/html","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.content||"","X-Requested-With":"XMLHttpRequest"}});if(!response.ok)throw new Error("Logout failed. Please try again.");window.location.assign(document.body.dataset.loginUrl)}catch(error){logoutConfirm.disabled=false;toast(error.message||"Logout failed. Please try again.")}});
let O=[];
let I=[],RC=[],AVAIL={},EXPIRING=[],L=[],flt="All",act="Deduct",inventoryInitialized=false;
const miss=r=>AVAIL[r[2]]?.missing||[];
const avail=()=>RC.filter(r=>AVAIL[r[2]]?.available).map(r=>r[0]);
const stat=i=>i.s<=0?["er","Out of stock"]:!i.o&&i.s<=i.m?["wn","Low stock"]:["ok","In stock"];
const fm=ms=>{const s=Math.max(0,Math.floor(ms/1000));return String(Math.floor(s/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0")};
const lv=ms=>ms<8*M?["","On time"]:ms<15*M?["y","Slow"]:["r","Late"];
const clock=t=>new Date(t).toLocaleTimeString("en-PH",{hour:"numeric",minute:"2-digit",timeZone:"Asia/Manila"});
function tm(o){if(o.st==="Ready"||o.st==="Served"){const elapsed=Math.max(0,((o.st==="Served"?o.ct:N())||N())-(o.rt||o.t));return`<span class="tm n"><b>${fm(elapsed)}</b><i>${o.st==="Served"?"Pickup / service timer stopped":"Waiting for pickup / service"}</i></span>`}const l=lv(N()-o.t);return`<span class="tm ${l[0]}"><b>${fm(N()-o.t)}</b><i>${l[1]}</i></span>`}
const al=s=>/allerg/i.test(s);
function spi(o){if(!o.sp)return`<div class="nsp">No kitchen notes</div>`;const a=al(o.sp);return`<button class="btn sm si" data-a="si" data-id="${o.id}" aria-expanded="${o.x}">${o.x?"Hide":"View"} kitchen notes</button>${o.x?`<div class="spb ${a?'al':''}" role="note"><b>${a?"Allergy alert":"Kitchen Notes"}</b><br>${o.sp}</div>`:""}`}
function card(o){const c=(o.st==="Ready"||o.st==="Served")?"n":lv(N()-o.t)[0];
return`<article class="oc ${c}" data-id="${o.id}"><div class="oh"><div><b class="tb">${o.tb?"Table "+o.tb:"Take-out"}</b><small>${o.id}</small></div><span class="tys"><span class="ty">${o.ty}</span>${o.sp?`<span class="ty fl">${al(o.sp)?"Allergy":"Kitchen Notes"}</span>`:""}</span></div>
<div class="tr"><span>Placed ${clock(o.t)}</span><span class="tmw">${tm(o)}</span></div>
<ul>${o.it.map(i=>`<li><b>${i[0]}x</b><span>${i[1]}${i[2]?`<small>${i[2]}</small>`:""}</span></li>`).join("")}</ul>
${spi(o)}
${o.st==="Pending"?`<button class="btn p" data-a="start" data-id="${o.id}">Accept / Start Cooking</button>`:o.st==="Preparing"?`<button class="btn ok" data-a="ready" data-id="${o.id}">Mark as Ready</button>`:o.st==="Ready"?`<button class="btn ok" data-a="served" data-id="${o.id}">Picked Up / Served</button>`:`<div class="rd">✓ Picked Up / Served</div>`}</article>`}
function orders(){const S=["Pending","Preparing","Ready","Served"],sh=flt=="All"?S:[flt];
$("#flt").innerHTML=["All",...S].map(f=>`<button class="chip ${f==flt?'on':''}" data-f="${f}" aria-pressed="${f==flt}">${f}${f=="All"?"":" ("+O.filter(o=>o.st==f).length+")"}</button>`).join("");
$("#cols").style.setProperty("--n",sh.length);
$("#cols").innerHTML=sh.map(s=>{const l=O.filter(o=>o.st==s).sort((a,b)=>a.t-b.t);return`<div class="col"><h2>${s} <span class="cnt">${l.length}</span></h2>${l.length?l.map(card).join(""):`<div class="empty">No ${s.toLowerCase()} orders</div>`}</div>`}).join("");
$("#c-o").textContent=O.filter(o=>o.st=="Pending").length}
async function refreshOrders(){try{const response=await fetch(ORDERS_URL,{credentials:"same-origin",headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});if(!response.ok)throw new Error("Could not load kitchen orders.");const payload=await response.json(),expanded=new Map(O.map(o=>[o.id,Boolean(o.x)]));serverOffset=payload.server_now-Date.now();O=payload.orders.map(o=>({...o,x:expanded.get(o.id)??false}));orders()}catch(error){toast(error.message||"Kitchen orders could not be refreshed.")}}
async function refreshInventory(){try{const response=await fetch(INVENTORY_URL,{credentials:"same-origin",headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});if(!response.ok)throw new Error("Could not load kitchen inventory.");const payload=await response.json();serverOffset=payload.server_now-Date.now();I=payload.ingredients;RC=payload.recipes;AVAIL=payload.availability;EXPIRING=payload.near_expiry||[];L=payload.logs;inv();if(!inventoryInitialized){inventoryInitialized=true;wInit();refreshWaste()}}catch(error){toast(error.message||"Kitchen inventory could not be refreshed.")}}
async function refreshWaste(){try{const response=await fetch(`${WASTE_URL}?range=${encodeURIComponent(wr)}`,{credentials:"same-origin",headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});if(!response.ok)throw new Error("Could not load kitchen waste records.");const payload=await response.json();serverOffset=payload.server_now-Date.now();W=payload.entries;EXPIRING=payload.near_expiry||EXPIRING;waste()}catch(error){toast(error.message||"Kitchen waste records could not be refreshed.")}}
async function saveStatus(button,order,action){const status={start:"preparing",ready:"ready",served:"served"}[action];button.disabled=true;try{const response=await fetch(`${ORDERS_URL}/${order.dbid}/status`,{method:"PATCH",credentials:"same-origin",headers:{Accept:"application/json","Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({status})});const payload=await response.json();if(!response.ok)throw new Error(payload.message||"Order status could not be updated.");serverOffset=payload.server_now-Date.now();const expanded=order.x;const updated=payload.order;O=O.map(o=>o.dbid===updated.dbid?{...updated,x:expanded}:o);orders();toast(status==="preparing"?order.id+" is now preparing":status==="ready"?order.id+" ready. Service staff notified.":order.id+" picked up / served. Waiting timer stopped.")}catch(error){toast(error.message||"Order status could not be updated.");refreshOrders()}finally{button.disabled=false}}
function inv(){const low=I.filter(i=>!i.o&&i.s<=i.m);
$("#c-i").textContent=low.length;$("#tick").hidden=!low.length;
$("#tt").textContent="Warning: "+low.map(i=>`${i.n} ${i.s<=0?"out of stock":"running low"} (${i.s} ${i.u}, min ${i.m})`).join(" · ");
$("#stock").innerHTML=I.map(i=>{const s=stat(i);return`<div class="row"><div><b>${i.n}</b><small>${i.s} ${i.u} · minimum ${i.m} ${i.u}</small></div><span class="b ${s[0]}">${s[1]}</span></div>`}).join("");
$("#rcp").innerHTML=RC.map(r=>`<div class="row"><div><b>${r[0]}</b><small>Per serving: ${r[1].map(([k,q])=>q+" "+I[k].u+" "+I[k].n).join(" · ")}</small></div></div>`).join("");
$("#mn").innerHTML=RC.map(r=>{const m=miss(r);return`<div class="row"><div><b>${r[0]}</b><small>${m.length?"Out of stock: "+m.join(", "):"All ingredients in stock"}</small></div><span class="b ${m.length?'er':'ok'}">${m.length?"Not available":"Available"}</span></div>`}).join("");
$("#logs").innerHTML=L.length?L.slice(0,8).map(l=>`<div class="row"><div><b>${l.a} ${l.q} ${l.u} · ${l.n}</b><small>${clock(l.t)} · ${l.by||"System"} · ${l.note}</small></div></div>`).join(""):`<div class="empty">No automatic entries yet. A new customer order sent to the kitchen will create a stock deduction log.</div>`}
function view(v){$("#v-orders").hidden=v!="orders";$("#v-inv").hidden=v!="inv";$("#v-waste").hidden=v!="waste";if(v=="waste")waste();document.querySelectorAll(".tabs button").forEach(b=>{const on=b.dataset.v==v;b.classList.toggle("on",on);b.setAttribute("aria-selected",on)});scrollTo(0,0)}
document.addEventListener("click",e=>{const el=e.target.closest("button");if(!el)return;const d=el.dataset;
if(d.v)return view(d.v);if(d.f){flt=d.f;return orders()}
if(d.a==="start"||d.a==="ready"||d.a==="served"){const o=O.find(x=>x.id===d.id);if(o)saveStatus(el,o,d.a);return}
if(d.a=="si"){const o=O.find(x=>x.id==d.id);o.x=!o.x;orders();const b=document.querySelector('[data-a="si"][data-id="'+d.id+'"]');if(b)b.focus();return}
if(d.a=="waste")return logWaste();
if(d.a=="osave")return saveOther();
if(d.a=="ocancel")return cancelOther();
if(d.a=="oedit")return openOther();
if(d.a=="pre"){const index=I.findIndex(i=>String(i.id)===String(d.k));if(index>=0){$("#w-item").value=String(index);wItem();$("#w-qty").focus()}return}


if(el.id==="theme"){const r=document.documentElement;setTheme(r.dataset.theme==="dark"?"light":"dark",true)}});
setInterval(()=>{document.querySelectorAll(".oc").forEach(c=>{const o=O.find(x=>x.id==c.dataset.id);if(!o)return;c.querySelector(".tmw").innerHTML=tm(o);c.className="oc "+((o.st==="Ready"||o.st==="Served")?"n":lv(N()-o.t)[0])});$("#clk").textContent=new Date(N()).toLocaleTimeString("en-PH",{hour:"numeric",minute:"2-digit",second:"2-digit",timeZone:"Asia/Manila"})},1000);
function setTheme(theme,persist=true){
 const value=theme==="dark"?"dark":"light";
 document.documentElement.dataset.theme=value;
 if(persist){try{localStorage.setItem("ps_kitchen_theme",value)}catch(e){}}
 const next=value==="dark"?"light":"dark";
 const label="Switch to "+next+" mode";
 const b=$("#theme");if(b){b.setAttribute("aria-label",label);b.title=label;b.setAttribute("aria-checked",String(value==="dark"));b.dataset.mode=value}
}
let initialTheme="light";
try{initialTheme=localStorage.getItem("ps_kitchen_theme")||(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light")}catch(e){}
setTheme(initialTheme,false);
orders();inv();refreshOrders();refreshInventory();setInterval(refreshOrders,15000);setInterval(refreshInventory,15000);setInterval(refreshWaste,15000);
/* ===== Food waste monitoring ===== */
const STG=["Pre-consumer spoilage","Storage / receiving","Preparation / trimming","Cooking / production","Plate waste (returned)"],OTH="Others, please type",RSN=["Expired","Spoiled","Dropped / spilled","Overcooked / burnt","Wrong order","Customer returned","Overproduction",OTH],STAFF=[document.body.dataset.staffName||"Kitchen Staff"],RNG={today:"Today",yesterday:"Yesterday",week:"This week",month:"This month",all:"All time"};
const sod=()=>{const d=new Date(N()).toLocaleDateString("en-CA",{timeZone:"Asia/Manila"});return new Date(d+"T00:00:00+08:00").getTime()},DAY=864e5,H=36e5;
const peso=n=>"₱"+Number(n).toLocaleString("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2}),r2=n=>Math.round(n*100)/100;
const esc=t=>String(t).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
let W=[];
let wr="today";
const stamp=t=>{const day=new Date(t).toLocaleDateString("en-CA",{timeZone:"Asia/Manila"}),today=new Date(N()).toLocaleDateString("en-CA",{timeZone:"Asia/Manila"}),yesterday=new Date(N()-DAY).toLocaleDateString("en-CA",{timeZone:"Asia/Manila"});return(day===today?"Today":day===yesterday?"Yesterday":new Date(t).toLocaleDateString("en-PH",{month:"short",day:"numeric",timeZone:"Asia/Manila"}))+", "+clock(t)};
const isoDay=n=>{const d=new Date(sod()+n*DAY);return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0")};
const UL={kg:"kg (Kilograms)",g:"g (Grams)",L:"L (Liters)",mL:"mL (Milliliters)",pcs:"pcs (Pieces)",pack:"pack / bag",can:"can"},FAC={g:.001,mL:.001},FAM={kg:["kg","g"],g:["kg","g"],L:["L","mL"],mL:["L","mL"]},COUNT=["pcs","pack","can"];
const r3=n=>Math.round(n*1000)/1000,ulab=u=>u=="pack"?"pack/bag":u;
let prevRs="Expired",other="";
function wInit(){if(!I.length)return;const o=a=>a.map(x=>`<option>${x}</option>`).join("");
 $("#w-item").innerHTML=I.map((i,index)=>`<option value="${index}">${esc(i.n)}</option>`).join("");
 $("#w-stage").innerHTML=o(STG);$("#w-reason").innerHTML=o(RSN);
 $("#w-by").innerHTML='<option value="">Select staff</option>'+o(STAFF);wItem()}
function wItem(){const i=I[+$("#w-item").value],ok=FAM[i.u]||[i.u];
 $("#w-unit").innerHTML=Object.keys(UL).map(u=>`<option value="${u}"${ok.includes(u)?"":" disabled"}${u==i.u?" selected":""}>${UL[u]}</option>`).join("");
 wUnit()}
function wUnit(){const i=I[+$("#w-item").value],u=$("#w-unit").value;$("#w-qty").step=COUNT.includes(u)?"1":"0.01";
 $("#w-av").textContent="On hand: "+i.s+" "+ulab(i.u)+" · "+peso(i.c)+" per "+ulab(i.u);wPrev()}
function wCalc(){const i=I[+$("#w-item").value],u=$("#w-unit").value,q=parseFloat($("#w-qty").value),f=({kg:{kg:1,g:.001},g:{g:1,kg:1000},L:{L:1,mL:.001},mL:{mL:1,L:1000}}[i.u]||{[i.u]:1})[u]||0;return{i,u,q,bq:r3(q*f)}}
function wPrev(){const{i,u,q,bq}=wCalc(),p=$("#w-prev");
 if(!(q>0)){p.className="prev";p.innerHTML="Enter a quantity to see the cost of this loss.";return}
 const c=r2(bq*i.c);p.className="prev";
 p.innerHTML=`Logging <b>${q} ${ulab(u)}</b> of <b>${esc(i.n)}</b>${u!=i.u?` (${bq} ${i.u})`:""} will cost <b>${peso(c)}</b>.`+(bq>i.s+1e-9?` <b>Only ${i.s} ${ulab(i.u)} on hand.</b>`:"")}
async function logWaste(){const{i,u,q,bq}=wCalc(),E=$("#w-err"),button=document.querySelector('button[data-a="waste"]'),bad=m=>{E.textContent=m};
 if(!(q>0))return bad("Enter a quantity greater than zero.");
 if(COUNT.includes(u)&&!Number.isInteger(q))return bad("Whole numbers only for "+ulab(u)+".");
 if(bq>i.s+1e-9)return bad("Only "+i.s+" "+ulab(i.u)+" of "+i.n+" on hand. You can't log more than the current stock.");
 const rv=$("#w-reason").value;if(rv==OTH&&!other)return bad("Type the specific reason for \"Others\".");
 bad("");if(button)button.disabled=true;
 try{const response=await fetch(WASTE_STORE_URL,{method:"POST",credentials:"same-origin",headers:{Accept:"application/json","Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({ingredient_id:i.id,quantity:q,unit:u,stage:$("#w-stage").value,reason:rv,other_reason:other})});const payload=await response.json();if(!response.ok)throw new Error(Object.values(payload.errors||{}).flat()[0]||payload.message||"Waste could not be recorded.");$("#w-qty").value="";if(wr==="yesterday"){wr="today";$("#w-range").value="today"}other="";othHint();await Promise.all([refreshInventory(),refreshWaste()]);wItem();toast(payload.cost===null?i.n+" waste logged and stock updated. Cost is unavailable because no lot cost is recorded.":q+" "+ulab(u)+" "+i.n+" logged ("+peso(payload.cost)+"). Stock updated.")}catch(error){bad(error.message||"Waste could not be recorded.")}finally{if(button)button.disabled=false}}
function othHint(){const h=$("#w-oth");h.innerHTML=$("#w-reason").value==OTH&&other?`Reason: ${esc(other)}<button class="linkb" data-a="oedit" type="button">Edit</button>`:""}
function openOther(){$("#m-in").value=other;$("#m-err").textContent="";$("#w-modal").hidden=false;$("#m-in").focus()}
function closeOther(){$("#w-modal").hidden=true;$("#w-reason").focus()}
function saveOther(){const v=$("#m-in").value.trim();if(!v){$("#m-err").textContent="Please type a reason.";return}other=v;closeOther();othHint();$("#w-err").textContent=""}
function cancelOther(){if(!other){$("#w-reason").value=prevRs}closeOther();othHint()}
function waste(){
 const f=W,known=f.filter(w=>w.cost!==null),tot=r2(known.reduce((a,w)=>a+w.cost,0));
 $("#w-rows").innerHTML=f.length?f.map(w=>`<tr><td>${stamp(w.t)}</td><td>${esc(w.n)}</td><td class="num">${w.q} ${w.u}</td><td>${esc(w.st)}</td><td>${esc(w.rs)}</td><td>${esc(w.by)}</td><td class="num">${w.cost===null?"—":peso(w.cost)}</td></tr>`).join(""):`<tr><td colspan="7"><div class="empty">No waste logged for this period.</div></td></tr>`;
 $("#w-foot").innerHTML=f.length?`<tr><td colspan="6">${f.length} ${f.length>1?"entries":"entry"} · ${RNG[wr]}${known.length<f.length?` · ${f.length-known.length} uncosted`:` · ${known.length} costed`}</td><td class="num">${peso(tot)}</td></tr>`:"";
 const by={};known.forEach(w=>by[w.st]=(by[w.st]||0)+w.cost);const rows=Object.entries(by).sort((a,b)=>b[1]-a[1]),mx=rows.length?rows[0][1]:1;
 $("#w-sub").textContent="Y: waste stage · X: cost lost (₱) · "+RNG[wr];
 $("#w-chart").innerHTML=rows.length?rows.map(([k,v])=>`<div class="lb"><span>${esc(k)}</span><div class="trk" role="img" aria-label="${esc(k)} ${peso(v)}"><div class="fill" style="width:${Math.max(3,v/mx*100)}%"></div></div><span class="v">${peso(v)}</span></div>`).join(""):'<div class="empty">No data.</div>';
 const ex=EXPIRING;
 $("#w-exp").innerHTML=ex.length?ex.map(i=>{const c=i.e<=0?"er":"wn",lab=i.e<0?"Expired":i.e==0?"Today":i.e==1?"Tomorrow":"In "+i.e+" days";
  return`<div class="row"><div><b>${esc(i.n)}: ${i.s} ${i.u}</b><small>${i.date} · ${peso(r2(i.s*i.c))} at risk · consider a chef's special</small></div><span class="b ${c}">${lab}</span><button class="btn sm" data-a="pre" data-k="${i.id}">Log</button></div>`}).join(""):'<div class="empty">Nothing close to expiry.</div>';
 $("#c-w").textContent=ex.length;$("#c-w").hidden=!ex.length}document.addEventListener("input",e=>{if(e.target.id=="w-qty"){$("#w-err").textContent="";wPrev()}});
document.addEventListener("keydown",e=>{if($("#w-modal").hidden)return;if(e.key=="Escape")cancelOther();if(e.key=="Enter"&&e.target.id=="m-in"){e.preventDefault();saveOther()}});
document.addEventListener("click",e=>{if(e.target.id=="w-modal")cancelOther()});
document.addEventListener("change",e=>{if(e.target.id=="w-item"){$("#w-err").textContent="";wItem()}if(e.target.id=="w-unit"){$("#w-err").textContent="";wUnit()}if(e.target.id=="w-reason"){$("#w-err").textContent="";if(e.target.value==OTH)openOther();else{prevRs=e.target.value;othHint()}}if(e.target.id=="w-range"){wr=e.target.value;refreshWaste()}});
