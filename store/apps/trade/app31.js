// ИТК Трейдинг — быстрые и долгие системы отдельно, кнопки теста — вверху (10.11.2026).
// Быстрые: свечи до 1 часа (много сделок, короткие серии). Долгие: 4 часа и 1 день. Тесты и списки — раздельно, не вперемешку.
"use strict";
const SPD10={scalp:['1m','5m'],fast:['15m','30m','1h'],slow:['4h','1d']},SPD1={scalp:['1m','5m'],fast:['15m','30m','1h'],slow:['4h','1d']},SPN={scalp:'Скальпинг',fast:'Быстрые',slow:'Долгие'};
if(!TF_ALL.includes('1m'))TF_ALL.unshift('1m','5m');if(!BTPS['1m'].some(p=>p[0]===7))BTPS['1m'].push([7,'Неделя','неделю']);if(!BTPS['5m'].some(p=>p[0]===30))BTPS['5m'].push([14,'2 недели','2 недели'],[30,'Месяц','месяц']);
BA_DAYS['1m']=7;BA_DAYS['5m']=30;BA10_DAYS['1m']=7;BA10_DAYS['5m']=30;
const spd=()=>UI.spd==='slow'?'slow':UI.spd==='scalp'?'scalp':'fast';
const tfGrp=tf=>SPD10.slow.includes(tf)?'slow':SPD10.scalp.includes(tf)?'scalp':'fast';
function spChips(){const s=spd();return `<div class="row" style="align-items:flex-start"><label>Системы</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">
 <button class="${s==='scalp'?'on':''}" data-a="spd" data-v="scalp">Скальпинг · 1 и 5 мин</button><button class="${s==='fast'?'on':''}" data-a="spd" data-v="fast">Быстрые · 15 мин–1 час</button><button class="${s==='slow'?'on':''}" data-a="spd" data-v="slow">Долгие · 4 часа и день</button></div></div>`}
// свечи в тесте на многих монетах — только из выбранной группы
function tf10(){const g=SPD10[spd()],t=(Array.isArray(UI.tfs)?UI.tfs:[]).filter(x=>g.includes(x));return t.length?t:g.slice()}
const _cg31=cgChips;cgChips=function(){const h=_cg31(),S=tf10(),g=SPD10[spd()];
 const row=`${spChips()}<div class="row" style="align-items:flex-start"><label>Свечи</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${g.map(t=>`<button class="${S.includes(t)?'on':''}" data-a="tfsel" data-v="${t}">${baTfn(t)}</button>`).join('')}</div></div>`;
 return h.replace(/<div class="row" style="align-items:flex-start"><label>Свечи<\/label>[\s\S]*?<\/div><\/div>/,row)};
const _b1031=ba10Run;ba10Run=function(){UI.tfs=tf10();saveUI();return _b1031()};
// тест одной монеты — тоже по группе
const _br31=baRun;baRun=async function(){const keep=BA_TF.slice();BA_TF.length=0;SPD1[spd()].forEach(t=>BA_TF.push(t));try{await _br31()}finally{BA_TF.length=0;keep.forEach(t=>BA_TF.push(t))}};
// списки: быстрые и долгие не смешиваем; кнопки теста — вверху
const CTRL='<div class="row" style="align-items:flex-start"><label>Проверка</label>';
const _bl31=baList;baList=function(){const B=BA;
 if(!B){let h=_bl31();return h.replace('<button class="go b" data-a="bastart">',spChips()+'<button class="go b" data-a="bastart">')}
 if(B.busy)return _bl31();
 const all=B.rows,has={scalp:all.some(x=>tfGrp(x.tf)==='scalp'),fast:all.some(x=>tfGrp(x.tf)==='fast'),slow:all.some(x=>tfGrp(x.tf)==='slow')};let g=B.grp||(has[spd()]?spd():(['scalp','fast','slow'].find(x=>has[x])||'fast'));
 let h;try{B.rows=all.filter(x=>tfGrp(x.tf)===g);h=_bl31()}finally{B.rows=all}
 // переносим кнопки и настройки теста наверх
 const i=h.lastIndexOf(CTRL);let ctrl='';if(i>0){ctrl=h.slice(i);h=h.slice(0,i)}
 if(B.kind!=='multi'&&ctrl&&!ctrl.includes('data-a="spd"'))ctrl=ctrl.replace('<button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="bastart">',spChips()+'<button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="bastart">');
 const sw=['scalp','fast','slow'].filter(x=>has[x]).length>1?`<div class="pct" style="margin:10px 0">${['scalp','fast','slow'].filter(x=>has[x]).map(x=>`<button class="${g===x?'on':''}" data-a="bagrp" data-v="${x}">${SPN[x]}</button>`).join('')}</div>`:`<p class="hint" style="margin:8px 0 0">${SPN[g]}: ${g==='scalp'?'свечи 1 и 5 минут':g==='fast'?'свечи 15 минут – 1 час':'свечи 4 часа и 1 день'}.</p>`;
 return (ctrl?`<details ${UI.ctrlOpen===0?'':'open'} id="bactrl" style="margin-bottom:8px"><summary class="sm" style="cursor:pointer;padding:4px 0"><b>Настройки и запуск теста</b></summary>${ctrl}</details>`:'')+sw+h};
document.addEventListener('toggle',e=>{if(e.target&&e.target.id==='bactrl'){UI.ctrlOpen=e.target.open?1:0;saveUI()}},true);
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='spd'){e.stopPropagation();UI.spd=v;UI.tfs=SPD10[v].slice();saveUI();if(BA&&!BA.busy)BA.grp=null;baPaint()}
 else if(a==='bagrp'){e.stopPropagation();BA.grp=v;baPaint()}},true);
render();
