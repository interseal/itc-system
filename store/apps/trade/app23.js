// ИТК Трейдинг — правило «Правила стратегии» (10.11.2026).
// У каждой стратегии свой стоп и свой выход — как их ставят авторы и учебники, а не один процент на всё:
// Супертренд — стоп по линии Супертренда (подтягивается за ценой); Параболик — стоп по точкам SAR; «черепахи» — стоп 2 ATR, выход по короткому каналу;
// Ишимоку — стоп за линией базы (Киджун); Боллинджер — стоп за экстремумом сигнальной свечи, тейк у средней линии; внутренний бар — стоп за материнской свечой, тейк 2 к 1;
// пробои и тренд по ADX — скользящий стоп 3 ATR от лучшей цены (выход «по люстре»); фигуры и уровни — стоп за фигурой, тейк у следующего уровня и т. д.
"use strict";
(function(){
const {ema,atr}=ITS.ind;
const lowN=(c,i,n)=>{let l=1e18;for(let j=Math.max(0,i-n+1);j<=i;j++)l=Math.min(l,c[j].low);return l},highN=(c,i,n)=>{let h=-1e18;for(let j=Math.max(0,i-n+1);j<=i;j++)h=Math.max(h,c[j].high);return h};
function sarLine(c){const s=0.02,mx=0.2,n=c.length,o=Array(n).fill(null);if(n<3)return o;let up=c[1].close>=c[0].close,sar=up?c[0].low:c[0].high,ep=up?c[1].high:c[1].low,af=s;
 for(let i=2;i<n;i++){sar=sar+af*(ep-sar);if(up){sar=Math.min(sar,c[i-1].low,c[i-2].low);if(c[i].low<sar){up=false;sar=ep;ep=c[i].low;af=s}else if(c[i].high>ep){ep=c[i].high;af=Math.min(mx,af+s)}}
  else{sar=Math.max(sar,c[i-1].high,c[i-2].high);if(c[i].high>sar){up=true;sar=ep;ep=c[i].high;af=s}else if(c[i].low<ep){ep=c[i].low;af=Math.min(mx,af+s)}}o[i]=sar}return o}
const midN=(c,i,n)=>i<n-1?null:(highN(c,i,n)+lowN(c,i,n))/2;
// stop: 'swing' N свечей | 'atr' K·ATR | 'mother' материнская свеча | 'bar' сигнальная свеча | 'lvl' за фигурой
// take: 'sig' — по обратному сигналу / сигналу «выход» | 'R' — R × риск | 'lvl' — следующий уровень
// trail: 'line' — линия индикатора | 'chand' — 3 ATR от лучшей цены после входа
const RU={
 ema:{stop:'swing',n:10,take:'sig',txt:'Стоп за ближайшим минимумом (для лонга) или максимумом (для шорта) последних 10 свечей. Выход — по обратному пересечению средних.'},
 st:{stop:'line',take:'sig',trail:'line',txt:'Стоп по линии Супертренда — она сама подтягивается за ценой. Выход — когда цена закрылась по другую сторону линии.'},
 rsi:{stop:'swing',n:10,take:'sig',txt:'Стоп за минимумом/максимумом последних 10 свечей. Выход — RSI вернулся к середине.'},
 don:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR от входа, как у «черепах» Ричарда Денниса. Выход — пробой короткого канала (10 свечей) в обратную сторону.'},
 macd:{stop:'swing',n:10,take:'sig',txt:'Стоп за минимумом/максимумом последних 10 свечей. Выход — обратное пересечение MACD и сигнальной линии.'},
 bb:{stop:'bar',take:'sig',txt:'Стоп за экстремумом сигнальной свечи (тень за полосой) с запасом. Выход — у средней линии.'},
 pat:{stop:'lvl',take:'lvl',txt:'Стоп за фигурой (за правым плечом, за нижним донышком). Тейк — у следующего уровня.'},
 lvl:{stop:'lvl',take:'lvl',txt:'Стоп за уровнем, от которого был отскок. Тейк — у следующего уровня.'},
 stoch:{stop:'swing',n:10,take:'R',R:2,txt:'Стоп за минимумом/максимумом последних 10 свечей. Тейк в 2 раза дальше стопа.'},
 ichi:{stop:'line',take:'sig',trail:'line',txt:'Стоп за линией базы (Киджун) — подтягивается вместе с ней. Выход — обратное пересечение линий.'},
 psar:{stop:'line',take:'sig',trail:'line',txt:'Стоп по точкам Параболика — они сами подтягиваются за ценой. Выход — переворот точек.'},
 adx:{stop:'atr',k:3,take:'sig',trail:'chand',txt:'Скользящий стоп 3 ATR от лучшей цены после входа (выход «по люстре»). Также выход по обратному сигналу.'},
 sqz:{stop:'atr',k:3,take:'none',trail:'chand',txt:'Скользящий стоп 3 ATR от лучшей цены — даёт движению после сжатия развернуться.'},
 rsi2:{stop:'atr',k:3,take:'sig',txt:'У Коннорса стопа нет, выход — цена вернулась за среднюю 5 свечей. У нас стоп обязателен: 3 ATR от входа, как защита от обвала.'},
 pull:{stop:'swing',n:10,take:'lvl',txt:'Стоп за минимумом отката (10 свечей). Тейк — у прошлого максимума (следующего уровня).'},
 cci:{stop:'swing',n:10,take:'R',R:2,txt:'Стоп за минимумом/максимумом последних 10 свечей. Тейк в 2 раза дальше стопа.'},
 ha:{stop:'swing',n:5,take:'sig',txt:'Стоп за минимумом/максимумом последних 5 свечей. Выход — смена цвета свечей Хейкен-Аши.'},
 inside:{stop:'mother',take:'R',R:2,txt:'Стоп за противоположным краем материнской свечи. Тейк в 2 раза дальше стопа.'},
 volbo:{stop:'bar',take:'none',trail:'chand',txt:'Стоп за свечой пробоя, дальше — скользящий стоп 3 ATR от лучшей цены.'},
 mtf:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR от входа. Выход — цена вернулась за среднюю 5 свечей.'},
 vwapm:{stop:'swing',n:5,take:'sig',txt:'Стоп за минимумом/максимумом последних 5 свечей. Выход — обратное пересечение MACD.'},
 kelt:{stop:'swing',n:5,take:'sig',txt:'Стоп за минимумом/максимумом последних 5 свечей. Выход — возврат цены к средней линии канала.'},
 rsibb:{stop:'swing',n:5,take:'sig',txt:'Стоп за ближайшим экстремумом 5 свечей. Выход — противоположная полоса или RSI(4) в противоположной зоне.'},
 sfp:{stop:'bar',take:'R',R:2,txt:'Стоп за тенью прокола. Тейк в 2 раза дальше стопа.'},
 mpull:{stop:'swing',n:5,take:'sig',txt:'Стоп за минимумом отката (5 свечей). Выход — цена закрылась за средней 50.'},
 orb:{stop:'swing',n:4,take:'sig',txt:'Стоп за минимумом/максимумом последних 4 свечей (за диапазоном). Выход — через заданное число часов после открытия сессии.'}};
ITS.rules=RU;
function lines(s,c,p,rule){if(rule.stop!=='line')return null;
 if(s.id==='st'&&s.calc){const x=s.calc(c,p);return {L:x.up,S:x.dn}}
 if(s.id==='psar'){const v=sarLine(c);return {L:v,S:v}}
 if(s.id==='ichi'){const k=p.k|0||26,v=c.map((_,i)=>midN(c,i,k));return {L:v,S:v}}return null}
function rlRun(c,s,sig,from,spot,F,cap){const p={};s.params.forEach(q=>p[q.k]=q.v);const R=RU[s.id]||{stop:'swing',n:10,take:'R',R:2},A=atr(c,14),Ln=lines(s,c,p,R),n=c.length;
 const lv=(R.stop==='lvl'||R.take==='lvl')?lvHelp(c):null;
 let eq=1,pk=1,dd=0,k=0,o=null,tpN=0,st=0,sg=0,skip=0;const P=[],SL=[];
 let CI=0;const close=(px,why)=>{const g=o.side>0?px/o.ep:2-px/o.ep/* №482 · шорт: от цены входа */,net=g*(1-F)*(1-F);eq*=net;pk=Math.max(pk,eq);dd=Math.max(dd,(1-eq/pk)*100);P.push((net-1)*100);if(cap)cap.push({t0:c[o.i0].time,t1:c[CI].time,p:(net-1)*100});SL.push(o.S*100);if(why==='st')st++;else if(why==='tp')tpN++;else sg++;o=null};
 function open(i,d,sgx){const ep=c[i].close,a=A[i]||ep*0.01;let stop,tp=null;
  if(R.stop==='sig')stop=sgx&&sgx.sl!=null&&(d>0?sgx.sl<ep:sgx.sl>ep)?sgx.sl:ep-d*2*a;else
  if(R.stop==='swing')stop=d>0?lowN(c,i,R.n)-a*0.1:highN(c,i,R.n)+a*0.1;
  else if(R.stop==='atr')stop=ep-d*R.k*a;
  else if(R.stop==='bar')stop=d>0?Math.min(c[i].low,c[i-1].low)-a*0.2:Math.max(c[i].high,c[i-1].high)+a*0.2;
  else if(R.stop==='mother'){let m=c[i-2];for(let j=i-1;j>=Math.max(2,i-3);j--){const b=c[j],mm=c[j-1];if(b.high<mm.high&&b.low>mm.low){m=mm;break}}stop=d>0?m.low-a*0.1:m.high+a*0.1}
  else if(R.stop==='line'){const v=d>0?Ln&&Ln.L[i]:Ln&&Ln.S[i];stop=v!=null&&(d>0?v<ep:v>ep)?v:ep-d*2*a}
  else if(R.stop==='lvl'){const q=lv.plan(i,d,ep,a);if(!q){skip++;return}stop=q.stop;tp=q.tp}
  // стоп не ближе половины обычной свечи — иначе выбьет шумом
  if(d>0)stop=Math.min(stop,ep-a*0.5);else stop=Math.max(stop,ep+a*0.5);
  const SM=SMul();if(SM>1&&R.stop!=='lvl')stop=ep-d*Math.abs(ep-stop)*SM;
  const risk=Math.abs(ep-stop);
  if(R.take==='R')tp=ep+d*R.R*risk;else if(R.take==='lvl'&&tp==null){const q=lv.next(i,d,ep,a);tp=q!=null?q:ep+d*2*risk;if(Math.abs(tp-ep)<risk){skip++;return}}
  o={side:d,ep,stop,tp,i0:i,best:ep,S:risk/ep}}
 for(let i=Math.max(from,2);i<n;i++){CI=i;
  if(o&&i>o.i0){const b=c[i],d=o.side;
   const hitS=d>0?b.low<=o.stop:b.high>=o.stop;
   if(hitS){close(d>0?Math.min(b.open,o.stop):Math.max(b.open,o.stop),'st')}
   else if(o.tp!=null&&(d>0?b.high>=o.tp:b.low<=o.tp))close(o.tp,'tp')}
  while(k<sig.length&&sig[k].i<i)k++;
  while(k<sig.length&&sig[k].i===i){const t=sig[k].t,d=t==='long'?1:t==='short'?(spot?0:-1):0;
   // выход по обратному сигналу — только там, где это правило стратегии; где свой тейк или скользящий стоп — сделку ведёт стоп
   if(o&&R.take==='sig'&&o.side!==d&&(d||t==='exit'))close(c[i].close,'sg');
   if(d&&!o)open(i,d,sig[k]);k++}
  // подтягиваем стоп после закрытия свечи
  if(o){const d=o.side,a=A[i]||o.ep*0.01;if(d>0?c[i].high>o.best:c[i].low<o.best)o.best=d>0?c[i].high:c[i].low;
   if(R.trail==='line'&&Ln){const v=d>0?Ln.L[i]:Ln.S[i];if(v!=null&&(d>0?v<c[i].close:v>c[i].close))o.stop=d>0?Math.max(o.stop,v):Math.min(o.stop,v)}
   if(R.trail==='chand'){const v=o.best-d*3*SMul()*a;o.stop=d>0?Math.max(o.stop,v):Math.min(o.stop,v)}}}
 const avg=a=>a.length?a.reduce((x,y)=>x+y,0)/a.length:0,W=P.filter(x=>x>0),Ls=P.filter(x=>x<=0),cnt=P.length;
 return {sl:avg(SL),tpv:0,n:cnt,tp:tpN,st,sg,w:W.length,ret:(eq-1)*100,dd,open:!!o,wnet:avg(W),lnet:avg(Ls),ex:avg(P),hi:cnt?Math.max(...P):0,lo:cnt?Math.min(...P):0,skip}}
// помощник: точки разворота для стопа за фигурой и тейка у следующего уровня (как в правиле «По уровням»)
function lvHelp(c){const k=4,W=200,n=c.length,PH=[],PL=[];
 for(let j=k;j<n-k;j++){let h=true,l=true;for(let q=j-k;q<=j+k;q++){if(q===j)continue;if(c[q].high>c[j].high)h=false;if(c[q].low<c[j].low)l=false}if(h)PH.push({i:j,ok:j+k,p:c[j].high});if(l)PL.push({i:j,ok:j+k,p:c[j].low})}
 const seen=(A,i)=>A.filter(x=>x.ok<=i&&x.i>=i-W);
 const next=(i,d,ep,a)=>{if(d>0){const v=seen(PH,i).map(x=>x.p).filter(p=>p>ep+a*0.3).sort((x,y)=>x-y);return v.length?v[0]-a*0.1:null}const v=seen(PL,i).map(x=>x.p).filter(p=>p<ep-a*0.3).sort((x,y)=>y-x);return v.length?v[0]+a*0.1:null};
 const plan=(i,d,ep,a)=>{let stop;if(d>0){const L=seen(PL,i).filter(x=>x.p<ep).reverse();let b=Math.min(c[i].low,c[i-1].low);if(L.length){b=Math.min(b,L[0].p);if(L[1]&&Math.abs(L[1].p-L[0].p)<=a)b=Math.min(b,L[1].p)}stop=Math.min(b-a*0.2,ep-a*0.5)}
  else{const H=seen(PH,i).filter(x=>x.p>ep).reverse();let b=Math.max(c[i].high,c[i-1].high);if(H.length){b=Math.max(b,H[0].p);if(H[1]&&Math.abs(H[1].p-H[0].p)<=a)b=Math.max(b,H[1].p)}stop=Math.max(b+a*0.2,ep+a*0.5)}
  const SM=SMul();if(SM>1)stop=ep-d*Math.abs(ep-stop)*SM;const risk=Math.abs(ep-stop);let tp;
  if(SM>1){const L=d>0?seen(PH,i).map(x=>x.p-a*0.1).filter(p=>p-ep>=risk).sort((x,y)=>x-y):seen(PL,i).map(x=>x.p+a*0.1).filter(p=>ep-p>=risk).sort((x,y)=>y-x);tp=L.length?L[0]:ep+d*2*risk}
  else{const t=next(i,d,ep,a);tp=t!=null?t:ep+d*2*risk}if(Math.abs(tp-ep)<risk)return null;return {stop,tp}};
 return {plan,next}}
window.RLRUN=rlRun;
})();
const _btr23=btRun;btRun=function(c,s,from,spot,fee,sigAll){if(UI.btRR!==5)return _btr23(c,s,from,spot,fee,sigAll);
 UI.btRR=3;const kc=BTCAP;BTCAP=null;let r;try{r=_btr23(c,s,from,spot,fee,sigAll)}finally{UI.btRR=5;BTCAP=kc}
 const p={};s.params.forEach(q=>p[q.k]=q.v);let sg=sigAll||s.run(c,p);const d=typeof dirOf==='function'?dirOf():0;if(d)sg=dirSig(sg,d);
 const X=RLRUN(c,s,sg.filter(x=>x.i>=from&&x.i<c.length-1),from,spot,fee/100,BTCAP);
 const A={stop:X.sl,n:X.n,win:X.w,byS:X.tp,bySig:X.sg,stops:X.st,ret:X.ret,dd:X.dd,best:X.hi,worst:X.lo,aw:X.wnet,al:X.lnet,ex:X.ex,skip:X.skip,open:null};
 return Object.assign(r,{A,R:[X],best:X,K:5})};
const _bres23=btRes;btRes=function(){const B=BT7;if(!B||B.busy||B.err||B.K!==5)return _bres23();
 const A=B.A,R=(ITS.rules||{})[B.s.id],tfn=(BTF.find(x=>x[0]===B.tf)||[0,B.tf])[1],d=t=>B.days<2?new Date(t*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):new Date(t*1000).toLocaleDateString('ru-RU'),pc=v=>A.n?fmt(v/A.n*100,0)+'%':'0%';
 return `<div class="wbox"><b>${B.s.name}</b> <span class="mu sm">${B.sym} · ${B.mkt==='fut'?'фьючерсы':'спот'} · свечи ${tfn} · ${d(B.t0)} — ${d(B.t1)} · ${fmt(B.candles,0)} свечей${B.dir?` · <b>${B.dir===1?'только лонг':'только шорт'}</b>`:''}</span></div>
 <h3 style="margin:14px 0 4px">1. Как работает стратегия</h3><p class="hint" style="margin-top:0">Стоп и выход — по правилам самой стратегии: ${R?R.txt:'стоп за ближайшим минимумом/максимумом, тейк в 2 раза дальше.'} С учётом комиссии биржи.</p>
 <div class="sgrid"><div><small>Стоп в среднем</small><span class="r">−${pf(A.stop)}%</span></div><div><small>Сделок</small>${A.n}${A.skip?` <span class="mu sm">(пропущено ${A.skip})</span>`:''}</div>
 <div><small>Прибыльных</small><span class="g">${pc(A.win)} (${A.win})</span></div><div><small>Убыточных</small><span class="r">${pc(A.n-A.win)} (${A.n-A.win})</span></div>
 <div><small>Закрылись по стопу</small>${pc(A.stops)} (${A.stops})</div><div><small>По тейку / по сигналу</small>${A.byS} / ${A.bySig}</div>
 <div><small>Итог за период</small><span class="${A.ret>=0?'g':'r'}">${pp(A.ret)}</span></div><div><small>Лучшая / худшая сделка</small><span class="g">${pp(A.best)}</span> / <span class="r">${pp(A.worst)}</span></div>
 <div><small>В среднем на сделку</small><span class="${A.ex>=0?'g':'r'}">${pp(A.ex,2)}</span></div><div><small>Наибольшая просадка</small><span class="r">${A.dd<0.05?'0%':'−'+fmt(A.dd,1)+'%'}</span></div></div>
 ${A.n<10?'<p class="err">Сделок мало — выводы ненадёжны. Возьмите период длиннее или свечи мельче.</p>':''}
 <div id="btmoney">${btMoney()}</div>
 <p class="hint">Серия сделок пока торгует только по правилу 1 к 3 — чтобы запустить её, выберите вверху «1 к 3».</p>
 <p class="hint">Проверка на прошлых свечах. Прошлый результат не гарантирует будущий. Если стоп и тейк задеты одной свечой, считается стоп; если свеча открылась за стопом — выход по цене открытия.</p>`};
const _bmo23=btMoney;btMoney=function(){const h=_bmo23();return BT7&&BT7.K===5?h.replace('4. Сколько это в деньгах','2. Сколько это в деньгах'):h};
BA_MODES.unshift(5);
const _bm23=baMode;baMode=k=>k===5?'правила стратегии':_bm23(k);
const _brow23=baRow;baRow=function(x){let h=_brow23(x);if(x.k===5)h=h.replace(`, стоп −${pf(x.stop)}%`,`, стоп в среднем −${pf(x.stop)}%`);return h};
// в описании стратегии — её правила стопа и выхода
const _is23=infoSheet;infoSheet=function(id){const h=_is23(id),R=(ITS.rules||{})[id];return R?h.replace('<h3 style="margin:12px 0 4px">Где работает лучше</h3>',`<h3 style="margin:12px 0 4px">Стоп и тейк по правилам стратегии</h3><p style="margin:0">${R.txt}</p><h3 style="margin:12px 0 4px">Где работает лучше</h3>`):h};
render();
