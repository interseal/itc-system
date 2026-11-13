// ИТК Трейдинг — «Точки входа»: где лучше войти по стратегии, а не по текущей цене
// PLAN.make(c, stratId, spot) → [{id,src,kind:'breakout'|'bounce',side,type:'stop'|'limit',entry,sl,tps:[t1,t2],why,score}]
(function(){
function levels(c,atr){const k=4,piv=[];for(let i=k;i<c.length-k;i++){let h=true,l=true;for(let j=i-k;j<=i+k;j++){if(j===i)continue;if(c[j].high>c[i].high)h=false;if(c[j].low<c[i].low)l=false}if(h)piv.push({i,p:c[i].high});if(l)piv.push({i,p:c[i].low})}
 const tol=atr*0.6,zones=[];piv.sort((a,b)=>a.p-b.p).forEach(x=>{const z=zones.find(z=>Math.abs(z.p-x.p)<=tol);if(z){z.p=(z.p*z.n+x.p)/(z.n+1);z.n++;z.last=Math.max(z.last,x.i)}else zones.push({p:x.p,n:1,last:x.i})});
 return zones.filter(z=>z.n>=2).sort((a,b)=>a.p-b.p)}
function make(c,sid,spot){if(c.length<60)return [];const n=c.length,price=c[n-1].close,atr=ITS.ind.atr(c,14)[n-1]||price*0.01,R=v=>+v.toFixed(v>=1000?2:v>=10?2:v>=1?3:5),out=[];
 const add=o=>{o.entry=R(o.entry);o.sl=R(o.sl);o.tps=o.tps.map(R);const risk=Math.abs(o.entry-o.sl);if(!risk)return;o.rr=Math.abs(o.tps[o.tps.length-1]-o.entry)/risk;o.dist=(o.entry-price)/price*100;if(o.side<0&&spot)return;
  if(o.type==='limit'&&((o.side>0&&o.entry>=price)||(o.side<0&&o.entry<=price)))return;if(o.type==='stop'&&((o.side>0&&o.entry<=price)||(o.side<0&&o.entry>=price)))return;if(Math.abs(o.dist)>15||o.rr<1)return;o.id=o.src+':'+o.side+':'+o.entry;out.push(o)};
 // 1) фигуры, которые формируются: вход по пробою
 if(window.PAT){const d=PAT.detect(c);d.figs.filter(f=>f.brk<0).forEach(f=>{const lastI=Math.max(...f.pts.map(p=>p[0]),...f.lines.map(l=>l[0][0]));if(lastI<n-60)return;
   const at=l=>l[0][1]+(l[1][1]-l[0][1])*(n-1-l[0][0])/((l[1][0]-l[0][0])||1);
   if(f.id==='dbot'||f.id==='ihs'){const neck=at(f.lines[0]),low=Math.min(...f.pts.slice(1).map(p=>p[1])),h=neck-low;add({src:f.name,kind:'breakout',side:1,type:'stop',entry:neck+atr*0.1,sl:low-atr*0.3,tps:[neck+h*0.6,neck+h],why:`Формируется «${f.name}». Покупка — когда цена пробьёт линию шеи ${R(neck)} вверх. Стоп под донышками, цель — высота фигуры.`})}
   if(f.id==='dtop'||f.id==='hs'){const neck=at(f.lines[0]),hi=Math.max(...f.pts.slice(1).map(p=>p[1])),h=hi-neck;add({src:f.name,kind:'breakout',side:-1,type:'stop',entry:neck-atr*0.1,sl:hi+atr*0.3,tps:[neck-h*0.6,neck-h],why:`Формируется «${f.name}». Продажа — когда цена пробьёт линию шеи ${R(neck)} вниз. Стоп над вершинами, цель — высота фигуры.`})}
   if(f.lines.length===2){const top=at(f.lines[0]),bot=at(f.lines[1]),h=Math.max(atr,(f.lines[0][0][1]-f.lines[1][0][1]));const exp=f.expect;
    if(exp>=0)add({src:f.name,kind:'breakout',side:1,type:'stop',entry:top+atr*0.1,sl:Math.max(bot,top-atr*1.5),tps:[top+h*0.5,top+h],why:`«${f.name}»: цена сжимается. Покупка при пробое верхней границы ${R(top)}. ${exp>0?'Эта фигура чаще пробивается вверх.':''}`});
    if(exp<=0)add({src:f.name,kind:'breakout',side:-1,type:'stop',entry:bot-atr*0.1,sl:Math.min(top,bot+atr*1.5),tps:[bot-h*0.5,bot-h],why:`«${f.name}»: цена сжимается. Продажа при пробое нижней границы ${R(bot)}. ${exp<0?'Эта фигура чаще пробивается вниз.':''}`})}})}
 // 2) уровни поддержки и сопротивления: отскок
 const lv=levels(c.slice(-400),atr),sup=lv.filter(z=>z.p<price-atr*0.3).slice(-2).reverse(),res=lv.filter(z=>z.p>price+atr*0.3).slice(0,2);
 sup.forEach((z,k)=>{const nextR=res[0]?res[0].p:price+3*atr,e=z.p+atr*0.15,sl=z.p-atr*0.9;add({src:'Уровень поддержки',kind:'bounce',side:1,type:'limit',entry:e,sl,tps:[e+(e-sl)*1.5,Math.min(e+(e-sl)*4,Math.max(e+(e-sl)*2,nextR-atr*0.2))],touches:z.n,why:`Поддержка ${R(z.p)}: цена разворачивалась от неё ${z.n} раз. Покупка лимитом чуть выше уровня в расчёте на отскок, стоп — под уровнем.`})});
 res.forEach((z,k)=>{const nextS=sup[0]?sup[0].p:price-3*atr,e=z.p-atr*0.15,sl=z.p+atr*0.9;add({src:'Уровень сопротивления',kind:'bounce',side:-1,type:'limit',entry:e,sl,tps:[e-(sl-e)*1.5,Math.max(e-(sl-e)*4,Math.min(e-(sl-e)*2,nextS+atr*0.2))],touches:z.n,why:`Сопротивление ${R(z.p)}: цена разворачивалась от него ${z.n} раз. Продажа лимитом чуть ниже уровня в расчёте на отскок вниз, стоп — над уровнем.`})});
 // 3) пробой ближайшего уровня (для трендовых стратегий)
 if(res[0])add({src:'Пробой сопротивления',kind:'breakout',side:1,type:'stop',entry:res[0].p+atr*0.25,sl:res[0].p-atr*0.8,tps:[res[0].p+atr*1.5,res[0].p+atr*3],why:`Если цена пробьёт сопротивление ${R(res[0].p)}, вероятно продолжение роста. Покупка после пробоя.`});
 if(sup[0])add({src:'Пробой поддержки',kind:'breakout',side:-1,type:'stop',entry:sup[0].p-atr*0.25,sl:sup[0].p+atr*0.8,tps:[sup[0].p-atr*1.5,sup[0].p-atr*3],why:`Если цена пробьёт поддержку ${R(sup[0].p)} вниз, вероятно продолжение падения. Продажа после пробоя.`});
 // подбор под стратегию
 const s=ITS.list.find(x=>x.id===sid);let state=0;if(s){const sg=s.run(c,(()=>{const o={};s.params.forEach(p=>o[p.k]=p.v);return o})()),L=sg[sg.length-1];state=L?(L.t==='long'?1:L.t==='short'?-1:0):0}
 const trend=['ema','st','macd','don'].includes(sid),revert=['rsi','bb','lvl'].includes(sid);
 out.forEach(o=>{let sc=o.rr;if(o.src&&!o.src.startsWith('Уров')&&!o.src.startsWith('Проб'))sc+=sid==='pat'?3:1.5;if(trend){if(o.side===state)sc+=2;else if(state)sc-=3;if(o.kind==='breakout')sc+=0.5}if(revert&&o.kind==='bounce')sc+=2;if(o.touches)sc+=Math.min(2,o.touches*0.4);sc-=Math.abs(o.dist)*0.15;o.score=sc;o.fit=trend?(o.side===state?'по стратегии':state?'против стратегии':''):revert?(o.kind==='bounce'?'по стратегии':''):sid==='pat'?(o.kind==='breakout'&&!o.src.startsWith('Проб')?'по стратегии':''):''});
 const seen=new Set();return out.sort((a,b)=>b.score-a.score).filter(o=>{const k=o.side+':'+Math.round(o.entry/atr);if(seen.has(k))return false;seen.add(k);return o.fit!=='против стратегии'}).slice(0,4)}
window.PLAN={make,levels};
})();
