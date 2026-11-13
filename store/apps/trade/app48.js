// ИТК Трейдинг — №473 (12.11.2026): кнопки работают сразу, повторная проверка — только после вопроса, выгрузка в «Загрузки».
// 1) Нажатия «Остановить проверку на сервере» и «Проверить на сервере» больше не ждут в общей очереди запросов
//    (раньше стояли за сбором итогов и паузами «Сервер не ответил» — казалось, что кнопка не реагирует).
// 2) Если проверка всей биржи уже была меньше суток назад — сначала спрашиваем, точно ли запускать заново.
// 3) «Выгрузить для Claude» в приложении на телефоне сохраняет файл сразу в «Загрузки».
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 // ---------- 1. срочные запросы — мимо очереди ----------
 const NOW_A=['job_stop','job_add','job_list'];
 const _td48=tdapi;
 tdapi=function(a,d){if(NOW_A.includes(a)&&typeof _td46==='function')return _td46(a,d);return _td48(a,d)};
 // ---------- 2. вопрос перед повторной проверкой ----------
 function ask(title,text,yes,no){return new Promise(res=>{const w=document.createElement('div');
  w.style.cssText='position:fixed;inset:0;z-index:2147483600;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';
  w.innerHTML=`<div style="background:var(--pan,#1e222d);color:var(--tx,#d1d4dc);border:1px solid var(--ln,#2a2e39);border-radius:12px;max-width:420px;width:100%;padding:18px 18px 14px;font-family:Roboto,Arial,sans-serif">
   <div style="display:flex;gap:10px;align-items:flex-start"><svg viewBox="0 0 24 24" style="width:26px;height:26px;flex:none;fill:#f0b90b"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
   <div><b style="font-size:17px">${title}</b><p style="margin:8px 0 0;font-size:14px;line-height:1.45;color:var(--mu,#9aa1ad)">${text}</p></div></div>
   <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button data-x="0" class="btn2" style="padding:10px 14px">${no}</button><button data-x="1" class="btn2" style="padding:10px 14px;background:var(--ac,#2962ff);color:#fff;border-color:transparent">${yes}</button></div></div>`;
  w.addEventListener('click',e=>{const b=e.target.closest('[data-x]');if(!b&&e.target!==w)return;e.stopPropagation();e.preventDefault();w.remove();res(!!b&&b.dataset.x==='1')},true);
  document.body.appendChild(w)})}
 const hrs=s=>{const h=s/3600;return h<1?Math.max(1,Math.round(s/60))+' мин':Math.round(h)+' ч'};
 const _st48=sjStart;
 sjStart=async function(k){
  if(k==='full'){let J=SJS.full.job;try{await sjLoad();J=SJS.full.job}catch(_){}
   const P=FULL.prog,pc=P&&P.total?Math.round(P.done/P.total*100):0;
   if(J&&['done','err','stop'].includes(J.status)){const ago=(J.now||Math.round(Date.now()/1000))-(J.updated_at||J.created_at||0);
    if(ago<86400){const ok=await ask('Проверка уже была '+hrs(ago)+' назад',
      'Прошлая проверка всей биржи закончилась меньше суток назад'+(pc?` (готово ${pc}%)`:'')+'. Её итоги сохранены — откройте «Самые прибыльные по базе».<br><br>Точно запустить проверку заново?','Запустить заново','Отмена');
     if(!ok){toast('Проверка не запускалась');return}}}}
  return _st48(k)};
 // ---------- 3. выгрузка в «Загрузки» в приложении на телефоне ----------
 const _sf48=saveFile;
 saveFile=function(name,txt,type){const AF=window.AndroidFiles;
  if(AF&&AF.saveFile){try{const b64=btoa(unescape(encodeURIComponent(txt)));if(AF.saveFile(b64,name,type||'application/json')){toast('Файл «'+name+'» сохранён в «Загрузки» — пришлите его в чат Claude');return}}catch(_){}}
  return _sf48(name,txt,type)};
})();
