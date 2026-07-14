<?php
$playlists = [];
$playlistDir = "/home/fpp/media/playlists";
if (is_dir($playlistDir)) {
    foreach (glob("$playlistDir/*.json") as $f) $playlists[] = basename($f, '.json');
    sort($playlists);
}
$AJAX = 'plugin.php?plugin=' . basename(__DIR__) . '&page=ajax.php&nopage=1';
$plJson = json_encode($playlists);
?>
<style>
#sm{
  --bg:#071019;--base:#0b1723;--card:#0f1f2e;--raise:#16293b;--high:#1d3648;
  --border:rgba(255,255,255,.08);--brdHi:rgba(120,200,255,.26);
  --text:#e8f1fb;--sub:#8aa2ba;--mut:#516678;
  --mint:#2fd3c4;--amber:#f6b53f;--red:#f5566f;--s1:#5bc2f5;--s2:#7cd0ff;
  --mintBg:rgba(47,211,196,.14);--mintBrd:rgba(47,211,196,.32);--mintInk:#06210f;
  --amberBg:rgba(246,181,63,.16);--amberBrd:rgba(246,181,63,.42);
  --redBg:rgba(245,86,111,.15);--redBrd:rgba(245,86,111,.4);
  --s1Bg:rgba(91,194,245,.16);--s1Brd:rgba(91,194,245,.3);
  --bloomOp:1;--bgImgOp:.66;--scrim1:rgba(7,16,25,.42);--scrim2:rgba(7,16,25,.84);
  --font:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
  --mono:ui-monospace,'SF Mono','Cascadia Code',Menlo,Consolas,monospace;
  position:relative;overflow:hidden;background:var(--bg);color:var(--text);font-family:var(--font);min-height:600px;
}
#sm.sm-light{
  --bg:#eef4fb;--base:#e3ebf5;--card:#ffffff;--raise:#f3f7fc;--high:#e9f0f8;
  --border:rgba(20,50,90,.10);--brdHi:rgba(60,140,220,.22);
  --text:#10202f;--sub:#4a5f76;--mut:#90a4bb;
  --mint:#0d9488;--amber:#b9820f;--red:#e11d5a;--s1:#2183d6;--s2:#1f8fb0;
  --mintBg:rgba(13,148,136,.12);--mintBrd:rgba(13,148,136,.35);--mintInk:#ffffff;
  --amberBg:rgba(185,130,15,.14);--amberBrd:rgba(185,130,15,.4);
  --redBg:rgba(225,29,90,.10);--redBrd:rgba(225,29,90,.35);
  --s1Bg:rgba(33,131,214,.12);--s1Brd:rgba(33,131,214,.3);
  --bloomOp:.5;--bgImgOp:.18;--scrim1:rgba(238,244,251,.6);--scrim2:rgba(238,244,251,.9);
}
#sm *{box-sizing:border-box}
#sm button{font-family:var(--font);cursor:pointer}
#sm ::-webkit-scrollbar{width:9px;height:9px}
#sm ::-webkit-scrollbar-thumb{background:var(--mut);border-radius:6px}
@keyframes sm-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.5);opacity:.55}}
@keyframes sm-slide{from{transform:translateY(8px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes sm-shimmer{0%{background-position:-320px 0}100%{background-position:320px 0}}
@keyframes sm-glow{0%,100%{filter:brightness(.62)}50%{filter:brightness(1.3)}}
.sm-skel{background:linear-gradient(90deg,var(--raise) 0px,var(--high) 160px,var(--raise) 320px);background-size:640px 100%;animation:sm-shimmer 1.2s linear infinite;border-radius:8px}
.sm-bg{position:absolute;inset:0;pointer-events:none}
.sm-bg.img{opacity:var(--bgImgOp);background:url(data:image/jpeg;base64,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) center/cover no-repeat;filter:blur(20px) saturate(1.3);transform:scale(1.07)}
.sm-bg.scrim{background:linear-gradient(180deg,var(--scrim1),var(--scrim2))}
.sm-bg.bloom{opacity:var(--bloomOp);background:radial-gradient(52% 42% at 50% -4%,rgba(246,181,63,.24),transparent 70%),radial-gradient(40% 34% at 5% 18%,rgba(91,194,245,.20),transparent 70%),radial-gradient(40% 34% at 96% 14%,rgba(124,208,255,.18),transparent 70%),radial-gradient(46% 42% at 78% 92%,rgba(56,190,232,.16),transparent 72%),radial-gradient(30% 26% at 50% 108%,rgba(255,170,80,.13),transparent 70%)}
.sm-wrap{position:relative;z-index:1;max-width:1180px;margin:0 auto;padding:22px 20px 40px}
#sm-garland{position:relative;height:46px;margin:0 -2px 12px}
/* now playing strip */
#sm-nowplaying{display:flex;align-items:center;gap:12px;padding:13px 20px;border-radius:14px;margin-bottom:14px;border:1px solid var(--brdHi);background:linear-gradient(100deg,rgba(255,255,255,.04),var(--card))}
#sm-nowplaying.on{background:linear-gradient(100deg,var(--amberBg),var(--card));border-color:var(--amberBrd);box-shadow:0 10px 34px -18px var(--amber)}
#sm-nowplaying.off{background:linear-gradient(100deg,var(--redBg),var(--card));border-color:var(--redBrd)}
#sm-np-dot{width:11px;height:11px;border-radius:50%;background:var(--mut);flex:none}
#sm-nowplaying.on #sm-np-dot{background:var(--amber);animation:sm-pulse 1.6s ease-in-out infinite}
#sm-nowplaying.off #sm-np-dot{background:var(--red)}
#sm-np-label{font-weight:800;letter-spacing:.04em;font-size:14px;color:var(--sub)}
#sm-nowplaying.on #sm-np-label{color:var(--amber)}
#sm-nowplaying.off #sm-np-label{color:var(--red)}
#sm-np-sub{color:var(--text);font-weight:600;font-size:14px}
/* tabs */
#sm-tabs{display:flex;align-items:flex-end;gap:2px;border-bottom:1px solid var(--border);margin-bottom:20px}
.sm-tab{appearance:none;border:none;background:transparent;color:var(--sub);font-size:14px;font-weight:500;padding:11px 18px;border-radius:10px 10px 0 0;border-bottom:2px solid transparent}
.sm-tab.active{background:var(--card);color:var(--text);font-weight:600;border-bottom:2px solid var(--mint)}
#sm-theme-btn{appearance:none;border:1px solid var(--border);background:var(--raise);color:var(--text);font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:8px}
/* cards */
.sm-card{background:linear-gradient(160deg,rgba(255,255,255,.04),transparent),var(--card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;box-shadow:inset 0 1px 0 rgba(255,255,255,.04)}
.sm-ct{font-weight:700;font-size:15px;margin-bottom:12px}
/* buttons */
.sm-btn{appearance:none;background:var(--raise);border:1px solid var(--border);color:var(--text);font-weight:600;font-size:13px;padding:8px 14px;border-radius:9px;white-space:nowrap}
.sm-btn.ghost{background:transparent;color:var(--sub)}
.sm-btn.solid{background:var(--mint);border-color:var(--mint);color:var(--mintInk);font-weight:700}
.sm-btn.danger{background:transparent;border-color:var(--redBrd);color:var(--red)}
.sm-btn.sm{padding:5px 10px;font-size:12px;border-radius:7px}
/* inputs */
.sm-input,.sm-select{width:100%;background:var(--raise);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:10px 12px;font-family:var(--mono);font-size:13px;outline:none}
/* beat FPP's own input/select rules — the plugin renders inside FPP's page */
#sm input,#sm select,#sm textarea{color:var(--text);background:var(--raise);opacity:1}
#sm input::placeholder{color:var(--mut)}
#sm option{background:var(--card);color:var(--text)}
.sm-select{appearance:none;font-family:var(--font);font-size:14px}
.sm-input:focus,.sm-select:focus{border-color:var(--mint)}
.sm-lbl{display:block;font-size:12px;color:var(--sub)}
.sm-lbl .sm-input,.sm-lbl .sm-select{margin-top:5px}
.sm-hint{font-size:12px;color:var(--mut)}
/* wells (logs) */
.sm-well{background:var(--high);border-radius:9px;padding:12px 14px;overflow:auto;font-family:var(--mono);font-size:12px;line-height:1.7;color:var(--sub);white-space:pre-wrap;word-break:break-all}
/* badges + chips + dots */
.sm-badge{margin-left:auto;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;padding:3px 9px;border-radius:999px;flex:none}
.sm-badge.mint{background:var(--mintBg);color:var(--mint)}
.sm-badge.amber{background:var(--amberBg);color:var(--amber)}
.sm-chip{border-radius:6px;padding:2px 6px;font-size:11px;font-family:var(--mono);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.4;background:var(--mintBg);color:var(--mint);border:1px solid var(--mintBrd)}
.sm-chip.rule{background:var(--s1Bg);color:var(--s1);border-color:var(--s1Brd)}
.sm-chip.blk{background:var(--redBg);color:var(--red);border-color:var(--redBrd)}
.sm-dot{width:9px;height:9px;border-radius:50%;flex:none}
/* segmented */
.sm-seg{display:flex;gap:3px;background:var(--raise);padding:3px;border-radius:9px}
.sm-seg button{appearance:none;border:none;padding:7px 14px;font-size:13px;font-weight:600;border-radius:8px;background:transparent;color:var(--sub)}
.sm-seg button.active{background:var(--card);color:var(--text)}
/* calendar */
.cal-dow{text-align:center;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--mut);font-weight:700;padding:4px 0}
.cal-cell{min-height:92px;padding:6px;border-radius:8px;border:1px solid var(--border);background:var(--card);cursor:pointer;display:flex;flex-direction:column;gap:3px;overflow:hidden}
.cal-cell.blank{background:transparent;border-color:transparent;cursor:default}
.cal-cell.bo{background:var(--redBg)}
.cal-cell.today{outline:2px solid var(--mint);outline-offset:-2px}
.cal-day{font-size:12px;font-weight:600;color:var(--sub);padding:1px 3px}
/* day view + rules */
.dv-entry{display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:10px;background:var(--card);border:1px solid var(--border);margin-bottom:8px}
.rule-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid var(--border)}
.rule-row:last-child{border-bottom:none}
.dow-btn{appearance:none;font-weight:600;font-size:13px;padding:7px 0;width:40px;border-radius:8px;border:1px solid var(--border);background:var(--raise);color:var(--sub)}
.dow-btn.on{background:var(--mint);color:var(--mintInk);border-color:transparent}
/* modal */
.sm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px}
.sm-modal{background:var(--card);border:1px solid var(--brdHi);border-radius:16px;padding:24px;width:100%;max-width:440px;max-height:88vh;overflow:auto;box-shadow:0 24px 70px rgba(0,0,0,.5)}
.sm-mt{font-weight:800;font-size:18px;margin-bottom:18px}
/* toasts */
#sm-toasts{position:fixed;right:18px;bottom:18px;z-index:10000;display:flex;flex-direction:column;gap:10px}
.sm-toast{display:flex;align-items:center;gap:10px;padding:12px 15px;border-radius:11px;background:var(--high);border:1px solid var(--brdHi);color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.35);min-width:220px;animation:sm-slide .25s ease;cursor:pointer;font-size:13.5px;font-weight:500}
.sm-toast .dot{width:8px;height:8px;border-radius:50%;flex:none}
</style>
<div id="sm">
  <div class="sm-bg img"></div>
  <div class="sm-bg scrim"></div>
  <div class="sm-bg bloom"></div>
  <div class="sm-wrap">
    <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:10px">
      <span style="font-size:18px;font-weight:800;letter-spacing:-.01em">ShowManager</span>
      <span style="font-size:12px;color:var(--mut);font-family:var(--mono)">FPP plugin<span id="sm-host"></span></span>
      <a href="plugin.php?plugin=<?= basename(__DIR__) ?>&page=kiosk.php&nopage=1" target="_blank" style="margin-left:auto;text-decoration:none"><button id="sm-kiosk-btn" style="appearance:none;border:1px solid var(--border);background:transparent;color:var(--sub);font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:8px">⛶ Kiosk</button></a>
      <button id="sm-theme-btn" onclick="smToggleTheme()">☾ Dark</button>
    </div>
    <div id="sm-garland"></div>
    <div id="sm-nowplaying">
      <span id="sm-np-dot"></span>
      <span id="sm-np-label">…</span>
      <span id="sm-np-sub"></span>
    </div>
    <div id="sm-tabs">
      <button class="sm-tab active" onclick="smTab('status')">Status</button>
      <button class="sm-tab" onclick="smTab('schedule')">Schedule</button>
      <button class="sm-tab" onclick="smTab('announcements')">Announcements</button>
      <button class="sm-tab" onclick="smTab('hardware')">Hardware</button>
    </div>
    <div id="sm-status" class="sm-pane"></div>
    <div id="sm-schedule" class="sm-pane" style="display:none"></div>
    <div id="sm-announcements" class="sm-pane" style="display:none"></div>
    <div id="sm-hardware" class="sm-pane" style="display:none"></div>
  </div>
  <div id="sm-modal-layer"></div>
  <div id="sm-toasts"></div>
</div>
<script>
const AJAX='<?= $AJAX ?>';
const PLAYLISTS=<?= $plJson ?>;
const DAYS=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];

/* ── THEME ── */
let smDark=true;try{smDark=localStorage.getItem('sm-theme')!=='light';}catch(e){}
function smToggleTheme(){smDark=!smDark;smApplyTheme();try{localStorage.setItem('sm-theme',smDark?'dark':'light');}catch(e){}}
function smApplyTheme(){document.getElementById('sm').classList.toggle('sm-light',!smDark);document.getElementById('sm-theme-btn').textContent=smDark?'☾ Dark':'☀ Light';}

/* ── HELPERS ── */
function fmtDate(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
function addDays(d,n){const r=new Date(d);r.setDate(r.getDate()+n);return r;}
function getSunday(d){const s=new Date(d);s.setDate(s.getDate()-s.getDay());s.setHours(0,0,0,0);return s;}
function mkey(y,m){return y+'-'+String(m).padStart(2,'0');}
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function escJ(s){return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'");}
function numOr(v,d){const n=parseFloat(v);return isNaN(n)?d:n;}
function qlabel(e){
  if(e.type==='blackout'){
    const range=(e.start_time||e.end_time)?` ${e.start_time||'00:00'}–${e.end_time||'23:59'}`:'';
    return '✖ Blackout'+range;
  }
  return e.playlist||(e.playlists||[]).join(' / ')||'(show)';
}
function plOptions(sel){return ['<option value="">— none —</option>',...PLAYLISTS.map(p=>`<option value="${escH(p)}"${p===sel?' selected':''}>${escH(p)}</option>`)].join('');}
function copyText(id){const el=document.getElementById(id);if(!el)return;navigator.clipboard.writeText(el.textContent);toast('Copied to clipboard','ok');}

/* ── TOASTS ── */
function toast(msg,kind){
  const c=document.getElementById('sm-toasts');if(!c)return;
  const d=document.createElement('div');d.className='sm-toast';
  const col=kind==='ok'?'var(--mint)':kind==='amber'?'var(--amber)':kind==='err'?'var(--red)':'var(--mut)';
  d.innerHTML='<span class="dot" style="background:'+col+'"></span><span>'+escH(msg)+'</span>';
  d.onclick=()=>d.remove();
  c.appendChild(d);
  setTimeout(()=>{d.remove();},3600);
}

/* ── CONFIRM MODAL ── */
let _confirmCb=null;
function smConfirm(title,body,okLabel,cb,solid){
  _confirmCb=cb;
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal">
      <div style="font-weight:800;font-size:18px;margin-bottom:8px">${escH(title)}</div>
      <div style="font-size:14px;color:var(--sub);line-height:1.5;margin-bottom:22px">${escH(body)}</div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="sm-btn" onclick="closeModal()">Cancel</button>
        <button class="sm-btn ${solid?'solid':'danger'}" style="${solid?'':'background:var(--red);border-color:var(--red);color:#fff'}" onclick="_runConfirm()">${escH(okLabel)}</button>
      </div>
    </div>
  </div>`);
}
function _runConfirm(){closeModal();const cb=_confirmCb;_confirmCb=null;if(cb)cb();}

/* ── GARLAND ── */
function buildGarland(){
  const el=document.getElementById('sm-garland');if(!el)return;
  const N=24,sag=16,baseY=7,cols=['#f6b53f','#5fc8ff','#fff1cf','#38d6e8','#ffd27a'];
  let gp='',prev=0,bulbs='';
  for(let i=0;i<N;i++){
    const f=i/(N-1),x=+(f*1000).toFixed(1);
    if(i===0)gp='M'+x+','+baseY;
    else{const mx=((prev+x)/2).toFixed(1);gp+=' Q'+mx+','+(baseY+sag)+' '+x+','+baseY;}
    prev=x;
    const c=cols[i%cols.length];
    bulbs+=`<span style="position:absolute;left:${(f*100).toFixed(3)}%;top:${baseY}px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center"><span style="width:3px;height:5px;background:#33412f;border-radius:0 0 1px 1px"></span><span style="width:11px;height:15px;border-radius:50% 50% 48% 48% / 60% 60% 42% 42%;background:radial-gradient(circle at 34% 26%,rgba(255,255,255,.6),${c} 70%);box-shadow:0 0 6px ${c},0 0 14px ${c};animation:sm-glow 2s ease-in-out infinite;animation-delay:${(i*0.11).toFixed(2)}s"></span></span>`;
  }
  el.innerHTML=`<svg viewBox="0 0 1000 46" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;overflow:visible"><path d="${gp}" fill="none" stroke="#33412f" stroke-width="2.2" stroke-linecap="round"></path></svg>`+bulbs;
}

/* ── TABS ── */
function smTab(name){
  document.querySelectorAll('.sm-tab').forEach((b,i)=>b.classList.toggle('active',['status','schedule','announcements','hardware'][i]===name));
  document.querySelectorAll('.sm-pane').forEach(p=>p.style.display='none');
  document.getElementById('sm-'+name).style.display='';
  if(name!=='status'&&statusTimer){clearInterval(statusTimer);statusTimer=null;}
  ({status:loadStatus,schedule:initSchedule,announcements:loadAnnouncements,hardware:loadHardware})[name]?.();
}

/* ── NOW PLAYING STRIP ── */
let npNext='';
function _cleanName(s){s=String(s||'').split('/').pop();return s.replace(/\.[^.]+$/,'');}
function _nowTrack(fpp){
  const pl=fpp.current_playlist?.playlist||fpp.current_playlist?.name||'';
  const seq=_cleanName(fpp.current_sequence||fpp.current_song||'');
  return seq&&seq!==pl?(seq+(pl?' · '+pl:'')):(pl||seq);
}
async function _npTick(){
  const [r,ov]=await Promise.all([
    fetch('/api/fppd/status').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_override').then(r=>r.json()).catch(()=>({})),
  ]);
  const playing=r.status===1||r.status==='playing';
  const disabled=!!ov.disabled_until;
  const np=document.getElementById('sm-nowplaying');
  if(!np)return;
  np.classList.toggle('off',disabled);
  np.classList.toggle('on',playing&&!disabled);
  const label=document.getElementById('sm-np-label');
  const sub=document.getElementById('sm-np-sub');
  if(disabled){
    label.textContent='DISABLED';
    const t=new Date(ov.disabled_until);
    sub.textContent='until '+t.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
  }else{
    label.textContent=playing?'SHOW RUNNING':'IDLE';
    sub.textContent=playing?_nowTrack(r):(npNext?'Next show '+npNext:'');
  }
  const host=r.HostName||r.hostname;
  if(host)document.getElementById('sm-host').textContent=' · '+host;
}

/* ── STATUS ── */
let statusTimer=null;
let triggerLog=[];
let logPaused=false;

function loadStatus(){
  clearInterval(statusTimer);
  const el=document.getElementById('sm-status');
  if(!el.dataset.loaded){
    el.innerHTML=`<div style="display:flex;flex-direction:column;gap:16px">
      <div class="sm-skel" style="height:150px"></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <div class="sm-skel" style="height:78px"></div><div class="sm-skel" style="height:78px"></div>
        <div class="sm-skel" style="height:78px"></div><div class="sm-skel" style="height:78px"></div>
      </div>
      <div class="sm-skel" style="height:200px"></div>
    </div>`;
  }
  renderStatus().then(()=>{el.dataset.loaded='1';});
  statusTimer=setInterval(_tick,3000);
}

/* Mirror the scheduler's blackout rules so the UI shows what will actually run */
function _todayInfo(sched){
  const today=fmtDate(new Date());
  const ents=(sched.entries||[]).filter(e=>e.date===today);
  const blk=ents.filter(e=>e.type==='blackout');
  const fullBlackout=blk.some(b=>!b.start_time&&!b.end_time);
  let shows=ents.filter(e=>e.type==='show').sort((a,b)=>a.time.localeCompare(b.time));
  if(fullBlackout)shows=[];
  else if(blk.length)shows=shows.filter(s=>!blk.some(b=>(b.start_time||'00:00')<=s.time&&s.time<=(b.end_time||'23:59')));
  return {shows,fullBlackout};
}
async function _fetchStatus(){
  const [fpp,xr,sched,log]=await Promise.all([
    fetch('/api/fppd/status').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_status').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_month&year='+new Date().getFullYear()+'&month='+(new Date().getMonth()+1)).then(r=>r.json()).catch(()=>({entries:[],rules:[]})),
    fetch(AJAX+'&action=get_log').then(r=>r.json()).catch(()=>({lines:[],running:false})),
  ]);
  const playing=fpp.status===1||fpp.status==='playing';
  const curName=fpp.current_playlist?.playlist||fpp.current_playlist?.name||'';
  const t=_todayInfo(sched);
  const now=new Date().toTimeString().slice(0,5);
  const nextIdx=t.shows.findIndex(s=>s.time>=now);
  const upcoming=nextIdx>=0?t.shows.slice(nextIdx):[];
  npNext=upcoming.length?upcoming[0].time:'';
  return {fpp,xr,log,playing,curName,shows:t.shows,fullBlackout:t.fullBlackout,nextIdx,upcoming};
}

/* region templates */
function _heroHtml(fpp,xr){
  const playing=fpp.status===1||fpp.status==='playing';
  const curPl=fpp.current_playlist?.playlist||fpp.current_playlist?.name||fpp.current_song||'Idle';
  const vol=fpp.volume!=null?fpp.volume:null;
  const fader=xr.xr18_fader!=null?xr.xr18_fader:null;
  const volPct=vol!=null?Math.max(0,Math.min(100,vol)):0;
  const fadPct=fader!=null?Math.max(0,Math.min(100,Math.round(fader*100))):0;
  return `
  <div style="position:absolute;top:-70px;right:-40px;width:280px;height:200px;background:radial-gradient(circle,var(--amberBg),transparent 68%);pointer-events:none"></div>
  <div style="position:relative;display:flex;flex-wrap:wrap;gap:24px;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:16px">
      <div style="width:52px;height:52px;border-radius:15px;flex:none;background:linear-gradient(150deg,var(--amberBg),var(--raise));border:1px solid var(--brdHi);display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--amber);box-shadow:0 0 22px -4px var(--amber)">♪</div>
      <div>
        <div style="display:flex;align-items:center;gap:9px;margin-bottom:6px">
          <span style="width:11px;height:11px;border-radius:50%;background:${playing?'var(--amber)':'var(--mut)'};${playing?'animation:sm-pulse 1.6s ease-in-out infinite':''}"></span>
          <span style="font-weight:800;letter-spacing:.04em;font-size:14px;color:${playing?'var(--amber)':'var(--sub)'}">${playing?'SHOW RUNNING':'IDLE'}</span>
        </div>
        <div style="font-size:32px;font-weight:800;letter-spacing:-.02em">${escH(playing?curPl:'Idle')}</div>
        <div style="color:var(--sub);font-size:13px;margin-top:6px;font-family:var(--mono)">${playing&&_cleanName(fpp.current_sequence||fpp.current_song||'')?'♪ '+escH(_cleanName(fpp.current_sequence||fpp.current_song||''))+' &nbsp;·&nbsp; ':''}uptime ${escH(fpp.uptime||'—')}</div>
      </div>
    </div>
    <div style="display:flex;gap:30px">
      <div style="text-align:right;min-width:110px">
        <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);font-weight:700">FPP Volume</div>
        <div style="font-size:30px;font-weight:800;font-family:var(--mono);margin-top:4px">${vol!=null?vol+'%':'—'}</div>
        <div style="height:5px;border-radius:3px;background:var(--high);margin-top:8px;overflow:hidden"><div style="width:${volPct}%;height:100%;border-radius:3px;background:linear-gradient(90deg,var(--s1),var(--mint))"></div></div>
      </div>
      <div style="text-align:right;min-width:110px">
        <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);font-weight:700">XR18 Fader</div>
        <div style="font-size:30px;font-weight:800;font-family:var(--mono);margin-top:4px">${fader!=null?fader.toFixed(2):'—'}</div>
        <div style="height:5px;border-radius:3px;background:var(--high);margin-top:8px;overflow:hidden"><div style="width:${fadPct}%;height:100%;border-radius:3px;background:linear-gradient(90deg,var(--s2),var(--amber))"></div></div>
      </div>
    </div>
  </div>`;
}
function _statsHtml(fpp,xr,shows,upcoming){
  return [
    ['V','FPP Version',fpp.version||'—','var(--s1)'],
    ['#','Instance',fpp.HostName||fpp.hostname||'—','var(--s2)'],
    ['▦','Shows Today',shows.length,'var(--amber)'],
    ['↗','Upcoming',upcoming.length,'var(--mint)'],
  ].map(([icon,l,v,c])=>`
  <div class="sm-card" style="border-radius:16px;padding:16px 18px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <span style="width:34px;height:34px;flex:none;border-radius:10px;background:var(--raise);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;font-family:var(--mono);color:${c}">${icon}</span>
      <span style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--mut);font-weight:700">${l}</span>
    </div>
    <div style="font-size:24px;font-weight:800;font-family:var(--mono);word-break:break-all;line-height:1.2">${escH(String(v))}</div>
  </div>`).join('');
}
function _schedHtml(d){
  if(d.fullBlackout) return '<div style="font-size:13px;color:var(--red);font-weight:600">⛔ Blackout day — no shows will run</div>';
  if(!d.shows.length) return '<div style="font-size:13px;color:var(--mut)">No shows today</div>';
  const nowIdx=d.playing?(d.nextIdx<0?d.shows.length-1:d.nextIdx-1):-1;
  return d.shows.map((s,i)=>{
    const isNow=i===nowIdx&&nowIdx>=0&&(!s.playlist||!d.curName||s.playlist===d.curName);
    const isNext=i===d.nextIdx&&!isNow;
    const isPast=(d.nextIdx<0||i<d.nextIdx)&&!isNow;
    return `<div style="display:flex;align-items:center;gap:14px;padding:10px 0;border-bottom:1px solid var(--border);opacity:${isPast?.42:1}">
      <span style="font-family:var(--mono);font-size:14px;font-weight:600;width:52px;color:${isNow?'var(--amber)':'var(--text)'}">${escH(s.time)}</span>
      <span style="font-size:14px;${isNext||isNow?'font-weight:600':''}">${escH(qlabel(s))}</span>
      ${isNext?'<span class="sm-badge mint">Next</span>':''}
      ${isNow?'<span class="sm-badge amber">Now</span>':''}
    </div>`;
  }).join('');
}
function _sysHtml(fpp,xr,running){
  return [
    ['FPP Daemon',fpp.fppd==='running',fpp.fppd==='running'?'running':'stopped'],
    ['XR18 Mixer',xr.xr18_fader!=null,xr.xr18_fader!=null?('fader '+xr.xr18_fader.toFixed(2)):'n/a'],
    ['Scheduler',running,running?'active':'stopped'],
  ].map(([l,ok,txt])=>`
  <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
    <span class="sm-dot" style="background:${ok?'var(--mint)':'var(--red)'};box-shadow:0 0 8px ${ok?'var(--mint)':'var(--red)'}"></span>
    <span style="font-size:14px">${l}</span>
    <span style="margin-left:auto;font-family:var(--mono);font-size:12.5px;color:var(--sub)">${escH(txt)}</span>
  </div>`).join('');
}

async function renderStatus(){
  const el=document.getElementById('sm-status');
  const d=await _fetchStatus();
  const {fpp,xr,log}=d;
  el.innerHTML=`
<div style="display:flex;flex-direction:column;gap:16px">
  <div class="sm-card" style="position:relative;overflow:hidden;border-color:var(--brdHi);border-radius:20px;padding:26px 28px;box-shadow:0 26px 70px -30px var(--amberBrd),inset 0 1px 0 rgba(255,255,255,.06)">
    <div id="sm-hero">${_heroHtml(fpp,xr)}</div>
  </div>
  <div id="sm-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">${_statsHtml(fpp,xr,d.shows,d.upcoming)}</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="sm-card">
        <div class="sm-ct" style="margin-bottom:14px">Manual Trigger</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <select id="trig-pl" class="sm-select" style="flex:1;min-width:140px">${plOptions('')}</select>
          <button class="sm-btn solid" onclick="triggerPlaylist()">▶ Start</button>
          <button class="sm-btn" onclick="stopPlaylist()">⏸ Stop</button>
        </div>
      </div>
      <div class="sm-card">
        <div class="sm-ct" style="margin-bottom:6px">Today's Schedule</div>
        <div id="sm-sched">${_schedHtml(d)}</div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="sm-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <div class="sm-ct" style="margin-bottom:0">System</div>
          <button class="sm-btn ghost sm" onclick="restartScheduler()">↻ Restart Scheduler</button>
        </div>
        <div id="sm-sys">${_sysHtml(fpp,xr,log.running)}</div>
      </div>
      <div class="sm-card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <div class="sm-ct" style="margin-bottom:0">Scheduler Log</div>
          <div style="display:flex;gap:6px">
            <button class="sm-btn ghost sm" onclick="toggleLogPause()" id="log-pause-btn">${logPaused?'Resume':'Pause'}</button>
            <button class="sm-btn ghost sm" onclick="copyText('sm-log-content')">Copy</button>
            <button class="sm-btn ghost sm" onclick="clearLog()">Clear</button>
            <button class="sm-btn ghost sm" onclick="refreshLog()">Refresh</button>
          </div>
        </div>
        <div id="sm-log-content" class="sm-well" style="max-height:220px">${escH(log.lines.join('\n'))||'(empty)'}</div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin:14px 0 6px">
          <span style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--mut);font-weight:700">Manual / Probe Output</span>
          <div style="display:flex;gap:6px">
            <button class="sm-btn ghost sm" onclick="copyText('sm-trigger-log')">Copy</button>
            <button class="sm-btn ghost sm" onclick="triggerLog=[];const tl=document.getElementById('sm-trigger-log');if(tl)tl.textContent=''">Clear</button>
          </div>
        </div>
        <div id="sm-trigger-log" class="sm-well" style="max-height:150px;min-height:36px;color:var(--mint)">${escH(triggerLog.join('\n'))}</div>
      </div>
    </div>
  </div>
</div>`;
  requestAnimationFrame(()=>{const lc=document.getElementById('sm-log-content');if(lc)lc.scrollTop=lc.scrollHeight;});
}

/* lightweight tick: updates only the dynamic regions, never touches the trigger card */
async function _tick(){
  if(!document.getElementById('sm-hero'))return;
  const d=await _fetchStatus();
  const {fpp,xr,log}=d;
  const hero=document.getElementById('sm-hero');if(hero)hero.innerHTML=_heroHtml(fpp,xr);
  const stats=document.getElementById('sm-stats');if(stats)stats.innerHTML=_statsHtml(fpp,xr,d.shows,d.upcoming);
  const scEl=document.getElementById('sm-sched');if(scEl)scEl.innerHTML=_schedHtml(d);
  const sysEl=document.getElementById('sm-sys');if(sysEl)sysEl.innerHTML=_sysHtml(fpp,xr,log.running);
  if(!logPaused){const lc=document.getElementById('sm-log-content');if(lc){lc.textContent=log.lines.join('\n')||'(empty)';lc.scrollTop=lc.scrollHeight;}}
}

function _appendTriggerLog(msg){
  triggerLog.push(msg);
  const tl=document.getElementById('sm-trigger-log');
  if(tl){tl.textContent=triggerLog.join('\n');tl.scrollTop=tl.scrollHeight;}
}
async function triggerPlaylist(){
  const pl=document.getElementById('trig-pl').value;
  if(!pl)return toast('Select a playlist first','err');
  _appendTriggerLog('[Trigger] '+pl+' — sending…');
  const r=await fetch(AJAX+'&action=trigger_playlist&playlist='+encodeURIComponent(pl)).then(r=>r.json()).catch(e=>({error:String(e)}));
  _appendTriggerLog('  URL:  '+r.url+'\n  HTTP: '+r.http+'\n  Body: '+(r.response||'(empty)'));
  toast(r.ok?('Started "'+pl+'"'):'Start failed — see probe output',r.ok?'ok':'err');
}
async function stopPlaylist(){
  _appendTriggerLog('[Stop] — sending…');
  const r=await fetch(AJAX+'&action=stop_playlist').then(r=>r.json()).catch(e=>({error:String(e)}));
  _appendTriggerLog('  HTTP: '+r.http+'  Body: '+(r.response||'(empty)'));
  toast('Show stopped','mut');
}
function toggleLogPause(){
  logPaused=!logPaused;
  const btn=document.getElementById('log-pause-btn');
  if(btn)btn.textContent=logPaused?'Resume':'Pause';
}
async function refreshLog(){
  if(logPaused)return;
  const r=await fetch(AJAX+'&action=get_log').then(r=>r.json()).catch(()=>null);
  if(!r)return;
  const lc=document.getElementById('sm-log-content');
  if(lc){lc.textContent=r.lines.join('\n')||'(empty)';lc.scrollTop=lc.scrollHeight;}
}
async function clearLog(){
  await fetch(AJAX+'&action=clear_log');
  const lc=document.getElementById('sm-log-content');
  if(lc)lc.textContent='';
  toast('Log cleared','mut');
}
async function restartScheduler(){
  await fetch(AJAX+'&action=scheduler_restart');
  toast('Scheduler restarting…','amber');
  setTimeout(refreshLog,2000);
}

/* ── SCHEDULE DATA ── */
const dataCache={};
async function fetchMonth(y,m){
  const k=mkey(y,m);
  if(dataCache[k])return dataCache[k];
  const d=await fetch(AJAX+'&action=get_month&year='+y+'&month='+m).then(r=>r.json()).catch(()=>({entries:[],rules:[]}));
  dataCache[k]=d;return d;
}
function invalidate(){Object.keys(dataCache).forEach(k=>delete dataCache[k]);}
let calState={view:'Month',cursor:new Date(),byDate:{},rules:[]};
async function calLoad(){
  const {view,cursor}=calState;
  const pairs=new Set();
  const y=cursor.getFullYear(),m=cursor.getMonth()+1;
  pairs.add(mkey(y,m));
  if(view==='Week'){const we=addDays(getSunday(cursor),6);pairs.add(mkey(we.getFullYear(),we.getMonth()+1));}
  const results=await Promise.all([...pairs].map(k=>{const[y,m]=k.split('-');return fetchMonth(+y,+m);}));
  const byDate={};const rules=[];
  results.forEach(md=>{
    (md.entries||[]).forEach(e=>{if(!byDate[e.date])byDate[e.date]=[];byDate[e.date].push(e);});
    (md.rules||[]).forEach(r=>{if(!rules.find(x=>x.id===r.id))rules.push(r);});
  });
  Object.keys(byDate).forEach(dt=>byDate[dt].sort((a,b)=>(a.time||'').localeCompare(b.time||'')));
  calState.byDate=byDate;calState.rules=rules;
  renderSchedule();
}
function initSchedule(){if(document.getElementById('sm-schedule').innerHTML==='')calLoad();else renderSchedule();}

/* ── MONTH VIEW ── */
function renderMonthView(){
  const {cursor,byDate}=calState;
  const y=cursor.getFullYear(),m=cursor.getMonth();
  const today=fmtDate(new Date());
  const first=new Date(y,m,1).getDay(),days=new Date(y,m+1,0).getDate();
  let html='<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-bottom:6px">'+DAYS.map(d=>`<div class="cal-dow">${d}</div>`).join('')+'</div>';
  html+='<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">';
  for(let i=0;i<first;i++)html+='<div class="cal-cell blank"></div>';
  for(let d=1;d<=days;d++){
    const ds=mkey(y,m+1)+'-'+String(d).padStart(2,'0');
    const ents=byDate[ds]||[];
    const fullBlk=ents.some(e=>e.type==='blackout'&&!e.start_time&&!e.end_time);
    const isTd=ds===today;
    html+=`<div class="cal-cell${fullBlk?' bo':''}${isTd?' today':''}" onclick="openDayModal('${ds}')">`;
    html+=`<div class="cal-day">${d}</div>`;
    if(fullBlk)html+='<div style="font-size:10px;color:var(--red);font-weight:700">✖ Blackout</div>';
    else{
      ents.slice(0,3).forEach(e=>html+=`<div class="sm-chip${e.type==='blackout'?' blk':e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(qlabel(e))}</div>`);
      if(ents.length>3)html+=`<div style="font-size:10px;color:var(--mut)">+${ents.length-3} more</div>`;
    }
    html+='</div>';
  }
  return html+'</div>';
}

/* ── WEEK VIEW ── */
function renderWeekView(){
  const {cursor,byDate}=calState;
  const ws=getSunday(cursor);
  const today=fmtDate(new Date());
  let hd='',bd='';
  for(let i=0;i<7;i++){
    const d=addDays(ws,i),ds=fmtDate(d);
    const isTd=ds===today;
    const ents=byDate[ds]||[];
    const fullBlk=ents.some(e=>e.type==='blackout'&&!e.start_time&&!e.end_time);
    hd+=`<div class="cal-dow" style="${isTd?'color:var(--mint)':''}">${DAYS[d.getDay()]}<br><span style="font-family:var(--mono);font-weight:400">${ds.slice(5)}</span></div>`;
    bd+=`<div class="cal-cell${fullBlk?' bo':''}${isTd?' today':''}" style="min-height:150px" onclick="openDayModal('${ds}')">`;
    if(fullBlk)bd+='<div style="font-size:10px;color:var(--red);font-weight:700">✖ Blackout</div>';
    else ents.slice(0,5).forEach(e=>bd+=`<div class="sm-chip${e.type==='blackout'?' blk':e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(qlabel(e))}</div>`);
    bd+='</div>';
  }
  return `<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-bottom:6px">${hd}</div><div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">${bd}</div>`;
}

/* ── DAY VIEW ── */
function renderDayView(){
  const {cursor,byDate}=calState;
  const ds=fmtDate(cursor);
  const ents=byDate[ds]||[];
  if(!ents.length)return `<div style="text-align:center;padding:48px 0;color:var(--sub)"><div style="font-size:32px">📅</div><div style="font-size:15px;font-weight:500;margin-top:10px">No shows scheduled</div></div>`;
  return ents.map(e=>{
    const isRule=!!e.rule_id,isBlk=e.type==='blackout';
    const c=isBlk?'var(--red)':isRule?'var(--s1)':'var(--mint)';
    return `<div class="dv-entry">
      <div style="width:4px;height:36px;border-radius:2px;background:${c};flex-shrink:0"></div>
      <span style="font-family:var(--mono);font-size:14px;color:${c};min-width:52px">${escH(e.time||'')}</span>
      <span style="flex:1;font-size:14px;font-weight:600">${escH(qlabel(e))}</span>
      ${isRule
        ?`<button class="sm-btn ghost sm" onclick="editRule('${escH(e.rule_id)}')">Edit Rule</button><button class="sm-btn danger sm" onclick="deleteRuleConfirm('${escH(e.rule_id)}')">Del Rule</button>`
        :`<button class="sm-btn danger sm" onclick="deleteEntry('${escH(e.id)}')">Remove</button>`}
    </div>`;
  }).join('');
}

/* ── SCHEDULE RENDER ── */
function renderSchedule(){
  const {view,cursor,rules}=calState;
  const ws=getSunday(cursor);
  const we=addDays(ws,6);
  const title=view==='Month'?MONTHS[cursor.getMonth()]+' '+cursor.getFullYear()
    :view==='Week'?fmtDate(ws)+' – '+fmtDate(we)
    :DAYS[cursor.getDay()]+', '+MONTHS[cursor.getMonth()]+' '+cursor.getDate()+', '+cursor.getFullYear();
  const calHtml=view==='Month'?renderMonthView():view==='Week'?renderWeekView():renderDayView();
  const rulesHtml=rules.length?rules.map(r=>`
  <div class="rule-row">
    <span style="width:10px;height:10px;border-radius:3px;background:var(--s1);flex:none"></span>
    <span style="font-weight:600;font-size:14px;min-width:150px">${escH(r.playlist||'—')}</span>
    <span style="font-family:var(--mono);font-size:12.5px;color:var(--sub)">${(r.days||[]).map(d=>DAYS[d]).join(', ')}</span>
    <span style="font-family:var(--mono);font-size:12.5px;color:var(--sub)">${escH(r.window_start||'')}${r.window_end?' – '+r.window_end:''}</span>
    ${r.interval_mins?`<span style="font-family:var(--mono);font-size:12.5px;color:var(--sub)">every ${r.interval_mins} min</span>`:''}
    <span style="font-family:var(--mono);font-size:12.5px;color:var(--mut)">${escH(r.start_date)} → ${escH(r.end_date)}</span>
    <div style="display:flex;gap:6px;margin-left:auto">
      <button class="sm-btn ghost sm" onclick="editRule('${r.id}')">Edit</button>
      <button class="sm-btn danger sm" onclick="deleteRuleConfirm('${r.id}')">Delete</button>
    </div>
  </div>`).join(''):'<div style="font-size:13px;color:var(--mut)">No rules defined.</div>';

  document.getElementById('sm-schedule').innerHTML=`
<div style="display:flex;flex-direction:column;gap:16px">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <div class="sm-seg">
      ${['Month','Week','Day'].map(v=>`<button class="${v===view?'active':''}" onclick="calSetView('${v}')">${v}</button>`).join('')}
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <button class="sm-btn" style="width:34px;height:34px;padding:0" onclick="calNav(-1)">‹</button>
      <span style="font-weight:700;font-size:16px;min-width:170px;text-align:center">${escH(title)}</span>
      <button class="sm-btn" style="width:34px;height:34px;padding:0" onclick="calNav(1)">›</button>
      <button class="sm-btn ghost" onclick="calGoToday()">Today</button>
    </div>
    <div style="display:flex;gap:8px;margin-left:auto">
      <button class="sm-btn solid" onclick="openAddModal()">+ Add Show</button>
      <button class="sm-btn danger" onclick="openAddModal(true)">Blackout Day</button>
    </div>
  </div>
  <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--sub)">
    <span style="display:flex;align-items:center;gap:6px"><span style="width:11px;height:11px;border-radius:3px;background:var(--mint)"></span>One-off show</span>
    <span style="display:flex;align-items:center;gap:6px"><span style="width:11px;height:11px;border-radius:3px;background:var(--s1)"></span>Rule-generated</span>
    <span style="display:flex;align-items:center;gap:6px"><span style="width:11px;height:11px;border-radius:3px;background:var(--red)"></span>Blackout</span>
  </div>
  <div class="sm-card" style="padding:14px;overflow-x:auto">
    <div style="min-width:640px">${calHtml}</div>
  </div>
  <div class="sm-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div class="sm-ct" style="margin-bottom:0">Repeating Rules</div>
      <button class="sm-btn sm" onclick="openRuleForm()">+ New Rule</button>
    </div>
    ${rulesHtml}
  </div>
</div>`;
}
function calSetView(v){calState.view=v;invalidate();calLoad();}
function calNav(dir){
  const d=new Date(calState.cursor);
  if(calState.view==='Month')d.setMonth(d.getMonth()+dir);
  else if(calState.view==='Week')d.setDate(d.getDate()+dir*7);
  else d.setDate(d.getDate()+dir);
  calState.cursor=d;invalidate();calLoad();
}
function calGoToday(){calState.cursor=new Date();invalidate();calLoad();}

/* ── CRUD ── */
function deleteEntry(id){
  smConfirm('Remove entry?','This removes the entry from the calendar.','Remove',async()=>{
    await fetch(AJAX+'&action=delete_entry&id='+encodeURIComponent(id));
    invalidate();calLoad();toast('Entry removed','mut');
  });
}
function deleteRuleConfirm(id){
  smConfirm('Delete rule?','This removes the repeating rule and all its generated shows from the calendar.','Delete',async()=>{
    await fetch(AJAX+'&action=delete_rule&id='+encodeURIComponent(id));
    invalidate();calLoad();toast('Rule deleted','mut');
  });
}
function editRule(id){
  const r=calState.rules.find(x=>x.id===id);
  if(r)openRuleForm(r);
}

/* ── MODAL ── */
function showModal(html){document.getElementById('sm-modal-layer').innerHTML=html;}
function closeModal(){document.getElementById('sm-modal-layer').innerHTML='';}

/* ── DAY MODAL ── */
function openDayModal(ds){
  const ents=calState.byDate[ds]||[];
  const rows=ents.length?`<div style="background:var(--high);border-radius:10px;padding:6px 14px;margin-bottom:18px">`+ents.map((e,i)=>{
    const isRule=!!e.rule_id,isBlk=e.type==='blackout';
    const c=isBlk?'var(--red)':isRule?'var(--s1)':'var(--mint)';
    return `<div style="display:flex;align-items:center;gap:12px;padding:10px 0;${i<ents.length-1?'border-bottom:1px solid var(--border)':''}">
      <span style="font-family:var(--mono);color:${c};font-size:13px;min-width:44px">${escH(e.time||'')}</span>
      <span style="font-size:14px;flex:1">${escH(qlabel(e))}</span>
      ${isRule
        ?`<button class="sm-btn ghost sm" onclick="editRule('${escH(e.rule_id)}');">Edit Rule</button><button class="sm-btn danger sm" onclick="deleteRuleConfirm('${escH(e.rule_id)}')">Del</button>`
        :`<button class="sm-btn danger sm" onclick="deleteEntry('${escH(e.id)}')">Remove</button>`}
    </div>`;
  }).join('')+'</div>':'<p style="color:var(--mut);font-size:13px;margin-bottom:16px">No entries.</p>';
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal">
      <div style="font-weight:800;font-size:18px;margin-bottom:4px">${escH(ds)}</div>
      <div style="font-size:13px;color:var(--sub);margin-bottom:16px">Entries scheduled for this day.</div>
      ${rows}
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="sm-btn solid sm" onclick="closeModal();openAddModal(false,'${ds}')">+ Add Show</button>
        <button class="sm-btn danger sm" onclick="closeModal();openAddModal(true,'${ds}')">+ Blackout</button>
        <button class="sm-btn ghost sm" onclick="closeModal();calState.cursor=new Date('${ds}T00:00:00');calSetView('Day')">Day View</button>
        <button class="sm-btn sm" style="margin-left:auto" onclick="closeModal()">Close</button>
      </div>
    </div>
  </div>`);
}

/* ── ADD SHOW / BLACKOUT MODAL ── */
function openAddModal(blackout=false,ds=''){
  ds=ds||fmtDate(calState.cursor);
  const pl=PLAYLISTS[0]||'';
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal" style="max-width:400px">
      <div class="sm-mt">${blackout?'Blackout':'Add Show'}</div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <label class="sm-lbl">Date<input type="date" id="add-date" class="sm-input" value="${escH(ds)}"></label>
        ${!blackout?`
        <label class="sm-lbl">Time<input type="time" id="add-time" class="sm-input" value="19:00"></label>
        <label class="sm-lbl">Playlist<select id="add-pl" class="sm-select">${plOptions(pl)}</select></label>`:`
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <label class="sm-lbl">Start (optional)<input type="time" id="add-bo-start" class="sm-input"></label>
          <label class="sm-lbl">End (optional)<input type="time" id="add-bo-end" class="sm-input"></label>
        </div>
        <div class="sm-hint">Leave times blank to block the whole day.</div>`}
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px">
        <button class="sm-btn" onclick="closeModal()">Cancel</button>
        <button class="sm-btn ${blackout?'danger':'solid'}" style="${blackout?'background:var(--red);border-color:var(--red);color:#fff':''}" onclick="saveAddModal(${blackout})">${blackout?'Save Blackout':'Add Show'}</button>
      </div>
    </div>
  </div>`);
}
async function saveAddModal(blackout){
  const date=document.getElementById('add-date').value;
  if(!date)return toast('Pick a date first','err');
  let body;
  if(blackout){
    body={date,type:'blackout'};
    const st=document.getElementById('add-bo-start')?.value;
    const et=document.getElementById('add-bo-end')?.value;
    if(st)body.start_time=st;
    if(et)body.end_time=et;
  } else {
    body={date,type:'show',time:document.getElementById('add-time').value,playlist:document.getElementById('add-pl').value};
  }
  await fetch(AJAX+'&action=save_entry',{method:'POST',body:JSON.stringify(body)});
  closeModal();invalidate();calLoad();
  toast(blackout?'Blackout saved':'Show added to calendar','ok');
}

/* ── RULE FORM MODAL ── */
function openRuleForm(r){
  const isNew=!r;
  r=r||{id:'',start_date:fmtDate(new Date()),end_date:'',days:[0,1,2,3,4,5,6],window_start:'19:00',window_end:'',interval_mins:''};
  const dayLabels=['Su','Mo','Tu','We','Th','Fr','Sa'];
  const dayBtns=dayLabels.map((l,i)=>`<button type="button" class="dow-btn${(r.days||[]).includes(i)?' on':''}" id="rd${i}" onclick="rdToggle(${i})">${l}</button>`).join('');
  const selPl=r.playlist||(r.playlists&&r.playlists[0])||'';
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal" style="max-width:460px">
      <div class="sm-mt">${isNew?'New Repeating Rule':'Edit Rule'}</div>
      <input type="hidden" id="rule-id" value="${escH(r.id)}">
      <div style="display:flex;flex-direction:column;gap:14px">
        <label class="sm-lbl">Playlist<select id="rule-pl" class="sm-select">${plOptions(selPl)}</select></label>
        <div>
          <div style="font-size:12px;color:var(--sub);margin-bottom:7px">Days of week</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">${dayBtns}</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <label class="sm-lbl">Show time<input type="time" id="rule-wstart" class="sm-input" value="${escH(r.window_start||'19:00')}"></label>
          <label class="sm-lbl">Repeat until<input type="time" id="rule-wend" class="sm-input" value="${escH(r.window_end||'')}"></label>
          <label class="sm-lbl">Interval (min)<input type="number" id="rule-iv" class="sm-input" value="${escH(String(r.interval_mins||''))}" min="1" step="1"></label>
          <span></span>
          <label class="sm-lbl">Start date<input type="date" id="rule-start" class="sm-input" value="${escH(r.start_date)}"></label>
          <label class="sm-lbl">End date<input type="date" id="rule-end" class="sm-input" value="${escH(r.end_date||'')}"></label>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px">
        <button class="sm-btn" onclick="closeModal()">Cancel</button>
        <button class="sm-btn solid" onclick="saveRule()">Save Rule</button>
      </div>
    </div>
  </div>`);
}
function rdToggle(i){
  document.getElementById('rd'+i).classList.toggle('on');
}
async function saveRule(){
  const days=[];
  for(let i=0;i<7;i++)if(document.getElementById('rd'+i).classList.contains('on'))days.push(i);
  const body={
    id:document.getElementById('rule-id').value||undefined,
    start_date:document.getElementById('rule-start').value,
    end_date:document.getElementById('rule-end').value,
    days,
    window_start:document.getElementById('rule-wstart').value,
    window_end:document.getElementById('rule-wend').value||undefined,
    interval_mins:document.getElementById('rule-iv').value?parseInt(document.getElementById('rule-iv').value):undefined,
    playlist:document.getElementById('rule-pl').value||undefined,
  };
  if(!body.start_date||!body.end_date||!body.window_start)return toast('Fill in start date, end date and show time','err');
  await fetch(AJAX+'&action=save_rule',{method:'POST',body:JSON.stringify(body)});
  closeModal();invalidate();calLoad();
  toast('Rule saved','ok');
}

/* ── ANNOUNCEMENTS TAB ── */
let annCfg={};
async function loadAnnouncements(){
  const r=await fetch(AJAX+'&action=get_announcements');
  annCfg=await r.json();
  renderAnnouncements();
}
function annGetPreRows(){
  const rows=[];let i=0;
  while(document.getElementById('pre-off-'+i)){
    rows.push({mins_before:parseFloat(document.getElementById('pre-off-'+i).value)||5,file:document.getElementById('pre-file-'+i).value});
    i++;
  }
  return rows;
}
function annAddPre(){annCfg.pre_show=annGetPreRows();annCfg.pre_show.push({mins_before:5,file:''});renderAnnouncements();}
function annRemovePre(i){annCfg.pre_show=annGetPreRows();annCfg.pre_show.splice(i,1);renderAnnouncements();}
function annEnableDaytime(on){
  annCfg.daytime=Object.assign({},annCfg.daytime||{},{enabled:on});
  renderAnnouncements();
}
function renderAnnouncements(){
  const el=document.getElementById('sm-announcements');
  const cfg=annCfg;
  const files=cfg._files||{main:[],daytime:[]};
  const preShow=cfg.pre_show||[{mins_before:5,file:''}];
  const daytime=cfg.daytime||{};
  const allMp3s=[...(files.main||[]),...(files.daytime||[])];
  const datalist=allMp3s.map(f=>`<option value="${escH(f)}">`).join('');
  const preRows=preShow.map((p,i)=>`
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
      <input type="number" class="sm-input" style="width:74px;flex:none" value="${p.mins_before||5}" min="1" max="120" id="pre-off-${i}">
      <span style="font-size:12px;color:var(--mut);flex:none">min</span>
      <input type="text" class="sm-input" value="${escH(p.file||'')}" list="mp3-list" id="pre-file-${i}" placeholder="audio file…">
      <button class="sm-btn ghost sm" style="flex:none;font-size:15px;padding:3px 9px" onclick="annRemovePre(${i})">×</button>
    </div>`).join('');
  const fileRows=[...(files.main||[]).map(f=>({f,folder:'main',path:f})),...(files.daytime||[]).map(f=>({f,folder:'daytime',path:'daytime/'+f}))]
    .map(x=>`<div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
      <span style="font-size:13px;font-family:var(--mono);overflow:hidden;text-overflow:ellipsis">${escH(x.f)}</span>
      <span style="font-size:11px;color:var(--mut);font-family:var(--mono);background:var(--raise);padding:2px 7px;border-radius:5px;flex:none">${x.folder}</span>
      <button class="sm-btn danger sm" style="margin-left:auto;flex:none" onclick="annDeleteFile('${escH(escJ(x.path))}')">Delete</button>
    </div>`).join('');
  const daytimeBody=daytime.enabled?`
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--sub);margin-bottom:12px"><input type="checkbox" id="ann-dt-en" checked style="width:auto">Enabled</label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <label class="sm-lbl">Window start<input type="time" id="ann-dt-start" class="sm-input" value="${escH(daytime.start||'10:00')}"></label>
      <label class="sm-lbl">Window end<input type="time" id="ann-dt-end" class="sm-input" value="${escH(daytime.end||'18:00')}"></label>
      <label class="sm-lbl">Interval (min)<input type="number" id="ann-dt-iv" class="sm-input" value="${daytime.interval_mins??20}" min="5" max="240"></label>
    </div>`:`
    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;padding:22px 10px;border:1px dashed var(--border);border-radius:11px">
      <div style="width:40px;height:40px;border-radius:50%;background:var(--raise);display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:20px">◔</div>
      <div style="font-size:13px;color:var(--sub)">Disabled — no daytime announcements scheduled.</div>
      <button class="sm-btn" style="margin-top:4px" onclick="annEnableDaytime(true)">Enable</button>
    </div>`;
  el.innerHTML=`<datalist id="mp3-list">${datalist}</datalist>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;align-items:start">
    <div class="sm-card">
      <div class="sm-ct" style="margin-bottom:14px">Ducking</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label class="sm-lbl">Duck level (0–1)<input type="number" id="ann-duck" class="sm-input" value="${cfg.duck_level??0.25}" min="0" max="1" step="0.05"></label>
        <label class="sm-lbl">Fade (secs)<input type="number" id="ann-fade" class="sm-input" value="${cfg.duck_fade_secs??2}" min="0.5" max="10" step="0.5"></label>
        <label class="sm-lbl">Gain boost (dB)<input type="number" id="ann-gain" class="sm-input" value="${cfg.gain_db??6}" min="0" max="24"></label>
        <label class="sm-lbl">Max duration (s)<input type="number" id="ann-maxdur" class="sm-input" value="${cfg.max_duration_secs??300}" min="10" max="3600"></label>
      </div>
    </div>
    <div class="sm-card">
      <div class="sm-ct" style="margin-bottom:14px">Lighting</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label class="sm-lbl">Pre-show brightness<input type="number" id="ann-prebright" class="sm-input" value="${cfg.pre_show_brightness??20}" min="0" max="200"></label>
        <label class="sm-lbl">Normal brightness<input type="number" id="ann-normbright" class="sm-input" value="${cfg.normal_brightness??100}" min="0" max="200"></label>
      </div>
    </div>
    <div class="sm-card">
      <div class="sm-ct" style="margin-bottom:14px">Background Music</div>
      <label class="sm-lbl">Playlist to resume after shows<select id="ann-bgpl" class="sm-select">${plOptions(cfg.background_playlist||'')}</select></label>
    </div>
    <div class="sm-card">
      <div class="sm-ct">Pre-Show Announcements</div>
      <p style="font-size:12px;color:var(--sub);margin:0 0 4px">Each row fires one announcement N minutes before show time.</p>
      ${preRows}
      <button class="sm-btn" style="margin-top:12px" onclick="annAddPre()">+ Add row</button>
    </div>
    <div class="sm-card">
      <div class="sm-ct">Daytime Announcements</div>
      ${daytimeBody}
    </div>
    <div class="sm-card">
      <div class="sm-ct">Files</div>
      <label class="sm-lbl" style="margin-bottom:10px">Destination<select id="ann-dest" class="sm-select"><option value="main">Main (pre-show)</option><option value="daytime">Daytime</option></select></label>
      <label style="display:flex;align-items:center;justify-content:center;gap:8px;padding:16px;border:1px dashed var(--border);border-radius:11px;color:var(--sub);font-size:13px;cursor:pointer;margin-bottom:12px">
        ⬆ Upload audio (MP3/WAV/OGG)
        <input type="file" id="ann-file" accept=".mp3,.wav,.ogg" style="display:none" onchange="annUpload()">
      </label>
      ${fileRows||'<div style="font-size:13px;color:var(--mut)">No announcement files uploaded yet.</div>'}
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
      <button class="sm-btn solid" onclick="saveAnnouncements()">Save Settings</button>
    </div>
  </div>`;
}
async function saveAnnouncements(){
  const preShow=annGetPreRows().filter(r=>r.file);
  preShow.sort((a,b)=>b.mins_before-a.mins_before);
  const dtEn=document.getElementById('ann-dt-en');
  const body={
    duck_level:numOr(document.getElementById('ann-duck').value,0.25),
    duck_fade_secs:numOr(document.getElementById('ann-fade').value,2),
    gain_db:numOr(document.getElementById('ann-gain').value,6),
    max_duration_secs:Math.round(numOr(document.getElementById('ann-maxdur').value,300)),
    pre_show_brightness:Math.round(numOr(document.getElementById('ann-prebright').value,20)),
    normal_brightness:Math.round(numOr(document.getElementById('ann-normbright').value,100)),
    background_playlist:document.getElementById('ann-bgpl').value,
    pre_show:preShow,
    daytime:dtEn?{
      enabled:dtEn.checked,
      start:document.getElementById('ann-dt-start')?.value||'10:00',
      end:document.getElementById('ann-dt-end')?.value||'18:00',
      interval_mins:Math.round(numOr(document.getElementById('ann-dt-iv')?.value,20)),
    }:Object.assign({},annCfg.daytime||{},{enabled:false}),
  };
  const r=await fetch(AJAX+'&action=save_announcements',{method:'POST',body:JSON.stringify(body)});
  const j=await r.json();
  if(j.ok){Object.assign(annCfg,body);toast('Changes saved','ok');}
  else toast('Save failed','err');
}
async function annUpload(){
  const fi=document.getElementById('ann-file');
  if(!fi.files.length)return;
  const fd=new FormData();
  fd.append('file',fi.files[0]);
  fd.append('folder',document.getElementById('ann-dest').value);
  const r=await fetch(AJAX+'&action=upload_announcement',{method:'POST',body:fd});
  const j=await r.json();
  if(j.ok){fi.value='';toast('File uploaded','ok');loadAnnouncements();}
  else toast('Upload failed','err');
}
function annDeleteFile(path){
  smConfirm('Delete file?','"'+path+'" will be permanently removed from the Pi.','Delete',async()=>{
    await fetch(AJAX+'&action=delete_announcement&path='+encodeURIComponent(path));
    toast('File deleted','mut');
    loadAnnouncements();
  });
}

/* ── HARDWARE TAB ── */
async function loadHardware(){
  const r=await fetch(AJAX+'&action=get_hardware');
  const cfg=await r.json();
  renderHardware(cfg);
}
function renderHardware(cfg){
  const el=document.getElementById('sm-hardware');
  const ip=escH(cfg.mixer_ip||'');
  const ch=escH(cfg.fader_channel!=null?String(cfg.fader_channel):'1');
  const lvl=escH(cfg.show_level!=null?String(cfg.show_level):'0.75');
  const idle=escH(cfg.idle_level!=null?String(cfg.idle_level):'0');
  const ach=escH(cfg.announce_ch!=null?String(parseInt(cfg.announce_ch)):'3');
  const avol=escH(cfg.announce_vol!=null?String(cfg.announce_vol):'0.75');
  el.innerHTML=`<div style="max-width:560px">
    <div class="sm-card" style="padding:22px 24px">
      <div style="font-weight:700;font-size:16px;margin-bottom:4px">Behringer XR18 Mixer</div>
      <div style="font-size:12.5px;color:var(--sub);margin-bottom:20px">Saving restarts the OSC bridge automatically.</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <label class="sm-lbl">Mixer IP<input type="text" id="hw-ip" class="sm-input" value="${ip}" placeholder="192.168.1.x"></label>
        <label class="sm-lbl">Music fader channel<input type="number" id="hw-ch" class="sm-input" value="${ch}" min="1" max="18"></label>
        <label class="sm-lbl">Show level (0–1)<input type="number" id="hw-lvl" class="sm-input" value="${lvl}" min="0" max="1" step="0.01"></label>
        <label class="sm-lbl">Idle level (0–1)<input type="number" id="hw-idle" class="sm-input" value="${idle}" min="0" max="1" step="0.01"></label>
        <label class="sm-lbl">Announce channel<input type="number" id="hw-ach" class="sm-input" value="${ach}" min="1" max="18"></label>
        <label class="sm-lbl">Announce level (0–1)<input type="number" id="hw-avol" class="sm-input" value="${avol}" min="0" max="1" step="0.01"></label>
      </div>
      <div class="sm-hint" style="margin-top:12px">Music faders fade to the show level when a show starts, and to the idle level after it ends.</div>
      <button class="sm-btn solid" style="margin-top:18px;font-size:14px;padding:11px 20px" onclick="saveHardware()">Save &amp; restart bridge</button>
    </div>
  </div>`;
}
async function saveHardware(){
  const body={
    mixer_ip:document.getElementById('hw-ip').value,
    fader_channel:Math.round(numOr(document.getElementById('hw-ch').value,1)),
    show_level:numOr(document.getElementById('hw-lvl').value,0.75),
    idle_level:numOr(document.getElementById('hw-idle').value,0),
    announce_ch:Math.round(numOr(document.getElementById('hw-ach').value,3)),
    announce_vol:numOr(document.getElementById('hw-avol').value,0.75),
  };
  const r=await fetch(AJAX+'&action=save_hardware',{method:'POST',body:JSON.stringify(body)});
  const j=await r.json();
  if(j.ok)toast('Saved — bridge restarted','ok');
  else toast('Save failed','err');
}

/* ── INIT ── */
smApplyTheme();
buildGarland();
loadStatus();
_npTick();setInterval(_npTick,5000);
</script>
