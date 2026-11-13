// ИТК Трейдинг — кнопка «Фигуры и паттерны»: показ на графике, список с объяснениями, автоторговля по паттернам
"use strict";
let PATL=[],PATD=null;
const _ps5=paintStrat;paintStrat=function(){_ps5();paintPat()};
function paintPat(){if(!CH||!CS||!C.length)return;PATL.forEach(s=>{try{CH.removeSeries(s)}catch(e){}});PATL=[];PATD=null;const box=$('#patbox');
 if(!UI.pat){if(box)box.innerHTML='';return}
 const done=C.slice(0,-1);PATD=PAT.detect(done);const from=Math.max(0,done.length-250);
 // фигуры — линиями на графике
 PATD.figs.filter(f=>Math.max(f.brk,(f.pts[f.pts.length-1]||[0])[0],(f.lines[0]||[[0]])[0][0])>=from).slice(-8).forEach(f=>{const col=f.dir>0?'#089981':f.dir<0?'#f23645':'#f0b90b';
  const add=(pts,style,w)=>{const u=[],seen=new Set();pts.slice().sort((a,b)=>a[0]-b[0]).forEach(p=>{const i=Math.max(0,Math.min(done.length-1,Math.round(p[0])));const t=done[i].time;if(!seen.has(t)){seen.add(t);u.push({time:t,value:p[1]})}});if(u.length<2)return;const s=CH.addLineSeries({color:col,lineWidth:w,lineStyle:style,priceLineVisible:false,lastValueVisible:false,crosshairMarkerVisible:false});s.setData(u);PATL.push(s)};
  if(f.pts.length)add(f.pts,0,2);f.lines.forEach(l=>add(l,2,1));if(f.target&&f.brk>=0){const i=f.brk;add([[i,f.target],[Math.min(done.length-1,i+15),f.target]],1,1)}});
 // свечные паттерны и пробои фигур — значками
 let M=[];try{M=CS.markers().slice()}catch(e){}
 PATD.cands.filter(x=>x.i>=from&&x.str>=(UI.patMin||1)).forEach(x=>M.push({time:done[x.i].time,position:x.dir>0?'belowBar':x.dir<0?'aboveBar':'inBar',color:x.dir>0?'#4dd0a8':x.dir<0?'#ff8a80':'#f0b90b',shape:x.dir>0?'circle':x.dir<0?'circle':'square',text:x.name}));
 PATD.figs.filter(f=>f.brk>=from).forEach(f=>M.push({time:done[f.brk].time,position:f.dir>0?'belowBar':'aboveBar',color:f.dir>0?'#089981':'#f23645',shape:f.dir>0?'arrowUp':'arrowDown',text:'пробой: '+f.name}));
 M.sort((a,b)=>a.time-b.time);CS.setMarkers(M);if(box)box.innerHTML=patHtml(done)}
function patHtml(done){const d=PATD;if(!d)return '';const tf=(TFS.find(t=>t[0]===UI.tf)||[0,UI.tf])[1],n=done.length,ago=i=>{const a=n-1-i;return a===0?'последняя свеча':a+' св. назад'};
 const lastI=f=>Math.max(f.brk,...f.pts.map(p=>p[0]),...f.lines.map(l=>l[0][0])),figs=d.figs.filter(f=>f.brk>=0?f.brk>=n-150:lastI(f)>=n-60).sort((a,b)=>lastI(b)-lastI(a)).slice(0,6),cs=d.cands.filter(x=>x.i>=n-40).slice(-8).reverse();
 const tag=dir=>dir>0?'<span class="tag long">рост</span>':dir<0?'<span class="tag short">падение</span>':'<span class="tag flat">ждём пробоя</span>';
 return `<div class="card"><div class="row" style="margin-top:0"><h3 style="margin:0;flex:1">Фигуры и паттерны · ${UI.sym} · ${tf}</h3><button class="btn2 sm" data-a="patauto">${ic('bot',16)} Автоторговля</button></div>
 <p class="grp">ФИГУРЫ</p>${figs.length?figs.map(f=>`<div class="pt1"><div class="row" style="margin:0"><b style="flex:1">${f.name}</b>${tag(f.dir)}</div><div class="sm mu">${f.brk>=0?'Пробой: '+ago(f.brk)+' по '+fp(done[f.brk].close)+(f.target?' · цель '+fp(f.target):''):'Фигура формируется — пробоя ещё не было'}</div><div class="sm">${f.about}</div></div>`).join(''):'<p class="mu sm">На видимой истории фигур не найдено.</p>'}
 <p class="grp">СВЕЧНЫЕ ПАТТЕРНЫ ЗА 40 СВЕЧЕЙ</p>${cs.length?cs.map(x=>`<div class="pt1"><div class="row" style="margin:0"><b style="flex:1">${x.name} <span class="mu sm">${'●'.repeat(x.str)}</span></b>${tag(x.dir)}</div><div class="sm mu">${ago(x.i)} · ${fp(done[x.i].close)}</div><div class="sm">${x.about}</div></div>`).join(''):'<p class="mu sm">Свежих свечных паттернов нет.</p>'}
 <div class="row"><label style="width:auto;flex:1">Показывать свечи силой от</label><div class="seg">${[1,2,3].map(v=>`<button class="${(UI.patMin||1)===v?'on':''}" data-a="patmin" data-v="${v}">${'●'.repeat(v)}</button>`).join('')}</div></div>
 <p class="hint">Точки ● — сила паттерна. Фигуры ищутся по точкам разворота; сигнал даёт только пробой. Паттерны срабатывают не всегда — смотрите итог стратегии «Фигуры и паттерны» на истории.</p></div>`}
// кнопка и место для списка
const _tv5=termView;termView=function(){let h=_tv5();h=h.replace(/(<button class="chip[^"]*" data-a="ind">)/,`<button class="chip ${UI.pat?'on':''}" data-a="pat">${ic('fig',18)} Фигуры и паттерны</button>$1`);h=h.replace('<div id="propbox"></div>','<div id="propbox"></div><div id="patbox"></div>');return h};
ICP.fig="M3 17l5-9 4 6 3-4 6 7H3zm0 2h18v2H3z";
// автоторговля по паттернам: бот по стратегии «Фигуры и паттерны» с заранее заданной суммой
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='pat'){UI.pat=!UI.pat;saveUI();render()}
 else if(a==='patmin'){UI.patMin=+v;saveUI();paintPat()}
 else if(a==='patauto'){openSheet('bot','strat');setTimeout(()=>{const s=$('#bst');if(s){s.value='pat'}const t=$('#btf');if(t)t.value=['1h','4h','1d'].includes(UI.tf)?UI.tf:'4h';const sl=$('#bsl');if(sl&&!sl.value)sl.value=3;const tp=$('#btp');if(tp&&!tp.value)tp.value=6;const h=document.querySelector('.sheet h3');if(h)h.firstChild.textContent='Автоторговля по фигурам и паттернам'},30)}});
// ---------- бот по стратегии на настоящем счёте (работает, пока приложение открыто) ----------
const _bv5=botsView;botsView=function(){if(typeof isReal==='function'&&isReal()&&UI.botTab==='strat'){return _bv3().replace('Демо-боты работают, пока приложение открыто.','<b class="r">Настоящий счёт:</b> бот сам открывает и закрывает сделки на бирже, пока приложение открыто. Стопы и тейки ставит биржа — они работают всегда.')}return _bv5()};
const _cs5=createStratBot;createStratBot=function(o,bk){if(!(typeof isReal==='function'&&isReal()))return _cs5(o,bk);const A=ACC();o.mkt=A.mkt;if(!(+o.amt>=5))return 'Минимальная сумма — 5 USDT';
 const sn=(ITS.list.find(s=>s.id===o.strat)||{}).name;if(!confirm(`НАСТОЯЩИЙ СЧЁТ «${A.name}»\n\nБот «${sn}» по ${o.sym} будет САМ открывать и закрывать сделки на бирже на ${o.amt} USDT${A.mkt==='fut'?' с плечом '+(o.lev||1)+'×':''} при каждом сигнале.\nСтоп ${o.sl||'нет'}%, тейк ${o.tp||'нет'}%.\n\nЗапустить?`))return 'Отменено';
 return _cs5(o,bk)};
// подменяем исполнение сигнала для настоящих счетов
const _ct5=createTrade;createTrade=function(o,bk){const acc=ACCS.find(a=>BOOK[a.id]===bk);if(!acc||acc.kind!=='bn'||!o.bot)return _ct5(o,bk);
 (async()=>{const b=bk.bots.find(x=>x.id===o.bot);try{const st=await tapi('tr_state',{id:acc.key});const pos=(st.positions||[]).find(p=>p.sym===o.sym+'USDT'),have=acc.mkt==='spot'?((st.balances.find(x=>x.a===o.sym)||{free:0}).free):0;
   if(pos&&((pos.amt>0&&o.side<0)||(pos.amt<0&&o.side>0))){await tapi('tr_close',{id:acc.key,symbol:o.sym});tlog(b,'Закрыл противоположную позицию')}
   if(pos&&((pos.amt>0&&o.side>0)||(pos.amt<0&&o.side<0))){tlog(b,'Позиция в эту сторону уже открыта — пропуск');return}
   if(acc.mkt==='spot'&&have*(PX[o.sym]||0)>5&&o.side>0){tlog(b,'Монета уже куплена — пропуск');return}
   const r=await tapi('tr_order',{id:acc.key,symbol:o.sym,side:o.side>0?'BUY':'SELL',type:'MARKET',qty:o.qty,lev:o.lev});const ep=(r.order&&r.order.avg)||PX[o.sym],q=(r.order&&r.order.filled)||o.qty;
   tlog(b,'Открыл на бирже '+(o.side>0?'покупку':'продажу')+' '+fmt(q,5)+' по '+fp(ep));event('bot',`Бот ${o.sym}: ${o.side>0?'покупка':'продажа'} на бирже по ${fp(ep)}`);
   if((o.sl&&o.sl.px)||(o.tps&&o.tps.length)){const pct=b.sl,tpp=b.tp;await tapi('tr_sltp',{id:acc.key,symbol:o.sym,side:o.side,qty:q,sl:pct?ep*(1-o.side*pct/100):0,tps:tpp?[{px:ep*(1+o.side*tpp/100),pct:100}]:[]})}
  }catch(e){if(b)tlog(b,'Биржа отклонила: '+e.message);event('bot',`Бот ${o.sym}: биржа отклонила — ${e.message}`)}save();if(UI.view==='bots'&&!SHEET)render();if(typeof pollRS==='function')pollRS(true)})();return {id:'x',status:'open'}};
// выход по сигналу на настоящем счёте: закрываем позицию на бирже
const _cl5=closeTrade;closeTrade=function(id,why,bk){bk=bk||D();const acc=ACCS.find(a=>BOOK[a.id]===bk);if(acc&&acc.kind==='bn')return;return _cl5(id,why,bk)};
const _sb6=stratBots;stratBots=async function(){await _sb6();for(const acc of ACCS.filter(a=>a.kind==='bn')){const bk=BOOK[acc.id];for(const b of bk.bots.filter(x=>x.type==='strat'&&x.status==='run')){try{const s=ITS.list.find(x=>x.id===b.strat);const k=(await klines(b.mkt,b.sym,b.tf,400)).slice(0,-1),p0={};s.params.forEach(q=>p0[q.k]=b.prm[q.k]!=null?b.prm[q.k]:q.v);const sg=s.run(k,p0),l=sg[sg.length-1];if(!l||l.i!==k.length-1||l.t!=='exit')continue;const ct=k[l.i].time;if(ct<=(b.lastX||0))continue;b.lastX=ct;
  await tapi('tr_close',{id:acc.key,symbol:b.sym}).then(()=>{tlog(b,'Сигнал «выход» — позиция закрыта на бирже');event('bot',`Бот ${b.sym}: выход по сигналу`)}).catch(()=>{});save()}catch(e){}}}};
// разрешаем кнопку «Новый бот по стратегии» на настоящем счёте: рынок — как у счёта
const _bns5=botNewSheet;botNewSheet=function(t){let h=_bns5(t);if(t==='strat'&&typeof isReal==='function'&&isReal())h=h.replace(/<select id="bmkt">[\s\S]*?<\/select>/,`<select id="bmkt"><option value="${ACC().mkt}">${ACC().mkt==='fut'?'Фьючерсы':'Спот'} (настоящий счёт)</option></select>`);return h};
render();
