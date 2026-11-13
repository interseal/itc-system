// ИТК Трейдинг — быстрые системы (10.11.2026).
// 1) Подбор настроек: для каждой стратегии перебираются несколько наборов настроек на первой половине истории, лучший честно проверяется на второй.
// 2) Фильтры «Активные часы» (вход только 07:00–21:00 по Гринвичу — Европа и США) и «Крупные свечи» (движение свечи не меньше обычного).
// 3) Свечи 30 минут в тесте одной монеты; в тесте на многих монетах — выбор свечей: 15 мин, 30 мин, 1 час, 4 часа, 1 день.
// Новые стратегии «RSI-2 по старшему графику» и «Пробой диапазона открытия сессии» — в strat2.js.
"use strict";
// ---------- фильтры ----------
const _sf28=sigFilter;sigFilter=function(c,sig,tf){let o=_sf28(c,sig,tf);
 if(UI.btHrs)o=o.map(x=>{if(x.t!=='long'&&x.t!=='short')return x;const h=Math.floor((c[x.i].time%86400)/3600);return h<7||h>=21?{i:x.i,t:'exit'}:x});
 if(UI.btVol){const A=ITS.ind.atr(c,14),P=A.map((a,i)=>a==null?null:a/c[i].close);o=o.map(x=>{if(x.t!=='long'&&x.t!=='short')return x;const v=[];for(let j=Math.max(0,x.i-199);j<=x.i;j++)if(P[j]!=null)v.push(P[j]);v.sort((a,b)=>a-b);const med=v.length?v[v.length>>1]:0;return P[x.i]!=null&&P[x.i]>=med?x:{i:x.i,t:'exit'}})}
 return o};
// фильтры «часы» и «крупные свечи» тоже должны срабатывать — включаем общий вызов фильтра, если включены только они
const _btr28=btRun;btRun=function(c,s,from,spot,fee,sigAll){
 if((UI.btHrs||UI.btVol)&&!UI.btHtf&&!UI.btAdx){const p={};s.params.forEach(q=>p[q.k]=q.v);sigAll=sigFilter(c,sigAll||s.run(c,p),BTTF||UI.btTf||UI.tf)}
 if(!UI.btOpt||!UI.btHonest||c.length-from<40)return _btr28(c,s,from,spot,fee,sigAll);
 // ---------- подбор настроек на первой половине ----------
 const G=PGRID[s.id];if(!G||!G.length)return _btr28(c,s,from,spot,fee,sigAll);
 const keep=s.params.map(q=>q.v);let best=null;
 try{for(const g of G){s.params.forEach(q=>{if(g[q.k]!=null)q.v=g[q.k]});const p={};s.params.forEach(q=>p[q.k]=q.v);let sg;try{sg=s.run(c,p)}catch(_){continue}
   if(UI.btHrs||UI.btVol)if(!UI.btHtf&&!UI.btAdx)sg=sigFilter(c,sg,BTTF||UI.btTf||UI.tf);
   const kc=BTCAP;BTCAP=null;let r;try{r=_btr28(c,s,from,spot,fee,sg)}finally{BTCAP=kc}const H=r.honest||{};
   const score=(H.fitN||0)>=5?H.fitSum:-1e9;if(!best||score>best.score)best={score,p:Object.assign({},p),sg}}
  if(!best)return _btr28(c,s,from,spot,fee,sigAll);
  s.params.forEach(q=>{if(best.p[q.k]!=null)q.v=best.p[q.k]});const r=_btr28(c,s,from,spot,fee,best.sg);r.prm=best.p;r.optN=G.length;return r}
 finally{s.params.forEach((q,i)=>q.v=keep[i])}};
// наборы настроек для перебора (по умолчанию — первый)
const PGRID={
 ema:[{f:9,s:21},{f:5,s:21},{f:12,s:34},{f:9,s:50},{f:20,s:50}],
 st:[{n:10,m:3},{n:10,m:2},{n:14,m:4},{n:7,m:2.5},{n:20,m:3}],
 rsi:[{n:14,lo:30,hi:70},{n:7,lo:25,hi:75},{n:14,lo:25,hi:75},{n:9,lo:30,hi:70,t:100}],
 don:[{n:20,x:10},{n:10,x:5},{n:40,x:20},{n:55,x:20}],
 macd:[{f:12,s:26,g:9},{f:8,s:21,g:5},{f:5,s:35,g:5}],
 bb:[{n:20,k:2},{n:20,k:2.5},{n:14,k:2},{n:30,k:2}],
 stoch:[{n:14,lo:20,hi:80},{n:9,lo:20,hi:80},{n:14,lo:10,hi:90},{n:5,lo:15,hi:85}],
 ichi:[{t:9,k:26,b:52},{t:7,k:22,b:44},{t:12,k:30,b:60}],
 psar:[{af:2,mx:20},{af:1,mx:10},{af:3,mx:20},{af:1,mx:20}],
 adx:[{n:14,a:25},{n:14,a:20},{n:10,a:25},{n:14,a:30}],
 sqz:[{n:20,bb:2,kc:1.5},{n:20,bb:2,kc:1.25},{n:14,bb:2,kc:1.5},{n:20,bb:2,kc:2}],
 rsi2:[{t:200,lo:10,hi:90},{t:200,lo:5,hi:95},{t:100,lo:10,hi:90},{t:200,lo:15,hi:85},{t:100,lo:5,hi:95}],
 pull:[{f:20,m:50,s:200},{f:10,m:50,s:200},{f:20,m:50,s:100}],
 cci:[{n:20,z:100},{n:14,z:100},{n:20,z:150},{n:30,z:100}],
 ha:[{r:2},{r:1},{r:3}],
 inside:[{w:1},{w:2},{w:3}],
 volbo:[{n:20,k:2},{n:10,k:2},{n:20,k:1.5},{n:20,k:3}],
 mtf:[{m:50,lo:10,hi:90},{m:50,lo:5,hi:95},{m:20,lo:10,hi:90},{m:50,lo:15,hi:85},{m:20,lo:5,hi:95}],
 orb:[{r:60,h:4},{r:30,h:4},{r:60,h:8},{r:30,h:2}],
 lvl:[{k:4,touch:2},{k:3,touch:2},{k:6,touch:2},{k:4,touch:3}],
 pat:[{mins:3},{mins:2}],
 vwapm:[{f:12,s:26,g:9,w:5},{f:8,s:21,g:5,w:3},{f:12,s:26,g:9,w:3}],
 kelt:[{n:20,k:2,r:2},{n:20,k:1.5,r:2},{n:14,k:2,r:1},{n:20,k:2.5,r:2}],
 rsibb:[{n:20,k:2,lo:20,hi:80},{n:20,k:2.5,lo:15,hi:85},{n:14,k:2,lo:20,hi:80}],
 sfp:[{n:20,m:40},{n:10,m:40},{n:30,m:50},{n:20,m:30}],
 mpull:[{a:9,b:21,c:50},{a:5,b:13,c:34},{a:9,b:21,c:100}]};
// ---------- кнопки фильтров ----------
const _fc28=fChips;fChips=function(scope){const ch=(k,t,on)=>`<button class="${on?'on':''}" data-a="btflt" data-v="${k}" data-s="${scope}">${t}</button>`;
 return _fc28(scope).replace(/<\/div><\/div>$/,`${ch('btHrs','Активные часы',UI.btHrs)}${ch('btVol','Крупные свечи',UI.btVol)}${ch('btOpt','Подбор настроек',UI.btOpt)}</div></div>`)+(scope==='ba'&&UI.btOpt?'<p class="hint" style="margin-top:0">С подбором настроек тест идёт в 3–5 раз дольше.</p>':'')};
// в отчёте — какие настройки подобраны
const _bres28=btRes;btRes=function(){let h=_bres28();const B=BT7;if(!B||!B.A)return h;const f=[];if(UI.btHrs)f.push('активные часы');if(UI.btVol)f.push('крупные свечи');
 if(f.length)h=h.replace('</span></div>',` · ${f.join(' · ')}</span></div>`);
 if(B.prm&&B.s){const P=B.s.params.map(q=>`${q.n}: <b>${B.prm[q.k]}</b>`).join(', ');h=h.replace('Подбора нет — правило не зависит от истории. ','').replace('<b>Честная проверка.</b>',`<b>Честная проверка.</b> Настройки подобраны на первой половине (из ${B.optN} вариантов): ${P}.`)}
 return h};
// ---------- свечи 30 минут и выбор свечей в тестах ----------
if(!BA_TF.includes('30m'))BA_TF.splice(BA_TF.indexOf('15m')+1,0,'30m');BA_DAYS['30m']=182;
BA10_DAYS['15m']=60;BA10_DAYS['30m']=120;
const TF_ALL=['15m','30m','1h','4h','1d'];
const tfSel=()=>{const t=Array.isArray(UI.tfs)&&UI.tfs.length?UI.tfs:['1h','4h','1d'];return TF_ALL.filter(x=>t.includes(x))};
const _cg28=cgChips;cgChips=function(){const S=tfSel();return `<div class="row" style="align-items:flex-start"><label>Свечи</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${TF_ALL.map(t=>`<button class="${S.includes(t)?'on':''}" data-a="tfsel" data-v="${t}">${baTfn(t)}</button>`).join('')}<button data-a="tfsel" data-v="fast">Быстрые</button></div></div>`+_cg28()};
const _b10=ba10Run;ba10Run=function(){BA10_TF.length=0;tfSel().forEach(t=>BA10_TF.push(t));return _b10()};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='tfsel'){e.stopPropagation();let S=tfSel();if(v==='fast')S=['15m','30m','1h'];else S=S.includes(v)?S.filter(x=>x!==v):S.concat(v);if(!S.length)S=['1h'];UI.tfs=S;saveUI();baPaint()}},true);
render();
