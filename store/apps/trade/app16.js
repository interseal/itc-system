// ИТК Трейдинг — вкладка = одна торговая сессия (10.11.2026).
// Серия запускается на вкладке и «закрепляет» её: пока серия идёт, на этой вкладке нельзя сменить пару, рынок,
// время свечи и стратегию — график показывает ровно то, по чему торгует серия. Посмотреть другое — в новой вкладке.
"use strict";
ICP.lock="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z";
const curTab=()=>TABS.list.find(t=>t.id===TABS.cur);
function serOf(ref){if(!ref)return null;if(ref.k==='srv'){const s=(typeof TD!=='undefined'?TD:[]).find(x=>x.id===ref.id);return s&&s.status==='run'?{strat:s.strat,sym:s.sym,tf:s.tf,mkt:s.mkt,n:s.n,srv:1}:null}
 const [b]=botById(ref.id);return b&&b.series&&b.status==='run'?{strat:b.strat,sym:b.sym,tf:b.tf,mkt:b.mkt,n:b.series.n}:null}
function tabLock(t){t=t||curTab();return t?serOf(t.ser):null}
// старые серии, запущенные до этого обновления: закрепляем за вкладкой с той же парой и временем свечи
function bindOld(){const used=new Set(TABS.list.map(t=>t.ser&&t.ser.id).filter(Boolean));const all=[];
 D().bots.filter(b=>b.series&&b.status==='run').forEach(b=>all.push({k:'loc',id:b.id,sym:b.sym,tf:b.tf}));(typeof TD!=='undefined'?TD:[]).filter(s=>s.status==='run').forEach(s=>all.push({k:'srv',id:s.id,sym:s.sym,tf:s.tf}));
 let ch=false;all.forEach(x=>{if(used.has(x.id))return;const t=TABS.list.find(t=>!tabLock(t)&&t.st&&t.st.sym===x.sym&&t.st.tf===x.tf);if(t){t.ser={k:x.k,id:x.id};used.add(x.id);ch=true}});if(ch)LS.set('tabs',TABS)}
function lockMsg(L){const s=ITS.list.find(q=>q.id===L.strat);return sheetWrap(`${ic('lock',20)}&nbsp;Здесь идёт торговая сессия`,`<p>На этой вкладке работает серия: <b>${s?s.name:L.strat} · ${L.sym} · ${(TFS.find(t=>t[0]===L.tf)||[0,L.tf])[1]}</b>${L.srv?' (на сервере)':''}.</p>
 <p class="mu sm">Пока серия идёт, пара, время свечи и стратегия на этой вкладке не меняются — чтобы график показывал ровно то, по чему идёт торговля. Посмотреть другую монету или стратегию можно в новой вкладке.</p>
 <button class="go b" data-a="locknew">Открыть новую вкладку</button><button class="btn2" style="width:100%;margin-top:8px;padding:10px" data-a="shclose">Остаться здесь</button>`)}
const _r16=render;render=function(){const L=UI.view==='term'?tabLock():null;if(L){UI.sym=L.sym;UI.tf=L.tf;UI.strat=L.strat;if(L.mkt)UI.mkt=L.mkt}
 if(SHEET&&SHEET[0]==='lock'){SHEET=null;_r16();SHEET=['lock'];const l=tabLock();if(l)$('#app').insertAdjacentHTML('beforeend',lockMsg(l));else SHEET=null;return}_r16()};
const BLOCK=['mkt','tf','tfmore','pairs','setpair','strat','pick'];
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;
 if(a==='locknew'){e.stopPropagation();SHEET=null;tabAdd();const t=curTab();if(t){t.ser=null;LS.set('tabs',TABS)}return}
 if(!BLOCK.includes(a)||UI.view!=='term')return;const L=tabLock();if(!L)return;
 if(a==='setpair'&&el.dataset.v===L.sym)return;if(a==='tf'&&el.dataset.v===L.tf)return;if(a==='mkt'&&el.dataset.v===L.mkt)return;if(a==='pick'&&el.dataset.v===L.strat)return;
 e.stopPropagation();e.preventDefault();SHEET=['lock'];render();history.pushState({sh:1},'')},true);
// новая вкладка — без закреплённой серии
const _ta16=tabAdd;tabAdd=function(){_ta16();const t=curTab();if(t&&t.ser){t.ser=null;LS.set('tabs',TABS);render()}};
// запуск серии закрепляет вкладку; вторую серию на занятой вкладке не запускаем
const _sst16=serStart;serStart=async function(){const L=tabLock(),er=$('#srerr');if(L){if(er)er.textContent='На этой вкладке уже идёт серия. Откройте новую вкладку («+» вверху), чтобы запустить ещё одну.';return}
 const before=new Set(D().bots.map(b=>b.id)),beforeS=new Set((typeof TD!=='undefined'?TD:[]).map(s=>s.id)),sid=$('#srs')&&$('#srs').value,tf=$('#srtf')&&$('#srtf').value;
 await _sst16();
 const nb=D().bots.find(b=>b.series&&!before.has(b.id)),ns=(typeof TD!=='undefined'?TD:[]).find(s=>!beforeS.has(s.id));const t=curTab();if(!t||(!nb&&!ns))return;
 t.ser=nb?{k:'loc',id:nb.id}:{k:'srv',id:ns.id};if(sid)UI.strat=sid;if(tf)UI.tf=tf;C=[];saveUI();LS.set('tabs',TABS);render()};
// значок замка на вкладке с идущей серией
const _tb16=tabsBar;tabsBar=function(){let h=_tb16();TABS.list.forEach(t=>{if(tabLock(t))h=h.replace(`data-a="tabgo" data-v="${t.id}">`,`data-a="tabgo" data-v="${t.id}">${ic('lock',14)}`)});return h};
bindOld();setTimeout(()=>{bindOld();render()},4000);
render();
