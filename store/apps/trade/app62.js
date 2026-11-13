// Alex Trade — №510 · BTC в «Индикаторах», объём прижат к низу (12.11.2026).
// «Сравнить с биткоином» теперь в окне «Индикаторы» (раздел «Сравнение»), включается и выключается там же.
// По умолчанию выключен. Кнопка BTC на левой панели убрана.
// Объём всегда стоит на нижней границе графика (над шкалой времени), а при окнах индикаторов — над ними.
"use strict";
// один раз выключаем сравнение, включённое раньше с левой панели
try{if(!localStorage.getItem('cmp62')){localStorage.setItem('cmp62','1');if(UI.cmp){UI.cmp=false;saveUI();try{cmpOff()}catch(_){}}}}catch(_){}
// убрать кнопку BTC с левой панели
const _tv62=termView;termView=function(){return _tv62().replace(/<button class="[^"]*" data-a="cmp39" title="Сравнить с биткоином">[\s\S]*?<\/button>/,'')};
// пункт в окне «Индикаторы»
const _is62=indSheet;indSheet=function(){const h=_is62();
 const row=`<p class="grp">СРАВНЕНИЕ</p><div class="opt ind ${UI.cmp?'on':''}" data-a="cmp39" data-n="биткоин btc сравнение с биткоином"><b><i class="dot" style="background:#ff9800"></i> Сравнить с биткоином (BTC)</b></div>`;
 return h.replace('<div id="ilist">','<div id="ilist">'+row)};
// объём — к нижней границе
function volFix62(){if(!CH)return;try{const np=(UI.ind||[]).map(id=>INDS.find(d=>d.id===id)).filter(d=>d&&d.pane).length;
 if(!np){CH.priceScale('right').applyOptions({scaleMargins:{top:.06,bottom:.1}});CH.priceScale('v').applyOptions({scaleMargins:{top:.8,bottom:0}})}
 else{const ph=Math.min(.2,.42/np),mb=ph*np+.04;CH.priceScale('v').applyOptions({scaleMargins:{top:Math.max(.5,1-mb-.14),bottom:mb}})}}catch(_){}}
const _pi62=paintInd;paintInd=function(){const r=_pi62.apply(this,arguments);volFix62();return r};
const _ps62=paintStrat;paintStrat=function(){const r=_ps62.apply(this,arguments);volFix62();return r};
// после перестройки графика (закрытие окна, смена монеты) — вернуть линию BTC, если она включена
const _bc62=buildChart;buildChart=function(){const r=_bc62.apply(this,arguments);if(UI.cmp)setTimeout(()=>{try{cmpLoad()}catch(_){}},0);return r};
render();
