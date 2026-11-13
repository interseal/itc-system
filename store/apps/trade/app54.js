// ИТК Трейдинг — №486 (12.11.2026): «Торговать на Binance» для любой системы.
// Кнопка есть у каждой пары «монета + система» в «Вся биржа», в «Проверке системы» (рядом с запуском серии) и у каждой серии на сервере.
// Нажали — окно подтверждения: сумма, плечо, галочка «Подтверждаю. Это настоящие деньги». Без галочки кнопка «Торговать» не нажимается.
// Сервер ведёт серию как раньше, а каждое её действие повторяет на Binance: вход по рынку, стоп и тейк самой биржи, перенос стопа, выход.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 // поля ввода — тёмные, как всё приложение (раньше белое поле со светлыми цифрами было не видно)
 document.head.insertAdjacentHTML('beforeend','<style>.bninp,#bnA,#bnN,#bnL,#fbA,#fbN{width:120px;background:var(--pan2,#2a2e39)!important;color:var(--tx,#d1d4dc)!important;border:1px solid var(--ln,#363a45)!important;border-radius:8px;padding:9px 10px;font-size:16px;font-family:inherit}.bninp:focus,#bnA:focus,#bnN:focus,#fbA:focus,#fbN:focus{outline:none;border-color:var(--ac,#2962ff)!important}</style>');
 let KEYS=null,BAL=null,BALT=0;
 // №489 · баланс фьючерсного счёта Binance (обновляется не чаще раза в минуту)
 async function bal(force){if(!force&&BAL&&Date.now()-BALT<60e3)return BAL;const L=await keys();if(!L.length)return null;try{const j=await tapi('tr_state',{id:L[0].id});BAL={wallet:+j.wallet||0,avail:+j.avail||0,equity:+j.equity||0};BALT=Date.now()}catch(_){}return BAL}
 // риск по истории: worst — худшая сделка, %; dd — наибольшая просадка серии, %; nsys — сколько систем входят одновременно
 function risk(o){const B=o.bal,lev=Math.max(1,o.lev||1),amt=+o.amt||0,ns=Math.max(1,o.nsys||1),w=Math.abs(+o.worst||0),dd=Math.abs(+o.dd||0);if(!B||!(B.wallet>0))return {html:'<p class="sm mu" style="margin:6px 0 0">Баланс Binance пока не получен — риск посчитать не из чего.</p>',lvl:0,safe:0};
  const cap=B.wallet,need=amt*ns,loss1=amt*lev*w/100*ns,lossDD=amt*lev*dd/100*ns,liq=cap/(amt*lev*ns)*100,big=Math.max(loss1,lossDD);
  const safe=Math.max(0,Math.floor(0.2*cap/(Math.max(w,dd,1)/100*ns*lev)/5)*5);   // сумма, при которой худший случай по истории ≈ 20% депозита
  const safeLev=Math.max(1,Math.floor(0.2*cap/(amt*Math.max(w,dd,1)/100*ns)));
  let lvl=0,msg='';
  if(need>B.avail+0.01){lvl=3;msg=`Не хватит денег: на один сигнал нужно $${fmt(need,0)} залога, а свободно $${fmt(B.avail,0)}.`}
  else if(big>=cap*0.95||liq<=w){lvl=3;msg=`<b>Вы можете потерять весь депозит.</b> По истории худшая сделка −${fmt(w,0)}%${dd>w?`, просадка серии −${fmt(dd,0)}%`:''}: при плече ${lev}× это −$${fmt(big,0)} из $${fmt(cap,0)}. Уменьшите плечо${lev>1?` до ${Math.min(lev-1,safeLev)}×`:''} или сумму до $${fmt(safe,0)}.`}
  else if(big>=cap*0.3){lvl=2;msg=`Одна неудачная сделка, как худшая по истории, заберёт ≈ $${fmt(big,0)} — это ${fmt(big/cap*100,0)}% депозита. Безопаснее — сумма до $${fmt(safe,0)}${lev>1?' или плечо 1×':''}.`}
  else {lvl=1;msg=`Худший случай по истории — около −$${fmt(big,0)}, это ${fmt(big/cap*100,0)}% депозита.`}
  const col=lvl>=3?'#f23645':lvl===2?'#f0b90b':'var(--gr,#089981)';
  return {lvl,safe,html:`<div class="sm" style="margin:8px 0 0;padding:8px 10px;border-radius:8px;border:2px solid ${col};line-height:1.45"><b style="color:${col}">${lvl>=3?'Опасно':lvl===2?'Осторожно':'Риск в норме'}</b><br>${msg}<div class="mu" style="font-size:12px;margin-top:4px">Баланс Binance: $${fmt(cap,0)} · свободно $${fmt(B.avail,0)}${ns>1?` · одновременно входят ${ns} системы`:''}</div></div>`}}
 window.bnBal=bal;window.bnRisk=risk;
 async function keys(){try{const j=await tapi('tr_keys');KEYS=(j.keys||[]).filter(k=>k.kind==='fut')}catch(_){KEYS=KEYS||[]}return KEYS}
 const RN=k=>typeof RULEN==='function'?RULEN(+k):'';const DN=d=>+d===0?'лонг и шорт':+d===1?'только лонг':'только шорт';
 // окно подтверждения; возвращает {amt,lev} или null
 function ask(title,html,amt,showN,R0){return new Promise(res=>{const w=document.createElement('div');w.style.cssText='position:fixed;inset:0;z-index:2147483600;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:16px';
  w.innerHTML=`<div style="background:var(--pan,#1e222d);color:var(--tx,#d1d4dc);border:2px solid #f23645;border-radius:12px;max-width:460px;width:100%;padding:18px;max-height:92vh;overflow:auto">
   <div style="display:flex;gap:10px;align-items:flex-start"><svg viewBox="0 0 24 24" style="width:28px;height:28px;flex:none;fill:#f23645"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg><b style="font-size:18px">${title}</b></div>
   <div class="sm" style="margin:10px 0 0;line-height:1.5">${html}</div>
   <div class="row" style="margin:10px 0 0"><label>Сумма на сделку, $</label><input id="bnA" type="number" min="5" max="5000" value="${amt}"></div>
   ${showN?`<div class="row"><label>Сделок в серии</label><input id="bnN" type="number" min="1" max="500" value="${showN===true?30:showN}"></div><p class="hint" style="margin:0">Рекомендуем 30 — потом серию сравним с проверкой на истории.</p>`:''}
   <div class="row"><label>Плечо</label><select id="bnL"><option value="1">без плеча</option><option value="2">2×</option><option value="3">3×</option><option value="5">5×</option></select></div>
   <div id="bnRisk"><p class="sm mu" style="margin:6px 0 0">Получаю баланс Binance…</p></div>
   <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px;cursor:pointer"><input type="checkbox" id="bnOk" style="width:20px;height:20px;flex:none;margin-top:2px"><span class="sm"><b>Подтверждаю торговлю.</b> Это настоящие деньги на моём счёте Binance.</span></label>
   <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button data-x="0" class="btn2" style="padding:10px 16px">Отмена</button><button data-x="1" id="bnGo" class="btn2" disabled style="padding:10px 16px;opacity:.45;background:#f23645;color:#fff;border-color:transparent">Торговать</button></div></div>`;
  const cb=w.querySelector('#bnOk'),go=w.querySelector('#bnGo'),fA=w.querySelector('#bnA'),fL=w.querySelector('#bnL');let touched=false;
  const upd=()=>{const r=risk(Object.assign({},R0||{},{bal:BAL,amt:+fA.value||0,lev:+fL.value||1}));w.querySelector('#bnRisk').innerHTML=r.html;return r};
  fA.addEventListener('input',()=>{touched=true;upd()});fL.addEventListener('change',upd);
  bal().then(()=>{if(!touched&&BAL&&BAL.wallet>0){const r=risk(Object.assign({},R0||{},{bal:BAL,amt:+fA.value||0,lev:+fL.value||1}));if(r.lvl>=2&&r.safe>=5)fA.value=r.safe}upd()});cb.addEventListener('change',()=>{go.disabled=!cb.checked;go.style.opacity=cb.checked?1:.45});
  w.addEventListener('click',e=>{const b=e.target.closest('[data-x]');if(!b&&e.target!==w)return;if(b&&b.dataset.x==='1'&&!cb.checked)return;e.stopPropagation();e.preventDefault();
   const r={amt:+w.querySelector('#bnA').value||amt,lev:+w.querySelector('#bnL').value||1,n:+((w.querySelector('#bnN')||{}).value)||30};w.remove();res(!!b&&b.dataset.x==='1'?r:null)},true);document.body.appendChild(w)})}
 const need=()=>{toast('Сначала подключите ключ Binance для фьючерсов в «Портфеле»');return null};
 const common=(K,lev)=>`Ключ: <b>${esc(K.name||'Binance фьючерсы')}</b>.<br>Сервер будет сам открывать и закрывать сделки на Binance по правилам системы: вход по рынку, стоп${' '}и тейк ставятся на бирже сразу и работают круглосуточно. Серия идёт и на демо — итоги видны рядом.<br>Сделка может закрыться в минус. Нужен односторонний режим позиций на Binance (One-way).`;
 // 1) пара «монета + система» из «Вся биржа»
 async function fromPair(v){const [sym,tf,st,k,dir,stop,dd0]=v.split('|');const L=await keys();if(!L.length)return need();const K=L[0],nm=(FULL.top&&FULL.top.nm&&FULL.top.nm[st])||st;
  const r=await ask('Подтвердите торговлю на Binance',`<b>${esc(sym)} · ${esc(nm)} · ${typeof baTfn==='function'?baTfn(tf):tf} · ${DN(dir)} · ${RN(k)}</b><br><br>`+common(K),100,true,{worst:Math.max(+stop||0,1),dd:+dd0||0});if(!r)return;
  if(r.amt<5||r.amt>5000){toast('Сумма на сделку — от $5 до $5 000');return}const Kn=+k;
  try{const j=await tdapi('td_start',{sym,mkt:'fut',tf,strat:st,prm:(typeof PRM!=='undefined'&&PRM[st])||{},amt:r.amt,lev:r.lev,sl:Kn>=4?1:Math.min(49,Math.max(0.05,+stop||1)),n:Math.round(r.n),acc:typeof curAcc!=='undefined'?curAcc:'',live:K.id,sys:{k:Kn,dir:+dir,htf:0,adx:0,maker:0,hrs:0,vol:0,sm:1}});
   const sr=j.series;if(sr){TD=[sr].concat((TD||[]).filter(x=>x.id!==sr.id));try{LS.set('tdc',TD)}catch(_){}}toast('Торговля на Binance запущена: '+sym);if(window.srvbOpen)window.srvbOpen()}catch(e){toast('Сервер не запустил: '+(e.message||'нет связи'))}}
 // 2) «Проверка системы» → та же серия, но на Binance
 async function fromSys(){const S=window.SYS||(typeof SYS!=='undefined'?SYS:null);if(!S)return;if(S.mkt==='spot'){toast('На Binance серия пока торгует только на фьючерсах');return}
  const L=await keys();if(!L.length)return need();const K=L[0],a=+((document.getElementById('sysa')||{}).value)||S.amt||100;
  const r=await ask('Подтвердите торговлю на Binance',`<b>${esc(S.sym)} · ${esc(typeof tdName==='function'?tdName({strat:S.strat}):S.strat)} · ${typeof tdTf==='function'?tdTf(S.tf):S.tf} · ${DN(S.dir||0)} · ${RN(S.k)}</b><br><br>`+common(K),a,S.n||30,{worst:Math.max(+S.sl||0,1),dd:(typeof BT7!=='undefined'&&BT7&&BT7.A&&+BT7.A.dd)||0});if(!r)return;
  if(r.amt<5||r.amt>5000){toast('Сумма на сделку — от $5 до $5 000');return}
  try{const j=await tdapi('td_start',{sym:S.sym,mkt:S.mkt,tf:S.tf,strat:S.strat,prm:S.prm||(typeof PRM!=='undefined'&&PRM[S.strat])||{},amt:r.amt,lev:r.lev,sl:S.k>=4?1:(S.sl>0?Math.min(S.sl,49):0.01),n:Math.max(1,Math.min(500,Math.round(r.n||S.n))),acc:typeof curAcc!=='undefined'?curAcc:'',live:K.id,sys:{k:S.k,dir:S.dir,htf:S.htf,adx:S.adx,maker:S.maker,hrs:S.hrs,vol:S.vol,sm:S.sm||1}});
   const sr=j.series;if(sr){TD=[sr].concat((TD||[]).filter(x=>x.id!==sr.id));try{LS.set('tdc',TD)}catch(_){}}SHEET=null;render();toast('Торговля на Binance запущена: '+S.sym);if(window.srvbOpen)window.srvbOpen()}catch(e){toast('Сервер не запустил: '+(e.message||'нет связи'))}}
 // 3) идущая серия на сервере — включить или выключить Binance
 async function forSeries(id){const s=(TD||[]).find(x=>x.id===id);if(!s)return;
  if(s.live&&s.live.on){if(!confirm('Отключить торговлю на Binance для этой серии? Новые сделки на бирже открываться не будут, уже открытая доведётся до выхода.'))return;
   try{await tdapi('td_live',{id,on:0});toast('Binance для серии отключён')}catch(e){toast('Не удалось: '+(e.message||''))}try{tdLoad()}catch(_){}return}
  const L=await keys();if(!L.length)return need();const K=L[0];
  const r=await ask('Подтвердите торговлю на Binance',`<b>${esc(s.sym)} · ${esc(tdName(s))} · ${tdTf(s.tf)}</b>${typeof tdRule==='function'&&s.sys?' · '+tdRule(s):''}<br>Сделка, которая уже открыта на демо, на биржу не переносится — на Binance пойдёт со следующей.<br><br>`+common(K),s.amt||100,false,{worst:Math.max(+s.sl||0,1)});if(!r)return;
  try{await tdapi('td_live',{id,on:1,key:K.id,amt:r.amt,lev:r.lev});toast('Серия торгует на Binance со следующей сделки')}catch(e){toast('Не удалось: '+(e.message||''))}try{RQ.tdlAt=0}catch(_){}try{tdLoad()}catch(_){}}
 // строка о Binance в карточке серии
 window.bnSerInfo=s=>{const L=s.live;if(!L)return '';const T=L.trades||[],sum=T.reduce((a,t)=>a+(+t.pnl||0),0),p=L.pos,last=(L.log||[]).slice(-1)[0];
  return `<div class="sm" style="margin-top:6px;padding:6px 8px;border-radius:6px;border:1px solid ${L.on?'#f23645':'var(--ln)'}">${ic('wallet',14)} <b>Binance</b> ${L.on?'· торгует':'· отключён'} · $${fmt(L.amt||0,0)} на сделку${L.lev>1?' × '+L.lev:''}
   · сделок ${T.length}${T.length?` · итог <b class="${sum>=0?'g':'r'}">${sgn(sum)} $</b>`:''}${p?` · <span class="tag long">открыта ${p.side>0?'лонг':'шорт'} от ${fp(p.ep)}</span>`:''}${last?`<div class="mu" style="font-size:11px">${esc(last)}</div>`:''}</div>`};
 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;if(!['fbotlive','sysgolive','tdlive'].includes(a))return;e.stopPropagation();e.preventDefault();
  if(a==='fbotlive')fromPair(el.dataset.v);else if(a==='sysgolive')fromSys();else forSeries(el.dataset.v)},true);
})();
