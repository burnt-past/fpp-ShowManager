<?php
// Full-screen kiosk for wall tablets.
// URL: plugin.php?plugin=<dir>&page=kiosk.php&nopage=1
$playlists = [];
$playlistDir = "/home/fpp/media/playlists";
if (is_dir($playlistDir)) {
    foreach (glob("$playlistDir/*.json") as $f) $playlists[] = basename($f, '.json');
    sort($playlists);
}
$AJAX = 'plugin.php?plugin=' . basename(__DIR__) . '&page=ajax.php&nopage=1';
$plJson = json_encode($playlists);
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<title>Show Kiosk</title>
<style>
:root{
  --bg:#071019;--card:#0f1f2e;--raise:#16293b;--high:#1d3648;
  --border:rgba(255,255,255,.08);--brdHi:rgba(120,200,255,.26);
  --text:#e8f1fb;--sub:#8aa2ba;--mut:#516678;
  --mint:#2fd3c4;--amber:#f6b53f;--red:#f5566f;--s1:#5bc2f5;--s2:#7cd0ff;
  --mintInk:#06210f;
  --amberBg:rgba(246,181,63,.16);--amberBrd:rgba(246,181,63,.42);
  --redBg:rgba(245,86,111,.15);--redBrd:rgba(245,86,111,.45);
  --mintBg:rgba(47,211,196,.14);
  --font:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
  --mono:ui-monospace,'SF Mono','Cascadia Code',Menlo,Consolas,monospace;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;height:100%}
body{background:var(--bg);color:var(--text);font-family:var(--font);overflow-x:hidden}
button{font-family:var(--font);cursor:pointer;appearance:none}
@keyframes k-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.5);opacity:.55}}
@keyframes k-glow{0%,100%{filter:brightness(.62)}50%{filter:brightness(1.3)}}
@keyframes k-slide{from{transform:translateY(8px);opacity:0}to{transform:translateY(0);opacity:1}}
.bg{position:fixed;inset:0;pointer-events:none}
.bg.img{opacity:.66;background:url(data:image/jpeg;base64,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) center/cover no-repeat;filter:blur(20px) saturate(1.3);transform:scale(1.07)}
.bg.scrim{background:linear-gradient(180deg,rgba(7,16,25,.42),rgba(7,16,25,.84))}
.bg.bloom{background:radial-gradient(52% 42% at 50% -4%,rgba(246,181,63,.24),transparent 70%),radial-gradient(40% 34% at 5% 18%,rgba(91,194,245,.20),transparent 70%),radial-gradient(40% 34% at 96% 14%,rgba(124,208,255,.18),transparent 70%),radial-gradient(46% 42% at 78% 92%,rgba(56,190,232,.16),transparent 72%)}
.wrap{position:relative;z-index:1;width:100%;height:100vh;display:flex;flex-direction:column;padding:14px 22px 16px}
#k-main{flex:1;min-height:0;display:flex;gap:12px;margin-bottom:12px;text-align:left}
#k-side{flex:1;min-width:280px;display:flex;flex-direction:column;gap:12px;min-height:0}
#k-3d-card{flex:2;display:none;flex-direction:column;padding:10px 12px 12px;min-width:0}
@media (max-width:900px),(max-height:560px){
  .wrap{height:auto;min-height:100vh}
  #k-main{flex-direction:column}
  #k-3d-card{min-height:300px}
}
.card{background:linear-gradient(160deg,rgba(255,255,255,.04),transparent),var(--card);border:1px solid var(--liveBrd,var(--brdHi));border-radius:18px;padding:20px 22px;transition:border-color .6s}
#k-state{transition:color .5s}
#k-dot{transition:background .5s,box-shadow .5s}
.klabel{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--mut);font-weight:700}
.btn{background:var(--raise);border:1px solid var(--liveBrd,var(--border));color:var(--text);font-weight:700;font-size:15px;padding:12px 22px;border-radius:12px;min-height:48px;transition:border-color .6s,background .6s,color .6s}
.kvbtn{width:54px;height:54px;border-radius:14px;font-size:28px;font-weight:800;background:var(--raise);border:1px solid var(--liveBrd,var(--brdHi));color:var(--text);transition:border-color .6s,background .6s}
#k-start{width:100%;border:none;background:var(--live1,var(--mint));color:var(--liveInk,var(--mintInk));font-weight:800;font-size:19px;padding:15px 20px;border-radius:14px;min-height:56px;transition:background .6s,color .6s}
#k-stop{width:100%;position:relative;overflow:hidden;background:var(--raise);color:var(--text);border:2px solid var(--live2Brd,var(--redBrd));font-weight:800;font-size:19px;padding:15px 20px;border-radius:14px;min-height:56px;transition:border-color .6s,background .6s}
/* Liquid glass where supported — near-clear fill, heavy blur/saturation, and
   edge highlights do the work. The opaque look above is the fallback. */
@supports ((backdrop-filter:blur(4px)) or (-webkit-backdrop-filter:blur(4px))){
  .card{
    background:linear-gradient(135deg,rgba(255,255,255,.10),rgba(255,255,255,.02) 45%,rgba(255,255,255,.06));
    border:1px solid var(--liveBrd,rgba(255,255,255,.18));
    box-shadow:inset 0 1px 0 rgba(255,255,255,.22),inset 0 -1px 0 rgba(255,255,255,.05),0 10px 34px rgba(0,0,0,.28);
    -webkit-backdrop-filter:blur(24px) saturate(1.6);backdrop-filter:blur(24px) saturate(1.6);
  }
  .btn,.kvbtn,#k-stop{
    background:rgba(255,255,255,.08);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.18);
    -webkit-backdrop-filter:blur(18px) saturate(1.5);backdrop-filter:blur(18px) saturate(1.5);
  }
  .btn{border-color:var(--liveBrd,rgba(255,255,255,.16))}
  .kvbtn{border-color:var(--liveBrd,rgba(255,255,255,.20))}
  #k-start{
    background:var(--live1A,rgba(47,211,196,.26));
    border:1.5px solid var(--live1,var(--mint));color:#fff;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.28);
    -webkit-backdrop-filter:blur(18px) saturate(1.5);backdrop-filter:blur(18px) saturate(1.5);
  }
  .toast{background:rgba(255,255,255,.10);-webkit-backdrop-filter:blur(20px) saturate(1.5);backdrop-filter:blur(20px) saturate(1.5)}
}
#toasts{position:fixed;right:18px;bottom:18px;z-index:100;display:flex;flex-direction:column;gap:10px}
.toast{display:flex;align-items:center;gap:10px;padding:14px 17px;border-radius:11px;background:var(--high);border:1px solid var(--brdHi);color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.35);min-width:240px;animation:k-slide .25s ease;font-size:15px;font-weight:500}
.toast .dot{width:8px;height:8px;border-radius:50%;flex:none}
</style>
</head>
<body>
<div class="bg img"></div>
<div class="bg scrim"></div>
<div class="bg bloom"></div>
<div class="bg" id="k-livetint"></div>
<div class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
    <span class="klabel" style="font-size:13px;letter-spacing:.14em" id="k-title">Show Kiosk</span>
    <span style="font-family:var(--mono);font-size:15px;color:var(--sub)" id="k-clock"></span>
  </div>
  <div id="k-garland" style="position:relative;height:40px;margin:4px -2px 10px;flex:none"></div>

  <div id="k-main">
    <!-- Live 3D preview (fpp-plugin-3DViewer) — auto-hidden when the plugin isn't installed -->
    <div class="card" id="k-3d-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;padding:0 4px">
        <div class="klabel">Live 3D Preview</div>
        <button class="btn" id="k-3d-tgl" style="min-height:34px;padding:5px 14px;font-size:13px">Hide</button>
      </div>
      <iframe id="k-3d" style="flex:1;min-height:0;width:100%;border:none;border-radius:10px;background:#05070c;display:block"></iframe>
    </div>
    <div id="k-side">
      <div class="card" style="flex:none;padding:16px 18px">
        <div style="display:flex;align-items:center;gap:10px">
          <span id="k-dot" style="width:14px;height:14px;border-radius:50%;background:var(--mut);flex:none"></span>
          <span id="k-state" style="font-size:clamp(22px,3.8vh,34px);font-weight:900;letter-spacing:-.01em;line-height:1;color:var(--sub)">…</span>
        </div>
        <div id="k-sub" style="font-size:clamp(14px,2.2vh,19px);font-weight:700;margin-top:8px"></div>
      </div>
      <div class="card" style="flex:1;min-height:0;overflow:auto">
        <div class="klabel" style="margin-bottom:6px">Today's Schedule</div>
        <div id="k-sched"><div style="color:var(--mut);font-size:15px;padding:10px 0">Loading…</div></div>
      </div>
      <div class="card" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;flex:none">
        <div class="klabel" style="align-self:flex-start">Temporary Volume</div>
        <div style="display:flex;align-items:center;gap:16px">
          <button class="kvbtn" onclick="kVol(-5)">−</button>
          <div id="k-vol" style="font-size:36px;font-weight:800;font-family:var(--mono);min-width:110px;color:var(--live1,var(--amber));transition:color .6s">—</div>
          <button class="kvbtn" onclick="kVol(5)">+</button>
        </div>
        <div style="font-size:12px;color:var(--sub)">Temporary — reverts at the next show</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;flex:none">
        <button id="k-start" onclick="kStart()">▶ Start show</button>
        <button id="k-stop">
          <span style="position:absolute;inset:0;opacity:.28"><span id="k-holdbar" style="display:block;width:0%;height:100%;background:var(--live2,var(--red));transition:width .05s linear"></span></span>
          <span style="position:relative">Hold to stop</span>
        </button>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:12px;justify-content:center;align-items:center;flex-wrap:wrap;padding-top:10px;border-top:1px solid var(--border);flex:none">
    <span style="font-size:13px;color:var(--sub);margin-right:4px">Disable system:</span>
    <button class="btn" onclick="kDisable('1h')">1 hour</button>
    <button class="btn" onclick="kDisable('tonight')">Tonight</button>
    <button class="btn" id="k-enable" style="display:none;background:var(--live1,var(--mint));border-color:var(--live1,var(--mint));color:var(--liveInk,var(--mintInk))" onclick="kEnable()">Re-enable now</button>
  </div>
</div>
<div id="toasts"></div>
<script>
const AJAX='<?= $AJAX ?>';
const PLAYLISTS=<?= $plJson ?>;
let state={playing:false,cur:'',vol:null,shows:[],nextIdx:-1,disabledUntil:null,host:''};

function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmtDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
function toast(msg,kind){
  const c=document.getElementById('toasts');
  const d=document.createElement('div');d.className='toast';
  const col=kind==='ok'?'var(--mint)':kind==='amber'?'var(--amber)':kind==='err'?'var(--red)':'var(--mut)';
  d.innerHTML='<span class="dot" style="background:'+col+'"></span><span>'+escH(msg)+'</span>';
  d.onclick=()=>d.remove();c.appendChild(d);setTimeout(()=>d.remove(),3600);
}

/* garland */
(function(){
  const el=document.getElementById('k-garland');
  const N=24,sag=18,baseY=8,cols=['#f6b53f','#5fc8ff','#fff1cf','#38d6e8','#ffd27a'];
  let gp='',prev=0,bulbs='';
  for(let i=0;i<N;i++){
    const f=i/(N-1),x=+(f*1000).toFixed(1);
    if(i===0)gp='M'+x+','+baseY;else{const mx=((prev+x)/2).toFixed(1);gp+=' Q'+mx+','+(baseY+sag)+' '+x+','+baseY;}
    prev=x;const c=cols[i%cols.length];
    bulbs+=`<span style="position:absolute;left:${(f*100).toFixed(3)}%;top:${baseY}px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center"><span style="width:3px;height:5px;background:#33412f;border-radius:0 0 1px 1px"></span><span class="gbulb" style="width:11px;height:15px;border-radius:50% 50% 48% 48% / 60% 60% 42% 42%;background:radial-gradient(circle at 34% 26%,rgba(255,255,255,.6),${c} 70%);box-shadow:0 0 6px ${c},0 0 14px ${c};animation:k-glow 2s ease-in-out infinite;animation-delay:${(i*0.11).toFixed(2)}s"></span></span>`;
  }
  el.innerHTML=`<svg viewBox="0 0 1000 52" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;overflow:visible"><path d="${gp}" fill="none" stroke="#33412f" stroke-width="2.2" stroke-linecap="round"></path></svg>`+bulbs;
})();

/* clock */
function tickClock(){
  document.getElementById('k-clock').textContent=new Date().toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});
}
tickClock();setInterval(tickClock,1000);

/* data polling — fast lane for playback state (1s, same cadence as FPP's
   own UI), slow lane for schedule + disable override (5s) */
let volTouched=0;
function _cleanName(s){s=String(s||'').split('/').pop();return s.replace(/\.[^.]+$/,'');}
async function pollFast(){
  const fpp=await fetch('/api/fppd/status').then(r=>r.json()).catch(()=>({}));
  state.playing=fpp.status===1||fpp.status==='playing';
  state.cur=fpp.current_playlist?.playlist||fpp.current_playlist?.name||'';
  state.seq=_cleanName(fpp.current_sequence||fpp.current_song||'');
  if(Date.now()-volTouched>2000)state.vol=fpp.volume!=null?fpp.volume:null;
  state.host=fpp.HostName||fpp.hostname||'';
  const now=new Date().toTimeString().slice(0,5);
  state.nextIdx=state.shows.findIndex(s=>s.time>=now);
  render();
}
async function pollSlow(){
  const [sched,ov]=await Promise.all([
    fetch(AJAX+'&action=get_month&year='+new Date().getFullYear()+'&month='+(new Date().getMonth()+1)).then(r=>r.json()).catch(()=>({entries:[]})),
    fetch(AJAX+'&action=get_override').then(r=>r.json()).catch(()=>({})),
  ]);
  state.disabledUntil=ov.disabled_until||null;
  const today=fmtDate(new Date());
  const ents=(sched.entries||[]).filter(e=>e.date===today);
  const blk=ents.filter(e=>e.type==='blackout');
  const fullBlackout=blk.some(b=>!b.start_time&&!b.end_time);
  let shows=ents.filter(e=>e.type==='show').sort((a,b)=>a.time.localeCompare(b.time));
  if(fullBlackout)shows=[];
  else if(blk.length)shows=shows.filter(s=>!blk.some(b=>(b.start_time||'00:00')<=s.time&&s.time<=(b.end_time||'23:59')));
  state.shows=shows;
  const now=new Date().toTimeString().slice(0,5);
  state.nextIdx=shows.findIndex(s=>s.time>=now);
  render();
}
async function poll(){await Promise.all([pollSlow(),pollFast()]);}

function render(){
  const dot=document.getElementById('k-dot');
  const st=document.getElementById('k-state');
  const sub=document.getElementById('k-sub');
  if(state.host)document.getElementById('k-title').textContent=state.host+' · Show Kiosk';
  if(state.disabledUntil){
    dot.style.background='var(--red)';dot.style.boxShadow='0 0 20px var(--red)';dot.style.animation='none';
    st.style.color='var(--red)';st.textContent='DISABLED';
    const t=new Date(state.disabledUntil);
    sub.textContent='until '+t.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
  }else if(state.playing){
    dot.style.background='var(--live1,var(--amber))';dot.style.boxShadow='0 0 20px var(--live1,var(--amber))';dot.style.animation='k-pulse 1.6s ease-in-out infinite';
    st.style.color='var(--live1,var(--amber))';st.textContent='SHOW RUNNING';
    sub.textContent=state.seq&&state.seq!==state.cur?(state.seq+(state.cur?' · '+state.cur:'')):state.cur;
  }else{
    dot.style.background='var(--mut)';dot.style.boxShadow='none';dot.style.animation='none';
    st.style.color='var(--sub)';st.textContent='IDLE';
    sub.textContent=state.nextIdx>=0?('Next show '+state.shows[state.nextIdx].time):'No more shows today';
  }
  document.getElementById('k-enable').style.display=state.disabledUntil?'':'none';
  const vol=document.getElementById('k-vol');
  vol.textContent=state.vol!=null?state.vol+'%':'—';
  const nowIdx=state.playing?(state.nextIdx<0?state.shows.length-1:state.nextIdx-1):-1;
  const rows=state.shows.map((s,i)=>{
    const isNow=i===nowIdx&&nowIdx>=0;
    const isNext=i===state.nextIdx&&!isNow;
    const isPast=(state.nextIdx<0||i<state.nextIdx)&&!isNow;
    const badge=isNow?'<span style="margin-left:auto;font-size:13px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:4px 12px;border-radius:999px;background:var(--amberBg);color:var(--amber)">Now</span>'
      :isNext?'<span style="margin-left:auto;font-size:13px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:4px 12px;border-radius:999px;background:var(--mintBg);color:var(--mint)">Next</span>':'';
    const name=s.playlist||(s.playlists||[]).join(' / ')||'(show)';
    return `<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);opacity:${isPast?.42:1}">
      <span style="font-family:var(--mono);font-size:17px;width:60px">${escH(s.time)}</span>
      <span style="font-size:16px">${escH(name)}</span>${badge}</div>`;
  }).join('');
  document.getElementById('k-sched').innerHTML=rows||'<div style="color:var(--mut);font-size:15px;padding:10px 0">No shows today</div>';
}

/* actions */
function _nextPlaylist(){
  if(state.nextIdx>=0){
    const s=state.shows[state.nextIdx];
    if(s.playlist)return s.playlist;
    if(s.playlists&&s.playlists.length)return s.playlists[0];
  }
  return PLAYLISTS[0]||'';
}
async function kStart(){
  const pl=_nextPlaylist();
  if(!pl)return toast('No playlists available','err');
  if(state.disabledUntil)await fetch(AJAX+'&action=set_disabled&mode=off');
  const r=await fetch(AJAX+'&action=trigger_playlist&playlist='+encodeURIComponent(pl)).then(r=>r.json()).catch(()=>({}));
  toast(r.ok?('Started "'+pl+'"'):'Start failed','ok');
  setTimeout(poll,1200);
}
async function kStop(){
  await fetch(AJAX+'&action=stop_playlist');
  toast('Show stopped','mut');
  setTimeout(poll,1200);
}
async function kDisable(mode){
  const r=await fetch(AJAX+'&action=set_disabled&mode='+mode).then(r=>r.json()).catch(()=>({}));
  toast('System disabled '+(mode==='1h'?'for 1 hour':'for tonight'),'amber');
  poll();
}
async function kEnable(){
  await fetch(AJAX+'&action=set_disabled&mode=off');
  toast('System re-enabled','ok');
  poll();
}
async function kVol(delta){
  if(state.vol==null)return;
  const v=Math.max(0,Math.min(100,state.vol+delta));
  state.vol=v;
  volTouched=Date.now();
  document.getElementById('k-vol').textContent=v+'%';
  await fetch('/api/system/volume',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({volume:v})}).catch(()=>{});
}

/* hold-to-stop */
(function(){
  const btn=document.getElementById('k-stop');
  const bar=document.getElementById('k-holdbar');
  let t0=0,timer=null;
  function cancel(){if(timer){clearInterval(timer);timer=null;}bar.style.width='0%';}
  btn.addEventListener('pointerdown',()=>{
    if(timer)return;
    t0=Date.now();
    timer=setInterval(()=>{
      const pct=Math.min(100,(Date.now()-t0)/1100*100);
      bar.style.width=pct+'%';
      if(pct>=100){cancel();kStop();}
    },30);
  });
  btn.addEventListener('pointerup',cancel);
  btn.addEventListener('pointerleave',cancel);
})();

/* 3D viewer embed — shown only when fpp-plugin-3DViewer is installed */
(async function(){
  const card=document.getElementById('k-3d-card');
  const frame=document.getElementById('k-3d');
  const tgl=document.getElementById('k-3d-tgl');
  let ok=false;
  try{ok=(await fetch('/3dviewer/index.html',{method:'HEAD'})).ok;}catch(e){}
  if(!ok)return;
  card.style.display='flex';
  let shown=true;try{shown=localStorage.getItem('k3d')!=='off';}catch(e){}
  // The viewer ships dev chrome (HUD, control panel, hints). We're same-origin,
  // so hide it from outside — no changes needed inside the viewer.
  frame.addEventListener('load',()=>{
    try{
      const d=frame.contentDocument;
      const st=d.createElement('style');
      st.textContent='#hud,#panel,.hint{display:none !important}';
      d.head.appendChild(st);
    }catch(e){}
  });
  function apply(){
    frame.style.display=shown?'block':'none';
    card.style.flex=shown?'2':'0 0 auto';   // collapsed card yields its space to the side column
    tgl.textContent=shown?'Hide':'Show';
    // Unload when hidden so the tablet GPU and the color feed go idle
    if(shown){if(frame.getAttribute('src')!=='/3dviewer/')frame.src='/3dviewer/';}
    else frame.src='about:blank';
  }
  tgl.onclick=()=>{shown=!shown;try{localStorage.setItem('k3d',shown?'on':'off');}catch(e){}apply();};
  apply();
})();

/* Live garland — mirrors a real string from the show onto the page's garland
   using the same-origin 2D virtual-display SSE feed plus the 3D viewer's baked
   geometry. Auto-picks the widest string-like model; override with
   ?garland=Model Name. Falls back to the default twinkle when the show is dark
   or the 3D viewer plugin isn't installed. */
(async function(){
  // Diagnostics: always logs to the console as [garland]; add &garlanddebug=1
  // to the kiosk URL for an on-screen readout.
  const dbgOn=new URLSearchParams(location.search).has('garlanddebug');
  let dbgEl=null;
  const dbg={stage:'init',frames:0,writes:0,feed:'—',model:'—'};
  function glog(stage,detail){
    dbg.stage=stage;
    console.info('[garland]',stage,detail??'');
    if(dbgOn){
      if(!dbgEl){
        dbgEl=document.createElement('div');
        dbgEl.style.cssText='position:fixed;left:10px;bottom:10px;z-index:200;background:rgba(0,0,0,.75);color:#7fb0ff;font:12px/1.6 monospace;padding:8px 12px;border-radius:8px;border:1px solid #2a4a82;white-space:pre';
        document.body.appendChild(dbgEl);
      }
      dbgEl.textContent='garland: '+dbg.stage+(detail?' — '+detail:'')
        +'\nmodel:   '+dbg.model
        +'\nfeed:    '+dbg.feed
        +'\nframes:  '+dbg.frames+'   bulb writes: '+dbg.writes;
    }
  }
  if(dbgOn)setInterval(()=>glog(dbg.stage),1000);

  const bulbs=[...document.querySelectorAll('#k-garland .gbulb')];
  const NB=bulbs.length;
  if(!NB)return glog('no garland bulbs in DOM');
  const defaults=bulbs.map(b=>b.style.cssText);
  let meta,geo;
  try{
    const mres=await fetch('/3dviewer/data/models.json');
    if(!mres.ok)return glog('models.json HTTP '+mres.status,'is fpp-plugin-3DViewer installed? (/3dviewer/)');
    meta=await mres.json();
    const gres=await fetch('/3dviewer/data/geometry.bin');
    if(!gres.ok)return glog('geometry.bin HTTP '+gres.status);
    const buf=await gres.arrayBuffer();
    const dv=new DataView(buf);
    const magic=String.fromCharCode(dv.getUint8(0),dv.getUint8(1),dv.getUint8(2),dv.getUint8(3));
    if(magic!=='F3DG')return glog('bad geometry magic',magic);
    const n=dv.getUint32(8,true);
    geo=new Float32Array(buf,16,n*3);
    glog('geometry loaded',n+' px, '+(meta.models||[]).length+' models, previewHeight '+meta.previewHeight);
  }catch(e){return glog('data load failed',String(e));}

  // ── candidate models & per-pixel feed keys (same scheme as feed.js) ──
  const models=meta.models||[];
  const nAll=geo.length/3;
  const keyArr=new Int32Array(nAll);
  for(let i=0;i<nAll;i++){
    keyArr[i]=(Math.round(geo[i*3])<<12)|((meta.previewHeight-Math.round(geo[i*3+1]))&0xfff);
  }
  function spanOf(m){
    let minx=1e12,maxx=-1e12,miny=1e12,maxy=-1e12;
    for(let i=m.start;i<m.start+m.count;i++){
      const x=geo[i*3],y=geo[i*3+1];
      if(x<minx)minx=x;if(x>maxx)maxx=x;
      if(y<miny)miny=y;if(y>maxy)maxy=y;
    }
    return {minx,maxx,w:maxx-minx,h:maxy-miny};
  }

  // ── shared paint state ──
  const cols=new Uint8Array(NB*3);
  let pending=null,dirty=false,lastLit=0,liveLook=false;
  const tint=document.getElementById('k-livetint');
  const want=new URLSearchParams(location.search).get('garland');

  function pickStatic(){
    if(want){
      const m=models.find(x=>x.name.toLowerCase()===want.toLowerCase());
      if(!m)glog('model not found',want);
      return m||null;
    }
    let best=null,bestScore=-1;
    for(const m of models){
      if(m.count<NB||m.count>1500)continue;
      const s=spanOf(m);
      if(s.w<s.h*2)continue;                        // want something horizontal
      const kw=/string|bulb|light|roof|outline|eave|garland|icicle/i.test(m.name)?3:1;
      const score=s.w*kw;
      if(score>bestScore){bestScore=score;best=m;}
    }
    if(!best)for(const m of models){
      const s=spanOf(m);
      if(!best||s.w>spanOf(best).w)best=m;
    }
    return best;
  }
  function bucketsFor(m){
    const span=spanOf(m),sw=span.w||1;
    const buckets=Array.from({length:NB},()=>[]);
    for(let i=m.start;i<m.start+m.count;i++){
      const b=Math.min(NB-1,Math.max(0,Math.floor((geo[i*3]-span.minx)/sw*NB)));
      buckets[b].push(i);
    }
    return buckets;
  }

  // ── Mode A: the 3D viewer plugin's binary feed (index-keyed, sees every
  // model incl. props absent from the virtualdisplaymap). Preferred. ──
  async function tryModeA(){
    let resp;
    try{resp=await fetch('/fpp3dviewer/',{cache:'no-store'});}catch(e){return false;}
    if(!resp.ok||!resp.body)return false;
    const m=pickStatic();
    if(!m){try{resp.body.cancel();}catch(e){}return false;}
    const buckets=bucketsFor(m);
    const need=(m.start+m.count)*3;
    dbg.model=m.name+' ('+m.count+' px, mode A)';
    dbg.feed='connected (A)';
    glog('model locked',dbg.model);
    let latest=null;
    (async()=>{
      const reader=resp.body.getReader();
      let buf=new Uint8Array(0);
      try{
        while(true){
          const {done,value}=await reader.read();
          if(done)break;
          const merged=new Uint8Array(buf.length+value.length);
          merged.set(buf,0);merged.set(value,buf.length);
          buf=merged;
          let off=0;
          while(buf.length-off>=4){
            const len=(buf[off]<<24)|(buf[off+1]<<16)|(buf[off+2]<<8)|buf[off+3];
            if(buf.length-off-4<len)break;
            latest=buf.slice(off+4,off+4+len);
            dbg.frames++;
            off+=4+len;
          }
          buf=off>0?buf.slice(off):buf;
        }
      }catch(e){}
      dbg.feed='disconnected (A)';
      glog('mode A stream ended');
    })();
    setInterval(()=>{
      const f=latest;
      if(!f)return;
      latest=null;
      if(f.length<need)return;                      // partial/mismatched frame
      for(let b=0;b<NB;b++){
        const idxs=buckets[b];
        if(!idxs.length)continue;
        // Average only LIT pixels so dark neighbors don't dilute the color;
        // fall back to the plain average when the whole bucket is near-black.
        let r=0,g=0,bl=0,nl=0,ra=0,ga=0,ba=0;
        for(let j=0;j<idxs.length;j++){
          const o=idxs[j]*3,R=f[o],G=f[o+1],B=f[o+2];
          ra+=R;ga+=G;ba+=B;
          if(R>16||G>16||B>16){r+=R;g+=G;bl+=B;nl++;}
        }
        const o=b*3,n=idxs.length;
        if(nl){cols[o]=r/nl|0;cols[o+1]=g/nl|0;cols[o+2]=bl/nl|0;}
        else{cols[o]=ra/n|0;cols[o+1]=ga/n|0;cols[o+2]=ba/n|0;}
        dbg.writes++;
      }
      paint();
    },66);
    return true;
  }

  // ── Mode B: FPP's 2D SSE feed. The source model is chosen EMPIRICALLY —
  // models absent from the virtualdisplaymap (e.g. rgbeffects imports) never
  // appear in this feed, so we watch it and only consider models whose pixels
  // actually light. ──
  let keyToBulb=null;             // set once a source model is locked in
  const seen=new Set();           // feed keys observed while calibrating

  function lockIn(m,cov){
    const span=spanOf(m),sw=span.w||1;
    keyToBulb=new Map();
    for(let i=m.start;i<m.start+m.count;i++){
      const b=Math.min(NB-1,Math.max(0,Math.floor((geo[i*3]-span.minx)/sw*NB)));
      keyToBulb.set(keyArr[i],b);
    }
    dbg.model=m.name+' ('+m.count+' px'+(cov!=null?', '+Math.round(cov*100)+'% live':'')+')';
    glog('model locked',dbg.model+', x-span '+Math.round(sw));
  }
  function coverage(m){
    let hit=0;
    for(let i=m.start;i<m.start+m.count;i++)if(seen.has(keyArr[i]))hit++;
    return hit/m.count;
  }
  function startModeB(){
    if(want){
      const m=models.find(x=>x.name.toLowerCase()===want.toLowerCase());
      if(!m)return glog('model not found',want);
      lockIn(m,null);
    }else{
      glog('calibrating','watching the feed for lit models (needs a sequence playing)');
      const chooser=setInterval(()=>{
        if(keyToBulb){clearInterval(chooser);return;}
        if(seen.size<50)return;
        let best=null,bestScore=-1,bestCov=0;
        for(const m of models){
          if(m.count<NB||m.count>1500)continue;
          const cov=coverage(m);
          if(cov<0.3)continue;                      // must actually be in the feed
          const s=spanOf(m);
          if(s.w<s.h*2)continue;
          const kw=/string|bulb|light|roof|outline|eave|garland|icicle/i.test(m.name)?3:1;
          const score=s.w*kw*cov;
          if(score>bestScore){bestScore=score;best=m;bestCov=cov;}
        }
        if(!best){                                  // relax shape: widest covered model
          for(const m of models){
            if(m.count<8||m.count>3000)continue;
            const cov=coverage(m);
            if(cov<0.3)continue;
            const score=spanOf(m).w*cov;
            if(score>bestScore){bestScore=score;best=m;bestCov=cov;}
          }
        }
        if(best){clearInterval(chooser);lockIn(best,bestCov);seen.clear();}
      },2000);
    }

    let opened=false,errs=0;
    const es=new EventSource('/api/http-virtual-display/');
    es.onopen=()=>{opened=true;errs=0;dbg.feed='connected (B)';glog('feed connected');};
    es.onerror=()=>{
      dbg.feed=opened?'reconnecting…':'error '+(errs+1);
      if(!opened&&++errs>=3){es.close();glog('feed unreachable','/api/http-virtual-display/ — is HTTP Virtual Display enabled in FPP outputs?');}
    };
    es.onmessage=ev=>{pending=ev.data;dbg.frames++;};
    // Drain only the latest payload ~15×/s — plenty for 24 bulbs
    setInterval(()=>{
      if(pending==null)return;
      const p=pending;pending=null;
      parse(p);
      if(dirty)paint();
    },66);
  }

  // ── SSE decode (FPP's custom base64 + RGB666) ──
  const INV=new Int16Array(128).fill(-1);
  const B64="0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz+/";
  for(let i=0;i<B64.length;i++)INV[B64.charCodeAt(i)]=i;
  const up6=v=>(v<<2)|(v>>4);

  // ── live theme: derive a 3-color palette from the bulbs and drive the whole
  // interface with it (card borders, state color, ambient wash). Buttons keep
  // their fixed colors — green=start / red=stop are safety affordances. ──
  const rootEl=document.documentElement;
  const sm=[[246,181,63],[91,194,245],[47,211,196]];   // smoothed live1..3
  function hueOf(r,g,b){
    const mx=Math.max(r,g,b),mn=Math.min(r,g,b),d=mx-mn;
    if(!d)return 0;
    let h;
    if(mx===r)h=((g-b)/d)%6;else if(mx===g)h=(b-r)/d+2;else h=(r-g)/d+4;
    return ((h*60)+360)%360;
  }
  function hueDist(a,b){const d=Math.abs(a-b)%360;return d>180?360-d:d;}
  function updateTheme(src){
    // candidates: reasonably bright bulbs, scored by saturation×brightness
    const cand=[];
    for(let i=0;i<NB;i++){
      const r=src[i*3],g=src[i*3+1],b=src[i*3+2];
      const mx=Math.max(r,g,b);
      if(mx<40)continue;
      const sat=mx?(mx-Math.min(r,g,b))/mx:0;
      cand.push({r,g,b,h:hueOf(r,g,b),score:sat*mx});
    }
    if(!cand.length)return;
    cand.sort((a,b)=>b.score-a.score);
    const p1=cand[0];
    let p2=p1,d2=-1;
    for(const c of cand){const d=hueDist(c.h,p1.h)*Math.sqrt(c.score);if(d>d2){d2=d;p2=c;}}
    let p3=p1,d3=-1;
    for(const c of cand){const d=Math.min(hueDist(c.h,p1.h),hueDist(c.h,p2.h))*Math.sqrt(c.score);if(d>d3){d3=d;p3=c;}}
    [p1,p2,p3].forEach((p,k)=>{
      sm[k][0]+=(p.r-sm[k][0])*0.12;
      sm[k][1]+=(p.g-sm[k][1])*0.12;
      sm[k][2]+=(p.b-sm[k][2])*0.12;
    });
    const c1=`${sm[0][0]|0},${sm[0][1]|0},${sm[0][2]|0}`;
    const c2=`${sm[1][0]|0},${sm[1][1]|0},${sm[1][2]|0}`;
    const c3=`${sm[2][0]|0},${sm[2][1]|0},${sm[2][2]|0}`;
    rootEl.style.setProperty('--live1',`rgb(${c1})`);
    rootEl.style.setProperty('--live2',`rgb(${c2})`);
    rootEl.style.setProperty('--live3',`rgb(${c3})`);
    rootEl.style.setProperty('--liveBrd',`rgba(${c1},.38)`);
    rootEl.style.setProperty('--live2Brd',`rgba(${c2},.55)`);
    rootEl.style.setProperty('--live1A',`rgba(${c1},.30)`);
    // readable label color on a live1-filled button
    const lum=.2126*sm[0][0]+.7152*sm[0][1]+.0722*sm[0][2];
    rootEl.style.setProperty('--liveInk',lum>150?'#06210f':'#ffffff');
    if(tint)tint.style.background=
      `radial-gradient(58% 46% at 50% -4%,rgba(${c1},.26),transparent 70%),`+
      `radial-gradient(42% 36% at 6% 20%,rgba(${c2},.18),transparent 70%),`+
      `radial-gradient(42% 36% at 94% 16%,rgba(${c3},.18),transparent 70%),`+
      `radial-gradient(48% 42% at 78% 96%,rgba(${c2},.14),transparent 72%)`;
  }

  // Real shows run LEDs well below full value (they'd be blinding at night),
  // but a monitor needs the full range — so normalize for display: auto-gain
  // against a slowly-decaying rolling peak, then a gamma lift for midtones.
  const disp=new Uint8Array(NB*3);
  let rollingPeak=255;
  function paint(){
    dirty=false;
    let framePeak=0;
    for(let i=0;i<NB*3;i++)if(cols[i]>framePeak)framePeak=cols[i];
    rollingPeak=Math.max(framePeak,rollingPeak*0.97,48);
    const gain=Math.min(255/rollingPeak,6);
    for(let i=0;i<NB*3;i++){
      const v=Math.min(255,cols[i]*gain);
      disp[i]=Math.round(255*Math.pow(v/255,0.75));
    }
    let lit=false;
    for(let i=0;i<NB;i++){
      const r=disp[i*3],g=disp[i*3+1],b=disp[i*3+2];
      const el=bulbs[i];
      if(cols[i*3]|cols[i*3+1]|cols[i*3+2]){
        lit=true;
        const c=`rgb(${r},${g},${b})`;
        el.style.background=`radial-gradient(circle at 34% 26%,rgba(255,255,255,.6),${c} 70%)`;
        el.style.boxShadow=`0 0 6px ${c},0 0 14px ${c}`;
        el.style.animation='none';
      }else{
        el.style.background='#10161f';
        el.style.boxShadow='none';
        el.style.animation='none';
      }
    }
    if(lit){
      lastLit=Date.now();liveLook=true;
      updateTheme(disp);
    }
  }
  // Show dark for a while → hand everything back to the default theme
  setInterval(()=>{
    if(liveLook&&Date.now()-lastLit>5000){
      liveLook=false;
      bulbs.forEach((b,i)=>b.style.cssText=defaults[i]);
      if(tint)tint.style.background='none';
      ['--live1','--live2','--live3','--liveBrd','--live2Brd','--liveInk','--live1A'].forEach(v=>rootEl.style.removeProperty(v));
    }
  },2000);

  function parse(p){
    let gi=0;const len=p.length;
    while(gi<len){
      let ge=p.indexOf('|',gi);if(ge===-1)ge=len;
      if(ge-gi>=5&&p.charCodeAt(gi+3)===58){
        const r=up6(INV[p.charCodeAt(gi)]),g=up6(INV[p.charCodeAt(gi+1)]),b=up6(INV[p.charCodeAt(gi+2)]);
        let li=gi+4;
        while(li<ge){
          let le=p.indexOf(';',li);if(le===-1||le>ge)le=ge;
          const t=le-li;let x=-1,yy=-1;
          if(t===6){
            x=(INV[p.charCodeAt(li)]<<12)|(INV[p.charCodeAt(li+1)]<<6)|INV[p.charCodeAt(li+2)];
            yy=(INV[p.charCodeAt(li+3)]<<12)|(INV[p.charCodeAt(li+4)]<<6)|INV[p.charCodeAt(li+5)];
          }else if(t===4){
            x=(INV[p.charCodeAt(li)]<<6)|INV[p.charCodeAt(li+1)];
            yy=(INV[p.charCodeAt(li+2)]<<6)|INV[p.charCodeAt(li+3)];
          }
          if(x>=0){
            const k=(x<<12)|(yy&0xfff);
            if(keyToBulb){
              const bi=keyToBulb.get(k);
              if(bi!==undefined){const o=bi*3;cols[o]=r;cols[o+1]=g;cols[o+2]=b;dirty=true;dbg.writes++;}
            }else{
              seen.add(k);
            }
          }
          li=le+1;
        }
      }
      gi=ge+1;
    }
  }

  if(!(await tryModeA())){
    glog('mode A unavailable — using 2D SSE feed');
    startModeB();
  }
})();

poll();
setInterval(pollFast,1000);
setInterval(pollSlow,5000);
</script>
</body>
</html>
