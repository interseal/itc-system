// Alex Trade — значок на рабочем столе (10.11.2026).
// Приложение можно установить как программу: на Windows — значок на рабочем столе и в меню «Пуск», открывается в своём окне без адресной строки.
"use strict";
let INSTP=null;
if('serviceWorker' in navigator){try{navigator.serviceWorker.register('sw.js',{scope:'./'}).catch(()=>{})}catch(_){}}
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();INSTP=e;try{render()}catch(_){}});
window.addEventListener('appinstalled',()=>{INSTP=null;toast('Готово: значок «Alex Trade» появился на рабочем столе');try{render()}catch(_){}});
const isApp=()=>window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches;
function instBox(){if(isApp())return '';
 return `<div class="wbox" style="margin-bottom:10px"><b>${ic('download',18)} Значок на рабочем столе</b>
 ${INSTP?`<p class="sm" style="margin:4px 0 8px">Установите «Alex Trade» как программу: появится значок на рабочем столе, приложение будет открываться в своём окне.</p><button class="go b" data-a="instapp">Поставить значок на рабочий стол</button>`
 :`${isPC()?`<p class="sm" style="margin:4px 0 8px">Скачайте значок и перетащите его из «Загрузок» на рабочий стол.</p><button class="go b" data-a="instapp">Скачать значок для рабочего стола</button>`:''}<p class="sm" style="margin:8px 0 0">Или: откройте приложение в браузере Яндекс, Chrome или Edge и нажмите значок установки справа в адресной строке (монитор со стрелкой) — или в меню браузера выберите «Установить приложение». На телефоне: меню браузера → «Добавить на главный экран».</p>`}</div>`}
const _ss32=settingsSheet;settingsSheet=function(){const h=_ss32();return h.replace('<h4>Вид</h4>',instBox()+'<h4>Вид</h4>')};
// кнопка в полосе вкладок, пока приложение не установлено и браузер готов его установить
const isPC=()=>!/Android|iPhone|iPad|Mobile/i.test(navigator.userAgent);
const _tb32=tabsBar;tabsBar=function(){const h=_tb32();return (INSTP||isPC())&&!isApp()&&!LS.get('pcicon',0)?h.replace('<div class="bar tabsbar">',`<div class="bar tabsbar"><button class="chip" data-a="instapp">${ic('download',18)} На рабочий стол</button>`):h};
document.addEventListener('click',async e=>{const el=e.target.closest('[data-a]');if(!el||el.dataset.a!=='instapp')return;e.stopPropagation();
 if(!INSTP){if(isPC()){pcIcon();return}toast('Меню браузера → «Добавить на главный экран»');return}
 INSTP.prompt();try{await INSTP.userChoice}catch(_){}INSTP=null;render()},true);
// если браузер сам не предлагает установку — скачиваем готовый значок Windows: его остаётся перетащить на рабочий стол
function pcIcon(){const url=location.origin+'/apps/trade/',txt=['[InternetShortcut]','URL='+url,'IconFile='+location.origin+'/apps/trade/itk.ico','IconIndex=0',''].join('\r\n');
 const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([txt],{type:'application/octet-stream'}));a.download='Alex Trade.url';document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},2000);
 LS.set('pcicon',1);SHEET=['pcicon'];render()}
const _r32=render;render=function(){if(SHEET&&SHEET[0]==='pcicon'){SHEET=null;_r32();SHEET=['pcicon'];$('#app').insertAdjacentHTML('beforeend',sheetWrap(`${ic('download',20)}&nbsp;Значок скачан`,`<p>Файл <b>«Alex Trade»</b> с нашим значком сохранён в папку «Загрузки».</p>
 <p><b>Осталось одно:</b> откройте «Загрузки» (значок со стрелкой вниз справа вверху браузера) и перетащите «Alex Trade» мышкой на рабочий стол.</p>
 <p class="hint">Двойной щелчок по значку откроет «Alex Trade» в браузере. Кнопка «На рабочий стол» больше не будет показываться; повторно скачать значок можно в «Настройках».</p><button class="go b" data-a="shclose">Хорошо</button>`));return}_r32()};
render();
