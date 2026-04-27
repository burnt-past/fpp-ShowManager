<?php
$playlists = [];
$playlistDir = "/home/fpp/media/playlists";
if (is_dir($playlistDir)) {
    foreach (glob("$playlistDir/*.json") as $f) $playlists[] = basename($f, '.json');
    sort($playlists);
}
$AJAX = 'plugin.php?plugin=' . basename(__DIR__) . '&page=ajax.php&nopage=1';
$playlistsJson = json_encode($playlists);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>

<style>
/* ── font override inside FPP chrome ─────────────── */
#sm-app, #sm-app * { box-sizing: border-box; }
#sm-app { font-family: 'Outfit', sans-serif; }
#sm-app input, #sm-app select, #sm-app textarea {
  font-family: 'DM Mono', monospace;
}
@keyframes sm-blink  { 0%,49%{opacity:1} 50%,100%{opacity:0} }
@keyframes sm-fadein { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
#sm-app button { font-family: 'Outfit', sans-serif; cursor: pointer; }
#sm-app ::-webkit-scrollbar { width: 5px; height: 5px; }
#sm-app ::-webkit-scrollbar-track { background: transparent; }
#sm-app ::-webkit-scrollbar-thumb { background: rgba(128,128,128,.25); border-radius: 10px; }
</style>

<div id="sm-app"></div>

<script type="text/babel">
const { useState, useEffect, useContext, createContext, useMemo, useCallback, useRef } = React;

/* ── AJAX base URL injected from PHP ─── */
const AJAX = '<?= $AJAX ?>';
const PLAYLISTS = <?= $playlistsJson ?>;

/* ── Design tokens ──────────────────────────────────── */
const DARK = {
  bg:'#0f1117', base:'#13161f', card:'#1a1d27', raise:'#1f2330', high:'#252839',
  border:'rgba(255,255,255,0.07)', brdHi:'rgba(255,255,255,0.13)',
  text:'#e2e6f3', sub:'#7c85a2', mut:'#3e4558',
  mint:'#34d399', mintD:'rgba(52,211,153,0.10)', mintG:'rgba(52,211,153,0.20)',
  amber:'#f59e0b', amberD:'rgba(245,158,11,0.12)',
  red:'#f43f5e', redD:'rgba(244,63,94,0.12)',
  blue:'#60a5fa', blueD:'rgba(96,165,250,0.12)',
  s1:'#3b82f6', s2:'#a855f7',
};
const LIGHT = {
  bg:'#f0f2f7', base:'#e8ebf2', card:'#ffffff', raise:'#f5f6fa', high:'#eaecf4',
  border:'rgba(0,0,0,0.08)', brdHi:'rgba(0,0,0,0.15)',
  text:'#1a1d2e', sub:'#4a5270', mut:'#9098b8',
  mint:'#059669', mintD:'rgba(5,150,105,0.10)', mintG:'rgba(5,150,105,0.20)',
  amber:'#d97706', amberD:'rgba(217,119,6,0.10)',
  red:'#dc2626', redD:'rgba(220,38,38,0.10)',
  blue:'#2563eb', blueD:'rgba(37,99,235,0.12)',
  s1:'#2563eb', s2:'#7c3aed',
};

const ThemeCtx = createContext({ T: DARK, isDark: true, toggle: ()=>{} });
const useTheme = () => useContext(ThemeCtx);

/* ── Helpers ────────────────────────────────────────── */
const DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function fmtDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function getSundayOf(d) { const s=new Date(d); s.setDate(s.getDate()-s.getDay()); s.setHours(0,0,0,0); return s; }
function addDays(d,n)   { const r=new Date(d); r.setDate(r.getDate()+n); return r; }
function monthKey(y,m)  { return `${y}-${String(m).padStart(2,'0')}`; }
function parseMins(s)   { const [h,m]=s.split(':').map(Number); return h*60+m; }
function fmtMins(n)     { return `${String(Math.floor(n/60)).padStart(2,'0')}:${String(n%60).padStart(2,'0')}`; }
function entryLabel(e)  {
  if (e.type==='blackout') return '✖ Blackout';
  return e.playlist || (e.playlists||[]).join(' / ') || '(show)';
}

/* ── Data fetch helpers ──────────────────────────────── */
const dataCache = {};
async function fetchMonth(y, m) {
  const key = monthKey(y,m);
  if (dataCache[key]) return dataCache[key];
  const r = await fetch(`${AJAX}&action=get_month&year=${y}&month=${m}`);
  const d = await r.json();
  dataCache[key] = d;
  return d;
}
function invalidateCache() { Object.keys(dataCache).forEach(k=>delete dataCache[k]); }

function getEntriesForDate(monthDataList, ds) {
  const all = [];
  for (const md of monthDataList) {
    for (const e of (md.entries||[])) {
      if (e.date===ds) all.push(e);
    }
  }
  return all.sort((a,b)=>(a.time||'').localeCompare(b.time||''));
}

/* ── Design primitives ───────────────────────────────── */
function Card({ children, style:s={}, glow }) {
  const {T} = useTheme();
  return (
    <div style={{ background:T.card, borderRadius:12,
      border:`1px solid ${glow?T.mintG:T.border}`,
      boxShadow: glow?`0 0 0 1px ${T.mintG},0 8px 24px rgba(0,0,0,.2)`:'0 2px 8px rgba(0,0,0,.12)',
      ...s }}>{children}</div>
  );
}

function Badge({ children, color, bg }) {
  const {T} = useTheme(); color = color||T.mint;
  return (
    <span style={{ display:'inline-flex', alignItems:'center', gap:5, padding:'3px 9px',
      borderRadius:20, background:bg||`${color}18`, border:`1px solid ${color}33`,
      fontSize:11, fontWeight:600, color, letterSpacing:'0.02em' }}>{children}</span>
  );
}

function Dot({ color, size=7, blink=false }) {
  const {T} = useTheme(); color=color||T.mint;
  return (
    <span style={{ display:'inline-block', width:size, height:size, borderRadius:'50%',
      background:color, flexShrink:0, boxShadow:`0 0 ${size*1.4}px ${color}88`,
      animation:blink?'sm-blink .9s step-end infinite':'none' }} />
  );
}

function Mono({ children, size=13, color, style:s={} }) {
  const {T}=useTheme();
  return (
    <span style={{ fontFamily:"'DM Mono',monospace", fontSize:size, color:color||T.text,
      fontVariantNumeric:'tabular-nums', ...s }}>{children}</span>
  );
}

function SectionLabel({ children }) {
  const {T}=useTheme();
  return <div style={{ fontSize:10, fontWeight:600, letterSpacing:'0.1em', textTransform:'uppercase', color:T.mut, marginBottom:10 }}>{children}</div>;
}

function Divider({ v }) {
  const {T}=useTheme();
  return v
    ? <div style={{ width:1, height:20, background:T.border, flexShrink:0 }} />
    : <div style={{ height:1, background:T.border, margin:'12px 0' }} />;
}

function Btn({ children, onClick, v='ghost', accent, small=false, style:s={}, disabled=false }) {
  const {T}=useTheme(); accent=accent||T.mint;
  const vs = {
    ghost:  { bg:'transparent', border:`1px solid ${T.border}`, color:T.sub },
    solid:  { bg:accent, border:`1px solid ${accent}`, color:'#fff', fontWeight:600 },
    soft:   { bg:`${accent}18`, border:`1px solid ${accent}33`, color:accent, fontWeight:600 },
    danger: { bg:T.redD, border:`1px solid ${T.red}44`, color:T.red },
  }[v]||{};
  return (
    <button onClick={onClick} disabled={disabled}
      style={{ padding:small?'3px 10px':'5px 14px', fontSize:small?10:12, fontWeight:500,
        letterSpacing:'0.02em', borderRadius:7, fontFamily:'Outfit',
        background:vs.bg, border:vs.border, color:vs.color, opacity:disabled?.5:1, ...s }}>
      {children}
    </button>
  );
}

function Seg({ opts, val, onChange }) {
  const {T}=useTheme();
  return (
    <div style={{ display:'flex', background:T.bg, borderRadius:8, padding:2, gap:2, border:`1px solid ${T.border}` }}>
      {opts.map(o=>(
        <button key={o} onClick={()=>onChange(o)} style={{ padding:'4px 14px', borderRadius:6, border:'none',
          background:val===o?T.card:'transparent', color:val===o?T.text:T.sub,
          fontFamily:'Outfit', fontWeight:val===o?600:400, fontSize:12, cursor:'pointer', transition:'all .15s' }}>{o}</button>
      ))}
    </div>
  );
}

function ModalBox({ title, onClose, children, width=460 }) {
  const {T}=useTheme();
  return (
    <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.65)', zIndex:9999,
      display:'flex', alignItems:'center', justifyContent:'center', backdropFilter:'blur(3px)' }}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{ background:T.card, borderRadius:14, border:`1px solid ${T.brdHi}`,
        width, maxWidth:'95vw', maxHeight:'85vh', overflow:'auto',
        boxShadow:'0 24px 60px rgba(0,0,0,.5)', animation:'sm-fadein .2s ease' }}>
        <div style={{ padding:'16px 20px', borderBottom:`1px solid ${T.border}`,
          display:'flex', justifyContent:'space-between', alignItems:'center' }}>
          <div style={{ fontWeight:600, fontSize:15, color:T.text }}>{title}</div>
          <button onClick={onClose} style={{ background:'none', border:'none',
            color:T.sub, cursor:'pointer', fontSize:20, lineHeight:1, padding:2 }}>×</button>
        </div>
        <div style={{ padding:20 }}>{children}</div>
      </div>
    </div>
  );
}

function FR({ label, children, hint }) {
  const {T}=useTheme();
  return (
    <div style={{ marginBottom:14 }}>
      <div style={{ fontSize:11, fontWeight:600, color:T.sub, marginBottom:5, letterSpacing:'0.04em' }}>{label}</div>
      {children}
      {hint && <div style={{ fontSize:10, color:T.mut, marginTop:4 }}>{hint}</div>}
    </div>
  );
}

function FInput({ value, onChange, type='text', min, max, step, placeholder, list }) {
  const {T}=useTheme();
  return (
    <input type={type} value={value} onChange={onChange} min={min} max={max} step={step}
      placeholder={placeholder} list={list}
      style={{ fontFamily:"'DM Mono',monospace", background:T.raise, border:`1px solid ${T.border}`,
        color:T.text, fontSize:12, padding:'7px 10px', outline:'none', width:'100%',
        borderRadius:6, boxSizing:'border-box' }} />
  );
}

function FSelect({ value, onChange, children }) {
  const {T}=useTheme();
  return (
    <select value={value} onChange={onChange}
      style={{ fontFamily:"'DM Mono',monospace", background:T.raise, border:`1px solid ${T.border}`,
        color:T.text, fontSize:12, padding:'7px 10px', outline:'none', width:'100%',
        borderRadius:6, boxSizing:'border-box' }}>
      {children}
    </select>
  );
}

/* ══ STATUS TAB ════════════════════════════════════════ */
function StatusTab() {
  const {T} = useTheme();
  const [fpp, setFpp] = useState(null);
  const [xr18, setXr18] = useState(null);
  const [todayShows, setTodayShows] = useState([]);

  useEffect(() => {
    const load = async () => {
      try {
        const [sr, xr, mr] = await Promise.all([
          fetch('/api/fppd/status').then(r=>r.json()).catch(()=>null),
          fetch(`${AJAX}&action=get_status`).then(r=>r.json()).catch(()=>null),
          fetch(`${AJAX}&action=get_month&year=${new Date().getFullYear()}&month=${new Date().getMonth()+1}`).then(r=>r.json()).catch(()=>({entries:[],rules:[]})),
        ]);
        setFpp(sr);
        setXr18(xr);
        const today = fmtDate(new Date());
        setTodayShows((mr.entries||[]).filter(e=>e.date===today&&e.type==='show').sort((a,b)=>a.time.localeCompare(b.time)));
      } catch(e) {}
    };
    load();
    const t = setInterval(load, 8000);
    return () => clearInterval(t);
  }, []);

  const isPlaying = fpp?.status === 1 || fpp?.status === 'playing';
  const curPl = fpp?.current_playlist?.name || fpp?.current_song || '—';
  const uptime = fpp?.uptime || '—';
  const vol = fpp?.volume ?? '—';
  const now = fmtDate(new Date());
  const upcoming = todayShows.filter(s => s.time >= new Date().toTimeString().slice(0,5)).slice(0,4);

  const MetricCard = ({ label, value, unit='' }) => (
    <Card style={{ padding:'14px 16px', flex:1, minWidth:100 }}>
      <div style={{ fontSize:11, color:T.sub, fontWeight:500 }}>{label}</div>
      <div style={{ marginTop:6, display:'flex', alignItems:'baseline', gap:4 }}>
        <Mono size={20} color={T.mint}>{value}</Mono>
        {unit && <span style={{ fontSize:12, color:T.mut }}>{unit}</span>}
      </div>
    </Card>
  );

  return (
    <div style={{ padding:20, display:'flex', flexDirection:'column', gap:16 }}>
      <Card glow style={{ padding:'24px 28px', position:'relative', overflow:'hidden' }}>
        <div style={{ position:'absolute', top:0, right:0, width:260, height:260, borderRadius:'50%',
          background:'radial-gradient(circle,rgba(52,211,153,0.06) 0%,transparent 70%)', pointerEvents:'none' }} />
        <div style={{ display:'flex', alignItems:'flex-start', justifyContent:'space-between', gap:24, flexWrap:'wrap' }}>
          <div style={{ flex:1, minWidth:200 }}>
            <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:8 }}>
              <Badge color={isPlaying?T.amber:T.sub}>
                {isPlaying && <Dot color={T.amber} size={6} blink />}
                {isPlaying ? 'On Air' : 'Idle'}
              </Badge>
            </div>
            <div style={{ fontSize:36, fontWeight:700, letterSpacing:'-0.03em', color:T.text, lineHeight:1 }}>
              {isPlaying ? curPl : 'Idle'}
            </div>
            <div style={{ marginTop:6, fontSize:13, color:T.sub }}>
              Uptime <Mono size={13} color={T.text}>{uptime}</Mono>
              &nbsp;&nbsp;Volume <Mono size={13} color={T.text}>{vol !== '—' ? vol+'%' : '—'}</Mono>
              {xr18?.xr18_fader != null && <>&nbsp;&nbsp;XR18 <Mono size={13} color={T.mint}>{xr18.xr18_fader.toFixed(2)}</Mono></>}
            </div>
          </div>
        </div>
      </Card>

      <div style={{ display:'flex', gap:12, flexWrap:'wrap' }}>
        <MetricCard label="FPP Version" value={fpp?.version||'—'} />
        <MetricCard label="Instance" value={fpp?.Instance_Name||'—'} />
        <MetricCard label="Shows Today" value={todayShows.length} />
        <MetricCard label="Upcoming" value={upcoming.length} />
      </div>

      <div style={{ display:'flex', gap:12, flexWrap:'wrap' }}>
        <Card style={{ flex:1, padding:'18px 20px', minWidth:200 }}>
          <SectionLabel>Today's Schedule</SectionLabel>
          {todayShows.length === 0 && <div style={{ fontSize:13, color:T.mut }}>No shows today</div>}
          {todayShows.map((s,i) => (
            <div key={i} style={{ display:'flex', alignItems:'center', gap:12, padding:'8px 10px',
              borderRadius:8, background:i===0?T.mintD:'transparent', marginBottom:2 }}>
              <Mono size={14} color={i===0?T.mint:T.sub}>{s.time}</Mono>
              <span style={{ fontSize:14, fontWeight:i===0?600:400, color:i===0?T.text:T.sub, flex:1 }}>
                {entryLabel(s)}
              </span>
              {i===0 && <Badge color={T.mint}>Next</Badge>}
            </div>
          ))}
        </Card>

        <Card style={{ width:260, flexShrink:0, padding:'18px 20px' }}>
          <SectionLabel>System</SectionLabel>
          {[
            { l:'FPP Daemon',   v:fpp?.fppd==='running'?'Running':'Stopped', ok:fpp?.fppd==='running' },
            { l:'Scheduler',    v:'Active', ok:true },
            { l:'XR18 Fader',   v:xr18?.xr18_fader!=null?xr18.xr18_fader.toFixed(2):'n/a', ok:xr18?.xr18_fader!=null },
          ].map(o=>(
            <div key={o.l} style={{ display:'flex', alignItems:'center', gap:10, padding:'8px 10px',
              background:T.high, borderRadius:8, marginBottom:6 }}>
              <Dot color={o.ok?T.mint:T.mut} size={7} />
              <div style={{ flex:1 }}>
                <div style={{ fontSize:13, fontWeight:500, color:T.text }}>{o.l}</div>
                <div style={{ fontSize:11, color:T.mut }}>{o.v}</div>
              </div>
            </div>
          ))}
        </Card>
      </div>
    </div>
  );
}

/* ══ SCHEDULE VIEWS ════════════════════════════════════ */
function MonthView({ year, month, byDate, onDayClick }) {
  const {T}=useTheme();
  const today=fmtDate(new Date());
  const firstDay=new Date(year,month,1).getDay();
  const dim=new Date(year,month+1,0).getDate();
  const cells=[]; for(let i=0;i<firstDay;i++)cells.push(null); for(let d=1;d<=dim;d++)cells.push(d);
  while(cells.length%7!==0)cells.push(null);
  const COLOR={};
  return (
    <div>
      <div style={{ display:'grid', gridTemplateColumns:'repeat(7,1fr)', padding:'8px 0', borderBottom:`1px solid ${T.border}` }}>
        {DAYS.map(d=><div key={d} style={{ textAlign:'center', fontSize:11, fontWeight:600, color:T.mut, letterSpacing:'0.06em' }}>{d}</div>)}
      </div>
      {[...Array(Math.ceil(cells.length/7))].map((_,wi)=>(
        <div key={wi} style={{ display:'grid', gridTemplateColumns:'repeat(7,1fr)', borderBottom:`1px solid ${T.border}` }}>
          {cells.slice(wi*7,wi*7+7).map((d,di)=>{
            if(!d) return <div key={di} style={{ minHeight:90, background:T.bg }} />;
            const mk=monthKey(year,month+1);
            const ds=`${mk}-${String(d).padStart(2,'0')}`;
            const ents=byDate[ds]||[];
            const isBlk=ents.some(e=>e.type==='blackout');
            const isToday=ds===today;
            return (
              <div key={di} onClick={()=>onDayClick(new Date(year,month,d))}
                style={{ minHeight:90, padding:8, cursor:'pointer',
                  background:isBlk?`${T.red}18`:isToday?T.mintD:T.bg,
                  borderRight:`1px solid ${T.border}`,
                  borderTop:isToday?`2px solid ${T.mint}`:`2px solid transparent` }}
                onMouseEnter={e=>e.currentTarget.style.background=isBlk?`${T.red}22`:isToday?T.mintD:T.card}
                onMouseLeave={e=>e.currentTarget.style.background=isBlk?`${T.red}18`:isToday?T.mintD:T.bg}>
                <div style={{ display:'flex', alignItems:'center', marginBottom:4 }}>
                  <div style={{ width:22, height:22, borderRadius:'50%', display:'flex', alignItems:'center', justifyContent:'center',
                    background:isToday?T.mint:'transparent' }}>
                    <Mono size={12} color={isToday?'#0f1117':T.sub}>{d}</Mono>
                  </div>
                </div>
                {isBlk && <div style={{ fontSize:10, color:T.red, fontWeight:600 }}>✖ Blackout</div>}
                {!isBlk && ents.slice(0,3).map((e,i)=>{
                  const c=e.rule_id?T.s1:T.mint;
                  return (
                    <div key={i} style={{ fontSize:10, fontWeight:500, color:'rgba(255,255,255,.8)',
                      background:`${c}28`, borderLeft:`2px solid ${c}`, padding:'1px 5px',
                      borderRadius:'0 3px 3px 0', marginBottom:2,
                      overflow:'hidden', whiteSpace:'nowrap', textOverflow:'ellipsis' }}>
                      {e.time} {entryLabel(e)}
                    </div>
                  );
                })}
                {ents.length>3&&<div style={{ fontSize:10, color:T.mut }}>+{ents.length-3} more</div>}
              </div>
            );
          })}
        </div>
      ))}
    </div>
  );
}

function WeekView({ weekStart, byDate, onDayClick }) {
  const {T}=useTheme();
  const today=fmtDate(new Date());
  const days=Array.from({length:7},(_,i)=>addDays(weekStart,i));
  return (
    <div>
      <div style={{ display:'flex', borderBottom:`1px solid ${T.border}` }}>
        {days.map((d,i)=>{
          const ds=fmtDate(d); const isToday=ds===today;
          return (
            <div key={i} onClick={()=>onDayClick(d)}
              style={{ flex:1, padding:'10px 4px', textAlign:'center', borderLeft:i?`1px solid ${T.border}`:'none',
                cursor:'pointer', background:isToday?T.mintD:'transparent' }}>
              <div style={{ fontSize:11, fontWeight:600, color:isToday?T.mint:T.mut, letterSpacing:'0.05em' }}>{DAYS[d.getDay()]}</div>
              <div style={{ width:26, height:26, borderRadius:'50%', margin:'4px auto 0',
                background:isToday?T.mint:'transparent', display:'flex', alignItems:'center', justifyContent:'center' }}>
                <Mono size={12} color={isToday?'#0f1117':T.text}>{d.getDate()}</Mono>
              </div>
            </div>
          );
        })}
      </div>
      <div style={{ display:'flex' }}>
        {days.map((d,i)=>{
          const ds=fmtDate(d); const isToday=ds===today;
          const ents=(byDate[ds]||[]).filter(e=>e.type!=='blackout');
          const isBlk=(byDate[ds]||[]).some(e=>e.type==='blackout');
          return (
            <div key={i} onClick={()=>onDayClick(d)}
              style={{ flex:1, minHeight:120, padding:6, borderLeft:i?`1px solid ${T.border}`:'none',
                cursor:'pointer', background:isBlk?`${T.red}10`:isToday?`${T.mint}05`:'transparent' }}>
              {isBlk&&<div style={{ fontSize:10, color:T.red, fontWeight:600, marginBottom:4 }}>✖ Blackout</div>}
              {ents.map((e,ei)=>{
                const c=e.rule_id?T.s1:T.mint;
                return (
                  <div key={ei} style={{ fontSize:10, fontWeight:600, color:c,
                    background:`${c}20`, borderLeft:`2px solid ${c}`,
                    padding:'2px 5px', borderRadius:'0 3px 3px 0', marginBottom:2,
                    overflow:'hidden', whiteSpace:'nowrap', textOverflow:'ellipsis' }}>
                    {e.time} {entryLabel(e)}
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function DayView({ date, byDate, rules, onAdd, onEditRule, onDeleteEntry, onDeleteRule }) {
  const {T}=useTheme();
  const ds=fmtDate(date);
  const ents=byDate[ds]||[];
  if(!ents.length) return (
    <div style={{ textAlign:'center', padding:'48px 0', color:T.sub }}>
      <div style={{ fontSize:32, marginBottom:10 }}>📅</div>
      <div style={{ fontSize:15, fontWeight:500 }}>No shows scheduled</div>
      <div style={{ fontSize:13, color:T.mut, marginTop:6 }}>Click "+ Add Show" to schedule one</div>
    </div>
  );
  return (
    <div style={{ display:'flex', flexDirection:'column', gap:6, padding:'4px 0' }}>
      {ents.map((e,i)=>{
        const isRule=!!e.rule_id; const isBlk=e.type==='blackout';
        const c=isBlk?T.red:isRule?T.s1:T.mint;
        return (
          <div key={i} style={{ display:'flex', alignItems:'center', gap:14, padding:'12px 16px',
            borderRadius:10, background:T.card, border:`1px solid ${T.border}` }}>
            <div style={{ width:4, height:36, background:c, borderRadius:2, flexShrink:0 }} />
            <Mono size={14} color={c} style={{ minWidth:52 }}>{e.time||''}</Mono>
            <div style={{ flex:1, fontSize:14, fontWeight:600, color:T.text }}>{entryLabel(e)}</div>
            <div style={{ display:'flex', gap:6 }}>
              {isRule
                ? <><Btn v="ghost" small onClick={()=>onEditRule(e.rule_id)}>Edit Rule</Btn>
                     <Btn v="danger" small onClick={()=>onDeleteRule(e.rule_id)}>Remove Rule</Btn></>
                : <Btn v="danger" small onClick={()=>onDeleteEntry(e.id)}>Remove</Btn>
              }
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ══ SCHEDULE TAB ══════════════════════════════════════ */
const EMPTY_RULE = { start_date:'', end_date:'', days:[0,1,2,3,4,5,6], window_start:'19:00', window_end:'', interval_mins:'', playlist:'' };

function RuleForm({ initial, onSave, onCancel }) {
  const {T}=useTheme();
  const [f,setF]=useState({...EMPTY_RULE, playlist:PLAYLISTS[0]||'', ...initial});
  const upd=k=>e=>setF(p=>({...p,[k]:e.target.value}));
  const toggleDay=d=>setF(p=>({...p,days:p.days.includes(d)?p.days.filter(x=>x!==d):[...p.days,d]}));
  return (
    <div style={{ background:T.raise, borderRadius:10, border:`1px solid ${T.border}`, padding:'16px 18px' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:14 }}>
        <div style={{ fontSize:14, fontWeight:600, color:T.text }}>{f.id?'Edit Rule':'New Repeating Rule'}</div>
        {onCancel&&<button onClick={onCancel} style={{ background:'none', border:'none', color:T.sub, cursor:'pointer', fontSize:20 }}>×</button>}
      </div>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:12, marginBottom:12 }}>
        <FR label="Start Date"><FInput type="date" value={f.start_date} onChange={upd('start_date')} /></FR>
        <FR label="End Date"><FInput type="date" value={f.end_date} onChange={upd('end_date')} /></FR>
      </div>
      <FR label="Days of Week">
        <div style={{ display:'flex', gap:5, flexWrap:'wrap', marginTop:4 }}>
          {DAYS.map((d,i)=>(
            <button key={d} onClick={()=>toggleDay(i)}
              style={{ padding:'4px 10px', borderRadius:6, fontFamily:'Outfit', fontWeight:600, fontSize:11, cursor:'pointer',
                border:`1px solid ${f.days.includes(i)?T.mint:T.border}`,
                background:f.days.includes(i)?T.mintD:'transparent',
                color:f.days.includes(i)?T.mint:T.sub }}>{d}</button>
          ))}
        </div>
      </FR>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr 1fr', gap:12 }}>
        <FR label="Window Start"><FInput type="time" value={f.window_start} onChange={upd('window_start')} /></FR>
        <FR label="Window End"><FInput type="time" value={f.window_end} onChange={upd('window_end')} /></FR>
        <FR label="Interval (min)"><FInput type="number" value={f.interval_mins} onChange={upd('interval_mins')} min="1" placeholder="e.g. 30" /></FR>
        <FR label="Playlist"><FSelect value={f.playlist} onChange={upd('playlist')}>
          {PLAYLISTS.map(p=><option key={p} value={p}>{p}</option>)}
        </FSelect></FR>
      </div>
      <div style={{ marginTop:14, display:'flex', gap:8 }}>
        <Btn v="solid" onClick={()=>onSave(f)}>Save Rule</Btn>
        {onCancel&&<Btn v="ghost" onClick={onCancel}>Cancel</Btn>}
      </div>
    </div>
  );
}

function ScheduleTab() {
  const {T}=useTheme();
  const [view, setView]           = useState('Month');
  const [cursor, setCursor]       = useState(new Date());
  const [monthData, setMonthData] = useState([]);
  const [rules, setRules]         = useState([]);
  const [dayModal, setDayModal]   = useState(null);
  const [addModal, setAddModal]   = useState(null);
  const [editRule, setEditRule]   = useState(null);
  const [showRuleForm, setShowRuleForm] = useState(false);

  const weekStart = useMemo(()=>getSundayOf(cursor),[cursor]);

  const loadData = useCallback(async () => {
    const pairs = new Set();
    pairs.add(monthKey(cursor.getFullYear(), cursor.getMonth()+1));
    if (view==='Week') {
      const we=addDays(weekStart,6);
      pairs.add(monthKey(we.getFullYear(), we.getMonth()+1));
    }
    const results = await Promise.all([...pairs].map(k=>{
      const [y,m]=k.split('-'); return fetchMonth(parseInt(y),parseInt(m));
    }));
    const merged={};
    const allRules=[];
    results.forEach(md=>{
      (md.entries||[]).forEach(e=>{ if(!merged[e.date])merged[e.date]=[]; merged[e.date].push(e); });
      (md.rules||[]).forEach(r=>{ if(!allRules.find(x=>x.id===r.id))allRules.push(r); });
    });
    Object.keys(merged).forEach(dt=>merged[dt].sort((a,b)=>(a.time||'').localeCompare(b.time||'')));
    setMonthData(merged);
    setRules(allRules);
  }, [view, cursor, weekStart]);

  useEffect(()=>{ loadData(); }, [loadData]);

  const nav=dir=>{ const d=new Date(cursor);
    if(view==='Month')d.setMonth(d.getMonth()+dir);
    else if(view==='Week')d.setDate(d.getDate()+dir*7);
    else d.setDate(d.getDate()+dir);
    setCursor(d); };

  const title = view==='Month'
    ? `${MONTHS[cursor.getMonth()]} ${cursor.getFullYear()}`
    : view==='Week'
    ? (()=>{const e=addDays(weekStart,6);return `${fmtDate(weekStart)} – ${fmtDate(e)}`;})()
    : `${DAYS[cursor.getDay()]}, ${MONTHS[cursor.getMonth()]} ${cursor.getDate()}, ${cursor.getFullYear()}`;

  const goDay=d=>{setCursor(d);setView('Day');setDayModal(null);};

  const refresh=()=>{ invalidateCache(); loadData(); };

  const handleSaveRule=async f=>{
    const body={...f, days:f.days, interval_mins:f.interval_mins?parseInt(f.interval_mins):undefined, window_end:f.window_end||undefined};
    if(!body.window_end)delete body.window_end;
    if(!body.interval_mins)delete body.interval_mins;
    await fetch(`${AJAX}&action=save_rule`,{method:'POST',body:JSON.stringify(body)});
    setEditRule(null); setShowRuleForm(false); refresh();
  };

  const handleDeleteRule=async id=>{
    if(!confirm('Delete this repeating rule and all its generated shows?'))return;
    await fetch(`${AJAX}&action=delete_rule&id=${encodeURIComponent(id)}`);
    refresh();
  };

  const handleDeleteEntry=async id=>{
    if(!confirm('Remove this entry?'))return;
    await fetch(`${AJAX}&action=delete_entry&id=${encodeURIComponent(id)}`);
    refresh();
  };

  return (
    <div style={{ display:'flex', flexDirection:'column', gap:0 }}>
      {/* toolbar */}
      <div style={{ background:T.base, borderBottom:`1px solid ${T.border}`, padding:'10px 20px',
        display:'flex', alignItems:'center', gap:10, flexWrap:'wrap' }}>
        <Seg opts={['Month','Week','Day']} val={view} onChange={setView} />
        <Divider v />
        <Btn v="ghost" onClick={()=>nav(-1)} style={{ padding:'4px 10px', fontSize:16, lineHeight:1 }}>‹</Btn>
        <Mono size={13} color={T.text} style={{ minWidth:220, textAlign:'center', display:'block' }}>{title}</Mono>
        <Btn v="ghost" onClick={()=>nav(1)} style={{ padding:'4px 10px', fontSize:16, lineHeight:1 }}>›</Btn>
        <Btn v="ghost" small onClick={()=>setCursor(new Date())}>Today</Btn>
        <div style={{ flex:1 }} />
        <Btn v="solid" onClick={()=>setAddModal(view==='Day'?cursor:new Date())}>+ Add Show</Btn>
        <Btn v="danger" onClick={()=>setAddModal({blackout:true,date:view==='Day'?cursor:new Date()})}>Blackout Day</Btn>
      </div>

      {/* calendar */}
      <div style={{ background:T.bg, borderBottom:`1px solid ${T.border}` }}>
        {view==='Month'&&<MonthView year={cursor.getFullYear()} month={cursor.getMonth()} byDate={monthData} onDayClick={d=>{setDayModal(d);}} />}
        {view==='Week'&&<WeekView weekStart={weekStart} byDate={monthData} onDayClick={d=>{setDayModal(d);}} />}
        {view==='Day'&&<div style={{ padding:20 }}><DayView date={cursor} byDate={monthData} rules={rules}
          onAdd={()=>setAddModal(cursor)} onEditRule={id=>setEditRule(rules.find(r=>r.id===id))}
          onDeleteEntry={handleDeleteEntry} onDeleteRule={handleDeleteRule} /></div>}
      </div>

      {/* rules panel */}
      <div style={{ background:T.bg, maxHeight:320, overflow:'auto' }}>
        <div style={{ padding:'10px 20px', display:'flex', justifyContent:'space-between', alignItems:'center',
          borderBottom:`1px solid ${T.border}`, position:'sticky', top:0, background:T.bg, zIndex:2 }}>
          <div style={{ fontSize:13, fontWeight:600, color:T.sub }}>Repeating Rules</div>
          <Btn v="ghost" small onClick={()=>{setShowRuleForm(p=>!p);setEditRule(null);}}>{showRuleForm?'Cancel':'+ New Rule'}</Btn>
        </div>
        <div style={{ padding:'12px 20px', display:'flex', flexDirection:'column', gap:10 }}>
          {showRuleForm&&!editRule&&<RuleForm onSave={handleSaveRule} onCancel={()=>setShowRuleForm(false)} />}
          {editRule&&<RuleForm initial={editRule} onSave={handleSaveRule} onCancel={()=>setEditRule(null)} />}
          {rules.map(r=>(
            <div key={r.id} style={{ display:'flex', alignItems:'center', gap:12, padding:'10px 14px',
              borderRadius:8, background:T.card, border:`1px solid ${T.border}` }}>
              <Dot color={T.mint} size={6} />
              <div style={{ flex:1 }}>
                <div style={{ fontSize:13, fontWeight:600, color:T.text }}>{r.playlist||'—'}</div>
                <Mono size={11} color={T.sub}>{(r.days||[]).map(d=>DAYS[d]).join(', ')} · {r.window_start}{r.window_end?'–'+r.window_end:''}{r.interval_mins?' every '+r.interval_mins+'m':''}</Mono>
                <Mono size={11} color={T.mut}>{r.start_date} → {r.end_date}</Mono>
              </div>
              <Btn v="ghost" small onClick={()=>{setEditRule(r);setShowRuleForm(false);}}>Edit</Btn>
              <Btn v="danger" small onClick={()=>handleDeleteRule(r.id)}>Delete</Btn>
            </div>
          ))}
          {rules.length===0&&!showRuleForm&&<div style={{ fontSize:13, color:T.mut }}>No rules defined.</div>}
        </div>
      </div>

      {/* day modal */}
      {dayModal&&(
        <DayModalSheet date={dayModal} byDate={monthData} rules={rules}
          onClose={()=>setDayModal(null)} onGoDay={goDay}
          onAdd={()=>{setAddModal(dayModal);}}
          onEditRule={id=>{setEditRule(rules.find(r=>r.id===id));setDayModal(null);}}
          onDeleteEntry={async id=>{await handleDeleteEntry(id);setDayModal(null);}}
          onDeleteRule={async id=>{await handleDeleteRule(id);setDayModal(null);}} />
      )}

      {/* add modal */}
      {addModal&&<AddShowModal date={addModal?.blackout?addModal.date:addModal} blackout={!!addModal?.blackout}
        onClose={()=>setAddModal(null)} onSave={async body=>{
          await fetch(`${AJAX}&action=save_entry`,{method:'POST',body:JSON.stringify(body)});
          setAddModal(null); refresh();
        }} />}
    </div>
  );
}

function DayModalSheet({ date, byDate, rules, onClose, onGoDay, onAdd, onEditRule, onDeleteEntry, onDeleteRule }) {
  const {T}=useTheme();
  const ds=fmtDate(date);
  const ents=byDate[ds]||[];
  return (
    <ModalBox title={ds} onClose={onClose}>
      {ents.length===0&&<p style={{ color:T.mut, fontSize:13, marginBottom:12 }}>No entries.</p>}
      {ents.map((e,i)=>{
        const isRule=!!e.rule_id; const isBlk=e.type==='blackout';
        const c=isBlk?T.red:isRule?T.s1:T.mint;
        return (
          <div key={i} style={{ display:'flex', alignItems:'center', gap:10, padding:'10px 12px',
            borderRadius:8, background:T.raise, border:`1px solid ${T.border}`, marginBottom:8 }}>
            <div style={{ width:3, height:32, background:c, borderRadius:2 }} />
            <Mono size={13} color={c} style={{ minWidth:48 }}>{e.time||''}</Mono>
            <div style={{ flex:1, fontSize:13, fontWeight:600, color:T.text }}>{entryLabel(e)}</div>
            {isRule
              ? <><Btn v="ghost" small onClick={()=>onEditRule(e.rule_id)}>Edit Rule</Btn>
                   <Btn v="danger" small onClick={()=>onDeleteRule(e.rule_id)}>Del Rule</Btn></>
              : <Btn v="danger" small onClick={()=>onDeleteEntry(e.id)}>Remove</Btn>}
          </div>
        );
      })}
      <Divider />
      <div style={{ display:'flex', gap:8 }}>
        <Btn v="solid" onClick={onAdd}>+ Add Show</Btn>
        <Btn v="ghost" onClick={()=>onGoDay(date)}>Open Day View</Btn>
      </div>
    </ModalBox>
  );
}

function AddShowModal({ date, blackout, onClose, onSave }) {
  const {T}=useTheme();
  const [f,setF]=useState({ date:fmtDate(date), time:'19:00', playlist:PLAYLISTS[0]||'' });
  const upd=k=>e=>setF(p=>({...p,[k]:e.target.value}));
  return (
    <ModalBox title={blackout?'Blackout Day':'Add Show'} onClose={onClose} width={360}>
      <FR label="Date"><FInput type="date" value={f.date} onChange={upd('date')} /></FR>
      {!blackout&&<>
        <FR label="Time"><FInput type="time" value={f.time} onChange={upd('time')} /></FR>
        <FR label="Playlist"><FSelect value={f.playlist} onChange={upd('playlist')}>
          {PLAYLISTS.map(p=><option key={p} value={p}>{p}</option>)}
        </FSelect></FR>
      </>}
      <div style={{ display:'flex', gap:8, marginTop:18, justifyContent:'flex-end' }}>
        <Btn v="ghost" onClick={onClose}>Cancel</Btn>
        <Btn v="solid" accent={blackout?T.red:T.mint} onClick={()=>onSave(blackout?{date:f.date,type:'blackout'}:{date:f.date,type:'show',time:f.time,playlist:f.playlist})}>
          {blackout?'Mark Blackout':'Add Show'}
        </Btn>
      </div>
    </ModalBox>
  );
}

/* ══ ANNOUNCEMENTS TAB ═════════════════════════════════ */
function AnnouncementsTab() {
  const {T}=useTheme();
  const [cfg,setCfg]=useState(null);
  const [saved,setSaved]=useState(false);

  useEffect(()=>{
    fetch(`${AJAX}&action=get_announcements`).then(r=>r.json()).then(d=>{
      setCfg(d);
    }).catch(()=>{});
  },[]);

  if(!cfg) return <div style={{ padding:40, color:T.sub, textAlign:'center' }}>Loading…</div>;

  const upd=k=>e=>{ setSaved(false); setCfg(p=>({...p,[k]:e.target.value})); };
  const updN=k=>e=>{ setSaved(false); setCfg(p=>({...p,[k]:parseFloat(e.target.value)||0})); };
  const updCheck=k=>e=>{ setSaved(false); setCfg(p=>({...p,[k]:e.target.checked})); };

  const save=async()=>{
    await fetch(`${AJAX}&action=save_announcements`,{method:'POST',body:JSON.stringify(cfg)});
    setSaved(true);
  };

  const addPreRow=()=>setCfg(p=>({...p,pre_show:[...p.pre_show,{mins_before:5,file:''}]}));
  const delPreRow=i=>setCfg(p=>({...p,pre_show:p.pre_show.filter((_,j)=>j!==i)}));
  const updPre=(i,k)=>e=>setCfg(p=>{const a=[...p.pre_show];a[i]={...a[i],[k]:e.target.value};return {...p,pre_show:a};});

  return (
    <div style={{ padding:20, display:'flex', flexDirection:'column', gap:16, maxWidth:760 }}>
      <Card style={{ padding:'20px 22px' }}>
        <div style={{ fontSize:14, fontWeight:600, color:T.text, marginBottom:16 }}>Audio Ducking</div>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:14 }}>
          <FR label="Duck Level (0–1)" hint="Music volume during announcement"><FInput type="number" value={cfg.duck_level??0.25} onChange={updN('duck_level')} min="0" max="1" step="0.05" /></FR>
          <FR label="Fade Duration (sec)"><FInput type="number" value={cfg.duck_fade_secs??2} onChange={updN('duck_fade_secs')} min="0.5" max="10" step="0.5" /></FR>
          <FR label="Announcement Gain (dB)"><FInput type="number" value={cfg.gain_db??6} onChange={updN('gain_db')} min="0" max="24" step="1" /></FR>
          <FR label="Max Duration (sec)"><FInput type="number" value={cfg.max_duration_secs??300} onChange={updN('max_duration_secs')} min="10" max="3600" /></FR>
        </div>
      </Card>
      <Card style={{ padding:'20px 22px' }}>
        <div style={{ fontSize:14, fontWeight:600, color:T.text, marginBottom:16 }}>Lighting Brightness</div>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:14 }}>
          <FR label="Pre-show Brightness (0–200)"><FInput type="number" value={cfg.pre_show_brightness??20} onChange={updN('pre_show_brightness')} min="0" max="200" /></FR>
          <FR label="Normal Brightness (0–200)"><FInput type="number" value={cfg.normal_brightness??100} onChange={updN('normal_brightness')} min="0" max="200" /></FR>
        </div>
      </Card>
      <Card style={{ padding:'20px 22px' }}>
        <div style={{ fontSize:14, fontWeight:600, color:T.text, marginBottom:16 }}>Background Music</div>
        <FR label="Background Playlist">
          <FSelect value={cfg.background_playlist||''} onChange={upd('background_playlist')}>
            <option value="">— none —</option>
            {PLAYLISTS.map(p=><option key={p} value={p}>{p}</option>)}
          </FSelect>
        </FR>
      </Card>
      <Card style={{ padding:'20px 22px' }}>
        <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:14 }}>
          <div style={{ fontSize:14, fontWeight:600, color:T.text }}>Pre-Show Announcements</div>
          <Btn v="ghost" small onClick={addPreRow}>+ Add Row</Btn>
        </div>
        {(cfg.pre_show||[]).map((p,i)=>(
          <div key={i} style={{ display:'flex', gap:10, alignItems:'flex-end', marginBottom:10 }}>
            <FR label="Mins Before" style={{ flex:1, margin:0 }}><FInput type="number" value={p.mins_before} onChange={updPre(i,'mins_before')} min="1" max="120" /></FR>
            <FR label="Audio File" style={{ flex:3, margin:0 }}><FInput value={p.file} onChange={updPre(i,'file')} placeholder="filename.mp3" /></FR>
            <Btn v="danger" small onClick={()=>delPreRow(i)}>✕</Btn>
          </div>
        ))}
      </Card>
      <Card style={{ padding:'20px 22px' }}>
        <div style={{ fontSize:14, fontWeight:600, color:T.text, marginBottom:14 }}>Daytime Announcements</div>
        <div style={{ display:'flex', alignItems:'center', gap:10, marginBottom:14 }}>
          <input type="checkbox" checked={!!cfg.daytime_enabled} onChange={updCheck('daytime_enabled')} id="dt-en" />
          <label htmlFor="dt-en" style={{ fontSize:13, color:T.text }}>Enable daytime announcements</label>
        </div>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:14 }}>
          <FR label="Start Time"><FInput type="time" value={cfg.daytime_start||'10:00'} onChange={upd('daytime_start')} /></FR>
          <FR label="End Time"><FInput type="time" value={cfg.daytime_end||'18:00'} onChange={upd('daytime_end')} /></FR>
          <FR label="Interval (min)"><FInput type="number" value={cfg.daytime_interval||20} onChange={updN('daytime_interval')} min="5" max="240" /></FR>
        </div>
      </Card>
      <div style={{ display:'flex', alignItems:'center', gap:12 }}>
        <Btn v="solid" onClick={save} style={{ padding:'8px 24px' }}>Save Settings</Btn>
        {saved&&<><Dot color={T.mint} size={7}/><span style={{ fontSize:12, color:T.mint }}>Saved</span></>}
      </div>
    </div>
  );
}

/* ══ HARDWARE TAB ══════════════════════════════════════ */
function HardwareTab() {
  const {T}=useTheme();
  const [cfg,setCfg]=useState(null);
  const [saved,setSaved]=useState(false);

  useEffect(()=>{
    fetch(`${AJAX}&action=get_hardware`).then(r=>r.json()).then(d=>setCfg(d)).catch(()=>{});
  },[]);

  if(!cfg) return <div style={{ padding:40, color:T.sub, textAlign:'center' }}>Loading…</div>;

  const upd=k=>e=>{ setSaved(false); setCfg(p=>({...p,[k]:e.target.value})); };
  const save=async()=>{
    await fetch(`${AJAX}&action=save_hardware`,{method:'POST',body:JSON.stringify(cfg)});
    setSaved(true);
  };

  return (
    <div style={{ padding:20, maxWidth:660, display:'flex', flexDirection:'column', gap:14 }}>
      <div style={{ fontSize:13, color:T.sub, lineHeight:1.7, padding:'12px 16px',
        borderLeft:`3px solid ${T.mint}`, background:T.mintD, borderRadius:'0 8px 8px 0' }}>
        The bridge syncs FPP master volume to the two music channels on the XR18 via OSC (UDP 10024).
        Moving either music-channel fader on the XR18 also updates FPP volume.
      </div>
      <Card style={{ padding:'20px 22px' }}>
        <div style={{ fontSize:14, fontWeight:600, color:T.text, marginBottom:16 }}>XR18 Configuration</div>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:14 }}>
          <div style={{ gridColumn:'1/-1' }}>
            <FR label="XR18 IP Address" hint="IP of the XR18 — check X AIR Edit"><FInput value={cfg.xr18_ip||''} onChange={upd('xr18_ip')} /></FR>
          </div>
          <FR label="Music Channel 1" hint="Left/mono channel (01–18)"><FInput value={cfg.music_ch1||''} onChange={upd('music_ch1')} /></FR>
          <FR label="Music Channel 2" hint="Right channel (01–18)"><FInput value={cfg.music_ch2||''} onChange={upd('music_ch2')} /></FR>
          <FR label="Announcement Channel" hint="Must differ from music channels"><FInput value={cfg.announce_ch||''} onChange={upd('announce_ch')} /></FR>
          <FR label="Announcement Volume" hint="0.0 (off) – 1.0 (full). 0.75 ≈ unity"><FInput type="number" value={cfg.announce_vol||'0.75'} onChange={upd('announce_vol')} min="0" max="1" step="0.05" /></FR>
        </div>
        <div style={{ marginTop:18, display:'flex', gap:12, alignItems:'center' }}>
          <Btn v="solid" onClick={save} style={{ padding:'7px 22px' }}>Save Settings</Btn>
          {saved&&<><Dot color={T.mint} size={7}/><span style={{ fontSize:12, color:T.mint }}>Saved</span></>}
        </div>
      </Card>
    </div>
  );
}

/* ══ APP ROOT ══════════════════════════════════════════ */
function App() {
  const [isDark, setIsDark] = useState(()=>{
    try { return localStorage.getItem('sm-theme')!=='light'; } catch{ return true; }
  });
  const T = isDark ? DARK : LIGHT;
  const toggle = () => setIsDark(d=>{ const n=!d; try{localStorage.setItem('sm-theme',n?'dark':'light');}catch{}; return n; });

  const [tab, setTab] = useState('Status');
  const TABS = ['Status','Schedule','Announcements','Hardware'];

  return (
    <ThemeCtx.Provider value={{ T, isDark, toggle }}>
      <div style={{ background:T.bg, color:T.text, fontFamily:"'Outfit',sans-serif", minHeight:600 }}>
        {/* tab bar + theme toggle */}
        <div style={{ background:T.base, borderBottom:`1px solid ${T.border}`, display:'flex', alignItems:'flex-end', padding:'0 20px', gap:2 }}>
          {TABS.map(t=>(
            <button key={t} onClick={()=>setTab(t)} style={{ padding:'0 16px', height:40, background:'none',
              border:'none', borderBottom:tab===t?`2px solid ${T.mint}`:'2px solid transparent',
              color:tab===t?T.text:T.sub, fontFamily:'Outfit',
              fontWeight:tab===t?600:400, fontSize:13, cursor:'pointer', transition:'color .15s' }}>{t}</button>
          ))}
          <div style={{ flex:1 }} />
          <button onClick={toggle} title="Toggle light/dark"
            style={{ background:'none', border:'none', fontSize:18, cursor:'pointer',
              color:T.sub, padding:'0 4px', marginBottom:6, lineHeight:1 }}>
            {isDark ? '☀️' : '🌙'}
          </button>
        </div>

        {/* tab content */}
        {tab==='Status'        && <StatusTab />}
        {tab==='Schedule'      && <ScheduleTab />}
        {tab==='Announcements' && <AnnouncementsTab />}
        {tab==='Hardware'      && <HardwareTab />}
      </div>
    </ThemeCtx.Provider>
  );
}

ReactDOM.createRoot(document.getElementById('sm-app')).render(<App />);
</script>
