// Alex Trade — №582 (14.11.2026): несколько бирж.
// Руководитель: «добавь биржи, где можно с российским паспортом, ключ на спот и на фьючерсы; когда начинаешь торговать —
// сначала выбираешь биржу (Binance, OKX…), и список монет показывает монеты именно этой биржи».
// Биржи с российским паспортом на ноябрь 2026: Bybit, OKX, BingX, Phemex, Pionex, Toobit (Bitget, MEXC, KuCoin, HTX ушли в октябре 2026).
// Сейчас: выбор биржи вверху, ключи всех бирж (спот и фьючерсы, проверяются сервером), список монет — по выбранной бирже.
// Торговля из приложения на настоящие деньги пока идёт через Binance; на других биржах — демо по ценам Binance (честно пишем об этом).
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const EX=[['binance','Binance','без паспорта РФ'],['bybit','Bybit','паспорт РФ'],['okx','OKX','паспорт РФ'],['bingx','BingX','паспорт РФ'],['phemex','Phemex','паспорт РФ'],['pionex','Pionex','паспорт РФ'],['toobit','Toobit','паспорт РФ']];
 const EXN=Object.fromEntries(EX.map(x=>[x[0],x[1]]));
 if(typeof EXCH!=='undefined'){EXCH.length=0;EX.forEach(x=>EXCH.push([x[0],x[1]]))}
 if(!UI.ex||!EXN[UI.ex])UI.ex='binance';
 const SETS={};   // ex|mkt → Set монет
 const keysOf=ex=>(window.EXKEYS||[]).filter(k=>(k.exchange||'binance')===ex);
 async function loadSet(ex,mkt){const k=ex+'|'+mkt;if(ex==='binance'||SETS[k])return;SETS[k]='…';
  try{const j=await tapi('tr_ex_coins',{ex,mkt});SETS[k]=j.coins&&j.coins.length?new Set(j.coins):null;if(!j.coins||!j.coins.length)toast(EXN[ex]+': '+(j.err||'список монет не пришёл'))}catch(e){SETS[k]=null;toast(EXN[ex]+': список монет не загрузился')}
  try{render()}catch(_){}}
 const curSet=()=>{if(UI.ex==='binance')return null;const v=SETS[UI.ex+'|'+(UI.mkt==='spot'?'spot':'fut')];return v instanceof Set?v:null};
 // ---- кнопка биржи в шапке ----
 const chip=()=>`<button class="exch69" data-a="exsw">${ic('swap',16)} ${EXN[UI.ex]} ${ic('down',14)}</button>`;
 const _hd=head;head=function(){const h=_hd();return h.replace('</header>',chip()+'</header>')};
 const _cv=coinsView;coinsView=function(){loadSet(UI.ex,UI.mkt==='spot'?'spot':'fut');let h=_cv();
  const s=curSet(),n=SETS[UI.ex+'|'+(UI.mkt==='spot'?'spot':'fut')];
  const note=UI.ex==='binance'?'':`<div class="ex69n">${n==='…'?'Загружаю монеты '+EXN[UI.ex]+'…':s?`Монеты ${EXN[UI.ex]} · ${UI.mkt==='spot'?'спот':'фьючерсы'}: ${s.size}`:'Список монет '+EXN[UI.ex]+' не загрузился — показаны все'}</div>`;
  return h.replace(/(<div class="clhd"><b[^>]*>[^<]*<\/b>)/,'$1'+chip()).replace('<div id="cll">',note+'<div id="cll">')};
 const _cb=clBody;clBody=function(){const s=curSet();if(!s||!CLL)return _cb();const all=CLL;CLL=all.filter(x=>s.has(x.s));try{return _cb()}finally{CLL=all}};
 const _cr=clRow;clRow=function(x){const s=curSet();return s&&x&&!s.has(x.s)?'':_cr(x)};   // избранные и группы — тоже только монеты этой биржи
 // ---- торговля: на других биржах пока только демо — честная плашка ----
 const _tv=termView;termView=function(){let h=_tv();if(UI.ex==='binance')return h;
  const k=keysOf(UI.ex).length;return h.replace(/(<div[^>]*id="form")/,`<div class="ex69w"><b>${EXN[UI.ex]}</b>: ${k?'ключ подключён':'ключа ещё нет'}. Монеты — с ${EXN[UI.ex]}, цены и сделки пока демо по ценам Binance. Торговлю на настоящие деньги на ${EXN[UI.ex]} подключим следующим шагом.</div>$1`)};
 // ---- окно выбора биржи ----
 function exSheet(){const m=UI.mkt==='spot'?'спот':'фьючерсы';
  return sheetWrap('Биржа для торговли',`<p class="hint">Выберите биржу — монеты и торговля пойдут по ней. Ключи можно добавить к нескольким биржам сразу, работать будет выбранная.</p>`+EX.map(x=>{const ks=keysOf(x[0]);const on=UI.ex===x[0];
   return `<div class="opt ${on?'on':''}" style="display:block"><div class="row" style="margin:0;align-items:center"><div data-a="exset" data-v="${x[0]}" style="flex:1;cursor:pointer"><b>${x[1]}</b> <span class="tag ${x[0]==='binance'?'flat':'long'}">${x[2]}</span>${on?' <span class="tag long">выбрана</span>':''}<p class="sm mu" style="margin:2px 0 0">${ks.length?ks.map(k=>`${k.kind==='spot'?'спот':'фьючерсы'} · ${esc(k.name)}${k.state==='unchecked'?' (не проверен)':' ✓'} <a data-a="exkdel" data-v="${k.id}" style="color:#f23645;cursor:pointer">удалить</a>`).join('<br>'):'ключа нет'}</p></div>
    <button class="btn2 sm" data-a="exkadd" data-v="${x[0]}">+ ключ</button></div></div>`}).join('')+`<p class="sm mu">Сейчас: ${m}. Спот и фьючерсы переключаются в «Монетах». Bitget, MEXC, KuCoin и HTX в октябре 2026 перестали работать с россиянами — их в списке нет.</p>`)}
 async function keysReload(){try{const j=await tapi('tr_keys');window.EXKEYS=j.keys||[]}catch(_){}}
 window.addEventListener('click',async e=>{const el=e.target.closest&&e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;   // на window: обработчики приложения на document тоже ловят «exsave» раньше
  if(a==='exsw'){e.stopImmediatePropagation();e.preventDefault();await keysReload();SHEET=['ex69'];render();return}
  if(a==='exset'){e.stopImmediatePropagation();UI.ex=v;if(v!=='binance'&&typeof isReal==='function'&&isReal()){const d=ACCS.find(x=>x.kind!=='bn');if(d){curAcc=d.id;toast('На '+EXN[v]+' торговля пока на демо-счёте')}}saveUI();SHEET=null;loadSet(v,UI.mkt==='spot'?'spot':'fut');render();return}
  if(a==='exkadd'){e.stopImmediatePropagation();UI.exSel=v;SHEET=['exn'];render();return}
  if(a==='exkdel'){e.stopImmediatePropagation();if(!confirm('Удалить этот ключ из приложения? На бирже его тоже лучше удалить.'))return;try{await tapi('tr_key_del',{id:v});await keysReload();toast('Ключ удалён')}catch(x){toast(x.message)}SHEET=['ex69'];render();return}
  // сохранение ключа не-Binance (Binance сохраняет прежний обработчик)
  if(a==='exsave'&&(UI.exSel||'binance')!=='binance'){e.stopImmediatePropagation();e.preventDefault();const er=$('#exerr');if(er)er.textContent='';
   const key=$('#exkey').value.trim(),sec=$('#exsec').value.trim(),pe=$('#expass'),pass=pe?pe.value.trim():'';if(!key||!sec){if(er)er.textContent='Вставьте ключ и секретный ключ';return}
   el.disabled=true;el.textContent='Проверяю ключ на бирже…';
   try{const j=await tapi('tr_key_add',{exchange:UI.exSel,kind:$('#extype').value,name:$('#exname').value.trim(),key,secret:sec,pass});$('#exsec').value='';if(pe)pe.value='';await keysReload();toast(EXN[UI.exSel]+': '+j.note);SHEET=['ex69'];render()}
   catch(x){if(er)er.textContent=x.message;el.disabled=false;el.textContent='Подключить'}return}
 },true);
 // окно ключа: пароль ключа (OKX) с id, подсказки для выбранной биржи
 const _ens=exNewSheet;exNewSheet=function(){const ex=UI.exSel||'binance';let h=_ens();
  h=h.replace(/<input type="password" autocomplete="off" placeholder="Passphrase">/,'<input id="expass" type="password" autocomplete="off" placeholder="Passphrase">');
  if(ex==='okx'&&!/id="expass"/.test(h))h=h.replace('<div class="wbox sm"><b>Как создать ключ</b>',row('Пароль ключа','<div class="in"><input id="expass" type="password" autocomplete="off" placeholder="Passphrase"></div>')+'<div class="wbox sm"><b>Как создать ключ</b>');
  if(ex==='bitget')h=h.replace(/(<div class="in"><input id="expass"[^>]*><\/div>)/,'');
  if(ex!=='binance')h=h.replace('<button class="go b" data-a="exsave">','<p class="hint">Ключ на спот и ключ на фьючерсы добавляются отдельно (на многих биржах это один ключ — тогда вставьте его дважды, выбрав тип). Сервер проверит ключ: счёт читается, а право «Вывод» выключено.</p><button class="go b" data-a="exsave">');
  return h};
 // показ окна выбора биржи через общий механизм окон
 const _rd=render;render=function(){if(SHEET&&(SHEET[0]==='ex69'||SHEET[0]==='exn')){const k=SHEET;SHEET=null;_rd();SHEET=k;$('#app').insertAdjacentHTML('beforeend',k[0]==='ex69'?exSheet():exNewSheet());return}_rd()};
 const st=document.createElement('style');st.textContent=`.exch69{display:inline-flex;align-items:center;gap:4px;border:1px solid var(--ac,#2962ff);background:#0f2a4d;color:#5eb8ff;border-radius:8px;padding:5px 9px;font-weight:600;cursor:pointer;margin-left:6px;white-space:nowrap}
 .ex69n{padding:4px 14px;font-size:12px;color:var(--mu,#787b86)}.ex69w{margin:8px 12px;padding:10px 12px;border-radius:10px;background:rgba(41,98,255,.12);border:1px solid rgba(41,98,255,.4);font-size:13px}`;document.head.appendChild(st);
 if(typeof ICP!=='undefined'&&!ICP.swap)ICP.swap="M6.99 11 3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z";
})();
