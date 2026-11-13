// ИТК Трейдинг — №481 (12.11.2026): сервер сам следит за новыми монетами — «Шорт после взлёта».
// Включили во вкладке «Листинги» — сервер в Гонконге раз в час (после закрытия часовой свечи) проверяет все монеты,
// которые запустились на фьючерсах Binance за последние 40 дней. Есть сигнал — открывает шорт на демо-счёте, ведёт и закрывает его
// по тем же правилам, что и проверка на истории. Сделки видны в «Листингах» и на странице «Работа ботов»; о новой сделке приложение сообщает.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 let W=null,L=null,META={},BUSY=false;
 const set=()=>{let s={pump:0.6,stall:2,m:20,hold:168,sb:0.1};try{Object.assign(s,JSON.parse(localStorage.getItem('nlsset')||'{}'))}catch(_){}return s};
 const dd=t=>new Date(t*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
 const pct=v=>(v>=0?'+':'')+fmt(v,1)+'%';
 async function load(){if(BUSY)return;BUSY=true;try{const a=await capi('kv_get',{k:'nlwatch'});W=(a&&a.v)||{on:0};const b=await tdapi('nl_list',{});L=b.items||[];META={last:b.last||0,err:b.err||'',now:b.now||0};notify()}catch(_){}BUSY=false;paint()}
 // о новых и закрытых сделках — сообщение в приложении
 function notify(){let seen={};try{seen=JSON.parse(localStorage.getItem('nlseen')||'{}')}catch(_){}const first=!Object.keys(seen).length;
  (L||[]).forEach(x=>{const k=x.sym+'|'+(x.sys||'A'),st=x.status;if(seen[k]===st)return;
   if(!first){const t=st==='open'?`Новая монета ${x.sym} (система ${SN(x)}): сервер открыл шорт после взлёта +${fmt(x.pump,0)}% (демо)`:`Новая монета ${x.sym} (система ${SN(x)}): шорт закрыт — ${x.why}, ${pct(x.pnl)} (${USD(x.usd)})`;try{event('bot',t)}catch(_){}toast(t)}
   seen[k]=st});try{localStorage.setItem('nlseen',JSON.stringify(seen))}catch(_){}}
 async function toggle(){const on=!(W&&W.on),s=set();let amt=+((document.getElementById('nlwA')||{}).value)||(W&&W.amt)||100;if(amt<10)amt=10;
  const v={on:on?1:0,pump:s.pump,stall:s.stall,m:s.m,hold:s.hold||168,sb:s.sb,amt,since:on?Math.floor(Date.now()/1000):((W&&W.since)||0)};
  try{await capi('kv_set',{k:'nlwatch',v});W=v;toast(on?'Сервер следит за новыми монетами — проверка раз в час':'Слежение выключено, открытые сделки досчитаются')}catch(e){toast('Не удалось: '+(e.message||'нет связи'))}paint()}
 function sum(f){const A=(L||[]).filter(x=>!f||(x.sys||'A')===f),C=A.filter(x=>x.status==='closed'),O=A.filter(x=>x.status==='open');return {A,C,O,win:C.filter(x=>x.pnl>0).length,usd:C.reduce((a,x)=>a+(x.usd||0),0),ousd:O.reduce((a,x)=>a+(x.usd||0),0)}}
 const SN=x=>({A:'А',B:'Б',C:'В'})[x.sys||'A']||'А';
 function rows(lim){const A=(L||[]).slice(0,lim||100);if(!A.length)return '<p class="sm mu" style="margin:6px 0 0">Сделок пока нет. Сигнал бывает 2–3 раза в месяц — когда новая монета сильно взлетит и остановится.</p>';
  return `<div style="overflow-x:auto;margin-top:6px"><table class="bt7" style="width:100%;font-size:13px"><tr><th>Монета</th><th>Система</th><th>Взлёт</th><th>Шорт открыт</th><th>Состояние</th><th>Итог</th></tr>
  ${A.map(x=>`<tr><td><b>${esc(x.sym)}</b></td><td>${SN(x)}</td><td class="g">+${fmt(x.pump,0)}%</td><td>${dd(x.t_in)}<div class="mu" style="font-size:11px">по ${fp(x.ep)} · стоп ${fp(x.stop)}</div></td><td>${x.status==='open'?`<span class="tag long">идёт</span><div class="mu" style="font-size:11px">${x.set&&x.set.hold?`держим до ${dd(x.t_in+x.set.hold*3600)}`:`${x.calm||0} из ${(x.set&&x.set.m)||20} свечей без нового минимума`}</div>`:`закрыт ${dd(x.t_out)}<div class="mu" style="font-size:11px">${esc(x.why||'')}</div>`}</td><td class="${x.pnl>=0?'g':'r'}"><b>${pct(x.pnl)}</b><div style="font-size:11px">${USD(x.usd||0)}</div></td></tr>`).join('')}</table></div>`}
 function card(full){const on=!!(W&&W.on),s=set(),S=sum();
  const st=META.last?`Последняя проверка сервера: ${dd(META.last)}.`:on?'Сервер проверит монеты в начале следующего часа.':'';
  return `<div class="card"><div class="row" style="margin:0;align-items:center"><span style="flex:1"><b>${ic('cloud',18)} Сервер следит за новыми монетами</b><br><span class="sm mu">${on?`Включено три системы на одном входе: взлёт от +${fmt(((W&&W.pump)||s.pump)*100,0)}%, остановка ${(W&&W.stall)||s.stall} свечи. Выход: А — после ${(W&&W.m)||s.m} свечей без нового минимума, В — через 3 дня, Б — через 7 дней; ставка $${fmt((W&&W.amt)||100,0)} на сделку, демо-счёт.`:'Выключено. Включите — сервер раз в час сам ищет сигнал «шорт после взлёта» у монет, которые запустились за последние 40 дней, и торгует на демо-счёте.'}</span></span>
   <button class="btn2" data-a="nlwon" style="${on?'background:var(--ac);color:#fff':''};padding:8px 12px">${on?'Включено':'Включить'}</button></div>
   ${!on&&full?`<div class="row" style="margin:8px 0 0"><label>Ставка на сделку, $</label><input id="nlwA" class="bninp" type="number" min="10" value="${(W&&W.amt)||100}"></div><p class="hint" style="margin:4px 0 0">Сервер возьмёт условия, выбранные ниже в «Шорт после взлёта новой монеты».</p>`:''}
   ${META.err?`<p class="err" style="margin:6px 0 0">${esc(META.err)}</p>`:''}
   ${S.A.length?['A','C','B'].map(k=>{const Q=sum(k);return `<div style="margin-top:8px"><b class="sm">Система ${k==='A'?'А · до остановки падения':k==='C'?'В · держать 3 дня':'Б · держать 7 дней'}</b><div class="sgrid"><div><small>Сделок</small>${Q.A.length} (идут ${Q.O.length})</div><div><small>Закрыто в плюс</small><span class="g">${Q.win} из ${Q.C.length}</span></div><div><small>Итог закрытых</small><b class="${Q.usd>=0?'g':'r'}">${USD(Q.usd)}</b></div><div><small>Открытые сейчас</small><span class="${Q.ousd>=0?'g':'r'}">${USD(Q.ousd)}</span></div></div></div>`}).join(''):''}
   ${rows(full?100:10)}<p class="sm mu" style="margin:6px 0 0">${st} Демо-счёт: без настоящих денег, без плеча, комиссия и проскальзывание учтены.</p></div>`}
 function paint(){const e=document.getElementById('nlwbox');if(e)e.innerHTML=(typeof lcard==='function'?lcard():'')+card(true);try{lrisk()}catch(_){}if(document.getElementById('srvbb')&&typeof window.srvbOpen==='function'){try{window.srvbOpen()}catch(_){}}}
 const _lb53=lsBody;lsBody=function(){if(W==null&&!BUSY)load();return `<div id="nlwbox">${W==null?'':lcard()+card(true)}</div>`+_lb53()};
 window.srvbExtra=()=>W==null?'':`<h3 style="margin:4px 0 8px">${ic('trend',20)} Новые монеты: шорт после взлёта</h3>`+lcard()+card(false);

 // ---------- №485 · настоящая торговля на Binance (фьючерсы): запуск только с подтверждением ----------
 let LV=null,LBUSY=false,LSYS={A:1,C:1,B:0},LAMT=100,LLEV=1,LN=30;
 // №489 · худшая сделка по истории для каждой системы — из последней проверки «Шорт после взлёта»
 let WST=null;capi('kv_get',{k:'nlsres'}).then(j=>{const R=j&&j.v;if(!R)return;const mn=T=>(T&&T.length)?Math.min(...T.map(t=>t.p)):null;WST={A:mn(R.T),B:mn(R.B),C:mn(R.C)};paint()}).catch(()=>{});
 const wOf=()=>{const ks=['A','C','B'].filter(k=>LSYS[k]);let w=0;ks.forEach(k=>{const v=WST&&WST[k]!=null?Math.abs(WST[k]):({A:35,C:44,B:44})[k];w=Math.max(w,v)});return {worst:w,nsys:ks.length||1}};
 function lrisk(){const e=document.getElementById('nlxRisk');if(!e||typeof window.bnRisk!=='function')return null;const a=+((document.getElementById('nlxA')||{}).value)||LAMT;const r=window.bnRisk(Object.assign(wOf(),{bal:LBAL,amt:a,lev:LLEV}));e.innerHTML=r.html;return r}
 let LBAL=null,LTOUCH=false;
 async function lload(){if(LBUSY)return;LBUSY=true;try{if(typeof window.bnBal==='function'){LBAL=await window.bnBal();if(LBAL&&LBAL.wallet>0&&!LTOUCH&&!(LV&&LV.live&&LV.live.on)){const r=window.bnRisk(Object.assign(wOf(),{bal:LBAL,amt:LAMT,lev:LLEV}));if(r.lvl>=2&&r.safe>=10)LAMT=r.safe}}}catch(_){}try{const j=await tdapi('nl_live_list',{});LV=j;if(j.live&&j.live.on){LSYS={A:0,B:0,C:0};(j.live.sys||[]).forEach(k=>LSYS[k]=1);LAMT=j.live.amt||LAMT;LLEV=j.live.lev||1;LN=j.live.n||30}}catch(_){LV=LV||{err:1}}LBUSY=false;paint()}
 const SNM={A:'А · до остановки падения',C:'В · держать 3 дня',B:'Б · держать 7 дней'};
 function confirmBox(title,html,yes,danger){return new Promise(res=>{const w=document.createElement('div');w.style.cssText='position:fixed;inset:0;z-index:2147483600;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:16px';
  w.innerHTML=`<div style="background:var(--pan,#1e222d);color:var(--tx,#d1d4dc);border:2px solid ${danger?'#f23645':'var(--ln,#2a2e39)'};border-radius:12px;max-width:460px;width:100%;padding:18px;max-height:90vh;overflow:auto">
   <div style="display:flex;gap:10px;align-items:flex-start"><svg viewBox="0 0 24 24" style="width:28px;height:28px;flex:none;fill:#f23645"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg><b style="font-size:18px">${title}</b></div>
   <div class="sm" style="margin:10px 0 0;line-height:1.5">${html}</div>
   <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px;cursor:pointer"><input type="checkbox" id="cbOk" style="width:20px;height:20px;flex:none;margin-top:2px"><span class="sm"><b>Подтверждаю.</b> Это настоящие деньги на моём счёте Binance.</span></label>
   <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button data-x="0" class="btn2" style="padding:10px 16px">Отмена</button><button data-x="1" id="cbGo" class="btn2" disabled style="padding:10px 16px;opacity:.45;background:#f23645;color:#fff;border-color:transparent">${yes}</button></div></div>`;
  const cb=w.querySelector('#cbOk'),go=w.querySelector('#cbGo');cb.addEventListener('change',()=>{go.disabled=!cb.checked;go.style.opacity=cb.checked?1:.45});
  w.addEventListener('click',e=>{const b=e.target.closest('[data-x]');if(!b&&e.target!==w)return;if(b&&b.dataset.x==='1'&&!cb.checked)return;e.stopPropagation();e.preventDefault();w.remove();res(!!b&&b.dataset.x==='1')},true);document.body.appendChild(w)})}
 async function lstart(){const keys=(LV&&LV.keys)||[];if(!keys.length){toast('Сначала подключите ключ Binance для фьючерсов');return}
  const sys=['A','C','B'].filter(k=>LSYS[k]);if(!sys.length){toast('Выберите хотя бы одну систему');return}
  const a=+((document.getElementById('nlxA')||{}).value)||LAMT;if(a<10||a>5000){toast('Сумма на сделку — от $10 до $5 000');return}LAMT=a;const nn=Math.round(+((document.getElementById('nlxN')||{}).value)||LN);if(nn<1||nn>200){toast('Сделок в серии — от 1 до 200');return}LN=nn;
  const s=set(),key=keys[0];
  const ok=await confirmBox('Подтвердите запуск серии на Binance',`Сервер будет сам открывать <b>настоящие</b> шорты на фьючерсах Binance по сигналу «шорт после взлёта новой монеты» и закрывать их по правилам систем.<br><br>
   Ключ: <b>${esc(key.name||'Binance фьючерсы')}</b><br>Системы: <b>${sys.map(k=>SNM[k]).join(', ')}</b><br>Серия: <b>${LN} сделок</b> на каждую систему, потом новые входы прекращаются.<br>Сумма: <b>$${fmt(a,0)}</b> на сделку каждой системы — на один сигнал до <b>$${fmt(a*sys.length,0)}</b>${LLEV>1?` · плечо <b>${LLEV}×</b>`:' · без плеча'}<br>
   Условия входа: взлёт от +${fmt(s.pump*100,0)}%, остановка ${s.stall} свечи, стоп на ${fmt(s.sb*100,0)}% выше свечей остановки — ставится на бирже сразу.<br><br>
   Сигналов 2–3 в месяц. Нужен односторонний режим позиций на Binance (One-way).`+(window.bnRisk?window.bnRisk(Object.assign(wOf(),{bal:LBAL,amt:a,lev:LLEV})).html:''),'Запустить',true);
  if(!ok)return;
  try{const j=await tdapi('nl_live_set',{on:1,key:key.id,sys,amt:a,lev:LLEV,n:LN,set:{pump:s.pump,stall:s.stall,m:s.m,sb:s.sb}});toast('Серия на Binance запущена — сервер ждёт сигнал');LV.live=j.live}catch(e){toast('Не запустилось: '+(e.message||'нет связи'))}lload()}
 async function lstop(){const ok=await confirmBox('Остановить новые входы?','Сервер больше не будет открывать новые сделки на Binance. Уже открытые сделки досчитаются по своим правилам — стопы на бирже остаются.','Остановить',false);if(!ok)return;
  try{await tdapi('nl_live_set',{on:0});toast('Новые входы на Binance остановлены')}catch(e){toast('Не удалось: '+(e.message||'нет связи'))}lload()}
 async function lclose(){const ok=await confirmBox('Закрыть все сделки сейчас?','Сервер снимет стопы и закроет по рынку все открытые сделки этой стратегии на Binance, и выключит новые входы.','Закрыть всё',true);if(!ok)return;
  try{const j=await tdapi('nl_live_close',{});toast(j.errors&&j.errors.length?'Не всё закрылось: '+j.errors.join('; '):'Все сделки закрыты')}catch(e){toast('Не удалось: '+(e.message||'нет связи'))}lload()}
 function lcard(){if(!LV)return '';const on=!!(LV.live&&LV.live.on),keys=LV.keys||[],I=LV.items||[],C=I.filter(x=>x.status==='closed'),O=I.filter(x=>x.status==='open'),usd=C.reduce((a,x)=>a+(x.pnl_usd||0),0);
  const sb=k=>`<button class="btn2" data-a="nlxs" data-v="${k}" ${on?'disabled':''} style="${LSYS[k]?'background:var(--ac);color:#fff':''}">${SNM[k]}</button>`;
  return `<div class="card" style="border:2px solid ${on?'#f23645':'var(--ln)'}"><b>${ic('wallet',18)} Настоящая торговля на Binance</b>
   <p class="sm mu" style="margin:4px 0 8px">${on?`<b style="color:#f23645">Работает</b> с ${dd(LV.live.since)} · ${(LV.live.sys||[]).map(k=>SNM[k]).join(', ')} · $${fmt(LV.live.amt,0)} на сделку${LV.live.lev>1?' · плечо '+LV.live.lev+'×':''}. Сервер сам открывает и закрывает сделки, стоп стоит на бирже.`:keys.length?'Выключено. Выберите системы и сумму — и запустите. Будет запрошено подтверждение.':'Ключ Binance для фьючерсов не подключён. Подключите его в «Портфеле» — после этого здесь включится кнопка запуска.'}</p>
   ${on?'':`<div class="row" style="gap:6px;flex-wrap:wrap;margin:0 0 6px">${sb('A')}${sb('C')}${sb('B')}</div>
   <div class="row"><label>Сделок в серии (на каждую систему)</label><input id="nlxN" class="bninp" type="number" min="1" max="200" value="${LN}"></div>
   <p class="hint" style="margin:0 0 6px">Рекомендуем 30 — столько хватает, чтобы честно сравнить с проверкой на истории.</p>
   <div class="row"><label>Сумма на сделку, $</label><input id="nlxA" class="bninp" type="number" min="10" max="5000" value="${LAMT}"></div>
   <div class="row"><label>Плечо</label><div class="pct" style="margin:0;flex:1">${[1,2,3].map(v=>`<button class="${LLEV===v?'on':''}" data-a="nlxl" data-v="${v}">${v}×</button>`).join('')}</div></div>
   <div id="nlxRisk"></div>
   <button class="go" data-a="nlxgo" ${keys.length?'':'disabled'} style="background:#f23645;color:#fff;font-size:16px;${keys.length?'':'opacity:.45'}">${ic('play_arrow',20)} Запустить серию на Binance</button>`}
   ${on?`<div class="row" style="gap:6px;margin:0"><button class="btn2" data-a="nlxstop" style="flex:1;padding:10px">Остановить новые входы</button><button class="btn2" data-a="nlxclose" style="flex:1;padding:10px;color:#f23645">Закрыть всё сейчас</button></div>`:''}
   ${I.length||on?['A','C','B'].filter(k=>(LV.live&&(LV.live.sys||[]).includes(k))||I.some(x=>(x.sys||'A')===k)).map(k=>{const N=(LV.live&&LV.live.n)||30,S=I.filter(x=>(x.sys||'A')===k&&(x.status==='open'||x.status==='closed')),Cs=S.filter(x=>x.status==='closed'),u=Cs.reduce((a,x)=>a+(x.pnl_usd||0),0),done=Cs.length>=N;
     return `<div style="margin-top:8px;padding:8px 10px;border-radius:8px;border:1px solid ${done?'var(--gr)':'var(--ln)'}"><b class="sm">${SNM[k]}</b> · ${done?'<span class="tag long">серия завершена</span>':`сделок ${S.length} из ${N}`}
      <div style="height:6px;border-radius:3px;background:var(--ln);margin:6px 0"><div style="height:6px;border-radius:3px;width:${Math.min(100,Math.round(S.length/N*100))}%;background:var(--ac)"></div></div>
      <div class="sm">Закрыто ${Cs.length} · в плюсе <span class="g">${Cs.filter(x=>x.pnl_usd>0).length}</span> · итог <b class="${u>=0?'g':'r'}">${USD(u)}</b>${Cs.length?` · в среднем ${pct(Cs.reduce((a,x)=>a+(x.pnl||0),0)/Cs.length)} на сделку`:''}</div></div>`}).join(''):''}
   ${I.length?`<div class="sgrid" style="margin-top:8px"><div><small>Сделок</small>${I.length} (идут ${O.length})</div><div><small>Закрыто в плюс</small><span class="g">${C.filter(x=>x.pnl_usd>0).length} из ${C.length}</span></div><div><small>Итог закрытых</small><b class="${usd>=0?'g':'r'}">${USD(usd)}</b></div></div>
   <div style="overflow-x:auto;margin-top:6px"><table class="bt7" style="width:100%;font-size:13px"><tr><th>Монета</th><th>Система</th><th>Вход</th><th>Состояние</th><th>Итог</th></tr>${I.map(x=>`<tr><td><b>${esc(x.sym)}</b></td><td>${SN(x)}</td><td>${x.ep?fp(x.ep):'—'}<div class="mu" style="font-size:11px">${x.t_open?dd(x.t_open):''}</div></td><td>${x.status==='open'?'<span class="tag long">идёт</span>':x.status==='closed'?esc(x.why||'закрыта'):`<span class="r">${esc(x.err||'ошибка')}</span>`}${(x.log||[]).length?`<div class="r" style="font-size:11px">${esc(x.log[x.log.length-1])}</div>`:''}</td><td class="${(x.pnl_usd||0)>=0?'g':'r'}">${x.status==='closed'?`<b>${USD(x.pnl_usd||0)}</b><div style="font-size:11px">${pct(x.pnl||0)}</div>`:'—'}</td></tr>`).join('')}</table></div>`:''}</div>`}
 document.addEventListener('input',e=>{if(e.target&&e.target.id==='nlxA'){LTOUCH=true;LAMT=+e.target.value||LAMT;lrisk()}});
 setTimeout(lload,5000);setInterval(()=>{if(document.visibilityState!=='hidden')lload()},300e3);
 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;if(!['nlxs','nlxl','nlxgo','nlxstop','nlxclose'].includes(a))return;e.stopPropagation();e.preventDefault();
  if(a==='nlxs'){LSYS[el.dataset.v]=LSYS[el.dataset.v]?0:1;LAMT=+((document.getElementById('nlxA')||{}).value)||LAMT;paint()}else if(a==='nlxl'){LLEV=+el.dataset.v;LAMT=+((document.getElementById('nlxA')||{}).value)||LAMT;paint()}else if(a==='nlxgo')lstart();else if(a==='nlxstop')lstop();else if(a==='nlxclose')lclose()},true);
 // первая загрузка и проверка раз в 5 минут, пока приложение открыто
 setTimeout(load,4000);setInterval(()=>{if(document.visibilityState!=='hidden')load()},300e3);
 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el||el.dataset.a!=='nlwon')return;e.stopPropagation();e.preventDefault();toggle()},true);
})();
