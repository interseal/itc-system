// Alex Trade — №506 · «Котировки»: цветные флажки групп, сколько угодно групп, свой порядок разделов (12.11.2026).
// Слева у каждой монеты — флажок цвета её группы; нажали на флажок — выбрали группы для монеты.
// Разделы списка идут в вашем порядке: «Порядок групп» — стрелками вверх и вниз, там же цвет, название, удаление.
"use strict";
Object.assign(ICP,{up:"M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z",dn:"M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z",sort:"M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"});
NAV.forEach(n=>{if(n[0]==='coins')n[2]='Котировки'});
// порядок разделов: «fav» — избранные, остальное — id групп
function gOrder(){const ids=['fav'].concat(GRPS.map(g=>g.id));let o=(Array.isArray(UI.gOrder)?UI.gOrder:[]).filter(x=>ids.includes(x));ids.forEach(x=>{if(!o.includes(x))o.push(x)});UI.gOrder=o;return o}
const secOf=id=>id==='fav'?{id:'fav',n:'Избранные',c:'#ffd54f',s:UI.fav}:GRPS.find(g=>g.id===id);
clRow=function(x){const g=gOf(x.s),fav=UI.fav.includes(x.s),ch=x.ch;
 const flags=g.length?g.slice(0,3).map(z=>`<i style="background:${z.c}"></i>`).join(''):'<i class="none"></i>';
 return `<div class="clr" data-a="clgo" data-v="${x.s}"><span class="clfl" data-a="clflag" data-v="${x.s}" title="Группы монеты">${flags}</span>${clAv(x.s)}<div class="clm"><b>${x.s}<span class="mu">/USDT${UI.mkt==='fut'?' · фьючерс':''}</span></b><small>${g.length?g.map(z=>`<i class="ctsw" style="background:${z.c};width:8px;height:8px"></i>${esc(z.n)}`).join(' '):'Объём за сутки '+clVol(x.vol||0)}</small></div>
 <div class="clp"><b>${x.p?fp(x.p):'—'}</b><small class="${ch>=0?'g':'r'}">${ch>=0?'+':''}${fmt(ch||0,2)}%</small></div><button class="clfv" data-a="clfav" data-v="${x.s}" title="Избранное">${ic(fav?'star':'staro',20)}</button></div>`};
clBody=function(){if(!CLL)return '<p class="mu" style="padding:16px">Загружаю котировки…</p>';const q=(UI.clq||'').trim().toUpperCase(),by=new Map(CLL.map(x=>[x.s,x]));
 const pick=a=>a.map(s=>by.get(s)||{s,p:PX[s]||0,ch:0,vol:0});
 if(q){const L=CLL.filter(x=>x.s.includes(q)).sort((a,b)=>(a.s.startsWith(q)?0:1)-(b.s.startsWith(q)?0:1)||b.vol-a.vol).slice(0,100);return L.length?L.map(clRow).join(''):'<p class="mu" style="padding:16px">Ничего не нашлось</p>'}
 const col=UI.clCol||{};const sec=(id,t,L,c)=>`<div class="clh" data-a="clcol" data-v="${id}"><span class="clhf" style="background:${c||'#363a45'}"></span>${t}<span class="mu">${L.length}</span>${ic(col[id]?'dn':'up',18)}</div>`+(col[id]?'':L.map(clRow).join('')||'<p class="mu" style="padding:6px 16px 10px;font-size:13px">Пусто — нажмите флажок слева у монеты, чтобы добавить её сюда.</p>');
 if(UI.clf==='fav')return sec('fav','Избранные',pick(UI.fav),'#ffd54f');
 if(UI.clf.startsWith('g:')){const g=GRPS.find(z=>z.id===UI.clf.slice(2));return g?sec(g.id,esc(g.n),pick(g.s),g.c):''}
 let html='';gOrder().forEach(id=>{const s=secOf(id);if(!s)return;if(id!=='fav'||s.s.length)html+=sec(id,esc(s.n),pick(s.s),s.c)});
 const shown=new Set(UI.fav.concat(...GRPS.map(g=>g.s))),rest=CLL.filter(x=>!shown.has(x.s)).sort((a,b)=>b.vol-a.vol),n=UI.clN||120;
 html+=sec('rest','Остальные · по объёму',rest.slice(0,n),'');if(!col.rest&&rest.length>n)html+=`<button class="btn2" style="width:calc(100% - 20px);margin:10px;padding:10px" data-a="clmore">Показать ещё ${Math.min(200,rest.length-n)} из ${rest.length-n}</button>`;return html};
coinsView=function(){gOrder();const chips=gOrder().map(id=>{const s=secOf(id);if(!s)return '';const v=id==='fav'?'fav':'g:'+id;return `<button class="${UI.clf===v?'on':''}" data-a="clf" data-v="${v}">${id==='fav'?ic('star',14):`<i class="ctsw" style="background:${s.c}"></i>`}${esc(s.n)}</button>`}).join('');
 return `<main class="page clpage"><div class="clhd"><b style="font-size:20px;flex:1">Котировки</b><div class="seg"><button class="${UI.mkt==='fut'?'on':''}" data-a="clmkt" data-v="fut">Фьючерсы</button><button class="${UI.mkt==='spot'?'on':''}" data-a="clmkt" data-v="spot">Спот</button></div></div>
 <div class="clsr">${ic('srch',20)}<input id="clq" placeholder="Поиск монеты" value="${esc(UI.clq||'')}" autocomplete="off"></div>
 <div class="ctbar"><button class="${UI.clf==='all'?'on':''}" data-a="clf" data-v="all">Все</button>${chips}<button data-a="gnew" data-v="">${ic('plus',14)} Группа</button><button data-a="gman">${ic('sort',14)} Порядок групп</button></div>
 <div id="cll">${clBody()}</div></main>`};
// управление группами: порядок, цвет, название, удаление
function gMan(){const o=gOrder();cpopShow(`<h4>Порядок групп в «Котировках»</h4>${o.map((id,i)=>{const s=secOf(id);if(!s)return '';return `<div class="gmr"><span class="clhf" style="background:${s.c}"></span>
 <span class="gmn" ${id==='fav'?'':`data-a="gren" data-v="${id}"`}>${esc(s.n)} <span class="mu">${s.s.length}</span></span>
 <button data-a="gmv" data-v="${id}|-1" ${i===0?'disabled':''} title="Выше">${ic('up',20)}</button><button data-a="gmv" data-v="${id}|1" ${i===o.length-1?'disabled':''} title="Ниже">${ic('dn',20)}</button>
 ${id==='fav'?'<span style="width:72px"></span>':`<button data-a="gcol" data-v="${id}" title="Цвет">${ic('palette',18)}</button><button data-a="gdel" data-v="${id}" title="Удалить">${ic('dwdel',18)}</button>`}</div>`}).join('')}
 <hr><button data-a="gnew" data-v="">${ic('plus',18)} Новая группа</button>`,Math.max(6,innerWidth/2-170),Math.max(60,innerHeight*.15))}
(function(){const st=document.createElement('style');st.textContent=`
.clfl{display:flex;flex-direction:column;gap:2px;align-self:stretch;justify-content:center;padding:0 6px 0 0;cursor:pointer;flex:none}
.clfl i{display:block;width:7px;height:22px;border-radius:0 4px 4px 0;flex:1;max-height:24px}.clfl i.none{background:#2a2e39;opacity:.6}
.clh{cursor:pointer;user-select:none}.clh svg{fill:#787b86;margin-left:4px}.clhf{width:7px;height:16px;border-radius:2px;display:inline-block;flex:none}
.gmr{display:flex;align-items:center;gap:6px;padding:6px 8px;border-bottom:1px solid #2a2e39}.gmr .gmn{flex:1;cursor:pointer;padding:6px 0}
.gmr button{width:36px!important;padding:6px!important;justify-content:center;border-radius:8px}.gmr button:disabled{opacity:.25}
.cpop{min-width:300px}`;document.head.appendChild(st)})();
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;const st=()=>{e.stopImmediatePropagation();e.preventDefault()};
 if(a==='clflag'){st();const r=el.getBoundingClientRect();CPX=r.right+4;CPY=r.top;if(CPY>innerHeight*.55)CPY=Math.max(6,r.top-320);gMine(v)}
 else if(a==='clcol'){st();UI.clCol=UI.clCol||{};UI.clCol[v]=!UI.clCol[v];saveUI();clPaint()}
 else if(a==='gman'){st();gMan()}
 else if(a==='gmv'){st();const [id,d]=v.split('|'),o=gOrder(),i=o.indexOf(id),j=i+ +d;if(i<0||j<0||j>=o.length)return;[o[i],o[j]]=[o[j],o[i]];UI.gOrder=o;
  const gi=o.filter(x=>x!=='fav');GRPS.sort((a,b)=>gi.indexOf(a.id)-gi.indexOf(b.id));gSave();saveUI();render();gMan()}},true);
render();
