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
:root{--bg:#0f1117;--base:#13161f;--card:#1a1d27;--raise:#1f2330;--high:#252839;--border:rgba(255,255,255,0.07);--brdHi:rgba(255,255,255,0.13);--text:#e2e6f3;--sub:#7c85a2;--mut:#3e4558;--mint:#34d399;--mintD:rgba(52,211,153,0.10);--mintG:rgba(52,211,153,0.20);--amber:#f59e0b;--amberD:rgba(245,158,11,0.12);--red:#f43f5e;--redD:rgba(244,63,94,0.12);--s1:#3b82f6;--s2:#a855f7}
.sm-light{--bg:#f0f2f7;--base:#e8ebf2;--card:#ffffff;--raise:#f5f6fa;--high:#eaecf4;--border:rgba(0,0,0,0.08);--brdHi:rgba(0,0,0,0.15);--text:#1a1d2e;--sub:#4a5270;--mut:#9098b8;--mint:#059669;--mintD:rgba(5,150,105,0.10);--mintG:rgba(5,150,105,0.20);--amber:#d97706;--amberD:rgba(217,119,6,0.10);--red:#dc2626;--redD:rgba(220,38,38,0.10);--s1:#2563eb;--s2:#7c3aed}
#sm{font-family:system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:500px}
#sm *{box-sizing:border-box}#sm button{font-family:inherit;cursor:pointer}
#sm-tabs{background:var(--base);border-bottom:1px solid var(--border);display:flex;align-items:flex-end;padding:0 16px}
.sm-tab{padding:0 16px;height:40px;background:none;border:none;border-bottom:2px solid transparent;color:var(--sub);font-size:13px;cursor:pointer}
.sm-tab.active{border-bottom-color:var(--mint);color:var(--text);font-weight:600}
#sm-theme-btn{margin-left:auto;margin-bottom:6px;background:none;border:none;font-size:18px;cursor:pointer;color:var(--sub)}
.sm-card{background:var(--card);border-radius:12px;border:1px solid var(--border);box-shadow:0 2px 8px rgba(0,0,0,.12);padding:18px 20px;margin-bottom:14px}
.sm-card.glow{border-color:var(--mintG);box-shadow:0 0 0 1px var(--mintG),0 8px 24px rgba(0,0,0,.2)}
.sm-btn{padding:5px 14px;font-size:12px;font-weight:500;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--sub);cursor:pointer;white-space:nowrap}
.sm-btn.solid{background:var(--mint);border-color:var(--mint);color:#fff;font-weight:600}
.sm-btn.danger{background:var(--redD);border-color:rgba(244,63,94,.3);color:var(--red)}
.sm-btn.sm{padding:3px 10px;font-size:10px}
.sm-input,.sm-select{width:100%;padding:7px 10px;font-size:12px;font-family:monospace;background:var(--raise);border:1px solid var(--border);color:var(--text);border-radius:6px;outline:none}
.sm-input:focus,.sm-select:focus{border-color:var(--mint)}
.sm-seg{display:flex;background:var(--bg);border-radius:8px;padding:2px;gap:2px;border:1px solid var(--border)}
.sm-seg button{padding:4px 14px;border-radius:6px;border:none;background:transparent;color:var(--sub);font-size:12px;cursor:pointer}
.sm-seg button.active{background:var(--card);color:var(--text);font-weight:600}
.sm-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
.sm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;display:flex;align-items:center;justify-content:center}
.sm-modal{background:var(--card);border-radius:14px;border:1px solid var(--brdHi);width:460px;max-width:95vw;max-height:85vh;overflow:auto;box-shadow:0 24px 60px rgba(0,0,0,.5)}
.sm-modal-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-weight:600;font-size:15px}
.sm-modal-body{padding:20px}
.sm-fr{margin-bottom:14px}.sm-fr label{display:block;font-size:11px;font-weight:600;color:var(--sub);margin-bottom:5px;letter-spacing:.04em;text-transform:uppercase}
.sm-fr .hint{font-size:10px;color:var(--mut);margin-top:4px}
.cal-grid{width:100%;border-collapse:collapse}
.cal-grid th{padding:8px 4px;font-size:11px;font-weight:600;color:var(--mut);text-align:center;border-bottom:1px solid var(--border)}
.cal-grid td{border:1px solid var(--border);padding:8px;vertical-align:top;cursor:pointer;min-height:90px;background:var(--bg)}
.cal-grid td:hover{background:var(--card)}.cal-grid td.today{border-top:2px solid var(--mint);background:var(--mintD)}
.cal-grid td.blackout{background:var(--redD)}.cal-grid td.empty{cursor:default;background:var(--bg)}
.cal-num{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-family:monospace;color:var(--sub)}
.cal-num.today{background:var(--mint);color:#0f1117}
.cal-chip{font-size:10px;font-weight:500;border-left:2px solid var(--mint);background:rgba(52,211,153,.18);padding:1px 5px;border-radius:0 3px 3px 0;margin-bottom:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.cal-chip.rule{border-left-color:var(--s1);background:rgba(59,130,246,.2)}
.cal-chip.blk{border-left-color:var(--red);background:var(--redD);color:var(--red)}
.dv-entry{display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:10px;background:var(--card);border:1px solid var(--border);margin-bottom:8px}
.rule-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;background:var(--card);border:1px solid var(--border);margin-bottom:8px}
.dow-btn{padding:4px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--sub);font-size:11px;font-weight:600;cursor:pointer;margin:2px}
.dow-btn.on{background:var(--mintD);border-color:rgba(52,211,153,.4);color:var(--mint)}
.sm-hr{height:1px;background:var(--border);margin:12px 0}
@keyframes sm-blink{0%,49%{opacity:1}50%,100%{opacity:0}}
.blink{animation:sm-blink .9s step-end infinite}
</style>
<div id="sm">
  <div id="sm-nowplaying" style="padding:6px 16px;background:var(--raise);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;font-size:12px;min-height:30px">
    <span id="sm-np-dot" style="width:6px;height:6px;border-radius:50%;background:var(--mut);flex-shrink:0"></span>
    <span id="sm-np-text" style="color:var(--sub)">Connecting…</span>
  </div>
  <div id="sm-tabs">
    <button class="sm-tab active" onclick="smTab('status')">Status</button>
    <button class="sm-tab" onclick="smTab('schedule')">Schedule</button>
    <button class="sm-tab" onclick="smTab('announcements')">Announcements</button>
    <button class="sm-tab" onclick="smTab('hardware')">Hardware</button>
    <button id="sm-theme-btn" onclick="smToggleTheme()">☀️</button>
  </div>
  <div id="sm-status"  class="sm-pane" style="padding:20px"></div>
  <div id="sm-schedule" class="sm-pane" style="display:none"></div>
  <div id="sm-announcements" class="sm-pane" style="padding:20px;max-width:760px;display:none"></div>
  <div id="sm-hardware" class="sm-pane" style="padding:20px;max-width:660px;display:none"></div>
  <div id="sm-modal-layer"></div>
</div>
<script>
const AJAX='<?= $AJAX ?>';
const PLAYLISTS=<?= $plJson ?>;
const DAYS=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
let smDark=true;try{smDark=localStorage.getItem('sm-theme')!=='light';}catch(e){}
function smToggleTheme(){smDark=!smDark;smApplyTheme();try{localStorage.setItem('sm-theme',smDark?'dark':'light');}catch(e){}}
function smApplyTheme(){document.getElementById('sm').classList.toggle('sm-light',!smDark);document.getElementById('sm-theme-btn').textContent=smDark?'☀️':'🌙';}
smApplyTheme();
function smTab(name){
  document.querySelectorAll('.sm-tab').forEach((b,i)=>b.classList.toggle('active',['status','schedule','announcements','hardware'][i]===name));
  document.querySelectorAll('.sm-pane').forEach(p=>p.style.display='none');
  document.getElementById('sm-'+name).style.display='';
  if(name!=='status'&&statusTimer){clearInterval(statusTimer);statusTimer=null;}
  ({status:loadStatus,schedule:initSchedule,announcements:loadAnnouncements,hardware:loadHardware})[name]?.();
}
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
async function _npTick(){
  const r=await fetch('/api/fppd/status').then(r=>r.json()).catch(()=>({}));
  const playing=r.status===1||r.status==='playing';
  const curPl=r.current_playlist?.playlist||r.current_playlist?.name||r.current_song||null;
  const dot=document.getElementById('sm-np-dot');
  const txt=document.getElementById('sm-np-text');
  if(!dot)return;
  if(playing){
    dot.style.background='var(--amber)';dot.classList.add('blink');
    txt.style.color='var(--amber)';txt.textContent='On Air — '+curPl;
  } else {
    dot.style.background='var(--mut)';dot.classList.remove('blink');
    txt.style.color='var(--sub)';txt.textContent='Idle';
  }
}
_npTick();setInterval(_npTick,5000);
async function clearLog(){
  await fetch(AJAX+'&action=clear_log');
  const lc=document.getElementById('sm-log-content');
  if(lc)lc.textContent='';
}
function plOptions(sel){return ['<option value="">— none —</option>',...PLAYLISTS.map(p=>`<option value="${escH(p)}"${p===sel?' selected':''}>${escH(p)}</option>`)].join('');}

/* ── STATUS ── */
let statusTimer=null;
let triggerLog=[];
let logPaused=false;

function loadStatus(){
  clearInterval(statusTimer);
  renderStatus();
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
  return {fpp,xr,log,playing,curName,shows:t.shows,fullBlackout:t.fullBlackout,nextIdx,upcoming};
}

/* helpers that build HTML for each updatable region */
function _heroHtml(fpp,xr){
  const playing=fpp.status===1||fpp.status==='playing';
  const curPl=fpp.current_playlist?.playlist||fpp.current_playlist?.name||fpp.current_song||'Idle';
  return `<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
    <span class="sm-badge" style="background:${playing?'rgba(245,158,11,.12)':'rgba(124,133,162,.12)'};border:1px solid ${playing?'rgba(245,158,11,.3)':'rgba(124,133,162,.3)'};color:${playing?'var(--amber)':'var(--sub)'}">
      ${playing?'<span class="sm-dot blink" style="width:6px;height:6px;background:var(--amber);border-radius:50%"></span>On Air':'Idle'}
    </span>
  </div>
  <div style="font-size:32px;font-weight:700;letter-spacing:-.03em;color:var(--text);line-height:1">${escH(playing?curPl:'Idle')}</div>
  <div style="margin-top:6px;font-size:13px;color:var(--sub)">
    Uptime <span style="font-family:monospace;color:var(--text)">${escH(fpp.uptime||'—')}</span>
    &nbsp;&nbsp;Volume <span style="font-family:monospace;color:var(--text)">${fpp.volume!=null?fpp.volume+'%':'—'}</span>
    ${xr.xr18_fader!=null?'&nbsp;&nbsp;XR18 <span style="font-family:monospace;color:var(--mint)">'+xr.xr18_fader.toFixed(2)+'</span>':''}
  </div>`;
}
function _statsHtml(fpp,xr,todayShows,upcoming){
  return [['FPP Version',fpp.version||'—'],['Instance',fpp.HostName||fpp.hostname||'—'],['Shows Today',todayShows.length],['Upcoming',upcoming.length],['Volume',fpp.volume!=null?fpp.volume+'%':'—'],['XR18',xr.xr18_fader!=null?xr.xr18_fader.toFixed(2):'—']].map(([l,v])=>`<div class="sm-card" style="flex:1;min-width:100px;margin-bottom:0"><div style="font-size:11px;color:var(--sub);font-weight:500">${l}</div><div style="margin-top:6px;font-size:18px;font-family:monospace;font-weight:600;color:var(--mint);word-break:break-all;line-height:1.2">${escH(String(v))}</div></div>`).join('');
}
function _schedHtml(d){
  if(d.fullBlackout) return '<div style="font-size:13px;color:var(--red);font-weight:600">⛔ Blackout day — no shows will run</div>';
  if(!d.shows.length) return '<div style="font-size:13px;color:var(--mut)">No shows today</div>';
  return d.shows.map((s,i)=>{
    const isNow=d.playing&&!!s.playlist&&s.playlist===d.curName;
    const isNext=i===d.nextIdx&&!isNow;
    const isPast=(d.nextIdx<0||i<d.nextIdx)&&!isNow;
    const bg=isNext?'var(--mintD)':isNow?'rgba(245,158,11,.08)':'transparent';
    const tc=isNext?'var(--mint)':isNow?'var(--amber)':'var(--sub)';
    return `<div style="display:flex;align-items:center;gap:12px;padding:8px 10px;border-radius:8px;background:${bg};opacity:${isPast?0.4:1}">
      <span style="font-family:monospace;font-size:14px;color:${tc}">${escH(s.time)}</span>
      <span style="font-size:14px;font-weight:${isNext||isNow?600:400};color:${isNext||isNow?'var(--text)':'var(--sub)'};flex:1">${escH(qlabel(s))}</span>
      ${isNext?'<span class="sm-badge" style="background:var(--mintD);border:1px solid rgba(52,211,153,.3);color:var(--mint)">Next</span>':''}
      ${isNow?'<span class="sm-badge" style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:var(--amber)">Now</span>':''}
    </div>`;
  }).join('');
}
function _sysHtml(fpp,xr,running){
  return [['FPP Daemon',fpp.fppd==='running','Running','Stopped'],['XR18',xr.xr18_fader!=null,xr.xr18_fader!=null?xr.xr18_fader.toFixed(2):'n/a','n/a'],['Scheduler',running,'Running','Stopped']].map(([l,ok,yes,no])=>`<div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--high);border-radius:8px;margin-bottom:6px"><span class="sm-dot" style="width:7px;height:7px;background:${ok?'var(--mint)':'var(--red)'}"></span><div><div style="font-size:13px;font-weight:500;color:var(--text)">${l}</div><div style="font-size:11px;color:var(--mut)">${ok?yes:no}</div></div></div>`).join('');
}

async function renderStatus(){
  const el=document.getElementById('sm-status');
  const d=await _fetchStatus();
  const {fpp,xr,log}=d;
  el.innerHTML=`
<div class="sm-card glow" style="position:relative;overflow:hidden;margin-bottom:14px">
  <div id="sm-hero">${_heroHtml(fpp,xr)}</div>
</div>
<div id="sm-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px">${_statsHtml(fpp,xr,d.shows,d.upcoming)}</div>
<div class="sm-card" style="margin:14px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:12px;font-weight:600;color:var(--sub);text-transform:uppercase;letter-spacing:.08em">Manual Trigger</span>
  <select id="trig-pl" class="sm-select" style="flex:1;min-width:160px">${plOptions('')}</select>
  <button class="sm-btn solid" onclick="triggerPlaylist()">&#9654; Start</button>
  <button class="sm-btn danger" onclick="stopPlaylist()">&#9646;&#9646; Stop</button>
</div>
<div style="display:flex;gap:12px;flex-wrap:wrap">
  <div class="sm-card" style="flex:1;min-width:200px">
    <div style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);margin-bottom:10px">Today's Schedule</div>
    <div id="sm-sched">${_schedHtml(d)}</div>
  </div>
  <div class="sm-card" style="width:240px;flex-shrink:0">
    <div style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);margin-bottom:10px">System</div>
    <div id="sm-sys">${_sysHtml(fpp,xr,log.running)}</div>
    <button class="sm-btn sm" style="width:100%;margin-top:4px" onclick="restartScheduler()">Restart Scheduler</button>
  </div>
</div>
<div class="sm-card" style="margin-top:14px">
  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
    <div style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);flex:1">Scheduler Log</div>
    <button class="sm-btn sm" onclick="toggleLogPause()" id="log-pause-btn">${logPaused?'Resume':'Pause'}</button>
    <button class="sm-btn sm" onclick="navigator.clipboard.writeText(document.getElementById('sm-log-content').textContent)">Copy</button>
    <button class="sm-btn sm" onclick="clearLog()">Clear</button>
    <button class="sm-btn sm" onclick="refreshLog()">Refresh</button>
  </div>
  <div id="sm-log-content" style="font-family:monospace;font-size:11px;color:var(--sub);max-height:260px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;background:var(--high);border-radius:6px;padding:10px;line-height:1.5">${escH(log.lines.join('\n'))||'(empty)'}</div>
  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px;margin-bottom:4px">
    <div style="font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--mut);flex:1">Manual / Probe Output</div>
    <button class="sm-btn sm" onclick="navigator.clipboard.writeText(document.getElementById('sm-trigger-log').textContent)">Copy</button>
    <button class="sm-btn sm" onclick="triggerLog=[];const tl=document.getElementById('sm-trigger-log');if(tl)tl.textContent=''">Clear</button>
  </div>
  <div id="sm-trigger-log" style="font-family:monospace;font-size:11px;color:var(--mint);min-height:40px;max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;background:var(--high);border-radius:6px;padding:10px;line-height:1.5">${escH(triggerLog.join('\n'))}</div>
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
  if(!pl)return alert('Select a playlist.');
  _appendTriggerLog('[Trigger] '+pl+' — sending…');
  const r=await fetch(AJAX+'&action=trigger_playlist&playlist='+encodeURIComponent(pl)).then(r=>r.json()).catch(e=>({error:String(e)}));
  _appendTriggerLog('  URL:  '+r.url+'\n  HTTP: '+r.http+'\n  Body: '+(r.response||'(empty)'));
}
async function stopPlaylist(){
  _appendTriggerLog('[Stop] — sending…');
  const r=await fetch(AJAX+'&action=stop_playlist').then(r=>r.json()).catch(e=>({error:String(e)}));
  _appendTriggerLog('  HTTP: '+r.http+'  Body: '+(r.response||'(empty)'));
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
async function restartScheduler(){
  await fetch(AJAX+'&action=scheduler_restart');
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
  let html='<table class="cal-grid"><thead><tr>'+DAYS.map(d=>`<th>${d}</th>`).join('')+'</tr></thead><tbody><tr>';
  let col=0;
  for(let i=0;i<first;i++){html+='<td class="empty"></td>';col++;}
  for(let d=1;d<=days;d++){
    const ds=mkey(y,m+1)+'-'+String(d).padStart(2,'0');
    const ents=byDate[ds]||[];
    const fullBlk=ents.some(e=>e.type==='blackout'&&!e.start_time&&!e.end_time);
    const isTd=ds===today;
    html+=`<td class="${fullBlk?'blackout':isTd?'today':''}" onclick="openDayModal('${ds}')">`;
    html+=`<div class="cal-num${isTd?' today':''}">${d}</div>`;
    if(fullBlk)html+='<div style="font-size:10px;color:var(--red);font-weight:600">✖ Blackout</div>';
    else ents.slice(0,3).forEach(e=>html+=`<div class="cal-chip${e.type==='blackout'?' blk':e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(qlabel(e))}</div>`);
    if(!fullBlk&&ents.length>3)html+=`<div style="font-size:10px;color:var(--mut)">+${ents.length-3} more</div>`;
    html+='</td>';
    if(++col===7){html+='</tr><tr>';col=0;}
  }
  while(col%7!==0&&col>0){html+='<td class="empty"></td>';col++;}
  return html+'</tr></tbody></table>';
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
    hd+=`<th style="${isTd?'color:var(--mint)':''}">${DAYS[d.getDay()]}<br><span style="font-family:monospace;font-weight:400">${ds.slice(5)}</span></th>`;
    bd+=`<td class="${fullBlk?'blackout':isTd?'today':''}" onclick="openDayModal('${ds}')">`;
    if(fullBlk)bd+='<div style="font-size:10px;color:var(--red);font-weight:600">✖</div>';
    else ents.slice(0,4).forEach(e=>bd+=`<div class="cal-chip${e.type==='blackout'?' blk':e.rule_id?' rule':''}">${escH(e.time||'')} ${escH(qlabel(e))}</div>`);
    bd+='</td>';
  }
  return `<table class="cal-grid"><thead><tr>${hd}</tr></thead><tbody><tr>${bd}</tr></tbody></table>`;
}

/* ── DAY VIEW ── */
function renderDayView(){
  const {cursor,byDate,rules}=calState;
  const ds=fmtDate(cursor);
  const ents=byDate[ds]||[];
  if(!ents.length)return `<div style="text-align:center;padding:48px 0;color:var(--sub)"><div style="font-size:32px">📅</div><div style="font-size:15px;font-weight:500;margin-top:10px">No shows scheduled</div></div>`;
  return ents.map(e=>{
    const isRule=!!e.rule_id,isBlk=e.type==='blackout';
    const c=isBlk?'var(--red)':isRule?'var(--s1)':'var(--mint)';
    return `<div class="dv-entry">
      <div style="width:4px;height:36px;border-radius:2px;background:${c};flex-shrink:0"></div>
      <span style="font-family:monospace;font-size:14px;color:${c};min-width:52px">${escH(e.time||'')}</span>
      <span style="flex:1;font-size:14px;font-weight:600">${escH(qlabel(e))}</span>
      ${isRule
        ?`<button class="sm-btn sm" onclick="editRule('${escH(e.rule_id)}')">Edit Rule</button><button class="sm-btn sm danger" onclick="deleteRuleConfirm('${escH(e.rule_id)}')">Del Rule</button>`
        :`<button class="sm-btn sm danger" onclick="deleteEntry('${escH(e.id)}')">Remove</button>`}
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
    <span class="sm-dot" style="width:6px;height:6px;background:var(--mint);border-radius:50%"></span>
    <div style="flex:1">
      <div style="font-size:13px;font-weight:600">${escH(r.playlist||'—')}</div>
      <div style="font-size:11px;font-family:monospace;color:var(--sub)">${(r.days||[]).map(d=>DAYS[d]).join(', ')} · ${escH(r.window_start||'')}${r.window_end?'–'+r.window_end:''}${r.interval_mins?' every '+r.interval_mins+'m':''}</div>
      <div style="font-size:11px;font-family:monospace;color:var(--mut)">${escH(r.start_date)} → ${escH(r.end_date)}</div>
    </div>
    <button class="sm-btn sm" onclick="editRule('${r.id}')">Edit</button>
    <button class="sm-btn sm danger" onclick="deleteRuleConfirm('${r.id}')">Delete</button>
  </div>`).join(''):'<div style="font-size:13px;color:var(--mut)">No rules defined.</div>';

  document.getElementById('sm-schedule').innerHTML=`
<div style="background:var(--base);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
  <div class="sm-seg">
    ${['Month','Week','Day'].map(v=>`<button class="${v===view?'active':''}" onclick="calSetView('${v}')">${v}</button>`).join('')}
  </div>
  <button class="sm-btn" onclick="calNav(-1)">‹</button>
  <span style="font-size:13px;font-family:monospace;min-width:220px;text-align:center">${escH(title)}</span>
  <button class="sm-btn" onclick="calNav(1)">›</button>
  <button class="sm-btn" onclick="calGoToday()" style="font-size:11px">Today</button>
  <div style="flex:1"></div>
  <button class="sm-btn solid" onclick="openAddModal()">+ Add Show</button>
  <button class="sm-btn danger" onclick="openAddModal(true)">Blackout Day</button>
</div>
<div id="cal-wrap" style="overflow-x:auto;background:var(--bg);border-bottom:1px solid var(--border)">${calHtml}</div>
<div style="background:var(--bg);max-height:300px;overflow:auto">
  <div style="padding:10px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--bg);z-index:2">
    <span style="font-size:13px;font-weight:600;color:var(--sub)">Repeating Rules</span>
    <button class="sm-btn sm" onclick="openRuleForm()">+ New Rule</button>
  </div>
  <div style="padding:12px 20px">${rulesHtml}</div>
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
async function deleteEntry(id){
  if(!confirm('Remove this entry?'))return;
  await fetch(AJAX+'&action=delete_entry&id='+encodeURIComponent(id));
  invalidate();calLoad();closeModal();
}
async function deleteRuleConfirm(id){
  if(!confirm('Delete this repeating rule and all its generated shows?'))return;
  await fetch(AJAX+'&action=delete_rule&id='+encodeURIComponent(id));
  invalidate();calLoad();closeModal();
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
  const rows=ents.length?ents.map(e=>{
    const isRule=!!e.rule_id,isBlk=e.type==='blackout';
    const c=isBlk?'var(--red)':isRule?'var(--s1)':'var(--mint)';
    return `<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;background:var(--raise);border:1px solid var(--border);margin-bottom:8px">
      <div style="width:3px;height:32px;background:${c};border-radius:2px"></div>
      <span style="font-family:monospace;font-size:13px;color:${c};min-width:48px">${escH(e.time||'')}</span>
      <span style="flex:1;font-size:13px;font-weight:600">${escH(qlabel(e))}</span>
      ${isRule
        ?`<button class="sm-btn sm" onclick="editRule('${escH(e.rule_id)}');closeModal()">Edit Rule</button><button class="sm-btn sm danger" onclick="deleteRuleConfirm('${escH(e.rule_id)}')">Del Rule</button>`
        :`<button class="sm-btn sm danger" onclick="deleteEntry('${escH(e.id)}')">Remove</button>`}
    </div>`;
  }).join(''):'<p style="color:var(--mut);font-size:13px;margin-bottom:12px">No entries.</p>';
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal">
      <div class="sm-modal-head"><span>${escH(ds)}</span><button onclick="closeModal()" style="background:none;border:none;color:var(--sub);font-size:20px;cursor:pointer">×</button></div>
      <div class="sm-modal-body">${rows}
        <div class="sm-hr"></div>
        <button class="sm-btn solid" onclick="closeModal();openAddModal(false,'${ds}')">+ Add Show</button>
        <button class="sm-btn danger" style="margin-left:8px" onclick="closeModal();openAddModal(true,'${ds}')">+ Blackout</button>
        <button class="sm-btn" style="margin-left:8px" onclick="closeModal();calState.cursor=new Date('${ds}T00:00:00');calSetView('Day')">Day View</button>
      </div>
    </div>
  </div>`);
}

/* ── ADD SHOW MODAL ── */
function openAddModal(blackout=false,ds=''){
  ds=ds||fmtDate(calState.cursor);
  const pl=PLAYLISTS[0]||'';
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal" style="width:360px">
      <div class="sm-modal-head"><span>${blackout?'Blackout Day':'Add Show'}</span><button onclick="closeModal()" style="background:none;border:none;color:var(--sub);font-size:20px;cursor:pointer">×</button></div>
      <div class="sm-modal-body">
        <div class="sm-fr"><label>Date</label><input type="date" id="add-date" class="sm-input" value="${escH(ds)}"></div>
        ${!blackout?`<div class="sm-fr"><label>Time</label><input type="time" id="add-time" class="sm-input" value="19:00"></div>
        <div class="sm-fr"><label>Playlist</label><select id="add-pl" class="sm-select">${plOptions(pl)}</select></div>`:`
        <div class="sm-fr"><label>Start time</label><input type="time" id="add-bo-start" class="sm-input"></div>
        <div class="sm-fr"><label>End time</label><input type="time" id="add-bo-end" class="sm-input"></div>
        <div style="font-size:11px;color:var(--mut);margin-top:-6px">Leave times blank to block the whole day</div>`}
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">
          <button class="sm-btn" onclick="closeModal()">Cancel</button>
          <button class="sm-btn ${blackout?'danger':'solid'}" onclick="saveAddModal(${blackout})">${blackout?'Mark Blackout':'Add Show'}</button>
        </div>
      </div>
    </div>
  </div>`);
}
async function saveAddModal(blackout){
  const date=document.getElementById('add-date').value;
  if(!date)return alert('Pick a date.');
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
}

/* ── RULE FORM MODAL ── */
function openRuleForm(r){
  const isNew=!r;
  r=r||{id:'',start_date:fmtDate(new Date()),end_date:'',days:[0,1,2,3,4,5,6],window_start:'19:00',window_end:'',interval_mins:''};
  const dayLabels=['Su','Mo','Tu','We','Th','Fr','Sa'];
  const dayBtns=dayLabels.map((l,i)=>`<button type="button" class="sm-btn${(r.days||[]).includes(i)?' solid':''}" id="rd${i}" onclick="rdToggle(${i})">${l}</button>`).join('');
  const selPl=r.playlist||(r.playlists&&r.playlists[0])||'';
  showModal(`<div class="sm-overlay" onclick="if(event.target===this)closeModal()">
    <div class="sm-modal" style="width:420px">
      <div class="sm-modal-head"><span>${isNew?'New Repeating Rule':'Edit Rule'}</span><button onclick="closeModal()" style="background:none;border:none;color:var(--sub);font-size:20px;cursor:pointer">×</button></div>
      <div class="sm-modal-body">
        <input type="hidden" id="rule-id" value="${escH(r.id)}">
        <div class="sm-fr"><label>Start date</label><input type="date" id="rule-start" class="sm-input" value="${escH(r.start_date)}"></div>
        <div class="sm-fr"><label>End date</label><input type="date" id="rule-end" class="sm-input" value="${escH(r.end_date||'')}"></div>
        <div class="sm-fr"><label>Days</label><div style="display:flex;gap:4px">${dayBtns}</div></div>
        <div class="sm-fr"><label>Show time</label><input type="time" id="rule-wstart" class="sm-input" value="${escH(r.window_start||'19:00')}"></div>
        <div class="sm-fr"><label>Repeat until</label><input type="time" id="rule-wend" class="sm-input" value="${escH(r.window_end||'')}"></div>
        <div class="sm-fr"><label>Interval (mins)</label><input type="number" id="rule-iv" class="sm-input" value="${escH(String(r.interval_mins||''))}" min="1" step="1"></div>
        <div class="sm-fr"><label>Playlist</label><select id="rule-pl" class="sm-select">${plOptions(selPl)}</select></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">
          <button class="sm-btn" onclick="closeModal()">Cancel</button>
          <button class="sm-btn solid" onclick="saveRule()">Save Rule</button>
        </div>
      </div>
    </div>
  </div>`);
}
function rdToggle(i){
  const btn=document.getElementById('rd'+i);
  btn.classList.toggle('solid');
}
async function saveRule(){
  const days=[];
  for(let i=0;i<7;i++)if(document.getElementById('rd'+i).classList.contains('solid'))days.push(i);
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
  if(!body.start_date||!body.end_date||!body.window_start)return alert('Fill in start date, end date and show time.');
  await fetch(AJAX+'&action=save_rule',{method:'POST',body:JSON.stringify(body)});
  closeModal();invalidate();calLoad();
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
function renderAnnouncements(){
  const el=document.getElementById('sm-announcements');
  const cfg=annCfg;
  const files=cfg._files||{main:[],daytime:[]};
  const preShow=cfg.pre_show||[{mins_before:5,file:''}];
  const daytime=cfg.daytime||{};
  const allMp3s=[...(files.main||[]),...(files.daytime||[])];
  const datalist=allMp3s.map(f=>`<option value="${escH(f)}">`).join('');
  const preRows=preShow.map((p,i)=>`<tr>
    <td><input type="number" class="sm-input" style="width:70px" value="${p.mins_before||5}" min="1" max="120" id="pre-off-${i}"></td>
    <td style="flex:1"><input type="text" class="sm-input" style="width:100%" value="${escH(p.file||'')}" list="mp3-list" id="pre-file-${i}"></td>
    <td><button class="sm-btn sm danger" onclick="annRemovePre(${i})">×</button></td>
  </tr>`).join('');
  const mainRows=(files.main||[]).map(f=>`<tr><td>${escH(f)}</td><td style="color:var(--sub)">Main</td><td><button class="sm-btn sm danger" onclick="annDeleteFile('${escH(escJ(f))}')">Delete</button></td></tr>`).join('');
  const dtRows=(files.daytime||[]).map(f=>`<tr><td>${escH(f)}</td><td style="color:var(--sub)">Daytime</td><td><button class="sm-btn sm danger" onclick="annDeleteFile('daytime/${escH(escJ(f))}')">Delete</button></td></tr>`).join('');
  const fileRows=mainRows+dtRows;
  el.innerHTML=`<datalist id="mp3-list">${datalist}</datalist>
  <div style="display:flex;flex-direction:column;gap:14px;max-width:680px">
  <div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Ducking</h4>
    <div class="sm-fr"><label>Duck level (0–1)</label><input type="number" id="ann-duck" class="sm-input" value="${cfg.duck_level??0.25}" min="0" max="1" step="0.05"></div>
    <div class="sm-fr"><label>Fade duration (sec)</label><input type="number" id="ann-fade" class="sm-input" value="${cfg.duck_fade_secs??2}" min="0.5" max="10" step="0.5"></div>
    <div class="sm-fr"><label>Gain boost (dB)</label><input type="number" id="ann-gain" class="sm-input" value="${cfg.gain_db??6}" min="0" max="24"></div>
    <div class="sm-fr"><label>Max duration (sec)</label><input type="number" id="ann-maxdur" class="sm-input" value="${cfg.max_duration_secs??300}" min="10" max="3600"></div>
  </div>
  <div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Lighting</h4>
    <div class="sm-fr"><label>Pre-show brightness</label><input type="number" id="ann-prebright" class="sm-input" value="${cfg.pre_show_brightness??20}" min="0" max="200"></div>
    <div class="sm-fr"><label>Normal brightness</label><input type="number" id="ann-normbright" class="sm-input" value="${cfg.normal_brightness??100}" min="0" max="200"></div>
  </div>
  <div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Background Music</h4>
    <div class="sm-fr"><label>Background playlist</label><select id="ann-bgpl" class="sm-select">${plOptions(cfg.background_playlist||'')}</select></div>
  </div>
  <div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Pre-Show Announcements</h4>
    <p style="font-size:12px;color:var(--sub);margin:0 0 10px">Each row fires one announcement N minutes before show time.</p>
    <table id="ann-pre-table" style="width:100%;border-collapse:collapse">
      <thead><tr><th style="text-align:left;font-size:11px;color:var(--mut);padding:0 8px 6px 0">Mins before</th><th style="text-align:left;font-size:11px;color:var(--mut);padding:0 0 6px">Audio file</th><th></th></tr></thead>
      <tbody>${preRows}</tbody>
    </table>
    <button class="sm-btn" style="margin-top:8px" onclick="annAddPre()">+ Add</button>
  </div>
  <div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Daytime Announcements</h4>
    <div class="sm-fr"><label>Enable</label><input type="checkbox" id="ann-dt-en" ${daytime.enabled?'checked':''} style="width:auto"></div>
    <div class="sm-fr"><label>Window</label>
      <input type="time" id="ann-dt-start" class="sm-input" value="${escH(daytime.start||'10:00')}" style="width:110px">
      <span style="align-self:center;padding:0 6px;color:var(--sub)">to</span>
      <input type="time" id="ann-dt-end" class="sm-input" value="${escH(daytime.end||'18:00')}" style="width:110px">
    </div>
    <div class="sm-fr"><label>Interval (mins)</label><input type="number" id="ann-dt-iv" class="sm-input" value="${daytime.interval_mins??20}" min="5" max="240"></div>
  </div>
  <div style="display:flex;justify-content:flex-end">
    <button class="sm-btn solid" onclick="saveAnnouncements()">Save Settings</button>
  </div>
  <div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Upload Announcement File</h4>
    <div class="sm-fr"><label>File (MP3/WAV/OGG)</label><input type="file" id="ann-file" accept=".mp3,.wav,.ogg" class="sm-input"></div>
    <div class="sm-fr"><label>Destination</label><select id="ann-dest" class="sm-select"><option value="main">Main (pre-show)</option><option value="daytime">Daytime</option></select></div>
    <div style="display:flex;justify-content:flex-end;margin-top:8px"><button class="sm-btn solid" onclick="annUpload()">Upload</button></div>
  </div>
  ${fileRows?`<div class="sm-card">
    <h4 style="margin:0 0 12px;color:var(--text)">Announcement Files</h4>
    <table style="width:100%;border-collapse:collapse">
      <thead><tr><th style="text-align:left;font-size:11px;color:var(--mut);padding:0 0 6px">File</th><th style="text-align:left;font-size:11px;color:var(--mut)">Folder</th><th></th></tr></thead>
      <tbody>${fileRows}</tbody>
    </table>
  </div>`:''}
  </div>`;
}
async function saveAnnouncements(){
  const preShow=annGetPreRows().filter(r=>r.file);
  preShow.sort((a,b)=>b.mins_before-a.mins_before);
  const body={
    duck_level:numOr(document.getElementById('ann-duck').value,0.25),
    duck_fade_secs:numOr(document.getElementById('ann-fade').value,2),
    gain_db:numOr(document.getElementById('ann-gain').value,6),
    max_duration_secs:Math.round(numOr(document.getElementById('ann-maxdur').value,300)),
    pre_show_brightness:Math.round(numOr(document.getElementById('ann-prebright').value,20)),
    normal_brightness:Math.round(numOr(document.getElementById('ann-normbright').value,100)),
    background_playlist:document.getElementById('ann-bgpl').value,
    pre_show:preShow,
    daytime:{enabled:document.getElementById('ann-dt-en').checked,start:document.getElementById('ann-dt-start').value,end:document.getElementById('ann-dt-end').value,interval_mins:parseInt(document.getElementById('ann-dt-iv').value)||20},
  };
  const r=await fetch(AJAX+'&action=save_announcements',{method:'POST',body:JSON.stringify(body)});
  const j=await r.json();
  if(j.ok){Object.assign(annCfg,body);alert('Saved.');}
}
async function annUpload(){
  const fi=document.getElementById('ann-file');
  if(!fi.files.length)return alert('Select a file first.');
  const fd=new FormData();
  fd.append('file',fi.files[0]);
  fd.append('folder',document.getElementById('ann-dest').value);
  const r=await fetch(AJAX+'&action=upload_announcement',{method:'POST',body:fd});
  const j=await r.json();
  if(j.ok){fi.value='';loadAnnouncements();}else alert('Upload failed.');
}
async function annDeleteFile(path){
  if(!confirm('Delete '+path+'?'))return;
  await fetch(AJAX+'&action=delete_announcement&path='+encodeURIComponent(path));
  loadAnnouncements();
}

/* ── HARDWARE TAB ── */
async function loadHardware(){
  const el=document.getElementById('sm-hardware');
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
  el.innerHTML=`<div class="sm-card" style="max-width:480px">
    <h3 style="margin:0 0 16px;color:var(--text)">XR18 Mixer</h3>
    <div class="sm-fr"><label>Mixer IP address</label><input type="text" id="hw-ip" class="sm-input" value="${ip}" placeholder="192.168.1.x"></div>
    <div class="sm-fr"><label>Music fader channel</label><input type="number" id="hw-ch" class="sm-input" value="${ch}" min="1" max="18"></div>
    <div class="sm-fr"><label>Show level (0–1)</label><input type="number" id="hw-lvl" class="sm-input" value="${lvl}" min="0" max="1" step="0.01"><div class="hint">Music faders move here when a show starts</div></div>
    <div class="sm-fr"><label>Idle level (0–1)</label><input type="number" id="hw-idle" class="sm-input" value="${idle}" min="0" max="1" step="0.01"><div class="hint">Music faders move here after a show ends</div></div>
    <div class="sm-fr"><label>Announce channel</label><input type="number" id="hw-ach" class="sm-input" value="${ach}" min="1" max="18"></div>
    <div class="sm-fr"><label>Announce level (0–1)</label><input type="number" id="hw-avol" class="sm-input" value="${avol}" min="0" max="1" step="0.01"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:12px">
      <button class="sm-btn solid" onclick="saveHardware()">Save</button>
    </div>
    <div class="hint" style="margin-top:8px">Saving restarts the XR18 bridge so changes apply immediately.</div>
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
  if(j.ok)alert('Saved. Bridge restarted.');
}

/* ── INIT ── */
loadStatus();
</script>
</body>
</html>
