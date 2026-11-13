// ИТК Трейдинг — №480 (12.11.2026): «Вся биржа» — вкладки по размеру S / M / L / XL и архив срезов итогов.
// Размер — сколько длится серия из 30 сделок на одной монете: S до 30 дней, M 1–3 месяца, L 3–6 месяцев, XL больше 6 месяцев.
// «Обновить данные»: сервер заново проверяет те же монеты на свежих свечах (всё, что проверено раньше этой минуты, считается заново).
// Старые итоги не затираются: каждый сбор итогов сохраняется на сервере отдельным срезом с датой — их можно открыть и сравнить.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const SZL=[['','Все'],['S','S · до 30 дн.'],['M','M · 1–3 мес.'],['L','L · 3–6 мес.'],['XL','XL · от 6 мес.']];
 let LIST=null,VIEWAT=0;   // список срезов; срез, который сейчас открыт (0 — последний)
 const dd=t=>new Date(t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'});
 async function loadList(){try{const j=await capi('kv_get',{k:'fts_list'});LIST=(j&&Array.isArray(j.v))?j.v:[]}catch(_){LIST=LIST||[]}try{fPaint()}catch(_){}}
 setTimeout(loadList,3500);
 // каждый новый сбор итогов — отдельный срез в архиве (последние 12)
 const _ft52=fTop;
 fTop=async function(){const before=FULL.top&&FULL.top.at;await _ft52();const T=FULL.top;if(!T||!T.at||T.at===before)return;VIEWAT=0;
  try{await capi('kv_set',{k:'fts_'+T.at,v:T});if(!LIST)await loadList();const L=(LIST||[]).filter(x=>x.at!==T.at);L.unshift({at:T.at,coins:T.coins,fresh:+FULL.since||0});
   const drop=L.splice(12);for(const x of drop){try{await capi('kv_set',{k:'fts_'+x.at,v:null})}catch(_){}}LIST=L;await capi('kv_set',{k:'fts_list',v:L})}catch(_){}fPaint()};
 async function openSnap(at){if(!at||(LIST&&LIST[0]&&LIST[0].at===at)){VIEWAT=0;try{const j=await capi('kv_get',{k:'fulltop'});if(j&&j.v&&j.v.at)FULL.top=j.v}catch(_){}fPaint();return}
  toast('Открываю срез от '+dd(at)+'…');try{const j=await capi('kv_get',{k:'fts_'+at});if(j&&j.v&&j.v.at){FULL.top=j.v;VIEWAT=at;fPaint()}else toast('Срез не найден')}catch(e){toast('Нет связи с сервером')}}
 // «Обновить данные»: задание для сервера с отметкой «свежее этой минуты»
 function ask(t,x,yes){return new Promise(res=>{const w=document.createElement('div');w.style.cssText='position:fixed;inset:0;z-index:2147483600;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';
  w.innerHTML=`<div style="background:var(--pan,#1e222d);color:var(--tx,#d1d4dc);border:1px solid var(--ln,#2a2e39);border-radius:12px;max-width:440px;width:100%;padding:18px"><b style="font-size:17px">${t}</b><p class="sm" style="margin:8px 0 0;line-height:1.45">${x}</p><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button data-x="0" class="btn2" style="padding:10px 14px">Отмена</button><button data-x="1" class="btn2" style="padding:10px 14px;background:var(--ac,#2962ff);color:#fff;border-color:transparent">${yes}</button></div></div>`;
  w.addEventListener('click',e=>{const b=e.target.closest('[data-x]');if(!b&&e.target!==w)return;e.stopPropagation();e.preventDefault();w.remove();res(!!b&&b.dataset.x==='1')},true);document.body.appendChild(w)})}
 async function refresh(){if(sjOn('full')){toast('Сервер уже проверяет биржу — дождитесь окончания');return}
  const ok=await ask('Обновить данные?','Сервер заново проверит те же монеты на свежих свечах — по всем вкладкам S, M, L и XL. Займёт несколько часов, телефон можно закрыть. Когда закончит, итоги соберутся сами и лягут в архив новым срезом с датой — прошлые срезы останутся.','Обновить');if(!ok)return;
  const since=Math.floor(Date.now()/1000);FULL.since=since;try{fSave()}catch(_){}
  try{const j=await tdapi('job_add',{kind:'full',set:Object.assign(sjSet('full'),{since})});SJS.full.job=j.job;SJSYNC=Date.now();toast('Сервер начал обновление данных');try{await fProg()}catch(_){}}catch(e){toast('Сервер не принял задание: '+(e.message||''))}fPaint()}
 // сервер закончил обновление — собрать итоги заново (это только чтение базы)
 setInterval(()=>{try{if(document.hidden||UI.view!=='lab'||(UI.labTab||'')!=='full'||FULL.running||FULL.busy)return;const J=SJS.full.job;if(!J||J.status!=='done')return;
  const fin=(J.updated_at||0)*1000,T=FULL.top;if(!VIEWAT&&(!T||fin>T.at+60e3)){FULL.since=0;try{fSave()}catch(_){}fTop()}}catch(_){}},60e3);
 // ---------- на экране: вкладки размеров, срезы, кнопка «Обновить данные» ----------
 function block(){const T=FULL.top;if(!T)return '';const z=FULL.size||'';
  const cnt={};SZL.forEach(([k])=>cnt[k]=0);try{const add=x=>{cnt['']++;cnt[szOf(x)]++};(T.sysAll||T.sys||[]).forEach(add)}catch(_){}
  const tb=SZL.map(([k,t])=>`<button class="btn2" data-a="fsz" data-v="${k}" style="flex:1;min-width:30%;padding:9px 6px;${z===k?'background:var(--ac);color:#fff':''}"><b>${t}</b><br><span class="sm" style="opacity:.8">систем ${cnt[k]}</span></button>`).join('');
  const L=LIST||[],cur=VIEWAT||(L[0]&&L[0].at)||T.at;
  return `<div class="card" style="padding:10px 12px"><h3 style="margin:0 0 6px">${ic('grid',20)} Системы по длине серии</h3>
  <p class="hint" style="margin:0 0 8px">Сколько длится серия из 30 сделок на одной монете. S — самые быстрые: до 30 дней.</p>
  <div class="row" style="gap:6px;margin:0 0 8px;flex-wrap:wrap">${tb}</div>
  <div class="row" style="gap:6px;margin:0;flex-wrap:wrap;align-items:center"><button class="btn2" data-a="fref" style="padding:9px 12px">${ic('replay',18)} Обновить данные</button>
   ${L.length?`<span class="sm mu">Срез:</span>${L.slice(0,6).map((x,i)=>`<button class="btn2 sm" data-a="fsnap" data-v="${x.at}" style="${x.at===cur?'background:var(--ac);color:#fff':''}">${dd(x.at)}${i===0?' · последний':''}</button>`).join('')}`:''}</div>
  ${VIEWAT?`<p class="sm" style="margin:8px 0 0;color:var(--yl,#f0b90b)">Открыт срез от ${dd(VIEWAT)}. <a href="#" data-a="fsnap" data-v="0" style="color:inherit">Вернуться к последнему</a></p>`:''}</div>`}
 const _fb52=fBody;fBody=function(){let h=_fb52();const b=block();if(!b)return h;
  const k=h.indexOf('Системы, которые работают');if(k<0)return h+b;const i=h.lastIndexOf('<div class="card">',k);return i<0?h+b:h.slice(0,i)+b+h.slice(i)};
 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;if(!['fsz','fref','fsnap'].includes(a))return;e.stopPropagation();e.preventDefault();
  if(a==='fsz'){FULL.size=v||'';fPaint()}else if(a==='fref')refresh();else if(a==='fsnap')openSnap(+v)},true);
})();
