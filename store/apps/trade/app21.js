// ИТК Трейдинг — правило «По уровням» (10.11.2026).
// Стоп — сразу за фигурой, тейк — у следующего уровня («отбойника»), как в классическом техническом анализе (Т. Булковски и др.):
//  • покупка: стоп чуть ниже ближайшего минимума под входом; если рядом второй минимум (двойное дно, плечо) — за нижний из них;
//  • продажа: стоп чуть выше ближайшего максимума над входом (правое плечо «головы и плеч», вершины двойной вершины);
//  • тейк — немного не доходя до ближайшего уровня, где цена уже разворачивалась; уровня нет — тейк в 2 раза дальше стопа;
//  • если до уровня ближе, чем до стопа, сделку не открываем — она невыгодна.
// Работает для всех стратегий: и в «Проверке на истории» (кнопка «По уровням»), и в «Тесте всех систем».
"use strict";
function SMul(){const v=+UI.btSM;return v===1.5||v===2||v===3?v:1}
function lvRun(c,sig,from,spot,F,cap){const k=4,W=200,atr=ITS.ind.atr(c,14),n=c.length,PH=[],PL=[];
 for(let j=k;j<n-k;j++){let h=true,l=true;for(let q=j-k;q<=j+k;q++){if(q===j)continue;if(c[q].high>c[j].high)h=false;if(c[q].low<c[j].low)l=false}if(h)PH.push({i:j,ok:j+k,p:c[j].high});if(l)PL.push({i:j,ok:j+k,p:c[j].low})}
 const seen=(A,i)=>A.filter(x=>x.ok<=i&&x.i>=i-W);
 function plan(i,side){const ep=c[i].close,a=atr[i]||ep*0.01;let stop,tp;
  if(side>0){const lows=seen(PL,i).filter(x=>x.p<ep).reverse();let base=Math.min(c[i].low,c[i-1].low);
   if(lows.length){base=Math.min(base,lows[0].p);if(lows[1]&&Math.abs(lows[1].p-lows[0].p)<=a)base=Math.min(base,lows[1].p)}
   stop=Math.min(base-a*0.2,ep-a*0.5);const SM=SMul();if(SM>1)stop=ep-(ep-stop)*SM;const risk=ep-stop;
   const lv=seen(PH,i).map(x=>x.p).filter(p=>p>ep+a*0.3).sort((x,y)=>x-y);if(SM>1){const f=lv.find(p=>p-a*0.1-ep>=risk);tp=f!=null?f-a*0.1:ep+2*risk}else tp=lv.length?lv[0]-a*0.1:ep+2*risk;
   if(tp-ep<risk)return null;return {side,ep,stop,tp,S:risk/ep,T:(tp-ep)/ep}}
  const highs=seen(PH,i).filter(x=>x.p>ep).reverse();let base=Math.max(c[i].high,c[i-1].high);
  if(highs.length){base=Math.max(base,highs[0].p);if(highs[1]&&Math.abs(highs[1].p-highs[0].p)<=a)base=Math.max(base,highs[1].p)}
  stop=Math.max(base+a*0.2,ep+a*0.5);const SM=SMul();if(SM>1)stop=ep+(stop-ep)*SM;const risk=stop-ep;
  const lv=seen(PL,i).map(x=>x.p).filter(p=>p<ep-a*0.3).sort((x,y)=>y-x);if(SM>1){const f=lv.find(p=>ep-(p+a*0.1)>=risk);tp=f!=null?f+a*0.1:ep-2*risk}else tp=lv.length?lv[0]+a*0.1:ep-2*risk;
  if(ep-tp<risk)return null;return {side,ep,stop,tp,S:risk/ep,T:(ep-tp)/ep}}
 let eq=1,pk=1,dd=0,k2=0,open=null,tpN=0,st=0,skip=0;const P=[],SL=[],TP=[];
 for(let i=Math.max(from,k*2+20);i<n;i++){
  if(open&&i>open.i0){const b=c[i],hitS=open.side>0?b.low<=open.stop:b.high>=open.stop,hitT=open.side>0?b.high>=open.tp:b.low<=open.tp;
   if(hitS||hitT){const net=(hitS?1-open.S:1+open.T)*(1-F)*(1-F);eq*=net;pk=Math.max(pk,eq);dd=Math.max(dd,(1-eq/pk)*100);P.push((net-1)*100);if(cap)cap.push({t0:c[open.i0].time,t1:c[i].time,p:(net-1)*100});SL.push(open.S*100);TP.push(open.T*100);if(hitS)st++;else tpN++;open=null}}
  while(k2<sig.length&&sig[k2].i<i)k2++;
  if(!open)while(k2<sig.length&&sig[k2].i===i){const t=sig[k2].t,d=t==='long'?1:t==='short'?(spot?0:-1):0;if(d&&!open){const p=plan(i,d);if(p)open=Object.assign(p,{i0:i});else skip++}k2++}}
 const avg=a=>a.length?a.reduce((x,y)=>x+y,0)/a.length:0,W1=P.filter(x=>x>0),L1=P.filter(x=>x<=0),cnt=P.length;
 return {sl:avg(SL),tpv:avg(TP),n:cnt,tp:tpN,st,w:W1.length,ret:(eq-1)*100,dd,open:!!open,wnet:avg(W1),lnet:avg(L1),ex:avg(P),hi:cnt?Math.max(...P):0,lo:cnt?Math.min(...P):0,skip}}
const _btr21=btRun;btRun=function(c,s,from,spot,fee,sigAll){if(UI.btRR!==4)return _btr21(c,s,from,spot,fee,sigAll);
 UI.btRR=3;const kc=BTCAP;BTCAP=null;let r;try{r=_btr21(c,s,from,spot,fee,sigAll)}finally{UI.btRR=4;BTCAP=kc}
 const p={};s.params.forEach(q=>p[q.k]=q.v);const sig=(sigAll||s.run(c,p)).filter(x=>x.i>=from&&x.i<c.length-1),X=lvRun(c,sig,from,spot,fee/100,BTCAP);
 const A={stop:X.sl,tpAvg:X.tpv,n:X.n,win:X.w,byS:X.tp,stops:X.st,ret:X.ret,dd:X.dd,best:X.hi,worst:X.lo,aw:X.wnet,al:X.lnet,ex:X.ex,skip:X.skip,open:null};
 return Object.assign(r,{A,R:[X],best:X,K:4})};
const _bres21=btRes;btRes=function(){const B=BT7;if(!B||B.busy||B.err||B.K!==4)return _bres21();
 const A=B.A,tfn=(BTF.find(x=>x[0]===B.tf)||[0,B.tf])[1],d=t=>B.days<2?new Date(t*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):new Date(t*1000).toLocaleDateString('ru-RU'),pc=v=>A.n?fmt(v/A.n*100,0)+'%':'0%';
 return `<div class="wbox"><b>${B.s.name}</b> <span class="mu sm">${B.sym} · ${B.mkt==='fut'?'фьючерсы':'спот'} · свечи ${tfn} · ${d(B.t0)} — ${d(B.t1)} · ${fmt(B.candles,0)} свечей</span></div>
 <h3 style="margin:14px 0 4px">1. Как работает стратегия</h3><p class="hint" style="margin-top:0">Вход по сигналу. <b>Стоп — сразу за фигурой</b>: для покупки чуть ниже ближайшего минимума (при двойном дне — за нижнее донышко), для продажи чуть выше ближайшего максимума (у «головы и плеч» — за правое плечо). <b>Тейк — у следующего уровня</b>, где цена уже разворачивалась. Если до уровня ближе, чем до стопа, сделку не открываем. С учётом комиссии биржи.</p>
 <div class="sgrid"><div><small>Стоп в среднем</small><span class="r">−${pf(A.stop)}%</span></div><div><small>Тейк в среднем</small><span class="g">+${pf(A.tpAvg)}%</span></div>
 <div><small>Сделок</small>${A.n}</div><div><small>Пропущено сигналов</small>${A.skip} <span class="mu sm">(уровень ближе стопа)</span></div>
 <div><small>Закрылись по тейку</small><span class="g">${pc(A.byS)} (${A.byS})</span></div><div><small>Закрылись по стопу</small><span class="r">${pc(A.stops)} (${A.stops})</span></div>
 <div><small>Итог за период</small><span class="${A.ret>=0?'g':'r'}">${pp(A.ret)}</span></div><div><small>Лучшая / худшая сделка</small><span class="g">${pp(A.best)}</span> / <span class="r">${pp(A.worst)}</span></div>
 <div><small>В среднем на сделку</small><span class="${A.ex>=0?'g':'r'}">${pp(A.ex,2)}</span></div><div><small>Наибольшая просадка</small><span class="r">${A.dd<0.05?'0%':'−'+fmt(A.dd,1)+'%'}</span></div></div>
 ${A.n<10?'<p class="err">Сделок мало — выводы ненадёжны. Возьмите период длиннее или свечи мельче.</p>':''}
 <div id="btmoney">${btMoney()}</div>
 <p class="hint">Серия сделок пока торгует только по правилу 1 к 3 — чтобы запустить её, выберите вверху «1 к 3».</p>
 <p class="hint">Проверка на прошлых свечах. Прошлый результат не гарантирует будущий. Если стоп и тейк задеты одной свечой, считается стоп.</p>`};
const _bmo21=btMoney;btMoney=function(){const h=_bmo21();return BT7&&BT7.K===4?h.replace('4. Сколько это в деньгах','2. Сколько это в деньгах'):h};
// «Тест всех систем» — тоже с правилом «По уровням»
BA_MODES.push(4);
const _bm21=baMode;baMode=k=>k===4?'стоп за фигурой, тейк у уровня':_bm21(k);
const _brow21=baRow;baRow=function(x){let h=_brow21(x);if(x.k===4)h=h.replace(`, стоп −${pf(x.stop)}%`,`, стоп в среднем −${pf(x.stop)}%`);return h};
render();
