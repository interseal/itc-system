// ИТК Трейдинг — отдельная вкладка «Анализ» (10.11.2026).
// Все проверки систем — на своей странице, без графика: «Тесты» (одна монета и много монет), «Проверка системы», «На депозит», «Серии».
// На экране графика кнопки остаются как были.
"use strict";
ICP.flask="M19.8 18.4L14 10.67V6.5l1.35-1.69c.26-.33.03-.81-.39-.81H9.04c-.42 0-.65.48-.39.81L10 6.5v4.17L4.2 18.4c-.49.66-.02 1.6.8 1.6h14c.82 0 1.29-.94.8-1.6z";
(function(){const st=document.createElement('style');st.textContent='.sheet.labpage{position:static;inset:auto;background:none;z-index:auto;display:block}.labpage .shin{max-height:none;border-radius:12px;max-width:none;margin:10px;width:auto}.labpage .shin>h3{display:none}.labtabs{display:flex;gap:6px;overflow-x:auto;padding:8px 10px}.labtabs button{flex:none}';document.head.appendChild(st)})();
const LABT=[['tests','rank','Тесты'],['sys','history','Проверка системы'],['dep','wallet','На депозит'],['ses','dash','Серии']];
const unwrap=h=>h.replace(/^<div class="sheet" data-a="shbg"><div class="shin"><h3>[\s\S]*?<\/h3>/,'').replace(/<\/div><\/div>\s*$/,'');
function labView(){const t=UI.labTab||'tests';let body='';
 if(t==='tests')body=`<div class="card"><h3 style="margin:0 0 8px">${ic('rank',20)} Тест всех систем</h3><div id="bares">${baList()}</div></div>`;
 else if(t==='sys')body=btSheet();
 else if(t==='dep')body=`<div class="card"><h3 style="margin:0 0 8px">${ic('wallet',20)} Сколько это на депозит</h3><div id="depres">${DEP?depBody():'<p class="hint">Откройте «Тесты», пройдите тест на многих монетах и у нужной системы нажмите «Сколько это на депозит» — расчёт появится здесь.</p>'}</div></div>`;
 else if(t==='ses')body=`<div class="card" id="labses">${typeof allSheet==='function'?unwrap(allSheet()):''}</div>`;
 return head()+`<div class="labtabs">${LABT.map(x=>`<button class="chip ${t===x[0]?'on':''}" data-a="labtab" data-v="${x[0]}">${ic(x[1],18)} ${x[2]}</button>`).join('')}</div>`+body}
// на вкладке «Анализ» окно проверки — часть страницы, а не всплывающее окно
const _bs29=btSheet;btSheet=function(){const h=_bs29();return UI.view==='lab'&&!(SHEET&&SHEET[0]==='bt')?h.replace('<div class="sheet" data-a="shbg">','<div class="sheet labpage">'):h};
const _bsh29=btShow;btShow=function(){if(UI.view==='lab'&&!(SHEET&&SHEET[0]==='bt')){const el=$('#btres');if(el)el.innerHTML=btRes();return}return _bsh29()};
setInterval(()=>{if(UI.view==='lab'&&(UI.labTab||'tests')==='ses'&&!SHEET){const e=$('#labses');if(e&&typeof allSheet==='function')e.innerHTML=unwrap(allSheet())}},4000);
// переходы внутри «Анализа» — без всплывающих окон
window.addEventListener('click',e=>{if(UI.view!=='lab')return;const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 const go=(tab)=>{e.stopPropagation();e.preventDefault();SHEET=null;UI.labTab=tab;saveUI();render();window.scrollTo(0,0)};
 if(a==='labtab')go(v);
 else if(a==='bago'){const [tf,d,st,k,dr]=v.split('|');UI.btS=st;UI.btTf=tf;UI.btP=+d;UI.btRR=+k;UI.btDir=+(dr||0);BT7=null;go('sys');btGo()}
 else if(a==='depopen'){const [tf,st,dr,k]=v.split('|');go('dep');depLoad(tf,st,+dr,+k)}
 else if(a==='ballopen'||a==='depback')go('tests');
 else if(a==='bt7open')go('sys');
 else if(a==='sysback'){e.stopPropagation();SHEET=null;render()}
 else if(a==='sesgo'){/* переход к сессии — на вкладку с графиком */UI.view='term'}},true);
render();
