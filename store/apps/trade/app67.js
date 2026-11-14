// Alex Trade — №571 (14.11.2026): «стеклянная» шапка у торговли на нескольких монетах.
// Руководитель: название, «идёт N из N» и строка «на сервере · ставка…» сливались с цифрами ниже —
// шапку выделяем стеклянной плашкой, как кнопки на сайте, чтобы сразу было видно, где название.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const _pk67=pkCard;
 pkCard=function(gid,L,compact){const h=_pk67(gid,L,compact);if(compact)return h;
  const m=h.match(/^<div class="plan[^"]*"[^>]*>/);const k=h.indexOf('<div class="sgrid">');if(!m||k<0)return h;
  return m[0]+'<div class="pkhead">'+h.slice(m[0].length,k)+'</div>'+h.slice(k)};
 const st=document.createElement('style');st.textContent=`
 .pkhead{margin:-2px -2px 10px;padding:12px 14px 10px;border-radius:14px;
  background:linear-gradient(180deg,rgba(255,255,255,.13),rgba(255,255,255,.05));
  border:1px solid rgba(255,255,255,.20);box-shadow:inset 0 1px 0 rgba(255,255,255,.18),0 6px 18px rgba(0,0,0,.28);
  -webkit-backdrop-filter:blur(12px) saturate(140%);backdrop-filter:blur(12px) saturate(140%)}
 .pkhead>.row b{font-size:16px;line-height:1.3}
 .pkhead>.sm.mu{margin-top:4px}
 .pkhead>div[style*="height:6px"]{margin:8px 0 0!important;background:rgba(255,255,255,.12)!important}
 .plan.long .pkhead{border-color:rgba(8,153,129,.45)} .plan.short .pkhead{border-color:rgba(242,54,69,.40)}`;
 document.head.appendChild(st);
})();
