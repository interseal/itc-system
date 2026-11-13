// Alex Trade — №504 · новое нижнее меню: Монеты · График · Торговля · Анализ · Меню (12.11.2026).
// «Монеты» — список: избранные, группы своими цветами, все остальные, поиск; нажали монету — открылся график.
// «График» — график последней монеты на весь экран. «Торговля» — прежний терминал.
// «Меню» — Портфель, Сигналы, Боты, счета и ключи биржи, настройки, подписка, регистрация.
"use strict";
Object.assign(ICP,{menu:"M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z",
 more:"M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z",
 star:"M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z",
 staro:"M22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24zM12 15.4l-3.76 2.27 1-4.28-3.32-2.88 4.38-.38L12 6.1l1.71 4.04 4.38.38-3.32 2.88 1 4.28L12 15.4z",
 srch:"M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z",
 chev:"M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z",
 key:"M12.65 10A5.99 5.99 0 007 6c-3.31 0-6 2.69-6 6s2.69 6 6 6a5.99 5.99 0 005.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z",
 gear:"M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 00.12-.61l-1.92-3.32a.488.488 0 00-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 00-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 00-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z",
 person:"M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z",
 crown:"M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"});
// ---------- нижнее меню ----------
NAV.length=0;[['coins','coins','Монеты'],['chart','candle','График'],['term','chart','Торговля'],['lab','flask','Анализ'],['menu','menu','Меню']].forEach(x=>NAV.push(x));
const navOn=k=>{const v=UI.view,full=v==='term'&&!!UI.full;return k==='chart'?full:k==='term'?(v==='term'&&!full):k==='menu'?['menu','port','sig','bots','deals','stats'].includes(v):v===k};
nav=function(){return `<nav>${NAV.map(n=>`<button class="${navOn(n[0])?'on':''}" data-a="view" data-v="${n[0]}">${ic(n[1])}<span>${n[2]}</span></button>`).join('')}</nav>`};
// ---------- «Монеты» ----------
let CLL=null,CLB=false,CLT=0;UI.clf=UI.clf||'all';
const clAv=s=>{let h=0;for(const ch of s)h=(h*31+ch.charCodeAt(0))%360;return `<span class="clav" style="background:hsl(${h},45%,32%)">${esc(s.slice(0,1))}</span>`};
const clVol=v=>v>=1e9?fmt(v/1e9,1)+' млрд $':v>=1e6?fmt(v/1e6,1)+' млн $':fmt(v/1e3,0)+' тыс. $';
function clRow(x){const g=gOf(x.s),c=g[0]?g[0].c:'transparent',fav=UI.fav.includes(x.s),ch=x.ch;
 return `<div class="clr" data-a="clgo" data-v="${x.s}"><i class="clst" style="background:${c}"></i>${clAv(x.s)}<div class="clm"><b>${x.s}<span class="mu">/USDT${UI.mkt==='fut'?' · фьючерс':''}</span></b><small>${g.length?g.map(z=>`<i class="ctsw" style="background:${z.c};width:8px;height:8px"></i>${esc(z.n)}`).join(' '):'Объём за сутки '+clVol(x.vol||0)}</small></div>
 <div class="clp"><b>${x.p?fp(x.p):'—'}</b><small class="${ch>=0?'g':'r'}">${ch>=0?'+':''}${fmt(ch||0,2)}%</small></div><button class="clfv" data-a="clfav" data-v="${x.s}" title="Избранное">${ic(fav?'star':'staro',20)}</button></div>`}
function clBody(){if(!CLL)return '<p class="mu" style="padding:16px">Загружаю монеты…</p>';const q=(UI.clq||'').trim().toUpperCase(),by=new Map(CLL.map(x=>[x.s,x]));
 const pick=a=>a.map(s=>by.get(s)||{s,p:PX[s]||0,ch:0,vol:0});let html='';
 if(q){const L=CLL.filter(x=>x.s.includes(q)).sort((a,b)=>(a.s.startsWith(q)?0:1)-(b.s.startsWith(q)?0:1)||b.vol-a.vol).slice(0,100);return L.length?L.map(clRow).join(''):'<p class="mu" style="padding:16px">Ничего не нашлось</p>'}
 const sec=(t,L,col)=>L.length?`<div class="clh">${col?`<i class="ctsw" style="background:${col}"></i>`:''}${t}<span class="mu">${L.length}</span></div>`+L.map(clRow).join(''):'';
 if(UI.clf==='fav')return sec('Избранные',pick(UI.fav))||'<p class="mu" style="padding:16px">Избранных пока нет — нажмите звёздочку у монеты.</p>';
 if(UI.clf.startsWith('g:')){const g=GRPS.find(z=>z.id===UI.clf.slice(2));return g?sec(esc(g.n),pick(g.s),g.c)||'<p class="mu" style="padding:16px">В группе нет монет.</p>':''}
 html+=sec('Избранные',pick(UI.fav));GRPS.forEach(g=>html+=sec(esc(g.n),pick(g.s),g.c));
 const shown=new Set(UI.fav.concat(...GRPS.map(g=>g.s))),rest=CLL.filter(x=>!shown.has(x.s)).sort((a,b)=>b.vol-a.vol),n=UI.clN||120;
 html+=sec('Все монеты · по объёму',rest.slice(0,n));if(rest.length>n)html+=`<button class="btn2" style="width:calc(100% - 20px);margin:10px;padding:10px" data-a="clmore">Показать ещё ${Math.min(200,rest.length-n)} из ${rest.length-n}</button>`;return html}
function coinsView(){return `<main class="page clpage"><div class="clhd"><b style="font-size:20px;flex:1">Монеты</b><div class="seg"><button class="${UI.mkt==='fut'?'on':''}" data-a="clmkt" data-v="fut">Фьючерсы</button><button class="${UI.mkt==='spot'?'on':''}" data-a="clmkt" data-v="spot">Спот</button></div></div>
 <div class="clsr">${ic('srch',20)}<input id="clq" placeholder="Поиск монеты" value="${esc(UI.clq||'')}" autocomplete="off"></div>
 <div class="ctbar"><button class="${UI.clf==='all'?'on':''}" data-a="clf" data-v="all">Все</button><button class="${UI.clf==='fav'?'on':''}" data-a="clf" data-v="fav">${ic('star',14)} Избранные</button>${GRPS.map(g=>`<button class="${UI.clf==='g:'+g.id?'on':''}" data-a="clf" data-v="g:${g.id}"><i class="ctsw" style="background:${g.c}"></i>${esc(g.n)}</button>`).join('')}<button data-a="gnew" data-v="">${ic('plus',14)} Группа</button></div>
 <div id="cll">${clBody()}</div></main>`}
async function clLoad(force){if(CLB)return;if(!force&&CLL&&Date.now()-CLT<15e3)return;CLB=true;try{CLL=await loadPairs(UI.mkt);CLT=Date.now();clPaint()}catch(e){if(!CLL){const el=$('#cll');if(el)el.innerHTML='<p class="err" style="padding:16px">Нет связи с биржей — список монет не загрузился.</p>'}}finally{CLB=false}}
function clPaint(){const el=$('#cll');if(el&&UI.view==='coins')el.innerHTML=clBody()}
setInterval(()=>{if(UI.view==='coins'&&!document.hidden){if(PL&&PL[UI.mkt])PL[UI.mkt].t=0;clLoad(true)}},20000);
document.addEventListener('input',e=>{if(e.target.id==='clq'){UI.clq=e.target.value;clPaint()}});
// ---------- «Меню» ----------
function menuView(){const it=(a,v,icn,t,s,soon)=>`<div class="mnr" data-a="${a}" ${v?`data-v="${v}"`:''}><span class="mni">${ic(icn,22)}</span><div style="flex:1"><b>${t}</b><small>${s}</small></div>${soon?'<span class="mnsoon">скоро</span>':ic('chev',20)}</div>`;
 return head()+`<main class="page"><div class="card mnc">
 ${it('view','port','pie','Портфель','баланс, открытые сделки, история, статистика')}
 ${it('view','sig','bell','Сигналы','положение стратегий по монетам прямо сейчас')}
 ${it('view','bots','bot','Боты','канальный, сетка, по стратегии, серии, бустер')}</div>
 <div class="card mnc">${it('accs','','key','Счета и ключи биржи','демо-счёт, настоящие счета Binance, добавить ключ')}
 ${it('settings','','gear','Настройки','вид, язык цифр, оповещения')}</div>
 <div class="card mnc">${it('mnsub','','crown','Подписка','тарифы и оплата',1)}${it('mnreg','','person','Регистрация и вход','свой аккаунт в Alex Trade',1)}</div>
 <p class="hint" style="text-align:center">Alex Trade · сделано в ИТК</p></main>`}
// ---------- стили ----------
(function(){const st=document.createElement('style');st.textContent=`
.clhd{display:flex;align-items:center;gap:8px;padding:12px 12px 6px}
.clsr{display:flex;align-items:center;gap:8px;margin:4px 10px 6px;padding:0 12px;border:1px solid var(--ln);border-radius:12px;background:#131722;color:var(--mu)}
.clsr input{flex:1;border:0;background:none;color:#d1d4dc;font:inherit;font-size:16px;padding:11px 0;outline:none}
.clh{display:flex;align-items:center;gap:6px;padding:12px 14px 4px;font-size:13px;color:var(--mu);font-weight:500}.clh .mu{margin-left:auto}
.clr{display:flex;align-items:center;gap:10px;padding:9px 6px 9px 0;border-bottom:1px solid #1f2330;cursor:pointer;position:relative}
.clr:active{background:#1e222d}.clst{width:4px;align-self:stretch;border-radius:0 3px 3px 0;flex:none}
.clav{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:15px;flex:none}
.clm{flex:1;min-width:0}.clm b{display:block;font-size:15px;white-space:nowrap}.clm b .mu{font-weight:400;font-size:12.5px}.clm small{display:flex;align-items:center;gap:4px;color:var(--mu);font-size:12px;white-space:nowrap;overflow:hidden}
.clp{text-align:right}.clp b{display:block;font-size:15px}.clp small{font-size:12.5px}
.clfv{border:0;background:none;color:#787b86;padding:6px;cursor:pointer}.clfv svg{fill:currentColor}.clr .clfv:has(path[d^="M12 17.27"]){color:#ffd54f}
.mnc{padding:4px 0!important}.mnr{display:flex;align-items:center;gap:12px;padding:12px 14px;cursor:pointer;border-bottom:1px solid #1f2330}.mnr:last-child{border-bottom:0}
.mnr b{display:block;font-size:15px}.mnr small{color:var(--mu);font-size:12.5px}.mni{width:38px;height:38px;border-radius:10px;background:#0f2a4d;color:#5eb8ff;display:grid;place-items:center}.mni svg{fill:currentColor}
.mnsoon{font-size:11px;color:#ffb74d;border:1px solid #ffb74d66;border-radius:8px;padding:2px 7px}`;document.head.appendChild(st)})();
// ---------- переходы ----------
function fcOn(){if(!UI.full){UI.fcKeep={arrows:UI.arrows,plans:UI.plans};UI.arrows=false;UI.plans=false}UI.full=1;UI.view='term';if(UI.layout>1)UI.layout=1}
function fcOff(){if(UI.full&&UI.fcKeep){UI.arrows=UI.fcKeep.arrows;UI.plans=UI.fcKeep.plans}UI.full=0}
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;const st=()=>{e.stopImmediatePropagation();e.preventDefault()};
 if(a==='view'){if(typeof cpopHide==='function')cpopHide();
  if(v==='chart'){st();SHEET=null;fcOn();saveUI();render();window.scrollTo(0,0)}
  else if(v==='coins'||v==='menu'){st();SHEET=null;if(UI.view==='term'&&UI.full){/* из графика в список — график остаётся «на весь экран» */}else fcOff();UI.view=v;saveUI();render();window.scrollTo(0,0)}
  else if(v==='term'){fcOff()}
  else fcOff()}
 else if(a==='fcterm'){fcOff()}
 else if(a==='clgo'&&!e.target.closest('[data-a=clfav]')){st();SHEET=null;if(UI.sym!==v){UI.sym=v;C=[]}fcOn();saveUI();render();window.scrollTo(0,0)}
 else if(a==='clfav'){st();UI.fav=UI.fav.includes(v)?UI.fav.filter(x=>x!==v):UI.fav.concat(v);saveUI();clPaint()}
 else if(a==='clf'){st();UI.clf=v;saveUI();render()}
 else if(a==='clmore'){st();UI.clN=(UI.clN||120)+200;clPaint()}
 else if(a==='clmkt'){st();if(UI.mkt!==v){UI.mkt=v;CLL=null;C=[];saveUI();render();clLoad(true)}}
 else if(a==='mnsub'||a==='mnreg'){st();toast(a==='mnsub'?'Подписка появится в следующих версиях':'Регистрация появится в следующих версиях — сейчас вход через магазин itcpartner.store')}},true);
// ---------- свои экраны в общем render ----------
const _r58=render;render=function(){if(UI.view==='chart')fcOn();
 if(UI.view==='coins'||UI.view==='menu'){try{CH&&CH.remove()}catch(e){}CH=null;CS=null;try{MINI.forEach(m=>{try{m.ch.remove();m.ws&&m.ws.close()}catch(_){}});MINI=[]}catch(_){}
  document.body.classList.remove('fullc');const sh=SHEET?(()=>{try{const [k,v]=SHEET;return ({acc:accSheet,set:settingsSheet})[k]?.()||''}catch(_){return ''}})():'';
  $('#app').innerHTML=(UI.view==='coins'?coinsView():menuView())+nav()+sh;try{paintTicker()}catch(_){}saveUI();if(UI.view==='coins')clLoad();return}
 return _r58()};
render();
