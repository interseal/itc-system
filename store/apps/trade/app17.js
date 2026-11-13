// ИТК Трейдинг — на каждой вкладке видна только её торговая сессия (10.11.2026).
// Серия, закреплённая за другой вкладкой, здесь не показывается: ни полоса «Идёт серия», ни её сделки, ни линии на графике.
"use strict";
function serVis(id){const own=TABS.list.find(t=>t.ser&&t.ser.id===id);if(own)return own.id===TABS.cur;
 const [b]=botById(id);const s=b||(typeof TD!=='undefined'?TD:[]).find(x=>x.id===id);if(!s)return true;return s.sym===UI.sym&&s.tf===UI.tf}
// временно прячем чужие серии, пока рисуется блок
function withOwn(fn){const hid=[];D().bots.forEach(b=>{if(b.series&&!serVis(b.id)){hid.push([b,b.status,b.series.hidden]);b.status='other';b.series.hidden=1}});
 const td=typeof TD!=='undefined'?TD:null;if(td)TD=td.filter(s=>serVis(s.id));try{return fn()}finally{hid.forEach(([b,st,h])=>{b.status=st;b.series.hidden=h});if(td)TD=td}}
const _sb17=serBanner;serBanner=function(){return withOwn(()=>_sb17())};
const _tb17=tdBanner;tdBanner=function(){return withOwn(()=>_tb17())};
const _sh17=serHtml;serHtml=function(){return withOwn(()=>_sh17())};
const _sa17=serActive;serActive=function(){return withOwn(()=>_sa17())};
// активные сделки чужой серии — не показываем
const _md17=myDeals;myDeals=function(){_md17();const e=$('#mydeals');if(!e||(typeof isReal==='function'&&isReal()))return;const bk=D();
 e.querySelectorAll('[data-a=dclose]').forEach(btn=>{const t=bk.trades.find(x=>x.id===btn.dataset.v);if(t&&t.bot&&!serVis(t.bot)){const tr=btn.closest('tr');if(tr)tr.remove()}})};
// линии на графике — только своей серии
const _tl17=tradeLines;tradeLines=function(){return withOwn(()=>_tl17()).filter(x=>{if(!x.tid)return true;const t=D().trades.find(y=>y.id===x.tid);return !(t&&t.bot&&!serVis(t.bot))})};
render();
