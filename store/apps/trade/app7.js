// ИТК Трейдинг — порядок на графике: одна идея за раз.
// Переключатель над графиком: «Стратегия» · «Вход 1…4» · «Скрыть». Рисуются только линии выбранного: вход, стоп, тейки.
"use strict";
if(!UI.z7){UI.zones=false;UI.z7=1;saveUI()}
ICP.eyeoff="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z";
// что можно выбрать сейчас
function ovOpts(){const o=[];if(UI.plans)PLANS.forEach((x,i)=>o.push({v:'p'+i,t:'Вход '+(i+1),s:ovHas(x.entry)?'заявка стоит':x.side>0?'купить':'продать',side:x.side}));
 if(PROP&&UI.arrows)o.push({v:'strat',t:'Стратегия',s:PROP.exit?'вне рынка':PROP.side>0?'купить':'продать',side:PROP.exit?0:PROP.side});return o}
function ovCur(){const o=ovOpts();if(UI.ov==='off')return 'off';if(o.some(x=>x.v===UI.ov))return UI.ov;return o.length?o[0].v:'off'}
function ovBar(){const o=ovOpts();if(!o.length)return '';const cur=ovCur();
 return o.map(x=>`<button class="chip ovc ${cur===x.v?'on':''}" data-a="ovsel" data-v="${x.v}"><i class="dot" style="background:${x.side>0?'#089981':x.side<0?'#f23645':'#8a90a6'}"></i>${x.t} <small>${x.s}</small></button>`).join('')+`<button class="chip ovc ${cur==='off'?'on':''}" data-a="ovsel" data-v="off">${ic('eyeoff',16)} Скрыть</button>`}
// линии выбранного
function ovLines(){const cur=ovCur(),o=[],pc=(p,e)=>(p>=e?'+':'')+fmt((p-e)/e*100,1)+'%';
 if(cur==='strat'){const P=PROP;if(!P||P.exit||P.declined)return o;o.push({k:'pe',p:P.ep,c:'#2962ff',a:'Вход',b:'сейчас',fixed:1});o.push({k:'ps',p:P.sl,c:'#f23645',a:'Стоп',b:pc(P.sl,P.ep),fixed:1});P.tps.forEach((t,i)=>o.push({k:'pt'+i,p:t,c:'#089981',a:'Тейк '+(i+1),b:pc(t,P.ep),fixed:1}))}
 else if(cur[0]==='p'){const x=PLANS[+cur.slice(1)];if(!x)return o;const now=PX[UI.sym]||x.entry;o.push({k:'plan',p:x.entry,c:'#2962ff',a:'Вход',b:pc(x.entry,now),fixed:1});o.push({k:'plsl',p:x.sl,c:'#f23645',a:'Стоп',b:pc(x.sl,x.entry),fixed:1});x.tps.forEach((t,j)=>o.push({k:'pltp'+j,p:t,c:'#089981',a:'Тейк '+(j+1),b:pc(t,x.entry),fixed:1}))}
 return o}
let OVB=[];const ovHas=p=>OVB.some(b=>b.p&&Math.abs(b.p-p)/p<0.002);
const _fl7=formLines;formLines=function(){let o=_fl7().filter(x=>!/^(pe|ps|pt\d|plan\d*|plsl|pltp\d)$/.test(x.k));let tl=[];try{tl=tradeLines()}catch(e){}OVB=o.concat(tl);if(+F.qty>0)return o;const L=ovLines();if(L.length&&ovHas(L[0].p))return o;return o.concat(L)};
// карточка под графиком — только выбранная
function ovCards(){const cur=ovCur(),pb=$('#planbox'),sb=$('#propbox');
 if(sb)sb.style.display=cur==='strat'?'':'none';
 if(pb){const isP=cur[0]==='p'&&cur!=='off';pb.style.display=isP?'':'none';if(isP){const i=+cur.slice(1);pb.querySelectorAll('.plan').forEach(el=>{const me=+el.dataset.v===i;el.style.display=me?'':'none';el.classList.toggle('sel',me)});const h=pb.querySelector('h3');if(h)h.textContent='Точка входа '+(i+1)+' из '+PLANS.length}}}
function ovPaint(fit){if(UI.ov!=='off'){const c=ovCur();if(c[0]==='p')UI.planSel=+c.slice(1);else UI.planSel=null}ovCards();ovScale();paintOrders();const b=$('#ovbar');if(b){const h=ovBar();b.innerHTML=h;b.style.display=h?'':'none'}if(fit&&CH){try{CH.timeScale().scrollToRealTime()}catch(e){}}}
// масштаб по цене: чтобы линии выбранного помещались на экране
function ovScale(){if(!CS)return;CS.applyOptions({autoscaleInfoProvider:orig=>{const r=orig();const L=(+F.qty>0)?[]:ovLines();if(!r||!r.priceRange||!L.length)return r;const ps=L.map(x=>x.p);r.priceRange.minValue=Math.min(r.priceRange.minValue,...ps);r.priceRange.maxValue=Math.max(r.priceRange.maxValue,...ps);return r}})}
const _ps7=paintStrat;paintStrat=function(){_ps7();if(!CH||!C.length)return;ovPaint(false)};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');
 if(!el){const lab=e.target.closest('#lbls .ol.fx');if(lab){const t=ovCur()==='strat'?$('#propbox'):$('#planbox');if(t&&t.style.display!=='none')t.scrollIntoView({behavior:'smooth',block:'start'})}return}
 const a=el.dataset.a,v=el.dataset.v;
 if(a==='ovsel'){UI.ov=v;saveUI();ovPaint(true)}
 else if(a==='plansel'){UI.ov='p'+v;saveUI();setTimeout(()=>ovPaint(true),0)}
 else if(a==='propno'||a==='propundo')setTimeout(()=>ovPaint(false),0)},true);
const _tv7=termView;termView=function(){let h=_tv7();h=h.replace('<div id="chartWrap">','<div class="bar ovbar" id="ovbar" style="display:none"></div><div id="chartWrap">');return h};
const _r7=render;render=function(){_r7();if(UI.view==='term'&&PLANS!==undefined)ovPaint(false)};
render();
