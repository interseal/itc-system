// ИТК Трейдинг — стоп обязателен везде (10.11.2026).
// Карточка стратегии под графиком («Сделок на истории», «Итог стратегии») теперь считает так же, как серия:
// вход по сигналу, стоп система подбирает сама по истории, тейк ровно в 3 раза дальше стопа (1 к 3). По обратному сигналу не выходим.
"use strict";
const _bt19=ITS.backtest;
function rrRun(c,sig,F,spot,sl){const S=sl/100;let eq=1,pk=1,dd=0,k=0,open=null;const tr=[];
 for(let i=0;i<c.length;i++){if(open&&i>open.i0){const b=c[i],slp=open.side>0?open.ep*(1-S):open.ep*(1+S),tpp=open.side>0?open.ep*(1+3*S):open.ep*(1-3*S);
   const hitS=open.side>0?b.low<=slp:b.high>=slp,hitT=open.side>0?b.high>=tpp:b.low<=tpp;
   if(hitS||hitT){const r=hitS?-S:3*S,net=(1+r)*(1-F)*(1-F);eq*=net;pk=Math.max(pk,eq);dd=Math.max(dd,(1-eq/pk)*100);tr.push({i0:open.i0,i,side:open.side,pnl:(net-1)*100,why:hitS?'стоп':'тейк'});open=null}}
  while(k<sig.length&&sig[k].i<i)k++;
  if(!open)while(k<sig.length&&sig[k].i===i){const t=sig[k].t,d=t==='long'?1:t==='short'?(spot?0:-1):0;if(d&&!open)open={side:d,ep:c[i].close,i0:i};k++}}
 const L=c[c.length-1].close,op=open?{side:open.side,ep:open.ep,i0:open.i0,pnl:((open.side>0?L/open.ep:2-L/open.ep)-1)*100,sl:open.side>0?open.ep*(1-S):open.ep*(1+S),tp:open.side>0?open.ep*(1+3*S):open.ep*(1-3*S)}:null;
 const w=tr.filter(t=>t.pnl>0).length;return {n:tr.length,win:tr.length?w/tr.length*100:0,ret:(eq-1)*100,dd,hold:(L/c[0].close-1)*100,trades:tr,open:op,sl,tp:3*sl}}
function autoStop(c,sig,F,spot){const atr=ITS.ind.atr(c,14),ap=[];for(let i=0;i<c.length;i++)if(atr[i])ap.push(atr[i]/c[i].close*100);ap.sort((a,b)=>a-b);const base=ap.length?ap[Math.floor(ap.length/2)]:1;
 const nice=v=>v<0.2?Math.round(v*100)/100:v<1?Math.round(v*20)/20:v<5?Math.round(v*2)/2:Math.round(v);
 const cand=[...new Set([0.5,0.75,1,1.5,2,2.5,3,4,5].map(m=>nice(base*m)).filter(v=>v>0))];
 const R=cand.map(sl=>rrRun(c,sig,F,spot,sl)),ok=R.filter(r=>r.n>=5);return (ok.length?ok:R).reduce((a,b)=>b.ret>a.ret?b:a,R[0])}
ITS.backtest=function(c,sig,o){o=o||{};if(!c||c.length<20)return _bt19(c,sig,o);const F=(o.fee||0.05)/100;
 if(o.sl>0)return rrRun(c,sig,F,o.spot,o.sl);
 return autoStop(c,sig,F,o.spot)};
// карточка стратегии: показываем стоп и тейк, которые подобрала система
const _bh19=btHtml;btHtml=function(b,s){let h=_bh19(b,s);if(b.sl==null)return h;
 h=h.replace('<div><small>Сделок на истории</small>',`<div><small>Стоп / тейк (1 к 3)</small><span class="r">−${pf(b.sl)}%</span> / <span class="g">+${pf(b.tp)}%</span></div><div><small>Сделок на истории</small>`);
 return h.replace('Проверка на прошлых свечах этого графика с учётом комиссии.','Проверка на прошлых свечах этого графика: вход по сигналу, стоп система подобрала сама, тейк в 3 раза дальше стопа, с учётом комиссии.')};
// стоп для новой серии по умолчанию — тот же, что подобрала система на истории этого графика
const _sd19=srDefSl;srDefSl=function(sid,tf){const r=UI.rr7||{};if((r.on&&r.key===UI.mkt+UI.sym+tf+sid)||(SRPRE&&SRPRE.sl))return _sd19(sid,tf);
 try{if(tf===UI.tf&&C&&C.length>60){const s=ITS.list.find(q=>q.id===sid);if(s){const p={};s.params.forEach(q=>p[q.k]=q.v);const done=C.slice(0,-1),b=autoStop(done,s.run(done,p),UI.mkt==='fut'?0.0005:0.001,UI.mkt==='spot');if(b&&b.n>=3)return b.sl}}}catch(_){}
 return _sd19(sid,tf)};
render();
