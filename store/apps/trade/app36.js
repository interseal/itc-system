// ИТК Трейдинг — «Запустить торговлю» прямо из анализа (10.11.2026).
// Нашли прибыльную систему на 10 монетах → одна кнопка: выбрать монеты (одну или все), число сделок и сумму на сделку → OK.
// На каждую монету запускается своя серия на сервере, а вместе они видны как один «пакет» с общим итогом.
"use strict";
ICP.cbon="M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z";ICP.cboff="M19 5v14H5V5h14m0-2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2z";
ICP.rocket="M12 2.5s4.5 2.04 4.5 10.5c0 2.49-1.04 5.57-1.6 7H9.1c-.56-1.43-1.6-4.51-1.6-7C7.5 4.54 12 2.5 12 2.5zm2 8.5c0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2 2-.9 2-2zm-6.31 9.52c-.48-1.23-1.52-4.17-1.67-6.87l-1.13.75c-.56.38-.89 1-.89 1.67V22l3.69-1.48zM20 22v-5.93c0-.67-.33-1.29-.89-1.66l-1.13-.75c-.15 2.69-1.2 5.64-1.67 6.87L20 22z";
let PK=null;// окно запуска: {g, sel:Set, n, amt, lev, busy, done, err:[]}
const pkKey=g=>g.tf+'|'+g.strat+'|'+(g.dir||0)+'|'+g.k;
const pkTitle=g=>`${g.sname} · ${baTfn(g.tf)} · ${baDirN(g.dir||0)} · ${baMode(g.k)}`;
function pkOpen(key,only){const B=BA;if(!B||!B.rows)return;const g=B.rows.find(x=>pkKey(x)===key);if(!g)return;pkOpenG(g,B.mkt||UI.mkt,B.fl||{},only)}
function pkOpenG(g,mkt,fl,only){const plus=g.coins.filter(x=>x.n>=3&&x.sum>0).map(x=>x.sym);
 PK={g,mkt:mkt||UI.mkt,fl:fl||{},sel:new Set(only?[only]:plus.length?plus:g.coins.map(x=>x.sym)),n:30,amt:100,lev:UI.pkL||1,dep:Math.round(accBal().v||1000),busy:'',err:[]};
 PK.n=pkRecN().n;const r=pkRec();PK.amt=r.amt>=10?r.amt:10;
 SHEET=['pk'];render();history.pushState({sh:1},'')}
// ---------- рекомендации: сколько сделок и какую ставку ----------
function pkStop(){const P=PK,g=P.g,st=g.coins.filter(x=>P.sel.has(x.sym)).map(x=>+x.stop||0).filter(v=>v>0).sort((a,b)=>a-b);
 if(g.k<=3&&st.length)return st[st.length>>1];const L=g.coins.filter(x=>P.sel.has(x.sym)&&x.n>=3);return 2.5}
function pkRecN(){const g=PK.g,d=Math.max(1,g.tdays||1),per=(g.trades||0)/Math.max(1,g.act||g.coins.length)/d*30,n=per>=45?50:per>=25?30:20,days=per>0?n/per*30:0;return {n,per,days}}
const nice=v=>v>=100?Math.floor(v/10)*10:v>=20?Math.floor(v/5)*5:Math.floor(v);
function pkRec(){const P=PK,N=Math.max(1,P.sel.size),L=P.mkt==='fut'?(P.lev||1):1,D=+P.dep||0,s=pkStop();
 const a=0.5*D/N,b=1.5*D/(L*s),c=10*D/(N*L*s),m=Math.min(a,b,c),why=m===a?`если сделки откроются сразу на всех ${N} монетах, в них будет не больше половины депозита`:m===b?`одна сделка по стопу (−${fmt(s,1)}%${L>1?' × плечо '+L:''}) потеряет не больше 1,5% депозита`:`даже если стопы сработают на всех ${N} монетах сразу, депозит потеряет не больше 10%`;
 return {amt:nice(m),raw:m,why,s,N,L,D,maxN:Math.floor(D*0.5/10)}}
function pkRecBox(){const P=PK,R=pkRec(),RN=pkRecN(),amt=+P.amt||0,N=R.N,lossOne=amt*R.L*R.s/100,lossAll=lossOne*N;
 let v;if(R.amt<10)v=`<p class="sm r" style="margin:6px 0 0"><b>Депозита мало для ${N} монет.</b> Даже минимальная ставка $10 слишком рискованна. Оставьте не больше ${Math.max(1,Math.floor(0.5*R.D/10))} монет или пополните депозит.</p>`;
 else if(amt>R.amt*1.15)v=`<p class="sm" style="margin:6px 0 0;color:var(--rd)"><b>Ставка ${USD(amt,false)} великовата для депозита ${USD(R.D,false)}.</b> Одна сделка по стопу — ${USD(-lossOne)}, все ${N} стопов разом — ${USD(-lossAll)} (${fmt(lossAll/R.D*100,0)}% депозита). Лучше <b>${USD(R.amt,false)}</b>, чтобы не улететь.</p><button class="btn2" style="margin-top:6px" data-a="pkrec">Поставить ${USD(R.amt,false)}</button>`;
 else v=`<p class="sm" style="margin:6px 0 0;color:var(--gr)"><b>Ставка ${USD(amt,false)} — в норме.</b> Одна сделка по стопу — ${USD(-lossOne)}, все ${N} стопов разом — ${USD(-lossAll)} (${fmt(lossAll/Math.max(1,R.D)*100,0)}% депозита).</p>`;
 return `<div class="wbox" style="border:1px solid var(--ac)"><b>${ic('bulb',18)} Рекомендация программы</b>
 <div class="sm" style="margin-top:4px">• <b>${RN.n} сделок</b> на каждой монете: система делает около ${fmt(RN.per,0)} сделок в месяц на одной монете${RN.days?`, серия пройдёт примерно за ${RN.days<2?'1–2 дня':dayW(RN.days)}`:''}. Меньше 20 сделок — итог будет случайным.</div>
 <div class="sm" style="margin-top:2px">• <b>Ставка до ${USD(Math.max(10,R.amt),false)}</b> на сделку: так ${R.why}. ${PK.g.k<=3?'Стоп в среднем':'Стоп у этой системы примерно'} −${fmt(R.s,1)}%.</div>${v}</div>`}
function pkSheet(){const P=PK;if(!P)return '';const g=P.g,fut=P.mkt==='fut',N=P.sel.size,amt=+P.amt||0,u=baUsd(g);
 const chip=x=>{const on=P.sel.has(x.sym),st=x.n<3?'мало сделок':x.sum>0?'в плюсе':'в минусе';return `<label data-a="pkc" data-v="${x.sym}" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid ${on?'var(--ac)':'var(--ln)'};border-radius:8px;cursor:pointer;${on?'':'opacity:.6'}">${ic(on?'cbon':'cboff',22)}<b style="flex:1">${x.sym}</b><span class="sm ${x.n<3?'mu':x.sum>0?'g':'r'}">${x.n<3?'—':USD(x.sum)}</span><span class="sm mu" style="width:78px;text-align:right">${st}</span></label>`};
 return sheetWrap(`${ic('rocket',20)}&nbsp;Запустить торговлю`,`<div class="wbox"><b>${g.sname}</b><div class="sm mu">${baTfn(g.tf)} · ${baDirN(g.dir||0)} · ${baMode(g.k)} · ${fut?'фьючерсы':'спот'}</div>
 <div class="sm" style="margin-top:4px">На проверке: ${USC(u.mo)} в месяц на ${u.n} монетах при $100 на сделку · в плюсе ${g.plus} из ${g.act}</div></div>
 <div class="row" style="align-items:center;margin-bottom:4px"><label style="flex:1">Монеты · выбрано ${N}</label><button class="btn2 sm" data-a="pkall" data-v="plus">В плюсе</button><button class="btn2 sm" data-a="pkall" data-v="all">Все</button><button class="btn2 sm" data-a="pkall" data-v="none">Ни одной</button></div>
 <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:6px;margin:0 0 4px">${g.coins.map(chip).join('')}</div>
 <p class="hint" style="margin-top:0">Галочка — монета торгуется, без галочки — нет. Сначала отмечены монеты, которые были в плюсе на проверке; «Все» — отметить все.</p>
 <div class="row"><label>Сделок на каждой монете</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${[10,20,30,50,100].map(v=>`<button class="${P.n===v?'on':''}" data-a="pkn" data-v="${v}">${v}</button>`).join('')}</div></div>
 ${row('Депозит на счёте',`<div class="in"><input type="number" id="pkd" min="10" step="any" value="${Math.round(+P.dep||0)}"><span>USDT</span></div>`)}
 ${row('Сумма на каждую сделку',`<div class="in"><input type="number" id="pka" min="10" step="any" value="${amt}"><span>USDT</span></div>`)}
 ${fut?`<div class="row"><label>Плечо</label><div class="pct" style="margin:0;flex:1">${[1,2,3,5].map(v=>`<button class="${P.lev===v?'on':''}" data-a="pkl" data-v="${v}">${v}×</button>`).join('')}</div></div>`:''}
 <div id="pkrecb">${pkRecBox()}</div><div class="wbox" id="pksum">${pkSum()}</div><div id="pkrisk">${riskBox(pkAdd())}</div>
 ${P.err.length?`<p class="err">${P.err.map(esc).join('<br>')}</p>`:''}
 ${P.busy?`<p class="mu">${P.busy}</p>`:riskCalc(pkAdd()).lvl===2&&!P.ack?`<button class="go" style="background:var(--rd)" data-a="pkack" ${N?'':'disabled'}>${ic('flag',18)} Риск очень большой — всё равно запустить?</button>`:`<button class="go b" data-a="pkgo" ${N?'':'disabled'}>${ic('play_arrow',18)} OK — запустить на ${N} ${N===1?'монете':'монетах'}</button>`}
 <p class="hint">Серии ведёт сервер точно по правилам проверки — телефон можно выключить. Пока на демо-деньгах. Все серии будут видны вместе в «Анализ → Серии».</p>`)}
function pkSum(){const P=PK,N=P.sel.size,amt=+P.amt||0,g=P.g,cs=g.coins.filter(x=>P.sel.has(x.sym)),d=Math.max(1,g.tdays||1),k=amt/100,mo=cs.reduce((a,x)=>a+x.sum,0)/d*30*k*(P.lev||1);
 return `<div class="sm">Будет запущено <b>${N}</b> ${N===1?'серия':N<5&&N>1?'серии':'серий'} по <b>${P.n}</b> сделок · ставка <b>${USD(amt,false)}</b>${P.lev>1?' × '+P.lev:''} на сделку</div>
 <div class="sm">Нужно денег: до <b>${USD(amt*N,false)}</b> (если сделки откроются сразу на всех монетах)</div>
 <div class="sm">По проверке это примерно ${USC(mo)} в месяц — не обещание, а то, что было на истории.</div>`}

// ---------- нагрузка на счёт: все серии торгуют с одного счёта ----------
// Считаем, как будто сделки открылись одновременно во всех идущих сериях плюс в новых.
function accBal(){try{if(typeof isReal==='function'&&isReal()&&typeof RS!=='undefined'&&RS){if(RS.kind==='fut')return {v:+RS.equity||+RS.wallet||+RS.avail||0,real:1};const u=(RS.balances||[]).find(b=>b.a==='USDT')||{};return {v:(+u.free||0)+(+u.locked||0),real:1}}}catch(_){}
 try{return {v:equity(D()),real:0}}catch(_){return {v:0,real:0}}}
function runExp(){const L=[];(TD||[]).filter(s=>s.status==='run'&&!s.hidden).forEach(s=>L.push({amt:+s.amt||0,lev:+s.lev||1,sl:s.sys&&s.sys.k>=4?2:(+s.sl||2),open:!!s.open}));
 try{D().bots.filter(b=>b.series&&b.status==='run').forEach(b=>L.push({amt:+b.amt||0,lev:+b.lev||1,sl:+b.sl||2,open:false}))}catch(_){}return L}
function riskCalc(add){const B=accBal(),L=runExp().concat(add||[]),M=L.reduce((a,x)=>a+x.amt,0),V=L.reduce((a,x)=>a+x.amt*x.lev,0),W=L.reduce((a,x)=>a+x.amt*x.lev*x.sl/100,0),now=L.filter(x=>x.open).length;
 const share=B.v>0?M/B.v:9,lev=B.v>0?V/B.v:9,wpct=B.v>0?W/B.v:9,lvl=share>1||lev>5||wpct>0.2?2:share>0.5||lev>2||wpct>0.08?1:0;return {B,L,M,V,W,now,share,lev,wpct,lvl}}
function riskBox(add){const R=riskCalc(add),n=R.L.length,acc=(typeof ACC==='function'&&ACC().name)||'счёт';
 const msg=[];if(R.share>1)msg.push(`денег на счёте не хватит: если сделки откроются одновременно, нужно ${USD(R.M,false)}, а на счёте ${USD(R.B.v,false)}`);
 if(R.lev>5)msg.push(`общее плечо получается ${fmt(R.lev,1)}× — это очень опасно: движение рынка на ${fmt(100/R.lev,0)}% против всех сделок съест весь счёт`);else if(R.lev>2)msg.push(`общее плечо ${fmt(R.lev,1)}× — выше спокойного уровня (до 2×)`);
 if(R.wpct>0.2)msg.push(`если все стопы сработают разом, счёт потеряет ${fmt(R.wpct*100,0)}%`);else if(R.wpct>0.08)msg.push(`если все стопы сработают разом — минус ${fmt(R.wpct*100,0)}% счёта`);
 if(R.share>0.5&&R.share<=1)msg.push(`в сделках может оказаться ${fmt(R.share*100,0)}% денег счёта — запаса почти нет`);
 const col=R.lvl===2?'var(--rd)':R.lvl===1?'#d29922':'var(--gr)',head=R.lvl===2?'Опасно':R.lvl===1?'Осторожно':'Нагрузка нормальная';
 return `<div class="wbox" style="border:2px solid ${col}"><div class="row" style="margin:0;align-items:center">${ic(R.lvl?'flag':'verified',18)}<b style="flex:1;margin-left:6px;color:${col}">${head}</b><span class="mu sm">${esc(acc)}: ${USD(R.B.v,false)}</span></div>
 <div class="sgrid" style="margin-top:6px"><div><small>${add&&add.length?'Серий вместе с новыми':'Идёт серий'}</small>${n}</div><div><small>Сейчас в сделке</small>${R.now}</div><div><small>Нужно, если все откроются</small>${USD(R.M,false)} · ${fmt(R.share*100,0)}%</div><div><small>Общее плечо</small><b style="color:${col}">${fmt(R.lev,R.lev<1?2:1)}×</b></div><div><small>Все стопы разом</small><span class="r">${USD(-R.W)}</span> · ${fmt(R.wpct*100,1)}%</div></div>
 ${msg.length?`<p class="sm" style="margin:6px 0 0;color:${col}">${(m=>m.charAt(0).toUpperCase()+m.slice(1))(msg.join('.<br>'))}.</p>`:'<p class="sm mu" style="margin:6px 0 0">Даже если сделки откроются во всех сериях сразу, денег хватает с запасом.</p>'}
 ${R.lvl===2?'<p class="sm" style="margin:4px 0 0"><b>Что сделать:</b> уменьшите сумму на сделку, плечо или число монет — или остановите часть серий.</p>':''}</div>`}
function pkAdd(){const P=PK;if(!P)return [];const g=P.g,stops=g.coins.map(x=>+x.stop||0).filter(v=>v>0).sort((a,b)=>a-b),med=stops.length?stops[stops.length>>1]:2;
 return [...P.sel].map(sym=>{const x=g.coins.find(c=>c.sym===sym)||{};return {amt:+P.amt||0,lev:P.mkt==='fut'?P.lev:1,sl:g.k>=4?2:(+x.stop||med),open:false}})}
const _r36=render;render=function(){if(SHEET&&SHEET[0]==='pk'){SHEET=null;_r36();SHEET=['pk'];$('#app').insertAdjacentHTML('beforeend',pkSheet());return}_r36()};
const pkRe=()=>{const dd=$('#pkd');if(dd&&PK)PK.dep=+dd.value||PK.dep;const sh=$('.sheet .shin'),y=sh?sh.scrollTop:0,o=$('.sheet');if(o){o.outerHTML=pkSheet();const n=$('.sheet .shin');if(n)n.scrollTop=y}};
async function pkGo(){const P=PK;if(!P||P.busy)return;const a=+($('#pka')||{}).value||P.amt;P.amt=a;
 if(a<10){P.err=['Сумма на сделку — не меньше 10 USDT'];pkRe();return}if(!P.sel.size)return;
 UI.pkA=a;UI.pkN=P.n;UI.pkL=P.lev;saveUI();
 const g=P.g,s=ITS.list.find(x=>x.id===g.strat),prm={};if(s)s.params.forEach(q=>prm[q.k]=q.v);
 const stops=g.coins.map(x=>+x.stop||0).filter(v=>v>0).sort((a,b)=>a-b),med=stops.length?stops[stops.length>>1]:1;
 const gid='pk'+Date.now().toString(36),syms=[...P.sel],grp=`${pkTitle(g)} · ${syms.length} ${syms.length===1?'монета':syms.length<5?'монеты':'монет'}`;
 const sys={k:g.k,dir:g.dir||0,htf:P.fl.htf?1:0,adx:P.fl.adx?1:0,maker:P.mkt==='fut'&&P.fl.maker?1:0,hrs:UI.btHrs?1:0,vol:UI.btVol?1:0,sm:P.fl.sm||(typeof SMul==='function'?SMul():1)};
 P.err=[];let ok=0;
 for(let i=0;i<syms.length;i++){const sym=syms[i],x=g.coins.find(c=>c.sym===sym)||{},sl=g.k>=4?1:Math.min(49,(+x.stop||med)||1);
  P.busy=`Запускаю ${i+1} из ${syms.length}: ${sym}…`;pkRe();
  try{const j=await tdapi('td_start',{sym,mkt:P.mkt,tf:g.tf,strat:g.strat,prm,amt:a,lev:P.mkt==='fut'?P.lev:1,sl,n:P.n,acc:typeof curAcc!=='undefined'?curAcc:'',sys,gid,grp});
   const sr=j.series;if(sr){sr.gid=sr.gid||gid;sr.grp=sr.grp||grp;TD=[sr].concat((TD||[]).filter(z=>z.id!==sr.id));ok++}}
  catch(e){P.err.push(sym+': '+(e.message||'не запустилась'))}}
 LS.set('tdc',TD);P.busy='';
 if(!ok){pkRe();return}
 SHEET=null;PK=P.err.length?null:null;UI.view='lab';UI.labTab='ses';saveUI();render();window.scrollTo(0,0);
 toast(`Торговля запущена: ${ok} ${ok===1?'серия':ok<5?'серии':'серий'} по ${P.n} сделок${P.err.length?' · не запустились: '+P.err.length:''}`);setTimeout(()=>{try{tdLoad()}catch(_){}},3000)}
// ---------- пакет серий: один общий вид ----------
function pkGroups(L){const m=new Map();(L||[]).filter(s=>s.gid&&!s.hidden).forEach(s=>{if(!m.has(s.gid))m.set(s.gid,[]);m.get(s.gid).push(s)});return [...m.entries()]}
function pkStats(L){let n=0,win=0,sum=0,open=0,u=0,N=0;L.forEach(s=>{const st=tdStats(s);n+=st.n;win+=st.win;sum+=st.sum;N+=s.n;if(s.open){open++;const x=tdOpenPnl(s);if(x!=null)u+=x}});
 const run=L.filter(s=>s.status==='run').length,stake=L.reduce((a,s)=>a+(+s.amt||0),0);return {n,win,loss:n-win,sum,open,u,N,run,stake}}
function pkCard(gid,L,compact){const S=pkStats(L),g=L[0],t0=Math.min(...L.map(s=>s.t0||Date.now())),pc=S.N?Math.min(100,Math.round(S.n/S.N*100)):0,tot=S.sum+S.u;
 const chips=L.slice().sort((a,b)=>tdStats(b).sum-tdStats(a).sum).map(s=>{const st=tdStats(s);return `<span data-a="sesgo" data-v="srv:${s.id}" style="cursor:pointer;padding:2px 7px;border-radius:6px;border:1px solid var(--ln)" class="${st.n?st.sum>0?'g':'r':'mu'}">${s.open?(s.open.side>0?'↑':'↓'):''}${s.sym} ${st.n?USD(st.sum):'—'}${s.status!=='run'?' ✓':''}</span>`}).join('');
 if(compact)return `<div class="serban" data-a="labses"><div class="row" style="margin:0;align-items:center">${ic('rocket',18)}<b style="flex:1;margin-left:6px">Торговля на ${L.length} монетах: ${S.run?'идёт':'завершена'}</b><b class="${tot>=0?'g':'r'}">${USD(tot)}</b></div>
 <div class="sm">${esc(g.grp||'')} · сделок ${S.n} из ${S.N} · в плюс ${S.win}, в минус ${S.loss}${S.open?` · сейчас в сделке ${S.open}`:''}</div></div>`;
 return `<div class="plan ${tot>=0?'long':'short'}" style="cursor:default"><div class="row" style="margin:0;align-items:center"><b style="flex:1">${ic('rocket',16)} ${esc(g.grp||'Пакет серий')}</b><span class="tag ${S.run?'long':'flat'}">${S.run?`идёт ${S.run} из ${L.length}`:'завершён'}</span></div>
 <div class="sm mu">На сервере · ставка ${USD(+g.amt||0,false)}${g.lev>1?' × '+g.lev:''} на сделку · по ${g.n} сделок на монету · запущен ${t0Txt(t0)}</div>
 <div style="height:6px;border-radius:3px;background:var(--ln);margin:6px 0"><div style="height:6px;border-radius:3px;width:${pc}%;background:var(--ac)"></div></div>
 <div class="sgrid"><div><small>Итог (с открытыми)</small><b style="font-size:18px" class="${tot>=0?'g':'r'}">${USD(tot)}</b></div><div><small>Закрытые сделки</small><b class="${S.sum>=0?'g':'r'}">${USD(S.sum)}</b></div><div><small>Сделок</small>${S.n} из ${S.N}</div><div><small>В плюс / в минус</small><span class="g">${S.win}</span> / <span class="r">${S.loss}</span></div><div><small>Сейчас в сделке</small>${S.open}${S.open?` · <span class="${S.u>=0?'g':'r'}">${USD(S.u)}</span>`:''}</div><div><small>От суммы ставок</small>${S.stake?`<span class="${tot>=0?'g':'r'}">${tot>=0?'+':''}${fmt(tot/S.stake*100,1)}%</span>`:'—'}</div></div>
 <div class="sm" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px">${chips}</div>
 <div class="row" style="margin-bottom:0">${S.run?`<button class="btn2 sm" style="flex:1" data-a="pkstop" data-v="${gid}">Остановить все</button>`:`<button class="btn2 sm" style="flex:1" data-a="pkhide" data-v="${gid}">Убрать</button>`}</div></div>`}
const pkSolo=fn=>{const all=TD;TD=(all||[]).filter(s=>!s.gid);try{return fn()}finally{TD=all}};
const _tb36=tdBanner;tdBanner=function(){const G=pkGroups(TD).filter(([,L])=>L.some(s=>s.status==='run'));return G.map(([id,L])=>pkCard(id,L,1)).join('')+pkSolo(()=>_tb36())};
const _tc36=tdCards;tdCards=function(){const G=pkGroups(TD);return G.slice(0,6).map(([id,L])=>pkCard(id,L)).join('')+pkSolo(()=>_tc36())};
const _as36=allSheet;allSheet=function(){const G=pkGroups(TD),h=pkSolo(()=>_as36());if(!G.length)return h;
 const box=`<h3 style="margin:4px 0 6px">${ic('rocket',18)} Торговля на нескольких монетах</h3>${G.map(([id,L])=>pkCard(id,L)).join('')}<h3 style="margin:14px 0 6px">Отдельные серии</h3>`;
 return h.replace(/(<\/h3>)/,m=>m+box).replace('<p class="mu">Торговых сессий пока нет. Запустите серию сделок — она появится здесь.</p>','<p class="mu">Отдельных серий нет.</p>')};
// во вкладке «Серии» анализа — обновлять каждые 20 секунд вместе с сервером
const _tp36=tdPaint;tdPaint=function(){_tp36();if(UI.view==='lab'&&UI.labTab==='ses'&&!SHEET){const el=$('#labses');if(el){const y=window.scrollY;el.innerHTML=unwrap(allSheet());window.scrollTo(0,y)}}};
async function pkAct(gid,act){const L=(TD||[]).filter(s=>s.gid===gid&&(act==='td_hide'||s.status==='run'));
 for(const s of L){try{const j=await tdapi(act,{id:s.id});if(j.series)TD=TD.map(z=>z.id===s.id?Object.assign(j.series,{gid:z.gid,grp:z.grp}):z)}catch(_){}}
 LS.set('tdc',TD);tdPaint();toast(act==='td_stop'?'Все серии пакета остановлены':'Пакет убран')}
// ---------- кнопки в анализе ----------
const _row36=ba10Row;ba10Row=function(g){const h=_row36(g);
 return h.replace(/(<button class="btn2 sm" style="margin-top:6px;padding:4px 10px" data-a="depopen")/,`<button class="go b sm" style="margin:6px 6px 0 0;padding:5px 12px;width:auto;display:inline-flex" data-a="pkopen" data-v="${pkKey(g)}">${ic('rocket',16)} Запустить торговлю</button>$1`)};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='pkopen'){e.stopPropagation();e.preventDefault();pkOpen(v)}
 else if(!PK){if(a==='pkstop'){e.stopPropagation();if(confirm('Остановить все серии пакета? Открытые сделки закроются по текущей цене.'))pkAct(v,'td_stop')}else if(a==='pkhide'){e.stopPropagation();pkAct(v,'td_hide')}else if(a==='labses'){e.stopPropagation();SHEET=null;UI.view='lab';UI.labTab='ses';saveUI();render()}return}
 else if(a==='pkc'){e.stopPropagation();e.preventDefault();const x=$('#pka');if(x)PK.amt=+x.value||PK.amt;PK.sel.has(v)?PK.sel.delete(v):PK.sel.add(v);pkRe()}
 else if(a==='pkall'){e.stopPropagation();const x=$('#pka');if(x)PK.amt=+x.value||PK.amt;const cs=PK.g.coins;PK.sel=new Set(v==='all'?cs.map(c=>c.sym):v==='plus'?cs.filter(c=>c.n>=3&&c.sum>0).map(c=>c.sym):[]);pkRe()}
 else if(a==='pkn'){e.stopPropagation();const x=$('#pka');if(x)PK.amt=+x.value||PK.amt;PK.n=+v;pkRe()}
 else if(a==='pkl'){e.stopPropagation();const x=$('#pka');if(x)PK.amt=+x.value||PK.amt;PK.lev=+v;pkRe()}
 else if(a==='pkgo'){e.stopPropagation();pkGo()}
 else if(a==='pkrec'){e.stopPropagation();PK.amt=Math.max(10,pkRec().amt);pkRe()}
 else if(a==='pkack'){e.stopPropagation();const x=$('#pka');if(x)PK.amt=+x.value||PK.amt;PK.ack=1;pkRe()}
 else if(a==='pkstop'){e.stopPropagation();if(confirm('Остановить все серии пакета? Открытые сделки закроются по текущей цене.'))pkAct(v,'td_stop')}
 else if(a==='pkhide'){e.stopPropagation();pkAct(v,'td_hide')}
 else if(a==='shclose'||a==='shbg'){if(SHEET&&SHEET[0]==='pk'&&(a==='shclose'||e.target===el))PK=null}},true);
document.addEventListener('input',e=>{if(PK&&e.target&&e.target.id==='pkd'){PK.dep=+e.target.value||0;const q=$('#pkrecb');if(q)q.innerHTML=pkRecBox();return}if(PK&&e.target&&e.target.id==='pka'){PK.amt=+e.target.value||0;const q=$('#pkrecb');if(q)q.innerHTML=pkRecBox();const s=$('#pksum');if(s)s.innerHTML=pkSum();const r=$('#pkrisk');if(r)r.innerHTML=riskBox(pkAdd());const b=$('[data-a=pkgo],[data-a=pkack]');if(b&&(riskCalc(pkAdd()).lvl===2)!==(b.dataset.a==='pkack')){pkRe();const x=$('#pka');if(x){x.focus();const l=x.value.length;try{x.setSelectionRange(l,l)}catch(_){}}}}});
render();
const _ss36=sysSheet;sysSheet=function(){const h=_ss36(),S=SYS;if(!S)return h;const add=[{amt:+S.amt||0,lev:S.mkt==='fut'?(+S.lev||1):1,sl:S.k>=4?2:(+S.sl||2),open:false}];
 return h.replace('<p class="err" id="syserr"></p>',`<div id="sysrisk">${riskBox(add)}</div><p class="err" id="syserr"></p>`)};
document.addEventListener('input',e=>{if(SYS&&e.target&&e.target.id==='sysa'){const r=$('#sysrisk');if(r)r.innerHTML=riskBox([{amt:+e.target.value||0,lev:SYS.mkt==='fut'?(+SYS.lev||1):1,sl:SYS.k>=4?2:(+SYS.sl||2),open:false}])}});
// во вкладке «Серии» — нагрузка на счёт сейчас
const _as36b=allSheet;allSheet=function(){const h=_as36b();if(!runExp().length)return h;const x=`<h3 style="margin:0 0 6px">${ic('wallet',18)} Нагрузка на счёт</h3>${riskBox([])}`;return h.replace(/(<\/h3>)/,m=>m+x)};
render();
