// ИТК Трейдинг — сделки сразу под графиком (10.11.2026):
// активная сделка (ждёт входа или открыта) — первой, ниже последние закрытые по этой паре; затем карточка серий.
"use strict";
const _tv12=termView;termView=function(){let h=_tv12();
 const md='<div class="card" id="mydeals"></div>',sb='<div id="serbox"></div>';
 if(h.includes(md)&&h.includes('<div class="stat" id="stat"></div>')){h=h.replace(md,'');const hasSb=h.includes(sb);if(hasSb)h=h.replace(sb,'');h=h.replace('<div class="stat" id="stat"></div>','<div class="stat" id="stat"></div>'+md+(hasSb?sb:''))}
 return h};
const STL=t=>{if(!t||!t.bot)return '';const [b]=botById(t.bot);return b&&b.series?`Серия ${b.series.opened} из ${b.series.n}`:b?'бот':''};
const _md12=myDeals;myDeals=function(){_md12();const e=$('#mydeals');if(!e)return;if(typeof isReal==='function'&&isReal())return;if((UI.mdTab||'act')!=='act')return;const bk=D();
 // подпись «Серия 3 из 20» у активной сделки
 e.querySelectorAll('[data-a=dclose]').forEach(btn=>{const t=bk.trades.find(x=>x.id===btn.dataset.v),lb=STL(t),td=btn.closest('tr')&&btn.closest('tr').querySelector('td small');if(lb&&td&&!td.dataset.s){td.dataset.s=1;td.insertAdjacentHTML('beforeend',' · <b>'+lb+'</b>')}});
 // последние закрытые по этой паре — сразу под активными
 const h=bk.hist.filter(x=>x.sym===UI.sym&&x.qty>0&&(!x.bot||typeof serVis!=='function'||serVis(x.bot))).slice(0,8);if(!h.length)return;
 e.insertAdjacentHTML('beforeend',`<div class="sm mu" style="margin:10px 0 4px">Последние закрытые · ${UI.sym}</div><table>${h.map(x=>{const b=x.bot?botById(x.bot)[0]:null,tm=new Date(x.t).toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'});return `<tr><td class="${x.side>0?'g':'r'}">${x.side>0?'↑':'↓'} ${x.sym}<br><small class="mu">${tm} · ${esc(x.why||'')}${b&&b.series?' · серия':''}</small></td><td>${fp(x.ep)} → ${fp(x.xp)}</td><td class="${x.pnl>=0?'g':'r'}">${sgn(x.pnl)}</td></tr>`}).join('')}</table>`)};
render();
// ---- запущенная серия видна у сделок: полоса над списком, подпись на графике, время запуска ----
const durTxt=ms=>{const m=Math.floor(ms/60e3),d=Math.floor(m/1440),h=Math.floor(m%1440/60),mm=m%60;return d?`${d} дн ${h} ч`:h?`${h} ч ${mm} мин`:`${mm} мин`};
const t0Txt=t=>new Date(t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
function serBanner(){const acc=ACC(),bk=D(),L=bk.bots.filter(b=>b.series&&b.status==='run');if(!L.length)return '';
 return L.map(b=>{const s=ITS.list.find(x=>x.id===b.strat),st=serStats(b,bk,acc),tf=(SRTF.find(x=>x[0]===b.tf)||[0,b.tf])[1],inT=b.series.opened>st.n,cur=Math.min(b.series.n,st.n+(inT?1:0)||1);
  return `<div class="serban" data-a="serscroll"><div class="row" style="margin:0;align-items:center">${ic('repeat',18)}<b style="flex:1;margin-left:6px">Идёт серия: сделка ${cur} из ${b.series.n}</b><span class="tag ${inT?'long':'flat'}">${inT?'в сделке':'ждёт сигнал'}</span></div>
  <div class="sm">${s?s.name:b.strat} · ${b.sym} · ${tf} · ставка ${fmt(b.amt,0)} $${b.lev>1?' × '+b.lev:''} · стоп −${fmt(b.sl,b.sl<1?2:1)}% · тейк +${fmt(b.tp,b.tp<1?2:1)}%</div>
  <div class="sm mu">Запущена ${t0Txt(b.series.t0)} · идёт ${durTxt(Date.now()-b.series.t0)} · закрыто ${st.n}: в плюс ${st.win}, в минус ${st.loss} · итог <b class="${st.sum>=0?'g':'r'}">${sgn(st.sum)} $</b></div></div>`}).join('')}
const _md12b=myDeals;myDeals=function(){_md12b();const e=$('#mydeals');if(!e)return;const h=serBanner();if(h)e.insertAdjacentHTML('afterbegin',h)};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a=serscroll]');if(!el)return;const s=$('#serbox');if(s)s.scrollIntoView({behavior:'smooth',block:'start'})});
setInterval(()=>{if(UI.view==='term'&&!SHEET)myDeals()},30e3);
// подпись на графике у сделки серии
const _tl12=tradeLines;tradeLines=function(){const o=_tl12();o.forEach(x=>{if(x.tid&&x.k==='ep'){const t=D().trades.find(y=>y.id===x.tid);if(t&&t.bot){const [b]=botById(t.bot);if(b&&b.series)x.a=`Серия ${b.series.opened}/${b.series.n} · `+x.a}}});return o};
// в карточке серии — когда запущена и сколько идёт
const _sh12=serHtml;serHtml=function(){let h=_sh12();if(!h)return h;D().bots.filter(b=>b.series&&!b.series.hidden).forEach(b=>{const k=`data-a="serstop" data-v="${b.id}"`,k2=`data-a="serrep" data-v="${b.id}"`;const txt=`<div class="sm mu" style="margin:4px 0 0">Запущена ${t0Txt(b.series.t0)} · ${b.status==='stop'&&b.series.done?'шла '+durTxt(b.series.done-b.series.t0):'идёт '+durTxt(Date.now()-b.series.t0)}</div>`;
 const i=h.indexOf(k)>=0?h.indexOf(k):h.indexOf(k2);if(i>0){const j=h.lastIndexOf('<div class="row" style="margin-bottom:0">',i);if(j>0)h=h.slice(0,j)+txt+h.slice(j)}});return h};
myDeals();serPaint();
// ---- живые свечи: переподключение и догрузка пропущенных (10.11.2026) ----
// Раньше поток свечей с биржи обрывался (сон телефона, смена сети, VPN) и больше не включался — свечи «замирали».
let KLT=Date.now(),KLF=false;
const _lk12=liveKl;liveKl=function(){_lk12();KLT=Date.now();const ws=klWs;if(!ws)return;const om=ws.onmessage;ws.onmessage=e=>{KLT=Date.now();om&&om(e)};
 ws.onclose=()=>{setTimeout(()=>{if(klWs===ws&&UI.view==='term')liveKl()},3000)}};
async function klFill(lim){if(KLF||UI.view!=='term'||UI.layout>1||!CS||!C.length)return;KLF=true;const key=UI.mkt+UI.sym+UI.tf;
 try{const k=await klines(UI.mkt,UI.sym,UI.tf,lim||30);if(key!==UI.mkt+UI.sym+UI.tf||!CS||!C.length)return;let closed=false;
  for(const b of k){const L=C[C.length-1];if(b.time<L.time)continue;if(b.time===L.time)C[C.length-1]=b;else{C.push(b);closed=true}CS.update(sdata([b])[0]);VS.update(volData([b])[0])}
  legend();if(closed){paintStrat();paintInd()}}catch(e){}finally{KLF=false}}
let KLR=0;
setInterval(()=>{if(UI.view!=='term'||UI.layout>1||!CS)return;const tfms=(typeof TFMS==='function'?TFMS(UI.tf):60e3);
 if(Date.now()-KLT>4e3){klFill(C.length&&Date.now()-C[C.length-1].time*1000>tfms*2?30:2);if(Date.now()-KLR>30e3){KLR=Date.now();liveKl()}}},2e3);
// ---- цены: если поток цен с биржи молчит, берём цену напрямую каждые 2 секунды ----
let PXT=0,PXB=false;const _ot12=onTick;onTick=function(a,f){PXT=Date.now();return _ot12(a,f)};
setInterval(async()=>{if(PXB||Date.now()-PXT<5e3||document.visibilityState==='hidden')return;PXB=true;
 try{const want={};want[UI.mkt+':'+UI.sym]=1;ACCS.forEach(x=>{BOOK[x.id].trades.forEach(t=>want[(t.mkt||'fut')+':'+t.sym]=1);BOOK[x.id].bots.forEach(b=>{if(b.status==='run')want[(b.mkt||'spot')+':'+b.sym]=1})});
  ['BTC','ETH'].forEach(s=>want['fut:'+s]=1);
  const by={fut:[],spot:[]};Object.keys(want).slice(0,12).forEach(k=>{const [m,s]=k.split(':');(by[m]||by.fut).push(s)});
  for(const m of ['fut','spot']){if(!by[m].length)continue;
   const rows=await Promise.all(by[m].map(s=>fetch(API[m]+'ticker/24hr?symbol='+s+'USDT').then(r=>r.ok?r.json():null).catch(()=>null)));
   const arr=rows.filter(Boolean).map(r=>({s:r.symbol,c:r.lastPrice,o:r.openPrice,q:r.quoteVolume}));const keep=PXT;if(arr.length){_ot12(arr,m==='fut');PXT=keep}}
  if(UI.view==='term'){try{stat24()}catch(e){}}}
 catch(e){}finally{PXB=false}},2e3);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&UI.view==='term'){klFill();liveKl()}});
addEventListener('online',()=>{if(UI.view==='term'){klFill();liveKl()}});
