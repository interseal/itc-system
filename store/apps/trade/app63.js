// Alex Trade — №525 (13.11.2026): «Вся биржа» снова находит свои итоги в базе.
// Итоги в базе лежат под «подписью» настроек: рынок + фильтры + число стратегий (например «futh0a0…N47»).
// Если число стратегий в приложении поменялось, подпись тоже менялась, и прошлые проверки переставали находиться:
// «Готово 0 из 1 400», а итоги пересобирались пустыми. Теперь, если под текущей подписью данных нет,
// берётся самая свежая проверка с теми же рынком и фильтрами. На экране видно, что лежит в базе.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 let SIGS=null,ALT='';
 async function loadSigs(){try{const j=await capi('res_sigs',{});SIGS=(j&&j.items)||[]}catch(_){SIGS=SIGS||[]}}
 const _fs63=fSig;
 fSig=function(){const b=_fs63();if(!SIGS)return b;
  if(SIGS.some(x=>x.sig===b&&+x.n>=20)){ALT='';return b}
  const pre=b.replace(/N\d+$/,'');
  const c=SIGS.filter(x=>x.sig!==b&&x.sig.indexOf(pre)===0&&/N\d+$/.test(x.sig)&&+x.n>=20).sort((p,q)=>(+q.t)-(+p.t))[0];
  ALT=c?c.sig:'';return c?c.sig:b};
 // после загрузки списка — пересчитать прогресс и, если итоги пустые, собрать их заново
 setTimeout(async()=>{await loadSigs();try{await fProg()}catch(_){}
  const T=FULL.top,empty=!T||!(T.sysAll||T.sys||[]).length;
  if(ALT&&empty&&!FULL.running&&!FULL.busy){try{await fTop()}catch(_){}}try{fPaint()}catch(_){}},4000);

 // №526 · пустые итоги больше не затирают хорошие. Причина пустых сборок: одна стратегия подгружается чуть позже,
 // и в первые секунды приложение видит 47 стратегий вместо 48 — подпись не совпадает, база отвечает «пусто».
 const emptyT=v=>!v||!((v.sysAll||v.sys||[]).length||(v.best||[]).length);
 const _cap63=tdapi;
 tdapi=function(a,d){
  if(a==='kv_set'&&d&&typeof d.k==='string'&&(d.k==='fulltop'||/^fts_\d/.test(d.k))&&d.v&&emptyT(d.v))return Promise.resolve({ok:true,skipped:1});
  if(a==='kv_set'&&d&&d.k==='fts_list'&&emptyT(FULL.top))return Promise.resolve({ok:true,skipped:1});
  return _cap63(a,d)};
 const _ft63=fTop;
 fTop=async function(){const prev=FULL.top;try{await _ft63()}catch(_){}
  if(emptyT(FULL.top)&&!emptyT(prev)){FULL.top=prev;try{toast('Итоги не пересобраны: база не ответила — оставлены прежние')}catch(_){}try{fPaint()}catch(_){}}};
 const d63=t=>new Date(t*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
 // №527 · пока собираются итоги — крутилка и полоса, чтобы было видно, что работа идёт
 (function(){const st=document.createElement('style');st.textContent='@keyframes sp63{to{transform:rotate(360deg)}}.sp63{width:22px;height:22px;flex:none;border-radius:50%;border:3px solid rgba(41,98,255,.25);border-top-color:#2962ff;animation:sp63 .8s linear infinite}.bz63{display:flex;gap:12px;align-items:center;margin:10px 0 0;padding:12px 14px;border-radius:10px;background:rgba(41,98,255,.1);border:1px solid rgba(41,98,255,.35)}.bz63 b{font-size:15px}.bar63{height:6px;border-radius:3px;background:var(--ln,#2a2e39);margin-top:6px;overflow:hidden}.bar63 i{display:block;height:6px;background:#2962ff;transition:width .4s}';document.head.appendChild(st)})();
 function busy63(){const t=FULL.busy||'';const m=t.match(/(\d+)\s*из\s*(\d+)/);const pc=m?Math.min(100,+m[1]/Math.max(1,+m[2])*100):0;
  return `<div class="bz63"><span class="sp63"></span><div style="flex:1;min-width:0"><b>${esc(t)}</b>${m?`<div class="bar63"><i style="width:${pc.toFixed(0)}%"></i></div>`:''}<div class="sm mu" style="margin-top:4px">Держите экран открытым — системы появятся, когда сборка дойдёт до конца.</div></div></div>`}
 const _fb63=fBody;
 fBody=function(){let h=_fb63();
  if(!FULL.running&&FULL.busy){const p=`<p class="mu sm">${esc(FULL.busy)}</p>`;h=h.indexOf(p)>=0?h.replace(p,busy63()):h;
   h=h.replace(/(Системы по длине серии<\/h3>)/,`$1<div class="bz63" style="margin:4px 0 8px"><span class="sp63"></span><div class="sm">Собираю итоги из базы — числа систем обновятся в конце.</div></div>`)}
  if(!SIGS)return h;
  const cur=_fs63(),L=SIGS.slice(0,4);
  const note=`<div class="sm mu" style="margin:8px 0 0;line-height:1.5">${ic('history',14)} В базе проверок: ${L.length?L.map(x=>`<b>${x.n}</b> монет от ${d63(+x.t)}${x.sig===cur?' (текущие настройки)':x.sig===ALT?' (показаны эти)':''}`).join(' · '):'пусто'}${ALT?'<br>Число стратегий с прошлой проверки поменялось — показаны итоги последней проверки с теми же рынком и фильтрами. Новая проверка на сервере обновит их.':''}</div>`;
  const i=h.indexOf('Готово <b>');if(i<0)return h+note;const j=h.indexOf('</div>',i);return j>0?h.slice(0,j+6)+note+h.slice(j+6):h+note};
})();
