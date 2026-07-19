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
/* Native-feel skin: inherits FPP's fonts and page colors; grey-alpha surfaces
   work on FPP's light content panel and any dark variant. The kiosk keeps its
   own themed look — this applies only to the embedded settings pages. */
#sm{
  --bg:transparent;--card:rgba(127,127,127,.07);--raise:rgba(127,127,127,.13);--high:rgba(127,127,127,.10);
  --border:rgba(127,127,127,.30);--brdHi:rgba(127,127,127,.45);
  --text:currentColor;--sub:#6c757d;--mut:#9aa4ae;
  --mint:#28a745;--amber:#b45309;--red:#dc2626;--s1:#2563eb;--s2:#7c3aed;
  --mintBg:rgba(40,167,69,.14);--mintBrd:rgba(40,167,69,.4);--mintInk:#ffffff;
  --amberBg:rgba(180,83,9,.12);--amberBrd:rgba(180,83,9,.4);
  --redBg:rgba(220,38,38,.10);--redBrd:rgba(220,38,38,.35);
  --s1Bg:rgba(37,99,235,.10);--s1Brd:rgba(37,99,235,.3);
  --font:'PP Neue Montreal','Neue Montreal','Inter','Helvetica Neue','Segoe UI',Roboto,Arial,sans-serif;
  --mono:ui-monospace,'SF Mono','Cascadia Code',Menlo,Consolas,monospace;
  position:relative;min-height:400px;font-family:var(--font);
}
#sm *{box-sizing:border-box}
#sm button{font-family:inherit;cursor:pointer}
@keyframes sm-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.5);opacity:.55}}
@keyframes sm-slide{from{transform:translateY(8px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes sm-shimmer{0%{background-position:-320px 0}100%{background-position:320px 0}}
.sm-skel{background:linear-gradient(90deg,var(--raise) 0px,var(--high) 160px,var(--raise) 320px);background-size:640px 100%;animation:sm-shimmer 1.2s linear infinite;border-radius:6px}
.sm-wrap{position:relative;width:100%;margin:0;padding:6px 4px 30px}
/* now playing strip */
#sm-nowplaying{display:flex;align-items:center;gap:12px;padding:10px 16px;border-radius:6px;margin-bottom:14px;border:1px solid var(--border);background:var(--card)}
#sm-nowplaying.on{background:var(--amberBg);border-color:var(--amberBrd)}
#sm-nowplaying.off{background:var(--redBg);border-color:var(--redBrd)}
#sm-np-dot{width:11px;height:11px;border-radius:50%;background:var(--mut);flex:none}
#sm-nowplaying.on #sm-np-dot{background:var(--amber);animation:sm-pulse 1.6s ease-in-out infinite}
#sm-nowplaying.off #sm-np-dot{background:var(--red)}
#sm-np-label{font-weight:700;letter-spacing:.04em;font-size:13px;color:var(--sub)}
#sm-nowplaying.on #sm-np-label{color:var(--amber)}
#sm-nowplaying.off #sm-np-label{color:var(--red)}
#sm-np-sub{font-weight:600;font-size:13px}
/* tabs — underline style; scroll horizontally on small screens instead of wrapping/clipping */
#sm-tabs{display:flex;align-items:center;gap:2px;border-bottom:1px solid var(--border);margin-bottom:18px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
#sm-tabs::-webkit-scrollbar{display:none}
.sm-tab{appearance:none;border:none;background:transparent;color:inherit;opacity:.6;font-size:14px;padding:10px 16px;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap;flex:none}
.sm-tab:hover{opacity:.9}
.sm-tab.active{opacity:1;font-weight:600;border-bottom-color:var(--mint)}
@media (max-width:600px){.sm-tab{padding:9px 11px;font-size:13px}}
/* cards — no outline, soft under-shadow */
.sm-card{background:var(--card);border:none;border-radius:8px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.10),0 6px 16px rgba(0,0,0,.07)}
/* 3-across responsive card grid (full width) */
.sm-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;align-items:start}
@media (max-width:1000px){.sm-grid3{grid-template-columns:repeat(2,1fr)}}
@media (max-width:640px){.sm-grid3{grid-template-columns:1fr}}
.sm-ct{font-weight:700;font-size:15px;margin-bottom:10px}
/* buttons — pill shaped, FPP-native */
.sm-btn{appearance:none;display:inline-flex;align-items:center;justify-content:center;gap:6px;background:var(--raise);border:1px solid var(--border);color:inherit;font-weight:600;font-size:13px;padding:7px 16px;border-radius:999px;white-space:nowrap}
.sm-btn svg{flex:none}
.sm-btn:hover{background:var(--high)}
.sm-btn.ghost{background:transparent;color:var(--sub)}
.sm-btn.solid{background:var(--mint);border-color:var(--mint);color:var(--mintInk);font-weight:600}
.sm-btn.danger{background:transparent;border-color:var(--redBrd);color:var(--red)}
.sm-btn.sm{padding:4px 12px;font-size:12px}
/* status pill */
.sm-pill{font-family:var(--mono);font-size:12px;font-weight:600;padding:3px 11px;border-radius:999px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sm-pill.ok{background:var(--mintBg);color:var(--mint)}
.sm-pill.idle{background:var(--amberBg);color:var(--amber)}
.sm-pill.bad{background:var(--redBg);color:var(--red)}
/* inputs — light touch, let FPP/browser defaults do most of the work */
.sm-input,.sm-select{width:100%;padding:7px 10px;font-size:13px;border:1px solid var(--brdHi);border-radius:4px;background:transparent;color:inherit}
.sm-input:focus,.sm-select:focus{border-color:var(--mint);outline:none}
/* FPP's own input styling stretches checkboxes into bars — force native */
#sm input[type=checkbox]{appearance:auto;-webkit-appearance:auto;width:16px;height:16px;min-width:16px;flex:none;margin:0;padding:0;accent-color:var(--mint)}
.sm-lbl{display:block;font-size:12px;color:var(--sub)}
.sm-lbl .sm-input,.sm-lbl .sm-select{margin-top:4px}
.sm-hint{font-size:12px;color:var(--mut)}
/* wells (logs) */
.sm-well{background:var(--high);border:1px solid var(--border);border-radius:5px;padding:10px 12px;overflow:auto;font-family:var(--mono);font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-all}
/* badges + chips + dots */
.sm-badge{margin-left:auto;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;padding:2px 8px;border-radius:999px;flex:none}
.sm-badge.mint{background:var(--mintBg);color:var(--mint)}
.sm-badge.amber{background:var(--amberBg);color:var(--amber)}
.sm-chip{border-radius:4px;padding:1px 6px;font-size:11px;font-family:var(--mono);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.5;background:var(--mintBg);color:var(--mint);border:1px solid var(--mintBrd)}
.sm-chip.rule{background:var(--s1Bg);color:var(--s1);border-color:var(--s1Brd)}
.sm-chip.blk{background:var(--redBg);color:var(--red);border-color:var(--redBrd)}
.sm-chip.bgm{background:rgba(124,58,237,.12);color:#7c3aed;border-color:rgba(124,58,237,.32)}
.sm-chip.bgf{background:rgba(8,145,178,.12);color:#0e7490;border-color:rgba(8,145,178,.32)}
/* segmented */
.sm-seg{display:flex;gap:0;border:1px solid var(--border);border-radius:5px;overflow:hidden}
.sm-seg button{appearance:none;border:none;padding:6px 14px;font-size:13px;background:transparent;color:inherit;opacity:.7}
.sm-seg button.active{background:var(--raise);opacity:1;font-weight:600}
/* calendar */
.cal-dow{text-align:center;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--mut);font-weight:700;padding:4px 0}
.cal-cell{min-height:92px;padding:6px;border-radius:4px;border:1px solid var(--border);background:var(--bg);cursor:pointer;display:flex;flex-direction:column;gap:3px;overflow:hidden}
.cal-cell:hover{background:var(--card)}
.cal-cell.blank{background:transparent;border-color:transparent;cursor:default}
.cal-cell.bo{background:var(--redBg)}
.cal-cell.today{outline:2px solid var(--mint);outline-offset:-2px}
.cal-day{font-size:12px;font-weight:600;color:var(--sub);padding:1px 3px}
/* day view + rules */
.dv-entry{display:flex;align-items:center;gap:14px;padding:10px 14px;border-radius:5px;background:var(--card);border:1px solid var(--border);margin-bottom:8px}
.rule-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid var(--border)}
.rule-row:last-child{border-bottom:none}
.dow-btn{appearance:none;font-weight:600;font-size:13px;padding:6px 0;width:38px;border-radius:4px;border:1px solid var(--border);background:var(--raise);color:inherit;opacity:.75}
.dow-btn.on{background:var(--mint);color:var(--mintInk);border-color:var(--mint);opacity:1}
/* modal — solid so it reads over any page */
.sm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px}
.sm-modal{background:#fff;color:#212529;border:1px solid rgba(0,0,0,.2);border-radius:8px;padding:22px;width:100%;max-width:440px;max-height:88vh;overflow:auto;box-shadow:0 16px 48px rgba(0,0,0,.35)}
.sm-mt{font-weight:700;font-size:17px;margin-bottom:16px}
/* toasts — solid dark, readable anywhere */
#sm-toasts{position:fixed;right:18px;bottom:18px;z-index:10000;display:flex;flex-direction:column;gap:10px}
.sm-toast{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:6px;background:#23272b;color:#f8f9fa;border:1px solid rgba(255,255,255,.15);box-shadow:0 6px 20px rgba(0,0,0,.3);min-width:220px;animation:sm-slide .25s ease;cursor:pointer;font-size:13px}
.sm-toast .dot{width:8px;height:8px;border-radius:50%;flex:none}
</style>
<div id="sm">
  <div class="sm-wrap">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <h2 style="margin:0;font-size:22px">Show Manager</h2>
      <span style="font-size:12px;color:var(--sub);font-family:var(--mono)"><span id="sm-host"></span></span>
      <a href="plugin.php?plugin=<?= basename(__DIR__) ?>&page=kiosk.php&nopage=1" target="_blank" style="margin-left:auto;text-decoration:none"><button class="sm-btn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>Kiosk</button></a>
    </div>
    <div id="sm-nowplaying">
      <span id="sm-np-dot"></span>
      <span id="sm-np-label">…</span>
      <span id="sm-np-sub"></span>
    </div>
    <div id="sm-tabs">
      <button class="sm-tab active" onclick="smTab('status')">Status</button>
      <button class="sm-tab" onclick="smTab('schedule')">Schedule</button>
      <button class="sm-tab" onclick="smTab('background')">Background</button>
      <button class="sm-tab" onclick="smTab('announcements')">Announcements</button>
      <button class="sm-tab" onclick="smTab('hardware')">Hardware</button>
      <button class="sm-tab" onclick="smTab('system')">System</button>
    </div>
    <div id="sm-status" class="sm-pane"></div>
    <div id="sm-schedule" class="sm-pane" style="display:none"></div>
    <div id="sm-background" class="sm-pane" style="display:none"></div>
    <div id="sm-announcements" class="sm-pane" style="display:none"></div>
    <div id="sm-hardware" class="sm-pane" style="display:none"></div>
    <div id="sm-system" class="sm-pane" style="display:none"></div>
  </div>
  <div id="sm-modal-layer"></div>
  <div id="sm-toasts"></div>
</div>
<script>
const AJAX='<?= $AJAX ?>';
const PLAYLISTS=<?= $plJson ?>;
const DAYS=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];

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
    return 'Blackout'+range;
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

/* ── TABS ── */
function smTab(name){
  document.querySelectorAll('.sm-tab').forEach((b,i)=>b.classList.toggle('active',['status','schedule','background','announcements','hardware','system'][i]===name));
  document.querySelectorAll('.sm-pane').forEach(p=>p.style.display='none');
  document.getElementById('sm-'+name).style.display='';
  if(name!=='status'&&statusTimer){clearInterval(statusTimer);statusTimer=null;clearInterval(_cdTimer);_cdTimer=null;}
  ({status:loadStatus,schedule:initSchedule,background:loadBackground,announcements:loadAnnouncements,hardware:loadHardware,system:loadSystem})[name]?.();
}

/* ── NOW PLAYING STRIP ── */
let npNext='';
function _cleanName(s){s=String(s||'').split('/').pop();return s.replace(/\.[^.]+$/,'');}
/* song metadata (title/artist) from the playing file — fetched only on change.
   Concurrent callers (npTick + status tick) await the same in-flight fetch. */
let songMeta=null,songMetaFor='',songMetaPromise=null;
function _metaStr(){const m=songMeta;return m?((m.artist?m.artist+' — ':'')+(m.title||'')).trim():'';}
async function _refreshSongMeta(file){
  if(file===songMetaFor){if(songMetaPromise)await songMetaPromise;return;}
  songMetaFor=file;
  if(!file){songMeta=null;songMetaPromise=null;return;}
  songMetaPromise=(async()=>{
    const m=await fetch(AJAX+'&action=song_meta&file='+encodeURIComponent(file)).then(r=>r.json()).catch(()=>null);
    songMeta=(m&&m.ok&&(m.title||m.artist))?m:null;
  })();
  await songMetaPromise;
}
function _nowTrack(fpp){
  const meta=_metaStr();
  if(meta)return meta;
  const pl=fpp.current_playlist?.playlist||fpp.current_playlist?.name||'';
  const seq=_cleanName(fpp.current_sequence||fpp.current_song||'');
  return seq&&seq!==pl?(seq+(pl?' · '+pl:'')):(pl||seq);
}
/* True when FPP is playing the configured background-music playlist (not a show) */
function _isBgMusic(fpp,bgName){
  const cur=fpp.current_playlist?.playlist||fpp.current_playlist?.name||'';
  const playing=fpp.status===1||fpp.status==='playing';
  return !!(playing&&bgName&&cur===bgName);
}
async function _npTick(){
  const [r,ov,xr]=await Promise.all([
    fetch('/api/fppd/status').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_override').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_status').then(r=>r.json()).catch(()=>({})),
  ]);
  const playing=r.status===1||r.status==='playing';
  await _refreshSongMeta(playing?(r.current_song||''):'');
  const bgMusic=_isBgMusic(r,xr.bg_music_playlist);
  const isShow=playing&&!bgMusic;
  const disabled=!!ov.disabled_until;
  const np=document.getElementById('sm-nowplaying');
  if(!np)return;
  np.classList.toggle('off',disabled);
  np.classList.toggle('on',isShow&&!disabled);   // amber only for a real show
  const label=document.getElementById('sm-np-label');
  const sub=document.getElementById('sm-np-sub');
  if(disabled){
    label.textContent='DISABLED';
    const t=new Date(ov.disabled_until);
    sub.textContent='until '+t.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
  }else{
    label.textContent=isShow?'SHOW RUNNING':(bgMusic?'BG MUSIC PLAYING':'IDLE');
    sub.textContent=playing?_nowTrack(r):(npNext?'Next show '+npNext:'');
  }
  const host=r.HostName||r.hostname;
  if(host)document.getElementById('sm-host').textContent=' · '+host;
}

/* ── STATUS ── */
let statusTimer=null;
let triggerLog=[];
let logPaused=false;
let tlBg=null,tlAnn=null,tlEvents=[],_cdTimer=null;

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
  clearInterval(_cdTimer);_cdTimer=setInterval(_updateCountdown,1000);
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
  const bgMusic=_isBgMusic(fpp,xr.bg_music_playlist);
  const isShow=playing&&!bgMusic;
  const state=isShow?'SHOW RUNNING':(bgMusic?'BG MUSIC PLAYING':'IDLE');
  const stateColor=isShow?'var(--amber)':(bgMusic?'var(--mint)':'var(--sub)');
  const vol=fpp.volume!=null?fpp.volume:null;
  const fader=xr.xr18_fader!=null?xr.xr18_fader:null;
  const volPct=vol!=null?Math.max(0,Math.min(100,vol)):0;
  const fadPct=fader!=null?Math.max(0,Math.min(100,Math.round(fader*100))):0;
  return `
  <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;justify-content:space-between">
    <div>
      <div style="margin-bottom:6px">
        <span style="font-weight:800;letter-spacing:.04em;font-size:14px;color:${stateColor}">${state}</span>
      </div>
      <div style="font-size:32px;font-weight:800;letter-spacing:-.02em">${escH(playing?curPl:'Idle')}</div>
      <div style="color:var(--sub);font-size:13px;margin-top:6px;font-family:var(--mono)">${(()=>{const nowLine=playing?(_metaStr()||_cleanName(fpp.current_sequence||fpp.current_song||'')):'';return nowLine?escH(nowLine)+' &nbsp;·&nbsp; ':'';})()}uptime ${escH(fpp.uptime||'—')}</div>
    </div>
    <div style="display:flex;gap:30px">
      <div style="text-align:right;min-width:110px">
        <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);font-weight:700">FPP Volume</div>
        <div style="font-size:30px;font-weight:800;font-family:var(--mono);margin-top:4px">${vol!=null?vol+'%':'—'}</div>
        <div style="height:5px;border-radius:3px;background:var(--high);margin-top:8px;overflow:hidden"><div style="width:${volPct}%;height:100%;border-radius:3px;background:var(--mint)"></div></div>
      </div>
      <div style="text-align:right;min-width:110px">
        <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);font-weight:700">XR18 Fader</div>
        <div style="font-size:30px;font-weight:800;font-family:var(--mono);margin-top:4px">${fader!=null?fader.toFixed(2):'—'}</div>
        <div style="height:5px;border-radius:3px;background:var(--high);margin-top:8px;overflow:hidden"><div style="width:${fadPct}%;height:100%;border-radius:3px;background:var(--mint)"></div></div>
      </div>
    </div>
  </div>`;
}
function _statsHtml(fpp,xr,shows,upcoming){
  return [
    ['FPP Version',fpp.version||'—'],
    ['Instance',fpp.HostName||fpp.hostname||'—'],
    ['Shows Today',shows.length],
    ['Upcoming',upcoming.length],
  ].map(([l,v])=>`
  <div class="sm-card" style="padding:14px 16px">
    <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--mut);font-weight:700;margin-bottom:10px">${l}</div>
    <div style="font-size:24px;font-weight:800;font-family:var(--mono);word-break:break-all;line-height:1.2">${escH(String(v))}</div>
  </div>`).join('');
}
function _sysHtml(fpp,xr,running){
  // state: 'ok' green · 'idle' amber · 'bad' red
  const rows=[
    ['FPP Daemon',fpp.fppd==='running'?'ok':'bad',fpp.fppd==='running'?'Running':'Stopped'],
    ['XR18 Mixer',xr.xr18_fader!=null?'ok':'bad',xr.xr18_fader!=null?('Fader '+xr.xr18_fader.toFixed(2)):'N/A'],
    ['Scheduler',running?'ok':'bad',running?'Active':'Stopped'],
  ];
  const bg=xr.background||null;
  if(bg&&bg.music_enabled) rows.push(['BG Music',bg.music?'ok':'idle',bg.music||'Idle']);
  if(bg&&bg.effect_enabled) rows.push(['BG Effect',bg.effect?'ok':'idle',bg.effect||'Idle']);
  return rows.map(([l,st,txt])=>`
  <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
    <span style="font-size:14px">${l}</span>
    <span class="sm-pill ${st}" style="margin-left:auto">${escH(txt)}</span>
  </div>`).join('');
}

/* ── tonight's timeline (shows + pre-show announcements + background windows) ── */
function _showName(s){return s.playlist||(s.playlists||[]).join(' / ')||'(show)';}
function _addMin(hm,delta){
  const p=(hm||'').split(':');if(p.length!==2)return null;
  let m=(+p[0])*60+(+p[1])+delta;
  if(m<0||m>=1440)return null;   // spilled to another day — skip
  return String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0');
}
function _computeEvents(d){
  const ev=[],shows=d.shows||[];
  shows.forEach(s=>ev.push({t:s.time,kind:'show',label:'Show — '+_showName(s)}));
  const pre=(tlAnn&&tlAnn.pre_show)||[];
  shows.forEach(s=>pre.forEach(p=>{
    if(!p.file)return;
    const mb=Math.round(+p.mins_before||0),t=_addMin(s.time,-mb);
    if(t)ev.push({t,kind:'ann',label:'Announcement — '+mb+'m before '+_showName(s)});
  }));
  if(tlBg){
    const m=tlBg.music||{},e=tlBg.effect||{};
    if(m.enabled&&m.playlist&&m.start&&m.start!==m.end){ev.push({t:m.start,kind:'bgm',label:'Background music starts'});ev.push({t:m.end,kind:'bgm',label:'Background music ends'});}
    if(e.enabled&&e.effect&&e.start&&e.start!==e.end){ev.push({t:e.start,kind:'bgf',label:'Background effect starts'});ev.push({t:e.end,kind:'bgf',label:'Background effect ends'});}
  }
  ev.sort((a,b)=>a.t.localeCompare(b.t));
  tlEvents=ev;return ev;
}
function _eventDate(t){const p=t.split(':'),d=new Date();d.setHours(+p[0],+p[1],0,0);return d;}
function _fmtCountdown(secs){const s=Math.max(0,Math.round(secs)),h=Math.floor(s/3600),m=Math.floor(s%3600/60),ss=s%60;return h>0?h+'h '+m+'m':(m>0?m+'m '+ss+'s':ss+'s');}
function _updateCountdown(){
  const elC=document.getElementById('sm-next-countdown');if(!elC)return;
  const elL=document.getElementById('sm-next-label'),now=new Date();
  let nextEv=null,best=Infinity;
  tlEvents.forEach(ev=>{const diff=(_eventDate(ev.t)-now)/1000;if(diff>=0&&diff<best){best=diff;nextEv=ev;}});
  if(!nextEv){elC.textContent='—';if(elL)elL.textContent='Nothing else scheduled today';return;}
  elC.textContent=_fmtCountdown(best);
  if(elL)elL.textContent=nextEv.label+' · '+nextEv.t;
}
/* inline line icons (Lucide-style) — no external font dependency, inherit color */
function _ico(kind){
  const a='width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"';
  const fill='width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="display:block"';
  return {
    show:`<svg ${fill}><path d="M7 4v16l13-8z"/></svg>`,
    play:`<svg ${fill}><path d="M7 4v16l13-8z"/></svg>`,
    ann:`<svg ${a}><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>`,
    bgm:`<svg ${a}><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>`,
    bgf:`<svg ${a}><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>`,
    download:`<svg ${a}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`,
    upload:`<svg ${a}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>`,
    cloudup:`<svg ${a}><path d="M12 13v8"/><path d="m8 17 4-4 4 4"/><path d="M4 14.9A7 7 0 1 1 15.7 8h1.8a4.5 4.5 0 0 1 2.5 8.2"/></svg>`,
    refresh:`<svg ${a}><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg>`,
    warn:`<svg ${a}><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h16.9a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    extlink:`<svg ${a}><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>`,
    ban:`<svg ${a}><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>`,
    expand:`<svg ${a}><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>`,
  }[kind]||'';
}
function _timelineHtml(d){
  _computeEvents(d);
  if(d.fullBlackout)return '<div style="font-size:13px;color:var(--red);font-weight:600">Blackout day — shows and background music suppressed</div>';
  if(!tlEvents.length)return '<div style="font-size:13px;color:var(--mut)">Nothing scheduled today</div>';
  const now=new Date().toTimeString().slice(0,5);
  const iconColor={show:'var(--mint)',ann:'var(--amber)',bgm:'#7c3aed',bgf:'#0e7490'};
  const nextT=(tlEvents.find(ev=>ev.t>=now)||{}).t;
  const rows=tlEvents.map(ev=>{
    const past=ev.t<now,isNext=ev.t===nextT&&!past;
    return `<div style="display:flex;align-items:center;gap:12px;padding:7px 0;border-bottom:1px solid var(--border);opacity:${past?.4:1}">
      <span style="font-family:var(--mono);font-size:13px;font-weight:600;width:46px">${escH(ev.t)}</span>
      <span style="width:16px;display:inline-flex;align-items:center;justify-content:center;color:${iconColor[ev.kind]||'var(--sub)'}">${_ico(ev.kind)}</span>
      <span style="font-size:13px;${isNext?'font-weight:600':''}">${escH(ev.label)}</span>
      ${isNext?'<span class="sm-badge mint" style="margin-left:auto">Next</span>':''}
    </div>`;
  }).join('');
  return `<div style="display:flex;align-items:baseline;gap:8px;margin-bottom:2px">
      <span style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--mut);font-weight:700">Next up in</span>
      <span id="sm-next-countdown" style="font-size:22px;font-weight:800;font-family:var(--mono)">—</span>
    </div>
    <div id="sm-next-label" style="font-size:12px;color:var(--sub);margin-bottom:12px">&nbsp;</div>
    ${rows}`;
}
function _warnHtml(ws){
  if(!ws||!ws.length)return '';
  const hasBad=ws.some(w=>w.level==='bad');
  return `<div class="sm-card" style="border-left:3px solid ${hasBad?'var(--red)':'var(--amber)'}">
    <div class="sm-ct" style="margin-bottom:8px;display:flex;align-items:center;gap:7px"><span style="color:${hasBad?'var(--red)':'var(--amber)'};display:inline-flex">${_ico('warn')}</span>${ws.length} schedule ${ws.length===1?'warning':'warnings'}</div>
    ${ws.map(w=>`<div style="display:flex;gap:8px;padding:4px 0;font-size:13px;align-items:flex-start"><span style="color:${w.level==='bad'?'var(--red)':'var(--amber)'};line-height:1.5">●</span><span>${escH(w.text)}</span></div>`).join('')}
  </div>`;
}

async function renderStatus(){
  const el=document.getElementById('sm-status');
  const [d,bg,ann,warn]=await Promise.all([
    _fetchStatus(),
    fetch(AJAX+'&action=get_background').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_announcements').then(r=>r.json()).catch(()=>({})),
    fetch(AJAX+'&action=get_warnings').then(r=>r.json()).catch(()=>({warnings:[]})),
  ]);
  tlBg=bg;tlAnn=ann;
  const {fpp,xr,log}=d;
  await _refreshSongMeta((fpp.status===1||fpp.status==='playing')?(fpp.current_song||''):'');
  el.innerHTML=`
<div style="display:flex;flex-direction:column;gap:16px">
  <div id="sm-warnings">${_warnHtml(warn.warnings)}</div>
  <div class="sm-card" style="padding:20px 22px">
    <div id="sm-hero">${_heroHtml(fpp,xr)}</div>
  </div>
  <div id="sm-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">${_statsHtml(fpp,xr,d.shows,d.upcoming)}</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="sm-card">
        <div class="sm-ct" style="margin-bottom:4px">Manual Trigger</div>
        <p style="font-size:12px;color:var(--sub);margin:0 0 12px"><b>Run Show</b> uses the full pipeline (dim, fader levels, post-show fade). <b>Start</b> is a raw FPP start for testing.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <select id="trig-pl" class="sm-select" style="flex:1;min-width:140px">${plOptions('')}</select>
          <button class="sm-btn solid" onclick="runShow()">${_ico('play')}Run Show</button>
          <button class="sm-btn" onclick="triggerPlaylist()">Start</button>
          <button class="sm-btn" onclick="stopPlaylist()">Stop</button>
        </div>
      </div>
      <div class="sm-card">
        <div class="sm-ct" style="margin-bottom:6px">Tonight's Timeline</div>
        <div id="sm-sched">${_timelineHtml(d)}</div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="sm-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <div class="sm-ct" style="margin-bottom:0">System</div>
          <button class="sm-btn ghost sm" onclick="restartScheduler()">${_ico('refresh')}Restart Scheduler</button>
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
  const playing=fpp.status===1||fpp.status==='playing';
  await _refreshSongMeta(playing?(fpp.current_song||''):'');
  const hero=document.getElementById('sm-hero');if(hero)hero.innerHTML=_heroHtml(fpp,xr);
  const stats=document.getElementById('sm-stats');if(stats)stats.innerHTML=_statsHtml(fpp,xr,d.shows,d.upcoming);
  const scEl=document.getElementById('sm-sched');if(scEl)scEl.innerHTML=_timelineHtml(d);
  _updateCountdown();
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
async function runShow(){
  const pl=document.getElementById('trig-pl').value;
  if(!pl)return toast('Select a playlist first','err');
  _appendTriggerLog('[Run Show] '+pl+' — through the full pipeline…');
  const r=await fetch(AJAX+'&action=trigger_show&playlist='+encodeURIComponent(pl)).then(r=>r.json()).catch(e=>({error:String(e)}));
  if(r.scheduler_running===false){
    _appendTriggerLog('  Scheduler is NOT running — start it first (System tab / Restart Scheduler).');
    return toast('Scheduler not running — start it first','err');
  }
  _appendTriggerLog('  Queued — the scheduler will dim, set levels, and start the show within a few seconds.');
  toast('Running "'+pl+'" through the show pipeline','ok');
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
let calState={view:'Month',cursor:new Date(),byDate:{},rules:[],bg:{}};
async function calLoad(){
  const {view,cursor}=calState;
  const pairs=new Set();
  const y=cursor.getFullYear(),m=cursor.getMonth()+1;
  pairs.add(mkey(y,m));
  if(view==='Week'){const we=addDays(getSunday(cursor),6);pairs.add(mkey(we.getFullYear(),we.getMonth()+1));}
  const [results,bg]=await Promise.all([
    Promise.all([...pairs].map(k=>{const[y,m]=k.split('-');return fetchMonth(+y,+m);})),
    fetch(AJAX+'&action=get_background').then(r=>r.json()).catch(()=>({})),
  ]);
  const byDate={};const rules=[];
  results.forEach(md=>{
    (md.entries||[]).forEach(e=>{if(!byDate[e.date])byDate[e.date]=[];byDate[e.date].push(e);});
    (md.rules||[]).forEach(r=>{if(!rules.find(x=>x.id===r.id))rules.push(r);});
  });
  Object.keys(byDate).forEach(dt=>byDate[dt].sort((a,b)=>(a.time||'').localeCompare(b.time||'')));
  calState.byDate=byDate;calState.rules=rules;calState.bg=bg||{};
  renderSchedule();
}
/* Background music/effect windows run daily — shown on every day cell.
   Effect runs through blackouts (lighting only); music does not (audio). */
function _bgWindows(){
  const bg=calState.bg||{},m=bg.music||{},e=bg.effect||{},out=[];
  if(m.enabled&&m.playlist) out.push({cls:'bgm',lbl:'Music',range:(m.start||'00:00')+'–'+(m.end||'00:00')});
  if(e.enabled&&e.effect)   out.push({cls:'bgf',lbl:'Effect',range:(e.start||'00:00')+'–'+(e.end||'00:00')});
  return out;
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
    if(fullBlk)html+='<div style="font-size:10px;color:var(--red);font-weight:700;display:flex;align-items:center;gap:3px"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex:none"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>Blackout</div>';
    else{
      ents.slice(0,3).forEach(e=>html+=`<div class="sm-chip${e.type==='blackout'?' blk':e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(qlabel(e))}</div>`);
      if(ents.length>3)html+=`<div style="font-size:10px;color:var(--mut)">+${ents.length-3} more</div>`;
      const bw=_bgWindows();
      if(bw.length)html+='<div style="margin-top:auto;padding-top:3px">'+bw.map(w=>`<div class="sm-chip ${w.cls}">${w.lbl} ${escH(w.range)}</div>`).join('')+'</div>';
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
    if(fullBlk)bd+='<div style="font-size:10px;color:var(--red);font-weight:700;display:flex;align-items:center;gap:3px"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex:none"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>Blackout</div>';
    else{
      ents.slice(0,5).forEach(e=>bd+=`<div class="sm-chip${e.type==='blackout'?' blk':e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(qlabel(e))}</div>`);
      const bw=_bgWindows();
      if(bw.length)bd+='<div style="margin-top:auto;padding-top:3px">'+bw.map(w=>`<div class="sm-chip ${w.cls}">${w.lbl} ${escH(w.range)}</div>`).join('')+'</div>';
    }
    bd+='</div>';
  }
  return `<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-bottom:6px">${hd}</div><div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">${bd}</div>`;
}

/* ── DAY VIEW ── */
function renderDayView(){
  const {cursor,byDate}=calState;
  const ds=fmtDate(cursor);
  const ents=byDate[ds]||[];
  let html=ents.map(e=>{
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
  // Background windows (run daily)
  const bg=calState.bg||{},m=bg.music||{},e=bg.effect||{};
  const bgRow=(on,c,lbl,name,start,end,note)=>`<div class="dv-entry">
    <div style="width:4px;height:36px;border-radius:2px;background:${c};flex-shrink:0"></div>
    <span style="font-family:var(--mono);font-size:14px;color:${c};min-width:110px">${escH(start||'00:00')}–${escH(end||'00:00')}</span>
    <span style="flex:1;font-size:14px"><span style="font-weight:600">${lbl}</span> · ${escH(name)}${note?` <span style="color:var(--mut);font-size:12px">${note}</span>`:''}</span>
    <button class="sm-btn ghost sm" onclick="smTab('background')">Edit</button>
  </div>`;
  if(m.enabled&&m.playlist) html+=bgRow(1,'#7c3aed','BG Music',m.playlist,m.start,m.end,'daily');
  if(e.enabled&&e.effect)   html+=bgRow(1,'#0e7490','BG Effect',e.effect,e.start,e.end,'daily · through blackouts');
  if(!html)return `<div style="text-align:center;padding:48px 0;color:var(--sub)"><div style="font-size:32px">📅</div><div style="font-size:15px;font-weight:500;margin-top:10px">Nothing scheduled</div></div>`;
  return html;
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
    <span style="display:flex;align-items:center;gap:6px"><span style="width:11px;height:11px;border-radius:3px;background:#7c3aed"></span>BG music</span>
    <span style="display:flex;align-items:center;gap:6px"><span style="width:11px;height:11px;border-radius:3px;background:#0e7490"></span>BG effect</span>
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
  const sysAudio=cfg._sysAudio||[];
  // Grouped audio picker: plugin announcement folders by name, plus audio
  // found elsewhere on the box (FPP music/upload) as absolute paths — the
  // scheduler accepts both forms.
  function fileOpts(sel){
    let out='<option value="">— select audio —</option>';
    const known=[];
    const grp=(label,items)=>items.length?`<optgroup label="${label}">${items.join('')}</optgroup>`:'';
    out+=grp('Announcements',(files.main||[]).map(f=>{known.push(f);
      return `<option value="${escH(f)}"${sel===f?' selected':''}>${escH(f)}</option>`;}));
    out+=grp('Announcements / daytime',(files.daytime||[]).map(f=>{const v='daytime/'+f;known.push(v);
      return `<option value="${escH(v)}"${sel===v?' selected':''}>${escH(f)}</option>`;}));
    out+=grp('FPP media',sysAudio.map(o=>{known.push(o.path);
      return `<option value="${escH(o.path)}"${sel===o.path?' selected':''}>${escH(o.label)}</option>`;}));
    if(sel&&!known.includes(sel))
      out+=`<option value="${escH(sel)}" selected>${escH(sel)} (missing)</option>`;
    return out;
  }
  const preRows=preShow.map((p,i)=>`
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
      <input type="number" class="sm-input" style="width:74px;flex:none" value="${p.mins_before||5}" min="1" max="120" id="pre-off-${i}">
      <span style="font-size:12px;color:var(--mut);flex:none">min</span>
      <select class="sm-select" id="pre-file-${i}">${fileOpts(p.file||'')}</select>
      <button class="sm-btn ghost sm" style="flex:none;font-size:15px;padding:3px 9px" onclick="annRemovePre(${i})">×</button>
    </div>`).join('');
  const fileRows=[...(files.main||[]).map(f=>({f,folder:'main',path:f})),...(files.daytime||[]).map(f=>({f,folder:'daytime',path:'daytime/'+f}))]
    .map(x=>`<div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
      <span style="font-size:13px;font-family:var(--mono);overflow:hidden;text-overflow:ellipsis">${escH(x.f)}</span>
      <span style="font-size:11px;color:var(--mut);font-family:var(--mono);background:var(--raise);padding:2px 7px;border-radius:5px;flex:none">${x.folder}</span>
      <button class="sm-btn danger sm" style="margin-left:auto;flex:none" onclick="annDeleteFile('${escH(escJ(x.path))}')">Delete</button>
    </div>`).join('');
  const daytimeBody=daytime.enabled?`
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--sub);margin-bottom:12px"><input type="checkbox" id="ann-dt-en" checked>Enabled</label>
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
  const fadeAnchor=['<option value="">Fade time before show</option>',
    ...preShow.filter(p=>p.file).map(p=>`<option value="${p.mins_before}"${String(cfg.fade_anchor_mins??'')===String(p.mins_before)?' selected':''}>${escH(String(p.mins_before))} min · ${escH(p.file)}</option>`)].join('');
  el.innerHTML=`<div class="sm-grid3">
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
        <label class="sm-lbl">Fade time (s)<input type="number" id="ann-fadesecs" class="sm-input" value="${cfg.pre_show_fade_secs??30}" min="0" max="600"></label>
        <label class="sm-lbl">Fade start<select id="ann-fadeanchor" class="sm-select">${fadeAnchor}</select></label>
        <label class="sm-lbl">Post-show fade back (s)<input type="number" id="ann-postfade" class="sm-input" value="${cfg.post_show_fade_secs??0}" min="0" max="600"></label>
      </div>
      <div class="sm-hint" style="margin-top:8px">Brightness fades to the pre-show level over the fade time, starting either the fade time before the show or when the selected pre-show audio begins. At show start the background effect is cleared, then brightness snaps to normal. When the show ends, brightness snaps to 0 and fades back to normal over the post-show fade (0 = snap straight back).</div>
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
        ${_ico('upload')} Upload audio (MP3/WAV/OGG)
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
    pre_show_fade_secs:Math.round(numOr(document.getElementById('ann-fadesecs').value,30)),
    post_show_fade_secs:Math.round(numOr(document.getElementById('ann-postfade').value,0)),
    fade_anchor_mins:document.getElementById('ann-fadeanchor').value,
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

/* ── BACKGROUND TAB ── */
let bgCfg={};
async function loadBackground(){
  const r=await fetch(AJAX+'&action=get_background');
  bgCfg=await r.json();
  renderBackground();
}
function effOptions(effects,sequences,selType,selName){
  const opt=(kind,name)=>{
    const v=kind+'|'+name;
    const on=(kind===selType&&name===selName);
    return `<option value="${escH(v)}"${on?' selected':''}>${escH(name)}</option>`;
  };
  let out='<option value="">— none —</option>';
  if((effects||[]).length)   out+=`<optgroup label="Effects (.eseq)">${effects.map(e=>opt('eseq',e)).join('')}</optgroup>`;
  if((sequences||[]).length) out+=`<optgroup label="Sequences (.fseq)">${sequences.map(s=>opt('fseq',s)).join('')}</optgroup>`;
  return out;
}
function renderBackground(){
  const el=document.getElementById('sm-background');
  const m=bgCfg.music||{},e=bgCfg.effect||{};
  const effects=bgCfg._effects||[],sequences=bgCfg._sequences||[];
  const hasFx=effects.length||sequences.length;
  el.innerHTML=`
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;max-width:760px">
    <div class="sm-card">
      <label style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:15px;margin-bottom:4px"><input type="checkbox" id="bg-m-en" ${m.enabled?'checked':''}>Background Music</label>
      <p style="font-size:12px;color:var(--sub);margin:0 0 12px">Loops a playlist during its window when no show is running.</p>
      <label class="sm-lbl" style="margin-bottom:10px">Playlist<select id="bg-m-pl" class="sm-select">${plOptions(m.playlist||'')}</select></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label class="sm-lbl">Window start<input type="time" id="bg-m-start" class="sm-input" value="${escH(m.start||'16:00')}"></label>
        <label class="sm-lbl">Window end<input type="time" id="bg-m-end" class="sm-input" value="${escH(m.end||'23:00')}"></label>
      </div>
      <label class="sm-lbl" style="margin-top:12px">Volume level (0–1)<input type="number" id="bg-m-lvl" class="sm-input" min="0" max="1" step="0.01" value="${escH(m.level!=null&&m.level!==''?String(m.level):'')}" placeholder="idle level"></label>
      <div class="sm-hint" style="margin-top:8px">Fader level while background music plays (blank = leave at the idle level). Same start &amp; end = all day.</div>
    </div>
    <div class="sm-card">
      <label style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:15px;margin-bottom:4px"><input type="checkbox" id="bg-e-en" ${e.enabled?'checked':''}>Background Effect</label>
      <p style="font-size:12px;color:var(--sub);margin:0 0 12px">Loops a lighting overlay during its window, layered on top. Suppressed while a show runs; keeps running through blackouts.</p>
      <label class="sm-lbl" style="margin-bottom:10px">Effect or sequence${hasFx?'':' <span style="color:var(--mut)">(none found in FPP)</span>'}<select id="bg-e-fx" class="sm-select">${effOptions(effects,sequences,e.type||'eseq',e.effect||'')}</select></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label class="sm-lbl">Window start<input type="time" id="bg-e-start" class="sm-input" value="${escH(e.start||'17:00')}"></label>
        <label class="sm-lbl">Window end<input type="time" id="bg-e-end" class="sm-input" value="${escH(e.end||'22:00')}"></label>
      </div>
      <div class="sm-hint" style="margin-top:8px">.eseq effects or .fseq sequences from FPP.</div>
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
      <button class="sm-btn solid" onclick="saveBackground()">Save Settings</button>
    </div>
  </div>`;
}
async function saveBackground(){
  const body={
    music:(()=>{
      const lv=document.getElementById('bg-m-lvl').value.trim();
      return {
        enabled:document.getElementById('bg-m-en').checked,
        playlist:document.getElementById('bg-m-pl').value,
        start:document.getElementById('bg-m-start').value||'00:00',
        end:document.getElementById('bg-m-end').value||'00:00',
        level:lv===''?null:numOr(lv,null),
      };
    })(),
    effect:(()=>{
      const v=document.getElementById('bg-e-fx').value;
      const i=v.indexOf('|');
      const type=i>=0?v.slice(0,i):'eseq';
      const name=i>=0?v.slice(i+1):'';
      return {
        enabled:document.getElementById('bg-e-en').checked,
        effect:name, type:type,
        start:document.getElementById('bg-e-start').value||'00:00',
        end:document.getElementById('bg-e-end').value||'00:00',
      };
    })(),
  };
  const r=await fetch(AJAX+'&action=save_background',{method:'POST',body:JSON.stringify(body)});
  const j=await r.json();
  if(j.ok){Object.assign(bgCfg,body);toast('Background settings saved','ok');}
  else toast('Save failed','err');
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

/* ── SYSTEM TAB (backup / restore / diagnostics) ── */
function loadSystem(){
  const el=document.getElementById('sm-system');
  el.innerHTML=`
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">
    <div class="sm-card">
      <div class="sm-ct" style="margin-bottom:6px">Backup &amp; Restore</div>
      <p style="font-size:12px;color:var(--sub);margin:0 0 14px">Download every Show Manager setting as one file, or restore from a previous backup. Restoring overwrites current settings and restarts the daemons.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="sm-btn solid" onclick="exportConfig()">${_ico('download')}Download backup</button>
        <button class="sm-btn" onclick="document.getElementById('sm-restore-file').click()">${_ico('upload')}Restore from file…</button>
        <input type="file" id="sm-restore-file" accept="application/json,.json" style="display:none" onchange="importConfig(this)">
      </div>
      <div class="sm-hint" style="margin-top:10px">Backup includes schedule, background, announcements, hardware and overrides.</div>
    </div>
    <div class="sm-card">
      <div class="sm-ct" style="margin-bottom:6px">Dropbox Backup</div>
      <div id="sm-dropbox"><div class="sm-skel" style="height:120px"></div></div>
    </div>
    <div class="sm-card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
        <div class="sm-ct" style="margin-bottom:0">Diagnostics</div>
        <div style="display:flex;gap:6px">
          <button class="sm-btn ghost sm" onclick="brightnessFlash()">Flash lights</button>
          <button class="sm-btn ghost sm" onclick="runDiag()">${_ico('refresh')}Re-check</button>
        </div>
      </div>
      <p style="font-size:12px;color:var(--sub);margin:0 0 12px">A quick pre-show health check of the rig.</p>
      <div id="sm-diag"><div class="sm-skel" style="height:180px"></div></div>
    </div>
  </div>`;
  runDiag();
  loadDropbox();
}
let dbxCfg={};
async function loadDropbox(){
  dbxCfg=await fetch(AJAX+'&action=get_dropbox').then(r=>r.json()).catch(()=>({}));
  renderDropbox();
}
function renderDropbox(){
  const box=document.getElementById('sm-dropbox');if(!box)return;
  const c=dbxCfg||{};
  const setup=`
    <p style="font-size:12px;color:var(--sub);margin:0 0 12px">Upload backups to your Dropbox. Create a Dropbox app (scoped access, <code>files.content.write</code>) at dropbox.com/developers, then paste its App key &amp; secret below.</p>
    <label class="sm-lbl">App key<input type="text" id="dbx-key" class="sm-input" value="${escH(c.app_key||'')}" placeholder="abcd1234…"></label>
    <label class="sm-lbl" style="margin-top:8px">App secret<input type="password" id="dbx-secret" class="sm-input" placeholder="${c.has_secret?'•••••••• (saved — leave blank to keep)':'app secret'}"></label>
    <label class="sm-lbl" style="margin-top:8px">Folder<input type="text" id="dbx-folder" class="sm-input" value="${escH(c.folder||'/ShowManager')}" placeholder="/ShowManager"></label>
    <button class="sm-btn solid" style="margin-top:12px" onclick="saveDropbox()">Save</button>`;
  let out=setup;
  if(c.app_key&&c.has_secret&&!c.connected){
    out+=`<div style="border-top:1px solid var(--border);margin-top:14px;padding-top:12px">
      <div style="font-size:12px;color:var(--sub);margin-bottom:8px"><b>1.</b> <a href="${escH(c.auth_url||'#')}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;vertical-align:bottom">Authorize in Dropbox ${_ico('extlink')}</a> — approve, then copy the code Dropbox shows you.</div>
      <div style="font-size:12px;color:var(--sub);margin-bottom:8px"><b>2.</b> Paste the code:</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="dbx-code" class="sm-input" style="flex:1;min-width:160px" placeholder="paste code">
        <button class="sm-btn solid" onclick="connectDropbox()">Connect</button>
      </div></div>`;
  }
  if(c.connected){
    const last=c.last_backup?new Date(c.last_backup).toLocaleString():'never';
    out+=`<div style="border-top:1px solid var(--border);margin-top:14px;padding-top:12px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><span class="sm-pill ok">Connected</span><span style="font-size:12px;color:var(--sub)">Last backup: ${escH(last)}</span></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <button class="sm-btn solid" onclick="backupDropbox()">${_ico('cloudup')}Back up now</button>
        <button class="sm-btn ghost sm" onclick="testDropbox()">Test</button>
        <button class="sm-btn danger sm" onclick="disconnectDropbox()">Disconnect</button>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px"><input type="checkbox" id="dbx-auto" ${c.auto?'checked':''} onchange="saveDropboxAuto(this.checked)">Nightly auto-backup (after 4&nbsp;AM)</label>
    </div>`;
  }
  box.innerHTML=out;
}
async function saveDropbox(){
  const body={app_key:document.getElementById('dbx-key').value,app_secret:document.getElementById('dbx-secret').value,folder:document.getElementById('dbx-folder').value,auto:!!dbxCfg.auto};
  const j=await fetch(AJAX+'&action=save_dropbox',{method:'POST',body:JSON.stringify(body)}).then(r=>r.json()).catch(()=>({}));
  if(j.ok){toast('Dropbox settings saved','ok');loadDropbox();}else toast('Save failed','err');
}
async function saveDropboxAuto(on){
  const body={app_key:dbxCfg.app_key,folder:dbxCfg.folder,auto:on};
  await fetch(AJAX+'&action=save_dropbox',{method:'POST',body:JSON.stringify(body)});
  dbxCfg.auto=on;toast(on?'Nightly backup on':'Nightly backup off','ok');
}
async function connectDropbox(){
  const code=document.getElementById('dbx-code').value.trim();
  if(!code)return toast('Paste the code first','err');
  const j=await fetch(AJAX+'&action=dropbox_connect',{method:'POST',body:JSON.stringify({code})}).then(r=>r.json()).catch(()=>({error:'network'}));
  if(j.ok){toast('Connected to Dropbox','ok');loadDropbox();}else toast(j.error||'Connect failed','err');
}
async function testDropbox(){
  toast('Testing…','amber');
  const j=await fetch(AJAX+'&action=dropbox_test').then(r=>r.json()).catch(()=>({error:'network'}));
  toast(j.ok?'Dropbox connection OK':(j.error||'Test failed'),j.ok?'ok':'err');
}
async function backupDropbox(){
  toast('Uploading backup…','amber');
  const j=await fetch(AJAX+'&action=dropbox_backup').then(r=>r.json()).catch(()=>({error:'network'}));
  if(j.ok){toast('Backed up to '+j.path,'ok');loadDropbox();}else toast(j.error||'Backup failed','err');
}
function disconnectDropbox(){
  smConfirm('Disconnect Dropbox?','Removes the stored Dropbox connection from this Pi. Your uploaded backups are not deleted.','Disconnect',async()=>{
    await fetch(AJAX+'&action=dropbox_disconnect');toast('Dropbox disconnected','mut');loadDropbox();
  });
}
async function exportConfig(){
  try{
    const r=await fetch(AJAX+'&action=export_config');
    const txt=await r.text();
    const stamp=new Date().toISOString().slice(0,16).replace(/[:T]/g,'-');
    const a=document.createElement('a');
    a.href=URL.createObjectURL(new Blob([txt],{type:'application/json'}));
    a.download='showmanager-backup-'+stamp+'.json';
    document.body.appendChild(a);a.click();a.remove();
    setTimeout(()=>URL.revokeObjectURL(a.href),4000);
    toast('Backup downloaded','ok');
  }catch(e){toast('Backup failed','err');}
}
function importConfig(input){
  const file=input.files&&input.files[0];
  input.value='';
  if(!file)return;
  smConfirm('Restore settings?','This overwrites current schedule, background, announcements and hardware settings with the backup, then restarts the daemons.','Restore',async()=>{
    try{
      const text=await file.text();
      const r=await fetch(AJAX+'&action=import_config',{method:'POST',body:text});
      const j=await r.json();
      if(j.ok){toast('Restored '+j.restored.length+' file(s) — daemons restarting','ok');}
      else toast(j.error||'Restore failed','err');
    }catch(e){toast('Restore failed — invalid file','err');}
  });
}
function _diagRow(c){
  const st=c.status==='ok'?'ok':(c.status==='warn'?'idle':'bad');
  return `<div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
    <span style="font-size:14px">${escH(c.label)}</span>
    <span style="font-size:12px;color:var(--sub);margin-left:auto;text-align:right">${escH(c.detail)}</span>
    <span class="sm-pill ${st}" style="min-width:0">${c.status==='ok'?'OK':(c.status==='warn'?'Check':'Fail')}</span>
  </div>`;
}
async function runDiag(){
  const box=document.getElementById('sm-diag');
  if(box)box.innerHTML='<div class="sm-skel" style="height:180px"></div>';
  const r=await fetch(AJAX+'&action=diagnostics').then(r=>r.json()).catch(()=>null);
  if(!box)return;
  if(!r||!r.checks){box.innerHTML='<div style="color:var(--red);font-size:13px">Diagnostics failed to run</div>';return;}
  box.innerHTML=r.checks.map(_diagRow).join('');
}
async function brightnessFlash(){
  toast('Flashing lights…','amber');
  const r=await fetch(AJAX+'&action=brightness_flash').then(r=>r.json()).catch(()=>({}));
  toast(r.note||'Done',r.ok?'ok':'err');
}

/* ── INIT ── */
loadStatus();
_npTick();setInterval(_npTick,5000);
</script>
