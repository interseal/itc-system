// Alex Trade — №501 · порядок в приложении (12.11.2026).
// Внизу 5 кнопок вместо 7: «Сделки» и «Статистика» переехали в «Портфель».
// «Анализ»: 4 вкладки вместо 9 (Новая проверка · Итоги · Одна система · Сервер), внутри — переключатель.
// «Серии» переехали в «Боты». В Терминале вместо «Тест всех систем» и «Проверка на истории» — одна кнопка «Найти систему».
// Повторы одной и той же проверки в «Итогах» показываются один раз.
"use strict";
(function(){const st=document.createElement('style');st.textContent='.labtabs{display:grid;grid-template-columns:1fr 1fr;gap:6px}.labtabs button{padding:8px 10px;font-size:14px;justify-content:center}';document.head.appendChild(st)})();
// ---------- нижнее меню ----------
(function(){const keep=['port','term','lab','sig','bots'];for(let i=NAV.length-1;i>=0;i--)if(!keep.includes(NAV[i][0]))NAV.splice(i,1)})();
const ins55=(h,bar)=>{const i=h.indexOf('</header>');return i>=0?h.slice(0,i+9)+bar+h.slice(i+9):bar+h};
const seg55=(items,cur,act)=>`<div class="bar"><div class="seg">${items.map(x=>`<button class="${cur===x[0]?'on':''}" data-a="${act}" data-v="${x[0]}">${x[1]}</button>`).join('')}</div></div>`;
// старые адреса «Сделки» и «Статистика» ведут в «Портфель»
const _r55=render;render=function(){if(UI.view==='deals'){UI.view='port';UI.ptab='deals'}else if(UI.view==='stats'){UI.view='port';UI.ptab='stats'}
 if(UI.view==='lab'&&UI.labTab==='ses'){UI.view='bots';UI.botTab='ses'}return _r55()};
// ---------- Портфель: Обзор · Сделки · Статистика ----------
const PTAB55=[['ov','Обзор'],['deals','Сделки'],['stats','Статистика']];
const _pv55=portView;portView=function(){const t=UI.ptab||'ov';const h=t==='deals'?dealsView():t==='stats'?statsView():_pv55();return ins55(h,seg55(PTAB55,t,'ptab'))};
const _pp55=paintPort;paintPort=function(){const t=UI.ptab||'ov';if(t==='stats')return paintStats();if(t==='deals')return;return _pp55()};
// открытые сделки обновляются сами, как раньше во вкладке «Сделки»
setInterval(()=>{if(UI.view==='port'&&UI.ptab==='deals'&&!SHEET&&!document.hidden&&!document.querySelector('details[open]')){const a=document.activeElement;if(a&&/INPUT|SELECT|TEXTAREA/.test(a.tagName))return;const y=window.scrollY;render();window.scrollTo(0,y)}},2500);
// ---------- Терминал: одна кнопка «Найти систему» ----------
const _tv55=termView;termView=function(){let h=_tv55();
 h=h.replace(/<button class="chip[^"]*" data-a="ballopen">[\s\S]*?<\/button>/,`<button class="chip" data-a="findsys">${ic('rank',18)} Найти систему</button>`);
 h=h.replace(/<button class="chip[^"]*" data-a="bt7open">[\s\S]*?<\/button>/,'');return h};
// ---------- Анализ: 4 вкладки ----------
const LABG55=[['new','rank','Новая проверка',[['tests','Подобрать систему'],['coins','Выбрать монеты']]],
 ['res','flag','Итоги',[['res','Итоги'],['arch','Архив'],['dep','На депозит']]],
 ['sys','history','Одна система',[['sys','Одна система']]],
 ['srv','coins','Сервер',[['full','Вся биржа'],['list','Новые монеты']]]];
const grp55=t=>LABG55.find(g=>g[3].some(x=>x[0]===t))||LABG55[0];
const _lv55=labView;labView=function(){const t=UI.labTab||'tests';let h=_lv55();const g=grp55(t);
 const i=h.indexOf('<div class="labtabs">'),j=i>=0?h.indexOf('</div>',i)+6:-1;
 let subs=g[3];if(g[0]==='res')subs=subs.filter(x=>x[0]!=='dep'||t==='dep'||(typeof DEP!=='undefined'&&DEP));
 const bar=`<div class="labtabs">${LABG55.map(x=>`<button class="chip ${g[0]===x[0]?'on':''}" data-a="labtab" data-v="${g[0]===x[0]?t:x[3][0][0]}">${ic(x[1],18)} ${x[2]}</button>`).join('')}</div>`+(subs.length>1?seg55(subs,t,'labtab'):'');
 if(i>=0)h=h.slice(0,i)+bar+h.slice(j);
 return h.replace(/во вкладке «Монеты» или «Тесты» \(много монет\)/g,'во вкладке «Новая проверка»').replace(/Откройте «Тесты»/g,'Откройте «Новая проверка»')};
// из Терминала — сразу в «Новая проверка» на текущей монете
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='findsys'){e.stopPropagation();e.preventDefault();SHEET=null;UI.view='lab';UI.labTab='tests';UI.baWho='one';saveUI();render();window.scrollTo(0,0)}
 else if(a==='ptab'){e.stopPropagation();e.preventDefault();UI.ptab=v;saveUI();render();window.scrollTo(0,0)}},true);
// ---------- Итоги: одинаковые проверки — один раз ----------
const key55=r=>[r.name,r.mkt,r.spd,(r.coins||[]).join(','),r.top&&r.top[0]?r.top[0].strat+'|'+r.top[0].tf+'|'+(r.top[0].dir||0):'-'].join('#');
const _rb55=resBody;resBody=function(){if(!RESL||!RESL.length)return _rb55();const full=RESL,seen=new Map(),show=[];
 for(const r of full){const k=key55(r);if(seen.has(k)){seen.get(k).dup55++;continue}r.dup55=0;seen.set(k,r);show.push(r)}
 RESL=show;try{return _rb55()}finally{RESL=full}};
const _rc55=resCard;resCard=function(r){const h=_rc55(r);if(!r.dup55)return h;const n=r.dup55;
 return h.replace(/(<div class="sm mu">Проверка от [^<]*)<\/div>/,`$1 <span class="mu">· такая же проверка была ещё ${n} ${plW(n,'раз','раза','раз')}</span></div>`)};
// ---------- Боты: вкладка «Серии» ----------
BTAB.push(['ses','Серии']);
const _bv55=botsView;botsView=function(){if(UI.botTab!=='ses')return _bv55();
 return head()+`<div class="bar"><div class="seg">${BTAB.map(x=>`<button class="${UI.botTab===x[0]?'on':''}" data-a="btab" data-v="${x[0]}">${x[1]}</button>`).join('')}</div></div><div class="card" id="botses">${typeof allSheet==='function'?unwrap(allSheet()):''}</div>`};
setInterval(()=>{if(UI.view==='bots'&&UI.botTab==='ses'&&!SHEET){const e=$('#botses');if(e&&typeof allSheet==='function')e.innerHTML=unwrap(allSheet())}},4000);
render();
