// ИТК Трейдинг — системы по группам (11.11.2026): можно проверять не все стратегии сразу, а одну группу — быстрее и нагляднее.
"use strict";
const FAMS=[
 ['all','Все системы','все стратегии сразу',null],
 ['trend','Тренд','идём по направлению движения',['ema','st','macd','ichi','psar','adx','ha','pull','rsi']],
 ['rev','Откат и возврат','покупаем провал, продаём перегрев',['rsi2','mtf','bb','stoch','cci','zrev','div']],
 ['brk','Пробой','вход, когда цена вырвалась из диапазона',['don','sqz','inside','volbo','orb']],
 ['scalp','Скальпинг','короткие сделки на минутках',['vwapm','kelt','rsibb','sfp','mpull']],
 ['btc','По биткоину','монета идёт за биткоином',['btcl','btct','lockb']],
 ['lock','Замок','лонг и шорт одновременно',['lockr','lockt','locks','lockb']],
 ['fig','Фигуры и уровни','фигуры, отскоки от уровней',['pat','lvl']],
 ['tv','TradingView','популярные и новые идеи с TradingView',['tvut','tvrf','tvce','tvssl','tvwt','tvhull','tvqqe','tvst200','tvsweep','tvfvg','tvdon','tvrsi70','tvvor','tvabs']]];
const famOf=k=>FAMS.find(f=>f[0]===k)||FAMS[0];
function famList(){const f=famOf(UI.fam||'all');return f[3]?ITS.list.filter(s=>f[3].includes(s.id)):ITS.list}
function famFrac(){return ITS.list.length?famList().length/ITS.list.length:1}
function famStep(){const cur=UI.fam||'all';return `<h3 style="margin:14px 0 4px">3. Какие системы проверять</h3><p class="hint" style="margin-top:0">Одна группа проверяется быстрее, и итоги групп легко сравнить между собой.</p>
 <div class="cpg" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px">${FAMS.map(f=>{const n=f[3]?ITS.list.filter(s=>f[3].includes(s.id)).length:ITS.list.length;return `<button class="${cur===f[0]?'on':''}" data-a="fam" data-v="${f[0]}" style="text-align:left;padding:10px 12px;display:block;width:100%"><b>${f[1]}</b> <span class="sm" style="opacity:.8">· ${n}</span><br><span class="sm" style="opacity:.75">${f[2]}</span></button>`}).join('')}</div>
 ${cur!=='all'?`<p class="sm mu" style="margin:4px 0 0">В группе: ${famList().map(s=>s.name).join(', ')}.</p>`:''}`}
window.addEventListener('click',e=>{const el=e.target.closest('[data-a="fam"]');if(!el)return;e.stopPropagation();e.preventDefault();UI.fam=el.dataset.v;saveUI();
 if((UI.labTab||'tests')==='coins'){const x=$('#labcoint');if(x)x.innerHTML=coinsTest()}else baPaint()},true);
// в «Итогах» и в названии теста — какая группа систем проверялась
if(typeof resName==='function'){const _rn42=resName;resName=function(B){const n=_rn42(B),f=famOf(UI.fam||'all');return f[0]==='all'?n:n+' · '+f[1]}}
Object.assign(ITS.rules||{},{
 tvut:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR от входа. Выход — цена пересекла линию UT Bot в обратную сторону.'},
 tvrf:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR. Выход — фильтр диапазона развернулся.'},
 tvce:{stop:'atr',k:3,take:'sig',txt:'Стоп 3 ATR. Выход — цена пробила линию Шандельера против сделки.'},
 tvssl:{stop:'swing',n:10,take:'sig',txt:'Стоп за минимумом/максимумом 10 свечей. Выход — канал SSL перевернулся.'},
 tvwt:{stop:'swing',n:10,take:'sig',txt:'Стоп за экстремумом 10 свечей. Выход — обратное пересечение линий WaveTrend.'},
 tvhull:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR. Выход — средняя Халла развернулась.'},
 tvqqe:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR. Выход — RSI пробил границу коридора в обратную сторону.'},
 tvst200:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR. Выход — Супертренд развернулся против сделки.'},
 tvsweep:{stop:'bar',take:'R',R:2,txt:'Стоп за тенью прокола. Тейк в 2 раза дальше стопа.'},
 tvfvg:{stop:'swing',n:5,take:'R',R:2,txt:'Стоп за экстремумом 5 свечей. Тейк в 2 раза дальше стопа.'},
 tvdon:{stop:'atr',k:2,take:'sig',txt:'Стоп 2 ATR. Выход — цена вернулась за середину канала.'},
 tvrsi70:{stop:'atr',k:3,take:'sig',txt:'Стоп 3 ATR. Выход — RSI вернулся к 50.'},
 tvvor:{stop:'swing',n:5,take:'R',R:2,txt:'Стоп за экстремумом 5 свечей. Тейк в 2 раза дальше стопа.'},
 tvabs:{stop:'bar',take:'R',R:2,txt:'Стоп за свечой. Тейк в 2 раза дальше стопа.'}});
render();
