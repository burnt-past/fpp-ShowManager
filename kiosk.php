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
.wrap{position:relative;z-index:1;max-width:1000px;margin:0 auto;min-height:100vh;display:flex;flex-direction:column;padding:20px 20px 24px}
.card{background:linear-gradient(160deg,rgba(255,255,255,.04),transparent),var(--card);border:1px solid var(--brdHi);border-radius:18px;padding:20px 22px}
.klabel{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--mut);font-weight:700}
.btn{background:var(--raise);border:1px solid var(--border);color:var(--text);font-weight:700;font-size:15px;padding:12px 22px;border-radius:12px;min-height:48px}
#toasts{position:fixed;right:18px;bottom:18px;z-index:100;display:flex;flex-direction:column;gap:10px}
.toast{display:flex;align-items:center;gap:10px;padding:14px 17px;border-radius:11px;background:var(--high);border:1px solid var(--brdHi);color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.35);min-width:240px;animation:k-slide .25s ease;font-size:15px;font-weight:500}
.toast .dot{width:8px;height:8px;border-radius:50%;flex:none}
</style>
</head>
<body>
<div class="bg img"></div>
<div class="bg scrim"></div>
<div class="bg bloom"></div>
<div class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
    <span class="klabel" style="font-size:13px;letter-spacing:.14em" id="k-title">Show Kiosk</span>
    <span style="font-family:var(--mono);font-size:15px;color:var(--sub)" id="k-clock"></span>
  </div>
  <div id="k-garland" style="position:relative;height:52px;margin:8px -2px 0"></div>

  <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:14px;padding:22px 0 12px">
    <div style="display:flex;align-items:center;gap:16px">
      <span id="k-dot" style="width:22px;height:22px;border-radius:50%;background:var(--mut);flex:none"></span>
      <span id="k-state" style="font-size:clamp(48px,7vw,104px);font-weight:900;letter-spacing:-.02em;line-height:.95;color:var(--sub)">…</span>
    </div>
    <div id="k-sub" style="font-size:clamp(24px,3vw,40px);font-weight:700"></div>
    <div style="display:flex;gap:16px;margin-top:22px;flex-wrap:wrap;justify-content:center">
      <button onclick="kStart()" style="border:none;background:var(--mint);color:var(--mintInk);font-weight:800;font-size:22px;padding:22px 46px;border-radius:16px;min-height:64px">▶ Start show</button>
      <button id="k-stop" style="position:relative;overflow:hidden;background:var(--raise);color:var(--text);border:2px solid var(--redBrd);font-weight:800;font-size:22px;padding:22px 46px;border-radius:16px;min-height:64px">
        <span style="position:absolute;inset:0;opacity:.28"><span id="k-holdbar" style="display:block;width:0%;height:100%;background:var(--red);transition:width .05s linear"></span></span>
        <span style="position:relative">Hold to stop</span>
      </button>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:16px;margin-bottom:22px;text-align:left">
    <div class="card">
      <div class="klabel" style="margin-bottom:8px">Today's Schedule</div>
      <div id="k-sched"><div style="color:var(--mut);font-size:15px;padding:10px 0">Loading…</div></div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px">
      <div class="klabel" style="align-self:flex-start">Temporary Volume</div>
      <div style="display:flex;align-items:center;gap:18px">
        <button onclick="kVol(-5)" style="width:60px;height:60px;border-radius:16px;font-size:30px;font-weight:800;background:var(--raise);border:1px solid var(--brdHi);color:var(--text)">−</button>
        <div id="k-vol" style="font-size:42px;font-weight:800;font-family:var(--mono);min-width:130px;color:var(--amber)">—</div>
        <button onclick="kVol(5)" style="width:60px;height:60px;border-radius:16px;font-size:30px;font-weight:800;background:var(--raise);border:1px solid var(--brdHi);color:var(--text)">+</button>
      </div>
      <div style="font-size:13px;color:var(--sub)">Temporary — reverts to the configured level at the next show</div>
    </div>
  </div>

  <div style="display:flex;gap:12px;justify-content:center;align-items:center;flex-wrap:wrap;padding-top:16px;border-top:1px solid var(--border);margin-top:auto">
    <span style="font-size:13px;color:var(--sub);margin-right:4px">Disable system:</span>
    <button class="btn" onclick="kDisable('1h')">1 hour</button>
    <button class="btn" onclick="kDisable('tonight')">Tonight</button>
    <button class="btn" id="k-enable" style="display:none;background:var(--mint);border-color:var(--mint);color:var(--mintInk)" onclick="kEnable()">Re-enable now</button>
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
    bulbs+=`<span style="position:absolute;left:${(f*100).toFixed(3)}%;top:${baseY}px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center"><span style="width:3px;height:5px;background:#33412f;border-radius:0 0 1px 1px"></span><span style="width:11px;height:15px;border-radius:50% 50% 48% 48% / 60% 60% 42% 42%;background:radial-gradient(circle at 34% 26%,rgba(255,255,255,.6),${c} 70%);box-shadow:0 0 6px ${c},0 0 14px ${c};animation:k-glow 2s ease-in-out infinite;animation-delay:${(i*0.11).toFixed(2)}s"></span></span>`;
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
    dot.style.background='var(--amber)';dot.style.boxShadow='0 0 20px var(--amber)';dot.style.animation='k-pulse 1.6s ease-in-out infinite';
    st.style.color='var(--amber)';st.textContent='SHOW RUNNING';
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

poll();
setInterval(pollFast,1000);
setInterval(pollSlow,5000);
</script>
</body>
</html>
