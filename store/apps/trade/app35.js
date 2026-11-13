// ИТК Трейдинг — главное в долларах (10.11.2026).
// Всю прибыль показываем просто: ставка $100 на каждую сделку, без плеча. 1% на сделку = $1.
// Наверху тестов — короткий вывод: какая система лучшая и сколько она дала бы в месяц; подробности — ниже, свёрнуты.
"use strict";
ICP.flag="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z";ICP.play_arrow="M8 5v14l11-7z";
const USD=(v,s)=>{const a=Math.abs(v),t=a>=100?Math.round(a).toLocaleString('ru-RU'):a.toLocaleString('ru-RU',{maximumFractionDigits:a<10?2:1});return (v<0?'−':s===false?'':'+')+'$'+t};
const USC=v=>`<span class="${v>=0?'g':'r'}">${USD(v)}</span>`;
function baUsd(g){const cs=g.coins||[],tot=cs.reduce((a,x)=>a+(x.sum||0),0),d=Math.max(1,g.tdays||1);
 return {tot,d,mo:tot/d*30,trM:(g.trades||0)/d*30,per:g.trades?tot/g.trades:0,n:cs.length}}
const dayW=n=>{n=Math.round(n);const m=n%10,h=n%100;return n+' '+(m===1&&h!==11?'день':m>=2&&m<=4&&(h<12||h>14)?'дня':'дней')};
// строка системы — сначала деньги
ba10Row=function(g){const u=baUsd(g),share=g.act?g.plus/g.act:0,cls=share>=0.7?'g':share>=0.5?'':'r',many=g.coins.length>12;
 return `<div class="plan ${u.tot>0?'long':'short'}" data-a="bago" data-v="${g.tf}|${g.days}|${g.strat}|${g.k}|${g.dir||0}" style="padding:8px 10px">
 <div class="row" style="margin:0;align-items:center"><b style="flex:1">${g.sname}</b><b style="font-size:17px">${USC(u.mo)}<span class="mu sm" style="font-weight:400"> в месяц</span></b></div>
 <div class="sm mu" style="margin-top:2px">Свечи ${baTfn(g.tf)} · <b>${baDirN(g.dir||0)}</b> · ${baMode(g.k)}</div>
 <div class="sm" style="margin-top:2px">За ${dayW(u.d)} проверки вслепую: ${USC(u.tot)} на ${u.n} монетах · ${g.trades} сделок (≈ ${fmt(u.trM,0)} в месяц) · в среднем ${USC(u.per)} со сделки · <b class="${cls}">в плюсе ${g.plus} из ${g.act} монет</b></div>
 ${!many?`<div class="sm" style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px">${g.coins.map(x=>`<span style="padding:1px 6px;border-radius:6px;border:1px solid var(--ln)" class="${x.n<3?'mu':x.sum>0?'g':'r'}">${x.sym} ${x.n<3?'—':USD(x.sum)}</span>`).join('')}</div>`
  :(()=>{const L=g.coins.filter(x=>x.n>=3&&x.sum<=0);return L.length?`<div class="sm" style="margin-top:4px">В минусе: <span class="r">${L.slice(0,20).map(x=>x.sym+' '+USD(x.sum)).join(', ')}${L.length>20?' и ещё '+(L.length-20):''}</span></div>`:''})()}
 <button class="btn2 sm" style="margin-top:6px;padding:4px 10px" data-a="depopen" data-v="${g.tf}|${g.strat}|${g.dir||0}|${g.k}">${ic('wallet',16)} Сколько это на депозит</button></div>`};
// главный вывод
function baKey(B){const R=B.rows||[],N=(B.coins||[]).length||10;
 const ok=R.filter(g=>g.act&&g.plus/g.act>=0.6&&g.trades>=20).map(g=>Object.assign({u:baUsd(g)},{g})).filter(x=>x.u.mo>0);
 ok.sort((a,b)=>b.u.mo-a.u.mo);const best=ok[0];
 if(!best)return `<div class="card" style="border:2px solid var(--rd)"><h3 style="margin:0 0 6px">${ic('flag',20)} Вывод</h3>
  <p style="margin:0">Ни одна система не зарабатывает на большинстве монет. Со ставкой $100 на сделку на этих монетах и свечах стабильной прибыли не будет — деньги сюда не ставим.</p>
  <p class="hint">Попробуйте другие свечи, «Стоп и тейк длиннее» или другую группу монет.</p></div>`;
 const g=best.g,u=best.u,good=g.coins.filter(x=>x.n>=3&&x.sum>0).sort((a,b)=>b.sum-a.sum),bad=g.coins.filter(x=>x.n>=3&&x.sum<=0).sort((a,b)=>a.sum-b.sum);
 const short=u.d<45,stable=g.act&&g.stable/g.act>=0.5;
 const verdict=short?'Проверка шла мало дней — это ещё не доказательство. Сначала серия на демо.':stable?'Результат устойчивый — можно запускать серию на демо, а потом на небольшие деньги.':'Прибыль есть, но идёт неровно — сначала серия на демо.';
 const alt=ok.slice(1,4).map(x=>`<div class="row" style="margin:4px 0 0;align-items:center;cursor:pointer" data-a="bago" data-v="${x.g.tf}|${x.g.days}|${x.g.strat}|${x.g.k}|${x.g.dir||0}"><span style="flex:1">${x.g.sname} <span class="mu sm">· ${baTfn(x.g.tf)} · ${baDirN(x.g.dir||0)} · ${baMode(x.g.k)}</span></span><b>${USC(x.u.mo)}<span class="mu sm" style="font-weight:400"> в мес.</span></b></div>`).join('');
 return `<div class="card" style="border:2px solid var(--gr)"><h3 style="margin:0 0 8px">${ic('flag',20)} Вывод</h3>
 <div style="font-size:15px">Лучшая система: <b>${g.sname}</b><br><span class="mu sm">Свечи ${baTfn(g.tf)} · ${baDirN(g.dir||0)} · ${baMode(g.k)}</span></div>
 <div class="sgrid" style="margin-top:10px"><div><small>Прибыль в месяц</small><b style="font-size:20px">${USC(u.mo)}</b></div><div><small>За ${dayW(u.d)} проверки</small><b>${USC(u.tot)}</b></div><div><small>Сделок в месяц</small><b>≈ ${fmt(u.trM,0)}</b></div><div><small>Со сделки в среднем</small><b>${USC(u.per)}</b></div><div><small>Монет в плюсе</small><b>${g.plus} из ${g.act}</b></div><div><small>Нужно денег</small><b>до ${USD(100*u.n,false)}</b></div></div>
 <p class="sm" style="margin:8px 0 0">Считаем просто: на каждую сделку ставим $100, без плеча, комиссия биржи уже вычтена. «Нужно денег» — если сделки открыты сразу на всех ${u.n} монетах.</p>
 ${good.length?`<p class="sm" style="margin:6px 0 0">Больше всего заработали: <span class="g">${good.slice(0,5).map(x=>x.sym+' '+USD(x.sum)).join(', ')}</span></p>`:''}
 ${bad.length?`<p class="sm" style="margin:4px 0 0">В минусе: <span class="r">${bad.slice(0,5).map(x=>x.sym+' '+USD(x.sum)).join(', ')}</span> — эти монеты для системы лучше убрать.</p>`:''}
 <p style="margin:8px 0 0"><b>${verdict}</b></p><p class="sm mu" style="margin:2px 0 0">«Запустить торговлю» — выберите монеты, число сделок и сумму, и серии сразу пойдут на всех выбранных монетах.</p>
 <div class="row" style="gap:8px;margin-top:8px;flex-wrap:wrap"><button class="go b" style="flex:1;margin:0" data-a="pkopen" data-v="${g.tf}|${g.strat}|${g.dir||0}|${g.k}">${ic('play_arrow',18)} Запустить торговлю</button><button class="btn2" style="padding:10px" data-a="bago" data-v="${g.tf}|${g.days}|${g.strat}|${g.k}|${g.dir||0}">${ic('history',18)} Проверка</button><button class="btn2" style="padding:10px" data-a="depopen" data-v="${g.tf}|${g.strat}|${g.dir||0}|${g.k}">${ic('wallet',18)} На депозит</button></div>
 ${alt?`<h4 style="margin:12px 0 2px">Следующие по прибыли</h4>${alt}`:''}</div>`}
const _bl35=ba10List;ba10List=function(){const B=BA;if(!B||B.busy)return _bl35();
 const h=_bl35(),i=h.indexOf('<h3 style="margin:14px 0 4px"'),j=h.indexOf('<p class="hint">Для каждой системы правила одни и те же');
 if(i<0||j<0)return baKey(B)+h;
 const head=h.slice(0,i).replace(/<div class="serban"[\s\S]*?<\/div><\/div>/,'').replace(/<p class="err">Системы, которая честно[\s\S]*?<\/p>/,'');
 const R=B.rows||[];
 return head+baKey(B)+`<details style="margin-top:10px"${B.det?' open':''} ontoggle="BA&&(BA.det=this.open)"><summary class="btn2" style="padding:10px;cursor:pointer;list-style:none;text-align:center">${ic('list',18)} Подробно по всем системам (${R.length})</summary>${h.slice(i,j)}</details>`+h.slice(j)};
// одиночная проверка: деньги наверху
const _bres35=btRes;btRes=function(){let h=_bres35();const B=BT7;if(!B||!B.A||B.busy||B.err||!B.A.n)return h;
 const A=B.A,tot=A.ex*A.n,t0=B.honest&&B.honest.tm?B.honest.tm:B.t0,d=Math.max(1,((B.t1||0)-(t0||0))/86400),mo=tot/d*30;
 const box=`<div class="card" style="border:2px solid ${tot>0?'var(--gr)':'var(--rd)'};margin-top:8px"><div class="row" style="margin:0;align-items:center"><b style="flex:1">Ставка $100 на сделку, без плеча</b><b style="font-size:19px">${USC(mo)}<span class="mu sm" style="font-weight:400"> в месяц</span></b></div>
 <div class="sm" style="margin-top:4px">${B.honest?'Вслепую за ':'За '}${dayW(d)}: ${USC(tot)} · ${A.n} сделок · в среднем ${USC(A.ex)} со сделки · комиссия вычтена.</div>
 <div class="sm" style="margin-top:4px"><b>${tot<=0?'Вывод: система в минусе — деньги не ставим.':A.n<20?'Вывод: сделок мало — результат может быть случайным.':'Вывод: система в плюсе — можно проверить серией на демо.'}</b></div></div>`;
 const k=h.indexOf('</div>');return k>0?h.slice(0,k+6)+box+h.slice(k+6):box+h};
render();
