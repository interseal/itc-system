// ИТК Трейдинг — бережные запросы к нашему серверу (11.11.2026).
// Защита хостинга от атак блокирует адрес, с которого идёт слишком много запросов подряд.
// Поэтому все запросы к нашему серверу идут по одному, с паузой не меньше 1,2 секунды;
// список серий обновляется не чаще раза в минуту; при ошибке связи — пауза 30 с → 1 → 2 → 5 минут.
"use strict";
const RQ={busy:false,q:[],last:0,cool:0,fails:0,tdl:null,tdlAt:0};
const RQGAP=300,TDLGAP=20e3;/* №435 · свой сервер в Гонконге — паузы короче */
function rqWait(ms){return new Promise(r=>setTimeout(r,ms))}
async function rqRun(fn){return new Promise((res,rej)=>{RQ.q.push({fn,res,rej});rqPump()})}
async function rqPump(){if(RQ.busy)return;RQ.busy=true;
 while(RQ.q.length){const it=RQ.q.shift();const now=Date.now(),w=Math.max(RQ.last+RQGAP-now,RQ.cool-now,0);if(w>0)await rqWait(w);
  try{const v=await it.fn();RQ.fails=0;RQ.cool=0;it.res(v)}
  catch(e){const net=!e||/Failed to fetch|NetworkError|сервер ответил (429|5\d\d)|Load failed/i.test(String(e.message||e));if(net){RQ.fails++;RQ.cool=Date.now()+[30e3,60e3,120e3,300e3][Math.min(3,RQ.fails-1)]}it.rej(e)}
  RQ.last=Date.now()}
 RQ.busy=false}
const _td46=tdapi;tdapi=function(a,d){
 if(a==='td_list'){const now=Date.now();if(RQ.tdl&&now-RQ.tdlAt<TDLGAP)return RQ.tdl;RQ.tdlAt=now;RQ.tdl=rqRun(()=>_td46(a,d)).catch(e=>{RQ.tdlAt=0;throw e});return RQ.tdl}
 return rqRun(()=>_td46(a,d))};
if(typeof tapi==='function'){const _ta46=tapi;tapi=function(a,d){return rqRun(()=>_ta46(a,d))}}
// подсказка, если связь с сервером на паузе
setInterval(()=>{const left=RQ.cool-Date.now();let el=$('#rqnote');if(left>0){if(!el){document.body.insertAdjacentHTML('beforeend','<div id="rqnote" style="position:fixed;left:50%;transform:translateX(-50%);top:8px;z-index:60;background:#2a2e39;border:1px solid var(--ln);color:var(--tx);border-radius:6px;padding:6px 12px;font-size:12px"></div>');el=$('#rqnote')}el.textContent=`Сервер не ответил — пауза ${Math.ceil(left/1000)} с, чтобы не перегружать его`}else if(el)el.remove()},1000);
