// ИТК Трейдинг — вкладки торговых сессий и «Сила к BTC» (10.11.2026)
"use strict";
ICP.tabadd="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z";
ICP.btc="M16 6l-4 4-2-2-6 6 1.4 1.4L10 10.8l2 2 5.4-5.4L20 10V4h-6z";
// ---------- 1. ВКЛАДКИ ----------
const TK=['mkt','sym','tf','strat','arrows','ind','vp','zones','plans','planSel','ov','ctype','olines','fills','autosc','pat','patMin','layout','multi','rsOn'];
const pickUI=()=>{const o={};TK.forEach(k=>{if(UI[k]!==undefined)o[k]=JSON.parse(JSON.stringify(UI[k]))});return o};
let TABS=LS.get('tabs',null);if(!TABS||!TABS.list||!TABS.list.length){TABS={list:[{id:'t1',name:'',st:pickUI()}],cur:'t1'}}
if(!TABS.list.find(t=>t.id===TABS.cur))TABS.cur=TABS.list[0].id;
const tabSave=()=>{const t=TABS.list.find(x=>x.id===TABS.cur);if(t)t.st=pickUI();LS.set('tabs',TABS)};
const tfName=tf=>(TFS.find(t=>t[0]===tf)||[0,tf])[1];
function tabName(t){if(t.name)return esc(t.name);const s=t.st||{},st=ITS.list.find(x=>x.id===s.strat);return `${s.sym||'—'} ${tfName(s.tf||'')}${st?' · '+esc(st.name.split(':')[0].split(' (')[0]):''}`}
function tabBusy(t){const s=t.st||{};return D().bots.some(b=>b.status==='run'&&b.sym===s.sym&&b.tf===s.tf&&(b.series||b.type==='strat'))}
function tabsBar(){return `<div class="bar tabsbar">${TABS.list.map(t=>`<button class="chip tabc ${t.id===TABS.cur?'on':''}" data-a="tabgo" data-v="${t.id}">${tabBusy(t)?'<i class="dot run"></i>':''}<span>${tabName(t)}</span>${TABS.list.length>1?`<span class="tabx" data-a="tabx" data-v="${t.id}">${ic('close',14)}</span>`:''}</button>`).join('')}<button class="chip" data-a="tabadd" title="Новая вкладка">${ic('tabadd',18)}</button></div>`}
function tabGo(id){if(id===TABS.cur)return;tabSave();const t=TABS.list.find(x=>x.id===id);if(!t)return;Object.assign(UI,JSON.parse(JSON.stringify(t.st||{})));TABS.cur=id;LS.set('tabs',TABS);C=[];RUL=null;SHEET=null;saveUI();render();window.scrollTo(0,0)}
function tabAdd(){tabSave();const id='t'+Date.now().toString(36);TABS.list.push({id,name:'',st:pickUI()});TABS.cur=id;LS.set('tabs',TABS);render();
 const b=document.querySelector('.tabsbar');if(b)b.scrollLeft=b.scrollWidth;toast('Новая вкладка: выберите пару, время свечи и стратегию — вкладка запомнит их')}
function tabClose(id){const t=TABS.list.find(x=>x.id===id);if(!t||TABS.list.length<2)return;if(!confirm(`Закрыть вкладку «${t.name||tabName(t)}»?${tabBusy(t)?'\nСерии и боты этой пары продолжат работать.':''}`))return;
 const i=TABS.list.indexOf(t);TABS.list.splice(i,1);if(TABS.cur===id){TABS.cur=TABS.list[Math.max(0,i-1)].id;Object.assign(UI,JSON.parse(JSON.stringify(TABS.list.find(x=>x.id===TABS.cur).st)));C=[];saveUI()}LS.set('tabs',TABS);render()}
// долгое нажатие — переименовать
let tabLP=null;document.addEventListener('pointerdown',e=>{const el=e.target.closest('[data-a=tabgo]');if(!el)return;tabLP=setTimeout(()=>{tabLP='done';const t=TABS.list.find(x=>x.id===el.dataset.v);const n=prompt('Название вкладки (пусто — автоматически):',t.name||'');if(n!==null){t.name=n.trim().slice(0,30);LS.set('tabs',TABS);render()}},650)},true);
['pointerup','pointercancel','pointermove'].forEach(ev=>document.addEventListener(ev,e=>{if(ev==='pointermove'&&!(e.movementX||e.movementY))return;if(tabLP&&tabLP!=='done')clearTimeout(tabLP);if(ev!=='pointermove')setTimeout(()=>{if(tabLP==='done')tabLP=null},50)},true));
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='tabx'){e.stopPropagation();e.preventDefault();tabClose(v)}
 else if(a==='tabgo'){e.stopPropagation();if(tabLP==='done'){tabLP=null;return}tabGo(v)}
 else if(a==='tabadd'){e.stopPropagation();tabAdd()}},true);
// ---------- 2. СИЛА К BTC ----------
let RSS=null,RSK={},RSD=null,RST=0;
async function rsKl(mkt,sym,tf,n){const k=mkt+sym+tf+n;const c=RSK[k];if(c&&Date.now()-c.t<45e3)return c.d;const d=await klines(mkt,sym,tf,n);RSK[k]={t:Date.now(),d};return d}
function align(a,b){const m=new Map(b.map(x=>[x.time,x]));const A=[],B=[];a.forEach(x=>{const y=m.get(x.time);if(y){A.push(x);B.push(y)}});return [A,B]}
async function rsCalc(){if(UI.sym==='BTC'){RSD={btc:1};return}const key=UI.mkt+UI.sym;
 try{const [c0,b0]=await Promise.all([rsKl(UI.mkt,UI.sym,'15m',700),rsKl(UI.mkt,'BTC','15m',700)]);const [c,b]=align(c0,b0);if(c.length<120){RSD={err:'Мало данных'};return}
  const n=c.length-1,ch=(a,k)=>k<=n?(a[n].close/a[n-k].close-1)*100:null;
  const P=[[4,'1 час'],[16,'4 часа'],[96,'24 часа'],[Math.min(672,n),'7 дней']].map(([k,t])=>({t,c:ch(c,k),b:ch(b,k)}));
  const rc=[],rb=[];for(let i=n-672>0?n-672:1;i<=n;i++){rc.push(c[i].close/c[i-1].close-1);rb.push(b[i].close/b[i-1].close-1)}
  let su=0,sbu=0,sd=0,sbd=0,mc=0,mb=0;rc.forEach((x,i)=>{const y=rb[i];if(y>0){su+=x;sbu+=y}else if(y<0){sd+=x;sbd+=y}mc+=x;mb+=y});mc/=rc.length;mb/=rb.length;
  let cov=0,vb=0,vc=0;rc.forEach((x,i)=>{const y=rb[i];cov+=(x-mc)*(y-mb);vb+=(y-mb)**2;vc+=(x-mc)**2});
  RSD={key,P,up:sbu?su/sbu:0,dn:sbd?sd/sbd:0,beta:vb?cov/vb:0,corr:vb&&vc?cov/Math.sqrt(vb*vc):0,t:Date.now()}}
 catch(e){RSD={err:'Нет связи с биржей'}}}
function rsVerdict(R){const u=R.up,d=R.dn,s=UI.sym;let v;
 if(R.corr<0.5)v=`У ${s} сейчас своё движение — биткоин влияет на неё слабо. Смотрите на график самой монеты.`;
 else if(u>=1.1&&d<=0.9)v=`${s} сильнее биткоина: в росте растёт больше, в падении падает меньше. Хороший кандидат для покупок.`;
 else if(u>=1.1&&d>=1.1)v=`${s} двигается сильнее биткоина в обе стороны: больше растёт и больше падает. Для покупок на росте BTC — подходит, но стоп нужен шире.`;
 else if(u<=0.9&&d>=1.1)v=`${s} слабее биткоина: растёт меньше, а падает больше. Для покупок хуже; для продаж на падении BTC — подходит.`;
 else if(u<=0.9&&d<=0.9)v=`${s} спокойнее биткоина: и растёт, и падает меньше.`;
 else v=`${s} движется примерно как биткоин.`;return v}
const pcB=v=>v==null?'—':`<span class="${v>=0?'g':'r'}">${v>=0?'+':''}${fmt(v,2)}%</span>`;
function rsHtml(){if(!UI.rsOn)return '';const R=RSD;
 if(!R)return `<div class="card"><h3>${ic('btc',18)} Сила к BTC</h3><p class="mu sm">Считаю…</p></div>`;
 if(R.btc)return `<div class="card"><h3>${ic('btc',18)} Сила к BTC</h3><p class="mu sm">Это сам биткоин. Выберите другую монету, чтобы сравнить её с BTC.</p></div>`;
 if(R.err)return `<div class="card"><h3>${ic('btc',18)} Сила к BTC</h3><p class="err">${R.err}</p></div>`;
 const s=UI.sym,word=(x,up)=>x<0?(up?'падает':'растёт'):(up?'растёт':'падает');
 return `<div class="card"><div class="row" style="margin-top:0"><h3 style="margin:0;flex:1">${ic('btc',18)} Сила ${s} к BTC</h3><span class="mu sm">по 15-минутным свечам</span></div>
 <table class="bt7"><tr><th>За</th><th>${s}</th><th>BTC</th><th>Разница</th></tr>${R.P.map(p=>`<tr><td>${p.t}</td><td>${pcB(p.c)}</td><td>${pcB(p.b)}</td><td>${pcB(p.c-p.b)}</td></tr>`).join('')}</table>
 <div class="sgrid" style="margin-top:8px"><div><small>Когда BTC растёт на 1%</small>${s} ${word(R.up,true)} на <b>${fmt(Math.abs(R.up),2)}%</b></div><div><small>Когда BTC падает на 1%</small>${s} ${word(R.dn,false)} на <b>${fmt(Math.abs(R.dn),2)}%</b></div>
 <div><small>Идёт за биткоином</small><b>${fmt(Math.max(0,R.corr)*100,0)}%</b></div><div><small>Размах к BTC в среднем</small><b>${fmt(R.beta,2)}×</b></div></div>
 <div class="wbox" style="margin-top:8px"><b>${rsVerdict(R)}</b></div>
 <p class="hint" style="margin-bottom:0">«Разница» больше нуля — монета сильнее биткоина за этот срок. Расчёт за последние 7 дней. Жёлтая линия внизу графика: растёт — монета обгоняет BTC, падает — отстаёт.</p></div>`}
function rsPaint(){const e=$('#rsbox');if(e)e.innerHTML=rsHtml()}
async function rsLine(){if(!CH||!CS||!C.length)return;try{if(RSS)CH.removeSeries(RSS)}catch(e){}RSS=null;if(!UI.rsOn||UI.sym==='BTC'||UI.layout>1)return;
 const key=UI.mkt+UI.sym+UI.tf;let b;try{b=await rsKl(UI.mkt,'BTC',UI.tf,1000)}catch(e){return}if(key!==UI.mkt+UI.sym+UI.tf||!CH)return;
 const [c,bb]=align(C,b);if(c.length<10)return;const k0=c[0].close/bb[0].close;
 try{if(RSS)CH.removeSeries(RSS)}catch(e){}
 RSS=CH.addLineSeries({priceScaleId:'rs',color:'#f0b90b',lineWidth:2,priceLineVisible:false,lastValueVisible:true,title:'к BTC',crosshairMarkerVisible:false});
 CH.priceScale('rs').applyOptions({scaleMargins:{top:0.78,bottom:0.02},visible:false});
 RSS.setData(c.map((x,i)=>({time:x.time,value:+(x.close/bb[i].close/k0*100).toFixed(3)})))}
const _ps10=paintStrat;paintStrat=function(){_ps10();rsLine()};
async function rsTick(force){if(!UI.rsOn||UI.view!=='term')return;const key=UI.mkt+UI.sym;if(!force&&RSD&&RSD.key===key&&Date.now()-RSD.t<60e3)return;if(!RSD||RSD.key!==key)RSD=null;rsPaint();await rsCalc();rsPaint()}
setInterval(()=>rsTick(false),20e3);
document.addEventListener('click',e=>{const el=e.target.closest('[data-a=rson]');if(!el)return;e.stopPropagation();UI.rsOn=!UI.rsOn;saveUI();render();if(UI.rsOn)setTimeout(()=>{const b=$('#rsbox');if(b)b.scrollIntoView({behavior:'smooth',block:'center'})},300)},true);
// «к BTC» в списке пар: на сколько монета за 24 часа сильнее или слабее биткоина
const _pp10=paintPairs;paintPairs=async function(){await _pp10();const bt=(PXV.BTC||{}).ch;if(bt==null)return;document.querySelectorAll('#plist tr').forEach(tr=>{const sp=tr.querySelector('[data-a=setpair]');if(!sp)return;const s=sp.dataset.v,td=tr.children[2];if(!td||s==='BTC'||UI.pCol!=='ch')return;const ch=(PXV[s]||{}).ch;if(ch==null)return;const d=ch-bt;td.insertAdjacentHTML('beforeend',`<br><small class="${d>=0?'g':'r'}">к BTC ${d>=0?'+':''}${fmt(d,1)}%</small>`)})};
// разметка
const _tv10=termView;termView=function(){let h=_tv10();h=h.replace('<div class="bar"><div class="seg">',tabsBar()+'<div class="bar"><div class="seg">');
 h=h.replace(/(<button class="chip[^"]*" data-a="seropen">)/,`<button class="chip ${UI.rsOn?'on':''}" data-a="rson">${ic('btc',18)} Сила к BTC</button>$1`);
 h=h.replace('<div id="serbox"></div>','<div id="rsbox"></div><div id="serbox"></div>');return h};
const _r10=render;render=function(){if(UI.view==='term')tabSave();_r10();if(UI.view==='term'){rsPaint();rsTick(false)}};
render();
