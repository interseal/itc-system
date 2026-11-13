// ИТК Трейдинг — простая вкладка «Монеты» (10.11.2026).
// Три шага сверху вниз: 1) какие монеты — одной кнопкой; 2) какие сделки; 3) большая кнопка «Найти лучшую систему».
// Тонкие настройки и полный список 500+ монет — свёрнуты ниже, чтобы не мешали.
"use strict";
document.head.insertAdjacentHTML('beforeend','<style>.cpg button{border:1px solid var(--ln);background:var(--pan2);color:inherit;border-radius:10px;cursor:pointer;font:inherit}.cpg button.on{background:var(--ac);color:#fff;border-color:var(--ac)}</style>');
let CPEND=null;
document.head.insertAdjacentHTML('beforeend','<style>.spin39{width:22px;height:22px;border:3px solid var(--ln);border-top-color:var(--ac);border-radius:50%;animation:sp39 .8s linear infinite;flex:0 0 auto}@keyframes sp39{to{transform:rotate(360deg)}}.go:active{transform:scale(.98);filter:brightness(1.15)}</style>');
const _cg37=cgRun;cgRun=async function(){if(!CSEL.size){toast('Сначала выберите монеты в шаге 1');return}CGB={busy:'Начинаю проверку…'};coinsPaint();return _cg37()};// набор, который ждёт расчёта характеристик
const CPRE=[
 ['top10','Топ-10','самые крупные монеты',x=>x.r<=10],
 ['top30','Топ-30','крупные и средние',x=>x.r<=30],
 ['top100','Топ-100','все ходовые монеты',x=>x.r<=100],
 ['hot','Резкие','ходят сильнее биткоина, из топ-150',x=>x.r<=150&&x.b>=1.3,1],
 ['calm','Спокойные','ходят слабее биткоина, из топ-150',x=>x.r<=150&&x.b!=null&&x.b<0.8,1],
 ['new','Новые','на бирже меньше полугода, из топ-150',x=>x.r<=150&&x.ob&&Date.now()-x.ob<182*864e5],
 ['fresh','Ещё не проверены','из топ-100, которых нет в базе',x=>x.r<=100&&!CSUM[x.s]],
 ['good','Были в плюсе','уже проверены, лучшая система в плюсе',x=>CSUM[x.s]&&CSUM[x.s].sum>0]];
function cpApply(id){if(!CAT)return;let L;if(id.startsWith('u:')){const n=id.slice(2);L=(CGRP[n]||[]).filter(s=>CAT.coins.some(x=>x.s===s))}else{const p=CPRE.find(x=>x[0]===id);if(!p)return;
  if(p[4]&&!CAT.mt){CPEND=id;UI.cpre=id;saveUI();catMetrics();return}L=CAT.coins.filter(p[3]).map(x=>x.s)}
 CSEL=new Set(L);UI.cpre=id;saveUI();coinsPaint()}
const _cm37=catMetrics;catMetrics=async function(){await _cm37();if(CPEND){const id=CPEND;CPEND=null;cpApply(id)}};
const cpCnt=p=>CAT?CAT.coins.filter(p[3]).length:0;
const plW=(n,a,b,c)=>{const m=n%10,h=n%100;return m===1&&h!==11?a:m>=2&&m<=4&&(h<12||h>14)?b:c};
function cpStep1(){const cur=UI.cpre||'',N=CSEL.size,list=[...CSEL];
 const btn=(id,t,sub,cnt)=>`<button class="${cur===id&&N?'on':''}" data-a="cpre" data-v="${esc(id)}" style="text-align:left;padding:10px 12px;display:block;width:100%"><b>${t}</b>${cnt!=null?` <span class="sm" style="opacity:.8">· ${cnt}</span>`:''}<br><span class="sm" style="opacity:.75">${sub}</span></button>`;
 const pre=CPRE.map(p=>btn(p[0],p[1],p[4]&&!(CAT&&CAT.mt)?p[2]+' (сначала посчитаю — 2–3 мин)':p[2],p[4]&&!(CAT&&CAT.mt)?null:cpCnt(p))).join('')
  +Object.keys(CGRP).map(n=>btn('u:'+n,esc(n),'моя группа',CGRP[n].length)).join('');
 return `<h3 style="margin:0 0 4px">1. Какие монеты проверить</h3><p class="hint" style="margin-top:0">Нажмите один вариант.</p>
 <div class="cpg" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px">${pre}</div>
 <div class="wbox" style="margin-top:8px">${N?`<b>Выбрано ${N} ${plW(N,'монета','монеты','монет')}:</b> <span class="sm">${list.slice(0,30).join(', ')}${N>30?' и ещё '+(N-30):''}</span>`:(cur?'<span class="r">В этом варианте сейчас нет ни одной монеты — выберите другой.</span>':'<span class="mu">Монеты ещё не выбраны.</span>')}
 <div class="row" style="margin:6px 0 0;gap:6px;flex-wrap:wrap"><button class="btn2 sm" data-a="cplist">${ic('list',16)} Выбрать вручную из списка</button>${N?`<button class="btn2 sm" data-a="cssave">Сохранить как мою группу</button><button class="btn2 sm" data-a="csnone">Очистить</button>`:''}</div></div>`}
function cpStep2(){const s=spd(),o=(v,t,sub)=>`<button class="${s===v?'on':''}" data-a="spd" data-v="${v}" style="text-align:left;padding:10px 12px;display:block;width:100%"><b>${t}</b><br><span class="sm" style="opacity:.75">${sub}</span></button>`;
 return `<h3 style="margin:14px 0 4px">2. Какие сделки искать</h3>
 <div class="cpg" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px">${o('scalp','Очень быстрые','свечи 1 и 5 минут · сделка — минуты')}${o('fast','Быстрые','свечи 15 мин, 30 мин, 1 час · сделка — часы')}${o('slow','Долгие','свечи 4 часа и день · сделка — дни')}</div>`}
coinsTest=function(){if(CAT&&!CSEL.size&&UI.cpre==null){UI.cpre='top10';CAT.coins.filter(x=>x.r<=10).forEach(x=>CSEL.add(x.s))}
 const n=CSEL.size,tfs=tf10(),min=Math.max(1,Math.round(n*tfs.length*0.25*famFrac()));
 return `
 ${CATBUSY?`<div class="wbox" style="border:2px solid var(--ac)"><b>${CATBUSY}</b> <button class="btn2 sm" data-a="cgstop">Остановить</button></div>`:''}
 ${cpStep1()}${cpStep2()}${famStep()}
 <h3 style="margin:14px 0 6px">4. Запуск</h3>
 ${CGB&&CGB.busy?`<div class="go" style="background:var(--pan2);border:2px solid var(--ac);color:var(--tx);cursor:default;display:flex;align-items:center;gap:10px;text-align:left"><span class="spin39"></span><span style="flex:1"><b>Идёт проверка…</b><br><span class="sm" style="font-weight:400">${esc(CGB.busy)}</span></span></div><button class="btn2" data-a="cgstop" style="width:100%;margin-top:6px;padding:10px">Остановить</button>`
 :`<button class="go b" data-a="cgrun" ${n?'':'disabled'} style="font-size:16px">${ic('play_arrow',20)} Найти лучшую систему${n?` · ${n} ${plW(n,'монета','монеты','монет')}`:''}</button>`}
 <p class="hint">${n?`Примерно ${min<60?min+' мин':fmt(min/60,1)+' ч'}. `:''}Каждая монета сразу сохраняется — можно остановить и продолжить потом, уже проверенные за неделю не пересчитываются. Итог откроется во вкладке «Тесты»: вывод в долларах и кнопка «Запустить торговлю».</p>
 <details style="margin-top:6px"${UI.cpAdv?' open':''} ontoggle="UI.cpAdv=this.open;saveUI()"><summary class="btn2" style="padding:10px;cursor:pointer;list-style:none;text-align:center">${ic('tune',18)} Дополнительные настройки</summary>
 <div style="margin-top:8px"><div class="row" style="align-items:flex-start"><label>Свечи</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${SPD10[spd()].map(t=>`<button class="${tfs.includes(t)?'on':''}" data-a="tfsel" data-v="${t}">${baTfn(t)}</button>`).join('')}</div></div>
 ${fChips('cg')}
 <div class="row"><label>Уже проверенные</label><div class="pct" style="margin:0;flex:1"><button class="${UI.cgskip!==0?'on':''}" data-a="cgskip" data-v="1">Не пересчитывать</button><button class="${UI.cgskip===0?'on':''}" data-a="cgskip" data-v="0">Пересчитать заново</button></div></div>
 <div class="row" style="gap:6px;flex-wrap:wrap"><button class="btn2 sm" data-a="catload">Обновить список монет</button><button class="btn2 sm" data-a="catmt">${ic('history',16)} Пересчитать, как монеты ходят относительно биткоина</button></div></div></details>
 <h3 style="margin:14px 0 6px">Уже проверенное</h3>
 <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:0"><button class="btn2" data-a="cgdb" ${n?'':'disabled'}>${ic('rank',18)} Итог по выбранным монетам</button><button class="btn2" data-a="cgcmp">${ic('dash',18)} Какие группы лучше</button></div>
 <p class="hint" style="margin-top:4px">Без нового теста — по тому, что уже сохранено.</p>
 <div id="cgcmp"></div>`};
const _lv37=labView;labView=function(){if((UI.labTab||'tests')!=='coins')return _lv37();const h=_lv37(),i=h.indexOf('<div class="card" id="labcoint">');const base=i>=0?h.slice(0,i):h;
 return base+`<div class="card" id="labcoint">${coinsTest()}</div><details class="card" id="cplistbox"${UI.cpList?' open':''} ontoggle="UI.cpList=this.open;saveUI()"><summary style="cursor:pointer;list-style:none"><h3 style="margin:0;display:flex;align-items:center;gap:6px">${ic('coins',20)} Все монеты списком${CAT?' · '+CAT.coins.length:''} <span class="mu sm" style="font-weight:400;margin-left:auto">${UI.cpList?'свернуть':'развернуть'}</span></h3></summary><div id="labcoins" style="margin-top:8px">${coinsBody()}</div></details>`};
// в полном списке — короче подсказка
const _cb37=coinsBody;coinsBody=function(){return _cb37().replace(/<p class="hint"><b>Бета<\/b>[\s\S]*?<\/p>/,'<p class="hint">Отметьте нужные монеты галочками — они попадут в шаг 1. Фильтры ниже помогают найти монеты; внутри строки варианты складываются, разные строки — сужают выбор.</p>')};
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el||UI.view!=='lab'||(UI.labTab||'tests')!=='coins')return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='cpre'){e.stopPropagation();e.preventDefault();cpApply(v)}
 else if(a==='cplist'){e.stopPropagation();e.preventDefault();UI.cpList=true;saveUI();const d=$('#cplistbox');if(d){d.open=true;d.scrollIntoView({behavior:'smooth'})}}
 else if(a==='csnone'||a==='csall'){UI.cpre='';}},true);
// галочки в списке меняют шаг 1
document.addEventListener('change',e=>{const t=e.target;if(t.dataset&&t.dataset.a2==='csel'){UI.cpre='';const x=$('#labcoint');if(x)setTimeout(()=>{x.innerHTML=coinsTest()},0)}},true);
render();
