// ИТК Трейдинг — серия «по системе» на сервере (10.11.2026).
// Из «Проверки на истории» одной кнопкой запускается серия, которая торгует ровно так, как проверяли:
// та же стратегия и свечи, то же правило стопа и выхода, то же направление (лонг/шорт), те же фильтры и та же комиссия.
// Торгует сервер — телефон можно выключить. Пока только на демо (без настоящих денег).
"use strict";
const RULEN=k=>k===5?'правила стратегии':k===4?'стоп за фигурой, тейк у уровня':k===0?'стоп + выход по сигналу':'1 к '+k;
function tdRule(s){const y=s.sys||{},f=[RULEN(y.k)];if(y.k>=0&&y.k<=3)f.push('стоп −'+fmt(s.sl,s.sl<1?2:1)+'%');if(y.dir===1)f.push('только лонг');if(y.dir===-1)f.push('только шорт');if(y.htf)f.push('по старшему тренду');if(y.adx)f.push('без боковика');if(y.hrs)f.push('активные часы');if(y.vol)f.push('крупные свечи');if(y.maker)f.push('лимитные заявки');return f.join(' · ')}
let SYS=null;// что запускаем
function sysFromBT(){const B=BT7;if(!B||!B.A||!B.s)return null;const k=B.K==null?3:B.K,sl=k<=3?(B.honest&&B.honest.sl?B.honest.sl:B.A.stop):B.A.stop;
 return {strat:B.s.id,sname:B.s.name,sym:B.sym,mkt:B.mkt,tf:B.tf,k,dir:B.dir||0,htf:UI.btHtf?1:0,adx:UI.btAdx?1:0,hrs:UI.btHrs?1:0,vol:UI.btVol?1:0,prm:B.prm||null,maker:B.mkt==='fut'&&UI.btMaker?1:0,sl:+(+sl||0).toFixed(3),n:UI.sysN||20,amt:UI.sysA||UI.bmA||25,lev:UI.sysL||1,ex:B.A.ex,cnt:B.A.n}}
function sysSheet(){const S=SYS;if(!S)return '';const fut=S.mkt==='fut',tfn=(BTF.find(x=>x[0]===S.tf)||[0,S.tf])[1];
 return sheetWrap(`${ic('cloud',20)}&nbsp;Серия по системе`,`<div class="wbox"><b>${S.sname}</b> <span class="mu sm">${S.sym} · ${fut?'фьючерсы':'спот'} · свечи ${tfn}</span>
 <div class="sm" style="margin-top:4px">${tdRule({sys:S,sl:S.sl})}</div><div class="sm mu" style="margin-top:4px">На проверке: ${S.cnt} сделок, в среднем <span class="${S.ex>=0?'g':'r'}">${pp(S.ex,2)}</span> на сделку.</div></div>
 ${S.ex<0||S.cnt<10?`<p class="err">${S.ex<0?'На проверке эта система в минусе — серия, скорее всего, тоже уйдёт в минус.':'На проверке слишком мало сделок — результат может быть любым.'}</p>`:''}
 <p class="hint">Серию ведёт сервер — точно по этим правилам, как на проверке. Телефон можно выключить. Пока только на демо-деньгах: так безопасно посмотреть, как система работает вживую.</p>
 <div class="row"><label>Сделок в серии</label><div class="pct" style="margin:0;flex:1">${[10,20,30,50,100].map(v=>`<button class="${S.n===v?'on':''}" data-a="sysn" data-v="${v}">${v}</button>`).join('')}</div></div>
 ${row('Ставка на сделку',`<div class="in"><input type="number" id="sysa" min="10" step="any" value="${S.amt}"><span>USDT</span></div>`)}
 ${fut?`<div class="row"><label>Плечо</label><div class="pct" style="margin:0;flex:1">${[1,2,3,5].map(v=>`<button class="${S.lev===v?'on':''}" data-a="sysl" data-v="${v}">${v}×</button>`).join('')}</div></div>`:''}
 <p class="err" id="syserr"></p>
 <button class="go b" data-a="sysgo">Запустить серию из ${S.n} сделок на сервере</button>
 <button class="go" data-a="sysgolive" style="margin-top:8px;background:#f23645;color:#fff">Торговать на Binance — настоящие деньги</button>
 <button class="btn2" style="width:100%;margin-top:8px;padding:10px" data-a="sysback">Назад к проверке</button>`)}
const _r26=render;render=function(){if(SHEET&&SHEET[0]==='sysser'){SHEET=null;_r26();SHEET=['sysser'];$('#app').insertAdjacentHTML('beforeend',sysSheet());return}_r26()};
async function sysGo(){const S=SYS,er=$('#syserr');if(!S)return;const a=+($('#sysa')||{}).value||S.amt;if(a<10){if(er)er.textContent='Ставка — не меньше 10 USDT';return}S.amt=a;UI.sysA=a;saveUI();
 const btn=$('[data-a=sysgo]');if(btn){btn.disabled=true;btn.textContent='Запускаю…'}
 try{const j=await tdapi('td_start',{sym:S.sym,mkt:S.mkt,tf:S.tf,strat:S.strat,prm:S.prm||(typeof PRM!=='undefined'&&PRM[S.strat])||{},amt:S.amt,lev:S.mkt==='fut'?S.lev:1,sl:S.k>=4?1:(S.sl>0?Math.min(S.sl,49):0.01),n:S.n,acc:typeof curAcc!=='undefined'?curAcc:'',sys:{k:S.k,dir:S.dir,htf:S.htf,adx:S.adx,maker:S.maker,hrs:S.hrs,vol:S.vol,sm:S.sm||1}});
  const sr=j.series;TD=[sr].concat((TD||[]).filter(x=>x.id!==sr.id));LS.set('tdc',TD);
  // закрепляем серию за вкладкой: на занятой вкладке открываем новую
  SHEET=null;UI.sym=S.sym;UI.mkt=S.mkt;UI.tf=S.tf;UI.strat=S.strat;if(typeof C!=='undefined')C=[];saveUI();
  if(typeof tabLock==='function'&&tabLock())tabAdd();const t=typeof curTab==='function'&&curTab();if(t){t.ser={k:'srv',id:sr.id};t.st=Object.assign(t.st||{},{sym:S.sym,tf:S.tf,strat:S.strat,mkt:S.mkt});LS.set('tabs',TABS)}
  render();toast(`Серия запущена на сервере: ${S.n} сделок, ${RULEN(S.k)}`);setTimeout(()=>{try{tdLoad()}catch(_){}},3000)}
 catch(e){if(er)er.textContent=e.message||'Не удалось запустить';if(btn){btn.disabled=false;btn.textContent=`Запустить серию из ${S.n} сделок на сервере`}}}
// кнопка в результатах проверки; старые подсказки «серия только 1 к 3» убираем
const _bres26=btRes;btRes=function(){let h=_bres26();const B=BT7;if(!B||!B.A||!B.A.n)return h;
 h=h.replace(/<p class="hint">Серия сделок пока торгует только по правилу 1 к 3[^<]*<\/p>/g,'').replace(/<button class="btn2"[^>]*data-a="serfrombt"[^>]*>[\s\S]*?<\/button>/,'');
 const btn=`<button class="go b" style="margin-top:10px" data-a="sysopen">${ic('cloud',18)} Запустить серию по этой системе</button><p class="hint" style="margin-top:4px">Серия будет торговать на сервере ровно по этим правилам: ${RULEN(B.K==null?3:B.K)}${B.dir===1?', только лонг':B.dir===-1?', только шорт':''}${UI.btHtf?', по старшему тренду':''}${UI.btAdx?', без боковика':''}.</p>`;
 return h.replace('<div id="coinres">',btn+'<div id="coinres">')};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='sysopen'){e.stopPropagation();SYS=sysFromBT();if(!SYS)return;SHEET=['sysser'];render();history.pushState({sh:1},'')}
 else if(a==='sysn'){e.stopPropagation();SYS.n=+v;UI.sysN=+v;saveUI();const x=$('#sysa');if(x)SYS.amt=+x.value||SYS.amt;render()}
 else if(a==='sysl'){e.stopPropagation();SYS.lev=+v;UI.sysL=+v;saveUI();const x=$('#sysa');if(x)SYS.amt=+x.value||SYS.amt;render()}
 else if(a==='sysgo'){e.stopPropagation();sysGo()}
 else if(a==='sysback'){e.stopPropagation();SHEET=['bt'];render()}},true);
// в тесте на 10 монетах «лучшая» — только с запасом: от +0,3% на сделку
render();
