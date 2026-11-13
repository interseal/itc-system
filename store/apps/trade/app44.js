// ИТК Трейдинг — «Листинги» (11.11.2026): как ведут себя новые монеты в первые часы торгов на Binance.
// Берём последние 50/100/200 запусков (фьючерсы или спот), качаем минутные свечи с первой минуты торгов
// и проверяем стратегии: лонг с открытия с выходом при откате от пика, шорт после разворота, и их сочетание.
"use strict";
let LST={mkt:'fut',n:100,h:24,slip:0.5,busy:'',res:null,err:''};try{Object.assign(LST,JSON.parse(localStorage.getItem('lstset')||'{}'),{busy:'',res:null,err:''})}catch(_){}
let LSTJOB=0;const LSTC={};// кэш свечей
const LSTV=[
 ['L5','Лонг с первой минуты · выход при откате 5% от пика','L',{tr:0.05}],
 ['L10','Лонг с первой минуты · выход при откате 10% от пика','L',{tr:0.10}],
 ['L20','Лонг с первой минуты · выход при откате 20% от пика','L',{tr:0.20}],
 ['LT15','Лонг с первой минуты · выход через 15 минут','LT',{m:15}],
 ['LT60','Лонг с первой минуты · выход через 1 час','LT',{m:60}],
 ['S10','Шорт после разворота: −10% от пика и новый минимум · выход при отскоке 10%','S',{d:0.10,tr:0.10}],
 ['S20','Шорт после разворота: −20% от пика и новый минимум · выход при отскоке 10%','S',{d:0.20,tr:0.10}],
 ['S20w','Шорт после разворота: −20% от пика · выход при отскоке 20% (держим дольше)','S',{d:0.20,tr:0.20}],
 ['ST60','Шорт через 1 час после запуска · держать до конца окна','ST',{m:60}],
 ['LS','Лонг (откат 10%) и потом шорт после разворота (−20%)','LS',{}]];
const lsFee=()=>LST.mkt==='fut'?0.0005:0.001;
function lsLong(c,tr,maxI,s,f){const ep=c[0].close*(1+s);let pk=Math.max(c[0].high,c[0].close),xp=null,xi=maxI;
 for(let i=1;i<=maxI;i++){const st=pk*(1-tr);if(c[i].low<=st){xp=Math.min(c[i].open,st)*(1-s);xi=i;break}pk=Math.max(pk,c[i].high)}
 if(xp==null)xp=c[maxI].close*(1-s);return {p:(xp*(1-f)/(ep*(1+f))-1)*100,xi}}
function lsLongT(c,m,maxI,s,f){const ep=c[0].close*(1+s),j=Math.min(maxI,m),xp=c[j].close*(1-s);return {p:(xp*(1-f)/(ep*(1+f))-1)*100}}
function lsShort(c,d,tr,maxI,s,f,from){let pk=0;for(let i=0;i<=Math.min(from||0,maxI);i++)pk=Math.max(pk,c[i].high);
 for(let i=Math.max(5,(from||0)+1);i<=maxI;i++){let lo=1e18;for(let q=i-5;q<i;q++)lo=Math.min(lo,c[q].low);
  if(c[i].close<=pk*(1-d)&&c[i].close<lo){const ep=c[i].close*(1-s),stop=pk*1.02;let low=c[i].close,xp=null;
   for(let j=i+1;j<=maxI;j++){if(c[j].high>=stop){xp=Math.max(c[j].open,stop)*(1+s);break}const rb=low*(1+tr);if(c[j].high>=rb){xp=Math.max(c[j].open,rb)*(1+s);break}low=Math.min(low,c[j].low)}
   if(xp==null)xp=c[maxI].close*(1+s);return {p:((ep-xp)/ep-f*(1+xp/ep))*100/* №482 · шорт: прибыль от цены входа */,i}}
  pk=Math.max(pk,c[i].high)}return null}
function lsShortT(c,m,maxI,s,f){if(maxI<=m)return null;const ep=c[m].close*(1-s),xp=c[maxI].close*(1+s);return {p:((ep-xp)/ep-f*(1+xp/ep))*100/* №482 · шорт: прибыль от цены входа */}}
function lsSim(c){const maxI=c.length-1,s=LST.slip/100,f=lsFee(),o={};
 for(const [id,,k,P] of LSTV){let r=null;
  if(k==='L')r=lsLong(c,P.tr,maxI,s,f);else if(k==='LT')r=lsLongT(c,P.m,maxI,s,f);else if(k==='S')r=lsShort(c,P.d,P.tr,maxI,s,f,0);else if(k==='ST')r=lsShortT(c,P.m,maxI,s,f);
  else if(k==='LS'){const a=lsLong(c,0.10,maxI,s,f),b=lsShort(c,0.20,0.10,maxI,s,f,a.xi);r={p:a.p+(b?b.p:0)}}
  o[id]=r?+r.p.toFixed(2):null}
 // как вела себя монета
 const o0=c[0].open,h60=Math.max(...c.slice(0,61).map(x=>x.high));let pi=0;for(let i=1;i<=maxI;i++)if(c[i].high>c[pi].high)pi=i;let mn=c[pi].low;for(let i=pi;i<=maxI;i++)mn=Math.min(mn,c[i].low);
 return {o,pump:(h60/c[0].close-1)*100,pumpO:(h60/o0-1)*100,peakMin:pi,drop:(mn/c[pi].high-1)*100,end:(c[maxI].close/c[0].close-1)*100}}
async function lsCoins(){if(LST.mkt==='fut'){const ex=await klGet(API.fut+'exchangeInfo');return ex.symbols.filter(x=>x.contractType==='PERPETUAL'&&x.quoteAsset==='USDT'&&x.onboardDate).map(x=>({sym:x.symbol,t0:+x.onboardDate}))}
 const ex=await klGet(API.spot+'exchangeInfo');const L=ex.symbols.filter(x=>x.quoteAsset==='USDT'&&x.status==='TRADING').map(x=>x.symbol);let F={};try{const j=await capi('kv_get',{k:'spotfirst'});F=j.v||{}}catch(_){}
 const need=L.filter(s=>!F[s]);for(let i=0;i<need.length;i++){if(LST.job!==LSTJOB)return [];LST.busy=`Ищу даты запуска на споте: ${i+1} из ${need.length} (один раз, дальше запомнится)`;lsPaint();
  try{const k=await klGet(API.spot+'klines?symbol='+need[i]+'&interval=1m&startTime=0&limit=1');if(k[0])F[need[i]]=k[0][0]}catch(_){}}
 if(need.length)try{await capi('kv_set',{k:'spotfirst',v:F})}catch(_){}
 return L.filter(s=>F[s]).map(s=>({sym:s,t0:F[s]}))}
async function lsRun(){const job=++LSTJOB;LST.job=job;LST.err='';LST.res=null;LST.busy='Загружаю список монет…';lsPaint();
 try{const now=Date.now(),need=LST.h*60+1;let L=(await lsCoins()).filter(x=>x.t0<now-LST.h*3600e3-60e3).sort((a,b)=>b.t0-a.t0).slice(0,LST.n);if(job!==LSTJOB)return;
  const rows=[],lim=LST.mkt==='fut'?1500:1000;
  for(let i=0;i<L.length;i++){if(job!==LSTJOB)return;const x=L[i],key=LST.mkt+'|'+x.sym+'|'+LST.h;LST.busy=`Качаю первые ${LST.h} ч торгов: ${x.sym.replace('USDT','')} — ${i+1} из ${L.length}`;lsPaint();
   try{let c=LSTC[key];if(!c){c=[];let t=x.t0;while(c.length<need){const a=await klGet(API[LST.mkt]+'klines?symbol='+x.sym+'&interval=1m&startTime='+t+'&limit='+lim);if(!a.length)break;a.forEach(k=>c.push({time:k[0]/1000,open:+k[1],high:+k[2],low:+k[3],close:+k[4]}));t=a[a.length-1][0]+60e3;if(a.length<lim)break}c=c.slice(0,need);LSTC[key]=c}
    if(c.length<Math.min(need,120))continue;rows.push(Object.assign({sym:x.sym.replace('USDT',''),t0:c[0].time*1000},lsSim(c)))}catch(_){}}
  LST.res={rows,mkt:LST.mkt,h:LST.h,slip:LST.slip,t:Date.now()};try{await capi('kv_set',{k:'listres',v:LST.res})}catch(_){}}
 catch(e){LST.err='Не удалось: '+(e.message||'нет связи с биржей')}LST.busy='';lsPaint()}
// ---------- итоги ----------
const lsMed=a=>{const b=a.slice().sort((x,y)=>x-y);return b.length?b[b.length>>1]:0};
function lsSum(R){const rows=R.rows,span=rows.length>1?(Math.max(...rows.map(r=>r.t0))-Math.min(...rows.map(r=>r.t0)))/864e5:30;
 return LSTV.map(([id,name])=>{const P=rows.map(r=>r.o[id]).filter(v=>v!=null);const n=P.length,sum=P.reduce((a,b)=>a+b,0);return {id,name,n,win:P.filter(v=>v>0).length,avg:n?sum/n:0,med:lsMed(P),best:n?Math.max(...P):0,worst:n?Math.min(...P):0,sum,mo:span>0?sum/span*30:0,span}}).sort((a,b)=>b.avg-a.avg)}
function lsBody(){const R=LST.res,mk=(v,cur,a,t)=>`<button class="${cur===v?'on':''}" data-a="${a}" data-v="${v}">${t}</button>`;
 let h=`<div class="card"><h3 style="margin:0 0 4px">${ic('rocket',20)} Новые монеты на Binance</h3><p class="hint" style="margin-top:0">Как ведут себя монеты в первые часы после запуска торгов: сильно ли растут, когда разворачиваются и что выгоднее — лонг с открытия или шорт после разворота. Проверка на настоящей истории последних запусков.</p>
 <div class="row"><label>Рынок</label><div class="pct" style="margin:0;flex:1">${mk('fut',LST.mkt,'lsm','Фьючерсы')}${mk('spot',LST.mkt,'lsm','Спот')}</div></div>
 <div class="row"><label>Последних запусков</label><div class="pct" style="margin:0;flex:1">${[50,100,200].map(v=>mk(v,LST.n,'lsn',v)).join('')}</div></div>
 <div class="row"><label>Смотреть первые</label><div class="pct" style="margin:0;flex:1">${[[6,'6 часов'],[24,'24 часа'],[72,'3 дня']].map(([v,t])=>mk(v,LST.h,'lsh',t)).join('')}</div></div>
 <div class="row"><label>Проскальзывание</label><div class="pct" style="margin:0;flex:1">${[0.2,0.5,1,2].map(v=>mk(v,LST.slip,'lss',String(v).replace('.',',')+'%')).join('')}</div></div>
 <p class="hint" style="margin-top:0">В первые минуты после запуска цена скачет, и заявка исполняется хуже, чем на графике. Проскальзывание учитывается и на входе, и на выходе, комиссия биржи тоже.</p>
 ${LST.busy?`<div class="go" style="background:var(--pan2);border:2px solid var(--ac);color:var(--tx);cursor:default;display:flex;align-items:center;gap:10px;text-align:left"><span class="spin39"></span><span style="flex:1"><b>Идёт проверка…</b><br><span class="sm" style="font-weight:400">${esc(LST.busy)}</span></span></div><button class="btn2" data-a="lsstop" style="width:100%;margin-top:6px;padding:10px">Остановить</button>`
  :`<button class="go b" data-a="lsrun" style="font-size:16px">${ic('play_arrow',20)} Скачать историю и проверить ${LST.n} запусков</button><p class="hint">${LST.mkt==='spot'?'В первый раз программа ищет даты запуска всех монет спота — это 2–3 минуты, потом запоминает. ':''}Примерно ${Math.round(LST.n*(LST.h>24?3:1)*0.3/60)+1} мин.</p>`}
 ${LST.err?`<p class="err">${esc(LST.err)}</p>`:''}</div>`;
 if(!R||!R.rows)return h;if(!R.rows.length)return h+'<div class="card"><p>Не удалось скачать ни одного запуска — попробуйте ещё раз.</p></div>';
 const S=lsSum(R),best=S[0],rows=R.rows,d1=new Date(Math.min(...rows.map(r=>r.t0))).toLocaleDateString('ru-RU'),d2=new Date(Math.max(...rows.map(r=>r.t0))).toLocaleDateString('ru-RU');
 const pm=lsMed(rows.map(r=>r.pump)),pk=lsMed(rows.map(r=>r.peakMin)),dr=lsMed(rows.map(r=>r.drop)),en=lsMed(rows.map(r=>r.end)),up=rows.filter(r=>r.pump>=20).length;
 h+=`<div class="card"><h3 style="margin:0 0 6px">${ic('flag',20)} Вывод</h3><p class="sm mu" style="margin:0 0 6px">${rows.length} запусков на ${R.mkt==='fut'?'фьючерсах':'споте'} с ${d1} по ${d2} · первые ${R.h} ч · проскальзывание ${String(R.slip).replace('.',',')}%</p>
 <div class="sgrid"><div><small>Рост за первый час (типично)</small><b class="${pm>=0?'g':'r'}">${pm>=0?'+':''}${fmt(pm,1)}%</b></div><div><small>Выросли на 20% и больше</small>${up} из ${rows.length}</div><div><small>Пик наступает через</small>≈ ${pk<60?Math.round(pk)+' мин':fmt(pk/60,1)+' ч'}</div><div><small>Падение от пика (типично)</small><b class="r">${fmt(dr,1)}%</b></div><div><small>Через ${R.h} ч от первой минуты</small><b class="${en>=0?'g':'r'}">${en>=0?'+':''}${fmt(en,1)}%</b></div></div>
 <div class="wbox" style="margin-top:8px;border:2px solid ${best.avg>0?'var(--gr)':'var(--rd)'}"><div class="sm mu">Лучшая стратегия</div><b>${esc(best.name)}</b>
 <div class="sm" style="margin-top:4px">В среднем <b class="${best.avg>=0?'g':'r'}">${best.avg>=0?'+':''}${fmt(best.avg,2)}%</b> на запуск · в плюсе ${best.win} из ${best.n} · при $100 на каждый запуск: <b class="${best.sum>=0?'g':'r'}">${USD(best.sum)}</b> за ${best.n} запусков (≈ ${USD(best.mo)} в месяц)</div></div>
 <p class="hint">«Типично» — середина: у половины запусков больше, у половины меньше. Так один безумный запуск не искажает картину.</p></div>`;
 h+=`<div class="card"><h3 style="margin:0 0 6px">Все стратегии</h3>${S.map((x,i)=>`<div class="plan ${x.avg>0?'long':'short'}" style="cursor:default;padding:8px 10px"><b>${i+1}. ${esc(x.name)}</b>
  <div class="sm" style="margin-top:2px">Сделок ${x.n} · в плюсе ${x.win} (${x.n?fmt(x.win/x.n*100,0):0}%) · в среднем <b class="${x.avg>=0?'g':'r'}">${x.avg>=0?'+':''}${fmt(x.avg,2)}%</b> · типично ${x.med>=0?'+':''}${fmt(x.med,2)}% · лучшая ${x.best>=0?'+':''}${fmt(x.best,0)}% · худшая ${fmt(x.worst,0)}%</div>
  <div class="sm">При $100 на каждый запуск: <b class="${x.sum>=0?'g':'r'}">${USD(x.sum)}</b> · ≈ ${USD(x.mo)} в месяц</div></div>`).join('')}</div>`;
 const tb=rows.slice().sort((a,b)=>b.t0-a.t0);
 h+=`<div class="card"><h3 style="margin:0 0 6px">По каждой монете</h3><div style="overflow-x:auto"><table class="bt7" style="width:100%;font-size:13px"><tr><th>Монета</th><th>Запуск</th><th>Рост за 1 ч</th><th>Пик через</th><th>Падение от пика</th><th>Лонг (откат 10%)</th><th>Шорт (−20%)</th></tr>
 ${tb.map(r=>`<tr><td><b>${r.sym}</b></td><td>${new Date(r.t0).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'})}</td><td class="${r.pump>=0?'g':'r'}">${r.pump>=0?'+':''}${fmt(r.pump,1)}%</td><td>${r.peakMin<60?r.peakMin+' мин':fmt(r.peakMin/60,1)+' ч'}</td><td class="r">${fmt(r.drop,1)}%</td><td class="${(r.o.L10||0)>=0?'g':'r'}">${r.o.L10==null?'—':(r.o.L10>=0?'+':'')+fmt(r.o.L10,1)+'%'}</td><td class="${(r.o.S20||0)>=0?'g':'r'}">${r.o.S20==null?'нет входа':(r.o.S20>=0?'+':'')+fmt(r.o.S20,1)+'%'}</td></tr>`).join('')}</table></div></div>`;
 return h}
function lsPaint(){const e=$('#lablst');if(e)e.innerHTML=lsBody()}
LABT.splice(3,0,['list','rocket','Листинги']);
const _lv44=labView;labView=function(){if((UI.labTab||'tests')!=='list')return _lv44();if(!LST.res&&!LST.busy&&!LST.loaded){LST.loaded=1;capi('kv_get',{k:'listres'}).then(j=>{if(j.v&&j.v.rows&&!LST.res){LST.res=j.v;lsPaint()}}).catch(()=>{})}
 const h=_lv44(),i=h.indexOf('<div class="labtabs">'),j=i>=0?h.indexOf('</div>',i)+6:0;return h.slice(0,j)+`<div id="lablst">${lsBody()}</div>`};
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;if(!['lsm','lsn','lsh','lss','lsrun','lsstop'].includes(a))return;e.stopPropagation();e.preventDefault();
 if(a==='lsrun'){lsRun();return}if(a==='lsstop'){LSTJOB++;LST.busy='';lsPaint();return}
 if(LST.busy)return;if(a==='lsm')LST.mkt=v;else if(a==='lsn')LST.n=+v;else if(a==='lsh')LST.h=+v;else if(a==='lss')LST.slip=+v;
 try{localStorage.setItem('lstset',JSON.stringify({mkt:LST.mkt,n:LST.n,h:LST.h,slip:LST.slip}))}catch(_){}lsPaint()},true);
render();
