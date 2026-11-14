// Alex Trade — №565 (14.11.2026): «Проверить на истории» у торговли на нескольких монетах.
// Руководитель: «минусовых сделок больше, чем плюсовых — может, стоп длиннее, или рынок не туда?»
// Сервер прогоняет ту же систему на тех же монетах за последние дни (как настоящая серия: минутные свечи, комиссия),
// но с разными стопами, свечами и фильтрами — и показывает, что дало бы больше.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const _pk=pkCard;
 pkCard=function(gid,L,compact){const h=_pk(gid,L,compact);if(compact)return h;
  const b=`<button class="btn2 sm" style="flex:1" data-a="pkbt" data-v="${gid}">Проверить на истории: стопы и свечи</button>`;
  const k=h.lastIndexOf('<div class="row" style="margin-bottom:0">');return k<0?h:h.slice(0,k)+`<div class="row" style="margin-bottom:6px">${b}</div>`+h.slice(k)};
 const TFN={'1m':'1 мин','3m':'3 мин','5m':'5 мин','15m':'15 мин','30m':'30 мин','1h':'1 час','2h':'2 часа','4h':'4 часа','1d':'1 день'};
 let BT=null;
 function box(html){let w=document.getElementById('pkbt');if(!w){w=document.createElement('div');w.id='pkbt';w.style.cssText='position:fixed;inset:0;z-index:70;background:rgba(0,0,0,.6);display:flex;align-items:flex-end;justify-content:center';
   w.addEventListener('click',e=>{if(e.target===w||e.target.closest('[data-x="close"]')){BT&&(BT.stop=true);w.remove()}else{const d=e.target.closest('[data-days]');if(d&&BT&&!BT.busy)run(BT.gid,+d.dataset.days)}});document.body.appendChild(w)}
  w.innerHTML=`<div style="background:var(--bg,#131722);color:var(--tx,#d1d4dc);width:100%;max-width:720px;max-height:92vh;overflow:auto;border-radius:14px 14px 0 0;padding:14px 14px 22px;box-sizing:border-box">
   <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><b style="flex:1;font-size:17px">Проверка на истории</b><button class="btn2 sm" data-x="close">Закрыть</button></div>${html}</div>`}
 const stopTxt=(k,v)=>k<=3?String(v).replace('.',',')+' %':(v==1?'обычный':'× '+String(v).replace('.',','));
 const filt=r=>[r.htf?'по тренду':'',r.maker?'лимитные':''].filter(Boolean).join(', ')||'—';
 function table(){const R=BT.rows,c=BT.cur,k=BT.k;
  const list=[...R.values()].sort((a,b)=>b.sum-a.sum);
  const isCur=r=>r.tf===c.tf&&r.stop===c.stop&&r.htf===c.htf&&r.maker===c.maker;
  const best=list[0];const cur=list.find(isCur);
  let h=`<div class="sm mu" style="margin-bottom:6px">${esc(BT.grp)} · ${BT.done} из ${BT.L.length} монет · за последние ${BT.days} ${BT.days===1?'день':BT.days<5?'дня':'дней'} · ставка и плечо — как в серии, комиссия биржи учтена</div>`;
  if(BT.err.length)h+=`<div class="sm r">Не посчитались: ${esc(BT.err.join(', '))}</div>`;
  if(cur&&best&&BT.done===BT.L.length){h+=`<div class="sm" style="margin:6px 0 10px;padding:8px;border-radius:8px;border:1px solid var(--ln)">`+(isCur(best)?`Сейчас настроено лучше всего из проверенного: <b class="${cur.sum>=0?'g':'r'}">${USD(cur.sum)}</b>.`:
   `Как сейчас: <b class="${cur.sum>=0?'g':'r'}">${USD(cur.sum)}</b> (${cur.n} сделок, в плюс ${cur.n?Math.round(cur.win/cur.n*100):0} %, комиссия ${USD(-cur.fee)}).<br>Лучше всего: <b>${TFN[best.tf]||best.tf}, стоп ${stopTxt(k,best.stop)}${best.htf||best.maker?', '+filt(best):''}</b> — <b class="${best.sum>=0?'g':'r'}">${USD(best.sum)}</b> (${best.n} сделок, в плюс ${best.n?Math.round(best.win/best.n*100):0} %).`)+
   `<br><span class="mu">Это прошлые дни, а не обещание: рынок меняется. Проверка помогает понять, где система теряет.</span></div>`}
  h+=`<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px"><tr class="mu" style="text-align:left"><th>Свеча</th><th>Стоп</th><th>Фильтр</th><th>Сделок</th><th>В плюс</th><th>Комиссия</th><th style="text-align:right">Итог</th></tr>`+
   list.map(r=>`<tr style="border-top:1px solid var(--ln);${isCur(r)?'background:rgba(41,98,255,.18)':''}"><td>${TFN[r.tf]||r.tf}${isCur(r)?' ●':''}</td><td>${stopTxt(k,r.stop)}</td><td>${filt(r)}</td><td>${r.n}</td><td>${r.n?Math.round(r.win/r.n*100)+' %':'—'}</td><td class="r">${r.fee?USD(-r.fee):'—'}</td><td style="text-align:right" class="${r.sum>=0?'g':'r'}"><b>${USD(r.sum)}</b></td></tr>`).join('')+`</table></div>
   <div class="sm mu" style="margin-top:6px">● — как торгует серия сейчас. «По тренду» — входить только по направлению старшего графика. «Лимитные» — заявки по своей цене, комиссия 0,02 % вместо 0,05 %.</div>`;
  h+=`<div class="row" style="margin-top:10px;gap:6px">${[3,5,7].map(d=>`<button class="btn2 sm" style="flex:1" data-days="${d}" ${BT.busy?'disabled':''}>${d} дн.</button>`).join('')}</div>`;
  return h}
 async function run(gid,days){const L=(TD||[]).filter(s=>s.gid===gid&&!s.hidden);if(!L.length)return;
  BT={gid,days,L,grp:L[0].grp||'Пакет серий',rows:new Map(),cur:null,k:2,done:0,err:[],busy:true,stop:false};
  box(`<div class="sm mu">Беру свечи Binance и считаю ${L.length} монет…</div>`);
  for(const s of L){if(BT.stop)return;
   box((BT.done?table():'')+`<div class="sm" style="margin-top:8px">Считаю ${s.sym} (${BT.done+1} из ${L.length})…</div>`);
   try{const j=await tdapi('td_bt',{id:s.id,days});BT.cur=j.cur;BT.k=j.k;
    j.rows.forEach(r=>{const key=r.tf+'|'+r.stop+'|'+r.htf+'|'+r.maker;const a=BT.rows.get(key)||{tf:r.tf,stop:r.stop,htf:r.htf,maker:r.maker,n:0,win:0,sum:0,fee:0};a.n+=r.n;a.win+=r.win;a.sum+=r.sum;a.fee+=r.fee;BT.rows.set(key,a)})}
   catch(e){BT.err.push(s.sym)}
   BT.done++}
  BT.busy=false;if(!BT.stop)box(BT.rows.size?table():`<div class="sm r">Не получилось посчитать: ${esc(BT.err.join(', '))}</div>`)}
 document.addEventListener('click',e=>{const b=e.target.closest('[data-a="pkbt"]');if(!b)return;e.stopPropagation();e.preventDefault();run(b.dataset.v,5)},true);
})();
