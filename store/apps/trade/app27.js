// ИТК Трейдинг — «Сколько это на депозит» (10.11.2026).
// Система торгует сразу всеми выбранными монетами с одного депозита: сделки по разным монетам идут одновременно и делят деньги.
// Берутся сделки честной проверки (вторая половина истории), считается доход на депозит, глубина просадки депозита и полоса убытков.
"use strict";
ICP.wallet="M21 18v1c0 1.1-.9 2-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14c1.1 0 2 .9 2 2v1h-9a2 2 0 00-2 2v8a2 2 0 002 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z";
let DEP=null,DJOB=0;
if(!UI.dpD)UI.dpD=1000;if(!UI.dpM)UI.dpM=20;if(!UI.dpL)UI.dpL=1;if(UI.dpC==null)UI.dpC=1;
async function depLoad(tf,strat,dir,k){const job=++DJOB,B=BA||{},coins=(B.coins&&B.coins.length?B.coins:BAC()),fl=B.fl||{htf:UI.btHtf,adx:UI.btAdx,maker:UI.btMaker},mkt=B.mkt||UI.mkt,spot=mkt==='spot',s=ITS.list.find(x=>x.id===strat);if(!s)return;
 const days=(typeof BA10_DAYS!=='undefined'&&BA10_DAYS[tf])||365,keep=[UI.btRR,UI.btDir,UI.btHonest,UI.btHtf,UI.btAdx,UI.btMaker],tr=[],skip=[];let tdays=0;
 DEP={busy:'Начинаю…',tf,strat,dir,k,sname:s.name,coins};depPaint();
 try{UI.btRR=k;UI.btDir=dir;UI.btHonest=1;UI.btHtf=fl.htf?1:0;UI.btAdx=fl.adx?1:0;UI.btMaker=fl.maker?1:0;
  for(let ci=0;ci<coins.length;ci++){const sym=coins[ci];if(job!==DJOB)return;DEP.busy=`Считаю ${sym} — ${ci+1} из ${coins.length}`;depPaint();
   try{const c=await histK(mkt,sym,tf,days,300);if(c.length<350)throw 0;const n=Math.round(days*86400e3/TFMS(tf)),from=Math.max(300,c.length-n);
    BTTF=tf;HTFCUR=null;if(fl.htf){try{HTFCUR=await htfLoad(mkt,sym,tf,days)}catch(_){}}
    BTCAP=[];const r=btRun(c,s,from,spot,mkt==='fut'?0.05:0.1);const cap=BTCAP;BTCAP=null;cap.forEach(x=>tr.push(Object.assign({sym},x)));
    if(r.honest)tdays=Math.max(tdays,(c[c.length-1].time-c[r.honest.mid].time)/86400)}catch(_){skip.push(sym)}}
 }finally{BTCAP=null;[UI.btRR,UI.btDir,UI.btHonest,UI.btHtf,UI.btAdx,UI.btMaker]=keep}
 if(job!==DJOB)return;tr.sort((a,b)=>a.t0-b.t0);DEP={tf,strat,dir,k,sname:s.name,coins,tr,skip,tdays,fl,mkt};depPaint()}
function depSim(D,mp,L,comp){const T=DEP.tr;let E=D,pk=D,dd=0,st=0,maxSt=0,taken=0,skipped=0,liq=0,maxOpen=0,win=0;const open=[],curve=[[T.length?T[0].t0:0,D]];
 const closeTo=t=>{open.sort((a,b)=>a.t1-b.t1);while(open.length&&open[0].t1<=t){const o=open.shift();let pnl=o.m*L*o.p/100;if(pnl<=-o.m){pnl=-o.m;liq++}E+=pnl;if(pnl>0){win++;st=0}else{st++;maxSt=Math.max(maxSt,st)}pk=Math.max(pk,E);dd=Math.max(dd,(1-E/pk)*100);curve.push([o.t1,E])}};
 for(const x of T){closeTo(x.t0);if(E<=D*0.01)break;const used=open.reduce((a,o)=>a+o.m,0),m=mp/100*(comp?E:D);if(m>E-used+1e-9||m<=0){skipped++;continue}open.push({m,p:x.p,t1:x.t1});taken++;maxOpen=Math.max(maxOpen,open.length)}
 closeTo(1e18);const days=DEP.tdays||365,ret=(E/D-1)*100,yr=E>0?(Math.pow(E/D,365/Math.max(1,days))-1)*100:-100;
 return {E,ret,yr,dd,maxSt,taken,skipped,liq,maxOpen,win,curve}}
function depCurve(cv,D){if(cv.length<2)return '';const W=320,H=90,t0=cv[0][0],t1=cv[cv.length-1][0]||t0+1,mn=Math.min(D,...cv.map(x=>x[1])),mx=Math.max(D,...cv.map(x=>x[1])),sx=t=>(t-t0)/(t1-t0||1)*W,sy=v=>H-4-(v-mn)/((mx-mn)||1)*(H-8);
 return `<svg viewBox="0 0 ${W} ${H}" style="width:100%;height:auto;margin:6px 0"><line x1="0" x2="${W}" y1="${sy(D)}" y2="${sy(D)}" stroke="var(--ln)" stroke-dasharray="3 3"/><polyline fill="none" stroke="var(--ac)" stroke-width="2" points="${cv.map(x=>sx(x[0]).toFixed(1)+','+sy(x[1]).toFixed(1)).join(' ')}"/></svg>`}
function depBody(){const P=DEP;if(!P)return '';if(P.busy)return `<p class="mu">${P.busy}</p><p class="hint">Загружаю историю каждой монеты и считаю сделки честной проверки. Не закрывайте окно.</p>`;
 if(!P.tr.length)return '<p class="err">Сделок нет — нечего считать.</p>';
 const D=+UI.dpD||1000,R=depSim(D,UI.dpM,UI.dpL,UI.dpC),m=v=>fmt(v,0)+' $',fut=P.mkt==='fut';
 const risk=R.dd>=50?'<b class="r">Слишком рискованно: депозит проседал больше чем наполовину. Уменьшите плечо или долю на сделку.</b>':R.dd>=30?'<b>Риск высокий: просадка больше 30%.</b>':'<b class="g">Просадка терпимая.</b>';
 return `<div class="wbox"><b>${P.sname}</b> <span class="mu sm">свечи ${baTfn(P.tf)} · ${baDirN(P.dir)} · ${baMode(P.k)} · ${P.coins.length-P.skip.length} монет · вслепую за ${fmt(P.tdays,0)} дн. · ${P.tr.length} сделок</span></div>
 ${row('Депозит',`<div class="in"><input type="number" id="dpD" min="10" step="any" value="${D}"><span>USDT</span></div>`)}
 <div class="row"><label>На одну сделку</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${[5,10,20,30,50,100].map(v=>`<button class="${UI.dpM===v?'on':''}" data-a="dpM" data-v="${v}">${v}%</button>`).join('')}</div></div>
 ${fut?`<div class="row"><label>Плечо</label><div class="pct" style="margin:0;flex:1">${[1,2,3,5,10].map(v=>`<button class="${UI.dpL===v?'on':''}" data-a="dpL" data-v="${v}">${v}×</button>`).join('')}</div></div>`:''}
 <div class="row"><label>Прибыль</label><div class="pct" style="margin:0;flex:1"><button class="${UI.dpC?'on':''}" data-a="dpC" data-v="1">Реинвестировать</button><button class="${!UI.dpC?'on':''}" data-a="dpC" data-v="0">Ставка от начального депозита</button></div></div>
 <p class="hint" style="margin-top:0">Каждая сделка берёт ${UI.dpM}% депозита${fut&&UI.dpL>1?`, позиция в ${UI.dpL} раза больше (плечо)`:''}. Сделки по разным монетам идут одновременно; если свободных денег не хватает — сделка пропускается.</p>
 <div class="sgrid"><div><small>Было → стало</small>${m(D)} → <b class="${R.E>=D?'g':'r'}">${m(R.E)}</b></div><div><small>Итог за ${fmt(P.tdays,0)} дн.</small><b class="${R.ret>=0?'g':'r'}">${pp(R.ret)}</b></div>
 <div><small>В пересчёте на год</small><b class="${R.yr>=0?'g':'r'}">${pp(R.yr,0)}</b></div><div><small>Худшая просадка депозита</small><b class="r">${R.dd<0.05?'0%':'−'+fmt(R.dd,1)+'%'}</b></div>
 <div><small>Сделок взято / пропущено</small>${R.taken} / ${R.skipped}</div><div><small>Прибыльных</small>${R.taken?fmt(R.win/R.taken*100,0):0}%</div>
 <div><small>Убыточных подряд, больше всего</small>${R.maxSt}</div><div><small>Открыто одновременно, больше всего</small>${R.maxOpen}</div>
 ${R.liq?`<div><small>Ликвидаций</small><b class="r">${R.liq}</b></div>`:''}</div>
 <h3 style="margin:12px 0 0">Депозит по ходу проверки</h3>${depCurve(R.curve,D)}
 <p class="sm">${risk}</p>
 <p class="hint">Расчёт по сделкам честной проверки (эти свечи система не видела), с комиссией. В жизни результат обычно хуже: проскальзывание, задержки, рынок меняется. Ориентир: доход в несколько раз больше худшей просадки — хорошо.</p>`}
function depSheet(){return sheetWrap(`${ic('wallet',20)}&nbsp;Сколько это на депозит`,`<div id="depres">${depBody()}</div><button class="btn2" style="width:100%;margin-top:8px;padding:10px" data-a="depback">Назад к тесту</button>`)}
function depPaint(){const e=$('#depres');if(e)e.innerHTML=depBody()}
const _r27=render;render=function(){if(SHEET&&SHEET[0]==='dep'){SHEET=null;_r27();SHEET=['dep'];$('#app').insertAdjacentHTML('beforeend',depSheet());return}_r27()};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='depopen'){e.stopPropagation();e.preventDefault();const [tf,st,dr,k]=v.split('|');SHEET=['dep'];render();history.pushState({sh:1},'');depLoad(tf,st,+dr,+k)}
 else if(a==='dpM'||a==='dpL'||a==='dpC'){e.stopPropagation();UI[a]=+v;saveUI();depPaint()}
 else if(a==='depback'){e.stopPropagation();DJOB++;SHEET=['ball'];render()}},true);
document.addEventListener('input',e=>{if(e.target.id==='dpD'){const v=+e.target.value;if(v>=10){UI.dpD=v;saveUI();const pos=e.target.selectionStart;depPaint();const i=$('#dpD');if(i){i.focus();try{i.setSelectionRange(pos,pos)}catch(_){}}}}});
render();
