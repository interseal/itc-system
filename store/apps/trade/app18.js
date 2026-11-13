// ИТК Трейдинг — «Все сессии»: одна кнопка, на которой видны все торговые сессии сразу (10.11.2026).
// Плитки без графиков: стратегия, пара, свечи, сделка N из M, сколько в плюс и в минус, итог. Нажатие — переход на вкладку сессии.
"use strict";
ICP.dash="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z";
function allSes(){const L=[];
 ACCS.forEach(a=>{const bk=BOOK[a.id];bk.bots.filter(b=>b.series&&!b.series.hidden).forEach(b=>{const st=serStats(b,bk,a),open=bk.trades.find(t=>t.bot===b.id&&t.rq>0);
  L.push({k:'loc',id:b.id,strat:b.strat,sym:b.sym,tf:b.tf,mkt:b.mkt,n:b.series.n,t0:b.series.t0,run:b.status==='run',st,amt:b.amt,lev:b.lev,where:a.kind==='bn'?'настоящий счёт':'в телефоне',open:open?{side:open.side,u:upnl(open)}:null,inT:b.series.opened>st.n})})});
 (typeof TD!=='undefined'?TD:[]).filter(s=>!s.hidden).forEach(s=>{const st=tdStats(s),u=tdOpenPnl(s);L.push({k:'srv',id:s.id,strat:s.strat,sym:s.sym,tf:s.tf,mkt:s.mkt,n:s.n,t0:s.t0,run:s.status==='run',st,amt:s.amt,lev:s.lev,where:'на сервере',open:s.open?{side:s.open.side,u}:null,inT:!!s.open})});
 return L.sort((x,y)=>(y.run-x.run)||(y.t0-x.t0))}
function allSheet(){const L=allSes(),R=L.filter(x=>x.run),tot=L.reduce((a,x)=>a+x.st.sum,0),totR=R.reduce((a,x)=>a+x.st.sum+(x.open&&x.open.u||0),0);
 const tile=x=>{const s=ITS.list.find(q=>q.id===x.strat),pc=Math.min(100,Math.round(x.st.n/x.n*100)),cur=Math.min(x.n,x.st.n+(x.inT?1:0));
  return `<div class="stile ${x.run?'':'done'} ${x.st.sum>=0?'pos':'neg'}" data-a="sesgo" data-v="${x.k}:${x.id}">
  <div class="row" style="margin:0;align-items:center"><b style="flex:1">${x.sym} · ${(TFS.find(t=>t[0]===x.tf)||[0,x.tf])[1]}</b>${x.k==='srv'?ic('cloud',16):ic('repeat',16)}</div>
  <div class="sm mu" style="margin:2px 0 6px">${s?s.name:x.strat}</div>
  <div class="big">${x.run?`${cur} из ${x.n}`:`${x.st.n} из ${x.n}`}</div>
  <div style="height:5px;border-radius:3px;background:var(--ln);margin:6px 0"><div style="height:5px;border-radius:3px;width:${pc}%;background:var(--ac)"></div></div>
  <div class="sm"><span class="g">${ic('up',14)}${x.st.win}</span> &nbsp; <span class="r">${ic('dn',14)}${x.st.loss}</span> &nbsp; <b class="${x.st.sum>=0?'g':'r'}">${sgn(x.st.sum)} $</b></div>
  <div class="sm mu" style="margin-top:4px">${x.run?(x.open?`в сделке ${x.open.side>0?'↑':'↓'}${x.open.u!=null?` <b class="${x.open.u>=0?'g':'r'}">${sgn(x.open.u)}</b>`:''}`:'ждёт сигнал'):'завершена'} · ${x.where}</div></div>`};
 return sheetWrap(`${ic('dash',20)}&nbsp;Все сессии`,!L.length?'<p class="mu">Торговых сессий пока нет. Запустите серию сделок — она появится здесь.</p>':
 `<div class="sgrid"><div><small>Идёт сейчас</small><b>${R.length}</b></div><div><small>Итог идущих (с открытыми)</small><b class="${totR>=0?'g':'r'}">${sgn(totR)} $</b></div><div><small>Всего сессий</small>${L.length}</div><div><small>Итог всех</small><b class="${tot>=0?'g':'r'}">${sgn(tot)} $</b></div></div>
 ${R.length?`<h3 style="margin:12px 0 6px">Идут</h3><div class="sgrid2">${R.map(tile).join('')}</div>`:''}
 ${L.length>R.length?`<h3 style="margin:12px 0 6px">Завершённые</h3><div class="sgrid2">${L.filter(x=>!x.run).slice(0,20).map(tile).join('')}</div>`:''}
 <p class="hint">Нажмите на плитку — откроется вкладка этой сессии с графиком.</p>`)}
const _r18=render;render=function(){if(SHEET&&SHEET[0]==='allses'){SHEET=null;_r18();SHEET=['allses'];$('#app').insertAdjacentHTML('beforeend',allSheet());return}_r18()};
setInterval(()=>{if(SHEET&&SHEET[0]==='allses'){const sh=$('.sheet .shin'),y=sh?sh.scrollTop:0;const old=$('.sheet');if(old){old.outerHTML=allSheet();const n=$('.sheet .shin');if(n)n.scrollTop=y}}},4000);
function sesGo(k,id){SHEET=null;let t=TABS.list.find(t=>t.ser&&t.ser.id===id);
 if(t){if(t.id!==TABS.cur)tabGo(t.id);else render();return}
 const x=allSes().find(z=>z.id===id);if(!x){render();return}
 t=TABS.list.find(t=>t.st&&t.st.sym===x.sym&&t.st.tf===x.tf&&t.st.strat===x.strat&&!tabLock(t));
 if(!t){tabSave();const nid='t'+Date.now().toString(36);t={id:nid,name:'',st:Object.assign(pickUI(),{sym:x.sym,tf:x.tf,strat:x.strat,mkt:x.mkt||UI.mkt})};TABS.list.push(t)}
 if(x.run)t.ser={k,id};LS.set('tabs',TABS);if(t.id!==TABS.cur)tabGo(t.id);else render()}
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;
 if(a==='allses'){e.stopPropagation();SHEET=['allses'];render();history.pushState({sh:1},'')}
 else if(a==='sesgo'){e.stopPropagation();const [k,id]=el.dataset.v.split(':');sesGo(k,id)}},true);
// кнопка «Все сессии» — первой в полосе вкладок
const _tb18=tabsBar;tabsBar=function(){const h=_tb18(),n=allSes().filter(x=>x.run).length;return h.replace('<div class="bar tabsbar">',`<div class="bar tabsbar"><button class="chip" data-a="allses">${ic('dash',18)} Все сессии${n?' · '+n:''}</button>`)};
render();
