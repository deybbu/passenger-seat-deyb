
const $=id=>document.getElementById(id);
const toast=t=>{const e=$("toast");e.textContent=t;e.classList.add("on");setTimeout(()=>e.classList.remove("on"),2600)};
function setTheme(t){document.documentElement.setAttribute("data-theme",t);$("theme").textContent=t=="dark"?"Light mode":"Dark mode";try{localStorage.setItem("ps_site2",t)}catch(e){}}
let th=null;try{th=localStorage.getItem("ps_site2")}catch(e){}
setTheme(th||(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"));
$("theme").onclick=()=>setTheme(document.documentElement.getAttribute("data-theme")=="dark"?"light":"dark");
$("burger").onclick=()=>{const o=$("links").classList.toggle("open");$("burger").setAttribute("aria-expanded",o)};
$("links").onclick=e=>{if(e.target.tagName=="A")$("links").classList.remove("open")};
const nav=$("nav"),sc=()=>nav.classList.toggle("sc",scrollY>40);addEventListener("scroll",sc,{passive:true});sc();
const MENU = (window.LANDING_MENU && window.LANDING_MENU.length > 0) ? window.LANDING_MENU : [
{n:"Meat boodle",d:"Grilled meats, rice and sides laid out on banana leaves."},
{n:"Seafood boodle",d:"Seafood, rice and sides laid out on banana leaves."},
{n:"Mixed boodle",d:"A mix of meat and seafood with rice and sides, made for sharing."}];
function leaf(i,w,h){const s="<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 "+w+" "+h+"'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='#2f6b3a'/><stop offset='1' stop-color='#6bb05a'/></linearGradient></defs><rect width='"+w+"' height='"+h+"' fill='url(#g)'/>"
 +"<g stroke='#1f4d2b' stroke-width='"+w/160+"' opacity='.35'>"+[.2,.4,.6,.8].map(t=>"<line x1='0' y1='"+h*t+"' x2='"+w+"' y2='"+h*(t-.1)+"'/>").join("")+"</g>"
 +"<ellipse cx='"+w/2+"' cy='"+h*.5+"' rx='"+w*.16+"' ry='"+h*.2+"' fill='#fff' opacity='.95'/>"
 +[0,1,2,3,4,5].map(k=>{const a=k*Math.PI/3+i;return "<ellipse cx='"+(w/2+Math.cos(a)*w*.3)+"' cy='"+(h*.5+Math.sin(a)*h*.28)+"' rx='"+w*.09+"' ry='"+h*.1+"' fill='"+["#C62828","#8a4b2a","#E53935","#d97706","#A61B1B","#b45309"][(k+i)%6]+"' transform='rotate("+(k*40)+" "+(w/2+Math.cos(a)*w*.3)+" "+(h*.5+Math.sin(a)*h*.28)+")'/>"}).join("")
 +"</svg>";return "data:image/svg+xml,"+encodeURIComponent(s)}
const photoOf=(m,i,w,h)=>m.photo||leaf(i,w,h);

let pick=0;
function render(){
  const m=MENU[pick];$("big").src=photoOf(m,pick,800,880);$("dn").textContent=m.n;$("dd").textContent=m.d;
  $("list").innerHTML=MENU.map((x,i)=>"<button class='bi' role='listitem' aria-pressed='"+(i==pick)+"' data-i='"+i+"'><img alt='' src='"+photoOf(x,i,176,128)+"'><span><b>"+x.n+"</b><small>"+x.d+"</small><em>Price from menu</em><span class='av'>&#10003; Available</span></span></button>").join("");
}
$("list").onclick=e=>{const b=e.target.closest("[data-i]");if(b){pick=+b.dataset.i;render()}};
render();
function cards(){$("cg2").innerHTML=MENU.map((m,i)=>"<article class='pc'><img alt='' src='"+photoOf(m,i,240,240)+"'><b>"+m.n+"</b><p>"+m.d+"</p><div class='pr'><span>Price from menu</span><button type='button' data-o='"+m.n+"' aria-label='Order "+m.n+"'>+</button></div></article>").join("");
  $("pl1").src=photoOf(MENU[0],0,600,600);$("pl2").src=photoOf(MENU[1 % MENU.length],1,600,600)}
cards();
$("cg2").onclick=e=>{const b=e.target.closest("[data-o]");if(b)toast("Scan the QR code at your table to order "+b.dataset.o+".")};
document.querySelector(".vt").onclick=e=>{const b=e.target.closest("[data-v]");if(!b)return;
  document.querySelectorAll(".vt button").forEach(x=>x.setAttribute("aria-pressed",x==b));
  $("vShow").hidden=b.dataset.v!="show";$("vCards").hidden=b.dataset.v!="cards"};
/* events calendar and inquiry form */
const Q=s=>document.querySelector(s);
let Y=new Date().getFullYear(),Mo=new Date().getMonth(),selD=null;
const MN=["January","February","March","April","May","June","July","August","September","October","November","December"];
const pad=n=>String(n).padStart(2,"0");
const getRealStatus=(y,m,d)=>{
  const dateStr=`${y}-${pad(m+1)}-${pad(d)}`;
  if(window.EVENT_AVAILABILITY && dateStr in window.EVENT_AVAILABILITY){
    return window.EVENT_AVAILABILITY[dateStr];
  }
  return "available";
};
function cal(){Q("#mt").textContent=MN[Mo]+" "+Y;
  const f=new Date(Y,Mo,1).getDay(),n=new Date(Y,Mo+1,0).getDate(),td=new Date();td.setHours(0,0,0,0);
  let h=["Su","Mo","Tu","We","Th","Fr","Sa"].map(x=>"<div class='dow'>"+x+"</div>").join("")+"<i></i>".repeat(f);
  for(let d=1;d<=n;d++){
    const curDate=new Date(Y,Mo,d);curDate.setHours(0,0,0,0);
    const past=curDate<td;
    const s=past?"past":getRealStatus(Y,Mo,d);
    const dateVal=`${Y}-${pad(Mo+1)}-${pad(d)}`;
    const isSel=(selD===dateVal);
    h+="<button type='button' class='d "+s+(isSel?" sel":"")+"' data-d='"+d+"' data-date='"+dateVal+"' "+(s=="available"?"":"disabled")+" aria-label='"+MN[Mo]+" "+d+", "+(past?"unavailable (past)":s)+"' aria-pressed='"+isSel+"'>"+d+(s=="pending"?"<small>Pending</small>":s=="booked"?"<small>Booked</small>":"")+"</button>";
  }
  Q("#cg").innerHTML=h}
Q("#cg").onclick=e=>{const b=e.target.closest(".d");if(!b||b.disabled)return;
  selD=b.dataset.date||(`${Y}-${pad(Mo+1)}-${pad(b.dataset.d)}`);
  Q("#sd").dataset.iso=selD;
  Q("#sd").value=new Date(Y,Mo,+b.dataset.d).toLocaleDateString("en-PH",{weekday:"long",month:"long",day:"numeric",year:"numeric"});
  Q("#sd-e").textContent="";cal()};
Q("#pm").onclick=()=>{if(--Mo<0){Mo=11;Y--}cal()};Q("#nm").onclick=()=>{if(++Mo>11){Mo=0;Y++}cal()};cal();
Q("#form").onsubmit=async e=>{e.preventDefault();let bad=0,first=null;
  const chk=(sel,ok,msg)=>{const el=Q(sel),er=Q(sel+"-e");if(ok(el.value.trim())){er.textContent="";el.removeAttribute("aria-invalid")}else{er.textContent=msg;el.setAttribute("aria-invalid","true");bad++;first=first||el}};
  chk("#sd",v=>!!selD,"Select an available date on the calendar.");
  chk("#fn",v=>v.length>0,"Enter your first name.");chk("#ln",v=>v.length>0,"Enter your last name.");
  chk("#em",v=>/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v),"Enter a valid email address.");
  chk("#mb",v=>v.replace(/\D/g,"").length>=10,"Enter a mobile number with at least 10 digits.");
  chk("#et",v=>v.length>0,"Select an event type.");chk("#tm",v=>v.length>0,"Select a preferred time.");chk("#px",v=>+v>=1,"Enter the number of guests.");
  if(bad){first.focus();toast("Please fix the highlighted fields");return}

  const btn=Q("#form button[type='submit']");
  btn.disabled=true;btn.textContent="Submitting...";

  const payload={
    event_date:selD,
    first_name:Q("#fn").value.trim(),
    last_name:Q("#ln").value.trim(),
    email:Q("#em").value.trim(),
    mobile_number:Q("#mb").value.trim(),
    event_type:Q("#et").value,
    preferred_time:Q("#tm").value,
    guest_count:parseInt(Q("#px").value,10),
    preferred_package:Q("#pk").value==="Not sure yet"?null:Q("#pk").value,
    dietary_restrictions:Q("#fp")?Q("#fp").value.trim()||null:null,
    special_requests:Q("#ms")?Q("#ms").value.trim()||null:null,
  };

  const tokenMeta=document.querySelector('meta[name="csrf-token"]');
  const csrfToken=tokenMeta?tokenMeta.getAttribute("content"):"";

  try{
    const res=await fetch("/events",{
      method:"POST",
      headers:{
        "Content-Type":"application/json",
        "Accept":"application/json",
        "X-CSRF-TOKEN":csrfToken,
      },
      body:JSON.stringify(payload),
    });
    const data=await res.json();
    if(res.ok && data.success){
      const ref=data.inquiry.reference;
      Q("#sum").innerHTML="<div><span>Reference</span><b>"+ref+"</b></div><div><span>Date</span><b>"+Q("#sd").value+"</b></div><div><span>Event</span><b>"+payload.event_type+", "+payload.guest_count+" guests</b></div><div><span>Request status</span><b>Pending review</b></div>";
      Q("#form").hidden=true;Q("#done").hidden=false;Q("#done").scrollIntoView({block:"center"});
      if(window.EVENT_AVAILABILITY){window.EVENT_AVAILABILITY[selD]="pending";}
      cal();
    }else{
      btn.disabled=false;btn.textContent="Submit inquiry";
      if(data.errors){
        Object.keys(data.errors).forEach(k=>{
          if(k==="event_date"&&Q("#sd-e")){Q("#sd-e").textContent=data.errors[k][0];}
          else if(Q("#"+k+"-e")){Q("#"+k+"-e").textContent=data.errors[k][0];}
        });
        toast(data.message||"Please fix the highlighted fields.");
      }else{
        toast(data.message||"Failed to submit inquiry. Please try again.");
      }
    }
  }catch(err){
    btn.disabled=false;btn.textContent="Submit inquiry";
    toast("Network error. Please try again.");
  }
};
Q("#again").onclick=()=>{
  const btn=Q("#form button[type='submit']");
  if(btn){btn.disabled=false;btn.textContent="Submit inquiry";}
  Q("#form").reset();selD=null;cal();Q("#form").hidden=false;Q("#done").hidden=true;
};
