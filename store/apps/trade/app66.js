// Alex Trade — №570 (14.11.2026): порядок на экране «Торговля».
// Руководитель: «тут каша, глаза не знают куда смотреть — сгруппировать по логике; индикаторы спрятать в кнопку
// „Индикаторы“, и всё в такой логике». Кнопки остаются те же (те же действия), но собраны в меню:
//   [Стратегия] [Индикаторы ▾] [Анализ ▾] [Торговля ▾] [Вид ▾]       — строка инструментов
//   [+SL] [+TP] | [Показать ▾] | [↶] … [Оповещения] [⛶] [📷]            — строка над графиком
// Новые кнопки, которые добавят позже, сами попадут в меню «Ещё», а не в общую ленту.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const GROUPS=[
  ['ind','Индикаторы','fx',['ind','vp','pat','arrows','zones','plans','rson','cmp39']],
  ['an','Анализ','rank',['coinme','bt7open','ballopen','findsys']],
  ['trade','Торговля','repeat',['seropen']],
  ['view','Вид','grid',['layout']]];
 const KEEP=['strat'];                       // всегда на виду
 const SHOW=['fills','olines','autosc'];     // строка над графиком → «Показать ▾»
 const NAMES={ind:'Список индикаторов…',arrows:'Сигналы стратегии',zones:'Подсветка сигналов'};
 const MENU={};                              // id меню → html пунктов (обновляется при каждой отрисовке)
 const esc2=s=>String(s).replace(/"/g,'&quot;');
 function item(b){const a=b.dataset.a,on=b.classList.contains('on');const c=b.cloneNode(true);c.className='mit'+(on?' on':'');
  if(NAMES[a]&&!/·/.test(c.textContent)){const sv=c.querySelector('svg');c.textContent=NAMES[a];if(sv)c.prepend(sv)}
  c.insertAdjacentHTML('beforeend',`<span class="mck">${on?'✓':''}</span>`);return c.outerHTML}
 function dd(id,name,icon,items,anyOn,count){MENU[id]=items.join('');
  return `<button class="chip ${anyOn?'on':''}" data-gm="${id}">${icon?ic(icon,18)+' ':''}${name}${count?' · '+count:''} ${ic('down',14)}</button>`}
 function groupTools(bar){const btns=[...bar.querySelectorAll(':scope > button, :scope > .chip')];if(!btns.some(b=>b.dataset.a==='strat'))return false;
  const used=new Set(),out=[];
  btns.filter(b=>KEEP.includes(b.dataset.a)).forEach(b=>{out.push(b.outerHTML);used.add(b)});
  GROUPS.forEach(([id,name,icon,acts])=>{const g=btns.filter(b=>acts.includes(b.dataset.a));if(!g.length)return;g.forEach(b=>used.add(b));
   g.sort((x,y)=>acts.indexOf(x.dataset.a)-acts.indexOf(y.dataset.a));
   if(g.length===1&&id!=='ind'){out.push(g[0].outerHTML);return}   // одна кнопка — без меню
   const on=g.filter(b=>b.classList.contains('on')).length;
   const cnt=id==='ind'?(((typeof UI!=='undefined'&&UI.ind)?UI.ind.length:0)+g.filter(b=>b.dataset.a!=='ind'&&b.classList.contains('on')).length):0;
   out.push(dd(id,name,icon,g.map(item),on>0,cnt))});
  const rest=btns.filter(b=>!used.has(b));if(rest.length===1)out.push(rest[0].outerHTML);else if(rest.length)out.push(dd('more','Ещё','',rest.map(item),rest.some(b=>b.classList.contains('on')),0));
  bar.innerHTML=out.join('');bar.classList.add('grp66');return true}
 function groupChartBar(bar){const btns=[...bar.querySelectorAll(':scope > button')];const g=btns.filter(b=>SHOW.includes(b.dataset.a));if(g.length<2)return;
  const names={fills:'Мои сделки на графике',olines:'Заявки на графике',autosc:'Автомасштаб'};
  const items=g.map(b=>{const on=b.classList.contains('on');return `<button class="mit${on?' on':''}" data-a="${esc2(b.dataset.a)}">${names[b.dataset.a]||b.textContent}<span class="mck">${on?'✓':''}</span></button>`});
  const anchor=g[0];anchor.insertAdjacentHTML('beforebegin',`<button data-gm="show" class="${g.some(b=>b.classList.contains('on'))?'on':''}">Показать ${ic('down',14)}</button>`);MENU.show=items.join('');g.forEach(b=>b.remove());
  // лишние разделители подряд
  [...bar.querySelectorAll(':scope > .sep')].forEach(s=>{const p=s.previousElementSibling;if(!p||p.classList.contains('sep'))s.remove()})}
 const _tv66=termView;termView=function(){const h=_tv66();try{const t=document.createElement('template');t.innerHTML=h;
   const tb=[...t.content.querySelectorAll('.bar')].find(b=>b.querySelector(':scope > [data-a="strat"]'));if(tb)groupTools(tb);
   const cb=t.content.querySelector('.cbar');if(cb)groupChartBar(cb);return t.innerHTML}catch(e){return h}};
 // меню
 function closeMenu(){const m=document.getElementById('gm66');if(m)m.remove()}
 function openMenu(btn){const id=btn.dataset.gm;closeMenu();const r=btn.getBoundingClientRect();const m=document.createElement('div');m.id='gm66';m.dataset.for=id;
  m.innerHTML=MENU[id]||'';document.body.appendChild(m);
  const w=Math.min(300,window.innerWidth-16);m.style.width=w+'px';m.style.left=Math.max(8,Math.min(r.left,window.innerWidth-w-8))+'px';m.style.top=(r.bottom+4)+'px';
  const mh=window.innerHeight-r.bottom-16;if(mh<200){m.style.top='';m.style.bottom=(window.innerHeight-r.top+4)+'px';m.style.maxHeight=(r.top-16)+'px'}else m.style.maxHeight=mh+'px'}
 document.addEventListener('click',e=>{const g=e.target.closest('[data-gm]');
  if(g){e.stopPropagation();e.preventDefault();const m=document.getElementById('gm66');if(m&&m.dataset.for===g.dataset.gm)closeMenu();else openMenu(g);return}
  const m=document.getElementById('gm66');if(!m)return;
  if(e.target.closest('#gm66 [data-a]')){setTimeout(closeMenu,0);return}   // пункт сработает обычным обработчиком приложения
  if(!e.target.closest('#gm66'))closeMenu()},true);
 window.addEventListener('scroll',closeMenu,{passive:true});window.addEventListener('resize',closeMenu);
 const st=document.createElement('style');st.textContent=`
 #gm66{position:fixed;z-index:80;background:var(--pan2,#1e222d);border:1px solid var(--ln,#363a45);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.5);padding:6px;overflow:auto}
 #gm66 .mit{display:flex;align-items:center;gap:8px;width:100%;text-align:left;background:none;border:0;color:var(--tx,#d1d4dc);padding:11px 10px;border-radius:8px;font-size:15px;cursor:pointer;white-space:normal}
 #gm66 .mit:active,#gm66 .mit:hover{background:rgba(255,255,255,.06)}
 #gm66 .mit.on{color:#5eb8ff}
 #gm66 .mck{margin-left:auto;font-weight:700;color:#5eb8ff;min-width:14px;text-align:right}
 .bar.grp66{gap:8px}`;document.head.appendChild(st);
})();
