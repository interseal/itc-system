// ИТК Трейдинг — демо-серии на сервере (10.11.2026): телефон можно выключить, сервер сам торгует по стратегии.
"use strict";
ICP.cloud="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z";
const TDAPI='https://hk.itcpartner.store/api/tdemo.php'/* №435 · сервер торговли в Гонконге */,TDS=['ema','st','rsi','don','macd','bb','pat','lvl'];
let TD=LS.get('tdc',[]),TDCRON=0,TDNOW=0,TDPING=null,TDBUSY=false;
async function tdapi(a,d){const r=await fetch(TDAPI+'?action='+a+'&t='+encodeURIComponent(TOK),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({t:TOK},d||{}))});let j;try{j=await r.json()}catch(e){throw new Error('сервер ответил '+r.status)}if(!j.ok)throw new Error(j.error||'ошибка');return j}
async function tdLoad(){if(TDBUSY)return;TDBUSY=true;try{const j=await tdapi('td_list',{});const old=new Map(TD.map(s=>[s.id,s]));TD=j.series||[];TDCRON=j.cron||0;TDNOW=j.now||0;LS.set('tdc',TD);
  TD.forEach(s=>{const o=old.get(s.id);if(o&&o.status==='run'&&s.status==='done'){const st=tdStats(s);event('bot',`Серия ${s.sym} на сервере завершена: ${st.n} сделок, в плюс ${st.win}, в минус ${st.loss}, итог ${sgn(st.sum)} $`)}
   if(o&&s.trades.length>o.trades.length){const t=s.trades[s.trades.length-1];event(/тейк/.test(t.why)?'tp':'sl',`${s.sym}: серия на сервере — ${t.why}, ${sgn(t.pnl)} $`)}
   else if(o&&!o.open&&s.open)event('fill',`${s.sym}: серия на сервере открыла ${s.open.side>0?'покупку':'продажу'} по ${fp(s.open.ep)}`)});
  tdPaint()}catch(e){}finally{TDBUSY=false}}
setInterval(()=>{if(document.visibilityState!=='hidden')tdLoad()},20e3);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')tdLoad()});
function tdStats(s){const T=s.trades||[],win=T.filter(t=>t.pnl>0).length,sum=T.reduce((a,t)=>a+t.pnl,0);
 return {n:T.length,win,loss:T.length-win,sum,tp:T.filter(t=>/тейк/.test(t.why)).length,sl:T.filter(t=>/стоп/.test(t.why)).length,aw:win?T.filter(t=>t.pnl>0).reduce((a,t)=>a+t.pnl,0)/win:0,al:T.length-win?T.filter(t=>t.pnl<=0).reduce((a,t)=>a+t.pnl,0)/(T.length-win):0}}
const tdName=s=>{const x=ITS.list.find(q=>q.id===s.strat);return x?x.name:s.strat};
const tdTf=tf=>(SRTF.find(x=>x[0]===tf)||[0,tf])[1];
const tdU=t=>(t.side>0?1:-1)*((PX[t.sym||'']||0)-t.ep);
function tdOpenPnl(s){const p=PX[s.sym]||s.px;if(!s.open||!p)return null;const o=s.open;return (p-o.ep)*o.qty*o.side-(o.ep+p)*o.qty*(s.mkt==='spot'?0.001:0.0005)}
function tdCronTxt(){if(!TDCRON)return 'Будильник сервера ещё не настроен: серия идёт, пока приложение открыто, и догоняет пропущенное при каждом открытии.';const m=Math.round((TDNOW-TDCRON)/60);return m<=3?'Сервер работает сам — телефон можно выключить.':`Будильник сервера молчит ${m} мин — серия догонит пропущенное при открытии приложения.`}
// полоса над сделками и карточки — для серий на сервере
function tdBanner(){const L=TD.filter(s=>s.status==='run');if(!L.length)return '';return L.map(s=>{const st=tdStats(s),cur=Math.min(s.n,st.n+(s.open?1:0)||1),u=tdOpenPnl(s),last=(s.trades||[]).slice(-5).reverse();
 return `<div class="serban" data-a="serscroll"><div class="row" style="margin:0;align-items:center">${ic('cloud',18)}<b style="flex:1;margin-left:6px">Серия на сервере: сделка ${cur} из ${s.n}</b><span class="tag ${s.open?'long':'flat'}">${s.open?'в сделке':'ждёт сигнал'}</span></div>
 <div class="sm">${tdName(s)} · ${s.sym} · ${tdTf(s.tf)} · ставка ${fmt(s.amt,0)} $${s.lev>1?' × '+s.lev:''} · ${typeof tdRule==='function'&&s.sys?tdRule(s):`стоп −${fmt(s.sl,s.sl<1?2:1)}% · тейк +${fmt(s.tp,s.tp<1?2:1)}%`}</div>
 <div class="sm mu">Запущена ${t0Txt(s.t0)} · идёт ${durTxt(Date.now()-s.t0)} · закрыто ${st.n}: в плюс ${st.win}, в минус ${st.loss} · итог <b class="${st.sum>=0?'g':'r'}">${sgn(st.sum)} $</b></div>
 ${s.open?`<div class="sm" style="margin-top:6px"><b class="${s.open.side>0?'g':'r'}">${s.open.side>0?'↑ Покупка':'↓ Продажа'}</b> по ${fp(s.open.ep)} · стоп ${fp(s.open.stop!=null?s.open.stop:s.open.ep*(1-s.open.side*s.sl/100))} · тейк ${s.open.stop!=null?(s.open.tp!=null?fp(s.open.tp):'по сигналу или стопу'):fp(s.open.ep*(1+s.open.side*s.tp/100))}${u!=null?` · сейчас <b class="${u>=0?'g':'r'}">${sgn(u)} $</b>`:''}</div>`:''}
 ${last.length?`<div class="sm mu" style="margin-top:6px">${last.map(t=>`${new Date(t.t1*1000).toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'})} ${t.side>0?'↑':'↓'} ${fp(t.ep)} → ${fp(t.xp)} <b class="${t.pnl>=0?'g':'r'}">${sgn(t.pnl)}</b> ${/тейк/.test(t.why)?'тейк':/стоп/.test(t.why)?'стоп':''}`).join('<br>')}</div>`:''}
 ${s.err?`<p class="err" style="margin:6px 0 0">${esc(s.err)}</p>`:''}<div class="sm mu" style="margin-top:6px">${ic('cloud',14)} ${tdCronTxt()}</div></div>`}).join('')}
function tdCards(){const L=TD.filter(s=>!s.hidden).slice(0,6);if(!L.length)return '';return L.map(s=>{const st=tdStats(s),run=s.status==='run',N=s.n,pc=Math.min(100,Math.round(st.n/N*100)),wr=st.n?st.win/st.n*100:0,ret=s.amt?st.sum/s.amt*100:0;
 return `<div class="plan ${st.sum>=0?'long':'short'}" style="cursor:default"><div class="row" style="margin:0"><b style="flex:1">${ic('cloud',16)} ${tdName(s)} · ${s.sym} · ${tdTf(s.tf)}</b><span class="tag ${run?'long':'flat'}">${run?(s.open?'в сделке':'ждёт сигнал'):'завершена'}</span></div>
 <div class="sm mu">На сервере · сделок ${st.n} из ${N}${s.open?' (+1 открыта)':''} · ставка ${fmt(s.amt,0)} $${s.lev>1?' × '+s.lev:''} · ${typeof tdRule==='function'&&s.sys?tdRule(s):`стоп −${fmt(s.sl,s.sl<1?2:1)}%, тейк +${fmt(s.tp,s.tp<1?2:1)}%`}</div>
 <div style="height:6px;border-radius:3px;background:var(--ln);margin:6px 0"><div style="height:6px;border-radius:3px;width:${pc}%;background:var(--ac)"></div></div>
 <div class="sgrid"><div><small>В плюс</small><span class="g">${st.win} (${fmt(wr,0)}%)</span></div><div><small>В минус</small><span class="r">${st.loss}</span></div><div><small>Итог</small><span class="${st.sum>=0?'g':'r'}">${sgn(st.sum)} $ (${ret>=0?'+':''}${fmt(ret,1)}%)</span></div><div><small>Тейк / стоп сработали</small>${st.tp} / ${st.sl}</div></div>
 <div class="sm mu" style="margin:4px 0 0">Запущена ${t0Txt(s.t0)} · ${run?'идёт '+durTxt(Date.now()-s.t0):'шла '+durTxt(((s.tdone||TDNOW)*1000)-s.t0)}</div>
 <div class="row" style="margin-bottom:0">${run?`<button class="btn2 sm" style="flex:1" data-a="tdstop" data-v="${s.id}">Остановить</button>`:`<button class="btn2 sm" style="flex:1" data-a="tdrep" data-v="${s.id}">Повторить</button><button class="btn2 sm" style="flex:1" data-a="tdhide" data-v="${s.id}">Убрать</button>`}</div></div>`}).join('')}
function tdPaint(){try{myDeals()}catch(e){}try{serPaint()}catch(e){}try{paintOrders()}catch(e){}}
const _md14=myDeals;myDeals=function(){_md14();const e=$('#mydeals');if(!e)return;const h=tdBanner();if(h)e.insertAdjacentHTML('afterbegin',h)};
const _sh14=serHtml;serHtml=function(){const loc=_sh14(),srv=tdCards();if(!srv)return loc;
 if(loc)return loc.replace(/(<\/div>)(<div class="plan )/,`$1${srv}$2`);
 return `<div class="card"><div class="row" style="margin-top:0"><h3 style="margin:0;flex:1">${ic('repeat',18)} Серии сделок</h3><button class="btn2 sm" data-a="histopen">${ic('history',16)} История</button><button class="btn2 sm" data-a="seropen">Новая серия</button></div>${srv}<p class="hint" style="margin-bottom:0">${tdCronTxt()}</p></div>`};
// линии на графике у сделки серии на сервере
const _tl14=tradeLines;tradeLines=function(){const o=_tl14();TD.filter(s=>s.status==='run'&&s.open&&s.sym===UI.sym&&s.mkt===UI.mkt).forEach(s=>{const t=s.open,st=tdStats(s),u=tdOpenPnl(s);
 o.push({k:'srv'+s.id,p:t.ep,c:t.side>0?'#089981':'#f23645',a:`Сервер ${st.n+1}/${s.n} · ${t.side>0?'ЛОНГ':'ШОРТ'}`,b:u!=null?sgn(u)+' $':'',fixed:1});
 if(t.stop!=null){o.push({k:'srvsl'+s.id,p:t.stop,c:'#f23645',a:'Стоп сервера',b:(t.side*(t.stop-t.ep)/t.ep*100>=0?'+':'')+fmt(t.side*(t.stop-t.ep)/t.ep*100,2)+'%',fixed:1});if(t.tp!=null)o.push({k:'srvtp'+s.id,p:t.tp,c:'#089981',a:'Тейк сервера',b:'+'+fmt(Math.abs(t.tp-t.ep)/t.ep*100,2)+'%',fixed:1});return}o.push({k:'srvsl'+s.id,p:t.ep*(1-t.side*s.sl/100),c:'#f23645',a:'Стоп сервера',b:'−'+fmt(s.sl,2)+'%',fixed:1});o.push({k:'srvtp'+s.id,p:t.ep*(1+t.side*s.tp/100),c:'#089981',a:'Тейк сервера',b:'+'+fmt(s.tp,2)+'%',fixed:1})});return o};
// умная история: серии с сервера тоже разбираются
const _sa14=serAll;serAll=function(){const L=_sa14();TD.forEach(s=>{const st=tdStats(s),tr=(s.trades||[]).map(t=>({pnl:t.pnl,t:t.t0*1000,side:t.side,why:t.why})),gw=tr.filter(x=>x.pnl>0).reduce((a,x)=>a+x.pnl,0),gl=-tr.filter(x=>x.pnl<=0).reduce((a,x)=>a+x.pnl,0);
 L.push({b:{id:s.id,strat:s.strat,tf:s.tf,sym:s.sym,amt:s.amt,sl:s.sl,series:{n:s.n,t0:s.t0,done:(s.tdone||0)*1000}},a:{kind:'srv',name:'Сервер'},st,tr,pf:gl?gw/gl:(gw?9:0),ret:s.amt?st.sum/s.amt*100:0,done:s.status!=='run',srv:1})});return L.sort((x,y)=>(y.b.series.t0||0)-(x.b.series.t0||0))};
const _hs14=histSheet;histSheet=function(){let h=_hs14();TD.forEach(s=>{h=h.split(`data-a="serrep" data-v="${s.id}"`).join(`data-a="tdrep" data-v="${s.id}"`)});return h.split('· Сервер ·').join('· на сервере ·')};
// окно серии: переключатель «на сервере»
UI.serSrv=UI.serSrv!==false;
const _ss14=serSheet;serSheet=function(){let h=_ss14();const real=typeof isReal==='function'&&isReal();if(real)return h;
 const sw=`<div class="row"><label>Где работает</label><div class="seg" style="flex:1"><button class="${UI.serSrv?'on':''}" data-a="srvsw" data-v="1">${ic('cloud',16)} На сервере</button><button class="${UI.serSrv?'':'on'}" data-a="srvsw" data-v="0">В телефоне</button></div></div>
 <p class="hint" id="srvinfo" style="margin-top:0">${UI.serSrv?'Телефон можно выключить: сервер сам берёт свечи с Binance, открывает и закрывает сделки. '+(TDPING?TDPING:'Проверяю связь сервера с биржей…'):'Серия идёт, пока приложение открыто.'}</p>`;
 return h.replace('<div class="risk" id="srrisk"></div>',sw+'<div class="risk" id="srrisk"></div>')};
async function tdPing(){try{const j=await tdapi('td_ping',{});const ok=j.res.filter(x=>x.ok);TDCRON=j.cron||TDCRON;TDNOW=j.now||TDNOW;
  TDPING=ok.length?`<b class="g">Сервер видит Binance</b> (${ok.map(x=>x.name.toLowerCase()).join(', ')}). ${tdCronTxt()}`:`<b class="r">Сервер не получает данные с Binance</b> — серию на сервере сейчас запустить нельзя, выберите «В телефоне».`}
 catch(e){TDPING='<span class="r">Не удалось проверить сервер: '+esc(e.message)+'</span>'}const i=$('#srvinfo');if(i&&UI.serSrv)i.innerHTML='Телефон можно выключить: сервер сам берёт свечи с Binance, открывает и закрывает сделки. '+TDPING}
const _sst14=serStart;serStart=async function(){const real=typeof isReal==='function'&&isReal();if(real||!UI.serSrv)return _sst14();
 const sid=$('#srs').value,tf=$('#srtf').value,n=Math.round(+$('#srn').value),amt=+$('#sra').value,lev=$('#srlev')?+$('#srlev').value:1,sl=+$('#srsl').value,er=$('#srerr');
 if(!TDS.includes(sid))return er.textContent='Эта стратегия пока работает только в телефоне — выберите «В телефоне»';
 if(!(n>=1&&n<=500))return er.textContent='Число сделок — от 1 до 500';if(!(amt>=10))return er.textContent='Минимальная ставка — 10 USDT';if(!(sl>0&&sl<50))return er.textContent='Укажите стоп в процентах, например 0,5';
 const btn=$('[data-a=srgo]');btn.disabled=true;btn.textContent='Запускаю на сервере…';
 try{const j=await tdapi('td_start',{sym:UI.sym,mkt:UI.mkt,tf,strat:sid,prm:(typeof PRM!=='undefined'&&PRM[sid])||{},amt,lev,sl,tp:+(sl*3).toFixed(4),n,acc:curAcc});
  TD.unshift(j.series);LS.set('tdc',TD);UI.serS=sid;UI.serTf=tf;UI.serN=n;UI.serA=amt;UI.serL=lev;SRPRE=null;saveUI();SHEET=null;render();toast(`Серия на сервере запущена: ${n} сделок. Телефон можно выключить.`)}
 catch(e){er.textContent=e.message;btn.disabled=false;btn.textContent='Начать серию'}};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='srvsw'){e.stopPropagation();UI.serSrv=v==='1';saveUI();$$('[data-a=srvsw]').forEach(x=>x.classList.toggle('on',x.dataset.v===v));const i=$('#srvinfo');if(i)i.innerHTML=UI.serSrv?'Телефон можно выключить: сервер сам берёт свечи с Binance, открывает и закрывает сделки. '+(TDPING||'Проверяю связь сервера с биржей…'):'Серия идёт, пока приложение открыто.';if(UI.serSrv&&!TDPING)tdPing()}
 else if(a==='seropen'||a==='serfrombt'||a==='serrep'){if(!TDPING)setTimeout(tdPing,100)}
 else if(a==='tdstop'){e.stopPropagation();if(!confirm('Остановить серию на сервере? Открытая сделка будет закрыта по текущей цене.'))return;tdapi('td_stop',{id:v}).then(j=>{TD=TD.map(s=>s.id===v?j.series:s);LS.set('tdc',TD);tdPaint();toast('Серия остановлена')}).catch(er=>toast(er.message))}
 else if(a==='tdhide'){e.stopPropagation();tdapi('td_hide',{id:v}).then(()=>{TD=TD.filter(s=>s.id!==v);LS.set('tdc',TD);tdPaint()}).catch(er=>toast(er.message))}
 else if(a==='tdrep'){e.stopPropagation();const s=TD.find(x=>x.id===v);if(s){UI.serSrv=true;SRPRE={strat:s.strat,tf:s.tf,n:s.n,amt:s.amt,lev:s.lev,sl:s.sl};SHEET=['ser'];render();history.pushState({sh:1},'');if(!TDPING)tdPing()}}},true);
tdLoad();
