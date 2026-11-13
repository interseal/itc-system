// ИТК Трейдинг — №479 (12.11.2026): страница «Работа ботов» и запуск бота из итогов всей биржи.
// Страница открывается кнопкой во вкладке «Боты» и в «Анализе». На ней все боты (серии), которые торгуют на сервере в Гонконге:
// сколько сделок прошло, сколько в плюсе и в минусе, итог в $ и %, открытая сделка, последние сделки. Обновляется сама раз в 20 с.
// В итогах «Вся биржа» у каждой пары кнопка «Запустить бота на демо-счёте» — серия из 30 сделок по $100, без плеча.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 document.head.insertAdjacentHTML('beforeend','<style>#srvb{position:fixed;inset:0;z-index:2147483000;background:var(--bg,#131722);overflow-y:auto;-webkit-overflow-scrolling:touch}#srvb .hd{position:sticky;top:0;z-index:2;display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--pan,#1e222d);border-bottom:1px solid var(--ln,#2a2e39)}#srvb .hd b{flex:1;font-size:17px}#srvb .wrapb{padding:10px 12px 90px;max-width:900px;margin:0 auto}#srvb .tl{display:flex;justify-content:space-between;gap:8px;padding:5px 0;border-bottom:1px solid var(--ln,#2a2e39);font-size:13px}</style>');
 let OPEN=false,TMR=0,F='run';
 const pct=(v,n)=>(v>=0?'+':'')+fmt(v,n==null?1:n)+'%';
 const dt=t=>new Date(t>1e12?t:t*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
 function sumAll(L){let n=0,win=0,sum=0,amt=0,open=0;L.forEach(s=>{const st=tdStats(s);n+=st.n;win+=st.win;sum+=st.sum;amt+=s.amt||0;if(s.open)open++});return {n,win,sum,amt,open}}
 function card(s){const st=tdStats(s),run=s.status==='run',pc=Math.min(100,Math.round(st.n/Math.max(1,s.n)*100)),wr=st.n?st.win/st.n*100:0,ret=s.amt?st.sum/s.amt*100:0,u=typeof tdOpenPnl==='function'?tdOpenPnl(s):null,last=(s.trades||[]).slice(-5).reverse();
  return `<div class="plan ${st.sum>=0?'long':'short'}" style="cursor:default;padding:10px 12px">
  <div class="row" style="margin:0;align-items:center"><b style="flex:1">${esc(s.sym)} · ${esc(tdName(s))} · ${tdTf(s.tf)}</b><span class="tag ${run?'long':'flat'}">${run?(s.open?'в сделке':'ждёт сигнал'):'завершена'}</span></div>
  <div class="sm mu">${typeof tdRule==='function'&&s.sys?tdRule(s):''} · ставка ${fmt(s.amt,0)} $${s.lev>1?' × '+s.lev:''} · запущена ${dt(s.t0)}</div>
  <div style="height:6px;border-radius:3px;background:var(--ln);margin:6px 0"><div style="height:6px;border-radius:3px;width:${pc}%;background:var(--ac)"></div></div>
  <div class="sgrid"><div><small>Сделок</small>${st.n} из ${s.n}</div><div><small>В плюс / в минус</small><span class="g">${st.win}</span> / <span class="r">${st.loss}</span> (${fmt(wr,0)}%)</div><div><small>Итог</small><b class="${st.sum>=0?'g':'r'}">${sgn(st.sum)} $ (${pct(ret)})</b></div><div><small>Открытая сделка</small>${s.open?`${s.open.side>0?'лонг':'шорт'} от ${fp(s.open.ep)}${u!=null?` · <span class="${u>=0?'g':'r'}">${sgn(u)} $</span>`:''}`:'нет'}</div></div>
  ${last.length?`<div style="margin-top:6px">${last.map(t=>`<div class="tl"><span>${t.t1||t.t?dt(t.t1||t.t):''} · ${t.side>0?'лонг':t.side<0?'шорт':''} · ${esc(t.why||'')}</span><b class="${t.pnl>=0?'g':'r'}">${sgn(t.pnl)} $</b></div>`).join('')}</div>`:'<p class="sm mu" style="margin:6px 0 0">Сделок пока нет — бот ждёт сигнал.</p>'}
  ${typeof window.bnSerInfo==='function'?window.bnSerInfo(s):''}
  <div class="row" style="margin:8px 0 0">${run&&s.mkt!=='spot'?`<button class="btn2 sm" style="flex:1;${s.live&&s.live.on?'':'color:#f23645'}" data-a="tdlive" data-v="${s.id}">${s.live&&s.live.on?'Отключить Binance':'Торговать на Binance'}</button>`:''}${run?`<button class="btn2 sm" style="flex:1" data-a="tdstop" data-v="${s.id}">Остановить</button>`:`<button class="btn2 sm" style="flex:1" data-a="tdhide" data-v="${s.id}">Убрать из списка</button>`}</div></div>`}
 function body(){const all=(TD||[]).filter(s=>!s.hidden),L=all.filter(s=>F==='all'||(F==='run'?s.status==='run':s.status!=='run')),A=sumAll(all.filter(s=>s.status==='run')),B=sumAll(all);
  const tb=(v,t)=>`<button class="btn2" data-a="sbf" data-v="${v}" style="${F===v?'background:var(--ac);color:#fff':''}">${t}</button>`;
  return `<div class="card"><div class="sgrid"><div><small>Работают сейчас</small><b>${all.filter(s=>s.status==='run').length}</b></div><div><small>Сейчас в сделке</small>${A.open}</div><div><small>Сделок всего</small>${B.n} (в плюс ${B.n?fmt(B.win/B.n*100,0):0}%)</div><div><small>Итог всех ботов</small><b class="${B.sum>=0?'g':'r'}">${sgn(B.sum)} $</b></div></div>
   <p class="sm mu" style="margin:6px 0 0">${typeof tdCronTxt==='function'?tdCronTxt():''} Обновляется само раз в 20 секунд.</p></div>
   ${typeof window.srvbExtra==='function'?window.srvbExtra():''}
   <div class="row" style="gap:6px;margin:0 0 8px;flex-wrap:wrap">${tb('run','Работают')}${tb('done','Завершены')}${tb('all','Все')}</div>
   ${L.length?L.map(card).join(''):`<div class="card"><p class="mu" style="margin:0">${F==='run'?'Сейчас ни один бот не работает. Запустите бота из «Анализа» → «Вся биржа» → кнопка «Запустить бота на демо-счёте» у нужной пары.':'Здесь пока пусто.'}</p></div>`}`}
 function paint(){const e=document.getElementById('srvbb');if(e)e.innerHTML=body()}
 function open(){if(OPEN){paint();return}OPEN=true;const w=document.createElement('div');w.id='srvb';
  w.innerHTML=`<div class="hd"><button class="btn2" data-a="sbclose" style="padding:6px 10px">${ic('close',18)}</button><b>${ic('bot',20)} Работа ботов</b><button class="btn2" data-a="sbre" style="padding:6px 10px">${ic('replay',18)}</button></div><div class="wrapb" id="srvbb">${body()}</div>`;
  document.body.appendChild(w);try{history.pushState({srvb:1},'')}catch(_){}
  try{tdLoad()}catch(_){}TMR=setInterval(()=>{if(document.visibilityState!=='hidden'){try{tdLoad()}catch(_){}}},20e3)}
 function close(back){if(!OPEN)return;OPEN=false;clearInterval(TMR);const w=document.getElementById('srvb');if(w)w.remove();if(back!==false){try{if(history.state&&history.state.srvb)history.back()}catch(_){}}}
 window.addEventListener('popstate',()=>{if(OPEN)close(false)});
 window.srvbOpen=open;
 // обновление страницы вместе со списком серий
 const _tp50=tdPaint;tdPaint=function(){_tp50();if(OPEN)paint()};
 // кнопка в «Ботах» и в «Анализе»
 const BTN=`<button class="go b" data-a="sbopen" style="margin:0 0 10px;display:flex;align-items:center;justify-content:center;gap:8px">${ic('bot',20)} Работа ботов на сервере</button>`;
 const _bv50=botsView;botsView=function(){const h=_bv50();const i=h.indexOf('<div class="card">');return i>=0?h.slice(0,i)+BTN+h.slice(i):BTN+h};
 // ---------- постоянный анализ: сервер сам перепроверяет биржу раз в сутки ----------
 let AUTO=null;capi('kv_get',{k:'autofull'}).then(j=>{AUTO=!!(j&&j.v&&j.v.on);try{fPaint()}catch(_){}}).catch(()=>{});
 const AUTOB=()=>`<div class="card" style="padding:10px 12px"><div class="row" style="margin:0;align-items:center"><span style="flex:1"><b>${ic('replay',18)} Постоянный анализ</b><br><span class="sm mu">${AUTO?'Включён: сервер сам перепроверяет биржу раз в сутки с настройками ниже, итоги обновляются сами.':'Выключен. Включите — сервер будет сам перепроверять биржу раз в сутки, телефон не нужен.'}</span></span>
  <button class="btn2" data-a="sbauto" style="${AUTO?'background:var(--ac);color:#fff':''};padding:8px 12px">${AUTO?'Включён':'Включить'}</button></div></div>`;
 async function autoSet(on){try{await capi('kv_set',{k:'autofull',v:{on:on?1:0,set:sjSet('full')}});AUTO=on;toast(on?'Постоянный анализ включён — сервер будет проверять биржу раз в сутки':'Постоянный анализ выключен')}catch(e){toast('Не удалось сохранить: '+(e.message||'нет связи'))}try{fPaint()}catch(_){}}
 // поменяли монеты/свечи/фильтры при включённом анализе — запоминаем новые настройки для сервера
 window.addEventListener('click',e=>{const el=e.target.closest&&e.target.closest('[data-a]');if(!el||!['fsc','fgr','ffl','fsm'].includes(el.dataset.a))return;if(AUTO&&UI.view==='lab'&&(UI.labTab||'')==='full')setTimeout(()=>{try{capi('kv_set',{k:'autofull',v:{on:1,set:sjSet('full')}})}catch(_){}},800)});
 const _fb50=fBody;fBody=function(){return BTN+(AUTO==null?'':AUTOB())+_fb50()};
 // ---------- запуск бота из итогов ----------
 function ask(html,yes){return new Promise(res=>{const w=document.createElement('div');w.style.cssText='position:fixed;inset:0;z-index:2147483600;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';
  w.innerHTML=`<div style="background:var(--pan,#1e222d);color:var(--tx,#d1d4dc);border:1px solid var(--ln,#2a2e39);border-radius:12px;max-width:440px;width:100%;padding:18px">${html}<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button data-x="0" class="btn2" style="padding:10px 14px">Отмена</button><button data-x="1" class="btn2" style="padding:10px 14px;background:var(--ac,#2962ff);color:#fff;border-color:transparent">${yes}</button></div></div>`;
  w.addEventListener('click',e=>{const b=e.target.closest('[data-x]');if(!b&&e.target!==w)return;e.stopPropagation();e.preventDefault();const amt=+((w.querySelector('#fbA')||{}).value||100),n=+((w.querySelector('#fbN')||{}).value||30);w.remove();res(!!b&&b.dataset.x==='1'?{amt,n}:null)},true);document.body.appendChild(w)})}
 async function botFrom(v){const [sym,tf,st,k,dir,stop]=v.split('|'),K=+k,D=+dir,nm=(FULL.top&&FULL.top.nm&&FULL.top.nm[st])||st;
  const r=await ask(`<b style="font-size:17px">Запустить бота на демо-счёте?</b><p class="sm" style="margin:8px 0">${esc(sym)} · ${esc(nm)} · ${typeof baTfn==='function'?baTfn(tf):tf} · ${D===0?'лонг и шорт':D===1?'только лонг':'только шорт'} · ${RULEN(K)}</p>
   <div class="row"><label>Ставка на сделку, $</label><input id="fbA" type="number" min="10" value="100" style="width:100px"></div><div class="row"><label>Сделок в серии</label><input id="fbN" type="number" min="5" max="500" value="30" style="width:100px"></div>
   <p class="sm mu" style="margin:6px 0 0">Торгует сервер в Гонконге ровно по правилам проверки, без плеча, на демо-счёте — без настоящих денег. Телефон можно закрыть.</p>`,'Запустить');
  if(!r)return;if(r.amt<10){toast('Ставка — не меньше $10');return}
  try{const j=await tdapi('td_start',{sym,mkt:(FULL.top&&FULL.top.mkt)||'fut',tf,strat:st,prm:(typeof PRM!=='undefined'&&PRM[st])||{},amt:r.amt,lev:1,sl:K>=4?1:Math.min(49,Math.max(0.05,+stop||1)),n:Math.round(r.n),acc:typeof curAcc!=='undefined'?curAcc:'',sys:{k:K,dir:D,htf:0,adx:0,maker:0,hrs:0,vol:0,sm:1}});
   const sr=j.series;if(sr){TD=[sr].concat((TD||[]).filter(x=>x.id!==sr.id));try{LS.set('tdc',TD)}catch(_){}}
   toast('Бот запущен на сервере: '+sym);open()}catch(e){toast('Сервер не запустил бота: '+(e.message||'нет связи'))}}
 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;if(!['sbopen','sbclose','sbre','sbf','fbot','sbauto'].includes(a))return;e.stopPropagation();e.preventDefault();
  if(a==='sbopen')open();else if(a==='sbclose')close();else if(a==='sbre'){try{RQ.tdlAt=0}catch(_){}try{tdLoad()}catch(_){}toast('Обновляю…')}else if(a==='sbf'){F=el.dataset.v;paint()}else if(a==='fbot')botFrom(el.dataset.v);else if(a==='sbauto')autoSet(!AUTO)},true);
})();
