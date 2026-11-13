// ИТК Трейдинг — «Анализ → Монеты»: каталог всех монет фьючерсов Binance, группы, тесты и база результатов (10.11.2026).
// • Каталог: все бессрочные фьючерсы к USDT (около 500), объём за сутки, возраст; по кнопке — характеристики за 60 дней:
//   насколько монета ходит относительно биткоина (бета), насколько повторяет его (связь), размах за день.
// • Группы: по объёму, по резкости к биткоину, по размаху, по возрасту, проверенные/не проверенные, свои группы.
// • Тест выбранных монет: итог каждой монеты по каждой системе сохраняется на сервере; уже проверенные можно не пересчитывать.
// • «Итог из базы» и «Сравнение групп» — без нового теста, по сохранённым результатам.
// • «Длина стопа и тейка» ×1 / ×1,5 / ×2 / ×3 — для резких монет; стратегии «По сигналу биткоина», «Дивергенция RSI», «Возврат к средней».
"use strict";
// ---------- свечи биткоина рядом со свечами монеты (для стратегии «По сигналу биткоина») ----------
const BCACHE={};
const _hk34=histK;histK=async function(mkt,sym,tf,days,warm,onp){const c=await _hk34(mkt,sym,tf,days,warm,onp);
 if(warm>0&&c.length>2){try{let b;if(sym==='BTC')b=c;else{const key=mkt+'|'+tf+'|'+days+'|'+warm,o=BCACHE[key];if(o&&Date.now()-o.at<15*60e3)b=o.c;else{b=await _hk34(mkt,'BTC',tf,days,warm);BCACHE[key]={c:b,at:Date.now()}}}
  window.BTCK={dt:c[1].time-c[0].time,c:b,idx:new Map(b.map((x,j)=>[x.time,j]))}}catch(_){}}return c};
// ---------- правила и подбор настроек для новых стратегий ----------
Object.assign(ITS.rules||{},{btct:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR от входа. Выход — как только биткоин разворачивается (закрылся по другую сторону быстрой средней).'},btcl:{stop:'atr',k:1.5,take:'sig',txt:'Стоп 1,5 ATR от входа. Выход — через заданное число свечей или по обратному сигналу биткоина.'},
 div:{stop:'swing',n:10,take:'sig',txt:'Стоп за минимумом/максимумом последних 10 свечей. Выход — RSI в противоположной зоне.'},
 zrev:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR от входа. Выход — цена вернулась к средней.'}});
Object.assign(PGRID,{btct:[{f:9,s:21,rs:20,r:1},{f:5,s:13,rs:10,r:1},{f:9,s:21,rs:20,r:0},{f:20,s:50,rs:30,r:1}],btcl:[{n:20,h:8,lag:1},{n:10,h:4,lag:1},{n:20,h:12,lag:0},{n:40,h:8,lag:1}],div:[{n:14,k:3,w:60},{n:9,k:3,w:40},{n:14,k:5,w:80}],zrev:[{n:50,z:2.5},{n:20,z:2},{n:100,z:3}]});
// ---------- длина стопа и тейка ----------
const SMN={1:'обычная',1.5:'×1,5',2:'×2',3:'×3'};
const _fc34=fChips;fChips=function(scope){const v=SMul();return _fc34(scope)+`<div class="row" style="align-items:flex-start"><label>Стоп и тейк длиннее</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${[1,1.5,2,3].map(k=>`<button class="${v===k?'on':''}" data-a="btsm" data-v="${k}" data-s="${scope}">${k===1?'Обычные':SMN[k]}</button>`).join('')}</div></div>`};
const _sfb34=sysFromBT;sysFromBT=function(){const S=_sfb34();if(S)S.sm=SMul();return S};
const _tdr34=tdRule;tdRule=function(s){const t=_tdr34(s);return s.sys&&s.sys.sm>1?t+' · стоп и тейк '+SMN[s.sys.sm]:t};
// ---------- каталог монет ----------
let CAT=null,CATBUSY='',CSUM={},CGRP={},CSEL=new Set(),CGJOB=0,CGB=null;
const capi=(a,d)=>tdapi(a,d);
async function catInit(){try{const [a,b,g]=await Promise.all([capi('kv_get',{k:'coincat'}),capi('kv_get',{k:'coinsum'}),capi('kv_get',{k:'cgroups'})]);CAT=a.v||null;CSUM=b.v||{};CGRP=g.v||{}}catch(_){CAT=CAT||null}
 if(!CAT){try{CAT=LS.get('coincat',null)}catch(_){}}if(!CAT||Date.now()-CAT.t>3*86400e3)await catLoad();coinsPaint()}
async function catLoad(){CATBUSY='Загружаю список монет с Binance…';coinsPaint();
 try{const ex=await klGet(API.fut+'exchangeInfo'),tk=await klGet(API.fut+'ticker/24hr'),vol={},ch={};tk.forEach(t=>{vol[t.symbol]=+t.quoteVolume;ch[t.symbol]=+t.priceChangePercent});
  const old=new Map(((CAT&&CAT.coins)||[]).map(x=>[x.s,x]));
  const coins=ex.symbols.filter(s=>s.contractType==='PERPETUAL'&&s.quoteAsset==='USDT'&&s.status==='TRADING').map(s=>{const o=old.get(s.baseAsset)||{};return Object.assign({},o,{s:s.baseAsset,v:vol[s.symbol]||0,ch:ch[s.symbol]||0,ob:s.onboardDate||0})}).sort((a,b)=>b.v-a.v);
  coins.forEach((x,i)=>x.r=i+1);CAT={t:Date.now(),coins,mt:CAT&&CAT.mt||0};await catSave()}catch(e){CATBUSY='';toast('Не удалось загрузить список монет: '+(e.message||''));coinsPaint();return}
 CATBUSY='';coinsPaint()}
async function catSave(){try{LS.set('coincat',CAT)}catch(_){}try{await capi('kv_set',{k:'coincat',v:CAT})}catch(_){}}
// характеристики: бета и связь с биткоином, размах за день — по дневным свечам за 60 дней
async function catMetrics(){if(!CAT)return;const job=++CGJOB;const day=async s=>(await klGet(API.fut+'klines?symbol='+pair(s)+'&interval=1d&limit=61')).map(x=>({t:x[0],h:+x[2],l:+x[3],c:+x[4]}));
 let B;try{B=await day('BTC')}catch(_){toast('Не удалось загрузить биткоин');return}const bm=new Map(B.map((x,i)=>[x.t,i]));
 const L=CAT.coins;for(let i=0;i<L.length;i++){if(job!==CGJOB)break;const x=L[i];CATBUSY=`Считаю характеристики: ${x.s} — ${i+1} из ${L.length}`;if(i%5===0)coinsPaint();
  try{const d=await day(x.s),ra=[],rb=[];for(let j=1;j<d.length;j++){const k=bm.get(d[j].t),k0=bm.get(d[j-1].t);if(k==null||k0==null)continue;ra.push(Math.log(d[j].c/d[j-1].c));rb.push(Math.log(B[k].c/B[k0].c))}
   const m=a=>a.reduce((s,v)=>s+v,0)/a.length,ma=m(ra),mb=m(rb);let cv=0,va=0,vb=0;for(let j=0;j<ra.length;j++){cv+=(ra[j]-ma)*(rb[j]-mb);va+=(ra[j]-ma)**2;vb+=(rb[j]-mb)**2}
   if(ra.length>=20){x.b=+(cv/vb).toFixed(2);x.cr=+(cv/Math.sqrt(va*vb)).toFixed(2)}const lst=d.slice(-30);x.rg=+(lst.reduce((s,y)=>s+(y.h-y.l)/y.c,0)/lst.length*100).toFixed(2);x.mt=Date.now()}catch(_){}
  if(i%25===24)await catSave()}
 CAT.mt=Date.now();await catSave();CATBUSY='';coinsPaint()}
// ---------- группы ----------
const CG_DEF=[
 ['Объём торгов',[['v1','Топ-20',x=>x.r<=20],['v2','21–60',x=>x.r>20&&x.r<=60],['v3','61–150',x=>x.r>60&&x.r<=150],['v4','Мелкие',x=>x.r>150]]],
 ['Ходит относительно биткоина',[['b1','Спокойнее BTC (до 0,8)',x=>x.b!=null&&x.b<0.8],['b2','Как BTC (0,8–1,3)',x=>x.b>=0.8&&x.b<1.3],['b3','Резкие (1,3–2)',x=>x.b>=1.3&&x.b<2],['b4','Очень резкие (от 2)',x=>x.b>=2]]],
 ['Размах за день',[['r1','До 3%',x=>x.rg!=null&&x.rg<3],['r2','3–6%',x=>x.rg>=3&&x.rg<6],['r3','Больше 6%',x=>x.rg>=6]]],
 ['Возраст',[['a1','Новые (до полугода)',x=>x.ob&&Date.now()-x.ob<182*864e5],['a2','Полгода–год',x=>x.ob&&Date.now()-x.ob>=182*864e5&&Date.now()-x.ob<365*864e5],['a3','Старше года',x=>!x.ob||Date.now()-x.ob>=365*864e5]]],
 ['Проверка',[['t0','Ещё не проверены',x=>!CSUM[x.s]],['t1','Уже проверены',x=>!!CSUM[x.s]],['t2','В плюсе по лучшей системе',x=>CSUM[x.s]&&CSUM[x.s].sum>0]]]];
const cgSel34=()=>new Set(Array.isArray(UI.cgf)?UI.cgf:['v1']);
function catFiltered(){if(!CAT)return [];const S=cgSel34(),q=(UI.cgq||'').trim().toUpperCase();let L=CAT.coins;
 for(const [,items] of CG_DEF){const on=items.filter(it=>S.has(it[0]));if(on.length)L=L.filter(x=>on.some(it=>it[2](x)))}
 const own=Object.keys(CGRP).filter(n=>S.has('u:'+n));if(own.length){const set=new Set(own.flatMap(n=>CGRP[n]));L=L.filter(x=>set.has(x.s))}
 if(q)L=L.filter(x=>x.s.includes(q));
 const so=UI.cgsort||'v',f={v:(a,b)=>b.v-a.v,b:(a,b)=>(b.b??-9)-(a.b??-9),rg:(a,b)=>(b.rg??-9)-(a.rg??-9),ob:(a,b)=>(b.ob||0)-(a.ob||0),res:(a,b)=>{const m=x=>{const c=CSUM[x.s];return c?(c.mo??c.sum):-1e9};return m(b)-m(a)}}[so];return L.slice().sort(f)}
const mln=v=>v>=1e9?fmt(v/1e9,1)+' млрд':fmt(v/1e6,0)+' млн';
function coinRow(x){const s=CSUM[x.s],on=CSEL.has(x.s),age=x.ob?Math.max(0,Math.round((Date.now()-x.ob)/2592e6)):null;
 return `<tr><td><input type="checkbox" data-a2="csel" data-v="${x.s}" ${on?'checked':''}></td><td><b>${x.s}</b></td><td>${mln(x.v)}</td><td>${x.b!=null?fmt(x.b,2)+'×':'—'}</td><td>${x.rg!=null?fmt(x.rg,1)+'%':'—'}</td><td>${age!=null?age+' мес':'—'}</td>
 <td class="sm">${s?`<span class="${s.sum>0?'g':'r'}">${s.sname} · ${baTfn(s.tf)} · ${s.mo!=null?USD(s.mo)+' в мес.':USD(s.sum)}</span> <span class="mu">${new Date(s.t).toLocaleDateString('ru-RU')}</span>`:'<span class="mu">не проверена</span>'}</td></tr>`}
function coinsBody(){if(!CAT&&!CATBUSY){catInit();return '<p class="mu">Загружаю каталог…</p>'}
 const L=catFiltered(),S=cgSel34(),show=UI.cgshow||60,mtOld=!CAT||!CAT.mt;
 const chips=CG_DEF.map(([t,items])=>`<div class="row" style="align-items:flex-start"><label>${t}</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${items.map(it=>`<button class="${S.has(it[0])?'on':''}" data-a="cgf" data-v="${it[0]}">${it[1]}${CAT?' · '+CAT.coins.filter(it[2]).length:''}</button>`).join('')}</div></div>`).join('')
  +(Object.keys(CGRP).length?`<div class="row" style="align-items:flex-start"><label>Свои группы</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${Object.keys(CGRP).map(n=>`<button class="${S.has('u:'+n)?'on':''}" data-a="cgf" data-v="u:${esc(n)}">${esc(n)} · ${CGRP[n].length}</button><button class="btn2 sm" data-a="cgdel" data-v="${esc(n)}" title="Удалить группу">${ic('trash',14)}</button>`).join('')}</div></div>`:'');
 return `${CATBUSY?`<div class="wbox"><b>${CATBUSY}</b> <button class="btn2 sm" data-a="cgstop">Остановить</button></div>`:''}
 <div class="wbox"><b>${CAT?CAT.coins.length:0} монет</b> <span class="mu sm">фьючерсы Binance к USDT · список от ${CAT?new Date(CAT.t).toLocaleString('ru-RU'):'—'} · характеристики ${CAT&&CAT.mt?'от '+new Date(CAT.mt).toLocaleDateString('ru-RU'):'ещё не посчитаны'}</span>
 <div class="row" style="margin:6px 0 0;gap:6px;flex-wrap:wrap"><button class="btn2 sm" data-a="catload">Обновить список</button><button class="btn2 sm" data-a="catmt">${ic('history',16)} Посчитать характеристики (бета, размах) — около 2–3 минут</button></div>
 ${mtOld?'<p class="hint" style="margin:6px 0 0">Чтобы работали группы «Ходит относительно биткоина» и «Размах за день», нажмите «Посчитать характеристики».</p>':''}</div>
 <p class="hint"><b>Бета</b> — во сколько раз сильнее биткоина ходит монета: 2× — когда биткоин растёт на 1%, монета в среднем растёт на 2%. <b>Размах</b> — среднее движение за день. Внутри одной строки группы складываются, разные строки — пересекаются (например, «Топ-20» и «Резкие» — резкие среди топ-20).</p>
 ${chips}
 <div class="row" style="gap:6px;flex-wrap:wrap"><div class="in" style="flex:1;min-width:140px"><input id="cgq" placeholder="Поиск монеты" value="${esc(UI.cgq||'')}"></div>
  <div class="in" style="flex:0 0 auto"><select id="cgsort">${[['v','по объёму'],['b','по резкости'],['rg','по размаху'],['ob','сначала новые'],['res','по итогу теста']].map(o=>`<option value="${o[0]}" ${(UI.cgsort||'v')===o[0]?'selected':''}>${o[1]}</option>`).join('')}</select></div></div>
 <div class="row" style="gap:6px;flex-wrap:wrap;margin:6px 0"><b style="flex:1">Найдено ${L.length} · выбрано ${CSEL.size}</b><button class="btn2 sm" data-a="csall">Выбрать найденные</button><button class="btn2 sm" data-a="csnone">Снять выбор</button><button class="btn2 sm" data-a="cssave">Сохранить выбор как группу</button></div>
 <div style="overflow-x:auto"><table class="bt7"><tr><th></th><th>Монета</th><th>Объём 24ч</th><th>Бета к BTC</th><th>Размах</th><th>Возраст</th><th>Лучшая система</th></tr>${L.slice(0,show).map(coinRow).join('')}</table></div>
 ${L.length>show?`<button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="cgmore">Показать ещё (${L.length-show})</button>`:''}`}
function coinsTest(){const n=CSEL.size;return `<h3 style="margin:0 0 8px">${ic('coins',20)} Тест выбранных монет</h3>
 ${CGB&&CGB.busy?`<div class="wbox"><b>${CGB.busy}</b> <button class="btn2 sm" data-a="cgstop">Остановить</button></div>`:''}
 ${spChips()}<div class="row" style="align-items:flex-start"><label>Свечи</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${SPD10[spd()].map(t=>`<button class="${tf10().includes(t)?'on':''}" data-a="tfsel" data-v="${t}">${baTfn(t)}</button>`).join('')}</div></div>
 ${fChips('cg')}
 <div class="row"><label>Уже проверенные</label><div class="pct" style="margin:0;flex:1"><button class="${UI.cgskip!==0?'on':''}" data-a="cgskip" data-v="1">Не пересчитывать (за 7 дней)</button><button class="${UI.cgskip===0?'on':''}" data-a="cgskip" data-v="0">Пересчитать заново</button></div></div>
 <button class="go b" data-a="cgrun" ${n?'':'disabled'}>Тест ${n} монет · свечи ${tf10().map(baTfn).join(', ')}</button>
 <p class="hint">Каждая монета сохраняется в базу сразу, как посчитана — тест можно остановить и продолжить потом. Около 30–60 секунд на монету.</p>
 <div class="row" style="gap:6px;flex-wrap:wrap"><button class="btn2" data-a="cgdb" ${n?'':'disabled'}>Итог по выбранным из базы</button><button class="btn2" data-a="cgcmp">Сравнить группы по базе</button></div>
 <div id="cgcmp"></div>`}
function coinsPaint(){const e=$('#labcoins');if(e)e.innerHTML=coinsBody();const t=$('#labcoint');if(t)t.innerHTML=coinsTest()}
// ---------- подпись настроек теста (по ней результаты хранятся в базе) ----------
const cgSig=()=>[UI.mkt,'h'+(UI.btHtf?1:0),'a'+(UI.btAdx?1:0),'m'+(UI.btMaker&&UI.mkt==='fut'?1:0),'r'+(UI.btHrs?1:0),'v'+(UI.btVol?1:0),'o'+(UI.btOpt?1:0),'s'+SMul()].join('')+(UI.fam&&UI.fam!=='all'?'F'+UI.fam:'N'+ITS.list.length);
const r3=v=>Math.round(v*1000)/1000;
// ---------- тест выбранных монет ----------
async function cgRun(){const syms=[...CSEL];if(!syms.length)return;const job=++CGJOB,mkt=UI.mkt,fee=mkt==='fut'?0.05:0.1,spot=mkt==='spot',sig=cgSig(),TFS=tf10(),keep=[UI.btRR,UI.btDir,UI.btHonest],DIRS=spot?[1]:[0,1,-1],T0=Date.now();
 let have=new Map();if(UI.cgskip!==0){try{const j=await capi('res_list',{sig});(j.items||[]).forEach(x=>{if(Date.now()/1000-x.t<7*86400)have.set(x.sym+'|'+x.tf,1)})}catch(_){}}
 CGB={busy:'Начинаю…'};coinsPaint();UI.btHonest=1;let done=0;
 try{for(let ci=0;ci<syms.length;ci++){const sym=syms[ci],rows=[];const eta=done?` · осталось около ${Math.max(1,Math.round((Date.now()-T0)/done*(syms.length-ci)/60e3))} мин`:'';
   for(const tf of TFS){if(job!==CGJOB)return;if(have.has(sym+'|'+tf))continue;const days=BA10_DAYS[tf]||365;let c;
    try{CGB.busy=`Монета ${ci+1} из ${syms.length} · ${sym} · свечи ${baTfn(tf)}${eta}`;coinsPaint();c=await histK(mkt,sym,tf,days,300)}catch(e){if(/монеты нет/.test((e&&e.message)||''))rows.push({sym,tf,sig,data:{e:[],tdays:0,t:Date.now(),few:1}});continue}
    /* №462 · у монеты слишком короткая история (или её нет на этом рынке) — записываем пустой итог, иначе проверка всей биржи никогда не дойдёт до конца */
    if(c.length<350){rows.push({sym,tf,sig,data:{e:[],tdays:0,t:Date.now(),few:1}});continue}BTTF=tf;HTFCUR=null;if(UI.btHtf){try{HTFCUR=await htfLoad(mkt,sym,tf,days)}catch(_){}}
    const n=Math.round(days*86400e3/TFMS(tf)),from=Math.max(300,c.length-n),tdays=(c[c.length-1].time-c[from+Math.floor((c.length-from)/2)].time)/86400,E=[];
    for(const s of famList()){if(job!==CGJOB)return;await baYield();const p={};s.params.forEach(q=>p[q.k]=q.v);let sg;try{sg=s.run(c,p)}catch(_){continue}
     for(const dr of DIRS)for(const k of BA_MODES){UI.btRR=k;UI.btDir=dr;let r;try{r=btRun(c,s,from,spot,fee,sg)}catch(_){continue}const H=r.honest||{};if(!r.A.n)continue;
      E.push([s.id,k,dr,r.A.n,r3(r.A.ex),H.pos||0,r3(r.A.dd),r3(r.A.stop||0),r3(H.fitSum||0),H.fitN||0])}}
    rows.push({sym,tf,sig,data:{e:E,tdays:r3(tdays),t:Date.now()}})}
   if(rows.length){try{await capi('res_put',{rows})}catch(_){toast('Не сохранилось в базу: '+sym)}cgSum(sym,rows)}done++}
 }finally{[UI.btRR,UI.btDir,UI.btHonest]=keep;CGB=null;try{await capi('kv_set',{k:'coinsum',v:CSUM})}catch(_){}coinsPaint()}
 await cgFromDB(true)}
// лучшая система монеты — для таблицы каталога
function cgSum(sym,rows){let best=null;const mk=(e,r)=>{const sum=e[3]*e[4],d=Math.max(1,r.data.tdays||1);return {strat:e[0],k:e[1],dir:e[2],tf:r.tf,sum,n:e[3],d,mo:sum/d*30}};
 rows.forEach(r=>r.data.e.forEach(e=>{const x=mk(e,r);if(e[3]>=10&&e[5]>=3&&(!best||x.mo>best.mo))best=x}));
 if(!best)rows.forEach(r=>r.data.e.forEach(e=>{const x=mk(e,r);if(e[3]>=5&&(!best||x.mo>best.mo))best=x}));
 if(best){const s=ITS.list.find(x=>x.id===best.strat);best.sname=s?s.name:best.strat;best.t=Date.now();CSUM[sym]=best}}
// итог по базе: та же сводка, что в «Тесте на многих монетах»
async function cgFromDB(open){const syms=[...CSEL];if(!syms.length)return;const sig=cgSig(),TFS=tf10();CGB={busy:'Собираю итог из базы…'};coinsPaint();let items=[];
 try{for(let i=0;i<syms.length;i+=80){const j=await capi('res_get',{sig,syms:syms.slice(i,i+80),tfs:TFS});items=items.concat(j.items||[])}}catch(e){CGB=null;toast('Не удалось прочитать базу: '+(e.message||''));coinsPaint();return}
 CGB=null;if(!items.length){toast('В базе нет результатов для этих монет с такими настройками — запустите тест');coinsPaint();return}
 BA=cgAgg(items,syms);if(open){UI.labTab='tests';saveUI();render();window.scrollTo(0,0);try{saveBA()}catch(_){}}}
function cgAgg(items,syms){const agg={},nm=Object.fromEntries(ITS.list.map(s=>[s.id,s.name]));
 items.forEach(it=>{const d=it.data||{};(d.e||[]).forEach(e=>{const key=it.tf+'|'+e[0]+'|'+e[2]+'|'+e[1];const g=agg[key]||(agg[key]={tf:it.tf,strat:e[0],sname:nm[e[0]]||e[0],dir:e[2],k:e[1],days:BA10_DAYS[it.tf]||365,coins:[]});
  g.coins.push({sym:it.sym,n:e[3],ex:e[4],sum:e[3]*e[4],pos:e[5],dd:e[6],stop:e[7],tdays:d.tdays||0,fit:e[8]})})});
 const others=syms.filter(s=>!CGROUPS.some(g=>g[1].includes(s)));const og=CGROUPS.find(g=>g[0]==='Другие');if(og)og[1]=others;else if(others.length)CGROUPS.push(['Другие',others]);
 const rows=Object.values(agg).map(g=>{const cs=g.coins,tr=cs.reduce((a,x)=>a+x.n,0),act=cs.filter(x=>x.n>=3);
  return Object.assign(g,{nc:cs.length,act:act.length,plus:act.filter(x=>x.sum>0).length,stable:act.filter(x=>x.sum>0&&x.pos>=3).length,trades:tr,avgEx:tr?cs.reduce((a,x)=>a+x.ex*x.n,0)/tr:0,avgSum:act.length?act.reduce((a,x)=>a+x.sum,0)/act.length:0,worst:act.length?Math.min(...act.map(x=>x.sum)):0,tdays:cs.length?cs[0].tdays:0})}).filter(g=>g.act>=Math.min(5,Math.max(1,Math.ceil(syms.length/2))));
 const seen={},uniq=rows.filter(g=>{const k=g.tf+'|'+g.strat+'|'+g.k+'|'+g.coins.map(x=>x.n+':'+x.sum.toFixed(3)).join(',');if(seen[k])return false;seen[k]=1;return true});
 uniq.sort((a,b)=>(b.plus/b.act-a.plus/a.act)||(b.stable-a.stable)||(b.avgEx-a.avgEx));
 return {kind:'multi',coins:syms,rows:uniq,t:Date.now(),skip:[],mkt:UI.mkt,fl:{htf:!!UI.btHtf,adx:!!UI.btAdx,maker:!!UI.btMaker&&UI.mkt==='fut',sm:SMul()},show:{m7:15,m5:15,m0:10},fromDB:1}}
// сравнение групп: лучшая система для каждой группы монет — по базе
async function cgCompare(){if(!CAT)return;const el=$('#cgcmp');const sig=cgSig(),TFS=tf10(),tested=CAT.coins.filter(x=>CSUM[x.s]).map(x=>x.s);
 if(!tested.length){if(el)el.innerHTML='<p class="hint">В базе пока нет проверенных монет.</p>';return}if(el)el.innerHTML='<p class="mu">Собираю базу…</p>';let items=[];
 try{for(let i=0;i<tested.length;i+=80){const j=await capi('res_get',{sig,syms:tested.slice(i,i+80),tfs:TFS});items=items.concat(j.items||[])}}catch(e){if(el)el.innerHTML='<p class="err">Не удалось прочитать базу</p>';return}
 const by=new Map(CAT.coins.map(x=>[x.s,x])),out=[];
 CG_DEF.slice(0,4).forEach(([t,its])=>its.forEach(it=>{const syms=tested.filter(s=>by.get(s)&&it[2](by.get(s)));if(syms.length<3)return;const A=cgAgg(items.filter(x=>syms.includes(x.sym)),syms);
  const top=A.rows.filter(g=>g.trades>=Math.max(20,syms.length*5)&&g.avgEx>0&&g.act&&g.plus/g.act>=0.6).map(g=>Object.assign(g,{u:baUsd(g)})).sort((a,b)=>b.u.mo-a.u.mo)[0];out.push({t,g:it[1],n:syms.length,top})}));
 if(el)el.innerHTML=`<h3 style="margin:12px 0 4px">Лучшая система для каждой группы</h3><p class="hint" style="margin-top:0">По сохранённым тестам (свечи ${TFS.map(baTfn).join(', ')}, текущие настройки проверки). Группа учитывается, если в ней проверено хотя бы 3 монеты.</p>
 <div style="overflow-x:auto"><table class="bt7"><tr><th>Группа</th><th>Монет</th><th>Лучшая система</th><th>В плюсе</th><th>В месяц ($100 на сделку)</th></tr>${out.map(r=>`<tr><td><span class="mu sm">${r.t}:</span> ${r.g}</td><td>${r.n}</td><td>${r.top?`<a href="#" data-a="bago" data-v="${r.top.tf}|${r.top.days}|${r.top.strat}|${r.top.k}|${r.top.dir||0}">${r.top.sname}</a> <span class="mu sm">${baTfn(r.top.tf)} · ${baDirN(r.top.dir||0)} · ${baMode(r.top.k)}</span>`:'<span class="mu">нет плюсовой</span>'}</td><td>${r.top?`${r.top.plus} из ${r.top.act}`:'—'}</td><td>${r.top?`<b>${USC(r.top.u.mo)}</b> <span class="mu sm">за ${dayW(r.top.u.d)} ${USD(r.top.u.tot)}</span>`:'—'}</td></tr>`).join('')}</table></div>
 <p class="hint">Прибыль — на всех проверенных монетах группы вместе, ставка $100 на каждую сделку, без плеча, комиссия вычтена. Учитываются системы, которые в плюсе хотя бы на 60% монет группы.</p>`}
// ---------- вкладка ----------
LABT.splice(1,0,['coins','coins','Монеты']);
const _lv34=labView;labView=function(){if((UI.labTab||'tests')!=='coins')return _lv34();return _lv34()+`<div class="card" id="labcoint">${coinsTest()}</div><div class="card"><h3 style="margin:0 0 8px">${ic('coins',20)} Монеты Binance</h3><div id="labcoins">${coinsBody()}</div></div>`};
document.addEventListener('change',e=>{const t=e.target;if(t.dataset&&t.dataset.a2==='csel'){t.checked?CSEL.add(t.dataset.v):CSEL.delete(t.dataset.v);const x=$('#labcoint');if(x)x.innerHTML=coinsTest();const b=document.querySelector('#labcoins b[style*="flex:1"]');if(b)b.textContent=`Найдено ${catFiltered().length} · выбрано ${CSEL.size}`}
 else if(t.id==='cgsort'){UI.cgsort=t.value;saveUI();coinsPaint()}},true);
let CGQT=0;document.addEventListener('input',e=>{if(e.target.id==='cgq'){clearTimeout(CGQT);const v=e.target.value;CGQT=setTimeout(()=>{UI.cgq=v;saveUI();const pos=v.length;coinsPaint();const i=$('#cgq');if(i){i.focus();try{i.setSelectionRange(pos,pos)}catch(_){}}},300)}});
window.addEventListener('click',async e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='btsm'){e.stopPropagation();e.preventDefault();UI.btSM=+v;saveUI();if(el.dataset.s==='bt'){const sh=$('.sheet');if(sh)sh.outerHTML=btSheet();if(BT7&&BT7.A)btGo()}else if(el.dataset.s==='cg')coinsPaint();else baPaint();return}
 if(UI.view!=='lab'||(UI.labTab||'tests')!=='coins')return;
 const stop=()=>{e.stopPropagation();e.preventDefault()};
 if(a==='cgf'){stop();const S=cgSel34();S.has(v)?S.delete(v):S.add(v);UI.cgf=[...S];UI.cgshow=60;saveUI();coinsPaint()}
 else if(a==='cgmore'){stop();UI.cgshow=(UI.cgshow||60)+100;coinsPaint()}
 else if(a==='csall'){stop();catFiltered().forEach(x=>CSEL.add(x.s));coinsPaint()}
 else if(a==='csnone'){stop();CSEL.clear();coinsPaint()}
 else if(a==='cssave'){stop();if(!CSEL.size){toast('Сначала отметьте монеты');return}const n=(prompt('Название группы',`Моя группа ${Object.keys(CGRP).length+1}`)||'').trim().slice(0,40);if(!n)return;CGRP[n]=[...CSEL];try{await capi('kv_set',{k:'cgroups',v:CGRP})}catch(_){}toast('Группа «'+n+'» сохранена');coinsPaint()}
 else if(a==='cgdel'){stop();if(!confirm('Удалить группу «'+v+'»? Монеты и результаты останутся.'))return;delete CGRP[v];try{await capi('kv_set',{k:'cgroups',v:CGRP})}catch(_){}coinsPaint()}
 else if(a==='catload'){stop();await catLoad()}
 else if(a==='catmt'){stop();catMetrics()}
 else if(a==='cgstop'){stop();CGJOB++;CATBUSY='';CGB=null;coinsPaint();toast('Остановлено — посчитанное уже сохранено')}
 else if(a==='cgskip'){stop();UI.cgskip=+v;saveUI();coinsPaint()}
 else if(a==='cgrun'){stop();cgRun()}
 else if(a==='cgdb'){stop();cgFromDB(true)}
 else if(a==='cgcmp'){stop();cgCompare()}
 else if(a==='spd'||a==='tfsel'||a==='btflt'){setTimeout(coinsPaint,0)}},true);
render();
