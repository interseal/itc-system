// ИТК Трейдинг — интерфейс
"use strict";
const ICP={candle:"M9 4H7v2H5v12h2v2h2v-2h2V6H9V4zm10 4h-2V4h-2v4h-2v7h2v5h2v-5h2V8z",full:"M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z",cam:"M12 15.2a3.2 3.2 0 100-6.4 3.2 3.2 0 000 6.4zM9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9z",heart:"M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z",star:"M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z",up:"M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z",dn:"M20 12l-1.41-1.41L13 16.17V4h-2v12.17l-5.58-5.59L4 12l8 8 8-8z",undo:"M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z",chart:"M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z",bell:"M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z",
wallet:"M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z",
list:"M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z",
pie:"M11 2v20c-5.07-.5-9-4.79-9-10s3.93-9.5 9-10zm2.03 0v8.99H22c-.47-4.74-4.24-8.52-8.97-8.99zm0 11.01V22c4.74-.47 8.5-4.25 8.97-8.99h-8.97z",
bot:"M20 9V7c0-1.1-.9-2-2-2h-3c0-1.66-1.34-3-3-3S9 3.34 9 5H6c-1.1 0-2 .9-2 2v2c-1.66 0-3 1.34-3 3s1.34 3 3 3v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-4c1.66 0 3-1.34 3-3s-1.34-3-3-3zM7.5 11.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5S9.83 13 9 13s-1.5-.67-1.5-1.5zM16 17H8v-2h8v2zm-1-4c-.83 0-1.5-.67-1.5-1.5S14.17 10 15 10s1.5.67 1.5 1.5S15.83 13 15 13z",
tune:"M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z",
close:"M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z",
plus:"M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z",
user:"M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z",
down:"M7 10l5 5 5-5z",fx:"M15.6 5.29c-1.1-.1-2.07.71-2.17 1.82L13.18 10H16v2h-3l-.44 5.07c-.2 2.2-2.14 3.83-4.34 3.63-1.37-.12-2.5-.94-3.08-2.07l1.5-1.5c.18.84.89 1.5 1.76 1.57 1.1.1 2.07-.71 2.17-1.82L11 12H8v-2h3.17l.27-3.07c.19-2.2 2.13-3.83 4.33-3.63 1.37.12 2.5.94 3.08 2.07l-1.5 1.5c-.18-.84-.89-1.5-1.75-1.58z",
cursor:"M10.07 14.27a.997.997 0 011.33.48l2.3 4.99 1.8-.83-2.31-5.01a1 1 0 01.48-1.32l.28-.13L18 11.44 6 4.94l1.48 11.67 2.59-2.34z",
hline:"M3 11h18v2H3z",trend:"M3.4 19.4L2 18 16.6 3.4 18 4.8z M19 3a2 2 0 110 4 2 2 0 010-4zM5 17a2 2 0 110 4 2 2 0 010-4z",
fib:"M3 4h18v1.5H3zm0 4.5h18V10H3zm0 4.5h18v1.5H3zm0 4.5h18V19H3z",text:"M5 4v3h5.5v12h3V7H19V4z",
ruler:"M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 10H3V8h2v4h2V8h2v4h2V8h2v4h2V8h2v4h2V8h2v8z",
trash:"M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z",
grid:"M3 3h8v8H3zm10 0h8v8h-8zM3 13h8v8H3zm10 0h8v8h-8z",vp:"M3 5h9v2H3zm0 4h14v2H3zm0 4h18v2H3zm0 4h11v2H3z",
swap:"M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z",
bell2:"M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z",
edit:"M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"};
const ic=(k,s)=>`<svg class="ic" viewBox="0 0 24 24" ${s?`style="width:${s}px;height:${s}px"`:''}><path d="${ICP[k]}"/></svg>`;
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const toast=t=>{const e=$('#toast');e.textContent=t;e.style.display='block';clearTimeout(toast.t);toast.t=setTimeout(()=>e.style.display='none',3500)};
onEvent=(k,t)=>{if(NOTE[k])toast(t)};
const UI=Object.assign({view:'term',mkt:'fut',sym:'SOL',tf:'4h',strat:'st',arrows:true,ind:[],vp:false,layout:1,multi:['BTC','ETH','SOL','BNB'],statP:30,dealTab:'act'},LS.get('ui',{}));
const F=Object.assign({side:1,et:'mkt',px:'',px2:'',n:5,trigOn:false,trig:'',qty:'',lev:5,slOn:false,sl:'',slClose:false,slTf:'1h',trOn:false,tr:1,tps:[],be:false,comment:''},LS.get('form',{}));
const PRM=LS.get('prm',{});
const saveUI=()=>{LS.set('ui',UI);LS.set('form',Object.assign({},F,{qty:'',comment:''}))};
const STR=()=>ITS.list.find(s=>s.id===UI.strat)||ITS.list[0];
const params=s=>{const o={};s.params.forEach(p=>o[p.k]=(PRM[s.id]&&PRM[s.id][p.k]!=null)?PRM[s.id][p.k]:p.v);return o};
let SHEET=null; // открытое нижнее окно
// ---------- шапка и меню ----------
function head(){const a=ACCS.find(x=>x.id===curAcc);return `<header><div class="acc" data-a="accs"><small>${UI.mkt==='fut'?'ФЬЮЧЕРСЫ':'СПОТ'} · ${esc(a.name)} ${ic('down',14)}</small><b id="accbal">$${fmt(equity(),1)}</b></div><button class="plusb" data-a="accadd" title="Новый счёт">${ic('plus')}</button>
 <div class="demo"><small>ДЕМО</small><button class="sw on" data-a="real"></button></div><button class="plusb" data-a="settings" title="Настройки">${ic('user')}</button><div class="tick" id="tick"></div></header>`}
function paintTicker(){const e=$('#tick');if(e)e.innerHTML=['BTC','ETH'].map(s=>`<div>${s}/USD<b>$${fmt(PX[s],PX[s]>=100?0:2)}</b></div>`).join('');const b=$('#accbal');if(b)b.textContent='$'+fmt(equity(),1)}
const NAV=[['port','pie','Портфель'],['term','chart','Терминал'],['lab','flask','Анализ'],['sig','bell','Сигналы'],['bots','bot','Боты'],['deals','list','Сделки'],['stats','wallet','Статистика']];
function nav(){return `<nav>${NAV.map(n=>`<button class="${UI.view===n[0]?'on':''}" data-a="view" data-v="${n[0]}">${ic(n[1])}<span>${n[2]}</span></button>`).join('')}</nav>`}
// ---------- ТЕРМИНАЛ ----------
let CH=null,CS=null,VS=null,LN=[],C=[],klWs=null,lastSig=null,ORDL=[],DRL=[],MINI=[];
const TOOLS=[['cur','cursor','Курсор'],['h','hline','Горизонтальный уровень'],['tr','trend','Линия тренда'],['fib','fib','Уровни Фибоначчи'],['txt','text','Подпись'],['rul','ruler','Линейка'],['clr','trash','Удалить рисунки']];
let TOOL='cur',DRAFT=null;
function termView(){const s=STR(),fut=UI.mkt==='fut',tfn=(TFS.find(t=>t[0]===UI.tf)||[0,UI.tf])[1];
 return head()+`<div class="bar"><div class="seg"><button class="${fut?'on':''}" data-a="mkt" data-v="fut">Фьючерсы</button><button class="${fut?'':'on'}" data-a="mkt" data-v="spot">Спот</button></div>
 <button class="chip on" data-a="pairs"><b>${UI.sym}</b>/USDT ${ic('down',16)}</button><span class="sep"></span>
 ${TFS.filter(t=>TFQ.includes(t[0])||t[0]===UI.tf).map(t=>`<button class="chip ${t[0]===UI.tf?'on':''}" data-a="tf" data-v="${t[0]}">${t[1]}</button>`).join('')}<button class="chip" data-a="tfmore">${ic('down',16)}</button><span class="sep"></span><button class="chip" data-a="ctype">${ic(UI.ctype==='line'||UI.ctype==='area'?'chart':'candle',18)}</button></div>
 <div class="bar"><button class="chip on" data-a="strat">${ic('tune',18)} ${s.name}</button><button class="chip ${UI.arrows?'on':''}" data-a="arrows">Сигналы</button><button class="chip ${UI.ind.length?'on':''}" data-a="ind">${ic('fx',18)} Индикаторы${UI.ind.length?' · '+UI.ind.length:''}</button><button class="chip ${UI.vp?'on':''}" data-a="vp">${ic('vp',18)} Профиль объёма</button><button class="chip ${UI.layout>1?'on':''}" data-a="layout">${ic('grid',18)} ${UI.layout===1?'1 график':UI.layout+' графика'}</button></div>
 ${UI.layout>1?`<div class="multi m${UI.layout}">${UI.multi.slice(0,UI.layout).map((m,i)=>`<div class="mc" data-a="mpick" data-v="${i}"><div class="ml">${m}/USDT · ${tfn} <span id="mp${i}"></span></div><div class="mch" id="mch${i}"></div></div>`).join('')}</div><p class="hint" style="margin:6px 12px">Нажмите на график, чтобы открыть его крупно; монеты в окнах меняются кнопкой «4 графика».</p>`:
 `<div class="cbar"><button class="r" data-a="addsl">+SL</button><button class="g" data-a="addtp">+TP</button><span class="sep"></span><button class="${UI.fills?'on':''}" data-a="fills">Мои сделки ${UI.fills?'вкл':'выкл'}</button><button class="${UI.olines!==false?'on':''}" data-a="olines">Заявки ${UI.olines!==false?'вкл':'выкл'}</button><span class="sep"></span><button data-a="undo" title="Отменить последний рисунок">${ic('undo',18)}</button><span style="flex:1"></span><button class="${UI.autosc!==false?'on':''}" data-a="autosc">Авто</button><button data-a="fullsc" title="Во весь экран">${ic('full',18)}</button><button data-a="shot" title="Снимок графика">${ic('cam',18)}</button></div><div id="chartWrap"><div class="tools">${TOOLS.map(t=>`<button class="${TOOL===t[0]?'on':''}" data-a="tool" data-v="${t[0]}" title="${t[2]}">${ic(t[1],20)}</button>`).join('')}</div>
 <div class="legend"><b id="lg1">${UI.sym}/USDT · ${tfn} · ${fut?'ФЬЮЧЕРСЫ':'СПОТ'}</b><span class="l" id="lg2"></span><span class="l" id="lg3"></span></div><div id="chart"></div><canvas id="ov"></canvas><div id="lbls"></div><div id="toolhint" class="toolhint"></div></div>`}
 <div class="stat" id="stat"></div>
 ${UI.layout===1?`<div class="card" id="btbox"><span class="mu">Считаю стратегию…</span></div>`:''}
 ${formHtml()}`}
function formHtml(){const fut=UI.mkt==='fut',p=PX[UI.sym]||0,sd=F.side;const sw=(k,on)=>`<button class="sw ${on?'on':''}" data-a="fsw" data-v="${k}"></button>`;const ep=formEntryPx();
 const slT=F.slOn&&+F.sl?`<b class="r">${fp(+F.sl)}</b>`:'<b>нет</b>',tps=F.tps.filter(t=>+t.px),tpT=tps.length?`<b class="g">${tps.map(t=>fp(+t.px)).join(', ')}</b>`:'<b>нет</b>';
 return `<div class="card" id="form"><div class="row" style="margin-top:0"><b style="font-size:17px;flex:1">${UI.sym}/USDT</b><span class="mu sm">Свободно ${fmt(D().bal,2)} USDT</span></div>
 <div class="bs" style="margin-bottom:8px"><button class="b ${sd>0?'on':''}" data-a="side" data-v="1">КУПИТЬ</button><button class="s ${sd<0?'on':''}" data-a="side" data-v="-1">ПРОДАТЬ</button></div>
 <div class="seg wide">${[['mkt','Рыночный'],['lim','Лимитный'],['ladder','Лестница']].map(x=>`<button class="${F.et===x[0]?'on':''}" data-a="et" data-v="${x[0]}">${x[1]}</button>`).join('')}</div>
 ${F.et==='lim'?row('Цена',inp('px',F.px||(p?(+p).toFixed(pd(p)):''),'USDT')):''}
 ${F.et==='ladder'?row('Цена от',inp('px',F.px||(p?(p*(1-sd*0.01)).toFixed(pd(p)):''),'USDT'))+row('Цена до',inp('px2',F.px2||(p?(p*(1-sd*0.04)).toFixed(pd(p)):''),'USDT'))+row('Заявок',inp('n',F.n,'шт')):''}
 <div class="row"><label>Триггер</label>${sw('trigOn',F.trigOn)}<span class="mu sm">заявка выставится, когда цена дойдёт до уровня; деньги до этого не заняты</span></div>
 ${F.trigOn?row('Цена триггера',inp('trig',F.trig,'USDT')):''}
 ${row('Кол-во',inp('qty',F.qty,UI.sym))}
 ${row('Сумма',`<div class="in"><input type="number" inputmode="decimal" step="any" id="fsum" value="${+F.qty&&ep?(+F.qty*ep).toFixed(2):''}"><span>USDT</span></div>`)}
 <div class="pct">${[1,5,10,25,50,75,100].map(x=>`<button data-a="pct" data-v="${x}">${x}%</button>`).join('')}</div>
 ${fut?row('Плечо',`<div class="in"><select data-f="lev">${[1,2,3,5,10,15,20,25,50].map(x=>`<option ${x==F.lev?'selected':''} value="${x}">${x}×</option>`).join('')}</select></div>`):''}
 <div class="sltp" data-a="sltp"><div>Стоп-лосс: ${slT}${F.slOn&&F.trOn?' <small class="mu">трейлинг</small>':''}</div><div>Тейк-профит: ${tpT}</div></div>
 ${row('Комментарий',`<div class="in"><input data-f="comment" value="${esc(F.comment)}" placeholder="заметка к сделке"></div>`)}
 <div id="risk" class="risk"></div><div class="err" id="ferr"></div>
 <button class="go ${sd>0?'b':'s'}" data-a="place">${sd>0?'Купить':'Продать'} ${UI.sym}</button>
 <p class="hint">Стоп и тейки можно поставить кнопками «+SL» и «+TP» над графиком, двигать пальцем и убирать крестиком прямо на графике.</p></div>
 <div class="card" id="mydeals"></div>`}
const row=(l,h)=>`<div class="row"><label>${l}</label>${h}</div>`;
const inp=(k,v,u)=>`<div class="in"><input type="number" inputmode="decimal" step="any" data-f="${k}" value="${v==null?'':v}"><span>${u}</span></div>`;
function formEntryPx(){const p=PX[UI.sym]||0;if(F.et==='lim')return +F.px||p;if(F.et==='ladder')return ((+F.px||p)+(+F.px2||p))/2;return F.trigOn&&+F.trig?+F.trig:p}
function paintRisk(){const e=$('#risk');if(!e)return;const ep=formEntryPx(),q=+F.qty,lev=UI.mkt==='fut'?+F.lev:1;if(!(q>0&&ep)){e.innerHTML='';return}const mg=q*ep/lev;let h=`<div><small>Залог</small>${fmt(mg,2)} USDT</div>`;
 if(F.slOn&&+F.sl){const l=(+F.sl-ep)*q*F.side;h+=`<div><small>Убыток по стопу</small><span class="r">${sgn(l)} USDT (${fmt(l/mg*100,1)}%)</span></div>`}
 const tps=F.tps.filter(t=>+t.px);if(tps.length){let rest=100,pr=0;tps.forEach((t,i)=>{const pc=i===tps.length-1?rest:Math.min(rest,+t.pct||0);rest-=pc;pr+=(+t.px-ep)*q*pc/100*F.side});h+=`<div><small>Прибыль по тейкам</small><span class="g">${sgn(pr)} USDT (${fmt(pr/mg*100,1)}%)</span></div>`;
  if(F.slOn&&+F.sl){const l=Math.abs((+F.sl-ep)*q);if(l)h+=`<div><small>Прибыль к риску</small>${fmt(pr/l,2)}</div>`}}
 e.innerHTML=h}
function placeForm(){const er=$('#ferr');er.textContent='';const p=PX[UI.sym];if(!p){er.textContent='Нет текущей цены — подождите пару секунд';return}
 const q=+F.qty;if(!(q>0)){er.textContent='Укажите количество';return}const ep=formEntryPx(),sd=F.side;
 if(F.et==='lim'&&!F.trigOn&&((sd>0&&+F.px>=p)||(sd<0&&+F.px<=p))){er.textContent='Цена лимитной заявки должна быть '+(sd>0?'ниже':'выше')+' рыночной — иначе выберите «Рыночный»';return}
 if(F.slOn&&+F.sl&&((sd>0&&+F.sl>=ep)||(sd<0&&+F.sl<=ep))){er.textContent='Стоп-лосс должен быть '+(sd>0?'ниже':'выше')+' цены входа';return}
 for(const t of F.tps){if(+t.px&&((sd>0&&+t.px<=ep)||(sd<0&&+t.px>=ep))){er.textContent='Тейк-профит должен быть '+(sd>0?'выше':'ниже')+' цены входа';return}}
 const o={sym:UI.sym,mkt:UI.mkt,side:sd,lev:UI.mkt==='fut'?+F.lev:1,qty:q,entry:{type:F.et,px:+F.px,px2:+F.px2,n:+F.n},trig:F.trigOn?+F.trig:0,
  sl:{px:F.slOn?+F.sl:0,trail:F.slOn&&F.trOn?+F.tr:0,close:F.slOn&&F.slClose,tf:F.slTf},tps:F.tps.filter(t=>+t.px).map(t=>({px:+t.px,pct:+t.pct||0,hit:0})),be:F.be,comment:F.comment};
 if(UI.mkt==='spot'&&sd<0){const own=D().trades.filter(t=>t.mkt==='spot'&&t.sym===UI.sym&&t.rq>0);if(!own.length){er.textContent='На споте продать можно только купленную монету';return}own.forEach(t=>closeTrade(t.id,'продажа вручную'));toast('Продано по рынку');render();return}
 const r=createTrade(o);if(typeof r==='string'){er.textContent=r;return}F.comment='';F.qty='';F.slOn=false;F.trOn=false;F.slClose=false;F.tps=[];F.trigOn=false;F.be=false;F.ttOn=false;toast(r.status==='trig'?'Смарт-сделка ждёт триггер':r.status==='open'?'Сделка открыта':'Заявка выставлена');render()}
// ---------- график ----------
function chartOpts(){return {autoSize:true,layout:{background:{color:'#131722'},textColor:'#787b86',fontFamily:'Roboto, Arial, sans-serif'},grid:{vertLines:{color:'#1f2330'},horzLines:{color:'#1f2330'}},rightPriceScale:{borderColor:'#363a45'},timeScale:{borderColor:'#363a45',timeVisible:true},crosshair:{mode:0},localization:{locale:'ru-RU'},handleScroll:{mouseWheel:true,pressedMouseMove:true,horzTouchDrag:true,vertTouchDrag:true}}}
function buildChart(){const el=$('#chart');if(!el)return;CH=LightweightCharts.createChart(el,chartOpts());
 CS=mkMain(CH);CH.priceScale('right').applyOptions({autoScale:UI.autosc!==false});
 VS=CH.addHistogramSeries({priceFormat:{type:'volume'},priceScaleId:'v',lastValueVisible:false,priceLineVisible:false});CH.priceScale('v').applyOptions({scaleMargins:{top:.84,bottom:0}});
 CH.subscribeClick(chartClick);CH.timeScale().subscribeVisibleLogicalRangeChange(()=>drawOverlay());dragInit()}
const volData=c=>c.map(x=>({time:x.time,value:x.volume,color:x.close>=x.open?'#08998144':'#f2364544'}));
async function loadChart(){const key=UI.mkt+UI.sym+UI.tf;try{const k=await klines(UI.mkt,UI.sym,UI.tf,1000);if(key!==UI.mkt+UI.sym+UI.tf||!CS)return;C=k;C.key=key;CS.setData(sdata(C));VS.setData(volData(C));paintStrat();paintInd();paintDraw();paintOrders();CH.timeScale().scrollToRealTime();liveKl();stat24()}
 catch(e){const l=$('#lg2');if(l)l.innerHTML='<span class="err">Нет связи с биржей: '+e.message+'</span>'}}
function liveKl(){try{klWs&&klWs.close()}catch(e){}const key=UI.mkt+UI.sym+UI.tf;klWs=new WebSocket(WSU[UI.mkt]+'ws/'+pair(UI.sym).toLowerCase()+'@kline_'+UI.tf);
 klWs.onmessage=e=>{if(key!==UI.mkt+UI.sym+UI.tf||!CS)return;const k=JSON.parse(e.data).k,b={time:k.t/1000,open:+k.o,high:+k.h,low:+k.l,close:+k.c,volume:+k.v};const L=C[C.length-1];if(L&&L.time===b.time)C[C.length-1]=b;else C.push(b);CS.update(sdata([b])[0]);VS.update(volData([b])[0]);legend();if(k.x){paintStrat();paintInd()}}}
function legend(){const b=C[C.length-1],L=C[C.length-2];if(!b||!L)return;const ch=b.close-L.close,e=$('#lg2');if(e)e.innerHTML=`<span class="${ch>=0?'g':'r'}">${fp(b.close)} ${ch>=0?'+':''}${fp(ch)} (${ch>=0?'+':''}${fmt(ch/L.close*100,2)}%)</span>`}
const sigWord=t=>t==='long'?'покупка':t==='short'?'продажа':'выход';
function paintStrat(){if(!CH||!C.length)return;LN.forEach(l=>{try{CH.removeSeries(l)}catch(e){}});LN=[];const s=STR(),p=params(s);
 if(s.lines)s.lines(C,p).forEach(l=>{const se=CH.addLineSeries({color:l.col,lineWidth:1.5,priceLineVisible:false,lastValueVisible:false,crosshairMarkerVisible:false});se.setData(C.map((x,i)=>l.v[i]==null?{time:x.time}:{time:x.time,value:l.v[i]}));LN.push(se)});
 const done=C.slice(0,-1),sig=s.run(done,p);
 const SM=(UI.arrows?sig.map(x=>({time:done[x.i].time,position:x.t==='long'?'belowBar':'aboveBar',color:x.t==='long'?'#089981':x.t==='short'?'#f23645':'#787b86',shape:x.t==='long'?'arrowUp':x.t==='short'?'arrowDown':'circle',text:x.t==='exit'?'выход':''})):[]).concat(fillMarkers()).sort((a,b)=>a.time-b.time);CS.setMarkers(SM);
 const bt=ITS.backtest(done,sig,{fee:UI.mkt==='fut'?0.05:0.1,spot:UI.mkt==='spot'});const last=sig[sig.length-1],key=UI.mkt+UI.sym+UI.tf+s.id;
 if(last){const sk=key+':'+done[last.i].time;if(lastSig&&lastSig.k===key&&lastSig.v!==sk&&last.i>=done.length-2)event('sig',`${UI.sym}: ${s.name} — сигнал «${sigWord(last.t)}»`);lastSig={k:key,v:sk}}
 const bx=$('#btbox');if(bx)bx.innerHTML=btHtml(bt,s);legend()}
function btHtml(b,s){const op=b.open?`<span class="tag ${b.open.side>0?'long':'short'}">${b.open.side>0?'В покупке':'В продаже'} ${b.open.pnl>=0?'+':''}${fmt(b.open.pnl,2)}%</span>`:'<span class="tag flat">Вне рынка</span>';
 return `<div class="row" style="margin-top:0"><b style="flex:1">${s.name}</b>${op}</div><div class="sgrid"><div><small>Сделок на истории</small>${b.n}</div><div><small>Прибыльных</small>${fmt(b.win,0)}%</div><div><small>Итог стратегии</small><span class="${b.ret>=0?'g':'r'}">${b.ret>=0?'+':''}${fmt(b.ret,1)}%</span></div><div><small>Если просто держать</small><span class="${b.hold>=0?'g':'r'}">${b.hold>=0?'+':''}${fmt(b.hold,1)}%</span></div><div><small>Наибольшая просадка</small><span class="r">−${fmt(b.dd,1)}%</span></div><div><small>Период проверки</small>${C.length-1} свечей</div></div><p class="hint">Проверка на прошлых свечах этого графика с учётом комиссии. Прошлый результат не гарантирует будущий.</p>`}
async function stat24(){const t=await t24(UI.mkt,UI.sym),e=$('#stat');if(!t||!e)return;const ch=+t.priceChangePercent;e.innerHTML=`<span>ЦЕНА<b>${fp(+t.lastPrice)}</b></span><span>ЗА 24Ч<b class="${ch>=0?'g':'r'}">${ch>=0?'+':''}${fmt(ch,2)}%</b></span><span>МАКС<b>${fp(+t.highPrice)}</b></span><span>МИН<b>${fp(+t.lowPrice)}</b></span><span>ОБЪЁМ 24Ч<b>${fmt(+t.quoteVolume/1e6,1)} млн USDT</b></span>`}
// ---------- индикаторы ----------
const INDS=[{id:'ema20',n:'EMA 20',col:'#4fc3f7',f:c=>ITS.ind.ema(c.map(x=>x.close),20)},{id:'ema50',n:'EMA 50',col:'#ffb74d',f:c=>ITS.ind.ema(c.map(x=>x.close),50)},{id:'ema200',n:'EMA 200',col:'#e57373',f:c=>ITS.ind.ema(c.map(x=>x.close),200)},
 {id:'sma20',n:'SMA 20',col:'#aed581',f:c=>ITS.ind.sma(c.map(x=>x.close),20)},{id:'bb',n:'Полосы Боллинджера 20',col:'#64b5f6',multi:c=>{const cl=c.map(x=>x.close),m=ITS.ind.sma(cl,20);const d=cl.map((_,i)=>{if(m[i]==null)return null;let s=0;for(let j=i-19;j<=i;j++)s+=(cl[j]-m[i])**2;return Math.sqrt(s/20)});return [m.map((v,i)=>v==null?null:v+2*d[i]),m,m.map((v,i)=>v==null?null:v-2*d[i])]}},
 {id:'vwap',n:'VWAP (за день)',col:'#f06292',f:c=>{let d=null,pv=0,v=0;return c.map(x=>{const day=Math.floor(x.time/86400);if(day!==d){d=day;pv=0;v=0}pv+=(x.high+x.low+x.close)/3*x.volume;v+=x.volume;return v?pv/v:null})}},
 {id:'rsi',n:'RSI 14 (снизу)',col:'#4dd0e1',pane:true,f:c=>ITS.ind.rsi(c.map(x=>x.close),14)}];
let INDL=[];
function paintInd(){if(!CH||!C.length)return;INDL.forEach(l=>{try{CH.removeSeries(l)}catch(e){}});INDL=[];let pane=false;
 UI.ind.forEach(id=>{const d=INDS.find(x=>x.id===id);if(!d)return;const arrs=d.multi?d.multi(C):[d.f(C)];arrs.forEach(a=>{const o={color:d.col,lineWidth:1,priceLineVisible:false,lastValueVisible:!!d.pane,crosshairMarkerVisible:false};if(d.pane){o.priceScaleId='rsi';pane=true}const se=CH.addLineSeries(o);se.setData(C.map((x,i)=>a[i]==null?{time:x.time}:{time:x.time,value:a[i]}));if(d.pane){se.createPriceLine({price:70,color:'#5c5f6e',lineStyle:2,lineWidth:1,axisLabelVisible:false});se.createPriceLine({price:30,color:'#5c5f6e',lineStyle:2,lineWidth:1,axisLabelVisible:false})}INDL.push(se)})});
 if(pane){CH.priceScale('rsi').applyOptions({scaleMargins:{top:.78,bottom:.02}});CH.priceScale('right').applyOptions({scaleMargins:{top:.06,bottom:.26}});CH.priceScale('v').applyOptions({scaleMargins:{top:.62,bottom:.24}})}
 else{CH.priceScale('right').applyOptions({scaleMargins:{top:.08,bottom:.12}});CH.priceScale('v').applyOptions({scaleMargins:{top:.84,bottom:0}})}}
// ---------- рисование ----------
const DRAW=LS.get('draw',{});const dkey=()=>UI.sym;
const FIBL=[0,.236,.382,.5,.618,.786,1];
function paintDraw(){if(!CS)return;DRL.forEach(x=>{try{x.pl?CS.removePriceLine(x.pl):CH.removeSeries(x.se)}catch(e){}});DRL=[];
 (DRAW[dkey()]||[]).forEach(d=>{if(d.k==='h')DRL.push({pl:CS.createPriceLine({price:d.p,color:'#ffd54f',lineWidth:1,lineStyle:0,axisLabelVisible:true,title:d.t||''})});
  else if(d.k==='txt')DRL.push({pl:CS.createPriceLine({price:d.p,color:'#90caf9',lineWidth:1,lineStyle:3,axisLabelVisible:false,title:d.t})});
  else if(d.k==='fib'){FIBL.forEach(f=>DRL.push({pl:CS.createPriceLine({price:d.a+(d.b-d.a)*f,color:f===0||f===1?'#ffd54f':'#ffb74d88',lineWidth:1,lineStyle:f===0||f===1?0:2,axisLabelVisible:false,title:(f*100).toFixed(1)+'%'})}))}
  else if(d.k==='tr'){const se=CH.addLineSeries({color:'#ffd54f',lineWidth:2,priceLineVisible:false,lastValueVisible:false,crosshairMarkerVisible:false});const a=d.t1<d.t2?[[d.t1,d.p1],[d.t2,d.p2]]:[[d.t2,d.p2],[d.t1,d.p1]];if(a[0][0]===a[1][0])a[1][0]+=1;se.setData(a.map(x=>({time:x[0],value:x[1]})));DRL.push({se})}})}
function chartClick(pr){if(!pr.point||TOOL==='cur')return;const price=CS.coordinateToPrice(pr.point.y),time=pr.time||(C[C.length-1]||{}).time;if(price==null)return;const arr=DRAW[dkey()]=DRAW[dkey()]||[],hint=$('#toolhint');
 if(TOOL==='h'){arr.push({k:'h',p:price});done()}
 else if(TOOL==='txt'){const t=prompt('Текст подписи');if(t){arr.push({k:'txt',p:price,t});done()}}
 else if(TOOL==='tr'||TOOL==='fib'||TOOL==='rul'){if(!DRAFT){DRAFT={p:price,t:time,x:pr.point.x,y:pr.point.y};hint.textContent='Теперь нажмите вторую точку';hint.style.display='block';drawOverlay();return}
  if(TOOL==='tr')arr.push({k:'tr',t1:DRAFT.t,p1:DRAFT.p,t2:time,p2:price});else if(TOOL==='fib')arr.push({k:'fib',a:DRAFT.p,b:price});
  else{const ch=(price-DRAFT.p)/DRAFT.p*100,i1=C.findIndex(c=>c.time===DRAFT.t),i2=C.findIndex(c=>c.time===time);RUL={a:DRAFT,b:{p:price,t:time,x:pr.point.x,y:pr.point.y},txt:`${ch>=0?'+':''}${fmt(ch,2)}% · ${fp(price-DRAFT.p)} · ${Math.abs(i2-i1)} свечей`};DRAFT=null;hint.style.display='none';TOOL='cur';$$('.tools button').forEach(b=>b.classList.toggle('on',b.dataset.v==='cur'));drawOverlay();return}
  done()}
 function done(){DRAFT=null;hint.style.display='none';LS.set('draw',DRAW);paintDraw();drawOverlay()}}
let RUL=null;
// ---------- профиль объёма и линейка (холст поверх графика) ----------
function drawOverlay(){const cv=$('#ov'),w=$('#chartWrap');if(!cv||!w||!CS)return;const W=w.clientWidth-36,H=w.clientHeight,r=devicePixelRatio||1;cv.width=W*r;cv.height=H*r;cv.style.width=W+'px';cv.style.height=H+'px';const g=cv.getContext('2d');g.scale(r,r);g.clearRect(0,0,W,H);
 if(UI.vp&&C.length){const lr=CH.timeScale().getVisibleLogicalRange();if(lr){const a=Math.max(0,Math.floor(lr.from)),b=Math.min(C.length-1,Math.ceil(lr.to));let lo=1e18,hi=-1e18;for(let i=a;i<=b;i++){lo=Math.min(lo,C[i].low);hi=Math.max(hi,C[i].high)}const N=40,bins=Array(N).fill(0),up=Array(N).fill(0),st=(hi-lo)/N||1;
  for(let i=a;i<=b;i++){const c=C[i],tp=(c.high+c.low+c.close)/3,k=Math.min(N-1,Math.floor((tp-lo)/st));bins[k]+=c.volume;if(c.close>=c.open)up[k]+=c.volume}
  const mx=Math.max(...bins)||1,pw=W*0.28,poc=bins.indexOf(mx),right=W-64;
  for(let k=0;k<N;k++){const y1=CS.priceToCoordinate(lo+st*(k+1)),y2=CS.priceToCoordinate(lo+st*k);if(y1==null||y2==null)continue;const h=Math.max(1,y2-y1-1),len=bins[k]/mx*pw,lu=up[k]/mx*pw;g.fillStyle=k===poc?'#f0b90b88':'#08998155';g.fillRect(right-len,y1,lu,h);g.fillStyle=k===poc?'#f0b90b66':'#f2364555';g.fillRect(right-len+lu,y1,len-lu,h)}}}
 const pt=s=>({x:CH.timeScale().timeToCoordinate(s.t),y:CS.priceToCoordinate(s.p)});
 if(DRAFT){const q=pt(DRAFT);if(q.x!=null){g.fillStyle='#ffd54f';g.beginPath();g.arc(q.x,q.y,4,0,7);g.fill()}}
 if(RUL){const a=pt(RUL.a),b=pt(RUL.b);if(a.x!=null&&b.x!=null){const up=RUL.b.p>=RUL.a.p;g.fillStyle=up?'#08998133':'#f2364533';g.fillRect(Math.min(a.x,b.x),Math.min(a.y,b.y),Math.abs(b.x-a.x),Math.abs(b.y-a.y));g.fillStyle='#fff';g.font='12px Roboto';g.fillText(RUL.txt,Math.min(a.x,b.x)+4,Math.min(a.y,b.y)-6)}}}
// ---------- линии сделок на графике: подписи, перетаскивание, крестик ----------
const pc=(p,ep,sd)=>ep?((p-ep)/ep*100*sd):0;
function formLines(){const o=[],sd=F.side,ep=formEntryPx(),q=+F.qty;if(!ep)return o;const nm=sd>0?'ПОКУПКА':'ПРОДАЖА',qt=fmt(q||0,q>=1?3:5)+' '+UI.sym;
 if(F.trigOn&&+F.trig)o.push({k:'trig',p:+F.trig,c:'#ffb74d',a:'ТРИГГЕР',b:qt,x:1});
 if(F.et==='lim')o.push({k:'px',p:+F.px||ep,c:'#2962ff',a:nm,b:qt,x:1});
 if(F.et==='ladder'){o.push({k:'px',p:+F.px,c:'#2962ff',a:nm+' от',b:'',x:1});o.push({k:'px2',p:+F.px2,c:'#2962ff',a:nm+' до',b:F.n+' заявок',x:1})}
 if(F.et==='mkt'&&!F.trigOn&&q>0)o.push({k:'mk',p:ep,c:'#2962ff',a:nm+' по рынку',b:qt,x:1,fixed:1});
 if(F.slOn&&+F.sl)o.push({k:'sl',p:+F.sl,c:'#f23645',a:'SL: '+fmt(pc(+F.sl,ep,sd),1)+'%',b:'100% '+UI.sym,x:1});
 F.tps.forEach((t,i)=>{if(+t.px)o.push({k:'tp'+i,p:+t.px,c:'#089981',a:'TP'+(F.tps.length>1?i+1:'')+': +'+fmt(pc(+t.px,ep,sd),1)+'%',b:(+t.pct||0)+'% '+UI.sym,x:1})});return o.filter(x=>x.p>0)}
function tradeLines(){const o=[];D().trades.filter(t=>t.sym===UI.sym&&t.mkt===UI.mkt).forEach(t=>{const nm=t.side>0?'ПОКУПКА':'ПРОДАЖА',ep=t.ep||((t.legs.find(l=>l.px)||{}).px)||t.trig;
  if(t.status==='trig')o.push({tid:t.id,k:'trig',p:t.trig,c:'#ffb74d',a:'ТРИГГЕР',b:nm+' '+fmt(t.qty,4),x:1});
  t.legs.forEach((l,i)=>{if(!l.f&&l.px)o.push({tid:t.id,k:'leg'+i,p:l.px,c:'#2962ff',a:nm,b:fmt(l.qty,4)+' '+t.sym,x:1})});
  if(t.rq>0){const u=upnl(t),mg=t.rq*t.ep/t.lev;o.push({tid:t.id,k:'ep',p:t.ep,c:u>=0?'#089981':'#f23645',a:(t.side>0?'ЛОНГ ':'ШОРТ ')+fmt(t.rq,4),b:sgn(u)+' $ ('+fmt(u/mg*100,1)+'%)',x:1,fixed:1})}
  if(t.sl.px){const l=t.rq?(t.sl.px-t.ep)*t.rq*t.side:0;o.push({tid:t.id,k:'sl',p:t.sl.px,c:'#f23645',a:(t.sl.trail?'Трейлинг SL: ':t.slBE?'Безубыток: ':'SL: ')+fmt(pc(t.sl.px,ep,t.side),1)+'%',b:t.rq?sgn(l)+' $':'100%',x:1})}
  t.tps.forEach((x,i)=>{if(!x.hit&&x.px)o.push({tid:t.id,k:'tp'+i,p:x.px,c:'#089981',a:'TP'+(t.tps.length>1?i+1:'')+': +'+fmt(pc(x.px,ep,t.side),1)+'%',b:x.pct+'%',x:1})})});return o}
let LINES=[];
function paintOrders(){if(!CS)return;ORDL.forEach(l=>{try{CS.removePriceLine(l)}catch(e){}});ORDL=[];LINES=UI.olines===false?formLines():formLines().concat(tradeLines());
 LINES.forEach(x=>ORDL.push(CS.createPriceLine({price:x.p,color:x.c,lineWidth:x.tid?1:1,lineStyle:x.tid?0:2,axisLabelVisible:true,title:''})));renderLabels()}
function renderLabels(){const box=$('#lbls');if(!box)return;box.innerHTML=LINES.map((x,i)=>`<div class="ol${x.fixed?' fx':''}" data-i="${i}" style="border-color:${x.c}"><span class="olt" style="background:${x.c}">${x.a}</span>${x.b?`<span class="olb">${x.b}</span>`:''}${x.x?`<button class="olx" data-a="olx" data-v="${i}">${ic('close',14)}</button>`:''}</div>`).join('');posLabels()}
function posLabels(){if(!CS)return;const els=[...document.querySelectorAll('#lbls .ol')].map(el=>{const x=LINES[+el.dataset.i];const y=x?CS.priceToCoordinate(x.p):null;return {el,y}});els.forEach(o=>{if(o.y==null||o.y<0)o.el.style.display='none'});const vis=els.filter(o=>o.y!=null&&o.y>=0).sort((a,b)=>a.y-b.y);let last=-1e9;vis.forEach(o=>{let top=o.y-10;if(DRAG&&+o.el.dataset.i===DRAG.i){}else if(top<last+21)top=last+21;last=top;o.el.style.display='flex';o.el.style.top=top+'px'})}
(function loop(){if(CS&&UI.view==='term'&&LINES.length)posLabels();requestAnimationFrame(loop)})();
function relabel(x){const ep=x.tid?null:formEntryPx();if(x.k==='sl')x.a=x.a.replace(/-?[\d ,]+%/,'')+'';}
let DRAG=null;
function dragInit(){const w=$('#chartWrap');if(!w)return;
 const near=y=>{let b=null,bd=10;LINES.forEach((x,i)=>{if(x.fixed)return;const yy=CS.priceToCoordinate(x.p);if(yy!=null&&Math.abs(yy-y)<bd){bd=Math.abs(yy-y);b=i}});return b};
 w.addEventListener('pointerdown',e=>{if(e.target.closest('.tools')||e.target.closest('.olx'))return;const lab=e.target.closest('.ol');if(TOOL!=='cur'&&!lab)return;const r=w.getBoundingClientRect();const i=lab?+lab.dataset.i:near(e.clientY-r.top);if(i==null||LINES[i].fixed)return;DRAG={i,y0:e.clientY,moved:false};CH.applyOptions({handleScroll:false,handleScale:false});try{w.setPointerCapture(e.pointerId)}catch(_){}e.preventDefault()},{passive:false});
 w.addEventListener('pointermove',e=>{if(!DRAG)return;if(Math.abs(e.clientY-DRAG.y0)>2)DRAG.moved=true;const r=w.getBoundingClientRect(),p=CS.coordinateToPrice(e.clientY-r.top);if(p==null)return;const x=LINES[DRAG.i];x.p=+p.toFixed(pd(p));ORDL[DRAG.i].applyOptions({price:x.p});liveLabel(x);const el=document.querySelector(`#lbls .ol[data-i="${DRAG.i}"]`);if(el){el.querySelector('.olt').textContent=x.a;const b=el.querySelector('.olb');if(b)b.textContent=x.b}posLabels()});
 const up=()=>{if(!DRAG)return;const x=LINES[DRAG.i],mv=DRAG.moved;DRAG=null;CH.applyOptions({handleScroll:{mouseWheel:true,pressedMouseMove:true,horzTouchDrag:true,vertTouchDrag:true},handleScale:true});if(mv)applyDrag(x)};w.addEventListener('pointerup',up);w.addEventListener('pointercancel',up)}
function liveLabel(x){let ep,sd;if(x.tid){const t=D().trades.find(y=>y.id===x.tid);if(!t)return;ep=t.ep||((t.legs.find(l=>l.px)||{}).px);sd=t.side;if(x.k==='sl'&&t.rq){x.b=sgn((x.p-t.ep)*t.rq*t.side)+' $'}}else{ep=formEntryPx();sd=F.side}
 if(x.k==='sl')x.a=x.a.replace(/:.*$/,': '+fmt(pc(x.p,ep,sd),1)+'%');else if(x.k.startsWith('tp'))x.a=x.a.replace(/:.*$/,': +'+fmt(pc(x.p,ep,sd),1)+'%');else x.b=fp(x.p)}
function applyDrag(x){const v=x.p;if(!x.tid){if(x.k.startsWith('tp'))F.tps[+x.k.slice(2)].px=v;else F[x.k]=v;syncForm();saveUI();paintOrders();return}
 const t=D().trades.find(y=>y.id===x.tid);if(!t)return;if(x.k==='sl'){t.sl.px=v;tlog(t,'Стоп перенесён на '+fp(v))}else if(x.k.startsWith('tp')){t.tps[+x.k.slice(2)].px=v;tlog(t,'Тейк перенесён на '+fp(v))}else if(x.k==='trig'){t.trig=v;t.trigDir=v>=(PX[t.sym]||v)?1:-1;tlog(t,'Триггер перенесён на '+fp(v))}else if(x.k.startsWith('leg')){const l=t.legs[+x.k.slice(3)];D().bal+=l.qty*(l.px-v)/t.lev;l.px=v;tlog(t,'Заявка перенесена на '+fp(v))}save();toast('Перенесено на '+fp(v));paintOrders()}
function syncForm(){$$('#form [data-f]').forEach(i=>{if(F[i.dataset.f]!=null&&i.type==='number'&&document.activeElement!==i)i.value=F[i.dataset.f]});const s=$('#fsum'),ep=formEntryPx();if(s&&document.activeElement!==s)s.value=+F.qty&&ep?(+F.qty*ep).toFixed(2):'';paintRisk();const sl=$('.sltp');if(sl){const tmp=document.createElement('div');tmp.innerHTML=formHtml();const n=tmp.querySelector('.sltp');if(n)sl.innerHTML=n.innerHTML}}
// крестик на подписи линии
function lineX(i){const x=LINES[i];if(!x)return;if(!x.tid){if(x.k==='sl'){F.slOn=false;F.trOn=false}else if(x.k.startsWith('tp'))F.tps.splice(+x.k.slice(2),1);else if(x.k==='trig')F.trigOn=false;else{F.qty='';F.et='mkt';F.slOn=false;F.tps=[];F.trigOn=false}saveUI();render();return}
 const t=D().trades.find(y=>y.id===x.tid);if(!t)return;
 if(x.k==='sl'){t.sl.px=0;t.sl.trail=0;t.slBE=0;tlog(t,'Стоп-лосс убран');toast('Стоп-лосс убран')}
 else if(x.k.startsWith('tp')){t.tps.splice(+x.k.slice(2),1);tlog(t,'Тейк-профит убран');toast('Тейк-профит убран')}
 else if(confirm(t.rq>0?'Закрыть позицию '+t.sym+' по рынку?':'Отменить заявку '+t.sym+'?')){closeTrade(t.id,t.rq>0?'закрыта на графике':'отменена')}
 save();render()}
// кнопки +SL и +TP
function curTrade(){if(+F.qty>0)return null;return D().trades.find(t=>t.sym===UI.sym&&t.mkt===UI.mkt)||null}
function addSL(){const t=curTrade();if(t){const ep=t.ep||((t.legs.find(l=>l.px)||{}).px)||t.trig||PX[t.sym];t.sl.px=+(ep*(1-t.side*0.01)).toFixed(pd(ep));tlog(t,'Добавлен стоп '+fp(t.sl.px));save();toast('Стоп-лосс добавлен к сделке — двигайте линию на графике');paintOrders();return}
 const ep=formEntryPx();if(!ep)return;F.slOn=true;F.sl=(ep*(1-F.side*0.01)).toFixed(pd(ep));saveUI();paintOrders();syncForm()}
function addTP(){const t=curTrade();const redist=a=>{const n=a.length;a.forEach((x,i)=>x.pct=i<n-1?Math.floor(100/n):100-Math.floor(100/n)*(n-1))};
 if(t){const ep=t.ep||((t.legs.find(l=>l.px)||{}).px)||t.trig||PX[t.sym];const live=t.tps.filter(x=>!x.hit);if(live.length>=5)return toast('Не больше 5 тейков');t.tps.push({px:+(ep*(1+t.side*0.01*(live.length+1))).toFixed(pd(ep)),pct:0,hit:0});redist(t.tps.filter(x=>!x.hit));tlog(t,'Добавлен тейк');save();toast('Тейк-профит добавлен к сделке');paintOrders();return}
 const ep=formEntryPx();if(!ep)return;if(F.tps.length>=5)return toast('Не больше 5 тейков');F.tps.push({px:(ep*(1+F.side*0.01*(F.tps.length+1))).toFixed(pd(ep)),pct:0});redist(F.tps);saveUI();paintOrders();syncForm()}
// окно «Стоп-лосс / Тейк-профит» (как в Capico)
let W=null;
function sltpOpen(tid){const t=tid?D().trades.find(x=>x.id===tid):null;const ep=t?(t.ep||((t.legs.find(l=>l.px)||{}).px)||t.trig||PX[t.sym]):formEntryPx();
 W=t?{tid,sym:t.sym,side:t.side,ep,qty:t.rq||t.qty,lev:t.lev,slOn:!!t.sl.px,sl:t.sl.px||+(ep*(1-t.side*0.1)).toFixed(pd(ep)),trOn:!!t.sl.trail,tr:t.sl.trail||1,slClose:t.sl.close,slTf:t.sl.tf||'1h',tpOn:t.tps.some(x=>!x.hit),tps:t.tps.filter(x=>!x.hit).map(x=>({px:x.px,pct:x.pct})),be:t.be,ttOn:!!t.tpTrail,tt:t.tpTrail||1}
  :{sym:UI.sym,side:F.side,ep,qty:+F.qty||0,lev:UI.mkt==='fut'?+F.lev:1,slOn:F.slOn,sl:+F.sl||+(ep*(1-F.side*0.1)).toFixed(pd(ep)),trOn:F.trOn,tr:F.tr||1,slClose:F.slClose,slTf:F.slTf,tpOn:F.tps.length>0,tps:F.tps.map(x=>({px:+x.px,pct:+x.pct})),be:F.be,ttOn:!!F.ttOn,tt:F.tt||1};
 if(!W.tps.length)W.tps=[{px:+(ep*(1+W.side*0.2)).toFixed(pd(ep)),pct:100}];openSheet('sltp')}
function sltpSheet(){const w=W;if(!w)return '';const p=PX[w.sym],sw=(k,on)=>`<button class="sw ${on?'on':''}" data-a="wsw" data-v="${k}"></button>`;const sp=pc(w.sl,w.ep,w.side);
 const loss=w.qty?(w.sl-w.ep)*w.qty*w.side:0,mg=w.qty*w.ep/w.lev||1;let prof=0,rest=100;w.tps.forEach((t,i)=>{const q=i===w.tps.length-1?rest:Math.min(rest,+t.pct||0);rest-=q;prof+=(t.px-w.ep)*w.qty*q/100*w.side});
 return sheetWrap('Стоп-лосс / Тейк-профит',`<div class="wbox"><b class="${w.side>0?'g':'r'}">${w.side>0?'Покупка (лонг)':'Продажа (шорт)'}</b><div class="sgrid"><div><small>Количество</small>${fmt(w.qty,5)} ${w.sym}</div><div><small>Цена входа</small>${fp(w.ep)}</div><div><small>Текущая цена</small>${fp(p)}</div><div><small>Залог</small>${fmt(mg,2)} USDT</div></div></div>
 <h4 class="r">Стоп-лосс ${sw('slOn',w.slOn)}</h4>${w.slOn?`<div class="wsec">${row('Цена',`<div class="in"><input type="number" step="any" data-w="sl" value="${w.sl}"><span>USDT</span></div>`)}
 <div class="row"><label>Изменение</label><input type="range" class="rng" min="-50" max="0" step="0.1" data-w="slp" value="${Math.max(-50,Math.min(0,sp)).toFixed(1)}"><div class="in" style="flex:none;width:90px"><input type="number" step="0.1" data-w="slp" value="${sp.toFixed(1)}"><span>%</span></div></div>
 <div class="row"><label>Трейлинг</label>${sw('trOn',w.trOn)}${w.trOn?`<div class="in" style="flex:none;width:100px"><input type="number" step="any" data-w="tr" value="${w.tr}"><span>%</span></div><span class="mu sm">стоп тянется за ценой</span>`:''}</div>
 <div class="row"><label>По закрытию свечи</label>${sw('slClose',w.slClose)}${w.slClose?`<div class="in" style="flex:none;width:96px"><select data-w="slTf">${TFS.slice(2).map(t=>`<option value="${t[0]}" ${w.slTf===t[0]?'selected':''}>${t[1]}</option>`).join('')}</select></div>`:''}</div>
 <p class="sm">Ожидаемый убыток: <b class="r">${sgn(loss)} USDT (${fmt(loss/mg*100,2)}%)</b></p></div>`:''}
 <h4 class="g">Тейк-профит ${sw('tpOn',w.tpOn)}</h4>${w.tpOn?`<div class="wsec">${w.tps.map((t,i)=>{const tp=pc(t.px,w.ep,w.side);return `<div class="wtp"><div class="row"><label>Цель ${i+1}</label><div class="in"><input type="number" step="any" data-w="tpx" data-i="${i}" value="${t.px}"><span>USDT</span></div>${w.tps.length>1?`<button class="plusb" data-a="wtpdel" data-v="${i}">${ic('close',18)}</button>`:''}</div>
 <div class="row"><label>Изменение</label><input type="range" class="rng" min="0" max="100" step="0.1" data-w="tpp" data-i="${i}" value="${Math.max(0,Math.min(100,tp)).toFixed(1)}"><div class="in" style="flex:none;width:90px"><input type="number" step="0.1" data-w="tpp" data-i="${i}" value="${tp.toFixed(1)}"><span>%</span></div></div>
 ${w.tps.length>1?`<div class="row"><label>Кол-во</label><input type="range" class="rng" min="1" max="100" step="1" data-w="tpq" data-i="${i}" value="${t.pct}"><div class="in" style="flex:none;width:90px"><input type="number" step="1" data-w="tpq" data-i="${i}" value="${t.pct}"><span>%</span></div></div>`:''}</div>`}).join('')}
 ${w.tps.length<5?`<button class="btn2 wide2" data-a="wtpadd">${ic('plus',16)} ${w.tps.length===1?'Добавить лесенку':'Добавить цель'}</button>`:''}
 <div class="row"><label>Трейлинг тейк</label>${sw('ttOn',w.ttOn)}${w.ttOn?`<div class="in" style="flex:none;width:100px"><input type="number" step="any" data-w="tt" value="${w.tt}"><span>%</span></div>`:'<span class="mu sm">после последней цели не закрывать сразу, а идти за ценой</span>'}</div>
 ${w.tps.length>1||w.tid?`<div class="row"><label>Безубыток</label>${sw('be',w.be)}<span class="mu sm">после первой цели стоп переносится на цену входа</span></div>`:''}
 <p class="sm">Ожидаемая прибыль: <b class="g">${sgn(prof)} USDT (${fmt(prof/mg*100,2)}%)</b></p></div>`:''}
 <div class="err" id="werr"></div><div class="row"><button class="btn2" data-a="shclose">Закрыть</button><button class="go b" style="margin:0;flex:1" data-a="wsave">Сохранить</button></div>`)}
function wInput(el){const w=W,k=el.dataset.w,i=+el.dataset.i,v=+el.value;if(k==='sl')w.sl=v;else if(k==='slp')w.sl=+(w.ep*(1+w.side*v/100)).toFixed(pd(w.ep));else if(k==='tr')w.tr=v;else if(k==='tt')w.tt=v;else if(k==='slTf')w.slTf=el.value;
 else if(k==='tpx')w.tps[i].px=v;else if(k==='tpp')w.tps[i].px=+(w.ep*(1+w.side*v/100)).toFixed(pd(w.ep));else if(k==='tpq')w.tps[i].pct=v;
 const sc=$('.sheet .shin').scrollTop;$('.sheet').outerHTML=sltpSheet();$('.sheet .shin').scrollTop=sc;const f=document.querySelector(`[data-w="${k}"]${el.dataset.i!=null?`[data-i="${i}"]`:''}${el.type==='range'?'.rng':':not(.rng)'}`);if(f&&el.type!=='range'){f.focus();try{f.setSelectionRange(f.value.length,f.value.length)}catch(e){}}}
function wSave(){const w=W,er=$('#werr');if(w.slOn&&((w.side>0&&w.sl>=w.ep)||(w.side<0&&w.sl<=w.ep)))return er.textContent='Стоп-лосс должен быть '+(w.side>0?'ниже':'выше')+' цены входа';
 if(w.tpOn){for(const t of w.tps)if((w.side>0&&t.px<=w.ep)||(w.side<0&&t.px>=w.ep))return er.textContent='Цели должны быть '+(w.side>0?'выше':'ниже')+' цены входа'}
 if(w.tid){const t=D().trades.find(x=>x.id===w.tid);if(!t)return;t.sl.px=w.slOn?w.sl:0;t.sl.trail=w.slOn&&w.trOn?w.tr:0;t.sl.close=w.slOn&&w.slClose;t.sl.tf=w.slTf;t.tps=t.tps.filter(x=>x.hit).concat(w.tpOn?w.tps.map(x=>({px:x.px,pct:x.pct,hit:0})):[]);t.be=w.be;t.tpTrail=w.tpOn&&w.ttOn?w.tt:0;tlog(t,'Изменены стоп и тейки');save()}
 else{F.slOn=w.slOn;F.sl=w.sl;F.trOn=w.trOn;F.tr=w.tr;F.slClose=w.slClose;F.slTf=w.slTf;F.tps=w.tpOn?w.tps.map(x=>({px:x.px,pct:x.pct})):[];F.be=w.be;F.ttOn=w.tpOn&&w.ttOn;F.tt=w.tt;saveUI()}
 W=null;SHEET=null;render();toast('Сохранено')}
// мои сделки под формой
function myDeals(){const e=$('#mydeals');if(!e)return;const bk=D(),act=bk.trades,u=act.reduce((s,t)=>s+upnl(t),0),inv=act.reduce((s,t)=>s+t.rq*t.ep/t.lev,0),h=bk.hist.slice(0,20),pn=bk.hist.reduce((s,x)=>s+x.pnl,0),tab=UI.mdTab||'act';
 e.innerHTML=`<div class="row" style="margin-top:0"><div class="seg"><button class="${tab==='act'?'on':''}" data-a="mdtab" data-v="act">Активные · ${act.length}</button><button class="${tab==='hist'?'on':''}" data-a="mdtab" data-v="hist">История</button></div>${act.length&&tab==='act'?`<button class="btn2 red sm" style="margin-left:auto" data-a="closeall">Закрыть все</button>`:''}</div>
 <div class="mds"><span>Сделок <b>${act.length}</b></span><span>Вложено <b>${fmt(inv,2)} $</b></span><span>PnL <b class="${pn>=0?'g':'r'}">${sgn(pn)}</b></span><span>Открытая <b class="${u>=0?'g':'r'}">${sgn(u)}</b></span></div>
 ${tab==='act'?(act.length?`<table><tr><th>Монета</th><th>Сумма</th><th>Вход</th><th>Прибыль</th><th></th></tr>${act.map(t=>{const x=upnl(t),mg=t.rq*t.ep/t.lev;return `<tr><td data-a="dchart" data-v="${t.id}"><b class="${t.side>0?'g':'r'}">${t.side>0?'↑':'↓'} ${t.sym}</b><br><small class="mu">${STN[t.status]}${t.mkt==='fut'?' · '+t.lev+'×':''}</small></td><td>${fmt(t.rq||t.qty,4)}</td><td>${t.ep?fp(t.ep):'—'}</td><td class="${x>=0?'g':'r'}">${t.rq?sgn(x):'—'}${t.rq?`<br><small>${fmt(x/mg*100,1)}%</small>`:''}</td><td><button class="plusb" data-a="sltpt" data-v="${t.id}">${ic('edit',18)}</button><button class="plusb" data-a="dclose" data-v="${t.id}">${ic('close',18)}</button></td></tr>`}).join('')}</table>`:'<p class="mu sm">Активных сделок нет</p>')
 :(h.length?`<table><tr><th>Монета</th><th>Вход → выход</th><th>Итог</th></tr>${h.map(x=>`<tr><td class="${x.side>0?'g':'r'}">${x.qty?(x.side>0?'↑ ':'↓ '):''}${x.sym}<br><small class="mu">${esc(x.why)}</small></td><td>${x.qty?fp(x.ep)+' → '+fp(x.xp):'—'}</td><td class="${x.pnl>=0?'g':'r'}">${sgn(x.pnl)}</td></tr>`).join('')}</table>`:'<p class="mu sm">Закрытых сделок нет</p>')}`}
// отметки моих сделок на графике
function fillMarkers(){if(!UI.fills||!C.length)return [];const o=[],near=ts=>{ts=ts/1000;let b=C[0].time;for(const c of C){if(c.time<=ts)b=c.time;else break}return b};const bk=D();
 bk.hist.filter(h=>h.sym===UI.sym&&h.qty).slice(0,100).forEach(h=>{o.push({time:near(h.t0||h.t),position:h.side>0?'belowBar':'aboveBar',color:h.side>0?'#089981':'#f23645',shape:h.side>0?'arrowUp':'arrowDown',text:'вход'});o.push({time:near(h.t),position:h.side>0?'aboveBar':'belowBar',color:'#f0b90b',shape:'circle',text:sgn(h.pnl)})});
 bk.trades.filter(t=>t.sym===UI.sym&&t.fq).forEach(t=>o.push({time:near(t.t),position:t.side>0?'belowBar':'aboveBar',color:t.side>0?'#089981':'#f23645',shape:t.side>0?'arrowUp':'arrowDown',text:'вход'}));return o}
// ---------- мультиграфик ----------
function buildMulti(){MINI.forEach(m=>{try{m.ch.remove();m.ws&&m.ws.close()}catch(e){}});MINI=[];UI.multi.slice(0,UI.layout).forEach(async(sym,i)=>{const el=$('#mch'+i);if(!el)return;const ch=LightweightCharts.createChart(el,Object.assign(chartOpts(),{timeScale:{visible:false},rightPriceScale:{borderVisible:false}}));const cs=ch.addCandlestickSeries({upColor:'#089981',downColor:'#f23645',borderVisible:false,wickUpColor:'#089981',wickDownColor:'#f23645'});const m={ch,cs};MINI.push(m);
  try{const k=await klines(UI.mkt,sym,UI.tf,300);cs.setData(k);const L=k[k.length-1];const e=$('#mp'+i);if(e)e.innerHTML=`<b>${fp(L.close)}</b>`;m.ws=new WebSocket(WSU[UI.mkt]+'ws/'+pair(sym).toLowerCase()+'@kline_'+UI.tf);m.ws.onmessage=ev=>{const q=JSON.parse(ev.data).k;cs.update({time:q.t/1000,open:+q.o,high:+q.h,low:+q.l,close:+q.c});const e2=$('#mp'+i);if(e2)e2.innerHTML=`<b>${fp(+q.c)}</b>`}}catch(e){}})}
// ---------- СИГНАЛЫ ----------
function sigView(){return head()+`<div class="bar"><div class="seg"><button class="${UI.mkt==='fut'?'on':''}" data-a="mkt" data-v="fut">Фьючерсы</button><button class="${UI.mkt==='spot'?'on':''}" data-a="mkt" data-v="spot">Спот</button></div><span class="sep"></span>${['15m','1h','4h','1d'].map(t=>`<button class="chip ${t===UI.tf?'on':''}" data-a="tf" data-v="${t}">${TFS.find(x=>x[0]===t)[1]}</button>`).join('')}</div>
 <div class="card"><h3>Сигналы по всем стратегиям</h3><p class="hint">Положение каждой стратегии на последней закрытой свече и её результат на истории. Нажмите строку, чтобы открыть график.</p><div id="sigtab" class="mu">Загружаю графики…</div></div>`}
async function paintSig(){const out=[],v=UI.view;for(const sym of ['BTC','ETH','SOL']){let c;try{c=(await klines(UI.mkt,sym,UI.tf,700)).slice(0,-1)}catch(e){out.push(`<p class="err">${sym}: нет связи с биржей</p>`);continue}
 const rows=ITS.list.map(s=>{const sg=s.run(c,params(s)),b=ITS.backtest(c,sg,{fee:UI.mkt==='fut'?0.05:0.1,spot:UI.mkt==='spot'}),l=sg[sg.length-1];const pos=b.open?(b.open.side>0?'<span class="tag long">Покупка</span>':'<span class="tag short">Продажа</span>'):'<span class="tag flat">Вне рынка</span>';const ago=l?c.length-1-l.i:null;
  return `<tr data-a="open" data-v="${sym}|${s.id}"><td>${s.name}</td><td>${pos}${ago!==null?`<br><small class="mu">${ago===0?'на последней свече':ago+' св. назад'}</small>`:''}</td><td class="${b.ret>=0?'g':'r'}">${b.ret>=0?'+':''}${fmt(b.ret,0)}%</td><td>${fmt(b.win,0)}%</td></tr>`}).join('');
 out.push(`<h3 style="margin:14px 0 4px">${sym}/USDT <small class="mu">${fp(c[c.length-1].close)}</small></h3><table><tr><th>Стратегия</th><th>Сейчас</th><th>На истории</th><th>Прибыльных</th></tr>${rows}</table>`);const e=$('#sigtab');if(e&&UI.view===v)e.innerHTML=out.join('')}}
// ---------- СДЕЛКИ ----------
const STN={trig:'ждёт триггер',wait:'ждёт входа',open:'открыта'};
function dealsView(){const bk=D(),act=bk.trades,dn=bk.hist;return head()+`<div class="bar"><div class="seg"><button class="${UI.dealTab==='act'?'on':''}" data-a="dtab" data-v="act">Активные · ${act.length}</button><button class="${UI.dealTab==='hist'?'on':''}" data-a="dtab" data-v="hist">История · ${dn.length}</button></div></div>
 ${UI.dealTab==='act'?(act.length?act.map(dealCard).join(''):'<div class="card mu">Активных сделок нет. Откройте сделку в терминале.</div>'):histTable(dn)}`}
function dealCard(t){const u=upnl(t),mg=t.rq*t.ep/t.lev||1,p=PX[t.sym];return `<div class="card deal"><div class="row" style="margin-top:0"><b class="${t.side>0?'g':'r'}" style="font-size:16px">${t.side>0?'↑ Покупка':'↓ Продажа'} ${t.sym}</b><span class="tag flat">${t.mkt==='fut'?t.lev+'× фьючерсы':'спот'}</span><span class="tag ${t.status==='open'?'long':'flat'}" style="margin-left:auto">${STN[t.status]}</span></div>
 <div class="sgrid"><div><small>Вход</small>${t.fq?fp(t.ep):t.status==='trig'?'триггер '+fp(t.trig):t.legs.filter(l=>!l.f).map(l=>l.px?fp(l.px):'по рынку').join(', ')}</div><div><small>Сейчас</small>${fp(p)}</div><div><small>Объём</small>${fmt(t.rq||t.qty,4)} ${t.sym}</div><div><small>Прибыль сейчас</small><span class="${u>=0?'g':'r'}">${sgn(u)} (${fmt(u/mg*100,1)}%)</span></div>
 <div><small>Стоп</small>${t.sl.px?fp(t.sl.px):'нет'}${t.sl.trail?' · трейлинг '+t.sl.trail+'%':''}${t.sl.close?' · по закрытию свечи':''}${t.slBE?' · безубыток':''}</div><div><small>Тейки</small>${t.tps.length?t.tps.map(x=>(x.hit?'✓ ':'')+fp(x.px)+' ('+x.pct+'%)').join('<br>'):'нет'}</div>
 ${t.real?`<div><small>Уже зафиксировано</small><span class="${t.real>=0?'g':'r'}">${sgn(t.real)}</span></div>`:''}${t.comment?`<div style="grid-column:1/3"><small>Комментарий</small>${esc(t.comment)}</div>`:''}</div>
 <details><summary class="mu sm">Журнал сделки</summary>${t.log.slice().reverse().map(l=>`<div class="sm"><span class="mu">${new Date(l.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}</span> ${esc(l.s)}</div>`).join('')}</details>
 <div class="row"><button class="btn2" data-a="dchart" data-v="${t.id}">На графике</button><button class="btn2" data-a="dedit" data-v="${t.id}">${ic('edit',16)} Стоп и тейки</button><button class="btn2 red" data-a="dclose" data-v="${t.id}">${t.rq>0?'Закрыть по рынку':'Отменить'}</button></div></div>`}
function histTable(h){return `<div class="card">${h.length?`<table><tr><th>Сделка</th><th>Вход → выход</th><th>Итог</th></tr>${h.slice(0,300).map(x=>`<tr><td class="${x.side>0?'g':'r'}">${x.qty?(x.side>0?'↑ ':'↓ '):''}${x.sym}<br><small class="mu">${new Date(x.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})} · ${esc(x.why)}${x.comment?' · '+esc(x.comment):''}</small></td><td>${x.qty?fp(x.ep)+' → '+fp(x.xp):'—'}</td><td class="${x.pnl>=0?'g':'r'}">${sgn(x.pnl)}</td></tr>`).join('')}</table>`:'<p class="mu">Закрытых сделок пока нет</p>'}</div>`}
function editSheet(id){const t=D().trades.find(x=>x.id===id);if(!t)return '';return sheetWrap('Стоп и тейки: '+t.sym,`${row('Стоп-лосс',`<div class="in"><input type="number" step="any" id="esl" value="${t.sl.px?(+t.sl.px).toFixed(pd(t.sl.px)):''}" placeholder="нет"><span>USDT</span></div>`)}
 ${row('Трейлинг',`<div class="in"><input type="number" step="any" id="etr" value="${t.sl.trail||''}" placeholder="выкл"><span>%</span></div>`)}
 ${t.tps.map((x,i)=>row('Тейк '+(i+1)+(x.hit?' ✓':''),`<div class="in"><input type="number" step="any" class="etp" data-i="${i}" value="${x.px}" ${x.hit?'disabled':''}><span>USDT</span></div>`)).join('')}
 ${t.tps.length<5?row('Новый тейк',`<div class="in"><input type="number" step="any" id="enew" placeholder="цена"><span>USDT</span></div><div class="in" style="flex:none;width:86px"><input type="number" id="enewp" value="100"><span>%</span></div>`):''}
 <button class="go b" data-a="esave" data-v="${t.id}">Сохранить</button>`)}
// ---------- БОТЫ ----------
function botsView(){const bs=D().bots;return head()+`<div class="card"><div class="row" style="margin:0"><h3 style="margin:0;flex:1">Боты</h3><button class="btn2" data-a="botnew">${ic('plus',16)} Создать бота</button></div><p class="hint">Демо-боты работают, пока приложение открыто. Круглосуточная работа — после подключения сервера и настоящего счёта.</p></div>
 ${bs.length?bs.map(botCard).join(''):'<div class="card mu">Ботов пока нет.</div>'}`}
const BT={grid:'Сеточный бот',chan:'Канальный бот',strat:'Бот по стратегии'};
function botCard(b){const run=b.status==='run',p=PX[b.sym]||0;let body='';
 if(b.type==='grid'){const v=run?botValue(b):0,tot=run?v-b.inv:b.final||0;body=`<div class="sgrid"><div><small>Диапазон</small>${fp(b.lo)} — ${fp(b.hi)}</div><div><small>Уровней</small>${b.n}</div><div><small>Вложено</small>${fmt(b.inv,2)} USDT</div><div><small>Сейчас стоит</small>${run?fmt(v,2):'—'}</div><div><small>Прибыль сетки</small><span class="g">${sgn(b.profit)}</span> · ${b.cnt} сделок</div><div><small>Общий итог</small><span class="${tot>=0?'g':'r'}">${sgn(tot)}</span></div></div>${run&&(p<b.lo||p>b.hi)?'<p class="err">Цена вышла из диапазона — бот ждёт возврата.</p>':''}`}
 else if(b.type==='chan'){const v=run?botValue(b):0;body=`<div class="sgrid"><div><small>Канал</small>${fp(b.lo)} — ${fp(b.hi)}</div><div><small>Состояние</small>${b.state==='in'?'в покупке с '+fp(b.ep):'ждёт нижней границы'}</div><div><small>Сумма</small>${fmt(b.amt,2)} USDT</div><div><small>Сейчас стоит</small>${run?fmt(v,2):'—'}</div><div><small>Прибыль</small><span class="${b.profit>=0?'g':'r'}">${sgn(b.profit)}</span> · ${b.cnt} сделок</div><div><small>Стоп</small>${b.sl?b.sl+'% ниже канала':'нет'}</div></div>`}
 else{const s=ITS.list.find(x=>x.id===b.strat)||{};const op=D().trades.filter(t=>t.bot===b.id),pr=D().hist.filter(h=>h.bot===b.id).reduce((a,h)=>a+h.pnl,0);body=`<div class="sgrid"><div><small>Стратегия</small>${s.name}</div><div><small>График</small>${(TFS.find(t=>t[0]===b.tf)||[0,b.tf])[1]} · ${b.mkt==='fut'?b.lev+'× фьючерсы':'спот'}</div><div><small>Сумма на сделку</small>${fmt(b.amt,2)} USDT</div><div><small>Открытых сделок</small>${op.length}</div><div><small>Итог закрытых</small><span class="${pr>=0?'g':'r'}">${sgn(pr)}</span></div><div><small>Стоп / тейк</small>${b.sl?b.sl+'%':'нет'} / ${b.tp?b.tp+'%':'нет'}</div></div>`}
 return `<div class="card"><div class="row" style="margin-top:0"><b style="font-size:16px;flex:1">${BT[b.type]} · ${b.sym}</b><span class="tag ${run?'long':'flat'}">${run?'работает':'остановлен'}</span></div>${body}
 <details><summary class="mu sm">Журнал бота</summary>${b.log.slice().reverse().map(l=>`<div class="sm"><span class="mu">${new Date(l.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}</span> ${esc(l.s)}</div>`).join('')}</details>
 <div class="row">${run?`<button class="btn2 red" data-a="botstop" data-v="${b.id}">Остановить</button>`:`<button class="btn2" data-a="botdel" data-v="${b.id}">Убрать из списка</button>`}</div></div>`}
function botNewSheet(type){const p=PX[UI.sym]||0,sel=`<div class="in"><select id="bsym">${PAIRS.map(s=>`<option ${s===UI.sym?'selected':''}>${s}</option>`).join('')}</select></div>`;
 if(!type)return sheetWrap('Новый бот',`${[['grid','Сеточный бот','Ставит сетку заявок в диапазоне: покупает на каждом шаге вниз и продаёт на шаге вверх. Зарабатывает на колебаниях в боковике.'],['chan','Канальный бот','Покупает у нижней границы канала и продаёт у верхней, повторяет по кругу. Можно задать стоп ниже канала.'],['strat','Бот по стратегии','Сам открывает и закрывает сделки по сигналам выбранной стратегии на закрытии свечи.']].map(x=>`<div class="opt" data-a="botnew" data-v="${x[0]}"><b>${x[1]}</b><p>${x[2]}</p></div>`).join('')}`);
 if(type==='grid')return sheetWrap('Сеточный бот',`${row('Монета',sel)}${row('Нижняя цена',`<div class="in"><input type="number" step="any" id="blo" value="${p?(p*0.92).toFixed(pd(p)):''}"><span>USDT</span></div>`)}${row('Верхняя цена',`<div class="in"><input type="number" step="any" id="bhi" value="${p?(p*1.08).toFixed(pd(p)):''}"><span>USDT</span></div>`)}${row('Уровней',`<div class="in"><input type="number" id="bn" value="20"><span>шт</span></div>`)}${row('Сумма',`<div class="in"><input type="number" step="any" id="binv" value="1000"><span>USDT</span></div>`)}${row('Стоп-лосс',`<div class="in"><input type="number" step="any" id="bsl" placeholder="нет"><span>USDT</span></div>`)}<button class="btn2" data-a="botauto">Взять диапазон с графика (60 свечей)</button><p class="hint">Половину суммы бот сразу вложит в монету для уровней выше цены.</p><div class="err" id="berr"></div><button class="go b" data-a="botgo" data-v="grid">Запустить</button>`);
 if(type==='chan')return sheetWrap('Канальный бот',`${row('Монета',sel)}${row('Нижняя граница',`<div class="in"><input type="number" step="any" id="blo" value="${p?(p*0.97).toFixed(pd(p)):''}"><span>USDT</span></div>`)}${row('Верхняя граница',`<div class="in"><input type="number" step="any" id="bhi" value="${p?(p*1.03).toFixed(pd(p)):''}"><span>USDT</span></div>`)}${row('Сумма',`<div class="in"><input type="number" step="any" id="binv" value="500"><span>USDT</span></div>`)}${row('Стоп ниже канала',`<div class="in"><input type="number" step="any" id="bsl" placeholder="нет"><span>%</span></div>`)}<button class="btn2" data-a="botauto">Взять канал с графика (60 свечей)</button><div class="err" id="berr"></div><button class="go b" data-a="botgo" data-v="chan">Запустить</button>`);
 return sheetWrap('Бот по стратегии',`${row('Монета',sel)}${row('Стратегия',`<div class="in"><select id="bst">${ITS.list.map(s=>`<option value="${s.id}" ${s.id===UI.strat?'selected':''}>${s.name}</option>`).join('')}</select></div>`)}${row('График',`<div class="in"><select id="btf">${TFS.slice(2).map(t=>`<option value="${t[0]}" ${t[0]===UI.tf?'selected':''}>${t[1]}</option>`).join('')}</select></div>`)}${row('Рынок',`<div class="in"><select id="bmkt"><option value="fut" ${UI.mkt==='fut'?'selected':''}>Фьючерсы</option><option value="spot" ${UI.mkt==='spot'?'selected':''}>Спот (только покупки)</option></select></div>`)}${row('Плечо',`<div class="in"><select id="blev">${[1,2,3,5,10].map(x=>`<option>${x}</option>`).join('')}</select></div>`)}${row('Сумма на сделку',`<div class="in"><input type="number" step="any" id="binv" value="500"><span>USDT</span></div>`)}${row('Стоп-лосс',`<div class="in"><input type="number" step="any" id="bsl" placeholder="нет"><span>%</span></div>`)}${row('Тейк-профит',`<div class="in"><input type="number" step="any" id="btp" placeholder="нет"><span>%</span></div>`)}<p class="hint">Настройки стратегии берутся из окна «Стратегия» в терминале. Сигнал проверяется раз в 30 секунд, сделка открывается после закрытия свечи.</p><div class="err" id="berr"></div><button class="go b" data-a="botgo" data-v="strat">Запустить</button>`)}
// ---------- ПОРТФЕЛЬ ----------
const PIEC=['#2962ff','#089981','#f0b90b','#4fc3f7','#f23645','#00bcd4','#ffb74d','#90a4ae','#aed581','#f06292'];
function portView(){const bk=D(),eq=equity(),inT=bk.trades.reduce((s,t)=>s+t.rq*t.ep/t.lev+upnl(t)+t.legs.filter(l=>!l.f&&t.status!=='trig').reduce((a,l)=>a+l.qty*(l.px||PX[t.sym]||0)/t.lev,0),0),inB=bk.bots.filter(b=>b.status==='run').reduce((s,b)=>s+botValue(b),0);
 const by={};by.USDT=bk.bal+bk.bots.filter(b=>b.status==='run').reduce((s,b)=>s+(b.cash||0),0);bk.trades.forEach(t=>{const v=t.rq*t.ep/t.lev+upnl(t);if(v)by[t.sym]=(by[t.sym]||0)+v});bk.bots.filter(b=>b.status==='run'&&b.coin).forEach(b=>by[b.sym]=(by[b.sym]||0)+b.coin*(PX[b.sym]||0));
 const arr=Object.entries(by).filter(x=>x[1]>0.01).sort((a,b)=>b[1]-a[1]),tot=arr.reduce((s,x)=>s+x[1],0)||1,first=bk.eq[0]?bk.eq[0].v:10000,ch=eq-first;
 return head()+`<div class="card"><h3>Портфель</h3><div class="big">$${fmt(eq,2)} <small class="${ch>=0?'g':'r'}">${sgn(ch)} (${fmt(ch/first*100,2)}%)</small></div>
 <div class="sgrid"><div><small>Свободно</small>${fmt(bk.bal,2)} USDT</div><div><small>В сделках</small>${fmt(inT,2)} USDT</div><div><small>В ботах</small>${fmt(inB,2)} USDT</div><div><small>Открытая прибыль</small><span class="${bk.trades.reduce((s,t)=>s+upnl(t),0)>=0?'g':'r'}">${sgn(bk.trades.reduce((s,t)=>s+upnl(t),0))}</span></div></div></div>
 <div class="card"><h3>Распределение</h3><div class="pie"><canvas id="pie" width="300" height="300"></canvas><div>${arr.map((x,i)=>`<div class="pl"><i style="background:${PIEC[i%10]}"></i><b>${x[0]}</b><span>${fmt(x[1],2)} $</span><span class="mu">${fmt(x[1]/tot*100,1)}%</span></div>`).join('')}</div></div></div>
 <div class="card"><h3>Капитал во времени</h3><div id="eqch" style="height:220px"></div>${bk.eq.length<2?'<p class="hint">График появится по мере работы — снимок капитала делается каждые 15 минут и после каждой сделки.</p>':''}</div>`}
function paintPort(){const cv=$('#pie');if(cv){const g=cv.getContext('2d'),bk=D();const by={};by.USDT=bk.bal+bk.bots.filter(b=>b.status==='run').reduce((s,b)=>s+(b.cash||0),0);bk.trades.forEach(t=>{const v=t.rq*t.ep/t.lev+upnl(t);if(v)by[t.sym]=(by[t.sym]||0)+v});bk.bots.filter(b=>b.status==='run'&&b.coin).forEach(b=>by[b.sym]=(by[b.sym]||0)+b.coin*(PX[b.sym]||0));
  const arr=Object.entries(by).filter(x=>x[1]>0.01).sort((a,b)=>b[1]-a[1]),tot=arr.reduce((s,x)=>s+x[1],0)||1;let a0=-Math.PI/2;g.clearRect(0,0,300,300);arr.forEach((x,i)=>{const a1=a0+x[1]/tot*Math.PI*2;g.beginPath();g.moveTo(150,150);g.arc(150,150,140,a0,a1);g.fillStyle=PIEC[i%10];g.fill();a0=a1});g.beginPath();g.arc(150,150,82,0,7);g.fillStyle='#1e222d';g.fill()}
 lineChart('#eqch',D().eq.map(e=>({time:Math.floor(e.t/1000),value:e.v})))}
let EXTRA=[];
function lineChart(sel,data,hist){const el=$(sel);if(!el||data.length<1)return;const ch=LightweightCharts.createChart(el,Object.assign(chartOpts(),{handleScroll:false,handleScale:false}));EXTRA.push(ch);const u=[];const seen=new Set();data.forEach(d=>{if(!seen.has(d.time)){seen.add(d.time);u.push(d)}});
 const s=hist?ch.addHistogramSeries({priceLineVisible:false}):ch.addAreaSeries({lineColor:'#2962ff',topColor:'#2962ff55',bottomColor:'#2962ff05',lineWidth:2,priceLineVisible:false});s.setData(u);ch.timeScale().fitContent()}
// ---------- СТАТИСТИКА ----------
function statsView(){const h=D().hist.filter(x=>!UI.statP||x.t>=Date.now()-UI.statP*864e5),w=h.filter(x=>x.pnl>0),l=h.filter(x=>x.pnl<=0),sum=h.reduce((s,x)=>s+x.pnl,0),gw=w.reduce((s,x)=>s+x.pnl,0),gl=-l.reduce((s,x)=>s+x.pnl,0);
 const bySym={};h.forEach(x=>{const r=bySym[x.sym]=bySym[x.sym]||{n:0,p:0,w:0};r.n++;r.p+=x.pnl;if(x.pnl>0)r.w++});
 return head()+`<div class="bar"><div class="seg">${[[7,'7 дней'],[30,'30 дней'],[90,'90 дней'],[0,'Всё время']].map(x=>`<button class="${UI.statP===x[0]?'on':''}" data-a="statp" data-v="${x[0]}">${x[1]}</button>`).join('')}</div></div>
 <div class="card"><h3>Прибыль и убыток</h3><div class="big ${sum>=0?'g':'r'}">${sgn(sum)} USDT</div><div class="sgrid"><div><small>Сделок</small>${h.length}</div><div><small>Прибыльных</small>${h.length?fmt(w.length/h.length*100,0):0}%</div><div><small>Средняя прибыль</small><span class="g">${w.length?sgn(gw/w.length):'—'}</span></div><div><small>Средний убыток</small><span class="r">${l.length?sgn(-gl/l.length):'—'}</span></div><div><small>Лучшая сделка</small>${h.length?sgn(Math.max(...h.map(x=>x.pnl))):'—'}</div><div><small>Худшая сделка</small>${h.length?sgn(Math.min(...h.map(x=>x.pnl))):'—'}</div><div><small>Фактор прибыли</small>${gl?fmt(gw/gl,2):'—'}</div><div><small>Комиссии учтены</small>да</div></div></div>
 <div class="card"><h3>По дням</h3><div id="dch" style="height:200px"></div>${h.length?'':'<p class="hint">Пока нет закрытых сделок за этот период.</p>'}</div>
 <div class="card"><h3>Нарастающим итогом</h3><div id="cch" style="height:200px"></div></div>
 <div class="card"><h3>По монетам</h3>${Object.keys(bySym).length?`<table><tr><th>Монета</th><th>Сделок</th><th>Прибыльных</th><th>Итог</th></tr>${Object.entries(bySym).sort((a,b)=>b[1].p-a[1].p).map(([s,r])=>`<tr><td>${s}</td><td>${r.n}</td><td>${fmt(r.w/r.n*100,0)}%</td><td class="${r.p>=0?'g':'r'}">${sgn(r.p)}</td></tr>`).join('')}</table>`:'<p class="mu">Нет данных</p>'}</div>`}
function paintStats(){const h=D().hist.filter(x=>!UI.statP||x.t>=Date.now()-UI.statP*864e5).slice().reverse();const day={};h.forEach(x=>{const d=Math.floor(x.t/864e5)*86400;day[d]=(day[d]||0)+x.pnl});const ds=Object.keys(day).map(Number).sort((a,b)=>a-b);
 lineChart('#dch',ds.map(d=>({time:d,value:+day[d].toFixed(2),color:day[d]>=0?'#089981':'#f23645'})),true);let c=0;lineChart('#cch',ds.map(d=>({time:d,value:+(c+=day[d]).toFixed(2)})))}
// ---------- нижние окна ----------
function sheetWrap(title,body){return `<div class="sheet" data-a="shbg"><div class="shin"><h3>${title}<button data-a="shclose">${ic('close')}</button></h3>${body}</div></div>`}
function stratSheet(){const cur=STR();return sheetWrap('Стратегия',`<p class="hint">Нажмите на стратегию — она сразу включится, окно закроется, а сигналы появятся на графике. Настройки выбранной стратегии — внизу окна.</p>${ITS.list.map(s=>`<div class="opt ${s.id===cur.id?'on':''}" data-a="pick" data-v="${s.id}"><b>${s.name}</b><p>${s.about}</p></div>`).join('')}
 <div class="opt" style="opacity:.6;cursor:default"><b>Свои стратегии</b><p>Сюда добавим ваши стратегии — пришлите описание правил, и они появятся в этом списке.</p></div>
 <h3 style="margin-top:16px">Настройки: ${cur.name}</h3>${cur.params.map(p=>`<div class="row"><label style="width:150px">${p.n}</label><div class="in"><input type="number" step="any" data-k="${p.k}" class="prm" value="${params(cur)[p.k]}"></div></div>`).join('')}
 <div class="row"><button class="btn2" data-a="prmsave">Применить</button><button class="btn2" data-a="prmdef">По умолчанию</button></div>`)}
function pairsSheet(){return sheetWrap('Выбор пары',`<div class="in" style="margin-bottom:8px"><input id="psearch" placeholder="Поиск монеты" data-a2="psearch"></div><div id="plist">${pairsList('')}</div>`)}
function pairsList(q){return `<table>${PAIRS.filter(s=>s.includes(q.toUpperCase())).map(s=>`<tr data-a="setpair" data-v="${s}"><td><b>${s}</b><span class="mu">/USDT</span></td><td style="text-align:right">${fp(PX[s])}</td></tr>`).join('')}</table>`}
function tfSheet(){return sheetWrap('Время свечи',`<div class="sgrid">${TFS.map(t=>`<div class="opt ${t[0]===UI.tf?'on':''}" data-a="tf" data-v="${t[0]}" style="margin:0;text-align:center"><b>${t[1]}</b></div>`).join('')}</div>`)}
function indSheet(){return sheetWrap('Индикаторы',`${INDS.map(d=>`<div class="opt ${UI.ind.includes(d.id)?'on':''}" data-a="indt" data-v="${d.id}"><b><i class="dot" style="background:${d.col}"></i> ${d.n}</b></div>`).join('')}`)}
function layoutSheet(){return sheetWrap('Сколько графиков',`<div class="sgrid">${[1,2,4].map(n=>`<div class="opt ${UI.layout===n?'on':''}" data-a="setlay" data-v="${n}" style="margin:0;text-align:center"><b>${n===1?'Один':n===2?'Два':'Четыре'}</b></div>`).join('')}</div>${UI.layout>1?`<h3 style="margin-top:14px">Монеты в окнах</h3>${UI.multi.slice(0,UI.layout).map((m,i)=>row('Окно '+(i+1),`<div class="in"><select class="msel" data-i="${i}">${PAIRS.map(s=>`<option ${s===m?'selected':''}>${s}</option>`).join('')}</select></div>`)).join('')}<button class="btn2" data-a="msave">Применить</button>`:''}`)}
function accSheet(){return sheetWrap('Счета',`${ACCS.map(a=>`<div class="opt ${a.id===curAcc?'on':''}" data-a="setacc" data-v="${a.id}"><b>${esc(a.name)} <span class="tag flat">демо</span></b><p>Капитал $${fmt(equity(BOOK[a.id]),2)} · сделок ${BOOK[a.id].trades.length} · ботов ${BOOK[a.id].bots.filter(b=>b.status==='run').length}</p></div>`).join('')}
 <button class="btn2" data-a="accadd">${ic('plus',16)} Новый демо-счёт</button>
 <div class="opt" style="opacity:.6;cursor:default;margin-top:12px"><b>Настоящий счёт Binance</b><p>Подключим следующим шагом через ключ доступа (без права вывода). Можно будет добавить до нескольких счетов.</p></div>`)}
function settingsSheet(){const a=ACCS.find(x=>x.id===curAcc);const sw=(k,t)=>`<div class="row"><span style="flex:1">${t}</span><button class="sw ${NOTE[k]?'on':''}" data-a="note" data-v="${k}"></button></div>`;
 return sheetWrap('Настройки',`<h4>Счёт «${esc(a.name)}»</h4><div class="row"><div class="in"><input id="accname" value="${esc(a.name)}"></div><button class="btn2" data-a="accren">Переименовать</button></div>
 <div class="row"><button class="btn2" data-a="accreset">Начать заново (10 000 USDT)</button>${ACCS.length>1?`<button class="btn2 red" data-a="accdel">Удалить счёт</button>`:''}</div>
 <h4>Уведомления</h4>${sw('sig','Новые сигналы стратегии')}${sw('fill','Вход в сделку')}${sw('trig','Срабатывание триггера')}${sw('sl','Стоп-лосс')}${sw('tp','Тейк-профит')}${sw('bot','Действия ботов')}${sw('sys','Показывать уведомлением телефона')}
 <p class="hint">Уведомления в Telegram подключим вместе с сервером — тогда они будут приходить, даже когда приложение закрыто.</p>`)}
// ---------- отрисовка ----------
function render(){try{CH&&CH.remove()}catch(e){}CH=null;CS=null;LN=[];INDL=[];DRL=[];ORDL=[];EXTRA.forEach(c=>{try{c.remove()}catch(e){}});EXTRA=[];MINI.forEach(m=>{try{m.ch.remove();m.ws&&m.ws.close()}catch(e){}});MINI=[];
 const V={term:termView,sig:sigView,deals:dealsView,bots:botsView,port:portView,stats:statsView,lab:()=>typeof labView==='function'?labView():'<main class="page"></main>'};
 let sh='';if(SHEET){const [k,v]=SHEET;sh={strat:stratSheet,pairs:pairsSheet,tf:tfSheet,ind:indSheet,lay:layoutSheet,acc:accSheet,set:settingsSheet,edit:()=>editSheet(v),sltp:sltpSheet,bot:()=>botNewSheet(v)}[k]()}
 $('#app').innerHTML=V[UI.view]()+nav()+sh;paintTicker();
 if(UI.view==='term'){if(UI.layout>1)buildMulti();else{buildChart();if(C.length&&C.key===UI.mkt+UI.sym+UI.tf){CS.setData(sdata(C));VS.setData(volData(C));paintStrat();paintInd();paintDraw();paintOrders();stat24();liveKl();setTimeout(drawOverlay,50)}else{C=[];loadChart().then(()=>setTimeout(drawOverlay,50))}}paintRisk();myDeals()}
 if(UI.view==='sig')paintSig();if(UI.view==='port')paintPort();if(UI.view==='stats')paintStats();saveUI()}
function openSheet(k,v){SHEET=[k,v];render();history.pushState({sh:1},'')}
function closeSheet(){SHEET=null;render()}
// ---------- нажатия ----------
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='shbg'){if(e.target===el)closeSheet();return}
 const go=()=>render();
 switch(a){
 case'view':UI.view=v;SHEET=null;go();break;
 case'mkt':UI.mkt=v;C=[];go();break;
 case'tf':UI.tf=v;C=[];SHEET=null;go();break;
 case'tfmore':openSheet('tf');break;
 case'pairs':openSheet('pairs');break;
 case'setpair':if(UI.layout>1&&UI.mpick!=null){UI.multi[UI.mpick]=v;UI.mpick=null}else{UI.sym=v;C=[];RUL=null}SHEET=null;go();break;
 case'strat':openSheet('strat');break;case'ind':openSheet('ind');break;case'layout':openSheet('lay');break;
 case'accs':openSheet('acc');break;case'settings':openSheet('set');break;
 case'shclose':closeSheet();break;
 case'pick':{UI.strat=v;SHEET=null;go();const n=(ITS.list.find(x=>x.id===v)||{}).name;toast('Стратегия «'+n+'» включена — сигналы на графике');break}
 case'prmsave':{const s=STR();PRM[s.id]={};$$('.prm').forEach(i=>PRM[s.id][i.dataset.k]=+i.value);LS.set('prm',PRM);SHEET=null;go();toast('Настройки применены');break}
 case'prmdef':delete PRM[STR().id];LS.set('prm',PRM);go();break;
 case'arrows':UI.arrows=!UI.arrows;go();break;
 case'vp':UI.vp=!UI.vp;go();break;
 case'indt':UI.ind=UI.ind.includes(v)?UI.ind.filter(x=>x!==v):UI.ind.concat(v);go();break;
 case'setlay':UI.layout=+v;go();break;
 case'msave':$$('.msel').forEach(s=>UI.multi[+s.dataset.i]=s.value);SHEET=null;go();break;
 case'mpick':UI.sym=UI.multi[+v];UI.layout=1;C=[];go();break;
 case'tool':if(v==='clr'){if(confirm('Удалить все рисунки на графике '+UI.sym+'?')){DRAW[dkey()]=[];LS.set('draw',DRAW);RUL=null;paintDraw();drawOverlay()}return}TOOL=v;DRAFT=null;$$('.tools button').forEach(b=>b.classList.toggle('on',b.dataset.v===v));{const h=$('#toolhint');const tx={h:'Нажмите на график — появится уровень',tr:'Нажмите первую точку линии',fib:'Нажмите на начало движения',txt:'Нажмите на уровень для подписи',rul:'Нажмите первую точку измерения'}[v];h.textContent=tx||'';h.style.display=tx?'block':'none'}if(v!=='rul'&&RUL){RUL=null;drawOverlay()}break;
 case'side':F.side=+v;if(UI.mkt==='spot'&&F.side<0){}go();break;
 case'et':F.et=v;go();break;
 case'fsw':F[v]=!F[v];if(v==='slOn'&&F.slOn&&!F.sl){const p=formEntryPx();F.sl=p?(p*(1-F.side*0.02)).toFixed(pd(p)):''}if(v==='trigOn'&&F.trigOn&&!F.trig){const p=PX[UI.sym];F.trig=p?(p*(1+F.side*0.01)).toFixed(pd(p)):''}go();break;
 case'tpadd':if(F.tps.length<5){const p=formEntryPx(),n=F.tps.length+1;F.tps.push({px:p?(p*(1+F.side*0.02*n)).toFixed(pd(p)):'',pct:n===1?50:25});go()}break;
 case'tpdel':F.tps.splice(+v,1);go();break;
 case'pct':{const px=formEntryPx();if(!px)return;const lev=UI.mkt==='fut'?+F.lev:1;let q=D().bal*(+v/100)*lev/px*(1-0.003);if(UI.mkt==='spot'&&F.side<0)q=D().trades.filter(t=>t.mkt==='spot'&&t.sym===UI.sym).reduce((s,t)=>s+t.rq,0)*(+v/100);F.qty=q>=1?q.toFixed(3):q.toFixed(5);$('[data-f=qty]').value=F.qty;syncForm();paintOrders();break}
 case'place':placeForm();break;
 case'addsl':addSL();break;case'addtp':addTP();break;
 case'fills':UI.fills=!UI.fills;go();break;case'olines':UI.olines=UI.olines===false;go();break;
 case'undo':{const a=DRAW[dkey()]||[];if(a.length){a.pop();LS.set('draw',DRAW);paintDraw()}else toast('Рисунков нет');break}
 case'olx':lineX(+v);break;
 case'sltp':sltpOpen(null);break;case'sltpt':sltpOpen(v);break;
 case'wsw':W[v]=!W[v];{const sc=$('.sheet .shin').scrollTop;$('.sheet').outerHTML=sltpSheet();$('.sheet .shin').scrollTop=sc}break;
 case'wtpadd':{const L=W.tps[W.tps.length-1],n=W.tps.length+1;W.tps.push({px:+(L.px*(1+W.side*0.05)).toFixed(pd(L.px)),pct:0});W.tps.forEach((x,i)=>x.pct=i<n-1?Math.floor(100/n):100-Math.floor(100/n)*(n-1));$('.sheet').outerHTML=sltpSheet();break}
 case'wtpdel':W.tps.splice(+v,1);if(W.tps.length===1)W.tps[0].pct=100;$('.sheet').outerHTML=sltpSheet();break;
 case'wsave':wSave();break;
 case'mdtab':UI.mdTab=v;myDeals();break;
 case'closeall':if(confirm('Закрыть все сделки и отменить все заявки этого счёта?')){D().trades.slice().forEach(t=>closeTrade(t.id,t.rq>0?'закрыта (все)':'отменена'));go()}break;
 case'dtab':UI.dealTab=v;go();break;
 case'dclose':{const t=D().trades.find(x=>x.id===v);if(t&&confirm(t.rq>0?'Закрыть сделку по рынку?':'Отменить заявку?')){closeTrade(v,t.rq>0?'закрыта вручную':'отменена');go()}break}
 case'dchart':{const t=D().trades.find(x=>x.id===v);if(t){UI.sym=t.sym;UI.mkt=t.mkt;UI.view='term';UI.layout=1;C=[];go()}break}
 case'dedit':sltpOpen(v);break;
 case'esave':{const t=D().trades.find(x=>x.id===v);if(!t)return;const sl=+$('#esl').value,tr=+$('#etr').value;t.sl.px=sl||0;t.sl.trail=tr||0;$$('.etp').forEach(i=>{const x=t.tps[+i.dataset.i];if(!x.hit)x.px=+i.value||x.px});const nw=$('#enew');if(nw&&+nw.value)t.tps.push({px:+nw.value,pct:+$('#enewp').value||100,hit:0});tlog(t,'Изменены стоп и тейки');save();SHEET=null;go();toast('Сохранено');break}
 case'botnew':openSheet('bot',v);break;
 case'botauto':{const s=$('#bsym').value;klines('spot',s,'1h',60).then(k=>{$('#blo').value=Math.min(...k.map(x=>x.low)).toFixed(pd(k[0].low));$('#bhi').value=Math.max(...k.map(x=>x.high)).toFixed(pd(k[0].high))}).catch(()=>toast('Нет связи с биржей'));break}
 case'botgo':{const s=$('#bsym').value,er=$('#berr');let r;if(v==='grid')r=createGrid({sym:s,lo:$('#blo').value,hi:$('#bhi').value,n:+$('#bn').value,inv:$('#binv').value,sl:$('#bsl').value});else if(v==='chan')r=createChan({sym:s,lo:$('#blo').value,hi:$('#bhi').value,amt:$('#binv').value,sl:$('#bsl').value});
   else{const st=$('#bst').value,sd=ITS.list.find(x=>x.id===st);r=createStratBot({sym:s,strat:st,prm:params(sd),tf:$('#btf').value,mkt:$('#bmkt').value,lev:$('#blev').value,amt:$('#binv').value,sl:$('#bsl').value,tp:$('#btp').value})}
   if(typeof r==='string'){er.textContent=r;return}SHEET=null;UI.view='bots';go();break}
 case'botstop':if(confirm('Остановить бота? Монеты бота будут проданы по рынку.')){stopBot(v);go()}break;
 case'botdel':D().bots=D().bots.filter(b=>b.id!==v);save();go();break;
 case'statp':UI.statP=+v;go();break;
 case'open':{const [s,id]=v.split('|');UI.sym=s;UI.strat=id;UI.view='term';UI.layout=1;C=[];go();break}
 case'setacc':curAcc=v;save();SHEET=null;go();break;
 case'accadd':addAcc();SHEET=null;go();toast('Создан новый демо-счёт на 10 000 USDT');break;
 case'accren':{const n=$('#accname').value.trim();if(n){ACCS.find(x=>x.id===curAcc).name=n;save();go()}break}
 case'accreset':if(confirm('Начать счёт заново? Все сделки, боты и история этого счёта будут удалены.')){resetAcc(curAcc);SHEET=null;go()}break;
 case'accdel':if(confirm('Удалить этот счёт полностью?')){delAcc(curAcc);SHEET=null;go()}break;
 case'note':NOTE[v]=!NOTE[v];LS.set('note',NOTE);if(v==='sys'&&NOTE.sys){try{Notification.requestPermission()}catch(e){}}go();break;
 case'real':toast('Настоящий счёт Binance подключим следующим шагом');break;
 }});
document.addEventListener('input',e=>{const f=e.target.dataset.f;if(f){F[f]=e.target.type==='number'?e.target.value:e.target.value;if(f==='px'||f==='px2'||f==='sl'||f==='trig'||f==='qty'){paintOrders();const s2=$('#fsum'),ep=formEntryPx();if(s2&&f!=='px2')s2.value=+F.qty&&ep?(+F.qty*ep).toFixed(2):''}paintRisk();saveUI();return}
 if(e.target.dataset.tp!=null){F.tps[+e.target.dataset.tp][e.target.dataset.k]=e.target.value;paintOrders();paintRisk();saveUI();return}
 if(e.target.dataset.w!=null&&W){if(e.target.type!=='range'||true)wInput(e.target);return}
 if(e.target.id==='fsum'){const ep=formEntryPx();if(ep){F.qty=(+e.target.value/ep).toFixed(6).replace(/0+$/,'').replace(/\.$/,'');const q=$('[data-f=qty]');if(q)q.value=F.qty;paintRisk();paintOrders();saveUI()}return}
 if(e.target.id==='psearch')$('#plist').innerHTML=pairsList(e.target.value)});
document.addEventListener('change',e=>{if(e.target.dataset.w==='slTf'&&W){W.slTf=e.target.value;return}const f=e.target.dataset.f;if(f){F[f]=e.target.value;if(f==='lev')paintRisk();saveUI()}});
// в мультиграфике кнопка выбора пары меняет монету окна: долгое нажатие не нужно — используем меню «графики»
// ---------- «Назад» ----------
history.replaceState({base:1},'');history.pushState({b:1},'');
addEventListener('popstate',()=>{if(SHEET){W=null;closeSheet();history.pushState({b:1},'');return}if(TOOL!=='cur'||RUL){TOOL='cur';RUL=null;DRAFT=null;render();history.pushState({b:1},'');return}if(UI.view!=='term'){UI.view='term';render();history.pushState({b:1},'');return}history.back()});
addEventListener('resize',()=>drawOverlay());
// ---------- живое обновление экранов ----------
let lastPaint=0;PXH.push(()=>{const n=Date.now();if(n-lastPaint<1000&&!engine.changed)return;lastPaint=n;paintTicker();
 if(engine.changed){engine.changed=false;if(UI.view==='term'&&!DRAG){paintOrders();myDeals();paintStrat()}if(UI.view==='deals'||UI.view==='bots'){if(!SHEET)render()}}
 else{if(UI.view==='term'&&!DRAG){if(D().trades.some(t=>t.sym===UI.sym&&t.rq>0))paintOrders();myDeals()}if((UI.view==='deals'||UI.view==='bots')&&!SHEET&&!document.querySelector('details[open]'))render()}});

