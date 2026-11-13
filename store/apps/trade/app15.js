// ИТК Трейдинг — сигналы серии на графике (10.11.2026).
// Стрелки «Купить/Продать» на графике — от стратегии, выбранной на графике. Если серия торгует по другой стратегии,
// её сигналы рисуются отдельно фиолетовыми стрелками «Серия», а над сделками — подсказка и кнопка «Показать стратегию серии».
"use strict";
function serActive(){const L=[];D().bots.filter(b=>b.series&&b.status==='run').forEach(b=>L.push({strat:b.strat,sym:b.sym,tf:b.tf,mkt:b.mkt,t0:b.series.t0,prm:b.prm||{}}));
 (typeof TD!=='undefined'?TD:[]).filter(s=>s.status==='run').forEach(s=>L.push({strat:s.strat,sym:s.sym,tf:s.tf,mkt:s.mkt,t0:s.t0,prm:s.prm||{},srv:1}));return L}
const _ps15=paintStrat;paintStrat=function(){if(CS&&!CS._p15){const o=CS.setMarkers.bind(CS);CS._p15=o;CS.setMarkers=m=>{CS._mk=m;o(m)}}_ps15();if(!CS||!C.length)return;
 const L=serActive().filter(x=>x.sym===UI.sym&&x.tf===UI.tf&&x.strat!==UI.strat);if(!L.length)return;const done=C.slice(0,-1),add=[];
 L.forEach(x=>{const s=ITS.list.find(q=>q.id===x.strat);if(!s)return;const p={};s.params.forEach(q=>p[q.k]=x.prm[q.k]!=null?x.prm[q.k]:q.v);let sg=[];try{sg=s.run(done,p)}catch(e){}
  sg.forEach(g=>{if(g.t==='exit')return;const t=done[g.i].time;if(t*1000<x.t0-3600e3)return;add.push({time:t,position:g.t==='long'?'belowBar':'aboveBar',color:'#2962ff',shape:g.t==='long'?'arrowUp':'arrowDown',text:'Серия: '+(g.t==='long'?'купить':'продать')})})});
 if(add.length)CS._p15((CS._mk||[]).concat(add).sort((a,b)=>a.time-b.time))};
function serHint(){const L=serActive().filter(x=>x.sym===UI.sym&&x.tf===UI.tf&&x.strat!==UI.strat);if(!L.length)return '';const x=L[0],s=ITS.list.find(q=>q.id===x.strat),c=STR();
 return `<div class="plan short" style="cursor:default;padding:8px 10px;margin:0 0 10px"><div class="sm">Стрелки «Купить / Продать» на графике — от стратегии <b>«${c.name}»</b>. Серия торгует по <b>«${s?s.name:x.strat}»</b>: её сигналы — фиолетовые стрелки «Серия». Сделка серии открывается только по её сигналам.</div><button class="btn2 sm" style="margin-top:6px;width:100%" data-a="serstrat" data-v="${x.strat}">Показать на графике стратегию серии</button></div>`}
const _md15=myDeals;myDeals=function(){_md15();const e=$('#mydeals');if(!e)return;const h=serHint();if(h){const bs=e.querySelectorAll('.serban'),b=bs[bs.length-1];if(b)b.insertAdjacentHTML('afterend',h);else e.insertAdjacentHTML('afterbegin',h)}};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a=serstrat]');if(!el)return;e.stopPropagation();UI.strat=el.dataset.v;saveUI();render();toast('На графике — сигналы стратегии серии')},true);
render();
