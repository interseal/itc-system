// ИТК Трейдинг — описание каждой стратегии и выбор направления сделок (10.11.2026).
// 1) В окне выбора стратегии и в «Проверке на истории» у каждой стратегии кнопка «Описание»: как работает, когда покупает, когда продаёт, когда выходит, где работает лучше, настройки.
// 2) В «Проверке на истории» выбор: лонг и шорт / только лонг / только шорт. При «только лонг» сигналы на продажу лишь закрывают покупку, при «только шорт» — наоборот.
"use strict";
ICP.info="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z";
ITS.list.forEach(s=>{if(!s.info&&ITS.infoOld&&ITS.infoOld[s.id])s.info=ITS.infoOld[s.id]});
const dirOf=()=>UI.mkt!=='spot'&&(UI.btDir===1||UI.btDir===-1)?UI.btDir:0;
function dirSig(sig,d){if(!d)return sig;return sig.map(x=>d===1&&x.t==='short'?{i:x.i,t:'exit'}:d===-1&&x.t==='long'?{i:x.i,t:'exit'}:x)}
const _btr22=btRun;btRun=function(c,s,from,spot,fee,sigAll){const d=dirOf();if(!d){const r=_btr22(c,s,from,spot,fee,sigAll);r.dir=0;return r}
 const p={};s.params.forEach(q=>p[q.k]=q.v);const r=_btr22(c,s,from,spot,fee,dirSig(sigAll||s.run(c,p),d));r.dir=d;return r};
// описание стратегии
function infoSheet(id){const s=ITS.list.find(x=>x.id===id);if(!s)return '';const I=s.info||{};const sec=(t,v)=>v?`<h3 style="margin:12px 0 4px">${t}</h3><p style="margin:0">${v}</p>`:'';
 return sheetWrap(`${ic('info',20)}&nbsp;${s.name}`,`<p class="mu" style="margin-top:0">${s.about||''}</p>
 ${sec('Как работает',I.how)}${sec('Когда покупает (лонг)',I.buy)}${sec('Когда продаёт (шорт)',I.sell)}${sec('Когда выходит',I.exit)}${sec('Где работает лучше',I.best)}
 ${s.params&&s.params.length?`<h3 style="margin:12px 0 4px">Настройки</h3><div class="sgrid">${s.params.map(q=>`<div><small>${q.n}</small>${q.v}</div>`).join('')}</div>`:''}
 <p class="hint">Хотите поправить правила — напишите, что изменить, и стратегия будет торговать по-новому.</p>
 <button class="go b" data-a="infoback">Назад</button>`)}
let INFOBACK=null;
const _r22=render;render=function(){if(SHEET&&SHEET[0]==='sinfo'){const v=SHEET[1];SHEET=null;_r22();SHEET=['sinfo',v];$('#app').insertAdjacentHTML('beforeend',infoSheet(v));return}_r22()};
// кнопка «Описание» в окне выбора стратегии
const _ss22=stratSheet;stratSheet=function(){let h=_ss22();ITS.list.forEach(s=>{h=h.replace(`data-a="pick" data-v="${s.id}"><b>${s.name}</b>`,`data-a="pick" data-v="${s.id}"><div class="row" style="margin:0;align-items:center"><b style="flex:1">${s.name}</b><button class="btn2 sm" data-a="sinfo" data-v="${s.id}" style="padding:4px 10px">${ic('info',16)} Описание</button></div>`)});return h};
// в «Проверке на истории»: «Описание» выбранной стратегии и выбор направления
const _bs22=btSheet;btSheet=function(){let h=_bs22();const d=dirOf(),cs=UI.btS||UI.strat;
 h=h.replace('<div class="row"><label>Стоп и тейк</label>',`<button class="btn2" style="width:100%;margin:2px 0 8px;padding:8px" data-a="sinfo" data-v="${cs}" id="btinfo">${ic('info',16)} Описание стратегии</button>
 ${UI.mkt==='fut'?`<div class="row"><label>Сделки</label><div class="pct" style="margin:0;flex:1">${[[0,'Лонг и шорт'],[1,'Только лонг'],[-1,'Только шорт']].map(q=>`<button class="${d===q[0]?'on':''}" data-a="btdir" data-v="${q[0]}">${q[1]}</button>`).join('')}</div></div>`:''}
 <div class="row"><label>Стоп и тейк</label>`);return h};
const _bres22=btRes;btRes=function(){let h=_bres22();const B=BT7;if(B&&B.A&&B.dir)h=h.replace(' свечей</span></div>',` свечей · <b>${B.dir===1?'только лонг':'только шорт'}</b></span></div>`);return h};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='sinfo'){e.stopPropagation();e.preventDefault();INFOBACK=SHEET?SHEET.slice():null;SHEET=['sinfo',el.id==='btinfo'?($('#bts')?$('#bts').value:v):v];render();history.pushState({sh:1},'')}
 else if(a==='infoback'){e.stopPropagation();SHEET=INFOBACK;INFOBACK=null;render()}
 else if(a==='btdir'){e.stopPropagation();UI.btDir=+v;saveUI();const B=BT7;if(B&&B.c&&B.A){const r=btRun(B.c,B.s,B.from,B.spot,B.fee);BT7=Object.assign(B,{dir:0,skip:0},r)}$('.sheet').outerHTML=btSheet()}},true);
render();
