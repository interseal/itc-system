// ИТК Трейдинг — «Проверка на истории»: стратегия за месяц / квартал / полгода / год.
// 1) как в серии: стоп система подбирает сама, тейк в 3 раза дальше; 2) насколько глубоко цена уходила против входа — где ставить стоп;
// 3) подбор стопа при тейке в 2 или 3 раза дальше стопа (1 к 2 / 1 к 3 — выбор UI.btRR). Результат можно применить к сигналам стратегии.
"use strict";
ICP.history="M13 3a9 9 0 00-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0013 21a9 9 0 000-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z";
const BTPS={'1m':[[1/24,'Час','час'],[3/24,'3 часа','3 часа'],[6/24,'6 часов','6 часов'],[12/24,'12 часов','12 часов'],[1,'Сутки','сутки'],[2,'2 дня','2 дня']],
 '5m':[[1,'День','день'],[2,'2 дня','2 дня'],[3,'3 дня','3 дня'],[5,'5 дней','5 дней'],[7,'Неделя','неделю']],
 def:[[30,'Месяц','месяц'],[91,'Квартал','квартал'],[182,'Полгода','полгода'],[365,'Год','год'],[730,'2 года','2 года']]};
let BTFORCE=0,BTCAP=null;// BTCAP — сюда пишутся сделки итогового расчёта (для пересчёта на депозит)
// стоп, подобранный на другом отрезке истории (честная проверка)
const BTPof=tf=>BTPS[tf]||BTPS.def;let BTP=BTPS.def;
const BTF=[['1m','1 мин'],['5m','5 мин'],['15m','15 мин'],['30m','30 мин'],['1h','1 час'],['4h','4 часа'],['1d','1 день']];
const btFix=()=>{BTP=BTPof(UI.btTf||UI.tf);if(!BTP.some(p=>p[0]===UI.btP))UI.btP=BTP[0][0]};
const TFMS=tf=>{const n=parseInt(tf),u=tf.slice(-1);return n*({m:60e3,h:3600e3,d:86400e3,w:604800e3}[u]||3600e3)};
let BT7=null,BTJOB=0;UI.btP=UI.btP||91;
const RR=()=>UI.rr7||{};const pf=v=>fmt(v,v<1?2:1);
// загрузка истории кусками
// загрузка свечей без спешки: Binance ограничивает частоту запросов; при отказе — пауза и повтор
let KLAST=0;const klWait=ms=>new Promise(r=>setTimeout(r,ms));
async function klGet(url){let err='';for(let a=0;a<5;a++){const gap=KLAST+220-Date.now();if(gap>0)await klWait(gap);KLAST=Date.now();
  try{const r=await fetch(url);if(r.ok)return await r.json();err='биржа ответила '+r.status;
   if(r.status===429||r.status===418||r.status>=500){const ra=+(r.headers.get('Retry-After')||0);await klWait(ra>0?Math.min(ra,60)*1000:[2e3,5e3,10e3,20e3,30e3][a]);continue}
   if(r.status===400)throw new Error('монеты нет на этом рынке')}catch(e){if(/монеты нет/.test(e.message))throw e;err=e.message||'нет связи';await klWait([1e3,3e3,6e3,10e3,15e3][a])}}
 throw new Error(err||'нет связи с биржей')}
async function histK(mkt,sym,tf,days,warm,onp){const ms=TFMS(tf),end=Date.now(),from=end-days*86400e3-warm*ms,lim=mkt==='fut'?1500:1000,tot=Math.ceil((end-from)/ms/lim),out=[];let t=from,k=0;
 while(t<end){const a=await klGet(API[mkt]+'klines?symbol='+pair(sym)+'&interval='+tf+'&limit='+lim+'&startTime='+Math.floor(t));if(!a.length)break;
  a.forEach(x=>{if(!out.length||x[0]/1000>out[out.length-1].time)out.push({time:x[0]/1000,open:+x[1],high:+x[2],low:+x[3],close:+x[4],volume:+x[5]})});t=a[a.length-1][0]+ms;onp&&onp(++k,tot);if(a.length<lim)break}
 return out.slice(0,-1)}
const pctl=(a,q)=>{if(!a.length)return 0;const s=[...a].sort((x,y)=>x-y);return s[Math.min(s.length-1,Math.floor(q*(s.length-1)+0.5))]};
// расчёт
function btRun(c,s,from,spot,fee,sigAll){const K=[0,1,2].includes(UI.btRR)?UI.btRR:3;const p={};s.params.forEach(q=>p[q.k]=q.v);const sig=(sigAll||s.run(c,p)).filter(x=>x.i>=from&&x.i<c.length-1);const F=fee/100;
 // ATR — обычное движение за свечу (нужно для стопа по умолчанию)
 const atr=ITS.ind.atr(c,14),ap=[];for(let i=from;i<c.length;i++)if(atr[i])ap.push(atr[i]/c[i].close*100);const base=pctl(ap,.5)||1;
 const nice=v=>v<0.2?Math.round(v*100)/100:v<1?Math.round(v*20)/20:v<5?Math.round(v*2)/2:Math.round(v);
 // сделки по сигналам без стопа — только чтобы оценить, как глубоко цена уходила против входа (раздел 2)
 const raw=(()=>{const tr=[];let pos=0,ep=0,i0=0,mae=0,k=0;const shut=(i)=>{const g=pos>0?c[i].close/ep:2-c[i].close/ep/* №482 */;tr.push({pnl:(g*(1-F)*(1-F)-1)*100,mae});pos=0};
  for(let i=from;i<c.length;i++){if(pos&&i>i0)mae=Math.max(mae,pos>0?(ep-c[i].low)/ep*100:(c[i].high-ep)/ep*100);
   while(k<sig.length&&sig[k].i===i){const t=sig[k].t,d=t==='long'?1:t==='short'?(spot?0:-1):0;if(pos&&pos!==d)shut(i);if(d&&!pos){pos=d;ep=c[i].close;i0=i;mae=0}k++}}return tr})();
 // 2) глубина ухода против входа
 const wins=raw.filter(t=>t.pnl>0).map(t=>t.mae),all=raw.map(t=>t.mae);
 const M={w50:pctl(wins,.5),w80:pctl(wins,.8),w90:pctl(wins,.9),a50:pctl(all,.5),a80:pctl(all,.8),nW:wins.length};
 // 3) подбор стопа при 1 к 3
 const SMx=typeof SMul==='function'?SMul():1,cand=[...new Set([0.5,0.75,1,1.5,2,2.5,3,4,5].map(m=>nice(base*m*SMx)).filter(v=>v>0))];
 // K=2 или 3 — тейк в K раз дальше стопа; K=0 — тейка нет, выход по обратному сигналу (стоп обязателен всегда)
 const sim=(sl,cap)=>{let tp=0,st=0,eq=1,pk=1,dd=0,k=0,open=null;const S=sl/100,P=[];
  const shut=(g,why,i)=>{const net=g*(1-F)*(1-F);eq*=net;pk=Math.max(pk,eq);dd=Math.max(dd,(1-eq/pk)*100);P.push((net-1)*100);if(cap)cap.push({t0:c[open.i0].time,t1:c[i].time,p:(net-1)*100});if(why==='st')st++;else tp++;open=null};
  for(let i=from;i<c.length;i++){
   if(open&&i>open.i0){const b=c[i],slp=open.side>0?open.ep*(1-S):open.ep*(1+S),hitS=open.side>0?b.low<=slp:b.high>=slp;
    if(hitS)shut(1-S,'st',i);
    else if(K){const tpp=open.side>0?open.ep*(1+K*S):open.ep*(1-K*S);if(open.side>0?b.high>=tpp:b.low<=tpp)shut(1+K*S,'tp',i)}}
   while(k<sig.length&&sig[k].i<i)k++;
   while(k<sig.length&&sig[k].i===i){const t=sig[k].t,d=t==='long'?1:t==='short'?(spot?0:-1):0;
    if(!K&&open&&open.side!==d&&(d||t==='exit'))shut(open.side>0?c[i].close/open.ep:2-c[i].close/open.ep,'sg',i);
    if(d&&!open)open={side:d,ep:c[i].close,i0:i};k++}}
  const W=P.filter(x=>x>0),L=P.filter(x=>x<=0),avg=a=>a.length?a.reduce((x,y)=>x+y,0)/a.length:0,n=P.length;
  return {sl,tpv:K?K*sl:0,n,tp,st,w:W.length,ret:(eq-1)*100,dd,open:!!open,wnet:avg(W),lnet:avg(L),ex:avg(P),hi:n?Math.max(...P):0,lo:n?Math.min(...P):0}};
 const R=cand.map(v=>sim(v));const ok=R.filter(r=>r.n>=5);const best=(ok.length?ok:R).reduce((a,b)=>b.ret>a.ret?b:a,R[0]);
 // 1) как работает стратегия — как в серии: вход по сигналу, стоп обязателен, тейк в 3 раза дальше стопа
 const STOP=BTFORCE>0?BTFORCE:best.sl,X=R.find(r=>Math.abs(r.sl-STOP)<1e-9)||sim(STOP);if(BTCAP)sim(STOP,BTCAP);
 const A={stop:STOP,n:X.n,win:X.w,byS:X.tp,stops:X.st,ret:X.ret,dd:X.dd,best:X.hi,worst:X.lo,aw:X.wnet,al:X.lnet,ex:X.ex,open:null};
 return {A,M,R,best,base,K,candles:c.length-from,c,from,spot,fee,s}}
async function btGo(){btFix();const job=++BTJOB,s=ITS.list.find(x=>x.id===(UI.btS||UI.strat))||STR(),tf=UI.btTf||UI.tf,days=UI.btP,mkt=UI.mkt,sym=UI.sym;
 const n=days*86400e3/TFMS(tf);if(n>40000){BT7={err:`Слишком много свечей (${fmt(n,0)}). Для периода «${(BTP.find(p=>p[0]===days)||[0,''])[1]}» выберите свечи крупнее.`};return btShow()}
 BT7={busy:'Загружаю историю…'};btShow();
 try{const c=await histK(mkt,sym,tf,days,300,(k,t)=>{if(job!==BTJOB)return;BT7={busy:`Загружаю историю: ${k} из ${t}`};btShow()});if(job!==BTJOB)return;
  if(c.length<350)throw new Error('биржа отдала слишком мало свечей');const from=Math.max(300,c.length-Math.round(n));BT7={busy:'Считаю…'};btShow();await new Promise(r=>setTimeout(r,30));
  const r=btRun(c,s,from,mkt==='spot',mkt==='fut'?0.05:0.1);BT7=Object.assign(r,{s,tf,days,mkt,sym,t0:c[from].time,t1:c[c.length-1].time})}
 catch(e){if(job!==BTJOB)return;BT7={err:'Не удалось: '+e.message}}btShow()}
function btShow(){if(!SHEET||SHEET[0]!=='bt')return;const el=$('#btres');if(el)el.innerHTML=btRes()}
const pp=(v,d=1)=>{const r=+v.toFixed(d);return r===0?'0%':(r>0?'+':'')+fmt(v,d)+'%'};
function btRes(){const B=BT7;if(!B)return '<p class="hint">Выберите период и нажмите «Проверить».</p>';if(B.busy)return `<p class="mu">${B.busy}</p>`;if(B.err)return `<p class="err">${B.err}</p>`;
 const K=B.K==null?3:B.K,A=B.A,M=B.M,tfn=(BTF.find(x=>x[0]===B.tf)||[0,B.tf])[1],d=t=>B.days<2?new Date(t*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):new Date(t*1000).toLocaleDateString('ru-RU'),few=A.n<10;
 const cur=RR(),applied=cur.on&&cur.key===B.mkt+B.sym+B.tf+B.s.id;
 return `<div class="wbox"><b>${B.s.name}</b> <span class="mu sm">${B.sym} · ${B.mkt==='fut'?'фьючерсы':'спот'} · свечи ${tfn} · ${d(B.t0)} — ${d(B.t1)} · ${fmt(B.candles,0)} свечей</span></div>
 <h3 style="margin:14px 0 4px">1. Как работает стратегия</h3><p class="hint" style="margin-top:0">${K?`Как в серии сделок: вход по сигналу, стоп обязателен — система сама подобрала его по истории, ${K===1?'тейк на таком же расстоянии, как стоп':`тейк в ${K} раза дальше стопа`} (1 к ${K}). По обратному сигналу не выходим.`:'Вход по сигналу, стоп обязателен — система сама подобрала его по истории. Тейка нет: сделка закрывается по обратному сигналу или по стопу.'} С учётом комиссии биржи.</p>
 <div class="sgrid"><div><small>Стоп / ${K?'тейк':'выход'}</small><span class="r">−${pf(A.stop)}%</span> / ${K?`<span class="g">+${pf(A.stop*K)}%</span>`:'по сигналу'}</div><div><small>Сделок</small>${A.n}</div><div><small>${K?'Закрылись по тейку':'Прибыльных'}</small><span class="g">${A.n?fmt(A.win/A.n*100,0):0}% (${A.win})</span></div><div><small>${K?'Закрылись по стопу':'Убыточных'}</small><span class="r">${A.n?fmt((A.n-A.win)/A.n*100,0):0}% (${A.n-A.win})</span></div>${K?'':`<div><small>Закрылись по стопу</small>${A.n?fmt(A.stops/A.n*100,0):0}% (${A.stops})</div><div><small>Закрылись по сигналу</small>${A.n?fmt(A.byS/A.n*100,0):0}% (${A.byS})</div>`}<div><small>Итог за период</small><span class="${A.ret>=0?'g':'r'}">${pp(A.ret)}</span></div><div><small>Лучшая / худшая сделка</small><span class="g">${pp(A.best)}</span> / <span class="r">${pp(A.worst)}</span></div><div><small>Наибольшая просадка</small><span class="r">${A.dd<0.05?'0%':'−'+fmt(A.dd,1)+'%'}</span></div></div>
 ${few?'<p class="err">Сделок мало — выводы ненадёжны. Возьмите период длиннее или свечи мельче (1 или 5 минут).</p>':''}
 <h3 style="margin:14px 0 4px">2. Где ставить стоп</h3><p class="hint" style="margin-top:0">Насколько далеко цена уходила против входа, прежде чем сделка закрылась.</p>
 ${M.nW?`<div class="sgrid"><div><small>Половина прибыльных сделок</small>уходила против не дальше <b>${pf(M.w50)}%</b></div><div><small>8 из 10 прибыльных</small>не дальше <b>${pf(M.w80)}%</b></div><div><small>9 из 10 прибыльных</small>не дальше <b>${pf(M.w90)}%</b></div><div><small>Обычное движение за одну свечу</small>${fmt(B.base,1)}%</div></div>
 <p class="sm" style="margin:8px 0">Чтобы стоп не выбивал 8 из 10 будущих прибыльных сделок, ставьте его не ближе <b>${pf(M.w80)}%</b> от входа. Ближе — стоп будет часто срабатывать впустую.</p>`:'<p class="mu sm">Прибыльных сделок нет — оценить нельзя.</p>'}
 ${K?`<h3 style="margin:14px 0 4px">3. Стоп и тейк 1 к ${K}</h3><p class="hint" style="margin-top:0">${K===1?'Тейк на таком же расстоянии от входа, как стоп':`Тейк всегда в ${K} раза дальше стопа`}. Пример: вход $1, стоп −10% ($0,90), тейк +${K*10}% ($1,${K*10}). Чтобы быть в плюсе, тейк должен срабатывать ${K===1?'больше чем в половине сделок':`хотя бы в 1 сделке из ${K+1}`}.</p>`:`<h3 style="margin:14px 0 4px">3. Стоп и выход по сигналу</h3><p class="hint" style="margin-top:0">Тейка нет: прибыль фиксируется по обратному сигналу. Стоп защищает от большого убытка. В таблице — какой стоп лучше.</p>`}
 <table class="bt7"><tr><th>Стоп</th><th>${K?'Тейк':'Выход'}</th><th>Сделок</th><th>${K?'Тейк<br>сработал':'По<br>сигналу'}</th><th>Стоп<br>сработал</th><th>Итог</th></tr>
 ${B.R.map(r=>`<tr class="${r===B.best?'best':''}"><td class="r">−${fmt(r.sl,r.sl<1?2:1)}%</td><td class="g">${K?`+${fmt(r.tpv,r.tpv<1?2:1)}%`:'сигнал'}</td><td>${r.n}</td><td>${r.n?fmt(r.tp/r.n*100,0):0}%</td><td>${r.n?fmt(r.st/r.n*100,0):0}%</td><td class="${r.ret>=0?'g':'r'}">${pp(r.ret,0)}</td></tr>`).join('')}</table>
 ${(()=>{const b=B.best;if(!b||!b.n)return '<p class="mu sm">Нет закрытых сделок для расчёта.</p>';const good=b.ret>0;
  return `<div class="wbox" style="margin-top:8px"><b class="${good?'g':'r'}">${good?'Лучший вариант':'Ни один вариант не дал прибыли'}</b><p class="sm" style="margin:4px 0">${good?`Стоп <b>−${pf(b.sl)}%</b> от входа, ${K?`тейк <b>+${pf(b.tpv)}%</b>`:'выход по сигналу'}. ${K?'Тейк сработал':'По сигналу закрылось'} в ${fmt(b.tp/b.n*100,0)}% сделок, стоп — в ${fmt(b.st/b.n*100,0)}%. Итог за период ${pp(b.ret)}, наибольшая просадка −${fmt(b.dd,1)}%.`:`На этом периоде стратегия ${K?`с правилом 1 к ${K}`:'со стопом и выходом по сигналу'} теряет деньги. Попробуйте другую стратегию, другие свечи или период.`}</p>
  ${good&&K?(applied?`<div class="row"><span class="g sm" style="flex:1">Применено к сигналам стратегии</span><button class="btn2 sm" data-a="bt7off">Отменить</button></div>`:`<button class="go b" data-a="bt7apply">Применить к сигналам: стоп −${pf(b.sl)}%, тейк +${pf(b.tpv)}%</button>`):''}</div>`})()}
 <div id="btmoney">${btMoney()}</div>
 <p class="hint">Проверка на прошлых свечах. Прошлый результат не гарантирует будущий. Если стоп и тейк задеты одной свечой, считается стоп.</p>`}
function btSheet(){btFix();const ss=ITS.list,cs=UI.btS||UI.strat,tf=UI.btTf||UI.tf;
 return sheetWrap(`${ic('history',20)}&nbsp;Проверка на истории`,`${row('Стратегия',`<div class="in"><select id="bts">${ss.map(s=>`<option value="${s.id}" ${s.id===cs?'selected':''}>${s.name}</option>`).join('')}</select></div>`)}
 ${row('Свечи',`<div class="in"><select id="bttf">${BTF.map(t=>`<option value="${t[0]}" ${t[0]===tf?'selected':''}>${t[1]}</option>`).join('')}${BTF.some(t=>t[0]===tf)?'':`<option value="${tf}" selected>${tf}</option>`}</select></div>`)}
 <div class="row"><label>Стоп и тейк</label><div class="pct" style="margin:0;flex:1">${[5,4,1,2,3,0].map(k=>`<button class="${([0,1,2,4,5].includes(UI.btRR)?UI.btRR:3)===k?'on':''}" data-a="btrr" data-v="${k}">${k===5?'Правила стратегии':k===4?'По уровням':k?'1 к '+k:'По сигналу'}</button>`).join('')}</div></div>
 <div class="pct" style="margin:8px 0">${BTP.map(p=>`<button class="${UI.btP===p[0]?'on':''}" data-a="btper" data-v="${p[0]}">${p[1]}</button>`).join('')}</div>
 <button class="go b" data-a="btgo">Проверить ${UI.sym} за ${(BTP.find(p=>p[0]===UI.btP)||[0,'',''])[2]}</button><div id="btres">${btRes()}</div>`)}
const _r8=render;render=function(){if(SHEET&&SHEET[0]==='bt'){SHEET=null;_r8();SHEET=['bt'];$('#app').insertAdjacentHTML('beforeend',btSheet());return}_r8()};
// применить найденный стоп/тейк к предложению стратегии
const _mp8=makeProposal;makeProposal=function(s,p,done,sig){_mp8(s,p,done,sig);const r=RR(),P=PROP;if(!P||P.exit||!r.on||r.key!==UI.mkt+UI.sym+UI.tf+s.id)return;const S=r.sl/100,dg=pd(P.ep);
 P.sl=+(P.ep*(1-P.side*S)).toFixed(dg);const t=+(P.ep*(1+P.side*(r.k||3)*S)).toFixed(dg);P.tps=[t];P.rr13=r;const b=$('#propbox');if(b)b.innerHTML=propHtml();paintOrders()};
const _ph8=propHtml;propHtml=function(){const P=PROP;if(!P||!P.rr13||P.exit)return _ph8();const two=[P.tps[0],P.tps[0]];P.tps=two;let h=_ph8();P.tps=[two[0]];
 h=h.replace(' · '+fp(two[0])+'</span>','</span>').replace('Тейки 50% + 50%','Тейк 100%').replace(/<p class="sm mu" style="margin:6px 0">Стоп и тейки[^<]*/,`<p class="sm mu" style="margin:6px 0">Стоп −${pf(P.rr13.sl)}% и тейк +${pf(P.rr13.sl*(P.rr13.k||3))}% — по проверке на истории (1 к ${P.rr13.k||3}). `);return h};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='bt7open'){e.stopPropagation();SHEET=['bt'];render();history.pushState({sh:1},'')}
 else if(a==='btper'){UI.btP=+v;UI.btS=$('#bts').value;UI.btTf=$('#bttf').value;saveUI();BT7=null;$('.sheet').outerHTML=btSheet()}
 else if(a==='btgo'){UI.btS=$('#bts').value;UI.btTf=$('#bttf').value;saveUI();btGo()}
 else if(a==='bt7apply'&&BT7&&BT7.best){UI.rr7={on:1,sl:BT7.best.sl,k:BT7.K||3,key:BT7.mkt+BT7.sym+BT7.tf+BT7.s.id};if(BT7.s.id!==UI.strat||BT7.tf!==UI.tf){UI.strat=BT7.s.id;UI.tf=BT7.tf}UI.ov='strat';saveUI();SHEET=null;render();toast(`Сигналы стратегии: стоп −${pf(BT7.best.sl)}%, тейк +${pf(BT7.best.sl*(BT7.K||3))}%`)}
 else if(a==='btrr'){e.stopPropagation();UI.btRR=+v;saveUI();const B=BT7;if(B&&B.c&&B.A){const r=btRun(B.c,B.s,B.from,B.spot,B.fee);BT7=Object.assign(B,{skip:0},r)}$('.sheet').outerHTML=btSheet()}
 else if(a==='bt7off'){UI.rr7={};saveUI();btShow();paintStrat()}},true);
document.addEventListener('change',e=>{if(e.target.id==='bts'||e.target.id==='bttf'){UI.btS=$('#bts').value;UI.btTf=$('#bttf').value;saveUI();BT7=null;if(e.target.id==='bttf'){$('.sheet').outerHTML=btSheet();return}const r=$('#btres');if(r)r.innerHTML=btRes()}});
// после «Согласиться» в режиме 1 к 3 — один тейк на 100%
document.addEventListener('click',e=>{const el=e.target.closest('[data-a=propyes]');if(!el||!PROP||!PROP.rr13)return;F.tps=[{px:String(PROP.tps[0]),pct:100}];saveUI();syncForm();paintOrders()});
// кнопка — в карточке стратегии и на панели над графиком
const _bh8=btHtml;btHtml=function(b,s){return _bh8(b,s)+`<button class="btn2" style="width:100%;margin-top:8px;padding:10px" data-a="bt7open">${ic('history',18)} Проверка на истории: месяц, квартал, год</button>`};
const _tv8=termView;termView=function(){let h=_tv8();h=h.replace(/(<button class="chip[^"]*" data-a="arrows">)/,`<button class="chip" data-a="bt7open">${ic('history',18)} Проверка на истории</button>$1`);return h};
render();
// ---------- стратегия «Отскок от уровней» (10.11.2026) ----------
// Уровни — места, где цена уже разворачивалась (пивоты). Берутся только прошлые свечи, без заглядывания вперёд.
// Покупка: свеча коснулась поддержки и закрылась выше неё (отскок вверх). Продажа: коснулась сопротивления и закрылась ниже.
(function(){if(ITS.list.find(s=>s.id==='lvl'))return;
ITS.list.push({id:'lvl',name:'Отскок от уровней',about:'Ищет уровни, от которых цена уже разворачивалась не меньше двух раз. Покупает, когда свеча коснулась поддержки и закрылась выше неё; продаёт, когда коснулась сопротивления и закрылась ниже. Можно требовать подтверждение свечным паттерном (молот, поглощение и др.). Подходит для коротких свечей — 1 и 5 минут.',
 params:[{k:'k',n:'Ширина разворота, свечей',v:4},{k:'win',n:'Сколько свечей смотреть назад',v:300},{k:'touch',n:'Минимум касаний уровня',v:2},{k:'pat',n:'Подтверждать свечным паттерном (1 — да, 0 — нет)',v:0}],
 run(c,p){const k=Math.max(2,p.k|0),W=p.win|0||300,need=p.touch|0||2,n=c.length,out=[];if(n<k*2+30)return out;
  const atr=ITS.ind.atr(c,14);const pv=[];// подтверждённые пивоты
  let cset=null;if(p.pat&&window.PAT){cset=new Map();try{PAT.detect(c).cands.forEach(x=>{if(x.dir)cset.set(x.i,x.dir)})}catch(e){}}
  let last=null,lastI=-1e9;
  for(let i=k*2+20;i<n;i++){const j=i-k;// пивот в j подтверждён к свече i
   let h=true,l=true;for(let q=j-k;q<=j+k;q++){if(q===j)continue;if(c[q].high>c[j].high)h=false;if(c[q].low<c[j].low)l=false}
   if(h)pv.push({i:j,p:c[j].high});if(l)pv.push({i:j,p:c[j].low});
   while(pv.length&&pv[0].i<i-W)pv.shift();
   const a=atr[i];if(!a)continue;const tol=a*0.6,zs=[];
   pv.forEach(x=>{if(x.i>=i-1)return;const z=zs.find(z=>Math.abs(z.p-x.p)<=tol);if(z){z.p=(z.p*z.n+x.p)/(z.n+1);z.n++}else zs.push({p:x.p,n:1})});
   const b=c[i],prev=c[i-1].close;let sig=null;
   for(const z of zs){if(z.n<need)continue;
    if(prev>z.p&&b.low<=z.p+a*0.25&&b.low>=z.p-a*0.8&&b.close>z.p+a*0.1&&b.close>=b.open){sig='long';break}
    if(prev<z.p&&b.high>=z.p-a*0.25&&b.high<=z.p+a*0.8&&b.close<z.p-a*0.1&&b.close<=b.open){sig='short';break}}
   if(sig&&cset){const d=cset.get(i);if(!(d&&(d>0)===(sig==='long')))sig=null}
   if(sig&&(sig!==last||i-lastI>=k*3)){out.push({i,t:sig});last=sig;lastI=i}}
  return out}});
})();

// ---------- 4. Сколько это в деньгах: ставка, плечо, серия из N сделок ----------
UI.bmA=UI.bmA||100;UI.bmL=UI.bmL||1;UI.bmN=UI.bmN||20;
function btMoney(){const B=BT7;if(!B||!B.A||!B.A.n)return '';const fut=B.mkt==='fut',st=UI.bmA,lev=fut?UI.bmL:1,N=UI.bmN,usd=p=>st*lev*p/100,m=v=>(v>=0?'+':'−')+fmt(Math.abs(v),2)+' $',pr=v=>(v>=0?'+':'−')+fmt(Math.abs(v),1)+'%';
 const A=B.A,b=B.best&&B.best.n?B.best:null;
 const col=(t,n,win,aw,al,ex)=>{const loss=n-win,ratio=al?Math.abs(aw/al):0,ser=usd(ex)*N;
  return `<div class="plan ${ser>=0?'long':'short'}" style="cursor:default"><b>${t}</b>
  <div class="sgrid" style="margin-top:6px"><div><small>Прибыльных</small><span class="g">${win} из ${n} (${fmt(win/n*100,0)}%)</span></div><div><small>Убыточных</small><span class="r">${loss} из ${n} (${fmt(loss/n*100,0)}%)</span></div>
  <div><small>Средняя прибыльная сделка</small><span class="g">${m(usd(aw))}</span></div><div><small>Средняя убыточная сделка</small><span class="r">${m(usd(al))}</span></div>
  <div><small>В среднем на одну сделку</small><span class="${ex>=0?'g':'r'}">${m(usd(ex))}</span></div><div><small>Серия из ${N} сделок</small><b class="${ser>=0?'g':'r'}">${m(ser)} (${pr(ser/st*100)} к ставке)</b></div></div>
  <p class="sm" style="margin:6px 0 0">${win&&loss?`Одна прибыльная сделка приносит в <b>${fmt(ratio,1)}</b> раза больше, чем теряет одна убыточная. `:''}${ex>0?`Поэтому, хотя прибыльных ${win/n<0.5?'меньше половины':win/n===0.5?'ровно половина':'больше половины'}, <b class="g">в итоге прибыль</b>: за ${N} сделок по ${fmt(st,0)} $${lev>1?' с плечом '+lev+'×':''} — около ${m(ser)}.`:`<b class="r">Прибыли нет</b>: убытки съедают прибыль, за ${N} сделок — около ${m(ser)}. Такие сделки не открывать.`}</p></div>`};
 return `<h3 style="margin:14px 0 4px">4. Сколько это в деньгах</h3><p class="hint" style="margin-top:0">Пересчёт результатов проверки на вашу ставку и серию сделок. С учётом комиссии биржи.</p>
 ${row('Ставка',`<div class="in"><input type="number" id="bmA" min="1" step="any" value="${st}"><span>USDT</span></div>`)}<div class="pct">${[50,100,250,500,1000].map(v=>`<button class="${st===v?'on':''}" data-a="bmA" data-v="${v}">$${v}</button>`).join('')}</div>
 ${fut?`<div class="row"><label>Плечо</label><div class="pct" style="margin:0;flex:1">${[1,2,3,5,10].map(v=>`<button class="${lev===v?'on':''}" data-a="bmL" data-v="${v}">${v}×</button>`).join('')}</div></div>`:''}
 <div class="row"><label>Сделок в серии</label><div class="pct" style="margin:0;flex:1">${[10,20,30,50,100].map(v=>`<button class="${N===v?'on':''}" data-a="bmN" data-v="${v}">${v}</button>`).join('')}</div></div>
 ${col(B.K===5?`Стоп и выход по правилам стратегии (стоп в среднем −${pf(A.stop)}%)`:B.K===4?`Стоп за фигурой (в среднем −${pf(A.stop)}%), тейк у следующего уровня (в среднем +${pf(A.tpAvg)}%)`:B.K===0?`Стоп −${pf(A.stop)}% и выход по обратному сигналу (стоп подобран системой)`:`Стоп −${pf(A.stop)}% и тейк +${pf(A.stop*(B.K||3))}% (1 к ${B.K||3}, подобрано системой)`,A.n,A.win,A.aw,A.al,A.ex)}
 <p class="hint">Это расчёт по прошлым свечам: в среднем на сделку × число сделок. В жизни результат серии может отличаться — проверьте её сначала на демо-счёте.</p>`}
function bmPaint(){const e=$('#btmoney');if(!e)return;const f=document.activeElement&&document.activeElement.id==='bmA',v=f?$('#bmA').value:null;e.innerHTML=btMoney();if(f){const i=$('#bmA');i.focus();i.value=v;try{i.setSelectionRange(v.length,v.length)}catch(_){}}}
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;if(a==='bmA'||a==='bmL'||a==='bmN'){e.stopPropagation();UI[a]=+el.dataset.v;saveUI();bmPaint()}},true);
document.addEventListener('input',e=>{if(e.target.id==='bmA'){const v=+e.target.value;if(v>0){UI.bmA=v;saveUI();bmPaint()}}});
