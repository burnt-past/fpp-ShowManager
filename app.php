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
  --mint:#0d9488;--amber:#b45309;--red:#dc2626;--s1:#2563eb;--s2:#7c3aed;
  --mintBg:rgba(13,148,136,.12);--mintBrd:rgba(13,148,136,.35);--mintInk:#ffffff;
  --amberBg:rgba(180,83,9,.12);--amberBrd:rgba(180,83,9,.4);
  --redBg:rgba(220,38,38,.10);--redBrd:rgba(220,38,38,.35);
  --s1Bg:rgba(37,99,235,.10);--s1Brd:rgba(37,99,235,.3);
  --font:inherit;
  --mono:ui-monospace,'SF Mono','Cascadia Code',Menlo,Consolas,monospace;
  position:relative;min-height:400px;
}
#sm *{box-sizing:border-box}
#sm button{font-family:inherit;cursor:pointer}
@keyframes sm-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.5);opacity:.55}}
@keyframes sm-slide{from{transform:translateY(8px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes sm-shimmer{0%{background-position:-320px 0}100%{background-position:320px 0}}
.sm-skel{background:linear-gradient(90deg,var(--raise) 0px,var(--high) 160px,var(--raise) 320px);background-size:640px 100%;animation:sm-shimmer 1.2s linear infinite;border-radius:6px}
.sm-wrap{position:relative;max-width:1180px;margin:0 auto;padding:6px 4px 30px}
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
/* tabs — FPP/bootstrap style */
#sm-tabs{display:flex;align-items:flex-end;gap:2px;border-bottom:1px solid var(--border);margin-bottom:18px}
.sm-tab{appearance:none;border:1px solid transparent;border-bottom:none;background:transparent;color:inherit;opacity:.7;font-size:14px;padding:9px 16px;border-radius:5px 5px 0 0;margin-bottom:-1px}
.sm-tab.active{border-color:var(--border);background:var(--bg);opacity:1;font-weight:600;border-bottom:1px solid transparent}
/* cards */
.sm-card{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:14px 16px}
.sm-ct{font-weight:700;font-size:15px;margin-bottom:10px}
/* buttons */
.sm-btn{appearance:none;background:var(--raise);border:1px solid var(--border);color:inherit;font-weight:600;font-size:13px;padding:7px 14px;border-radius:5px;white-space:nowrap}
.sm-btn:hover{background:var(--high)}
.sm-btn.ghost{background:transparent;color:var(--sub)}
.sm-btn.solid{background:var(--mint);border-color:var(--mint);color:var(--mintInk);font-weight:600}
.sm-btn.danger{background:transparent;border-color:var(--redBrd);color:var(--red)}
.sm-btn.sm{padding:4px 10px;font-size:12px}
/* inputs — light touch, let FPP/browser defaults do most of the work */
.sm-input,.sm-select{width:100%;padding:7px 10px;font-size:13px;border:1px solid var(--brdHi);border-radius:4px;background:transparent;color:inherit}
.sm-input:focus,.sm-select:focus{border-color:var(--mint);outline:none}
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
.sm-dot{width:9px;height:9px;border-radius:50%;flex:none}
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
      <a href="plugin.php?plugin=<?= basename(__DIR__) ?>&page=kiosk.php&nopage=1" target="_blank" style="margin-left:auto;text-decoration:none"><button class="sm-btn">⛶ Kiosk</button></a>
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
    </div>
    <div id="sm-status" class="sm-pane"></div>
    <div id="sm-schedule" class="sm-pane" style="display:none"></div>
    <div id="sm-background" class="sm-pane" style="display:none"></div>
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

/* ── TABS ── */
function smTab(name){
  document.querySelectorAll('.sm-tab').forEach((b,i)=>b.classList.toggle('active',['status','schedule','background','announcements','hardware'][i]===name));
  document.querySelectorAll('.sm-pane').forEach(p=>p.style.display='none');
  document.getElementById('sm-'+name).style.display='';
  if(name!=='status'&&statusTimer){clearInterval(statusTimer);statusTimer=null;}
  ({status:loadStatus,schedule:initSchedule,background:loadBackground,announcements:loadAnnouncements,hardware:loadHardware})[name]?.();
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
  // state: 'ok' green · 'idle' amber · 'bad' red
  const rows=[
    ['FPP Daemon',fpp.fppd==='running'?'ok':'bad',fpp.fppd==='running'?'running':'stopped'],
    ['XR18 Mixer',xr.xr18_fader!=null?'ok':'bad',xr.xr18_fader!=null?('fader '+xr.xr18_fader.toFixed(2)):'n/a'],
    ['Scheduler',running?'ok':'bad',running?'active':'stopped'],
  ];
  const bg=xr.background||null;
  if(bg&&bg.music_enabled) rows.push(['BG Music',bg.music?'ok':'idle',bg.music?('▶ '+bg.music):'idle']);
  if(bg&&bg.effect_enabled) rows.push(['BG Effect',bg.effect?'ok':'idle',bg.effect?('▶ '+bg.effect):'idle']);
  const col={ok:'var(--mint)',idle:'var(--amber)',bad:'var(--red)'};
  return rows.map(([l,st,txt])=>`
  <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
    <span class="sm-dot" style="background:${col[st]};box-shadow:0 0 8px ${col[st]}"></span>
    <span style="font-size:14px">${l}</span>
    <span style="margin-left:auto;font-family:var(--mono);font-size:12.5px;color:var(--sub);overflow:hidden;text-overflow:ellipsis;max-width:150px;white-space:nowrap">${escH(txt)}</span>
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
  el.innerHTML=`<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;align-items:start">
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
function effOptions(list,sel){
  return ['<option value="">— none —</option>',...(list||[]).map(e=>`<option value="${escH(e)}"${e===sel?' selected':''}>${escH(e)}</option>`)].join('');
}
function renderBackground(){
  const el=document.getElementById('sm-background');
  const m=bgCfg.music||{},e=bgCfg.effect||{};
  const effects=bgCfg._effects||[];
  el.innerHTML=`
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;max-width:760px">
    <div class="sm-card">
      <label style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:15px;margin-bottom:4px"><input type="checkbox" id="bg-m-en" ${m.enabled?'checked':''} style="width:auto">Background Music</label>
      <p style="font-size:12px;color:var(--sub);margin:0 0 12px">Loops a playlist during its window when no show is running.</p>
      <label class="sm-lbl" style="margin-bottom:10px">Playlist<select id="bg-m-pl" class="sm-select">${plOptions(m.playlist||'')}</select></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label class="sm-lbl">Window start<input type="time" id="bg-m-start" class="sm-input" value="${escH(m.start||'16:00')}"></label>
        <label class="sm-lbl">Window end<input type="time" id="bg-m-end" class="sm-input" value="${escH(m.end||'23:00')}"></label>
      </div>
      <div class="sm-hint" style="margin-top:8px">Same start &amp; end = all day.</div>
    </div>
    <div class="sm-card">
      <label style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:15px;margin-bottom:4px"><input type="checkbox" id="bg-e-en" ${e.enabled?'checked':''} style="width:auto">Background Effect</label>
      <p style="font-size:12px;color:var(--sub);margin:0 0 12px">Loops an FPP overlay effect (lighting) during its window. Suppressed while a show runs.</p>
      <label class="sm-lbl" style="margin-bottom:10px">Effect${effects.length?'':' <span style="color:var(--mut)">(none found in FPP)</span>'}<select id="bg-e-fx" class="sm-select">${effOptions(effects,e.effect||'')}</select></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label class="sm-lbl">Window start<input type="time" id="bg-e-start" class="sm-input" value="${escH(e.start||'17:00')}"></label>
        <label class="sm-lbl">Window end<input type="time" id="bg-e-end" class="sm-input" value="${escH(e.end||'22:00')}"></label>
      </div>
      <div class="sm-hint" style="margin-top:8px">Effects are .eseq files in FPP's Effects library.</div>
    </div>
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
      <button class="sm-btn solid" onclick="saveBackground()">Save Settings</button>
    </div>
  </div>`;
}
async function saveBackground(){
  const body={
    music:{
      enabled:document.getElementById('bg-m-en').checked,
      playlist:document.getElementById('bg-m-pl').value,
      start:document.getElementById('bg-m-start').value||'00:00',
      end:document.getElementById('bg-m-end').value||'00:00',
    },
    effect:{
      enabled:document.getElementById('bg-e-en').checked,
      effect:document.getElementById('bg-e-fx').value,
      start:document.getElementById('bg-e-start').value||'00:00',
      end:document.getElementById('bg-e-end').value||'00:00',
    },
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

/* ── INIT ── */
loadStatus();
_npTick();setInterval(_npTick,5000);
</script>
