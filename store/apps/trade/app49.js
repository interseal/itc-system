// ИТК Трейдинг — №475 (12.11.2026): итоги «Вся биржа» хранятся на сервере одним файлом.
// Раньше «Самые прибыльные по базе» каждый раз заново собирались из 1 400 записей (несколько минут), а результат нигде не хранился.
// Теперь: собрали один раз — итог сохраняется на сервере в Гонконге (и в телефоне), при открытии показывается сразу.
// Пересобирается сам, когда сервер закончил новую проверку, или по кнопке «Обновить итоги».
// Этот же файл на сервере может прочитать Claude — скачивать и пересылать в чат не нужно.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const LSK='itc_fulltop_v1';
 const fmtD=t=>{const d=new Date(t);return d.toLocaleDateString('ru-RU',{day:'2-digit',month:'2-digit'})+' в '+d.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'})};
 function put(T){try{localStorage.setItem(LSK,JSON.stringify(T))}catch(_){}}
 function take(){try{return JSON.parse(localStorage.getItem(LSK)||'null')}catch(_){return null}}
 FULL.saved='';   // '' | 'сохраняю' | 'на сервере' | 'только в телефоне'
 // 1. после сбора — сохраняем
 const _ft49=fTop;
 fTop=async function(){try{await _ft49()}catch(_){}const T=FULL.top;if(!T||!T.at)return;T.sig=fSig();
  // в файл идёт всё нужное экрану и выгрузке: системы — первые 400 по монетам в плюсе и 200 самых доходных
  if(T.sysAll&&T.sysAll.length>400){const a=T.sysAll.slice(0,400),b=T.sysAll.filter(x=>x.share>=0.6&&x.mo>0).sort((x,y)=>y.mo-x.mo).slice(0,200);T.sysAll=[...new Set(a.concat(b))]}
  put(T);FULL.saved='сохраняю';fPaint();
  try{await capi('kv_set',{k:'fulltop',v:T});FULL.saved='на сервере'}catch(e){FULL.saved='только в телефоне'}fPaint()};
 // 2. при открытии — показываем сохранённое сразу; если сервер с тех пор закончил новую проверку — пересобираем сами
 async function boot(){
  if(!FULL.top){const L=take();if(L&&L.at){FULL.top=L;FULL.saved='в телефоне';fPaint()}}
  let S=null;try{const j=await capi('kv_get',{k:'fulltop'});if(j&&j.v&&j.v.at)S=j.v}catch(_){}
  if(S&&(!FULL.top||S.at>FULL.top.at)){FULL.top=S;put(S);fPaint()}
  if(S||FULL.top){FULL.saved=S?'на сервере':'в телефоне';fPaint()}
  // свежая проверка на сервере закончилась позже, чем собраны итоги — собрать заново (без вопросов: это только чтение базы)
  try{const J=(typeof sjLoad==='function')?await sjLoad():null;const fin=J&&['done','err','stop'].includes(J.status)?(J.updated_at||0)*1000:0;
   const T=FULL.top;if(!FULL.running&&!FULL.busy&&(!T||(fin&&fin>T.at+60e3)||(T.sig&&T.sig!==fSig())))fTop()}catch(_){}}
 setTimeout(boot,2500);
 // 3. на экране: когда собраны итоги и где хранятся; кнопка называется «Обновить итоги»
 const _fb49=fBody;
 fBody=function(){let h=_fb49();const T=FULL.top;
  if(T&&T.at){h=h.replace('Самые прибыльные по базе</button>','Обновить итоги</button>');
   const note=`<p class="sm mu" style="margin:6px 0 0">${ic('wallet',16)} Итоги собраны ${fmtD(T.at)} по ${T.coins} монетам${FULL.saved?' · '+(FULL.saved==='на сервере'?'хранятся на сервере, Claude может прочитать их сам':FULL.saved==='сохраняю'?'сохраняю на сервер…':FULL.saved==='в телефоне'?'сохранены в телефоне':'на сервер не сохранились — сохранены в телефоне'):''}</p>`;
   const i=h.indexOf('</button>',h.indexOf('data-a="ftop"'));if(i>0){const j=h.indexOf('</div>',i);if(j>0)h=h.slice(0,j+6)+note+h.slice(j+6)}}
  return h};
})();
