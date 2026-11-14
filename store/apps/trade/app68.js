// Alex Trade — №572 (14.11.2026): «Анализ» не открывался у руководителя (кнопка внизу «не нажимается»).
// Если какая-то вкладка «Анализа» падает с ошибкой, экран больше не застывает: показываем, что случилось,
// и кнопки «Открыть „Новая проверка“» / «Вся биржа» — чтобы можно было работать и прислать снимок Claude.
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const _lv68=labView;
 labView=function(){try{return _lv68()}catch(e){const t=UI.labTab||'tests';try{console.error('labView',t,e)}catch(_){}
  const msg=String(e&&e.message||e).slice(0,200),where=String(e&&e.stack||'').split('\n').slice(1,3).join(' ').replace(/https?:\/\/[^/]+/g,'').slice(0,200);
  let h='';try{h=head()}catch(_){}
  return h+`<div class="card"><h3 style="margin:0 0 8px">${ic('rank',20)} Анализ</h3><p class="err">Вкладка «${esc(t)}» не открылась: ${esc(msg)}</p><p class="sm mu">${esc(where)}</p>
   <p class="sm mu">Сделайте снимок экрана и пришлите Claude — он исправит.</p>
   <div class="row" style="gap:6px"><button class="btn2" style="flex:1" data-a="labtab" data-v="tests">Новая проверка</button><button class="btn2" style="flex:1" data-a="labtab" data-v="full">Вся биржа</button><button class="btn2" style="flex:1" data-a="labtab" data-v="arch">Архив</button></div></div>`}};
})();
