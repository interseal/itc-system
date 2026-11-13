// ИТК Трейдинг — умная история серий с подсказками и сведения обо всех монетах Binance (10.11.2026)
"use strict";
ICP.info="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z";
ICP.bulb="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z";
const big=v=>v==null?'—':v>=1e9?fmt(v/1e9,1)+' млрд $':v>=1e6?fmt(v/1e6,1)+' млн $':v>=1e3?fmt(v/1e3,0)+' тыс $':fmt(v,0)+' $';
// ================= 1. УМНАЯ ИСТОРИЯ =================
function serAll(){const out=[];for(const a of ACCS){const bk=BOOK[a.id];bk.bots.filter(b=>b.series).forEach(b=>{const st=serStats(b,bk,a);
  const tr=a.kind==='bn'?((b.series.real||{}).closes||[]).map(x=>({pnl:x.pnl,t:x.t,side:0,why:''})):bk.hist.filter(h=>h.bot===b.id&&h.qty>0).map(h=>({pnl:h.pnl,t:h.t0||h.t,side:h.side,why:h.why||''}));
  const gw=tr.filter(x=>x.pnl>0).reduce((s,x)=>s+x.pnl,0),gl=-tr.filter(x=>x.pnl<=0).reduce((s,x)=>s+x.pnl,0);
  out.push({b,a,st,tr,pf:gl?gw/gl:(gw?9:0),ret:b.amt?st.sum/b.amt*100:0,done:b.status==='stop'})})}
 return out.sort((x,y)=>(y.b.series.t0||0)-(x.b.series.t0||0))}
function serVerdict(x){const st=x.st,slr=st.n&&st.sl!=null?st.sl/st.n:null;
 if(st.n<5)return ['flat','Мало сделок','Сделок меньше пяти — выводы делать рано. Повторите серию.'];
 if(st.sum>0&&x.pf>=1.3&&st.n>=10)return ['long','Стоит повторить',`Прибыльная серия: прибыль в ${fmt(x.pf,1)} раза больше убытков. Повторите с теми же настройками — можно увеличить число сделок.`];
 if(st.sum>0)return ['long','Слабый плюс',`Серия в плюсе, но с небольшим запасом. Повторите ещё раз${st.n<20?' на 20–30 сделок':''}, чтобы убедиться.`];
 if(slr!=null&&slr>=0.7)return ['short','Стоп слишком близко',`Стоп сработал в ${fmt(slr*100,0)}% сделок. Расширьте стоп — подберите его в «Проверке на истории».`];
 return ['short','Не повторять',`Убыточная серия: ${st.loss} из ${st.n} в минус. Такие сделки лучше не повторять — смените стратегию, время свечи или пару.`]}
function hourOf(t){return new Date(t).getHours()}
function serTips(L){const tips=[],g={};L.filter(x=>x.st.n>0).forEach(x=>{const s=ITS.list.find(q=>q.id===x.b.strat),k=x.b.strat+'|'+x.b.tf+'|'+x.b.sym;
  const o=g[k]||(g[k]={name:`${s?s.name:x.b.strat} · ${(TFS.find(t=>t[0]===x.b.tf)||[0,x.b.tf])[1]} · ${x.b.sym}`,cnt:0,sum:0,n:0,win:0,plus:0});o.cnt++;o.sum+=x.st.sum;o.n+=x.st.n;o.win+=x.st.win;if(x.st.sum>0)o.plus++});
 const G=Object.values(g).sort((a,b)=>b.sum-a.sum);
 if(G.length&&G[0].sum>0)tips.push(['long',`Лучше всего работает: <b>${G[0].name}</b> — ${G[0].cnt} ${G[0].cnt===1?'серия':'серии'}, итог ${sgn(G[0].sum)} $, в плюс ${fmt(G[0].win/G[0].n*100,0)}% сделок. Повторяйте эту связку.`]);
 const W=G.filter(x=>x.sum<0).slice(-1)[0];if(W&&W!==G[0])tips.push(['short',`Хуже всего: <b>${W.name}</b> — итог ${sgn(W.sum)} $. В такие сделки лучше не заходить.`]);
 const T=L.flatMap(x=>x.tr);if(T.length>=10){const L1=T.filter(x=>x.side>0),S1=T.filter(x=>x.side<0),sm=a=>a.reduce((s,x)=>s+x.pnl,0);
  if(L1.length>=5&&S1.length>=5){const a=sm(L1),b=sm(S1);if(a>0&&b<0)tips.push(['long',`Покупки дают ${sgn(a)} $, а продажи ${sgn(b)} $. Лучше торговать только покупки.`]);else if(b>0&&a<0)tips.push(['long',`Продажи дают ${sgn(b)} $, а покупки ${sgn(a)} $. Лучше торговать только продажи.`])}
  const H=[[0,6,'ночью (0–6 ч)'],[6,12,'утром (6–12 ч)'],[12,18,'днём (12–18 ч)'],[18,24,'вечером (18–24 ч)']].map(([f,t,n])=>{const q=T.filter(x=>{const h=hourOf(x.t);return h>=f&&h<t});return {n,c:q.length,s:sm(q),w:q.filter(x=>x.pnl>0).length}}).filter(x=>x.c>=4).sort((a,b)=>b.s-a.s);
  if(H.length>=2&&H[0].s>0&&H[H.length-1].s<0)tips.push(['flat',`Лучшее время — ${H[0].n}: ${sgn(H[0].s)} $. Хуже всего — ${H[H.length-1].n}: ${sgn(H[H.length-1].s)} $. Запускайте серии в удачное время.`]);
  const sl=T.filter(x=>/стоп/.test(x.why)).length;if(T.some(x=>x.why)&&sl/T.length>0.7&&sm(T)<0)tips.push(['short',`Стоп срабатывает в ${fmt(sl/T.length*100,0)}% сделок. Похоже, стоп стоит слишком близко к входу — расширьте его.`])}
 const tot=L.reduce((s,x)=>s+x.st.sum,0),n=L.reduce((s,x)=>s+x.st.n,0);
 return {tips,tot,n,G}}
function histSheet(){const L=serAll(),A=serTips(L);
 return sheetWrap(`${ic('history',20)}&nbsp;Умная история`,!L.length?'<p class="mu">Серий пока не было. Запустите серию сделок — здесь появится разбор: что работает, что нет и что повторить.</p>':
 `<div class="sgrid"><div><small>Серий</small>${L.length}</div><div><small>Сделок</small>${A.n}</div><div><small>Итог всех серий</small><span class="${A.tot>=0?'g':'r'}">${sgn(A.tot)} $</span></div><div><small>Связок «стратегия · свечи · пара»</small>${A.G.length}</div></div>
 ${A.tips.length?`<h3 style="margin:14px 0 6px">${ic('bulb',18)} Подсказки</h3>${A.tips.map(t=>`<div class="plan ${t[0]}" style="cursor:default"><div class="sm">${t[1]}</div></div>`).join('')}`:''}
 ${A.G.length>1?`<h3 style="margin:14px 0 6px">Связки</h3><table class="bt7"><tr><th>Стратегия · свечи · пара</th><th>Серий</th><th>В плюс</th><th>Итог</th></tr>${A.G.map(o=>`<tr><td>${o.name}</td><td>${o.cnt}</td><td>${o.n?fmt(o.win/o.n*100,0):0}%</td><td class="${o.sum>=0?'g':'r'}">${sgn(o.sum)} $</td></tr>`).join('')}</table>`:''}
 <h3 style="margin:14px 0 6px">Все серии</h3>${L.map(x=>{const v=serVerdict(x),s=ITS.list.find(q=>q.id===x.b.strat),d=new Date(x.b.series.t0).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
  return `<div class="plan ${x.st.sum>=0?'long':'short'}" style="cursor:default"><div class="row" style="margin:0"><b style="flex:1">${s?s.name:x.b.strat} · ${x.b.sym} · ${(TFS.find(t=>t[0]===x.b.tf)||[0,x.b.tf])[1]}</b><span class="tag ${v[0]}">${x.done?v[1]:'идёт'}</span></div>
  <div class="sm mu">${d} · ${x.a.kind==='bn'?'настоящий счёт':esc(x.a.name)} · ${x.st.n} из ${x.b.series.n} сделок · стоп −${fmt(x.b.sl,x.b.sl<1?2:1)}%</div>
  <div class="sgrid" style="margin-top:6px"><div><small>В плюс / в минус</small><span class="g">${x.st.win}</span> / <span class="r">${x.st.loss}</span></div><div><small>Итог</small><span class="${x.st.sum>=0?'g':'r'}">${sgn(x.st.sum)} $ (${x.ret>=0?'+':''}${fmt(x.ret,1)}%)</span></div></div>
  ${x.done?`<p class="sm" style="margin:6px 0">${v[2]}</p>`:''}<div class="row" style="margin-bottom:0"><button class="btn2 sm" style="flex:1" data-a="serrep" data-v="${x.b.id}">Повторить</button></div></div>`}).join('')}`)}
const _r11=render;render=function(){if(SHEET&&SHEET[0]==='hist'){SHEET=null;_r11();SHEET=['hist'];$('#app').insertAdjacentHTML('beforeend',histSheet());return}if(SHEET&&SHEET[0]==='coin'){const s=SHEET[1];SHEET=null;_r11();SHEET=['coin',s];$('#app').insertAdjacentHTML('beforeend',coinSheet(s));coinLoad(s);return}_r11()};
// подсказка в уведомлении о завершении серии
const _ev11=event;event=function(k,t){if(k==='bot'&&/^Серия .* завершена/.test(t)){const L=serAll().filter(x=>x.done).sort((a,b)=>(b.b.series.done||0)-(a.b.series.done||0));if(L[0]){t+='. '+serVerdict(L[0])[1]}}return _ev11(k,t)};
// кнопка «История» в карточке серий и в окне серии
const _sh11=serHtml;serHtml=function(){const h=_sh11();return h?h.replace('<button class="btn2 sm" data-a="seropen">Новая серия</button>',`<button class="btn2 sm" data-a="histopen">${ic('history',16)} История</button><button class="btn2 sm" data-a="seropen">Новая серия</button>`):h};
const _ss11=serSheet;serSheet=function(){return _ss11().replace('<p class="hint" style="margin-top:0">Программа сама',`<button class="btn2" style="width:100%;margin-bottom:8px;padding:10px" data-a="histopen">${ic('history',18)} Умная история серий</button><p class="hint" style="margin-top:0">Программа сама`)};
// ================= 2. МОНЕТЫ BINANCE =================
let COINS=LS.get('coins',null),FONB=LS.get('fonb',null),CNL=false;
let CNF=0,FNF=0;async function coinsLoad(){if(COINS&&Date.now()-COINS.t*1000<3600e3)return COINS;if(CNL||Date.now()-CNF<300e3)return COINS;CNL=true;try{const j=await tapi('tr_coins',{});COINS={t:j.t,c:j.coins};LS.set('coins',COINS)}catch(e){CNF=Date.now()}CNL=false;return COINS}
async function fonbLoad(){if(FONB&&Date.now()-FONB.t<86400e3)return FONB;if(Date.now()-FNF<300e3)return FONB;FNF=Date.now();try{const j=await fetch(API.fut+'exchangeInfo').then(r=>r.json());const m={};(j.symbols||[]).forEach(s=>{if(s.quoteAsset==='USDT'&&s.contractType==='PERPETUAL')m[s.baseAsset]=s.onboardDate});FONB={t:Date.now(),m};LS.set('fonb',FONB)}catch(e){}return FONB}
const ageDays=s=>{const d=FONB&&FONB.m[s];return d?Math.floor((Date.now()-d)/86400e3):null};
const SPOTAGE={};
async function spotAge(s){if(SPOTAGE[s]!==undefined)return SPOTAGE[s];try{const a=await fetch(API.spot+'klines?symbol='+s+'USDT&interval=1d&startTime=0&limit=1').then(r=>r.json());SPOTAGE[s]=a&&a[0]?Math.floor((Date.now()-a[0][0])/86400e3):null}catch(e){SPOTAGE[s]=null}return SPOTAGE[s]}
function coinScore(s,info,vol,age){const R=[];let sc=0;
 if(vol!=null){if(vol>=100e6){R.push(['long','Очень много торгов: '+big(vol)+' за сутки — подходит и для минуток, сделки проходят без проскальзывания.']);sc+=2}
  else if(vol>=20e6){R.push(['long','Торгуется хорошо: '+big(vol)+' за сутки.']);sc+=1}
  else if(vol>=3e6){R.push(['flat','Торгов мало: '+big(vol)+' за сутки — только небольшие суммы и крупные свечи.'])}
  else{R.push(['short','Торгов очень мало: '+big(vol)+' за сутки — цену легко двигают, лучше не торговать.']);sc-=3}}
 if(age!=null){if(age<30){R.push(['short',`Новая монета: на Binance ${age} ${age%10===1&&age%100!==11?'день':age%10>=2&&age%10<=4&&(age%100<10||age%100>=20)?'дня':'дней'}. Резкие скачки, высокий риск.`]);sc-=1}
  else if(age<180)R.push(['flat',`Молодая монета: на Binance около ${Math.round(age/30)} мес.`]);else{R.push(['long',`Давно на рынке: на Binance ${age>=365?fmt(age/365,1)+' г.':Math.round(age/30)+' мес.'}`]);sc+=1}}
 if(info){const r=info.r;if(r&&r<=20){R.push(['long',`Крупная монета: ${r}-е место по капитализации (${big(info.mc)}).`]);sc+=2}else if(r&&r<=100){R.push(['long',`Средняя монета: ${r}-е место (${big(info.mc)}).`]);sc+=1}else if(r&&r<=500)R.push(['flat',`Небольшая монета: ${r}-е место (${big(info.mc)}).`]);else{R.push(['short','Маленькая капитализация — цену проще раскачать.']);sc-=1}
  if(info.mc&&info.fdv&&info.fdv/info.mc>2){R.push(['short',`В обращении только ${fmt(info.mc/info.fdv*100,0)}% всех монет — будущие выпуски могут давить на цену.`]);sc-=1}
  if(info.athc!=null&&info.athc<-80)R.push(['flat',`Цена на ${fmt(-info.athc,0)}% ниже максимума (${info.athd}).`])}
 else R.push(['flat','Нет сведений о капитализации — монета маленькая или новая.']);
 const v=sc>=3?['long','Стоит торговать']:sc>=0?['flat','Осторожно']:['short','Лучше не торговать'];return {R,v,sc}}
function coinSheet(s){return sheetWrap(`${ic('info',20)}&nbsp;${s} — о монете`,`<div id="coinbody"><p class="mu">Загружаю сведения…</p></div>`)}
async function coinLoad(s){const [C1]=await Promise.all([coinsLoad(),fonbLoad()]);let list=[];try{list=await loadPairs(UI.mkt)}catch(e){}const px=list.find(x=>x.s===s)||{};const info=C1&&C1.c&&C1.c[s];let age=ageDays(s);if(age==null)age=await spotAge(s);
 const sc=coinScore(s,info,px.vol,age),bt=((PXV.BTC&&PXV.BTC.ch!=null)?PXV.BTC:(list.find(x=>x.s==='BTC')||{})).ch,b=$('#coinbody');if(!b||!SHEET||SHEET[1]!==s)return;
 b.innerHTML=`<div class="wbox"><div class="row" style="margin:0"><b style="flex:1">${info?esc(info.n)+' · ':''}${s}</b><span class="tag ${sc.v[0]}">${sc.v[1]}</span></div><div class="sm mu">${UI.mkt==='fut'?'Фьючерсы':'Спот'} Binance · цена ${fp(PX[s]||px.p)}</div></div>
 <div class="sgrid" style="margin-top:8px"><div><small>Капитализация</small>${big(info&&info.mc)}${info&&info.r?` <small>(${info.r}-е место)</small>`:''}</div><div><small>Все монеты по текущей цене</small>${big(info&&info.fdv)}</div>
 <div><small>Торги на Binance за сутки</small>${big(px.vol)}</div><div><small>Торги на всех биржах за сутки</small>${big(info&&info.v)}</div>
 <div><small>На Binance (фьючерсы)</small>${age!=null?(age<365?age+' дн.':fmt(age/365,1)+' г.'):'—'}</div><div><small>Изменение за 24 ч / к BTC</small>${px.ch!=null?`<span class="${px.ch>=0?'g':'r'}">${px.ch>=0?'+':''}${fmt(px.ch,1)}%</span>`:'—'}${px.ch!=null&&bt!=null&&s!=='BTC'?` / <span class="${px.ch-bt>=0?'g':'r'}">${px.ch-bt>=0?'+':''}${fmt(px.ch-bt,1)}%</span>`:''}</div>
 ${info?`<div><small>За 7 / 30 дней</small>${['c7','c30'].map(k=>info[k]==null?'—':`<span class="${info[k]>=0?'g':'r'}">${info[k]>=0?'+':''}${fmt(info[k],0)}%</span>`).join(' / ')}</div><div><small>За год</small>${info.c1y==null?'—':`<span class="${info.c1y>=0?'g':'r'}">${info.c1y>=0?'+':''}${fmt(info.c1y,0)}%</span>`}</div>
 <div><small>Максимум цены</small>${info.ath?fp(info.ath):'—'} <small>${info.athd||''}</small></div><div><small>В обращении</small>${info.cs&&(info.ms||info.ts)?fmt(info.cs/(info.ms||info.ts)*100,0)+'% от всех':'—'}</div>`:''}</div>
 <h3 style="margin:14px 0 6px">${ic('bulb',18)} Стоит ли торговать</h3>${sc.R.map(r=>`<div class="plan ${r[0]}" style="cursor:default;padding:8px 10px"><div class="sm">${r[1]}</div></div>`).join('')}
 ${s!==UI.sym?`<button class="go b" data-a="setpair" data-v="${s}">Открыть график ${s}</button>`:''}<p class="hint">Капитализация и выпуск монет — по данным CoinGecko, обновляются раз в час. Это подсказка, а не совет покупать.</p>`}
// список пар: все монеты Binance, вкладка «Новые», сортировка по капитализации и силе к BTC, кнопка «о монете»
const _pse11=pairsSheet;pairsSheet=function(){let h=_pse11();h=h.replace(/(data-a="ptab" data-v="fav">[\s\S]*?<\/button>)/,`$1<button class="${UI.pTab==='new'?'on':''}" data-a="ptab" data-v="new">НОВЫЕ</button>`);
 h=h.replace('<table class="pt">',`<div class="pct" style="margin:6px 0">${[['vol','Объём'],['ch','Рост 24ч'],['mc','Капитализация'],['btc','Сильнее BTC']].map(x=>`<button class="${UI.pSort===x[0]?'on':''}" data-a="psort2" data-v="${x[0]}">${x[1]}</button>`).join('')}</div><div class="sm mu" id="pcount"></div><table class="pt">`);return h};
paintPairs=async function(){let list;try{list=await loadPairs(UI.mkt)}catch(e){const b=$('#plist');if(b)b.innerHTML='<tr><td colspan="3" class="err">Нет связи с биржей</td></tr>';return}
 const Cn=(COINS&&COINS.c)||{};{const c0=COINS,f0=FONB;if(!COINS||Date.now()-COINS.t*1000>3600e3)coinsLoad().then(c=>{if(c&&c!==c0&&$('#plist'))paintPairs()});if(!FONB)fonbLoad().then(f=>{if(f&&f!==f0&&$('#plist'))paintPairs()})}
 const q=PLQ.toUpperCase(),bt=((PXV.BTC&&PXV.BTC.ch!=null)?PXV.BTC:(list.find(x=>x.s==='BTC')||{})).ch||0;let a=list.filter(x=>(!q||x.s.includes(q)||((Cn[x.s]||{}).n||'').toUpperCase().includes(q))&&(UI.pTab!=='fav'||UI.fav.includes(x.s))&&(UI.pTab!=='new'||((ageDays(x.s)??9999)<90)));
 const k=UI.pSort,chv=x=>(PXV[x.s]||x).ch;a.sort((x,y)=>k==='name'?x.s.localeCompare(y.s):k==='price'?y.p-x.p:k==='ch'?chv(y)-chv(x):k==='mc'?(((Cn[y.s]||{}).mc||0)-((Cn[x.s]||{}).mc||0)):k==='btc'?chv(y)-chv(x):UI.pTab==='new'?(ageDays(x.s)??9999)-(ageDays(y.s)??9999):y.vol-x.vol);
 const c=$('#pcount');if(c)c.textContent=`${UI.pTab==='new'?'Новых на Binance за 90 дней':'Всего пар'}: ${a.length}`;
 const b=$('#plist');if(!b)return;b.innerHTML=a.map(x=>{const p=PX[x.s]||x.p,ch=chv(x),inf=Cn[x.s],ag=ageDays(x.s),d=ch-bt;return `<tr class="${x.s===UI.sym?'on':''}"><td><button class="hb ${UI.fav.includes(x.s)?'on':''}" data-a="pfav" data-v="${x.s}">${ic('heart',16)}</button><span data-a="setpair" data-v="${x.s}"><b>${x.s}</b><span class="mu">/USDT</span>${ag!=null&&ag<30?' <span class="tag short" style="font-size:10px;padding:0 4px">новая</span>':''}<br><small class="mu">${inf?(inf.r?'#'+inf.r+' · ':'')+big(inf.mc):''}</small></span><button class="hb" data-a="coininfo" data-v="${x.s}" title="О монете">${ic('info',16)}</button></td><td style="text-align:right" data-a="setpair" data-v="${x.s}">${fp(p)}<br><small class="mu">${big(x.vol)}</small></td><td style="text-align:right" data-a="setpair" data-v="${x.s}"><span class="${ch>=0?'g':'r'}">${ch>=0?'+':''}${fmt(ch,2)}%</span>${x.s!=='BTC'?`<br><small class="${d>=0?'g':'r'}">к BTC ${d>=0?'+':''}${fmt(d,1)}%</small>`:''}</td></tr>`}).join('')||'<tr><td colspan="3" class="mu">Ничего не найдено</td></tr>'};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='histopen'){e.stopPropagation();SHEET=['hist'];render();history.pushState({sh:1},'')}
 else if(a==='coininfo'){e.stopPropagation();SHEET=['coin',v];render();history.pushState({sh:1},'')}
 else if(a==='coinme'){e.stopPropagation();SHEET=['coin',UI.sym];render();history.pushState({sh:1},'')}
 else if(a==='psort2'){e.stopPropagation();UI.pSort=v;saveUI();$$('[data-a=psort2]').forEach(x=>x.classList.toggle('on',x.dataset.v===v));paintPairs()}},true);
const _tv11=termView;termView=function(){let h=_tv11();h=h.replace(/(<button class="chip[^"]*" data-a="rson">)/,`<button class="chip" data-a="coinme">${ic('info',18)} О монете</button>$1`);return h};
render();coinsLoad();fonbLoad();
