// ИТК Трейдинг — «Точки входа»: программа подсказывает лучшую цену входа по стратегии; «ОК» + сумма → заявка ждёт цену
"use strict";
let PLANS=[];UI.plans=UI.plans!==false;UI.planAmt=UI.planAmt||100;
const _ps6=paintStrat;paintStrat=function(){_ps6();if(!CH||!C.length)return;const done=C.slice(0,-1);PLANS=UI.plans?PLAN.make(done,UI.strat,UI.mkt==='spot'):[];if(UI.planSel!=null&&!PLANS[UI.planSel])UI.planSel=null;const b=$('#planbox');if(b)b.innerHTML=planHtml();paintOrders()};
function planHtml(){if(!UI.plans)return '';const s=STR(),p=PX[UI.sym]||C[C.length-1].close;
 if(!PLANS.length)return `<div class="card"><h3>Точки входа · ${s.name}</h3><p class="mu sm">Сейчас нет хорошей точки входа: рядом нет сильных уровней и формирующихся фигур. Загляните позже или смените время свечи.</p></div>`;
 return `<div class="card"><div class="row" style="margin-top:0"><h3 style="margin:0;flex:1">Точки входа · ${s.name}</h3><span class="mu sm">${UI.sym} · сейчас ${fp(p)}</span></div><p class="hint" style="margin-top:0">Где лучше войти — не по текущей цене, а на уровне или после пробоя. Нажмите «ОК», укажите сумму — заявка будет ждать, пока цена дойдёт до точки.</p>
 ${PLANS.map((o,i)=>{const sl=(o.sl-o.entry)/o.entry*100,t1=(o.tps[0]-o.entry)/o.entry*100,t2=(o.tps[1]-o.entry)/o.entry*100;return `<div class="plan ${UI.planSel===i?'sel':''} ${o.side>0?'long':'short'}" data-a="plansel" data-v="${i}">
  <div class="row" style="margin:0"><b style="flex:1">${i+1}. ${o.side>0?'Купить':'Продать'} ${o.kind==='bounce'?'от уровня':'на пробое'} по ${fp(o.entry)}</b>${o.fit?`<span class="tag long">${o.fit}</span>`:''}</div>
  <div class="sm mu">${o.src} · до точки ${o.dist>=0?'+':''}${fmt(o.dist,1)}% · ${o.type==='limit'?'лимитная заявка':'заявка по триггеру'}</div>
  <div class="sgrid" style="margin-top:6px"><div><small>Стоп</small><span class="r">${fp(o.sl)} (${fmt(sl,1)}%)</span></div><div><small>Тейки 50% + 50%</small><span class="g">${fp(o.tps[0])} · ${fp(o.tps[1])}</span><br><small>${t1>=0?'+':''}${fmt(t1,1)}% · ${t2>=0?'+':''}${fmt(t2,1)}%</small></div></div>
  <div class="sm" style="margin:6px 0">${o.why} Прибыль к риску ${fmt(o.rr,1)}.</div>
  <div class="row" style="margin:0"><button class="go ${o.side>0?'b':'s'}" style="margin:0;flex:1;padding:10px" data-a="planok" data-v="${i}">ОК — поставить заявку</button></div></div>`}).join('')}
 <p class="hint">Точки рассчитаны по уровням, от которых цена уже разворачивалась, и по формирующимся фигурам. Это подсказка, а не гарантия.</p></div>`}
// линии точек входа на графике
const _fl6=formLines;formLines=function(){const o=_fl6();if(!UI.plans||!PLANS.length||(+F.qty>0))return o;PLANS.forEach((x,i)=>{o.push({k:'plan'+i,p:x.entry,c:x.side>0?'#3ddc97':'#ff6b8a',a:`${i+1}: ${x.side>0?'купить':'продать'} ${x.kind==='bounce'?'от уровня':'на пробое'}`,b:fmt(x.dist,1)+'%',fixed:1});if(UI.planSel===i){o.push({k:'plsl',p:x.sl,c:'#f23645',a:`${i+1}: стоп`,b:'',fixed:1});x.tps.forEach((t,j)=>o.push({k:'pltp'+j,p:t,c:'#089981',a:`${i+1}: тейк ${j+1}`,b:'',fixed:1}))}});return o};
// окно «ОК»: сумма и плечо
function planSheet(i){const o=PLANS[i];if(!o)return '';const fut=UI.mkt==='fut',real=typeof isReal==='function'&&isReal(),lev=fut?(+F.lev||5):1,amt=UI.planAmt,q=amt*lev/o.entry,loss=Math.abs(o.entry-o.sl)*q,prof=(Math.abs(o.tps[0]-o.entry)*q+Math.abs(o.tps[1]-o.entry)*q)/2;
 return sheetWrap(`${o.side>0?'Купить':'Продать'} ${UI.sym} по ${fp(o.entry)}`,`<div class="wbox"><b class="${o.side>0?'g':'r'}">${o.side>0?'Покупка':'Продажа'} ${o.kind==='bounce'?'от уровня':'на пробое'}</b> <span class="mu sm">${o.src}</span>
 <div class="sgrid" style="margin-top:6px"><div><small>Вход</small>${fp(o.entry)}<br><small>${o.type==='limit'?'лимит':'по триггеру'}, сейчас ${fp(PX[UI.sym])}</small></div><div><small>Стоп</small><span class="r">${fp(o.sl)}</span></div><div><small>Тейк 1 (50%)</small><span class="g">${fp(o.tps[0])}</span></div><div><small>Тейк 2 (50%)</small><span class="g">${fp(o.tps[1])}</span></div></div></div>
 ${row('Сумма',`<div class="in"><input type="number" step="any" id="pamt" value="${amt}"><span>USDT</span></div>`)}<div class="pct">${[50,100,250,500,1000].map(v=>`<button data-a="pamtset" data-v="${v}">$${v}</button>`).join('')}</div>
 ${fut?row('Плечо',`<div class="in"><select id="plev">${[1,2,3,5,10,20].map(x=>`<option ${x==lev?'selected':''} value="${x}">${x}×</option>`).join('')}</select></div>`):''}
 <div class="risk" id="prisk"><div><small>Количество</small>${fmt(q,q>=1?3:5)} ${UI.sym}</div><div><small>Убыток по стопу</small><span class="r">−${fmt(loss,2)} USDT</span></div><div><small>Прибыль по тейкам</small><span class="g">+${fmt(prof,2)} USDT</span></div><div><small>После первого тейка</small>стоп в безубыток</div></div>
 ${real?'<p class="err">Настоящий счёт: заявка уйдёт на биржу и будет ждать цену там. Стоп и тейки программа поставит на бирже сразу после входа (если приложение будет закрыто — при следующем открытии).</p>':'<p class="hint">Демо-счёт: заявка ждёт цену, пока приложение открыто.</p>'}
 <div class="err" id="perr"></div><button class="go ${o.side>0?'b':'s'}" data-a="plango" data-v="${i}">Поставить заявку</button>`)}
const _r6=render;render=function(){if(SHEET&&SHEET[0]==='plan'){const i=SHEET[1];SHEET=null;_r6();SHEET=['plan',i];$('#app').insertAdjacentHTML('beforeend',planSheet(+i));return}_r6()};
// ожидающие стопы/тейки для настоящего счёта (ставим после входа)
const PEND=LS.get('pend',[]);
async function checkPend(){if(!(typeof isReal==='function'&&isReal())||!RS)return;const acc=ACC();let ch=false;
 for(const x of PEND.filter(p=>p.acc===acc.id)){const pos=RS.kind==='fut'?(RS.positions||[]).find(p=>p.sym===x.sym+'USDT'&&(p.amt>0?1:-1)===x.side):null,have=RS.kind==='spot'?((RS.balances.find(b=>b.a===x.sym)||{free:0,locked:0})):null;
  const filled=pos||(have&&(have.free+have.locked)*(PX[x.sym]||0)>=x.qty*x.entry*0.9);const waiting=RS.orders.some(o=>o.sym===x.sym+'USDT'&&o.id===x.oid)||(RS.algo||[]).some(o=>o.sym===x.sym+'USDT'&&o.id===x.aid);
  if(filled){x.done=1;ch=true;await sendSet({sym:x.sym,dir:x.side,qty:pos?Math.abs(pos.amt):x.qty,sl:x.sl,trail:0,tps:x.tps},'Цена дошла до точки входа — стоп и тейки поставлены');event('fill',`${x.sym}: вход по плану ${fp(x.entry)} — стоп и тейки на бирже`)}
  else if(!waiting&&Date.now()-x.t>60e3){x.done=1;ch=true}}
 if(ch){const keep=PEND.filter(p=>!p.done);PEND.length=0;keep.forEach(p=>PEND.push(p));LS.set('pend',PEND)}}
const _pr6=pollRS;pollRS=async function(f){await _pr6(f);try{await checkPend()}catch(e){}};
async function planGo(i){const o=PLANS[i];if(!o)return;const amt=+$('#pamt').value,fut=UI.mkt==='fut',lev=fut?+($('#plev')||{value:1}).value:1,er=$('#perr');if(!(amt>=5))return er.textContent='Минимальная сумма — 5 USDT';UI.planAmt=amt;saveUI();
 const q=amt*lev/o.entry,real=typeof isReal==='function'&&isReal();
 if(!real){const r=createTrade({sym:UI.sym,mkt:UI.mkt,side:o.side,lev,qty:+q.toFixed(6),entry:{type:o.type==='limit'?'lim':'mkt',px:o.entry},trig:o.type==='stop'?o.entry:0,sl:{px:o.sl,trail:0,close:false,tf:UI.tf},tps:[{px:o.tps[0],pct:50,hit:0},{px:o.tps[1],pct:50,hit:0}],be:true,comment:'точка входа: '+o.src});
  if(typeof r==='string')return er.textContent=r;SHEET=null;render();toast(`Заявка ждёт цену ${fp(o.entry)} — стоп и тейки уже заданы`);return}
 if(!confirm(`НАСТОЯЩИЙ СЧЁТ\n\n${o.side>0?'КУПИТЬ':'ПРОДАТЬ'} ${fmt(q,5)} ${UI.sym} на ${amt} USDT${fut?' с плечом '+lev+'×':''}\nкогда цена дойдёт до ${o.entry} (${o.type==='limit'?'лимит':'триггер'}).\nСтоп ${o.sl}, тейки ${o.tps.join(' и ')}.\n\nОтправить на биржу?`))return;
 const btn=$('[data-a=plango]');btn.disabled=true;btn.textContent='Отправляю на биржу…';
 try{const r=await tapi('tr_order',{id:ACC().key,symbol:UI.sym,side:o.side>0?'BUY':'SELL',type:o.type==='limit'?'LIMIT':'MARKET',qty:q,price:o.type==='limit'?o.entry:0,trigger:o.type==='stop'?o.entry:0,lev:fut?lev:0});
  PEND.push({acc:ACC().id,sym:UI.sym,side:o.side,entry:o.entry,qty:q,sl:o.sl,tps:[{px:o.tps[0],pct:50},{px:o.tps[1],pct:50}],oid:r.order?r.order.id:'',aid:r.algo?String(r.algo):'',t:Date.now()});LS.set('pend',PEND);
  SHEET=null;render();toast(`Заявка на бирже ждёт цену ${fp(o.entry)}`);pollRS(true)}catch(e){er.textContent=e.message;btn.disabled=false;btn.textContent='Поставить заявку'}}
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='planok'){e.stopPropagation();SHEET=['plan',v];render();history.pushState({sh:1},'')}
 else if(a==='plansel'){UI.planSel=UI.planSel===+v?null:+v;$('#planbox').innerHTML=planHtml();paintOrders()}
 else if(a==='pamtset'){$('#pamt').value=v;$('#pamt').dispatchEvent(new Event('input',{bubbles:true}))}
 else if(a==='plango')planGo(+v)
 else if(a==='plans'){UI.plans=!UI.plans;saveUI();render()}},true);
document.addEventListener('input',e=>{if(e.target.id==='pamt'||e.target.id==='plev'){const i=+SHEET[1],sc=$('.sheet .shin').scrollTop,val=e.target.value,id=e.target.id;UI.planAmt=+$('#pamt').value||UI.planAmt;const lv=$('#plev')?$('#plev').value:null;if(lv)F.lev=+lv;$('.sheet').outerHTML=planSheet(i);$('.sheet .shin').scrollTop=sc;const f=$('#'+id);if(f){f.value=val;if(id==='pamt'){f.focus();try{f.setSelectionRange(val.length,val.length)}catch(_){}}}}});
document.addEventListener('change',e=>{if(e.target.id==='plev'){F.lev=+e.target.value;const i=+SHEET[1];$('.sheet').outerHTML=planSheet(i)}});
const _tv6=termView;termView=function(){let h=_tv6();h=h.replace('<div id="patbox"></div>','<div id="planbox"></div><div id="patbox"></div>');h=h.replace(/(<button class="chip[^"]*" data-a="pat">)/,`<button class="chip ${UI.plans?'on':''}" data-a="plans">${ic('target',18)} Точки входа</button>$1`);return h};
ICP.target="M12 2a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16zm0-14a6 6 0 100 12 6 6 0 000-12zm0 10a4 4 0 110-8 4 4 0 010 8zm0-6a2 2 0 100 4 2 2 0 000-4z";
render();
