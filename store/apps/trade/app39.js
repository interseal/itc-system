// Alex Trade — графики в духе профессиональных терминалов (11.11.2026).
// 1) позиция на графике: зелёная зона до тейка, красная до стопа, в долларах;
// 2) отчёт проверки: кривая денег при $100 на сделку и главные показатели;
// 3) подсказка свечи при наведении; 4) сравнение с биткоином на графике;
// 6) оповещения на уровнях; 7) повтор истории; 8) избранные монеты над графиком.
"use strict";
Object.assign(ICP,{replay:"M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z",pause:"M6 19h4V5H6v14zm8-14v14h4V5h-4z",
 next:"M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z",prev:"M6 6h2v12H6zm3.5 6l8.5 6V6z",play_arrow:"M8 5v14l11-7z",cmp:"M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"});
document.head.insertAdjacentHTML('beforeend',`<style>
.wl39{display:flex;gap:6px;overflow-x:auto;padding:6px 12px 2px;scrollbar-width:none}.wl39::-webkit-scrollbar{display:none}
.wl39 button{flex:0 0 auto;border:1px solid var(--ln);background:var(--pan);color:var(--tx);border-radius:6px;padding:4px 10px;font:inherit;font-size:12px;cursor:pointer;text-align:left;line-height:1.25}
.wl39 button.on{border-color:var(--ac)}.wl39 b{font-size:13px}
#rp39{position:absolute;left:44px;right:8px;top:30px;z-index:6;background:rgba(19,23,34,.94);border:1px solid var(--ln);border-radius:6px;padding:6px 8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px}
#rp39 button{background:var(--pan2);border:0;color:var(--tx);border-radius:4px;padding:4px 8px;cursor:pointer;font:inherit;display:inline-flex;align-items:center;gap:2px}
#rp39 button.on{background:var(--ac);color:#fff}#rp39 input{flex:1;min-width:120px;accent-color:var(--ac)}
.legend .l{font-variant-numeric:tabular-nums}
</style>`);
const f39=v=>v==null||isNaN(v)?'—':fp(v);
const vol39=v=>v>=1e9?fmt(v/1e9,2)+' млрд':v>=1e6?fmt(v/1e6,2)+' млн':v>=1e3?fmt(v/1e3,1)+' тыс.':fmt(v,0);
// ---------- 3) подсказка свечи и 4) сравнение с BTC ----------
let CMPS=null,CMPK=null,CMPB=null;
const _bc39=buildChart;buildChart=function(){CMPS=null;_bc39();if(!CH)return;
 CH.subscribeCrosshairMove(p=>{const e=$('#lg2');if(!e)return;if(!p||!p.time||!p.seriesData){legend();return}const d=p.seriesData.get(CS);if(!d||d.open==null){legend();return}
  const i=C.findIndex(x=>x.time===p.time),pr=i>0?C[i-1].close:d.open,ch=(d.close-pr)/pr*100,cl=d.close>=d.open?'g':'r';
  e.innerHTML=`<span class="mu">О</span> <span class="${cl}">${f39(d.open)}</span> <span class="mu">М</span> <span class="${cl}">${f39(d.high)}</span> <span class="mu">Мин</span> <span class="${cl}">${f39(d.low)}</span> <span class="mu">З</span> <span class="${cl}">${f39(d.close)} ${ch>=0?'+':''}${fmt(ch,2)}%</span>${i>=0&&C[i].volume?` <span class="mu">Объём</span> ${vol39(C[i].volume)}`:''}${MK39.has(p.time)?` · <b>Сигнал: ${esc(MK39.get(p.time))}</b>`:''}`});
 CH.timeScale().subscribeVisibleLogicalRangeChange(()=>{if(UI.cmp)requestAnimationFrame(cmpPaint)})};
async function cmpLoad(){if(!UI.cmp||!CH||!C.length)return;if(UI.sym==='BTC'){UI.cmp=false;saveUI();toast('Это и есть биткоин — выберите другую монету');return}
 const key=UI.mkt+'|'+UI.tf;try{if(CMPK!==key||!CMPB||Date.now()-CMPB.t>60e3){CMPB={c:await klines(UI.mkt,'BTC',UI.tf,1000),t:Date.now()};CMPK=key}}catch(_){return}
 if(!CMPS)CMPS=CH.addLineSeries({color:'#ff9800',lineWidth:2,priceLineVisible:false,lastValueVisible:false,crosshairMarkerVisible:false,title:'BTC'});cmpPaint()}
function cmpPaint(){if(!UI.cmp||!CMPS||!CMPB||!C.length)return;const lr=CH.timeScale().getVisibleLogicalRange(),a=Math.max(0,Math.min(C.length-1,lr?Math.ceil(lr.from):0));
 const M=new Map(CMPB.c.map(x=>[x.time,x.close]));let j=a;while(j<C.length&&!M.has(C[j].time))j++;if(j>=C.length)return;const b0=M.get(C[j].time),c0=C[j].close;
 CMPS.setData(C.filter(x=>M.has(x.time)).map(x=>({time:x.time,value:c0*M.get(x.time)/b0})));
 const L=C[C.length-1],bl=M.get(L.time)||CMPB.c[CMPB.c.length-1].close,cc=(L.close/c0-1)*100,bc=(bl/b0-1)*100,e=$('#lg3');
 if(e)e.innerHTML=`<span style="color:#ff9800">━ BTC ${bc>=0?'+':''}${fmt(bc,1)}%</span> · <b>${UI.sym} ${cc>=0?'+':''}${fmt(cc,1)}%</b> <span class="mu">за видимый участок · ${cc>bc?UI.sym+' сильнее':'BTC сильнее'}</span>`}
function cmpOff(){try{CMPS&&CH.removeSeries(CMPS)}catch(_){}CMPS=null;const e=$('#lg3');if(e)e.innerHTML=''}
const _lc39=loadChart;loadChart=async function(){if(REP)rpStop(true);await _lc39();cmpLoad()};
// ---------- 1) позиция на графике ----------
function posList(){const o=[];try{D().trades.filter(t=>t.status==='open'&&t.sym===UI.sym&&t.mkt===UI.mkt&&t.ep).forEach(t=>{const tp=(t.tps||[]).find(x=>!x.hit&&+x.px);o.push({ep:t.ep,sl:t.sl&&+t.sl.px||0,tp:tp?+tp.px:0,side:t.side,val:(t.rq||t.qty||0)*t.ep,t:t.t,lab:'Ваша сделка'})})}catch(_){}
 if(UI.plans&&UI.planSel!=null&&PLANS[UI.planSel]){const x=PLANS[UI.planSel];o.push({ep:x.entry,sl:x.sl,tp:(x.tps||[])[0]||0,side:x.side,val:100,t:0,lab:'План '+(UI.planSel+1)})}return o}
function posDraw(g,W){const L=posList();if(!L.length||!CS)return;const rw=(CH.priceScale('right').width()||64),right=W-rw-2;
 L.forEach(P=>{const y0=CS.priceToCoordinate(P.ep);if(y0==null)return;let x0=right-(P.t?150:240);
  if(P.t){let i=C.length-1;while(i>0&&C[i].time*1000>P.t)i--;const xx=CH.timeScale().logicalToCoordinate(i);if(xx!=null&&xx<right-40)x0=Math.max(0,xx)}
  const box=(p,col,txt)=>{if(!p)return;const y=CS.priceToCoordinate(p);if(y==null)return;g.fillStyle=col+'2e';g.fillRect(x0,Math.min(y0,y),right-x0,Math.abs(y-y0));g.strokeStyle=col;g.lineWidth=1;g.beginPath();g.moveTo(x0,y);g.lineTo(right,y);g.stroke();
   g.font='600 11px Roboto, Arial';const tw=g.measureText(txt).width+10,ty=y<y0?Math.max(2,y-18):y+2;g.fillStyle=col;g.fillRect(x0+4,ty,tw,16);g.fillStyle='#fff';g.fillText(txt,x0+9,ty+12)};
  const pc=p=>(p-P.ep)/P.ep*100*P.side,usd=p=>P.val*pc(p)/100;
  if(P.tp)box(P.tp,'#089981',`Тейк ${USD(usd(P.tp))} · ${pc(P.tp)>=0?'+':''}${fmt(pc(P.tp),2)}%`);
  if(P.sl)box(P.sl,'#f23645',`Стоп ${USD(usd(P.sl))} · ${fmt(pc(P.sl),2)}%`);
  g.strokeStyle='#d1d4dc';g.setLineDash([4,3]);g.beginPath();g.moveTo(x0,y0);g.lineTo(right,y0);g.stroke();g.setLineDash([]);
  if(P.tp&&P.sl){const rr=Math.abs(pc(P.tp)/pc(P.sl));g.font='600 11px Roboto, Arial';const t=`${P.lab} · ${P.side>0?'лонг':'шорт'} · 1 к ${fmt(rr,1)}`,tw=g.measureText(t).width+10;g.fillStyle='#2a2e39';g.fillRect(x0+4,y0-8,tw,16);g.fillStyle='#d1d4dc';g.fillText(t,x0+9,y0+4)}})}
const _do39=drawOverlay;drawOverlay=function(){_do39();const cv=$('#ov'),w=$('#chartWrap');if(!cv||!w||!CS)return;const r=devicePixelRatio||1,g=cv.getContext('2d');g.save();g.setTransform(r,0,0,r,0,0);try{posDraw(g,w.clientWidth-36)}catch(_){}g.restore()};
// ---------- 6) оповещения на горизонтальных уровнях ----------
const ALP={};
function alrTick(){if(UI.alr===false)return;Object.keys(DRAW||{}).forEach(sym=>{const p=PX[sym];if(p==null)return;(DRAW[sym]||[]).forEach(d=>{if(d.k!=='h')return;const k=sym+'|'+d.p,pv=ALP[k];ALP[k]=p;
 if(pv!=null&&((pv<d.p&&p>=d.p)||(pv>d.p&&p<=d.p))){const m=`${sym} дошла до уровня ${fp(d.p)}`;toast(m);try{navigator.vibrate&&navigator.vibrate([200,80,200])}catch(_){}
  try{if(window.Notification&&Notification.permission==='granted')new Notification('Alex Trade',{body:m,icon:'itk.ico'})}catch(_){}}})})}
setInterval(alrTick,2000);
// ---------- 7) повтор истории ----------
let REP=null;
function rpApply(){if(!REP||!CS)return;C=REP.full.slice(0,REP.i);C.key=REP.full.key;CS.setData(sdata(C));VS.setData(volData(C));try{paintStrat()}catch(_){}try{paintInd()}catch(_){}try{drawOverlay()}catch(_){}try{legend()}catch(_){}try{cmpPaint()}catch(_){}
 const b=$('#rp39');if(b)b.outerHTML=rpBar();else{const w=$('#chartWrap');if(w)w.insertAdjacentHTML('beforeend',rpBar())}}
function rpBar(){const R=REP,c=R.full[R.i-1];return `<div id="rp39"><b>Повтор</b><button data-a="rpb" data-v="-10" title="Назад на 10 свечей">${ic('prev',16)}10</button><button class="on" data-a="rpplay">${ic(R.tm?'pause':'play_arrow',16)}${R.tm?'Пауза':'Пуск'}</button><button data-a="rpb" data-v="1" title="Вперёд на 1 свечу">${ic('next',16)}1</button><button data-a="rpspd">×${R.spd}</button>
 <input type="range" id="rprng" min="60" max="${R.full.length}" value="${R.i}"><span class="mu">${R.i} из ${R.full.length}${c?' · '+new Date(c.time*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):''}</span><button data-a="rpx">${ic('close',16)} Выйти</button></div>`}
function rpStart(){if(!C.length||!CS)return;if(UI.layout>1){toast('Повтор работает на одном графике');return}try{if(klWs){klWs.onmessage=null;klWs.close()}}catch(_){}
 REP={full:C.slice(),i:Math.max(60,C.length-150),spd:1,tm:null};REP.full.key=C.key;rpApply();CH.timeScale().scrollToRealTime();toast('Повтор истории: свечи пойдут по одной — смотрите, где стратегия входит и выходит')}
function rpPlay(){if(!REP)return;if(REP.tm){clearInterval(REP.tm);REP.tm=null;rpApply();return}REP.tm=setInterval(()=>{if(!REP)return;if(REP.i>=REP.full.length){clearInterval(REP.tm);REP.tm=null;rpApply();return}REP.i++;rpApply();CH.timeScale().scrollToRealTime()},1000/REP.spd);rpApply()}
function rpStop(silent){if(!REP)return;if(REP.tm)clearInterval(REP.tm);const full=REP.full;REP=null;const b=$('#rp39');if(b)b.remove();if(!silent){C=full;C.key=full.key;loadChart()}}
// ---------- 8) избранные монеты над графиком ----------
function wlHtml(){return (UI.fav||[]).slice(0,20).map(s=>{const p=PX[s],ch=(PXV[s]||{}).ch;return `<button class="${s===UI.sym?'on':''}" data-a="setpair" data-v="${s}"><b>${s}</b><br>${p!=null?fp(p):'—'} <span class="${ch>=0?'g':'r'}">${ch!=null?(ch>=0?'+':'')+fmt(ch,2)+'%':''}</span></button>`}).join('')}
setInterval(()=>{const e=$('#wl39');if(e&&UI.view==='term')e.innerHTML=wlHtml()},3000);
// ---------- кнопки в терминале ----------
const _tv39=termView;termView=function(){let h=_tv39();
 h=h.replace('<div class="bar">',`<div class="wl39" id="wl39">${wlHtml()}</div><div class="bar">`);
 h=h.replace(/(<button class="chip[^"]*" data-a="layout">)/,`<button class="chip ${UI.cmp?'on':''}" data-a="cmp39">${ic('cmp',18)} Сравнить с BTC</button>$1`);
 h=h.replace('<span style="flex:1"></span><button class="',`<button class="${UI.alr!==false?'on':''}" data-a="alr39" title="Оповещение, когда цена дойдёт до вашего горизонтального уровня">${ic('bell',18)} Оповещения</button><button data-a="rp39" title="Свечи по одной, как вживую">${ic('replay',18)} Повтор</button><span style="flex:1"></span><button class="`);
 return h};
const _rd39=render;render=function(){if(REP&&UI.view!=='term')rpStop(true);_rd39();if(UI.view==='term'&&REP){const w=$('#chartWrap');if(w&&!$('#rp39'))w.insertAdjacentHTML('beforeend',rpBar())}eqPaint()};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='cmp39'){e.stopPropagation();UI.cmp=!UI.cmp;saveUI();el.classList.toggle('on',!!UI.cmp);if(UI.cmp)cmpLoad();else cmpOff()}
 else if(a==='alr39'){e.stopPropagation();UI.alr=UI.alr===false;saveUI();el.classList.toggle('on',UI.alr!==false);if(UI.alr!==false){try{Notification.requestPermission()}catch(_){}toast('Оповещения включены: нарисуйте горизонтальный уровень — когда цена до него дойдёт, придёт сообщение')}else toast('Оповещения выключены')}
 else if(a==='rp39'){e.stopPropagation();REP?rpStop():rpStart()}
 else if(a==='rpplay'){e.stopPropagation();rpPlay()}
 else if(a==='rpb'){e.stopPropagation();if(REP){REP.i=Math.max(60,Math.min(REP.full.length,REP.i+(+v)));rpApply()}}
 else if(a==='rpspd'){e.stopPropagation();if(REP){REP.spd=REP.spd===1?3:REP.spd===3?10:1;if(REP.tm){clearInterval(REP.tm);REP.tm=null;rpPlay()}else rpApply()}}
 else if(a==='rpx'){e.stopPropagation();rpStop()}},true);
document.addEventListener('input',e=>{if(e.target&&e.target.id==='rprng'&&REP){REP.i=+e.target.value;if(REP.tm){clearInterval(REP.tm);REP.tm=null}C=REP.full.slice(0,REP.i);CS.setData(sdata(C));VS.setData(volData(C));try{cmpPaint()}catch(_){}}});
document.addEventListener('change',e=>{if(e.target&&e.target.id==='rprng'&&REP)rpApply()});
setInterval(()=>{if(UI.view==='term'&&posList().length)try{drawOverlay()}catch(_){}},3000);
// ---------- 2) отчёт проверки: кривая денег ----------
const _btr39=btRun;btRun=function(c,s,from,spot,fee,sg){if(BTCAP)return _btr39(c,s,from,spot,fee,sg);BTCAP=[];let r;try{r=_btr39(c,s,from,spot,fee,sg);r.cap39=BTCAP}finally{BTCAP=null}return r};
function eqStats(T){T=T.slice().sort((a,b)=>a.t1-b.t1);let s=0,pk=0,dd=0,w=0,l=0,ws=0,ls=0,st=0,mst=0,dur=0;const pts=[];
 T.forEach(x=>{s+=x.p;pk=Math.max(pk,s);dd=Math.max(dd,pk-s);pts.push([x.t1,s]);dur+=x.t1-x.t0;if(x.p>0){w++;ws+=x.p;st=0}else{l++;ls+=x.p;st++;mst=Math.max(mst,st)}});
 return {n:T.length,sum:s,dd,w,l,ws,ls,pf:ls<0?ws/-ls:null,best:T.length?Math.max(...T.map(x=>x.p)):0,worst:T.length?Math.min(...T.map(x=>x.p)):0,avgW:w?ws/w:0,avgL:l?ls/l:0,dur:T.length?dur/T.length:0,mst,pts}}
const dur39=s=>s<3600?Math.round(s/60)+' мин':s<86400?fmt(s/3600,1)+' ч':fmt(s/86400,1)+' дн.';
const _bres39=btRes;btRes=function(){const h=_bres39(),B=BT7;if(!B||!B.A||!B.cap39||!B.cap39.length||B.busy)return h;const S=eqStats(B.cap39);
 const card=`<div class="card" style="margin-top:8px"><h3 style="margin:0 0 6px">${ic('chart',18)} Как рос депозит <span class="mu sm" style="font-weight:400">ставка $100 на сделку, без плеча${B.honest?', только проверка вслепую':''}</span></h3>
 <div id="eq39" style="height:190px;margin:4px -4px 8px"></div>
 <div class="sgrid"><div><small>Итог</small><b class="${S.sum>=0?'g':'r'}" style="font-size:17px">${USD(S.sum)}</b></div><div><small>Самая большая просадка</small><b class="r">${USD(-S.dd)}</b></div>
 <div><small>Прибыль больше убытков в</small><b class="${S.pf!=null&&S.pf>=1?'g':'r'}">${S.pf==null?'нет убытков':fmt(S.pf,2)+' раза'}</b></div><div><small>Сделок в плюс</small>${S.w} из ${S.n} · ${fmt(S.w/S.n*100,0)}%</div>
 <div><small>Средняя прибыльная</small><span class="g">${USD(S.avgW)}</span></div><div><small>Средняя убыточная</small><span class="r">${USD(S.avgL)}</span></div>
 <div><small>Лучшая сделка</small><span class="g">${USD(S.best)}</span></div><div><small>Худшая сделка</small><span class="r">${USD(S.worst)}</span></div>
 <div><small>Сделка длится в среднем</small>${dur39(S.dur)}</div><div><small>Убытков подряд (макс.)</small>${S.mst}</div></div>
 <p class="hint" style="margin:6px 0 0">${S.pf!=null&&S.pf<1?'Убытки больше прибыли — система в минусе.':S.pf!=null&&S.pf<1.3?'Запас маленький: прибыль лишь немного больше убытков.':'Прибыль заметно больше убытков — хороший знак.'} Просадка — сколько депозит терял от своего максимума до дна.</p></div>`;
 const a=h.indexOf('Ставка $100 на сделку, без плеча'),e=a>=0?h.indexOf('</b></div></div>',a):-1;if(e>=0){const z=e+16;return h.slice(0,z)+card+h.slice(z)}
 const k=h.indexOf('<div id="coinres">');return k>=0?h.slice(0,k)+card+h.slice(k):h+card};
let EQCH=null;
function eqPaint(){const el=$('#eq39'),B=BT7;if(!el||!B||!B.cap39||el.dataset.done===String(B.cap39.length+'|'+B.t1))return;el.dataset.done=String(B.cap39.length+'|'+B.t1);el.innerHTML='';try{EQCH&&EQCH.remove()}catch(_){}
 const S=eqStats(B.cap39),ch=LightweightCharts.createChart(el,Object.assign(chartOpts(),{handleScroll:false,handleScale:false,rightPriceScale:{borderColor:'#363a45'},timeScale:{borderColor:'#363a45',timeVisible:false}}));EQCH=ch;
 const se=ch.addBaselineSeries({baseValue:{type:'price',price:0},topLineColor:'#089981',topFillColor1:'rgba(8,153,129,.28)',topFillColor2:'rgba(8,153,129,.04)',bottomLineColor:'#f23645',bottomFillColor1:'rgba(242,54,69,.04)',bottomFillColor2:'rgba(242,54,69,.28)',lineWidth:2,priceFormat:{type:'custom',formatter:v=>USD(v)}});
 const seen=new Set(),d=[];if(S.pts.length)d.push({time:Math.floor(B.cap39.reduce((a,x)=>Math.min(a,x.t0),1e12))-1,value:0});S.pts.forEach(p=>{let t=Math.floor(p[0]);while(seen.has(t))t++;seen.add(t);d.push({time:t,value:+p[1].toFixed(2)})});
 d.sort((a,b)=>a.time-b.time);const u=[];d.forEach(x=>{if(!u.length||x.time>u[u.length-1].time)u.push(x)});se.setData(u);ch.timeScale().fitContent()}
const _bsh39=btShow;btShow=function(){_bsh39();setTimeout(eqPaint,0)};
// ---------- чистый график: у сигналов только стрелки, слово — в подсказке при наведении ----------
let MK39=new Map();
const _ps39=paintStrat;paintStrat=function(){if(CS&&!CS._p39){const o=CS.setMarkers.bind(CS);CS._p39=1;CS.setMarkers=m=>{MK39=new Map();(m||[]).forEach(x=>{if(x.text){const t=String(x.text).trim();MK39.set(x.time,MK39.has(x.time)?MK39.get(x.time)+', '+t:t)}});o((m||[]).map(x=>x.text?Object.assign({},x,{text:''}):x))}}_ps39()};
// когда показана зона выбранного плана — подписи вход/стоп/тейки справа не повторяем (цены на шкале остаются)
document.head.insertAdjacentHTML('beforeend','<style>#lbls .ol.nl39{visibility:hidden!important;pointer-events:none}</style>');
const _rl39=renderLabels;renderLabels=function(){_rl39();if(!UI.plans||UI.planSel==null)return;document.querySelectorAll('#lbls .ol').forEach(el=>{const x=LINES[+el.dataset.i];if(x&&(x.k==='plsl'||/^pltp/.test(x.k)||x.k==='plan'||x.k==='plan'+UI.planSel))el.classList.add('nl39')})};
render();
