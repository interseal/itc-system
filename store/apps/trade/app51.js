// ИТК Трейдинг — №479 (12.11.2026): «Листинги» → стратегия «Шорт после взлёта новой монеты» на часовых свечах.
// Замысел Алексея: в открытие не заходим — монета улетает слишком быстро. Ждём, пока взлёт остановится:
// несколько часовых свечей подряд без нового максимума, и цена закрылась ниже их минимумов — берём шорт.
// Держим, пока падение не остановится: N часовых свечей подряд без нового минимума — выходим.
// Стоп — чуть выше максимума свечей, на которых цена остановилась. Проверка на настоящей истории запусков Binance (фьючерсы).
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 let NS={pump:0.6,stall:2,m:20,hold:168,sb:0.1,n:100,busy:'',res:null,err:''};try{Object.assign(NS,JSON.parse(localStorage.getItem('nlsset')||'{}'),{busy:'',res:null,err:''})}catch(_){}
 let JOB=0;const CC={};
 const FEE=0.0005,SLP=0.002;
 // одна сделка на монету: первый сигнал в первые 3 недели после запуска
 function sim(c,P0){const n=c.length;if(n<30)return null;let P=c[0].high,pi=0;const base=c[0].close;
  for(let i=1;i<n;i++){const x=c[i];if(x.high>P){P=x.high;pi=i}if(i>21*24)return null;
   if(P>=base*(1+P0.pump)&&i-pi>=P0.stall){let lo=1e18,hi=0;for(let q=i-P0.stall;q<i;q++){lo=Math.min(lo,c[q].low);hi=Math.max(hi,c[q].high)}
    if(x.close<lo){const ep=x.close*(1-SLP),stop=hi*(1+P0.sb);let low=x.low,cnt=0,xp=null,j=i+1,why='';
     for(;j<n;j++){const y=c[j];if(y.high>=stop){xp=Math.max(y.open,stop)*(1+SLP);why='стоп';break}if(P0.hold>0){if(j-i>=P0.hold){xp=y.close*(1+SLP);why='прошло '+(P0.hold/24)+' дн.';break}continue}if(y.low<low){low=y.low;cnt=0}else cnt++;if(cnt>=P0.m){xp=y.close*(1+SLP);why='падение остановилось';break}}
     if(xp==null){j=n-1;xp=c[n-1].close*(1+SLP);why=n>=744?'конец окна (31 день)':'идёт сейчас'}
     return {p:((ep-xp)/ep-FEE*(1+xp/ep))*100/* №482 · шорт: прибыль от цены входа */,h:j-i,pump:(P/base-1)*100,ent:(ep/P-1)*100,t:c[i].time*1000,why}}}}
  return null}
 async function run(){const job=++JOB;NS.err='';NS.res=null;NS.busy='Загружаю список монет…';paint();
  try{const ex=await klGet(API.fut+'exchangeInfo');const now=Date.now();
   const L=ex.symbols.filter(x=>x.contractType==='PERPETUAL'&&x.quoteAsset==='USDT'&&x.onboardDate&&x.onboardDate<now-2*864e5).map(x=>({sym:x.symbol,t0:+x.onboardDate})).sort((a,b)=>b.t0-a.t0).slice(0,NS.n);
   const rows=[];
   for(let i=0;i<L.length;i++){if(job!==JOB)return;const x=L[i];NS.busy=`Качаю часовые свечи первого месяца: ${x.sym.replace('USDT','')} — ${i+1} из ${L.length}`;paint();
    try{let c=CC[x.sym];if(!c){const a=await klGet(API.fut+'klines?symbol='+x.sym+'&interval=1h&startTime='+x.t0+'&limit=744');c=a.map(k=>({time:k[0]/1000,open:+k[1],high:+k[2],low:+k[3],close:+k[4]}));CC[x.sym]=c}
     rows.push({sym:x.sym.replace('USDT',''),t0:x.t0,c})}catch(_){}}
   NS.rows=rows;calc();try{await capi('kv_set',{k:'nlsres',v:NS.res})}catch(_){}}
  catch(e){NS.err='Не удалось: '+(e.message||'нет связи с биржей')}NS.busy='';paint()}
 // №483 · две системы выхода: А — пока падение не остановится (m свечей без нового минимума), Б — держать hold часов
 function calcSys(P0){const T=[];(NS.rows||[]).forEach(r=>{const s=sim(r.c,P0);if(s)T.push(Object.assign({sym:r.sym,t0:r.t0},s))});return T}
function calc(){if(!NS.rows)return;const P0={pump:NS.pump,stall:NS.stall,m:NS.m,sb:NS.sb,hold:0},A=calcSys(P0),B=calcSys(Object.assign({},P0,{hold:168})),C=calcSys(Object.assign({},P0,{hold:72}));/* №484 · Б — 7 дней, В — 3 дня */

  NS.res={T:A,B,C,coins:NS.rows.length,set:P0,hold:NS.hold,t:Date.now(),d1:Math.min(...NS.rows.map(r=>r.t0)),d2:Math.max(...NS.rows.map(r=>r.t0))}}
 const med=a=>{const b=a.slice().sort((x,y)=>x-y);return b.length?b[b.length>>1]:0};
 function body(){const R=NS.res,mk=(v,cur,a,t)=>`<button class="${String(cur)===String(v)?'on':''}" data-a="${a}" data-v="${v}">${t}</button>`;
  let h=`<div class="card"><h3 style="margin:0 0 4px">${ic('trend',20)} Шорт после взлёта новой монеты</h3>
  <p class="hint" style="margin-top:0">В открытие не заходим — монета улетает слишком быстро. Ждём, пока взлёт остановится: несколько часовых свечей подряд без нового максимума, и цена закрылась ниже их минимумов — берём шорт. Держим, пока падение не остановится. Стоп — чуть выше свечей, на которых цена остановилась. Одна сделка на монету, сигнал — в первые 3 недели после запуска.</p>
  <div class="row"><label>Взлёт от первой свечи не меньше</label><div class="pct" style="margin:0;flex:1">${[[0.3,'+30%'],[0.6,'+60%'],[1,'+100%']].map(([v,t])=>mk(v,NS.pump,'nlp',t)).join('')}</div></div>
  <div class="row"><label>Остановка: часовых свечей без нового максимума</label><div class="pct" style="margin:0;flex:1">${[2,3,5].map(v=>mk(v,NS.stall,'nls',v)).join('')}</div></div>
  <div class="row"><label>Система А — выход: часовых свечей без нового минимума</label><div class="pct" style="margin:0;flex:1">${[10,15,20,30].map(v=>mk(v,NS.m,'nlm',v)).join('')}</div></div>
  <p class="hint" style="margin-top:0">Система Б — держать сделку 7 дней, система В — 3 дня (если раньше не сработал стоп).</p>
  <div class="row"><label>Стоп выше свечей остановки</label><div class="pct" style="margin:0;flex:1">${[[0.05,'+5%'],[0.1,'+10%'],[0.2,'+20%']].map(([v,t])=>mk(v,NS.sb,'nlb',t)).join('')}</div></div>
  <div class="row"><label>Последних запусков</label><div class="pct" style="margin:0;flex:1">${[50,100,200].map(v=>mk(v,NS.n,'nln',v)).join('')}</div></div>
  <p class="hint" style="margin-top:0">Комиссия 0,05% и проскальзывание 0,2% учтены на входе и на выходе. Плату за удержание шорта (фандинг) проверка не учитывает — у новых монет она бывает заметной. Монеты, которые биржа уже убрала, в историю не попадают.</p>
  ${NS.busy?`<div class="go" style="background:var(--pan2);border:2px solid var(--ac);color:var(--tx);cursor:default;display:flex;align-items:center;gap:10px;text-align:left"><span class="spin39"></span><span style="flex:1"><b>Идёт проверка…</b><br><span class="sm" style="font-weight:400">${esc(NS.busy)}</span></span></div><button class="btn2" data-a="nlstop" style="width:100%;margin-top:6px;padding:10px">Остановить</button>`
   :`<button class="go b" data-a="nlrun" style="font-size:16px">${ic('play_arrow',20)} ${NS.rows?'Скачать заново и проверить':'Скачать историю и проверить'} ${NS.n} запусков</button>${NS.rows?'<p class="hint">Свечи уже скачаны — меняйте настройки выше, итог пересчитывается сразу.</p>':''}`}
  ${NS.err?`<p class="err">${esc(NS.err)}</p>`:''}</div>`;
  if(!R)return h;const A=R.T||[],B=R.B||[],C=R.C||[];if(!A.length&&!B.length)return h+'<div class="card"><p>С этими настройками сигналов не было — ослабьте условия.</p></div>';
  const span=Math.max(30,(R.d2-R.d1)/864e5),nmA=`А · до остановки падения (${(R.set&&R.set.m)||NS.m} свечей)`,nmB='Б · держать 7 дн.',nmC='В · держать 3 дня';
  const st=T=>{const P=T.map(t=>t.p),n=P.length,win=P.filter(v=>v>0).length,sum=P.reduce((a,b)=>a+b,0),avg=n?sum/n:0,per=n/span*30;return {n,win,sum,avg,per,mo:avg*per,med:med(P),best:n?Math.max(...P):0,worst:n?Math.min(...P):0,hold:med(T.map(t=>t.h))}};
  const SA=st(A),SB=st(B),SC=st(C),win=[['А',SA],['Б',SB],['В',SC]].filter(x=>x[1].n).sort((a,b)=>b[1].mo-a[1].mo)[0][0];
  const col=(S,nm,k)=>{const c=S.avg>=0?'var(--gr)':'var(--rd)';return `<div style="flex:1;min-width:260px;padding:10px 12px;border-radius:8px;border:2px solid ${c}">
   <b>${nm}</b>${win===k?' <span class="tag long">лучше</span>':''}
   <div class="sgrid" style="margin-top:6px"><div><small>В плюс</small><span class="g">${S.win} из ${S.n}</span></div><div><small>В среднем</small><b class="${S.avg>=0?'g':'r'}">${S.avg>=0?'+':''}${fmt(S.avg,1)}%</b></div><div><small>Типично</small>${S.med>=0?'+':''}${fmt(S.med,1)}%</div><div><small>Лучшая / худшая</small><span class="g">+${fmt(S.best,0)}%</span> / <span class="r">${fmt(S.worst,0)}%</span></div><div><small>Держим (типично)</small>${S.hold<48?S.hold+' ч':fmt(S.hold/24,1)+' дн.'}</div></div>
   <div class="sm" style="margin-top:6px">${ic('wallet',14)} $1 000 в каждую сделку: <b style="color:${c}">≈ ${USD(S.mo*10)} в месяц</b> · за всю проверку ${USD(S.sum*10)}</div></div>`};
  h+=`<div class="card"><h3 style="margin:0 0 6px">${ic('flag',20)} Три системы на одних и тех же сделках</h3><p class="sm mu" style="margin:0 0 8px">${R.coins} запусков с ${new Date(R.d1).toLocaleDateString('ru-RU')} по ${new Date(R.d2).toLocaleDateString('ru-RU')} · сигналов ${SA.n} (≈ ${fmt(SA.per,1)} в месяц). Вход и стоп одинаковые, отличается только выход.</p>
  <div class="row" style="gap:8px;flex-wrap:wrap;margin:0">${col(SA,nmA,'А')}${col(SC,nmC,'В')}${col(SB,nmB,'Б')}</div>
  <p class="hint">Без плеча, комиссия и проскальзывание учтены. Сделок пока мало — это прикидка, а не гарантия. Проверьте обе системы на демо-счёте.</p></div>`;
  const bm={},cm={};B.forEach(t=>bm[t.sym]=t);C.forEach(t=>cm[t.sym]=t);const cell=t=>t?`<b class="${t.p>=0?'g':'r'}">${t.p>=0?'+':''}${fmt(t.p,1)}%</b><div class="mu" style="font-size:11px">${t.h<48?t.h+' ч':fmt(t.h/24,1)+' дн.'} · ${esc(t.why)}</div>`:'—';
  h+=`<div class="card"><h3 style="margin:0 0 6px">Сделки по монетам</h3><div style="overflow-x:auto"><table class="bt7" style="width:100%;font-size:13px"><tr><th>Монета</th><th>Шорт открыт</th><th>Взлёт</th><th>А · до остановки</th><th>В · 3 дня</th><th>Б · 7 дней</th></tr>
  ${A.slice().sort((a,b)=>b.t-a.t).map(t=>`<tr><td><b>${esc(t.sym)}</b></td><td>${new Date(t.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'})}</td><td class="g">+${fmt(t.pump,0)}%</td><td>${cell(t)}</td><td>${cell(cm[t.sym])}</td><td>${cell(bm[t.sym])}</td></tr>`).join('')}</table></div></div>`;
  return h}
 function paint(){const e=document.getElementById('nlsbox');if(e)e.innerHTML=body()}
 const _lb51=lsBody;lsBody=function(){if(!NS.res&&!NS.busy&&!NS.loaded){NS.loaded=1;capi('kv_get',{k:'nlsres'}).then(j=>{if(j.v&&j.v.T&&!NS.res){NS.res=j.v;paint()}}).catch(()=>{})}return `<div id="nlsbox">${body()}</div>`+_lb51()};
 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;if(!['nlp','nls','nlm','nlh','nlb','nln','nlrun','nlstop'].includes(a))return;e.stopPropagation();e.preventDefault();
  if(a==='nlrun'){run();return}if(a==='nlstop'){JOB++;NS.busy='';paint();return}if(NS.busy)return;
  if(a==='nlp')NS.pump=+v;else if(a==='nls')NS.stall=+v;else if(a==='nlm')NS.m=+v;else if(a==='nlh')NS.hold=+v;else if(a==='nlb')NS.sb=+v;else if(a==='nln')NS.n=+v;
  try{localStorage.setItem('nlsset',JSON.stringify({pump:NS.pump,stall:NS.stall,m:NS.m,hold:NS.hold,sb:NS.sb,n:NS.n}))}catch(_){}
  if(NS.rows)calc();paint()},true);
})();
