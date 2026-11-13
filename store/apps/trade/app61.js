// Alex Trade — №508 · чистый «График» и удобные «Котировки» (12.11.2026).
// «График»: при открытии только свечи и объём внизу — сигналы, планы и линии стратегий не рисуются.
// Слева кнопка «BTC» — наложить график биткоина для сравнения. Индикаторы и фигуры — по кнопкам, как раньше.
// «Котировки»: долгое нажатие на монете — выбрать цвет/группу; кнопка «Сортировка» — группами по цветам
// (прямо или наоборот) или одним списком: по объёму, росту, падению, названию; там же «Порядок групп».
"use strict";
Object.assign(ICP,{swapv:"M16 17.01V10h-2v7.01h-3L15 21l4-3.99h-3zM9 3L5 6.99h3V14h2V6.99h3L9 3z"});
NAV.forEach(n=>{if(n[0]==='coins')n[2]='Котировки'});
const FULL61=()=>UI.view==='term'&&!!UI.full&&UI.layout<=1;
// ---------- чистый график ----------
const _ps61=paintStrat;paintStrat=function(){if(!FULL61())return _ps61();if(!CH||!CS)return;
 try{LN.forEach(l=>{try{CH.removeSeries(l)}catch(_){}});LN=[]}catch(_){}
 try{CS.setMarkers([])}catch(_){}try{PLANS=[]}catch(_){}
 try{if(UI.pat&&typeof paintPat==='function')paintPat()}catch(_){}try{if(typeof rsLine==='function')rsLine()}catch(_){}
 try{CH.priceScale('v').applyOptions({scaleMargins:{top:.82,bottom:0}});VS&&VS.applyOptions({visible:true})}catch(_){}};
const _ov61=ovPaint;ovPaint=function(f){if(FULL61()){try{paintOrders()}catch(_){}return}return _ov61(f)};
// кнопка «BTC» на левой панели
const _tv61=termView;termView=function(){const h=_tv61();return h.replace('<span class="dwsep"></span>',`<span class="dwsep"></span><button class="${UI.cmp?'on':''}" data-a="cmp39" title="Сравнить с биткоином">${ic('btc',20)}</button>`)};
// ---------- «Котировки»: сортировка и группировка ----------
const SORT61=[['grp','Группами по цветам'],['grpr','Группами — в обратном порядке'],['vol','Одним списком: по объёму'],['up','Одним списком: по росту за сутки'],['dn','Одним списком: по падению за сутки'],['az','Одним списком: по названию']];
const _cb61=clBody;clBody=function(){const m=UI.clSort||'grp';if(!CLL||(UI.clq||'').trim()||UI.clf!=='all'||m==='grp')return _cb61();
 if(m==='grpr'){const o=gOrder().slice(),k=UI.gOrder;UI.gOrder=o.reverse();try{return _cb61()}finally{UI.gOrder=k}}
 const by=new Map(CLL.map(x=>[x.s,x]));const all=CLL.slice();UI.fav.concat(...GRPS.map(g=>g.s)).forEach(s=>{if(!by.has(s)){const x={s,p:PX[s]||0,ch:0,vol:0};by.set(s,x);all.push(x)}});
 const f={vol:(a,b)=>b.vol-a.vol,up:(a,b)=>b.ch-a.ch,dn:(a,b)=>a.ch-b.ch,az:(a,b)=>a.s.localeCompare(b.s)}[m];all.sort(f);const n=UI.clN||150;
 return `<div class="clh"><span class="clhf" style="background:#2962ff"></span>${esc(SORT61.find(x=>x[0]===m)[1])}<span class="mu">${all.length}</span></div>`+all.slice(0,n).map(clRow).join('')+(all.length>n?`<button class="btn2" style="width:calc(100% - 20px);margin:10px;padding:10px" data-a="clmore">Показать ещё ${Math.min(200,all.length-n)}</button>`:'')};
const _cv61=coinsView;coinsView=function(){let h=_cv61();h=h.replace(/<button data-a="gman">[\s\S]*?<\/button>/,'');
 return h.replace('<b style="font-size:20px;flex:1">Котировки</b>',`<b style="font-size:20px;flex:1">Котировки</b><button class="clsort" data-a="clsort" title="Сортировка и группы">${ic('swapv',22)}</button>`)};
function clSortPop(el){const r=el.getBoundingClientRect(),m=UI.clSort||'grp';cpopShow(`<h4>Как показать котировки</h4>${SORT61.map(x=>`<button data-a="clsortset" data-v="${x[0]}"><span class="chk ${m===x[0]?'on':''}">${m===x[0]?ic('check',14):''}</span>${x[1]}</button>`).join('')}<hr><button data-a="gman">${ic('sort',18)} Порядок групп — что первым, что вторым</button><button data-a="gnew" data-v="">${ic('plus',18)} Новая группа</button>`,Math.max(6,r.right-300),r.bottom+4)}
(function(){const st=document.createElement('style');st.textContent=`.clsort{border:1px solid var(--ln);background:#131722;color:#d1d4dc;border-radius:10px;width:40px;height:36px;display:grid;place-items:center;margin-right:8px;cursor:pointer}.clsort svg{fill:currentColor}
.clr.lp{background:#1e222d}`;document.head.appendChild(st)})();
// долгое нажатие на монете — выбрать цвет/группу
let LP61=null,LP61eat=0;
document.addEventListener('pointerdown',e=>{if(UI.view!=='coins')return;const row=e.target.closest('.clr');if(!row||e.target.closest('[data-a=clfav],[data-a=clflag]'))return;const s=row.dataset.v;clearTimeout(LP61&&LP61.t);
 LP61={x:e.clientX,y:e.clientY,row,t:setTimeout(()=>{LP61=null;LP61eat=Date.now();row.classList.add('lp');row.removeAttribute('data-a');setTimeout(()=>row.setAttribute('data-a','clgo'),800);try{navigator.vibrate&&navigator.vibrate(18)}catch(_){}const r=row.getBoundingClientRect();CPX=Math.max(6,Math.min(innerWidth-260,e.clientX-40));CPY=r.bottom+2;if(CPY>innerHeight*.55)CPY=Math.max(6,r.top-330);gMine(s);setTimeout(()=>row.classList.remove('lp'),600)},520)}},true);
document.addEventListener('pointermove',e=>{if(LP61&&Math.hypot(e.clientX-LP61.x,e.clientY-LP61.y)>8){clearTimeout(LP61.t);LP61=null}},true);
['pointerup','pointercancel'].forEach(k=>document.addEventListener(k,()=>{if(LP61){clearTimeout(LP61.t);LP61=null}},true));
document.addEventListener('contextmenu',e=>{if(UI.view==='coins'&&e.target.closest('.clr'))e.preventDefault()},true);
window.addEventListener('click',e=>{if(Date.now()-LP61eat<700&&e.target.closest('.clr')&&!e.target.closest('#cpop')){e.stopImmediatePropagation();e.preventDefault();return}
 const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;const st=()=>{e.stopImmediatePropagation();e.preventDefault()};
 if(a==='clsort'){st();clSortPop(el)}
 else if(a==='clsortset'){st();UI.clSort=v;saveUI();cpopHide();clPaint()}},true);
render();
