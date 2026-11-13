// ИТК Трейдинг — «Серия сделок»: программа сама входит по сигналам выбранной стратегии
// (отскок от уровней, фигуры и паттерны и любые другие), делает ровно N сделок подряд
// со стопом и тейком 1 к 3 и показывает итог: сколько в плюс, сколько в минус, общий результат.
// Одна сделка за раз: следующая открывается только после закрытия предыдущей по стопу или тейку.
"use strict";
ICP.repeat="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z";
const SRN=[10,20,30,50,75,100],SRA=[10,25,50,100,250,500],SRTF=[['1m','1 мин'],['5m','5 мин'],['15m','15 мин'],['30m','30 мин'],['1h','1 час'],['4h','4 часа'],['1d','1 день']];
let SRPRE=null;
const accOf=bk=>ACCS.find(a=>BOOK[a.id]===bk);
const isBn=bk=>{const a=accOf(bk);return !!(a&&a.kind==='bn')};
function botById(id){for(const a of ACCS){const b=BOOK[a.id].bots.find(x=>x.id===id);if(b)return [b,BOOK[a.id],a]}return [null,null,null]}
// счёт открытых сделок серии
const _ct9=createTrade;createTrade=function(o,bk){const r=_ct9(o,bk);if(o&&o.bot&&typeof r!=='string'){const [b]=botById(o.bot);if(b&&b.series){b.series.opened++;b.series.lastOpen=Date.now();save()}}return r};
function serOpen(b,bk,acc){if(acc&&acc.kind==='bn'){if(typeof RS==='undefined'||!RS||ACC().id!==acc.id)return Date.now()-(b.series.lastOpen||0)<15*60e3;
  const sym=b.sym+'USDT';if(RS.kind==='fut')return (RS.positions||[]).some(p=>p.sym===sym)||(RS.orders||[]).some(o=>o.sym===sym&&!o.reduce&&!o.close);
  const bal=(RS.balances||[]).find(x=>x.a===b.sym);return !!(bal&&(bal.free+bal.locked)*(PX[b.sym]||0)>5)}
 return bk.trades.some(t=>t.bot===b.id)}
// такт ботов: серия ждёт, пока закрыта предыдущая сделка, и останавливается после N сделок
const _sb9=stratBots;stratBots=async function(){const held=[];
 for(const a of ACCS){const bk=BOOK[a.id];for(const b of bk.bots)if(b.series&&b.status==='run'&&(b.series.opened>=b.series.n||serOpen(b,bk,a))){b.status='hold';held.push(b)}}
 try{await _sb9()}finally{held.forEach(b=>{if(b.status==='hold')b.status='run'})}
 for(const a of ACCS){const bk=BOOK[a.id];for(const b of bk.bots)if(b.series&&b.status==='run'&&b.series.opened>=b.series.n&&!serOpen(b,bk,a)){
  // на настоящем счёте ждём пару минут, чтобы биржа успела записать результат последней сделки
  if(a.kind==='bn'&&Date.now()-(b.series.lastOpen||0)<60e3)continue;
  b.status='stop';b.tstop=Date.now();b.series.done=Date.now();tlog(b,'Серия завершена');
  const st=serStats(b,bk,a);event('bot',`Серия ${b.sym} завершена: ${st.n} сделок, в плюс ${st.win}, в минус ${st.loss}, итог ${sgn(st.sum)} $`);save()}}
 serPaint()};
// итоги серии
function serStats(b,bk,acc){if(acc&&acc.kind==='bn'){const R=b.series.real||{closes:[],fee:0};const cl=R.closes||[];const win=cl.filter(x=>x.pnl>0).length;const sum=cl.reduce((s,x)=>s+x.pnl,0)+(R.fee||0);
  return {real:1,n:cl.length,win,loss:cl.length-win,sum,tp:null,sl:null,aw:win?cl.filter(x=>x.pnl>0).reduce((s,x)=>s+x.pnl,0)/win:0,al:cl.length-win?cl.filter(x=>x.pnl<=0).reduce((s,x)=>s+x.pnl,0)/(cl.length-win):0,fee:R.fee||0}}
 const hs=bk.hist.filter(h=>h.bot===b.id&&h.qty>0),win=hs.filter(h=>h.pnl>0).length,sum=hs.reduce((s,h)=>s+h.pnl,0);
 return {n:hs.length,win,loss:hs.length-win,sum,tp:hs.filter(h=>/тейк/.test(h.why||'')).length,sl:hs.filter(h=>/стоп|безубыт/.test(h.why||'')).length,aw:win?hs.filter(h=>h.pnl>0).reduce((s,h)=>s+h.pnl,0)/win:0,al:hs.length-win?hs.filter(h=>h.pnl<=0).reduce((s,h)=>s+h.pnl,0)/(hs.length-win):0}}
async function serReal(){for(const a of ACCS.filter(x=>x.kind==='bn')){for(const b of BOOK[a.id].bots.filter(x=>x.series&&!x.series.hidden&&(x.status!=='stop'||Date.now()-(x.series.done||0)<10*60e3))){
  if(b.mkt!=='fut')continue;try{const j=await tapi('tr_series',{id:a.key,symbol:b.sym,from:b.series.t0});b.series.real={closes:j.closes||[],fee:j.fee||0}}catch(e){}}}save();serPaint()}
setInterval(serReal,60e3);
// карточка серий в терминале
function serHtml(){const acc=ACC(),bk=D(),L=bk.bots.filter(b=>b.series&&!b.series.hidden).slice(0,6);if(!L.length)return '';
 return `<div class="card"><div class="row" style="margin-top:0"><h3 style="margin:0;flex:1">${ic('repeat',18)} Серии сделок</h3><button class="btn2 sm" data-a="seropen">Новая серия</button></div>${L.map(b=>{const s=ITS.list.find(x=>x.id===b.strat),st=serStats(b,bk,acc),run=b.status!=='stop',tf=(SRTF.find(x=>x[0]===b.tf)||[0,b.tf])[1],N=b.series.n,pc=Math.min(100,Math.round(st.n/N*100)),wr=st.n?st.win/st.n*100:0,ret=b.amt?st.sum/b.amt*100:0;
  return `<div class="plan ${st.sum>=0?'long':'short'}" style="cursor:default"><div class="row" style="margin:0"><b style="flex:1">${s?s.name:b.strat} · ${b.sym} · ${tf}</b><span class="tag ${run?'long':'flat'}">${run?(b.series.opened>st.n?'в сделке':'ждёт сигнал'):'завершена'}</span></div>
  <div class="sm mu">Сделок ${st.n} из ${N}${b.series.opened>st.n?' (+1 открыта)':''} · ставка ${fmt(b.amt,0)} $${b.lev>1?' × '+b.lev:''} · стоп −${fmt(b.sl,b.sl<1?2:1)}%, тейк +${fmt(b.tp,b.tp<1?2:1)}%</div>
  <div style="height:6px;border-radius:3px;background:var(--ln);margin:6px 0"><div style="height:6px;border-radius:3px;width:${pc}%;background:var(--ac)"></div></div>
  <div class="sgrid"><div><small>В плюс</small><span class="g">${st.win} (${fmt(wr,0)}%)</span></div><div><small>В минус</small><span class="r">${st.loss}</span></div><div><small>Итог</small><span class="${st.sum>=0?'g':'r'}">${sgn(st.sum)} $ (${ret>=0?'+':''}${fmt(ret,1)}%)</span></div><div><small>Средняя: плюс / минус</small><span class="g">${sgn(st.aw)}</span> / <span class="r">${sgn(st.al)}</span></div>${st.real?`<div><small>Комиссия биржи</small>${sgn(st.fee)} $</div>`:`<div><small>Тейк / стоп сработали</small>${st.tp} / ${st.sl}</div>`}</div>
  <div class="row" style="margin-bottom:0">${run?`<button class="btn2 sm" style="flex:1" data-a="serstop" data-v="${b.id}">Остановить</button>`:`<button class="btn2 sm" style="flex:1" data-a="serrep" data-v="${b.id}">Повторить</button><button class="btn2 sm" style="flex:1" data-a="serhide" data-v="${b.id}">Убрать</button>`}</div></div>`}).join('')}
 <p class="hint" style="margin-bottom:0">${typeof isReal==='function'&&isReal()?'Настоящий счёт: стоп и тейк стоят на бирже, новые сделки серия открывает, пока приложение открыто.':'Демо-счёт: серия работает, пока приложение открыто.'}</p></div>`}
function serPaint(){const e=$('#serbox');if(!e)return;const a=document.activeElement;if(a&&e.contains(a))return;e.innerHTML=serHtml()}
setInterval(()=>{if(UI.view==='term')serPaint()},4000);
// окно запуска
function srDefSl(sid,tf){const r=UI.rr7||{};if(r.on&&r.key===UI.mkt+UI.sym+tf+sid)return r.sl;if(SRPRE&&SRPRE.sl)return SRPRE.sl;
 if(tf===UI.tf&&C&&C.length>30){const a=ITS.ind.atr(C,14),v=[];for(let i=C.length-100;i<C.length;i++)if(i>0&&a[i])v.push(a[i]/C[i].close*100);v.sort((x,y)=>x-y);const m=v[v.length>>1]||0;if(m){const s=m*1.5;return s<0.2?Math.round(s*100)/100:s<1?Math.round(s*20)/20:Math.round(s*10)/10}}
 return ''}
function serSheet(){const P=SRPRE||{},sid=P.strat||UI.serS||UI.strat,tf=P.tf||UI.serTf||UI.tf,n=P.n||UI.serN||20,amt=P.amt||UI.serA||25,fut=UI.mkt==='fut',sl=P.sl||srDefSl(sid,tf),real=typeof isReal==='function'&&isReal();
 return sheetWrap(`${ic('repeat',20)}&nbsp;Серия сделок · ${UI.sym}`,`<p class="hint" style="margin-top:0">Программа сама входит по сигналам стратегии и делает сделки подряд — по одной: следующая открывается после того, как предыдущая закрылась по стопу или тейку. В конце — итог серии.</p>
 ${row('Стратегия',`<div class="in"><select id="srs">${ITS.list.map(s=>`<option value="${s.id}" ${s.id===sid?'selected':''}>${s.name}</option>`).join('')}</select></div>`)}
 ${row('Свечи',`<div class="in"><select id="srtf">${SRTF.map(t=>`<option value="${t[0]}" ${t[0]===tf?'selected':''}>${t[1]}</option>`).join('')}</select></div>`)}
 ${row('Сделок',`<div class="in"><input type="number" id="srn" min="1" max="500" step="1" value="${n}"><span>шт.</span></div>`)}<div class="pct">${SRN.map(v=>`<button class="${+n===v?'on':''}" data-a="srnset" data-v="${v}">${v}</button>`).join('')}</div>
 ${row('Ставка',`<div class="in"><input type="number" id="sra" min="10" step="any" value="${amt}"><span>USDT</span></div>`)}<div class="pct">${SRA.map(v=>`<button class="${+amt===v?'on':''}" data-a="sraset" data-v="${v}">$${v}</button>`).join('')}</div>
 ${fut?row('Плечо',`<div class="in"><select id="srlev">${[1,2,3,5,10,20].map(x=>`<option ${x==(P.lev||UI.serL||F.lev||5)?'selected':''} value="${x}">${x}×</option>`).join('')}</select></div>`):''}
 ${row('Стоп',`<div class="in"><input type="number" id="srsl" step="any" min="0.01" value="${sl}"><span>%</span></div>`)}
 <div class="risk" id="srrisk"></div>
 ${real?'<p class="err">Настоящий счёт: сделки уйдут на биржу. Стоп и тейк ставятся на бирже сразу после входа.</p>':''}
 <div class="err" id="srerr"></div><button class="go b" data-a="srgo">Начать серию</button>`)}
function srRisk(){const e=$('#srrisk');if(!e)return;const n=+$('#srn').value||0,a=+$('#sra').value||0,lev=$('#srlev')?+$('#srlev').value:1,sl=+$('#srsl').value||0,loss=a*lev*sl/100,prof=loss*3;
 e.innerHTML=`<div><small>Тейк (1 к 3)</small><span class="g">+${fmt(sl*3,sl<1?2:1)}%</span></div><div><small>Одна сделка: минус / плюс</small><span class="r">−${fmt(loss,2)}</span> / <span class="g">+${fmt(prof,2)} $</span></div><div><small>Худший случай серии</small><span class="r">−${fmt(loss*n,2)} $</span></div><div><small>Чтобы быть в плюсе</small>тейк хотя бы в ${Math.ceil(n/4+0.0001)} из ${n}</div>`}
const _r9=render;render=function(){if(SHEET&&SHEET[0]==='ser'){SHEET=null;_r9();SHEET=['ser'];$('#app').insertAdjacentHTML('beforeend',serSheet());srRisk();return}_r9()};
function serStart(){const sid=$('#srs').value,tf=$('#srtf').value,n=Math.round(+$('#srn').value),amt=+$('#sra').value,lev=$('#srlev')?+$('#srlev').value:1,sl=+$('#srsl').value,er=$('#srerr');
 if(!(n>=1&&n<=500))return er.textContent='Число сделок — от 1 до 500';if(!(amt>=10))return er.textContent='Минимальная ставка — 10 USDT';if(!(sl>0&&sl<50))return er.textContent='Укажите стоп в процентах, например 0,5';
 const real=typeof isReal==='function'&&isReal();if(real&&!confirm(`НАСТОЯЩИЙ СЧЁТ\n\nСерия из ${n} сделок по ${amt} USDT${lev>1?' с плечом '+lev+'×':''}.\nСтоп −${sl}%, тейк +${+(sl*3).toFixed(3)}%.\nХудший случай: −${fmt(amt*lev*sl/100*n,2)} USDT.\n\nНачать?`))return;
 const mkt=real?ACC().mkt:UI.mkt;const b=createStratBot({sym:UI.sym,mkt,tf,strat:sid,prm:{},amt,lev,sl,tp:+(sl*3).toFixed(4)});if(typeof b==='string')return er.textContent=b;
 b.series={n,t0:Date.now(),opened:0};b.name='Серия';UI.serS=sid;UI.serTf=tf;UI.serN=n;UI.serA=amt;UI.serL=lev;SRPRE=null;saveUI();save();SHEET=null;render();
 toast(`Серия запущена: ${n} сделок. Ждём первый сигнал «${(ITS.list.find(s=>s.id===sid)||{}).name}» на свечах ${(SRTF.find(t=>t[0]===tf)||[0,tf])[1]}`);setTimeout(()=>stratBots(),500)}
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='seropen'){e.stopPropagation();SRPRE=null;SHEET=['ser'];render();history.pushState({sh:1},'')}
 else if(a==='serfrombt'&&BT7&&BT7.best){e.stopPropagation();SRPRE={strat:BT7.s.id,tf:BT7.tf,sl:BT7.best.sl,amt:Math.max(10,UI.bmA||25),lev:UI.bmL||1,n:UI.bmN||20};SHEET=['ser'];render()}
 else if(a==='srnset'){$('#srn').value=v;$$('[data-a=srnset]').forEach(x=>x.classList.toggle('on',x.dataset.v===v));srRisk()}
 else if(a==='sraset'){$('#sra').value=v;$$('[data-a=sraset]').forEach(x=>x.classList.toggle('on',x.dataset.v===v));srRisk()}
 else if(a==='srgo')serStart()
 else if(a==='serstop'){const [b,bk]=botById(v);if(b&&confirm('Остановить серию? Открытая сделка будет закрыта.')){stopBot(b.id,'серия остановлена',bk);b.series.done=Date.now();save();serPaint()}}
 else if(a==='serrep'){const [b]=botById(v);if(b){SRPRE={strat:b.strat,tf:b.tf,n:b.series.n,amt:b.amt,lev:b.lev,sl:b.sl};SHEET=['ser'];render();history.pushState({sh:1},'')}}
 else if(a==='serhide'){const [b]=botById(v);if(b){b.series.hidden=1;save();serPaint()}}},true);
document.addEventListener('input',e=>{if(['srn','sra','srsl'].includes(e.target.id))srRisk()});
document.addEventListener('change',e=>{if(e.target.id==='srlev')srRisk();if(e.target.id==='srs'||e.target.id==='srtf'){const s=$('#srsl');const d=srDefSl($('#srs').value,$('#srtf').value);if(s&&d)s.value=d;srRisk()}});
// кнопки: на панели над графиком, рядом с точками входа, в итогах проверки на истории
const _tv9=termView;termView=function(){let h=_tv9();h=h.replace(/(<button class="chip[^"]*" data-a="bt7open">)/,`<button class="chip" data-a="seropen">${ic('repeat',18)} Серия сделок</button>$1`);h=h.replace('<div id="planbox"></div>','<div id="serbox"></div><div id="planbox"></div>');return h};
const _ob9=ovBar;ovBar=function(){const h=_ob9();return h?h+`<button class="chip ovc" data-a="seropen">${ic('repeat',16)} Серия</button>`:h};
const _br9=btRes;btRes=function(){let h=_br9();if(BT7&&BT7.best&&BT7.best.n&&BT7.best.ret>0&&BT7.K!=null&&BT7.K!==3)h=h.replace('<p class="hint">Проверка на прошлых свечах.','<p class="hint">Серия сделок пока торгует только по правилу 1 к 3 — чтобы запустить её, выберите вверху «1 к 3».</p><p class="hint">Проверка на прошлых свечах.');else if(BT7&&BT7.best&&BT7.best.n&&BT7.best.ret>0)h=h.replace('<p class="hint">Проверка на прошлых свечах.',`<button class="btn2" style="width:100%;margin:8px 0;padding:10px" data-a="serfrombt">${ic('repeat',18)} Запустить серию с этим стопом</button><p class="hint">Проверка на прошлых свечах.`);return h};
const _r9b=render;render=function(){_r9b();if(UI.view==='term'&&!(SHEET&&SHEET[0]==='ser'))serPaint();else if(UI.view==='term')serPaint()};
render();
