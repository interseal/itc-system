// ИТК Трейдинг · №375 · Демо-счёт работает как настоящий, только без денег.
// 1) Стоп, тейк, триггер и лимитные заявки демо-сделок проверяются по КАЖДОЙ сделке на бирже (та же цена, что на графике),
//    поэтому срабатывают и от короткого укола свечи. Раньше проверка шла раз в секунду по сводной цене и уколы пропускала.
// 2) Пока приложение закрыто или телефон заблокирован, демо-серия «как будто продолжает работать»: при открытии программа
//    проходит минутные свечи за пропущенное время и по тем же правилам, что и в живом режиме, закрывает сделки по стопу
//    и тейку и открывает следующие по сигналам стратегии — пока серия не наберёт заданное число сделок.
//    Время входа и выхода в истории — настоящее, когда это случилось на бирже.
"use strict";
(function(){
const isDemo=a=>a.kind!=='bn';
const demoBooks=()=>ACCS.filter(isDemo).map(a=>BOOK[a.id]);
const ACT=t=>t.status==='open'||t.status==='wait'||t.status==='trig';
const openT=()=>{const o=[];demoBooks().forEach(bk=>bk.trades.forEach(t=>{if(ACT(t))o.push([t,bk])}));return o};
const tfMs=tf=>{const m=/^(\d+)([mhdw])$/.exec(tf||'1h');return m?+m[1]*{m:60e3,h:3600e3,d:864e5,w:6048e5}[m[2]]:3600e3};
const isSer=b=>b&&b.series&&b.type==='strat';
let REPLAY=false;

// «часы» программы на время пересчёта прошлого: всё, что запишется (вход, выход, журнал), получит время с биржи
const _now=Date.now.bind(Date);let SIM=0;Date.now=()=>SIM||_now();
function quiet(fn){const ev=onEvent,sy=NOTE.sys;onEvent=()=>{};NOTE.sys=false;try{return fn()}finally{onEvent=ev;NOTE.sys=sy}}

// одна цена → только демо-сделки этой монеты на этом рынке
function tickDemo(mkt,sym,p){if(REPLAY)return;let ch=false;for(const [t,bk] of openT()){if(t.sym!==sym||t.mkt!==mkt)continue;if(tradeTick(t,p,bk))ch=true;t.chk=Date.now()}if(ch){save();engine.changed=true}}

// ---------- 1. живой поток сделок биржи ----------
const WS={fut:null,spot:null},KEY={fut:'',spot:''};
function streams(){for(const mkt of ['fut','spot']){const syms=[...new Set(openT().filter(([t])=>t.mkt===mkt).map(([t])=>t.sym))].sort(),k=syms.join(',');
  if(k===KEY[mkt]&&WS[mkt]&&WS[mkt].readyState<2)continue;KEY[mkt]=k;try{WS[mkt]&&WS[mkt].close()}catch(e){}WS[mkt]=null;if(!syms.length)continue;
  const ws=new WebSocket(WSU[mkt]+'stream?streams='+syms.map(s=>pair(s).toLowerCase()+'@aggTrade').join('/'));let last={};
  ws.onmessage=e=>{try{const m=JSON.parse(e.data).data;if(!m||!m.s)return;const s=m.s.slice(0,-4),p=+m.p;if(last[s]===p)return;last[s]=p;if(mkt==='fut')PX[s]=p;tickDemo(mkt,s,p)}catch(x){}};
  ws.onclose=()=>{if(WS[mkt]===ws){WS[mkt]=null;KEY[mkt]=''}};WS[mkt]=ws}}

// ---------- свечи с биржи ----------
async function kls(mkt,sym,tf,from,maxPages){const out=[],lim=mkt==='fut'?1500:1000,step=tfMs(tf);let st=from;
 for(let g=0;g<(maxPages||10);g++){const r=await fetch(API[mkt]+'klines?symbol='+pair(sym)+'&interval='+tf+'&startTime='+st+'&limit='+lim);if(!r.ok)throw new Error('биржа '+r.status);const a=await r.json();if(!a.length)break;
  a.forEach(k=>out.push({t:k[0],time:k[0]/1000,o:+k[1],h:+k[2],l:+k[3],c:+k[4],open:+k[1],high:+k[2],low:+k[3],close:+k[4],volume:+k[5]}));if(a.length<lim)break;st=a[a.length-1][0]+step}return out}
// путь цены внутри минутной свечи: открытие → ближний край → дальний край → закрытие
const path=c=>c.c>=c.o?[c.o,c.l,c.h,c.c]:[c.o,c.h,c.l,c.c];

// ---------- 2. серии на демо-счёте: проигрываем пропущенное время ----------
// время, до которого серия уже «прожита»
function serSince(b,bk){if(b.simT)return b.simT;let t=b.t;const tr=bk.trades.filter(x=>x.bot===b.id);
 if(tr.length)return Math.min(...tr.map(x=>x.chk||x.t||_now()));
 t=Math.max(t,b.series.lastOpen||0);(bk.hist||[]).forEach(h=>{if(h.bot===b.id)t=Math.max(t,h.t||0)});return t}
async function replaySeries(b,bk){
 const s=ITS.list.find(x=>x.id===b.strat);if(!s)return null;
 const now=_now(),TF=tfMs(b.tf),since=Math.max(serSince(b,bk),now-7*864e5);if(now-since<90e3)return null;
 const p0={};s.params.forEach(q=>p0[q.k]=b.prm&&b.prm[q.k]!=null?b.prm[q.k]:q.v);
 const K=await kls(b.mkt,b.sym,b.tf,Math.floor(since/TF)*TF-400*TF,20);
 const M=await kls(b.mkt,b.sym,'1m',Math.floor(since/60e3)*60e3,12);
 if(!K.length||!M.length)return null;
 // сигналы — ровно как в живом режиме: стратегия смотрит 399 закрытых свечей, сигнал должен быть на последней
 const SG=[];for(let i=0;i<K.length;i++){const at=K[i].t+TF;if(at>now||at<=since)continue;if(K[i].t<b.t)continue;
  const w=K.slice(Math.max(0,i-398),i+1);let sg;try{sg=s.run(w,p0)}catch(e){continue}const l=sg[sg.length-1];if(l&&l.i===w.length-1)SG.push({at,ct:K[i].time,t:l.t,px:K[i].close})}
 const pxNow=PX[b.sym];let opened=0,closed=0;const h0=(bk.hist||[]).length;
 REPLAY=true;try{quiet(()=>{let si=0;
  const mine=()=>bk.trades.filter(t=>t.bot===b.id);
  const signal=(g,px)=>{SIM=g.at;if(b.status!=='run'&&b.status!=='hold')return;if(mine().length)return;if(b.series.opened>=b.series.n)return;if(g.ct<=b.last)return;
   b.last=g.ct;if(g.t==='exit'){tlog(b,'Сигнал: выход');return}if(g.t==='short'&&b.mkt==='spot'){tlog(b,'Сигнал на продажу — на споте пропускаем');return}
   const side=g.t==='long'?1:-1;PX[b.sym]=px;const qty=b.amt*b.lev/px;
   const o={sym:b.sym,mkt:b.mkt,side,lev:b.lev,qty,entry:{type:'mkt'},trig:0,sl:{px:b.sl?px*(1-side*b.sl/100):0,trail:0,close:false,tf:b.tf},tps:b.tp?[{px:px*(1+side*b.tp/100),pct:100,hit:0}]:[],be:false,comment:'бот: '+s.name,bot:b.id};
   const st=b.status;b.status='run';const r=createTrade(o,bk);b.status=st;
   if(typeof r!=='string'){r.chk=g.at;opened++;tlog(b,'Сигнал «'+(side>0?'покупка':'продажа')+'» — открыта сделка')}else tlog(b,'Не открыл: '+r)};
  for(const c of M){
   while(si<SG.length&&SG[si].at<=c.t){signal(SG[si],SG[si].at===c.t?c.o:SG[si].px);si++}
   for(const t of mine()){if((t.t||0)>c.t+60e3)continue;const t0=t.chk||t.t||0;const P=c.t<t0-30e3?[c.c]:path(c);SIM=c.t+30e3;
    for(const p of P){if(!bk.trades.includes(t))break;if(tradeTick(t,p,bk)&&!bk.trades.includes(t))closed++}
    if(bk.trades.includes(t))t.chk=c.t+60e3}
   if(b.series.opened>=b.series.n&&!mine().length&&b.status!=='stop'){SIM=c.t+60e3;b.status='stop';b.tstop=SIM;b.series.done=SIM;tlog(b,'Серия завершена');break}}
  // сигналы после последней полной минуты — их подхватит живой режим
 })}finally{SIM=0;REPLAY=false;if(pxNow)PX[b.sym]=pxNow}
 b.simT=_now();bk.eq&&bk.eq.sort((x,y)=>x.t-y.t);(bk.hist||[]).sort((x,y)=>(y.t||0)-(x.t||0));
 if(opened||closed||(bk.hist||[]).length!==h0){save();engine.changed=true;return {sym:b.sym,opened,closed,done:b.status==='stop'}}
 save();return null}

// ---------- 3. остальные демо-сделки (не из серии): догоняем стоп и тейк ----------
async function catchTrades(force){
 const L=openT().filter(([t])=>{if(t.bot){for(const bk of demoBooks()){const b=bk.bots.find(x=>x.id===t.bot);if(isSer(b))return false}}const c=t.chk||t.t||_now();return force||_now()-c>70e3});
 for(const [t,bk] of L){const t0=t.chk||t.t||_now();let M;try{M=await kls(t.mkt,t.sym,'1m',Math.floor(t0/60e3)*60e3,12)}catch(e){continue}
  let ch=false;REPLAY=true;try{quiet(()=>{for(const c of M){if(!bk.trades.includes(t))break;const P=c.t<t0-30e3?[c.c]:path(c);SIM=c.t+30e3;
   for(const p of P){if(!bk.trades.includes(t))break;if(tradeTick(t,p,bk))ch=true}}})}finally{SIM=0;REPLAY=false}
  if(bk.trades.includes(t))t.chk=_now();if(ch){bk.eq&&bk.eq.sort((x,y)=>x.t-y.t);save();engine.changed=true}}}

// ---------- живой режим серии ждёт, пока прошлое не проиграно ----------
const _sb13=stratBots;stratBots=async function(){const held=[];
 for(const bk of demoBooks())for(const b of bk.bots)if(isSer(b)&&b.status==='run'&&(REPLAY||busy||document.visibilityState==='hidden'||_now()-serSince(b,bk)>90e3)){b.status='hold';held.push(b)}
 try{await _sb13()}finally{held.forEach(b=>{if(b.status==='hold')b.status='run'})}
 if(!REPLAY&&!busy&&document.visibilityState!=='hidden')for(const bk of demoBooks())for(const b of bk.bots)if(isSer(b)&&b.status==='run'&&!held.includes(b))b.simT=_now()};

let busy=false;
async function sync(force){if(busy)return;busy=true;const rep=[];try{
 for(const bk of demoBooks())for(const b of bk.bots.slice()){if(!isSer(b)||b.status!=='run')continue;try{const r=await replaySeries(b,bk);if(r)rep.push(r)}catch(e){}}
 await catchTrades(force);
 }finally{busy=false}
 streams();
 if(rep.length){const o=rep.reduce((s,r)=>s+r.opened,0),c=rep.reduce((s,r)=>s+r.closed,0);
  toast(`Пока приложение было закрыто, демо-серия ${rep.map(r=>r.sym).join(', ')}: открыто сделок ${o}, закрыто ${c}${rep.some(r=>r.done)?' · серия завершена':''}`);
  try{typeof serPaint==='function'&&serPaint()}catch(e){}if(UI.view==='term'&&typeof paintOrders==='function')try{paintOrders()}catch(e){}}}

// ---------- запуск ----------
setInterval(()=>{streams();if(document.visibilityState!=='hidden')sync(false)},15e3);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')sync(true)});
setTimeout(()=>sync(true),1200);
})();
