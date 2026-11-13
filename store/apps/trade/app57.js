// Alex Trade — №503/504 · группы монет любого цвета, полноэкранный «График» и торговля прямо на графике (12.11.2026).
// 1) Группы монет — сколько угодно: своё название и любой цвет. Монета может быть в нескольких группах.
//    Над графиком — строка групп: открыть монету группы или всю группу сразу на 2–4 графиках.
// 2) Кнопка «График» внизу: график на весь экран, слева панель рисования, снизу короткое меню.
// 3) Долгое нажатие на цене (или правая кнопка мыши) — меню: купить/продать лимитом, стоп-заявка на пробой,
//    стоп или тейк сюда (для новой заявки и для открытой сделки), уровень.
"use strict";
// ---------- группы ----------
const CPAL=['#f23645','#ff5252','#e91e63','#ff4081','#ab47bc','#7e57c2','#5c6bc0','#2962ff','#448aff','#03a9f4','#00bcd4','#26c6da','#009688','#089981','#4caf50','#8bc34a','#cddc39','#ffeb3b','#ffd54f','#ffc107','#ff9800','#ff7043','#8d6e63','#bdbdbd','#78909c','#ffffff'];
let GRPS=LS.get('cgrps',null);
if(!Array.isArray(GRPS)){GRPS=[];const old=LS.get('ctag',{}),OC={r:['#f23645','Красная'],o:['#ff9800','Оранжевая'],y:['#ffd54f','Жёлтая'],g:['#089981','Зелёная'],b:['#2962ff','Синяя'],v:['#ab47bc','Фиолетовая']};
 Object.keys(OC).forEach(k=>{const s=Object.keys(old).filter(x=>old[x]===k);if(s.length)GRPS.push({id:'g'+k,n:OC[k][1],c:OC[k][0],s})});LS.set('cgrps',GRPS)}
const gSave=()=>LS.set('cgrps',GRPS);
const gOf=s=>GRPS.filter(g=>g.s.includes(s));
const ctCol=s=>{const g=gOf(s)[0];return g?g.c:''};
const ctDot=(s,sz)=>{const L=gOf(s).slice(0,3);return L.map(g=>`<i class="ctd" style="background:${g.c};width:${sz||8}px;height:${sz||8}px"></i>`).join('')};
(function(){const st=document.createElement('style');st.textContent=`
.ctd{display:inline-block;border-radius:50%;vertical-align:middle;margin:0 3px 1px 0;flex:none}
.ctbar{display:flex;gap:6px;overflow-x:auto;padding:4px 10px 6px;scrollbar-width:none}.ctbar::-webkit-scrollbar{display:none}
.ctbar button{flex:none;display:flex;align-items:center;gap:5px;border:1px solid var(--ln);background:#131722;color:#d1d4dc;border-radius:16px;padding:4px 10px;font:inherit;font-size:13px;cursor:pointer;max-width:170px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ctbar button.on{border-color:#5eb8ff}.ctsw{width:12px;height:12px;border-radius:50%;flex:none;display:inline-block}
.cpop{position:fixed;z-index:60;background:#1e222d;border:1px solid var(--ln);border-radius:12px;padding:6px;min-width:240px;max-width:92vw;max-height:78vh;overflow-y:auto;box-shadow:0 8px 28px #000a;font-size:14px}
.cpop h4{margin:4px 8px 6px;font-size:13px;color:var(--mu);font-weight:500;display:flex;align-items:center;gap:6px}
.cpop button{display:flex;align-items:center;gap:8px;width:100%;border:0;background:none;color:#d1d4dc;text-align:left;padding:10px 10px;border-radius:8px;font:inherit;font-size:14px;cursor:pointer}
.cpop button:active,.cpop button:hover{background:#2a2e39}.cpop .g{color:#26a69a}.cpop .r{color:#ef5350}.cpop hr{border:0;border-top:1px solid var(--ln);margin:4px 0}
.cpop .cprow{display:grid;grid-template-columns:repeat(7,30px);gap:7px;padding:6px 8px}.cpop .cprow i{width:30px;height:30px;border-radius:50%;cursor:pointer;border:2px solid #1e222d;display:block}.cpop .cprow i.on{border-color:#fff}
.cpop .cpin{display:flex;align-items:center;gap:8px;padding:6px 10px;color:var(--mu);font-size:13px}.cpop .cpin input[type=color]{width:44px;height:30px;border:0;background:none;padding:0}
.cpop .chk{width:18px;height:18px;border-radius:5px;border:2px solid #787b86;flex:none;display:grid;place-items:center}.cpop .chk.on{background:#2962ff;border-color:#2962ff}
.mc.ctag{box-shadow:inset 0 3px 0 var(--ctc)}
/* полноэкранный график */
body.fullc{overflow:hidden}
body.fullc #chartWrap{position:fixed!important;left:0;right:0;top:0;bottom:calc(var(--navh,58px) + 46px + env(safe-area-inset-bottom));height:auto!important;min-height:0!important;z-index:15}
body.fullc .tools{bottom:0}
.fcbar{display:none}
body.fullc .fcbar{display:flex;position:fixed;left:0;right:0;bottom:calc(var(--navh,58px) + env(safe-area-inset-bottom));height:46px;z-index:16;background:var(--pan,#1e222d);border-top:1px solid var(--ln);align-items:center;gap:4px;padding:0 6px;overflow-x:auto;scrollbar-width:none}
body.fullc .fcbar::-webkit-scrollbar{display:none}
.fcbar button{flex:none;border:1px solid transparent;background:none;color:#d1d4dc;border-radius:8px;padding:6px 9px;font:inherit;font-size:13.5px;display:flex;align-items:center;gap:4px;cursor:pointer}
.fcbar button.on{color:#5eb8ff;border-color:#2962ff55;background:#0f2a4d}.fcbar .fsep{width:1px;height:24px;background:var(--ln);flex:none;margin:0 2px}`;document.head.appendChild(st)})();
// ---------- всплывающее меню ----------
function cpopShow(html,x,y){cpopHide();const d=document.createElement('div');d.className='cpop';d.id='cpop';d.innerHTML=html;document.body.appendChild(d);
 const W=innerWidth,H=innerHeight,r=d.getBoundingClientRect();d.style.left=Math.max(6,Math.min(x,W-r.width-6))+'px';d.style.top=Math.max(6,Math.min(y,H-r.height-6))+'px';
 setTimeout(()=>document.addEventListener('pointerdown',cpopOut,true),0)}
function cpopOut(e){if(!e.target.closest('#cpop'))cpopHide()}
function cpopHide(){const d=$('#cpop');if(d)d.remove();document.removeEventListener('pointerdown',cpopOut,true)}
let CPX=0,CPY=0;const cpAt=el=>{const r=el.getBoundingClientRect();CPX=r.left;CPY=r.bottom+4;if(CPY>innerHeight*0.6)CPY=Math.max(6,r.top-10-Math.min(innerHeight*0.7,420))};
// группы монеты: отметить, в каких она группах
function gMine(sym){cpopShow(`<h4>Группы монеты <b style="color:#d1d4dc">${sym}</b></h4>
 ${GRPS.map(g=>{const on=g.s.includes(sym);return `<button data-a="gtog" data-v="${g.id}|${sym}"><span class="chk ${on?'on':''}">${on?ic('check',14):''}</span><i class="ctsw" style="background:${g.c}"></i>${esc(g.n)}<span class="mu" style="margin-left:auto">${g.s.length}</span></button>`}).join('')||'<p class="hint" style="margin:6px 10px">Групп пока нет.</p>'}
 <hr><button data-a="gnew" data-v="${sym}">${ic('plus',18)} Новая группа с ${sym}</button>`,CPX,CPY)}
// одна группа: монеты и действия
function gShow(id){const g=GRPS.find(x=>x.id===id);if(!g)return;const has=g.s.includes(UI.sym);
 cpopShow(`<h4><i class="ctsw" style="background:${g.c}"></i>${esc(g.n)} · ${g.s.length} ${plW(g.s.length,'монета','монеты','монет')}</h4>
 ${g.s.map(s=>`<button data-a="ctopen" data-v="${s}"><b>${s}</b><span class="mu">/USDT</span><span style="margin-left:auto" class="mu">${PX[s]?fp(PX[s]):''}</span></button>`).join('')||'<p class="hint" style="margin:6px 10px">В группе пока нет монет.</p>'}
 ${g.s.length>1?`<hr><button data-a="gmulti" data-v="${g.id}">${ic('dash',18)} Открыть всю группу на ${g.s.length>2?4:2} графиках</button>`:''}
 <hr><button data-a="gtog" data-v="${g.id}|${UI.sym}">${has?ic('close',18)+' Убрать '+UI.sym+' из группы':ic('plus',18)+' Добавить '+UI.sym+' в группу'}</button>
 <button data-a="gcol" data-v="${g.id}">${ic('palette',18)} Цвет группы</button><button data-a="gren" data-v="${g.id}">${ic('dwedit',18)} Переименовать</button><button data-a="gdel" data-v="${g.id}" class="r">${ic('dwdel',18)} Удалить группу</button>`,CPX,CPY)}
function gColor(id,newSym){const g=GRPS.find(x=>x.id===id);cpopShow(`<h4>${g?'Цвет группы «'+esc(g.n)+'»':'Цвет новой группы'}</h4><div class="cprow">${CPAL.map(c=>`<i data-a="gcset" data-v="${id||''}|${c}|${newSym||''}" style="background:${c}" class="${g&&g.c===c?'on':''}"></i>`).join('')}</div>
 <label class="cpin">Свой цвет: <input type="color" id="gcin" value="${g?g.c:'#2962ff'}"><button data-a="gcset2" data-v="${id||''}||${newSym||''}" style="width:auto;padding:6px 10px">Выбрать</button></label>`,CPX,CPY)}
function ctBar(){const mine=gOf(UI.sym);
 return `<div class="ctbar"><button data-a="gmine" title="В каких группах эта монета">${mine.length?mine.slice(0,3).map(g=>`<i class="ctsw" style="background:${g.c}"></i>`).join(''):ic('plus',14)} ${UI.sym}: группы</button>${GRPS.map(g=>`<button class="${g.s.includes(UI.sym)?'on':''}" data-a="gshow" data-v="${g.id}"><i class="ctsw" style="background:${g.c}"></i>${esc(g.n)} · ${g.s.length}</button>`).join('')}<button data-a="gnew" data-v="">${ic('plus',14)} Группа</button></div>`}
// ---------- «График» на весь экран: короткое меню снизу ----------
const TFQ57=[['1m','1м'],['5m','5м'],['15m','15м'],['1h','1ч'],['4h','4ч'],['1d','Д']];
function fcBar(){const tf=(TFQ57.find(t=>t[0]===UI.tf)||[0,UI.tf])[1];return `<div class="fcbar"><button data-a="view" data-v="coins"><b>${ctDot(UI.sym,8)}${UI.sym}</b>${ic('down',14)}</button><button data-a="fctf"><b>${tf}</b>${ic('down',14)}</button><span class="fsep"></span>
 <button data-a="ind">${ic('chart',16)} Индикаторы</button><button data-a="fcmore" title="Ещё">${ic('more',18)}</button><button data-a="dwx" data-v="undo" title="Отменить">${ic('undo',18)}</button><span class="fsep"></span><button data-a="fcterm">${ic('dworder',16)} Торговать</button></div>`}
// экран Терминала: строка групп, точки у монет, цвет окон; в режиме «График» — меню снизу
const _tv57=termView;termView=function(){let h=_tv57();
 h=h.replace(/(<button class="chip on" data-a="pairs">)(<b>)/,`$1${ctDot(UI.sym,9)}$2`);
 h=h.replace(/(<div class="mc)(" data-a="mpick" data-v=")(\d+)(">)/g,(m,a,b,i,d)=>{const c=ctCol(UI.multi[+i]);return c?`${a} ctag" style="--ctc:${c}${b}${i}${d}`:m});
 h=h.replace(/(<div class="ml">)([A-Z0-9]+)(\/USDT)/g,(m,a,s,u)=>a+ctDot(s)+s+u);
 h=h.replace(/(data-a="setpair" data-v="([A-Z0-9]+)"[^>]*>)/g,(m,a,s)=>a+ctDot(s,7));
 const i=h.indexOf('<div id="chartWrap">');h=i>=0?h.slice(0,i)+ctBar()+h.slice(i):h;return h+fcBar()};
const _pl57=pairsList;pairsList=function(q){return _pl57(q).replace(/(<tr data-a="setpair" data-v="([A-Z0-9]+)"><td>)/g,(m,a,s)=>a+ctDot(s,9))};
// ---------- торговля с графика: долгое нажатие ----------
let LP=null;
function lpBind(){const w=$('#chartWrap');if(!w||w.dataset.lp)return;w.dataset.lp=1;
 const start=e=>{if(UI.view!=='term'||!CS||UI.layout>1)return;if(typeof DT!=='undefined'&&DT!=='cur')return;if(e.target.closest('.tools,.dwfly,.dwbar,.ol,button,.ctbar'))return;
  const cv=$('#dwc')||$('#ov');if(!cv)return;const r=cv.getBoundingClientRect(),x=e.clientX-r.left,y=e.clientY-r.top;let pw=60;try{pw=CH.priceScale('right').width()}catch(_){}if(x<0||y<0||x>r.width-pw||y>r.height)return;
  clearTimeout(LP&&LP.tm);LP={x0:e.clientX,y0:e.clientY,y,tm:setTimeout(()=>{const p=CS.coordinateToPrice(y);LP=null;if(p!=null&&p>0){try{navigator.vibrate&&navigator.vibrate(18)}catch(_){}lpMenu(p,e.clientX,e.clientY)}},560)}};
 w.addEventListener('pointerdown',start,true);
 w.addEventListener('pointermove',e=>{if(LP&&Math.hypot(e.clientX-LP.x0,e.clientY-LP.y0)>9){clearTimeout(LP.tm);LP=null}},true);
 ['pointerup','pointercancel','pointerleave'].forEach(k=>w.addEventListener(k,()=>{if(LP){clearTimeout(LP.tm);LP=null}},true));
 w.addEventListener('contextmenu',e=>{if(UI.view!=='term'||!CS)return;const cv=$('#dwc')||$('#ov');if(!cv)return;const r=cv.getBoundingClientRect();const p=CS.coordinateToPrice(e.clientY-r.top);if(p==null||p<=0)return;e.preventDefault();lpMenu(p,e.clientX,e.clientY)})}
function lpMenu(p,x,y){const px=+p.toFixed(pd(p)),cur=PX[UI.sym]||(C.length?C[C.length-1].close:0),above=px>cur,t=typeof curTrade==='function'?curTrade():null,real=typeof isReal==='function'&&isReal();
 const live=t&&!real&&(t.rq>0||t.legs.some(l=>!l.f)),pr=fp(px),dist=cur?((px-cur)/cur*100):0;
 const sp=UI.mkt==='spot';
 cpopShow(`<h4>${UI.sym} · цена ${pr} <span class="${dist>=0?'g':'r'}">(${dist>=0?'+':''}${fmt(dist,2)}% от текущей)</span></h4>
 ${above?`<button data-a="lpdo" data-v="sell-lim|${px}" class="r">${ic('dworder',18)} Продать лимитом по ${pr}</button>${sp?'':''}<button data-a="lpdo" data-v="buy-stop|${px}" class="g">${ic('dworder',18)} Купить на пробое ${pr} (стоп-заявка)</button>`
  :`<button data-a="lpdo" data-v="buy-lim|${px}" class="g">${ic('dworder',18)} Купить лимитом по ${pr}</button><button data-a="lpdo" data-v="sell-stop|${px}" class="r">${ic('dworder',18)} Продать на пробое вниз ${pr} (стоп-заявка)</button>`}
 <hr>
 ${live?`<button data-a="lpdo" data-v="t-sl|${px}">Перенести стоп сделки сюда</button><button data-a="lpdo" data-v="t-tp|${px}">Добавить тейк сделки сюда (до 5)</button>`
  :`<button data-a="lpdo" data-v="f-sl|${px}">Стоп-лосс заявки сюда</button><button data-a="lpdo" data-v="f-tp|${px}">Тейк-профит заявки сюда (до 5, доли делятся поровну)</button>`}
 <hr><button data-a="lpdo" data-v="lvl|${px}">${ic('hline',18)} Горизонтальный уровень</button>${typeof dwArr==='function'?'':''}`,x,y)}
function lpDo(v){const [k,ps]=v.split('|'),p=+ps,s=String(p);const redist=a=>{const n=a.length;a.forEach((x,i)=>x.pct=i<n-1?Math.floor(100/n):100-Math.floor(100/n)*(n-1))};
 if(k==='buy-lim'||k==='sell-lim'){F.side=k[0]==='b'?1:-1;F.et='lim';F.px=s;F.trigOn=false;lpForm(`Заявка: ${F.side>0?'купить':'продать'} лимитом по ${fp(p)}. Укажите объём и нажмите «${F.side>0?'Купить':'Продать'}».`)}
 else if(k==='buy-stop'||k==='sell-stop'){F.side=k[0]==='b'?1:-1;F.et='mkt';F.trigOn=true;F.trig=s;lpForm(`Стоп-заявка: ${F.side>0?'купить':'продать'}, когда цена дойдёт до ${fp(p)}. Укажите объём и нажмите «${F.side>0?'Купить':'Продать'}».`)}
 else if(k==='f-sl'){F.slOn=true;F.sl=s;lpForm('Стоп-лосс заявки: '+fp(p))}
 else if(k==='f-tp'){if(F.tps.length>=5)return toast('Не больше 5 тейков');F.tps.push({px:s,pct:0});F.tps.sort((a,b)=>F.side>0?+a.px-+b.px:+b.px-+a.px);redist(F.tps);lpForm(`Тейк-профит ${F.tps.length} из 5: ${fp(p)}. Доли разделены поровну — поменять можно в форме заявки.`)}
 else if(k==='t-sl'){const t=curTrade();if(!t)return;t.sl.px=p;t.slBE=false;tlog(t,'Стоп перенесён на '+fp(p));save();paintOrders();toast('Стоп сделки: '+fp(p))}
 else if(k==='t-tp'){const t=curTrade();if(!t)return;const live=t.tps.filter(x=>!x.hit);if(live.length>=5)return toast('Не больше 5 тейков');t.tps.push({px:p,pct:0,hit:0});redist(t.tps.filter(x=>!x.hit));tlog(t,'Добавлен тейк '+fp(p));save();paintOrders();toast('Тейк добавлен: '+fp(p)+' · доли поделены поровну')}
 else if(k==='lvl'){if(typeof dwArr!=='function')return;dwSave();const o=dwMake('h',[{t:C.length?C[C.length-1].time:0,p}]);dwArr().push(o);LS.set('draw',DRAW);DVER++}}
function lpForm(msg){saveUI();try{LS.set('form',F)}catch(_){}render();setTimeout(()=>{try{syncForm();paintOrders()}catch(_){}const f=$('#form');if(f)f.scrollIntoView({behavior:'smooth',block:'nearest'})},60);toast(msg)}
// ---------- кнопки ----------
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;const st=()=>{e.stopImmediatePropagation();e.preventDefault()};
 if(a==='fcterm'){st();UI.full=0;saveUI();render();return}
 if(a==='fctf'){st();cpAt(el);cpopShow(`<h4>Свечи</h4>${TFQ57.concat([['3d','3 дня'],['1w','Неделя']]).filter(t=>typeof TF_ALL==='undefined'||TF_ALL.includes(t[0])||['1m','5m','15m','1h','4h','1d'].includes(t[0])).map(t=>`<button data-a="tf" data-v="${t[0]}" class="${UI.tf===t[0]?'g':''}">${t[1]}</button>`).join('')}`,CPX,CPY);return}
 if(a==='fcmore'){st();cpAt(el);cpopShow(`<h4>${UI.sym} · график</h4><button data-a="pat">${ic('fig',18)} Фигуры и паттерны ${UI.pat?'— вкл':''}</button><button data-a="fcgrp">${ic('dash',18)} Группы монет</button><button data-a="cmp39">${ic('btc',18)} Сравнить с биткоином</button><button data-a="shot">${ic('cam',18)} Снимок графика</button><button data-a="setlay2">${ic('dash',18)} Несколько графиков</button><hr><button data-a="dwx" data-v="clr" class="r">${ic('trash',18)} Удалить все рисунки</button>`,CPX,CPY);return}
 if(a==='setlay2'){st();cpopHide();UI.full=0;saveUI();render();setTimeout(()=>{try{openSheet('lay')}catch(_){}},50);return}
 if(a==='tf'&&el.closest('#cpop'))cpopHide();
 if((a==='pat'||a==='cmp39'||a==='shot')&&el.closest('#cpop'))cpopHide();
 if(a==='fcgrp'){st();cpAt(el);if(GRPS.length)cpopShow(`<h4>Группы монет</h4>${GRPS.map(g=>`<button data-a="gshow" data-v="${g.id}"><i class="ctsw" style="background:${g.c}"></i>${esc(g.n)}<span class="mu" style="margin-left:auto">${g.s.length}</span></button>`).join('')}<hr><button data-a="gmine">${ic('palette',18)} Группы монеты ${UI.sym}</button><button data-a="gnew" data-v="">${ic('plus',18)} Новая группа</button>`,CPX,CPY);else gMine(UI.sym);return}
 if(a==='gmine'){st();if(!el.closest('#cpop'))cpAt(el);gMine(UI.sym)}
 else if(a==='gshow'){st();if(!el.closest('#cpop'))cpAt(el);gShow(v)}
 else if(a==='gtog'){st();const [id,s]=v.split('|'),g=GRPS.find(x=>x.id===id);if(!g)return;const i=g.s.indexOf(s);i>=0?g.s.splice(i,1):g.s.push(s);gSave();render();if(el.closest('#cpop')&&$('#cpop h4')&&/Группы монеты/.test($('#cpop h4').textContent))gMine(s);else cpopHide()}
 else if(a==='gnew'){st();if(!el.closest('#cpop'))cpAt(el);cpopHide();uiAsk('Название новой группы',`Группа ${GRPS.length+1}`).then(n=>{n=(n||'').trim().slice(0,30);if(!n)return;const g={id:'g'+Date.now().toString(36),n,c:CPAL[(GRPS.length*5+7)%CPAL.length],s:v?[v]:[]};GRPS.push(g);gSave();render();CPX=Math.max(10,innerWidth/2-130);CPY=innerHeight*0.25;gColor(g.id)})}
 else if(a==='gcol'){st();gColor(v)}
 else if(a==='gcset'||a==='gcset2'){st();const [id,c0]=v.split('|'),c=a==='gcset2'?(($('#gcin')||{}).value||'#2962ff'):c0,g=GRPS.find(x=>x.id===id);if(g){g.c=c;gSave()}cpopHide();render()}
 else if(a==='gren'){st();const g=GRPS.find(x=>x.id===v);if(!g)return;cpopHide();uiAsk('Новое название группы',g.n).then(n=>{n=(n||'').trim().slice(0,30);if(n){g.n=n;gSave();render()}})}
 else if(a==='gdel'){st();const g=GRPS.find(x=>x.id===v);if(!g)return;cpopHide();uiOk('Удалить группу «'+g.n+'»?','Монеты останутся в списке, пропадёт только группа.','Удалить',1).then(y=>{if(!y)return;GRPS=GRPS.filter(x=>x.id!==v);gSave();render()})}
 else if(a==='ctopen'){st();cpopHide();UI.sym=v;UI.layout=1;C=[];saveUI();render()}
 else if(a==='gmulti'){st();cpopHide();const g=GRPS.find(x=>x.id===v);if(!g)return;const n=g.s.length>2?4:2;UI.multi=g.s.slice(0,n);while(UI.multi.length<n)UI.multi.push(['BTC','ETH','SOL','BNB'].find(s=>!UI.multi.includes(s)));UI.full=0;UI.layout=n;saveUI();render()}
 else if(a==='lpdo'){st();cpopHide();lpDo(v)}},true);
if(!ICP.check)ICP.check="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z";
if(!ICP.palette)ICP.palette="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z";
const _r57=render;render=function(){const r=_r57();const full=UI.view==='term'&&!!UI.full;document.body.classList.toggle('fullc',full);
 if(full){const n=document.querySelector('nav');if(n)document.documentElement.style.setProperty('--navh',(n.offsetHeight-(parseFloat(getComputedStyle(n).paddingBottom)||0))+'px')}
 if(UI.view==='term')lpBind();return r};
render();
