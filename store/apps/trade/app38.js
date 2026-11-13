// ИТК Трейдинг — простое меню во вкладках «Тесты» и «Проверка системы» (10.11.2026).
// Главное — сверху и крупно: на чём искать, какие сделки, кнопка запуска.
// Фильтры проверки (честная, тренд, боковик, часы, свечи, лимитные, подбор, длина стопа) — свёрнуты в «Дополнительные настройки».
"use strict";
const ADVTXT='<p class="hint" style="margin:6px 0 0"><b>Честная</b> — систему подбирают на первой половине истории, а итог считают по второй, которую она не видела; лучше не выключать. <b>По старшему тренду</b> — сделки только по направлению большого тренда. <b>Без боковика</b> — не торговать, когда рынок стоит на месте. <b>Активные часы</b> — только в часы, когда рынок живой. <b>Крупные свечи</b> — вход только на свечах с большим объёмом. <b>Лимитные заявки</b> — комиссия меньше. <b>Подбор настроек</b> — программа сама подбирает параметры стратегии. <b>Стоп и тейк длиннее</b> — для монет, которые ходят резче биткоина.</p>';
const advOn=()=>[UI.btHonest?'честная':'',UI.btHtf?'по тренду':'',UI.btAdx?'без боковика':'',UI.btHrs?'активные часы':'',UI.btVol?'крупные свечи':'',UI.btMaker&&UI.mkt==='fut'?'лимитные':'',UI.btOpt?'подбор настроек':'',SMul()>1?'стоп и тейк ×'+String(SMul()).replace('.',','):''].filter(Boolean);
const _fc38=fChips;fChips=function(scope){const h=_fc38(scope);if(scope!=='bt'&&scope!=='ba')return h;const k='adv'+scope,on=advOn();
 return `<details class="adv38" data-k="${k}" ${UI[k]?'open':''} style="margin:8px 0"><summary class="btn2" style="padding:10px 12px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:6px">${ic('tune',18)}<b>Дополнительные настройки</b><span class="sm mu" style="margin-left:auto;text-align:right">${on.length?'включено: '+on.join(', '):'всё выключено'}</span></summary><div style="margin-top:8px">${h}${ADVTXT}</div></details>`};
document.addEventListener('toggle',e=>{const t=e.target;if(t&&t.classList&&t.classList.contains('adv38')){UI[t.dataset.k]=t.open?1:0;saveUI()}},true);
// ---------- «Тесты»: пока теста нет — три простых шага ----------
function baCoinOpts(){const s=new Set([UI.sym]);CGROUPS[0][1].forEach(x=>s.add(x));if(typeof CAT!=='undefined'&&CAT&&CAT.coins)CAT.coins.slice(0,80).forEach(x=>s.add(x.s));return [...s]}
function baStart38(){const many=UI.baWho==='many',n=many?BAC().length:1,c=(o,t,sub)=>`<button class="${(UI.baWho==='many')===(o==='many')?'on':''}" data-a="bawho" data-v="${o}" style="text-align:left;padding:10px 12px;display:block;width:100%"><b>${t}</b><br><span class="sm" style="opacity:.75">${sub}</span></button>`;
 return `<p class="hint" style="margin-top:0">Программа сама переберёт все стратегии и правила стопа и тейка и покажет, какая зарабатывает больше — в долларах, при ставке $100 на сделку.</p>
 <h3 style="margin:10px 0 4px">1. На чём искать</h3>
 <div class="cpg" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px">${c('one','Одна монета',`сейчас ${UI.sym} — лучшая система именно для неё`)}${c('many','Много монет сразу','система, которая работает почти на всех')}</div>
 ${many?`<div style="margin-top:8px">${cgChips()}</div><p class="hint" style="margin-top:0">Свои наборы монет (Топ-30, резкие, новые и т. д.) — во вкладке «Монеты».</p>`
  :`<div class="row" style="margin-top:8px"><label>Монета</label><div class="in" style="flex:1"><select id="basym">${baCoinOpts().map(s=>`<option value="${s}" ${s===UI.sym?'selected':''}>${s}</option>`).join('')}</select></div></div>`}
 ${typeof cpStep2==='function'?cpStep2():spChips()}${famStep()}
 <h3 style="margin:14px 0 6px">4. Запуск</h3>
 <button class="go b" data-a="${many?'ba10start':'bastart'}" style="font-size:16px">${ic('play_arrow',20)} Найти лучшую систему ${many?`на ${n} монетах`:'для '+UI.sym}</button>
 <p class="hint">${many?`Примерно ${n*5}–${n*12} минут — не закрывайте приложение.`:'Займёт 3–6 минут.'} Итог — вывод в долларах и кнопка «Запустить торговлю».</p>
 ${fChips('ba')}`}
const _bl38=baList;baList=function(){const B=BA;if(!B)return baStart38();const h=_bl38();
 // после теста настройки свёрнуты — сверху сразу результат
 return UI.ctrlOpen===1?h:h.replace('<details open id="bactrl"','<details id="bactrl"').replace('<summary class="sm" style="cursor:pointer;padding:4px 0"><b>Настройки и запуск теста</b></summary>',`<summary class="btn2" style="padding:10px 12px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:6px">${ic('tune',18)}<b>Новый тест и настройки</b></summary>`)};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='bawho'){e.stopPropagation();e.preventDefault();UI.baWho=v;saveUI();baPaint()}},true);
document.addEventListener('change',e=>{const t=e.target;if(t&&t.id==='basym'){UI.sym=t.value;if(typeof C!=='undefined')C=[];saveUI();baPaint()}},true);
render();
// ---------- «Проверка системы»: период подписан, дополнительные настройки — перед кнопкой ----------
const _bs38=btSheet;btSheet=function(){let h=_bs38();const i=h.indexOf('<details class="adv38"');if(i>=0){const j=h.indexOf('</details>',i)+10;const adv=h.slice(i,j);h=h.slice(0,i)+h.slice(j);const k=h.indexOf('<button class="go b" data-a="btgo">');if(k>=0)h=h.slice(0,k)+adv+h.slice(k);else h=h.slice(0,i)+adv+h.slice(i)}
 return h.replace('<div class="pct" style="margin:8px 0">${BTP','').replace(/<div class="pct" style="margin:8px 0">(<button class="[^"]*" data-a="btper")/,'<div class="row" style="align-items:center"><label>Период проверки</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">$1').replace(/(data-a="btper"[^>]*>[^<]*<\/button>)(?![\s\S]*data-a="btper")<\/div>/,'$1</div></div>')};
render();
