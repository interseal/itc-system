// ИТК Трейдинг — подсветка сигналов стратегии на графике и предложение сделки: «Согласиться» / «Не согласен»
"use strict";
let ZS=null,PROP=null;const DECL=LS.get('decl',{});
// подписи на стрелках
const _ps4=paintStrat;paintStrat=function(){_ps4();if(!CH||!C.length||!CS)return;const s=STR(),p=params(s),done=C.slice(0,-1),sig=s.run(done,p);
 // стрелки с подписями «Купить / Продать / Выход»
 if(UI.arrows){const M=sig.map(x=>({time:done[x.i].time,position:x.t==='long'?'belowBar':'aboveBar',color:x.t==='long'?'#089981':x.t==='short'?'#f23645':'#b0bec5',shape:x.t==='long'?'arrowUp':x.t==='short'?'arrowDown':'circle',text:x.t==='long'?'Купить':x.t==='short'?'Продать':'Выход'})).concat(fillMarkers()).sort((a,b)=>a.time-b.time);CS.setMarkers(M)}
 // подсветка участков: зелёный — стратегия в покупке, красный — в продаже
 try{if(ZS)CH.removeSeries(ZS)}catch(e){}ZS=null;
 if(UI.arrows&&UI.zones!==false){ZS=CH.addHistogramSeries({priceScaleId:'zone',priceLineVisible:false,lastValueVisible:false,base:0});CH.priceScale('zone').applyOptions({scaleMargins:{top:0,bottom:0},visible:false});
  let pos=0,k=0;const z=done.map((c,i)=>{while(k<sig.length&&sig[k].i===i){pos=sig[k].t==='long'?1:sig[k].t==='short'?(UI.mkt==='spot'?0:-1):0;k++}return {time:c.time,value:pos?1:0,color:pos>0?'rgba(8,153,129,0.05)':pos<0?'rgba(242,54,69,0.05)':'rgba(0,0,0,0)'}});
  const L=C[C.length-1];z.push({time:L.time,value:pos?1:0,color:pos>0?'rgba(8,153,129,0.05)':pos<0?'rgba(242,54,69,0.05)':'rgba(0,0,0,0)'});ZS.setData(z)}
 makeProposal(s,p,done,sig)};
// предложение по последнему сигналу
function makeProposal(s,p,done,sig){PROP=null;const box=$('#propbox');const last=sig[sig.length-1];const price=PX[UI.sym]||C[C.length-1].close;
 if(!last||!UI.arrows){if(box)box.innerHTML='';paintOrders();return}
 const ago=done.length-1-last.i,key=UI.mkt+UI.sym+UI.tf+s.id+':'+done[last.i].time;
 if(last.t==='exit'){PROP={exit:1,key,ago,s};}
 else if(last.t==='short'&&UI.mkt==='spot'){PROP={exit:1,key,ago,s,spot:1}}
 else{const side=last.t==='long'?1:-1,atr=ITS.ind.atr(done,14),a=atr[atr.length-1]||price*0.01,sl=price-side*1.5*a,t1=price+side*1.5*a,t2=price+side*3*a;
  const moved=(price-done[last.i].close)/done[last.i].close*100*side;PROP={side,key,ago,s,ep:price,sl:+sl.toFixed(pd(price)),tps:[+t1.toFixed(pd(price)),+t2.toFixed(pd(price))],moved,sigPx:done[last.i].close}}
 if(DECL[key])PROP.declined=1;if(box)box.innerHTML=propHtml();paintOrders()}
function propHtml(){const P=PROP;if(!P)return '';const tf=(TFS.find(t=>t[0]===UI.tf)||[0,UI.tf])[1],when=P.ago===0?'на последней закрытой свече':P.ago+' '+(P.ago%10===1&&P.ago%100!==11?'свеча':P.ago%10>=2&&P.ago%10<=4&&(P.ago%100<10||P.ago%100>=20)?'свечи':'свечей')+' назад';
 if(P.exit)return `<div class="prop flat"><div class="row" style="margin:0"><b style="flex:1">${ic('bell',18)} ${P.s.name}: ${P.spot?'сигнал на продажу':'сигнал «выход»'}</b><span class="mu sm">${tf} · ${when}</span></div><p class="sm mu" style="margin:6px 0 0">Стратегия сейчас вне рынка — новых входов не предлагает. ${P.spot?'На споте продажа — это закрытие купленной монеты.':'Открытые сделки по этой паре стратегия советует закрыть.'}</p></div>`;
 const rr=Math.abs(P.tps[1]-P.ep)/Math.abs(P.ep-P.sl),sl=(P.sl-P.ep)/P.ep*100,t1=(P.tps[0]-P.ep)/P.ep*100,t2=(P.tps[1]-P.ep)/P.ep*100,late=P.ago>3||P.moved>2;
 return `<div class="prop ${P.side>0?'long':'short'}${P.declined?' dim':''}"><div class="row" style="margin:0"><b style="flex:1">${ic('bell',18)} ${P.s.name}: ${P.side>0?'КУПИТЬ':'ПРОДАТЬ'} ${UI.sym}</b><span class="mu sm">${tf} · ${when}</span></div>
 <div class="sgrid" style="margin-top:8px"><div><small>Вход сейчас</small>${fp(P.ep)}</div><div><small>Сигнал был по</small>${fp(P.sigPx)} <small class="${P.moved>=0?'g':'r'}">${P.moved>=0?'+':''}${fmt(P.moved,1)}%</small></div><div><small>Стоп-лосс</small><span class="r">${fp(P.sl)} (${fmt(sl,1)}%)</span></div><div><small>Тейки 50% + 50%</small><span class="g">${fp(P.tps[0])} (${t1>=0?'+':''}${fmt(t1,1)}%) · ${fp(P.tps[1])}</span></div></div>
 <p class="sm mu" style="margin:6px 0">Стоп и тейки рассчитаны по средней волатильности (ATR): стоп 1,5 ATR, тейки 1,5 и 3 ATR. Прибыль к риску ${fmt(rr,1)}.${late?' <span class="r">Сигнал не свежий — цена уже ушла, вход хуже.</span>':''}</p>
 ${P.declined?`<div class="row"><span class="mu sm" style="flex:1">Вы отклонили этот сигнал.</span><button class="btn2 sm" data-a="propundo">Вернуть</button></div>`:`<div class="row"><button class="go ${P.side>0?'b':'s'}" style="margin:0;flex:1" data-a="propyes">Согласиться</button><button class="btn2" style="flex:1;padding:12px" data-a="propno">Не согласен</button></div>
 <p class="hint" style="margin-bottom:0">«Согласиться» заполнит заявку ниже — сделка откроется только после вашей кнопки «Купить» или «Продать».</p>`}</div>`}
// линии предложения на графике (пунктир, до ответа)
const _fl4=formLines;formLines=function(){const o=_fl4();const P=PROP;if(!P||P.exit||P.declined||!UI.arrows||(+F.qty>0))return o;
 o.push({k:'pe',p:P.ep,c:'#9e9e9e',a:'Стратегия: '+(P.side>0?'купить':'продать'),b:'',fixed:1});o.push({k:'ps',p:P.sl,c:'#f23645',a:'Стратегия: стоп',b:fmt((P.sl-P.ep)/P.ep*100,1)+'%',fixed:1});P.tps.forEach((t,i)=>o.push({k:'pt'+i,p:t,c:'#089981',a:'Стратегия: тейк '+(i+1),b:'+'+fmt(Math.abs(t-P.ep)/P.ep*100,1)+'%',fixed:1}));return o};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;
 if(a==='propyes'&&PROP&&!PROP.exit){const P=PROP;F.side=P.side;F.et='mkt';F.trigOn=false;F.slOn=true;F.trOn=false;F.slClose=false;F.sl=String(P.sl);F.tps=[{px:String(P.tps[0]),pct:50},{px:String(P.tps[1]),pct:50}];F.be=true;F.comment='по сигналу: '+P.s.name;
  const free=typeof isReal==='function'&&isReal()&&RS?(RS.kind==='fut'?RS.avail:((RS.balances.find(b=>b.a==='USDT')||{free:0}).free)):D().bal,lev=UI.mkt==='fut'?+F.lev:1;const q=free*0.1*lev/P.ep;F.qty=q>=1?q.toFixed(3):q.toFixed(5);saveUI();render();setTimeout(()=>{const f=$('#form');if(f)f.scrollIntoView({behavior:'smooth',block:'start'})},100);toast('Заявка заполнена по сигналу: 10% баланса, стоп и два тейка. Проверьте и нажмите кнопку.')}
 else if(a==='propno'&&PROP){DECL[PROP.key]=Date.now();const ks=Object.keys(DECL);if(ks.length>300)ks.slice(0,ks.length-300).forEach(k=>delete DECL[k]);LS.set('decl',DECL);PROP.declined=1;$('#propbox').innerHTML=propHtml();paintOrders();toast('Сигнал отклонён')}
 else if(a==='propundo'&&PROP){delete DECL[PROP.key];LS.set('decl',DECL);PROP.declined=0;$('#propbox').innerHTML=propHtml();paintOrders()}
 else if(a==='zones'){UI.zones=UI.zones===false;saveUI();paintStrat()}});
// место для предложения — над панелью заявки; переключатель подсветки участков
const _tv4=termView;termView=function(){let h=_tv4();h=h.replace('<div class="card" id="form">','<div id="propbox"></div><div class="card" id="form">');h=h.replace(/(data-a="arrows">)Сигналы(<\/button>)/,'$1Сигналы стратегии$2<button class="chip ${UI.zones!==false&&UI.arrows?\'on\':\'\'}" data-a="zones">Подсветка</button>'.replace('${UI.zones!==false&&UI.arrows?\'on\':\'\'}',UI.zones!==false&&UI.arrows?'on':''));return h};
// уведомление о новом сигнале — с кнопкой ответа
const _ev4=onEvent;onEvent=function(k,t){_ev4(k,t);if(k==='sig'){try{if(navigator.vibrate)navigator.vibrate([80,60,80])}catch(e){}}};
render();
