// ИТК Трейдинг — компьютерная версия (10.11.2026).
// На широком экране (от 1100 точек): меню слева столбиком, график на всю высоту окна, справа — колонка
// со сделками, стратегией, сериями и формой заявки; кнопки над графиком переносятся, а не прячутся за край.
// На телефоне всё как было.
"use strict";
(function(){const st=document.createElement('style');st.textContent=`
body.desk{padding-left:88px}
body.desk #app{max-width:1240px;padding-bottom:0}
body.desk.vterm #app{max-width:none}
body.desk nav{top:0;bottom:0;left:0;right:auto;width:88px;flex-direction:column;justify-content:flex-start;border-top:0;border-right:1px solid var(--ln);padding:8px 0 0}
body.desk nav button{flex:none;max-width:none;padding:12px 0;font-size:12px}
body.desk nav button svg{width:24px;height:24px}
body.desk .bar{flex-wrap:wrap;overflow:visible;row-gap:6px}
body.desk .cbar{flex-wrap:wrap}
body.desk .dk{display:grid;grid-template-columns:minmax(0,1fr) 420px;height:calc(100vh - var(--dkTop,120px))}
body.desk .dkmain{display:flex;flex-direction:column;min-width:0;min-height:0}
body.desk .dkmain #chartWrap{flex:1;height:auto;min-height:360px}
body.desk .dkside{overflow-y:auto;border-left:1px solid var(--ln);background:var(--bg);padding-bottom:12px}
body.desk .dkside .card{margin:10px}
body.desk .dkside .sgrid{grid-template-columns:repeat(2,minmax(0,1fr))}
body.desk .sheet{align-items:center}
body.desk .shin{border-radius:16px;max-height:90vh}
body.desk:not(.vterm) #app > .card,body.desk:not(.vterm) #app > .bar{border-radius:12px}
body.desk.vterm .dktabs{display:flex;flex:1;min-width:0;flex-wrap:nowrap;overflow-x:auto;background:none;border:0;padding:0 6px;gap:6px}
body.desk.vterm .dkin{display:contents}
body.desk.vterm .dkmain{overflow:hidden}
body.desk.vterm .dkmain .bar .chip{padding:4px 9px;font-size:13px}
body.desk.vterm .dkmain .bar .chip svg{width:16px;height:16px}
body.desk.vterm .dkmain .bar{padding:6px 10px;gap:5px}
body.desk.vterm .cbar{flex-wrap:wrap;row-gap:4px;overflow:visible}
body.desk.vterm .cbar .chip{padding:3px 8px;font-size:12.5px}
body.desk .dkside{overflow-x:hidden}
body.desk.vterm header{padding:6px 12px}
body.desk .pct{justify-content:flex-start}
body.desk .pct button{flex:0 0 auto;padding-left:14px;padding-right:14px}
body.desk:not(.vterm) .go.b,body.desk:not(.vterm) #bares .btn2[style*="width:100%"]{max-width:520px}
body.desk #bares h3+p.hint+.plan,body.desk #bares .plan{max-width:none}
`;document.head.appendChild(st)})();
const DESK=()=>window.innerWidth>=1100;
function deskLayout(){const d=DESK();document.body.classList.toggle('desk',d);document.body.classList.toggle('vterm',UI.view==='term');
 if(!d||UI.view!=='term'||UI.layout>1)return;const app=$('#app'),cw=$('#chartWrap');if(!app||!cw||app.querySelector(':scope > .dk'))return;
 const kids=[...app.children],top=[],main=[],side=[];let phase=0;
 for(const el of kids){if(el.tagName==='NAV'||el.classList.contains('sheet'))continue;
  if(el.tagName==='HEADER'||el.classList.contains('tabsbar')){if(phase===0){top.push(el);continue}}
  if(phase===0)phase=1;
  if(phase===1){main.push(el);if(el.id==='stat')phase=2;continue}
  side.push(el)}
 if(!main.includes(cw))return;
 const dk=document.createElement('div');dk.className='dk';const m=document.createElement('div');m.className='dkmain';const s=document.createElement('div');s.className='dkside';
 main.forEach(x=>m.appendChild(x));side.forEach(x=>s.appendChild(x));dk.appendChild(m);dk.appendChild(s);
 const after=top[top.length-1];if(after)after.after(dk);else app.prepend(dk);
 // компактно: вкладки сессий — в шапку; кнопки стратегий — в строку с парой и свечами; «Вход 1/2, Стратегия, Серия» — в строку над графиком
 const hd=app.querySelector(':scope > header'),tb=app.querySelector(':scope > .tabsbar');
 if(hd&&tb){tb.classList.add('dktabs');const tk=hd.querySelector('#tick');tk?hd.insertBefore(tb,tk):hd.appendChild(tb)}
 const bars=main.filter(x=>x.classList&&x.classList.contains('bar')&&!x.classList.contains('ovbar'));
 if(bars.length>=2){const sep=document.createElement('span');sep.className='sep';bars[0].appendChild(sep);bars[1].classList.add('dkin');bars[0].appendChild(bars[1])}
 const cb=m.querySelector('.cbar'),ov=m.querySelector('#ovbar');
 if(cb&&ov){ov.classList.add('dkin');const sp=[...cb.children].find(x=>x.tagName==='SPAN'&&!x.className&&!x.textContent.trim());const sep=document.createElement('span');sep.className='sep';if(sp){cb.insertBefore(sep,sp);cb.insertBefore(ov,sp)}else{cb.appendChild(sep);cb.appendChild(ov)}}
 const h=(hd?hd.getBoundingClientRect().height:0);document.documentElement.style.setProperty('--dkTop',Math.round(h)+'px');
 try{drawOverlay()}catch(_){}}
const _r33=render;render=function(){_r33();try{deskLayout()}catch(e){console.log('desk',e)}};
let DKW=DESK();window.addEventListener('resize',()=>{const d=DESK();if(d!==DKW){DKW=d;render()}else if(d&&UI.view==='term'){const hd=$('#app > header');document.documentElement.style.setProperty('--dkTop',Math.round(hd?hd.getBoundingClientRect().height:0)+'px')}});
render();
