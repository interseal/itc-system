// ИТК Трейдинг — вкладка «Итоги» (11.11.2026).
// Каждая проверка многих монет сама сохраняется сюда: когда была, какие монеты, лучшая система на эту дату,
// и кнопка «Запустить торговлю» — с рекомендацией, сколько сделок и какую ставку ставить под ваш депозит.
"use strict";
let RESL=null;// список итогов
async function resLoad(){try{const j=await capi('kv_get',{k:'results'});RESL=Array.isArray(j.v)?j.v:[]}catch(_){RESL=RESL||[]}}
function resName(B){try{if(UI.cpre){if(UI.cpre.startsWith('u:'))return 'Группа «'+UI.cpre.slice(2)+'»';const p=CPRE.find(x=>x[0]===UI.cpre);if(p)return p[1]+' монеты'}}catch(_){}return 'Выбранные монеты'}
const rowPack=g=>({sname:g.sname,strat:g.strat,tf:g.tf,dir:g.dir||0,k:g.k,days:g.days,tdays:g.tdays,trades:g.trades,plus:g.plus,act:g.act,stable:g.stable,avgEx:g.avgEx,coins:g.coins.map(x=>({sym:x.sym,n:x.n,sum:+(+x.sum||0).toFixed(3),stop:x.stop}))});
function resRec(B,name,t){
 const ok=B.rows.filter(g=>g.act&&g.plus/g.act>=0.6&&g.trades>=20).map(g=>({g,u:baUsd(g)})).filter(x=>x.u.mo>0).sort((a,b)=>b.u.mo-a.u.mo).slice(0,3);
 return {id:'r'+Date.now().toString(36)+Math.random().toString(36).slice(2,5),t:t||Date.now(),name:name||resName(B),mkt:B.mkt||UI.mkt,coins:(B.coins||[]).slice(0,200),spd:t?'':(typeof spd==='function'?spd():''),fl:B.fl||{},archId:B.archId||null,top:ok.map(x=>rowPack(x.g))}}
async function resSave(B){if(!B||B.kind!=='multi'||!B.rows||!B.rows.length)return;const rec=resRec(B);
 await resLoad();RESL.unshift(rec);RESL=RESL.slice(0,40);try{await capi('kv_set',{k:'results',v:RESL});toast('Итог сохранён: «Анализ → Итоги»')}catch(_){}}
const _sb40=saveBA;saveBA=async function(){const B=BA,was=B&&B.arch;await _sb40();if(B&&!was&&!B.busy)try{await resSave(B)}catch(_){}};
const SPDN40={scalp:'очень быстрые сделки',fast:'быстрые сделки',slow:'долгие сделки'};
function resCard(r){const d=new Date(r.t),b=r.top[0],n=(r.coins||[]).length;
 const head=`<div class="row" style="margin:0;align-items:flex-start"><div style="flex:1"><b style="font-size:15px">${esc(r.name)}</b> <span class="mu sm">· ${n} ${plW(n,'монета','монеты','монет')}${r.spd?' · '+SPDN40[r.spd]:''} · ${r.mkt==='fut'?'фьючерсы':'спот'}</span><div class="sm mu">Проверка от ${d.toLocaleDateString('ru-RU')}, ${d.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'})}</div></div><button class="btn2 sm" data-a="resdel" data-v="${r.id}" title="Удалить">${ic('trash',16)}</button></div>
 <div class="sm" style="margin-top:4px">${(r.coins||[]).slice(0,24).join(', ')}${n>24?' и ещё '+(n-24):''}</div>`;
 if(!b)return `<div class="card">${head}<p class="sm" style="margin:8px 0 0;color:var(--rd)"><b>Прибыльной системы не нашлось</b> — на этих монетах и свечах деньги не ставим.</p>${r.archId?`<button class="btn2 sm" style="margin-top:6px" data-a="arcopen" data-v="${r.archId}">Открыть подробно</button>`:''}</div>`;
 const u=baUsd(b),good=b.coins.filter(x=>x.n>=3&&x.sum>0).length,stable=b.act&&b.stable/b.act>=0.5,short=u.d<45,age=(Date.now()-r.t)/864e5;
 return `<div class="card" style="border:1px solid var(--ln)">${head}
 <div class="wbox" style="margin-top:8px;border:2px solid var(--gr)"><div class="sm mu">Лучшая система на эту дату</div><b style="font-size:16px">${esc(b.sname)}</b><div class="sm mu">свечи ${baTfn(b.tf)} · ${baDirN(b.dir)} · ${baMode(b.k)}</div>
 <div class="sgrid" style="margin-top:6px"><div><small>В месяц при $100</small><b style="font-size:18px">${USC(u.mo)}</b></div><div><small>За ${dayW(u.d)} проверки</small>${USC(u.tot)}</div><div><small>Монет в плюсе</small>${b.plus} из ${b.act}</div><div><small>Сделок в месяц</small>≈ ${fmt(u.trM,0)}</div></div>
 <p class="sm" style="margin:6px 0 0"><b>${short?'Проверка шла мало дней — сначала демо.':stable?'Результат устойчивый.':'Прибыль есть, но идёт неровно.'}</b>${age>14?` <span class="r">Итогу больше двух недель — рынок мог измениться, лучше проверить заново.</span>`:''}</p></div>
 ${r.top.slice(1).map((x,i)=>{const v=baUsd(x);return `<div class="row" style="margin:6px 0 0;align-items:center"><span class="sm" style="flex:1">${i+2}. ${esc(x.sname)} <span class="mu">· ${baTfn(x.tf)} · ${baDirN(x.dir)}</span></span><b class="sm">${USC(v.mo)}<span class="mu" style="font-weight:400"> в мес.</span></b><button class="btn2 sm" style="margin-left:6px" data-a="respk" data-v="${r.id}|${i+1}">${ic('play_arrow',14)}</button></div>`}).join('')}
 <div class="row" style="gap:6px;margin-top:10px;flex-wrap:wrap"><button class="go b" style="flex:2;margin:0;min-width:200px" data-a="respk" data-v="${r.id}|0">${ic('rocket',18)} Запустить торговлю${good?' · '+good+' '+plW(good,'монета','монеты','монет'):''}</button>${r.archId?`<button class="btn2" style="flex:1;padding:10px" data-a="arcopen" data-v="${r.archId}">Подробно</button>`:''}</div></div>`}
function resView(){if(RESL==null){resLoad().then(()=>{const e=$('#labres');if(e)e.innerHTML=resBody()});return '<p class="mu">Загружаю…</p>'}return resBody()}
const RIMP='<button class="btn2" style="width:100%;padding:10px;margin-bottom:8px" data-a="resimp">'+'Перенести проверки из архива'+'</button>';
function resBody(){if(!RESL.length)return RIMP+`<div class="card"><p style="margin:0"><b>Итогов пока нет.</b></p><p class="hint">Запустите проверку во вкладке «Монеты» или «Тесты» (много монет) — когда она закончится, итог появится здесь: дата, монеты, лучшая система и кнопка «Запустить торговлю».</p></div>`;
 return RIMP+`<p class="hint" style="margin:0 0 8px">Здесь сохраняются все проверки. Нажмите «Запустить торговлю» — программа подскажет, сколько сделок поставить и какую ставку выбрать под ваш депозит.</p>`+RESL.map(resCard).join('')}
async function resImport(btn){if(btn)btn.textContent='Ищу проверки в архиве…';let L=[];try{const j=await tdapi('an_list',{});L=(j.items||[]).filter(x=>x.kind==='multi')}catch(e){toast('Нет связи с сервером');if(btn)btn.textContent='Перенести проверки из архива';return}
 await resLoad();const have=new Set(RESL.map(r=>r.archId).filter(Boolean));let n=0,k=0;
 for(const it of L){if(have.has(it.id))continue;k++;if(btn)btn.textContent=`Переношу ${k} из ${L.length-have.size}…`;try{const {d}=await archGet(it.id);const B=unpackBA(d);B.archId=it.id;RESL.push(resRec(B,it.title||'Проверка из архива',(+it.created_at||0)*1000||Date.now()));n++}catch(_){}}
 RESL.sort((a,b)=>b.t-a.t);RESL=RESL.slice(0,80);try{await capi('kv_set',{k:'results',v:RESL})}catch(_){}
 const x=$('#labres');if(x)x.innerHTML=resBody();toast(n?`Перенесено из архива: ${n}`:'Новых проверок в архиве нет')}
LABT.splice(2,0,['res','flag','Итоги']);
const _lv40=labView;labView=function(){if((UI.labTab||'tests')!=='res')return _lv40();const h=_lv40(),i=h.indexOf('<div class="labtabs">'),j=i>=0?h.indexOf('</div>',i)+6:0;return h.slice(0,j)+`<div id="labres">${resView()}</div>`};
window.addEventListener('click',async e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='respk'){e.stopPropagation();e.preventDefault();const [id,k]=v.split('|'),r=(RESL||[]).find(x=>x.id===id);if(!r||!r.top[+k])return;const g=Object.assign({},r.top[+k]);pkOpenG(g,r.mkt,r.fl)}
 else if(a==='resimp'){e.stopPropagation();e.preventDefault();resImport(el)}
 else if(a==='resdel'){e.stopPropagation();e.preventDefault();if(!confirm('Удалить этот итог?'))return;RESL=(RESL||[]).filter(x=>x.id!==v);try{await capi('kv_set',{k:'results',v:RESL})}catch(_){}const x=$('#labres');if(x)x.innerHTML=resBody()}},true);
render();
