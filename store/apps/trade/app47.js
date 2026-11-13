// ИТК Трейдинг — тяжёлые проверки считает сервер в Гонконге (11.11.2026).
// Кнопка «Проверить на сервере» ставит задание на сервер. Там невидимый браузер («робот») открывает это же приложение
// и считает тем же кодом, что и телефон; результаты сразу сохраняются в базу сервера. Телефон можно закрыть,
// а при следующем открытии — видно, что готово. Считать в самом телефоне по-прежнему можно (ссылка под кнопкой).
//   full — «Вся биржа» (№436)      list — «Листинги» (№437)
//   coins — «Монеты»: найти лучшую систему (№438)      tests — «Тесты»: много монет сразу (№438)
"use strict";
const SJS={full:{job:null,t:0},list:{job:null,t:0},coins:{job:null,t:0},tests:{job:null,t:0}};let SJBUSY=0;
const SJ=SJS.full;// для совместимости
const sjOn=(k='full')=>{const J=SJS[k].job;return !!J&&['wait','run'].includes(J.status)};
async function sjLoad(){try{const j=await tdapi('job_list',{});for(const k in SJS){SJS[k].job=(j.jobs||[]).find(x=>x.kind===k)||null;SJS[k].t=Date.now()}}catch(_){}return SJS.full.job}
const sjUI=()=>{const u=JSON.parse(JSON.stringify(UI));delete u.view;return u};
const sjSet=k=>k==='list'?{mkt:LST.mkt,n:LST.n,h:LST.h,slip:LST.slip}:k==='coins'?{ui:sjUI(),syms:[...CSEL]}:k==='tests'?{ui:sjUI(),syms:BAC()}:{scope:FULL.scope,groups:FULL.groups,fl:FULL.fl};
const sjPaint=k=>k==='list'?lsPaint():k==='coins'?coinsPaint():k==='tests'?baPaint():fPaint();
async function sjStart(k){if(SJBUSY)return;SJBUSY=1;try{const j=await tdapi('job_add',{kind:k,set:sjSet(k)});SJS[k].job=j.job;toast('Проверка поставлена на сервер — телефон можно закрыть')}catch(e){toast('Сервер не принял задание: '+(e.message||''))}SJBUSY=0;sjPaint(k)}
async function sjStop(k){const J=SJS[k].job;if(!J)return;try{await tdapi('job_stop',{id:J.id});await sjLoad();toast('Проверка на сервере остановлена')}catch(e){toast('Нет связи с сервером')}sjPaint(k)}
function sjAgo(s){s=Math.max(0,s|0);return s<90?s+' с':Math.round(s/60)+' мин'}
function sjCard(k){const J=SJS[k].job;let h='';
 if(sjOn(k)){const ago=J.beat?J.now-J.beat:J.now-J.created_at,st=J.status==='wait'&&!J.beat?'Задание ждёт робота сервера — начнёт в течение минуты':(ago>240?'Робот не отзывается '+sjAgo(ago)+' — сервер перезапустит его сам':esc(J.busy||'Идёт проверка…'));
  h=`<div class="go" style="background:var(--pan2);border:2px solid var(--ac);color:var(--tx);cursor:default;display:flex;align-items:center;gap:10px;text-align:left;margin-top:8px"><span class="spin39"></span><span style="flex:1"><b>Считает сервер в Гонконге</b><br><span class="sm" style="font-weight:400">${st}${J.beat?' · '+sjAgo(ago)+' назад':''}</span></span></div>
  <p class="sm mu" style="margin:6px 0 0">Телефон можно закрыть — проверка идёт на сервере круглосуточно.${k==='full'?' Готовое видно по полосе выше. Если поменять монеты, свечи или фильтры, сервер продолжит уже с новыми настройками.':k==='list'?' Итог появится здесь, когда сервер закончит.':' Итог появится во вкладках «Итоги» и «Архив», когда сервер закончит.'}</p>
  <button class="btn2" data-a="sjstop" data-v="${k}" style="width:100%;margin-top:6px;padding:10px">Остановить проверку на сервере</button>`}
 else{h=`<button class="go b" data-a="sjrun" data-v="${k}" style="font-size:16px;margin-top:8px">${ic('play_arrow',20)} Проверить на сервере</button>
  <p class="sm mu" style="margin:6px 0 0">Считает сервер в Гонконге, телефон можно сразу закрыть.${J&&J.status==='done'?' Прошлая проверка на сервере закончена.':''}${J&&J.status==='err'?' <span class="r">Прошлая проверка: '+esc(J.err||'ошибка')+'</span>':''}</p>`}
 return h}
const sjLocal=(a)=>`<button class="lnk sm" data-a="${a}" style="margin-top:6px;background:none;border:0;color:var(--mu);text-decoration:underline;cursor:pointer">или считать в этом устройстве (держать открытым)</button>`;
function sjSwap(h,k,btn){const a=h.indexOf(btn);if(a<0)return h;const b=h.indexOf('</button>',a)+9;return h.slice(0,a)+sjCard(k)+(sjOn(k)?'':sjLocal(k==='list'?'lsrun':k==='coins'?'cgrun':k==='tests'?'ba10start':'frun'))+h.slice(b)}
const _fb47=fBody;fBody=function(){let h=_fb47();if(window.__ITC_ROBOT)return h;
 if(!FULL.running)h=sjSwap(h,'full','<button class="go b" data-a="frun"');
 return h.replace('Пока идёт проверка, экран не гаснет — держите приложение открытым (лучше на ноутбуке).','Проверку считает сервер в Гонконге — телефон можно закрыть.')};
const _cb47=coinsTest;coinsTest=function(){let h=_cb47();if(window.__ITC_ROBOT||(CGB&&CGB.busy))return h;return sjSwap(h,'coins','<button class="go b" data-a="cgrun"')};
const _bs47=baStart38;baStart38=function(){let h=_bs47();if(window.__ITC_ROBOT||UI.baWho!=='many')return h;return sjSwap(h,'tests','<button class="go b" data-a="ba10start"').replace('— не закрывайте приложение.','— на сервере, телефон можно закрыть.')};
const _lb47=lsBody;lsBody=function(){let h=_lb47();if(window.__ITC_ROBOT||LST.busy)return h;return sjSwap(h,'list','<button class="go b" data-a="lsrun"')};
// пока считает сервер — раз в 20 с обновляем состояние; закончил — подтягиваем итог
setInterval(async()=>{if(window.__ITC_ROBOT||document.hidden||UI.view!=='lab')return;const tab=UI.labTab||'',k=tab==='full'?'full':tab==='list'?'list':tab==='coins'?'coins':tab==='tests'&&UI.baWho==='many'?'tests':'';if(!k||Date.now()-SJS[k].t<20e3)return;
 const was=sjOn(k);await sjLoad();if(k==='full')sjAdopt();
 if(k==='full'){if(sjOn(k)||was){try{await fProg()}catch(_){}}fPaint()}
 else if(k==='list'){if(was&&!sjOn(k)){try{const j=await capi('kv_get',{k:'listres'});if(j.v&&j.v.rows)LST.res=j.v}catch(_){}}lsPaint()}
 else{if(was&&!sjOn(k)){RESL=null;toast('Сервер закончил проверку — итог в «Итогах» и «Архиве»')}sjPaint(k)}},5000);
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;if(a!=='sjrun'&&a!=='sjstop')return;e.stopPropagation();e.preventDefault();const k=el.dataset.v||'full';if(a==='sjrun')sjStart(k);else sjStop(k)},true);
setTimeout(()=>{if(!window.__ITC_ROBOT)sjLoad().then(()=>{const t=UI.labTab||'';if(sjAdopt()&&t==='full')fProg().then(fPaint,()=>{});if(t==='full')fPaint();if(t==='list')lsPaint();if(t==='coins')coinsPaint();if(t==='tests')baPaint()})},1500);
// ---------- №462 · настройки «Всей биржи» в телефоне и в задании на сервере — всегда одни и те же ----------
// Раньше задание запоминало настройки на момент запуска: поменяли «Топ-100» на «Топ-200» — сервер продолжал считать старые 100 монет.
const sjNorm=s=>{s=s||{};const fl=s.fl||{};return JSON.stringify([+s.scope||0,(s.groups||[]).slice().sort(),Object.keys(fl).sort().map(k=>[k,+fl[k]||0])])};
let SJSYNC=0;
// открыли приложение, а на сервере идёт проверка с другими настройками — показываем настройки сервера
function sjAdopt(){const J=SJS.full.job;if(window.__ITC_ROBOT||!sjOn('full')||!J.set||Date.now()-SJSYNC<15e3)return 0;
 if(sjNorm(J.set)===sjNorm(sjSet('full')))return 0;
 if(J.set.scope!=null)FULL.scope=+J.set.scope||0;if(Array.isArray(J.set.groups)&&J.set.groups.length)FULL.groups=J.set.groups.slice();
 if(J.set.fl)FULL.fl=Object.assign({htf:0,adx:0,hrs:0,vol:0,maker:0,opt:0,sm:1},J.set.fl);
 fSave();FULL.prog=null;return 1}
// поменяли настройки, пока считает сервер — передаём их в задание; робот начнёт заново с ними, посчитанное остаётся
async function sjSync(){if(window.__ITC_ROBOT||!sjOn('full'))return;const J=SJS.full.job,S=sjSet('full');if(sjNorm(J.set)===sjNorm(S))return;
 SJSYNC=Date.now();J.set=JSON.parse(JSON.stringify(S));
 try{const j=await tdapi('job_add',{kind:'full',set:S});SJS.full.job=j.job;SJSYNC=Date.now();toast('Сервер продолжит проверку с новыми настройками');try{await fProg()}catch(_){}fPaint()}
 catch(e){toast('Не удалось передать настройки на сервер: '+(e.message||''))}}
window.addEventListener('click',()=>{if(!window.__ITC_ROBOT&&UI.view==='lab'&&(UI.labTab||'')==='full')setTimeout(sjSync,400)});
// ---------- режим «робот сервера» ----------
async function fRetry(fn){return fn()}
if(window.__ITC_ROBOT)(async()=>{const id=window.__ITC_ROBOT;let stop=0,again=0,sig0='';
 await new Promise(r=>setTimeout(r,8000));
 let J;try{J=(await tdapi('job_get',{id})).job}catch(e){window.__ITC_ROBOT_DONE=1;return}
 const status=()=>J.kind==='list'?(LST.busy||'Готовлю проверку'):J.kind==='coins'?((CGB&&CGB.busy)||'Готовлю проверку'):J.kind==='tests'?((BA&&BA.busy&&typeof BA.busy==='string'?BA.busy:'')||'Идёт тест на многих монетах'):(FULL.busy||'Готовлю проверку')+(FULL.prog?` · готово ${FULL.prog.done} из ${FULL.prog.total}`:'');
 const halt=()=>{if(J.kind==='list'){LSTJOB++}else if(J.kind==='coins'){CGJOB++}else if(J.kind==='tests'){BAJOB++}else fStop()};
 const beat=async()=>{try{const r=await tdapi('job_beat',{id,busy:status()});if(r.status==='stop'){stop=1;halt()}
  /* №462 · настройки задания поменяли с телефона — останавливаемся, сервер тут же откроет приложение заново уже с ними */
  else if(r.sig){if(!sig0)sig0=r.sig;else if(r.sig!==sig0&&!again){again=1;halt()}}}catch(_){}};
 await beat();const iv=setInterval(beat,30e3);
 let err='',left=0;
 if(J.kind==='coins'||J.kind==='tests'){const S=J.set||{};Object.assign(UI,S.ui||{});
  if(J.kind==='coins'){CSEL=new Set(S.syms||[]);if(!CSEL.size)err='не выбраны монеты';else{try{await fRetry(cgRun)}catch(e){err=e.message||'ошибка'}}}
  else{try{await ba10Run()}catch(e){err=e.message||'ошибка'}}
  // итог сохраняется в архив и «Итоги» сам (saveBA) — ждём, пока запишется
  for(let i=0;i<60&&!stop&&!(BA&&BA.arch);i++)await new Promise(r=>setTimeout(r,1000));await new Promise(r=>setTimeout(r,6000));if(!stop&&!err&&!(BA&&BA.arch))err='итог не сохранился'}
 else if(J.kind==='list'){Object.assign(LST,J.set||{});try{await lsRun()}catch(e){err=e.message||'ошибка'}if(LST.err)err=LST.err;if(!LST.res&&!stop&&!err)left=1}
 else{Object.assign(FULL,J.set||{});FULL.fl=Object.assign({htf:0,adx:0,hrs:0,vol:0,maker:0,opt:0,sm:1},FULL.fl||{});
  try{await fRun()}catch(e){err=e.message||'ошибка'}
  try{const P=await fProg();left=P.total-P.done}catch(_){left=1}err=''}
 clearInterval(iv);
 if(again&&!stop){window.__ITC_ROBOT_DONE=1;return}
 // не всё посчитано (обрыв связи и т. п.) — задание остаётся, сервер сам продолжит через несколько минут
 if(stop||!left)try{await tdapi('job_done',{id,status:stop?'stop':(err?'err':'done'),err})}catch(_){}
 window.__ITC_ROBOT_DONE=1})();
