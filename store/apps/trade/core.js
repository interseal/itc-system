// Alex Trade — ядро: хранилище, счета, цены, демо-движок смарт-сделок и ботов
"use strict";
const LS={get(k,d){try{const v=localStorage.getItem('itct_'+k);return v?JSON.parse(v):d}catch(e){return d}},set(k,v){try{localStorage.setItem('itct_'+k,JSON.stringify(v))}catch(e){}},del(k){try{localStorage.removeItem('itct_'+k)}catch(e){}}};
let PAIRS=['BTC','ETH','SOL','BNB','XRP','DOGE','ADA','TON','AVAX','LINK','DOT','LTC','TRX','SUI','NEAR','APT','ARB','OP','ATOM','FIL'];
const PXV={};// объём и изменение за 24ч: {base:{ch,vol}}
const TFS=[['1m','1м'],['5m','5м'],['15m','15м'],['30m','30м'],['1h','1ч'],['4h','4ч'],['6h','6ч'],['1d','Д'],['1w','Н'],['1M','М']];
const TFG=[['Минуты',[['1m','1 минута'],['5m','5 минут'],['15m','15 минут'],['30m','30 минут']]],['Часы',[['1h','1 час'],['4h','4 часа'],['6h','6 часов']]],['Дни',[['1d','1 день'],['1w','1 неделя'],['1M','1 месяц']]]];
let TFQ=LS.get('tffav',['1m','1h','1d']);
const FEE={fut:0.0005,spot:0.001};
const API={fut:'https://fapi.binance.com/fapi/v1/',spot:'https://api.binance.com/api/v3/'};
const WSU={fut:'wss://fstream.binance.com/',spot:'wss://stream.binance.com:9443/'};
const pair=s=>s+'USDT';
const fmt=(v,d)=>v==null||isNaN(v)?'—':Number(v).toLocaleString('ru-RU',{minimumFractionDigits:d,maximumFractionDigits:d});
const pd=v=>{v=Math.abs(+v||0);return v>=1000?2:v>=10?2:v>=1?3:v>=0.1?4:5};
const fp=v=>fmt(v,pd(v));
const sgn=v=>{const r=Math.round(v*100)/100;return (r>0?'+':r<0?'':'')+fmt(r===0?0:v,2)};
const uid=()=>Date.now().toString(36)+Math.random().toString(36).slice(2,6);
async function klines(mkt,sym,tf,limit){const r=await fetch(API[mkt]+'klines?symbol='+pair(sym)+'&interval='+tf+'&limit='+(limit||600));if(!r.ok)throw new Error('биржа ответила '+r.status);const a=await r.json();return a.map(k=>({time:k[0]/1000,open:+k[1],high:+k[2],low:+k[3],close:+k[4],volume:+k[5]}))}
async function t24(mkt,sym){try{const r=await fetch(API[mkt]+'ticker/24hr?symbol='+pair(sym));return r.ok?r.json():null}catch(e){return null}}

// ---------- счета ----------
let ACCS=LS.get('accs',null);
if(!ACCS){ACCS=[{id:'d1',name:'Демо-счёт',kind:'demo'}];LS.set('accs',ACCS);const old=LS.get('demo',null);LS.set('acc_d1',old&&old.bal!=null?{bal:old.bal,trades:[],bots:[],hist:(old.hist||[]).map(h=>Object.assign({id:uid()},h)),eq:[]}:null)}
const newBook=()=>({bal:10000,trades:[],bots:[],hist:[],eq:[{t:Date.now(),v:10000}]});
const BOOK={};ACCS.forEach(a=>{BOOK[a.id]=LS.get('acc_'+a.id,null)||newBook()});
let curAcc=LS.get('cur','d1');if(!BOOK[curAcc])curAcc=ACCS[0].id;
const D=()=>BOOK[curAcc];
let saveT=null;const save=()=>{clearTimeout(saveT);saveT=setTimeout(()=>{ACCS.forEach(a=>LS.set('acc_'+a.id,BOOK[a.id]));LS.set('accs',ACCS);LS.set('cur',curAcc)},300)};
function addAcc(name){const id='d'+uid();ACCS.push({id,name:name||('Демо-счёт '+(ACCS.length+1)),kind:'demo'});BOOK[id]=newBook();curAcc=id;save();return id}
function delAcc(id){if(ACCS.length<2)return false;ACCS=ACCS.filter(a=>a.id!==id);delete BOOK[id];LS.del('acc_'+id);if(curAcc===id)curAcc=ACCS[0].id;save();return true}
function resetAcc(id){BOOK[id]=newBook();save()}

// ---------- цены ----------
const PX={};const PXH=[];let pxWs=null,pxWs2=null;
function onTick(a,fut){const seen=new Set();for(const m of a){if(!m.s.endsWith('USDT'))continue;const s=m.s.slice(0,-4);if(!fut&&PX[s]!=null&&PXV[s]&&PXV[s].fut)continue;PX[s]=+m.c;const o=+m.o;PXV[s]={ch:o?(+m.c-o)/o*100:0,vol:+m.q,fut:fut||(PXV[s]&&PXV[s].fut)};seen.add(s)}
 const act=new Set();ACCS.forEach(x=>{BOOK[x.id].trades.forEach(t=>act.add(t.sym));BOOK[x.id].bots.forEach(b=>b.status==='run'&&act.add(b.sym))});seen.forEach(s=>{if(act.has(s))engine(s,PX[s])});PXH.forEach(f=>f())}
function startPrices(){try{pxWs&&pxWs.close();pxWs2&&pxWs2.close()}catch(e){}pxWs=new WebSocket(WSU.fut+'ws/!miniTicker@arr');pxWs.onmessage=e=>onTick(JSON.parse(e.data),true);pxWs.onclose=()=>setTimeout(startPrices,4000);
 pxWs2=new WebSocket(WSU.spot+'ws/!miniTicker@arr');pxWs2.onmessage=e=>onTick(JSON.parse(e.data),false)}
// полный список пар биржи (как в Capico): все пары к USDT на споте и фьючерсах
const PL={fut:null,spot:null};
async function loadPairs(mkt){if(PL[mkt]&&Date.now()-PL[mkt].t<120e3)return PL[mkt].list;const r=await fetch(API[mkt]+'ticker/24hr');const a=await r.json();const list=a.filter(x=>x.symbol.endsWith('USDT')&&+x.quoteVolume>0).map(x=>({s:x.symbol.slice(0,-4),p:+x.lastPrice,ch:+x.priceChangePercent,vol:+x.quoteVolume}));PL[mkt]={t:Date.now(),list};list.forEach(x=>{if(PX[x.s]==null)PX[x.s]=x.p});return list}
// ---------- события и уведомления ----------
const NOTE=Object.assign({sig:true,fill:true,sl:true,tp:true,trig:true,bot:true,sys:false},LS.get('note',{}));
let onEvent=()=>{};
function event(kind,text){const a=ACCS.find(x=>x.id===curAcc);onEvent(kind,text);if(NOTE[kind]&&NOTE.sys){try{if(Notification.permission==='granted')new Notification('Alex Trade',{body:text})}catch(e){}}}

// ---------- смарт-сделки ----------
// t = {id,sym,mkt,side,lev,qty,entry:{type:'mkt'|'lim'|'ladder',px,px2,n},trig,trigDir,sl:{px,trail,close,tf},tps:[{px,pct,hit}],be,comment,
//      status:'trig'|'wait'|'open'|'done'|'cancel',legs:[{px,qty,f}],fq,ep,rq,real,fees,peak,t,log:[]}
function legsOf(e,qty,side){if(e.type==='ladder'){const n=Math.max(2,Math.min(20,e.n|0)),a=+e.px,b=+e.px2,o=[];for(let i=0;i<n;i++)o.push({px:a+(b-a)*i/(n-1),qty:qty/n,f:0});return o}return [{px:e.type==='mkt'?0:+e.px,qty,f:0}]}
const tlog=(t,s)=>{t.log.push({t:Date.now(),s});if(t.log.length>40)t.log.shift()};
function reserveNeed(t){return t.legs.filter(l=>!l.f).reduce((s,l)=>s+l.qty*(l.px||PX[t.sym]||0),0)/t.lev}
function createTrade(o,bk){bk=bk||D();const p=PX[o.sym];if(!p)return 'Нет текущей цены — подождите пару секунд';
 const t=Object.assign({id:uid(),status:'wait',fq:0,ep:0,rq:0,real:0,fees:0,peak:p,t:Date.now(),log:[],tps:[],sl:{px:0,trail:0,close:false,tf:'1h'},be:false,comment:''},JSON.parse(JSON.stringify(o)));
 t.legs=legsOf(t.entry,t.qty,t.side);if(t.mkt==='spot'){t.lev=1;if(t.side<0)return 'На споте можно только покупать: продажа — это закрытие купленного'}
 if(t.trig){t.trigDir=t.trig>=p?1:-1;t.status='trig';tlog(t,'Ждёт триггер '+fp(t.trig))}
 else{const need=reserveNeed(t);if(need>bk.bal+1e-9)return 'Недостаточно средств: нужно '+fmt(need,2)+' USDT';bk.bal-=need;tlog(t,'Выставлена')}
 bk.trades.unshift(t);if(t.status!=='trig'&&t.entry.type==='mkt')fillLeg(t,t.legs[0],p,bk);save();return t}
function fillLeg(t,l,px,bk){if(l.f)return;if(!l.px){/* рыночная: резерв считали по текущей цене */}l.f=1;const fee=l.qty*px*FEE[t.mkt];bk.bal-=fee+(l.qty*px-l.qty*(l.px||px))/t.lev;
 t.ep=(t.ep*t.fq+px*l.qty)/(t.fq+l.qty);t.fq+=l.qty;t.rq+=l.qty;t.fees+=fee;t.status='open';t.peak=px;tlog(t,'Вход '+fmt(l.qty,4)+' по '+fp(px));event('fill',`${t.sym}: вход ${t.side>0?'в покупку':'в продажу'} по ${fp(px)}`)}
function closePart(t,q,px,why,bk){q=Math.min(q,t.rq);if(q<=0)return;const pn=(px-t.ep)*q*t.side,fee=q*px*FEE[t.mkt];bk.bal+=q*t.ep/t.lev+pn-fee;t.rq-=q;t.real+=pn;t.fees+=fee;tlog(t,why+': закрыто '+fmt(q,4)+' по '+fp(px)+' ('+sgn(pn-fee)+' USDT)');
 if(t.rq<=1e-12){finish(t,why,bk)}}
function releaseLegs(t,bk){t.legs.forEach(l=>{if(!l.f){bk.bal+=l.qty*(l.px||PX[t.sym]||0)/t.lev;l.f=-1}})}
function finish(t,why,bk){releaseLegs(t,bk);t.status=t.fq?'done':'cancel';t.tclose=Date.now();t.why=why;
 if(t.fq){const net=t.real-t.fees;bk.hist.unshift({id:t.id,sym:t.sym,mkt:t.mkt,side:t.side,qty:t.fq,ep:t.ep,xp:t.ep+t.real/(t.fq*t.side),pnl:net,why,t:t.tclose,t0:t.t,comment:t.comment,bot:t.bot||''});bk.hist=bk.hist.slice(0,1000);snapEq(bk,true)}
 bk.trades=bk.trades.filter(x=>x.id!==t.id);if(t.fq)(bk.done=bk.done||[]).unshift(t);bk.done&&(bk.done=bk.done.slice(0,200))}
function closeTrade(id,why,bk){bk=bk||D();const t=bk.trades.find(x=>x.id===id);if(!t)return;if(t.rq>0)closePart(t,t.rq,PX[t.sym]||t.ep,why||'вручную',bk);else finish(t,why||'отменена',bk);save()}
function tradeTick(t,p,bk){
 if(t.status==='trig'){if((t.trigDir>0&&p>=t.trig)||(t.trigDir<0&&p<=t.trig)){const need=reserveNeed(t);if(need>bk.bal){tlog(t,'Триггер сработал, но не хватило средств');finish(t,'не хватило средств',bk);event('trig',`${t.sym}: триггер сработал, но средств не хватило`);return true}
   bk.bal-=need;t.status='wait';tlog(t,'Сработал триггер '+fp(t.trig));event('trig',`${t.sym}: сработал триггер ${fp(t.trig)}`);if(t.entry.type==='mkt')fillLeg(t,t.legs[0],p,bk);return true}return false}
 let ch=false;
 for(const l of t.legs){if(l.f||!l.px)continue;if((t.side>0&&p<=l.px)||(t.side<0&&p>=l.px)){fillLeg(t,l,l.px,bk);ch=true}}
 if(t.status!=='open')return ch;
 if(t.side>0)t.peak=Math.max(t.peak,p);else t.peak=Math.min(t.peak,p);
 if(t.sl.trail>0){const n=t.side>0?t.peak*(1-t.sl.trail/100):t.peak*(1+t.sl.trail/100);if(!t.sl.px||(t.side>0?n>t.sl.px:n<t.sl.px)){t.sl.px=n;ch=true}}
 if(t.sl.px&&!t.sl.close&&((t.side>0&&p<=t.sl.px)||(t.side<0&&p>=t.sl.px))){const w=t.sl.trail?'трейлинг-стоп':t.slBE?'безубыток':'стоп-лосс';event('sl',`${t.sym}: сработал ${w} ${fp(t.sl.px)}`);closePart(t,t.rq,t.sl.px,w,bk);return true}
 if(t.tpTr){if(t.side>0)t.tpTr.pk=Math.max(t.tpTr.pk,p);else t.tpTr.pk=Math.min(t.tpTr.pk,p);if((t.side>0&&p<=t.tpTr.pk*(1-t.tpTrail/100))||(t.side<0&&p>=t.tpTr.pk*(1+t.tpTrail/100))){event('tp',`${t.sym}: трейлинг тейк закрыл позицию по ${fp(p)}`);closePart(t,t.rq,p,'трейлинг тейк',bk);return true}return ch}
 for(const tp of t.tps){if(tp.hit||!tp.px)continue;if((t.side>0&&p>=tp.px)||(t.side<0&&p<=tp.px)){tp.hit=1;const last=t.tps.every(x=>x.hit||!x.px);if(last&&t.tpTrail>0){t.tpTr={pk:p};tlog(t,'Последняя цель достигнута — тейк идёт за ценой ('+t.tpTrail+'%)');event('tp',`${t.sym}: цель ${fp(tp.px)} достигнута, включён трейлинг тейк`);return true}const q=last?t.rq:Math.min(t.rq,t.fq*tp.pct/100);event('tp',`${t.sym}: тейк-профит ${fp(tp.px)}`);closePart(t,q,tp.px,'тейк-профит',bk);
   if(t.be&&t.rq>0&&!t.slBE){t.sl.px=t.ep*(1+t.side*2*FEE[t.mkt]);t.slBE=1;tlog(t,'Стоп перенесён в безубыток')}
   if(t.rq>0&&t.legs.some(l=>!l.f)){releaseLegs(t,bk)}ch=true;if(t.status!=='open')return true}}
 return ch}
// стоп по закрытию свечи — проверка раз в 30 секунд
async function candleStops(){for(const a of ACCS){const bk=BOOK[a.id];for(const t of bk.trades.filter(x=>x.status==='open'&&x.sl.close&&x.sl.px)){try{const k=await klines(t.mkt,t.sym,t.sl.tf||'1h',3);const c=k[k.length-2];if(c.time*1000<t.t)continue;if((t.side>0&&c.close<=t.sl.px)||(t.side<0&&c.close>=t.sl.px)){event('sl',`${t.sym}: свеча закрылась за стопом — позиция закрыта`);closePart(t,t.rq,PX[t.sym]||c.close,'стоп по закрытию свечи',bk);save()}}catch(e){}}}}

// ---------- боты ----------
// сетка: {id,type:'grid',sym,lo,hi,n,inv,lv:[{px,st:'b'|'s'|''}],q,cash,coin,profit,cnt,status,t,sl}
function botValue(b){const p=PX[b.sym]||0;if(b.type==='grid'||b.type==='chan')return b.cash+b.coin*p;return 0}
function createGrid(o,bk){bk=bk||D();const p=PX[o.sym];if(!p)return 'Нет текущей цены';const lo=+o.lo,hi=+o.hi,n=Math.max(2,Math.min(100,o.n|0)),inv=+o.inv;
 if(!(hi>lo))return 'Верхняя граница должна быть выше нижней';if(p<=lo||p>=hi)return 'Текущая цена должна быть внутри диапазона';if(!(inv>=10))return 'Минимальная сумма — 10 USDT';if(inv>bk.bal)return 'Недостаточно средств';
 const step=(hi-lo)/n,lv=[];for(let i=0;i<=n;i++)lv.push({px:lo+step*i,st:''});const q=inv/n/((lo+hi)/2);
 const b={id:uid(),type:'grid',sym:o.sym,lo,hi,n,inv,lv,q,cash:inv,coin:0,profit:0,cnt:0,status:'run',t:Date.now(),sl:+o.sl||0,log:[]};
 let up=0;lv.forEach((l,i)=>{if(l.px<p-step*0.01)l.st='b';else if(l.px>p+step*0.01){l.st='s';up++}});
 const buy=q*up;b.coin=buy;b.cash-=buy*p*(1+FEE.spot);bk.bal-=inv;bk.bots.unshift(b);tlog(b,'Запущен: '+n+' уровней, куплено '+fmt(buy,5)+' '+o.sym);save();event('bot',`Сеточный бот ${o.sym} запущен`);return b}
function gridTick(b,p,bk){let ch=false;for(let i=0;i<b.lv.length;i++){const l=b.lv[i];
  if(l.st==='b'&&p<=l.px&&b.cash>=b.q*l.px){b.cash-=b.q*l.px*(1+FEE.spot);b.coin+=b.q;l.st='';if(b.lv[i+1])b.lv[i+1].st='s';ch=true;tlog(b,'Купил по '+fp(l.px))}
  else if(l.st==='s'&&p>=l.px&&b.coin>=b.q*0.999){b.cash+=b.q*l.px*(1-FEE.spot);b.coin-=b.q;l.st='';if(b.lv[i-1])b.lv[i-1].st='b';const step=(b.hi-b.lo)/b.n;b.profit+=b.q*step-b.q*l.px*2*FEE.spot;b.cnt++;ch=true;tlog(b,'Продал по '+fp(l.px)+', сетка +'+fmt(b.q*step,2))}}
 if(b.sl&&p<=b.sl){stopBot(b.id,'стоп-лосс бота',bk);event('bot',`Сеточный бот ${b.sym} остановлен по стоп-лоссу`);return true}
 return ch}
// канал: покупка у нижней границы, продажа у верхней
function createChan(o,bk){bk=bk||D();const p=PX[o.sym];if(!p)return 'Нет текущей цены';const lo=+o.lo,hi=+o.hi,amt=+o.amt;if(!(hi>lo))return 'Верхняя граница должна быть выше нижней';if(!(amt>=10))return 'Минимальная сумма — 10 USDT';if(amt>bk.bal)return 'Недостаточно средств';
 const b={id:uid(),type:'chan',sym:o.sym,lo,hi,amt,sl:+o.sl||0,cash:amt,coin:0,ep:0,profit:0,cnt:0,state:'wait',status:'run',t:Date.now(),log:[]};bk.bal-=amt;bk.bots.unshift(b);tlog(b,'Запущен: канал '+fp(lo)+' — '+fp(hi));save();event('bot',`Канальный бот ${o.sym} запущен`);return b}
function chanTick(b,p,bk){if(b.state==='wait'&&p<=b.lo){b.coin=b.cash*(1-FEE.spot)/p;b.ep=p;b.cash=0;b.state='in';tlog(b,'Купил у нижней границы по '+fp(p));event('bot',`Канальный бот ${b.sym}: покупка ${fp(p)}`);return true}
 if(b.state==='in'){const stop=b.sl?b.lo*(1-b.sl/100):0;if(p>=b.hi||(stop&&p<=stop)){const v=b.coin*p*(1-FEE.spot),pr=v-b.coin*b.ep;b.cash=v;b.coin=0;b.profit+=pr;b.cnt++;b.state='wait';tlog(b,(p>=b.hi?'Продал у верхней границы':'Стоп')+' по '+fp(p)+' ('+sgn(pr)+')');event('bot',`Канальный бот ${b.sym}: продажа ${fp(p)} (${sgn(pr)} USDT)`);return true}}return false}
// бот по стратегии: открывает смарт-сделки по сигналам на закрытии свечи
function createStratBot(o,bk){bk=bk||D();if(!(+o.amt>=10))return 'Минимальная сумма — 10 USDT';const b={id:uid(),type:'strat',sym:o.sym,mkt:o.mkt,tf:o.tf,strat:o.strat,prm:o.prm||{},amt:+o.amt,lev:o.mkt==='fut'?(+o.lev||1):1,sl:+o.sl||0,tp:+o.tp||0,status:'run',t:Date.now(),last:0,profit:0,cnt:0,log:[]};bk.bots.unshift(b);tlog(b,'Запущен');save();event('bot',`Бот «${(ITS.list.find(s=>s.id===o.strat)||{}).name}» по ${o.sym} запущен`);return b}
async function stratBots(){for(const a of ACCS){const bk=BOOK[a.id];for(const b of bk.bots.filter(x=>x.type==='strat'&&x.status==='run')){try{const s=ITS.list.find(x=>x.id===b.strat);if(!s)continue;const k=(await klines(b.mkt,b.sym,b.tf,400)).slice(0,-1),p0={};s.params.forEach(q=>p0[q.k]=b.prm[q.k]!=null?b.prm[q.k]:q.v);const sg=s.run(k,p0),l=sg[sg.length-1];if(!l||l.i!==k.length-1)continue;const ct=k[l.i].time;if(ct<=b.last||ct*1000<b.t)continue;b.last=ct;
  const mine=bk.trades.filter(t=>t.bot===b.id);
  for(const t of mine){if(l.t==='exit'||(l.t==='long'&&t.side<0)||(l.t==='short'&&t.side>0)){const r=t.rq?((PX[t.sym]||t.ep)-t.ep)*t.rq*t.side:0;b.profit+=r;b.cnt++;closeTrade(t.id,'сигнал бота',bk)}}
  if(l.t==='exit'){tlog(b,'Сигнал: выход');continue}if(l.t==='short'&&b.mkt==='spot'){tlog(b,'Сигнал на продажу — на споте пропускаем');continue}
  const side=l.t==='long'?1:-1;if(mine.some(t=>t.side===side&&bk.trades.includes(t)))continue;const p=PX[b.sym];if(!p)continue;const qty=b.amt*b.lev/p;
  const o={sym:b.sym,mkt:b.mkt,side,lev:b.lev,qty,entry:{type:'mkt'},trig:0,sl:{px:b.sl?p*(1-side*b.sl/100):0,trail:0,close:false,tf:b.tf},tps:b.tp?[{px:p*(1+side*b.tp/100),pct:100,hit:0}]:[],be:false,comment:'бот: '+s.name,bot:b.id};
  const r=createTrade(o,bk);tlog(b,typeof r==='string'?'Не открыл: '+r:'Сигнал «'+(side>0?'покупка':'продажа')+'» — открыта сделка');event('bot',`Бот ${b.sym}: сигнал «${side>0?'покупка':'продажа'}»`);save()}catch(e){}}}}
function stopBot(id,why,bk){bk=bk||D();const b=bk.bots.find(x=>x.id===id);if(!b||b.status!=='run')return;const p=PX[b.sym]||0;
 if(b.type==='grid'||b.type==='chan'){const v=b.cash+b.coin*p*(1-FEE.spot);bk.bal+=v;const pnl=v-(b.inv||b.amt);bk.hist.unshift({id:b.id,sym:b.sym,mkt:'spot',side:1,qty:0,ep:0,xp:p,pnl,why:why||'бот остановлен',t:Date.now(),t0:b.t,comment:b.type==='grid'?'сеточный бот':'канальный бот',bot:b.id});b.cash=0;b.coin=0;b.final=pnl;snapEq(bk,true)}
 else{bk.trades.filter(t=>t.bot===b.id).forEach(t=>closeTrade(t.id,'бот остановлен',bk))}
 b.status='stop';b.tstop=Date.now();tlog(b,'Остановлен'+(why?': '+why:''));save()}

// ---------- общий такт по цене ----------
function engine(sym,p){for(const a of ACCS){const bk=BOOK[a.id];let ch=false;
  for(const t of bk.trades.slice())if(t.sym===sym&&tradeTick(t,p,bk))ch=true;
  for(const b of bk.bots)if(b.sym===sym&&b.status==='run'){if(b.type==='grid'&&gridTick(b,p,bk))ch=true;else if(b.type==='chan'&&chanTick(b,p,bk))ch=true}
  if(ch){save();engine.changed=true}}}
// ---------- капитал ----------
function upnl(t){const p=PX[t.sym]||t.ep;return t.rq?(p-t.ep)*t.rq*t.side:0}
function equity(bk){bk=bk||D();let e=bk.bal;for(const t of bk.trades){e+=t.rq*t.ep/t.lev+upnl(t);t.legs.forEach(l=>{if(!l.f&&t.status!=='trig')e+=l.qty*(l.px||PX[t.sym]||0)/t.lev})}for(const b of bk.bots)if(b.status==='run')e+=botValue(b);return e}
function snapEq(bk,force){const e=equity(bk),L=bk.eq[bk.eq.length-1],n=Date.now();if(force||!L||n-L.t>15*60e3){bk.eq.push({t:n,v:+e.toFixed(2)});if(bk.eq.length>3000)bk.eq.splice(0,bk.eq.length-3000)}}
setInterval(()=>{ACCS.forEach(a=>snapEq(BOOK[a.id]));save()},60e3);
setInterval(()=>{candleStops();stratBots()},30e3);
