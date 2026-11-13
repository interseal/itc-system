// ИТК Трейдинг — «Тест всех систем» (10.11.2026).
// Для текущей монеты прогоняет все наши стратегии на свечах 1 мин, 5 мин, 15 мин, 1 час, 4 часа, 1 день,
// на всех периодах проверки и со всеми правилами выхода (1 к 1, 1 к 2, 1 к 3, по сигналу; стоп подбирает система).
// Итог — список от лучшего к худшему: плюсовые, минусовые, совсем убыточные. Нажатие — подробная проверка этого варианта.
"use strict";
ICP.rank="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z";
const BA_TF=['1m','5m','15m','1h','4h','1d'],BA_MODES=[1,2,3,0];
let BA=null,BAJOB=0;
function baMode(k){return k?'1 к '+k:'стоп + выход по сигналу'}
const baDirN=d=>d===1?'только лонг':d===-1?'только шорт':'лонг и шорт';
const baTfn=tf=>(BTF.find(x=>x[0]===tf)||[0,tf])[1];
const baYield=()=>new Promise(r=>setTimeout(r,0));
async function baRun(){const job=++BAJOB,mkt=UI.mkt,sym=UI.sym,fee=mkt==='fut'?0.05:0.1,spot=mkt==='spot',L=[],keep=UI.btRR,keepD=UI.btDir,DIRS=spot?[1]:[0,1,-1];
 BA={busy:'Начинаю…',sym,mkt,rows:[]};baPaint();
 try{for(let ti=0;ti<BA_TF.length;ti++){const tf=BA_TF[ti],P=BTPof(tf),maxD=Math.max(...P.map(p=>p[0]));
   let c;try{c=await histK(mkt,sym,tf,maxD,300,(k,t)=>{if(job!==BAJOB)return;BA.busy=`Загружаю историю: свечи ${baTfn(tf)} — ${k} из ${t}`;baPaint()})}catch(e){BA.skip=(BA.skip||[]).concat(baTfn(tf));continue}
   if(job!==BAJOB)return;if(c.length<350){BA.skip=(BA.skip||[]).concat(baTfn(tf));continue}
   for(const s of famList()){if(job!==BAJOB)return;BA.busy=`Считаю: свечи ${baTfn(tf)} · ${s.name}`;baPaint();await baYield();
    const p={};s.params.forEach(q=>p[q.k]=q.v);let sg;try{sg=s.run(c,p)}catch(_){continue}
    for(const per of P){const n=Math.round(per[0]*86400e3/TFMS(tf)),from=Math.max(300,c.length-n);if(c.length-from<20)continue;
     for(const dr of DIRS)for(const k of BA_MODES){UI.btRR=k;UI.btDir=dr;let r;try{r=btRun(c,s,from,spot,fee,sg)}catch(_){continue}
      L.push({dir:dr,tf,days:per[0],pname:per[1],strat:s.id,sname:s.name,k,stop:r.A.stop,n:r.A.n,win:r.A.win,ret:r.A.ret,dd:r.A.dd,ex:r.A.ex,sum:r.A.ex*r.A.n,day:r.A.ex*r.A.n/per[0]})}}}}
 }finally{UI.btRR=keep;UI.btDir=keepD}
 if(job!==BAJOB)return;
 // для каждой стратегии + свечей + периода оставляем лучшее правило выхода, остальные — в подробностях
 const best={};L.forEach(x=>{const key=x.tf+'|'+x.days+'|'+x.strat+'|'+x.dir;if(!best[key]||x.sum>best[key].sum)best[key]=x});
 const rows=Object.values(best).sort((a,b)=>b.day-a.day);
 BA={sym,mkt,rows,all:L,t:Date.now(),skip:BA.skip,show:{p0:15,p1:15,p2:15,neg:15,bad:15,few:10}};LS.set('ballres',{sym,mkt,rows,t:BA.t,skip:BA.skip});baPaint()}
function baRow(x){const cls=x.sum>0?'g':'r',wr=x.n?Math.round(x.win/x.n*100):0;
 return `<div class="plan ${x.sum>0?'long':'short'}" data-a="bago" data-v="${x.tf}|${x.days}|${x.strat}|${x.k}|${x.dir||0}" style="padding:8px 10px">
 <div class="row" style="margin:0;align-items:center"><b style="flex:1">${x.sname}</b><b class="${cls}">${pp(x.day,2)} в день</b></div>
 <div class="sm mu" style="margin-top:2px">Свечи ${baTfn(x.tf)} · за ${x.pname.toLowerCase()} · <b>${baDirN(x.dir||0)}</b> · ${baMode(x.k)}, стоп −${pf(x.stop)}%</div>
 <div class="sm" style="margin-top:2px">За период <b class="${cls}">${pp(x.sum)}</b> · сделок ${x.n} · прибыльных ${wr}% · в среднем на сделку <span class="${x.ex>=0?'g':'r'}">${pp(x.ex,2)}</span> · просадка ${x.dd<0.05?'0%':'−'+fmt(x.dd,1)+'%'}</div></div>`}
function baList(){const B=BA;if(!B)return `<p class="hint">Программа проверит все наши стратегии для ${UI.sym} (${UI.mkt==='fut'?'фьючерсы':'спот'}) на свечах 1 мин, 5 мин, 15 мин, 1 час, 4 часа и 1 день, на всех периодах проверки и со всеми правилами: 1 к 1, 1 к 2, 1 к 3, выход по сигналу и «по уровням» (стоп за фигурой, тейк у следующего уровня). Стоп система подбирает сама. На фьючерсах — отдельно лонг и шорт, только лонг и только шорт. Это займёт 2–5 минут.</p><button class="go b" data-a="bastart">Начать тест ${UI.sym}</button>`;
 if(B.busy)return `<p class="mu">${B.busy}</p><p class="hint">Не закрывайте это окно до конца теста.</p>`;
 const F=B.f==null?'a':B.f,RW=B.rows.filter(x=>F==='a'||(x.dir||0)===+F),ok=RW.filter(x=>x.n>=10),few=RW.filter(x=>x.n<10),pos=ok.filter(x=>x.sum>0),p0=pos.filter(x=>x.dd<0.05),p1=pos.filter(x=>x.dd>=0.05&&x.sum>=3*x.dd),p2=pos.filter(x=>x.dd>=0.05&&x.sum<3*x.dd),neg=ok.filter(x=>x.sum<=0&&x.sum>-10),bad=ok.filter(x=>x.sum<=-10);
 const grp=(id,title,hint,arr,color)=>arr.length?`<h3 style="margin:14px 0 4px" class="${color}">${title} · ${arr.length}</h3><p class="hint" style="margin-top:0">${hint}</p>${arr.slice(0,(B.show[id]||15)).map(baRow).join('')}${arr.length>(B.show[id]||15)?`<button class="btn2" style="width:100%;padding:10px" data-a="bamore" data-v="${id}">Показать ещё (${arr.length-(B.show[id]||15)})</button>`:''}`:'';
 const top=p0[0]||p1[0]||p2[0];
 return `<div class="wbox"><b>${B.sym}</b> <span class="mu sm">${B.mkt==='fut'?'фьючерсы':'спот'} · проверено вариантов: ${(B.all||[]).length||B.rows.length*5} · ${new Date(B.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}</span></div>
 ${top?`<div class="serban" data-a="bago" data-v="${top.tf}|${top.days}|${top.strat}|${top.k}|${top.dir||0}"><b>Лучше всего: ${top.sname}</b><div class="sm">Свечи ${baTfn(top.tf)}, ${baDirN(top.dir||0)}, ${baMode(top.k)}, стоп −${pf(top.stop)}%. В среднем ${pp(top.day,2)} в день, за ${top.pname.toLowerCase()} ${pp(top.sum)}, сделок ${top.n}.</div></div>`:'<p class="err">Ни одна система не дала прибыли с достаточным числом сделок.</p>'}
 ${B.mkt==='fut'?`<div class="pct" style="margin:10px 0">${[['a','Все'],['0','Лонг и шорт'],['1','Только лонг'],['-1','Только шорт']].map(q=>`<button class="${F===q[0]?'on':''}" data-a="bafil" data-v="${q[0]}">${q[1]}</button>`).join('')}</div>`:''}
 ${B.skip&&B.skip.length?`<p class="hint">Не удалось загрузить свечи: ${B.skip.join(', ')}.</p>`:''}
 ${grp('p0','Плюсовые без просадки','Дали прибыль, и счёт ни разу не опускался ниже прежнего максимума. Сверху — самые доходные в пересчёте на один день.',p0,'g')}
 ${grp('p1','Плюсовые с небольшой просадкой','Прибыль за период как минимум в 3 раза больше самой глубокой просадки.',p1,'g')}
 ${grp('p2','Плюсовые с заметной просадкой','В плюсе, но по дороге счёт проседал почти на столько же, сколько заработал, — рискованно.',p2,'')}
 ${grp('neg','Минусовые','Небольшой убыток — до −10% за период.',neg,'r')}
 ${grp('bad','Совсем убыточные','Потеряли больше 10% за период — по ним не торговать.',bad,'r')}
 ${grp('few','Мало сделок','Меньше 10 сделок за период — вывод ненадёжен.',few,'mu')}
 <p class="hint">Считаем так, как торгует серия: одна и та же ставка на каждую сделку, проценты к ставке, с учётом комиссии. Чтобы сравнивать короткие и длинные периоды, список отсортирован по доходу в среднем за день. Для каждой стратегии, свечей и периода показано лучшее правило выхода. Нажмите на строку — откроется подробная проверка этого варианта. Прошлый результат не гарантирует будущий.</p>
 <button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="bastart">Пройти тест заново</button>`}
function baSheet(){return sheetWrap(`${ic('rank',20)}&nbsp;Тест всех систем`,`<div id="bares">${baList()}</div>`)}
function baPaint(){const e=$('#bares');if(e)e.innerHTML=baList()}
const _r20=render;render=function(){if(SHEET&&SHEET[0]==='ball'){SHEET=null;_r20();SHEET=['ball'];$('#app').insertAdjacentHTML('beforeend',baSheet());return}_r20()};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='ballopen'){e.stopPropagation();if(!BA||BA.sym!==UI.sym||BA.mkt!==UI.mkt){const o=LS.get('ballres2',null);BA=o&&o.sym===UI.sym&&o.mkt===UI.mkt&&!BA?Object.assign(o,{show:{p0:15,p1:15,p2:15,neg:15,bad:15,few:10}}):(BA&&BA.busy?BA:null)}SHEET=['ball'];render();history.pushState({sh:1},'')}
 else if(a==='bastart'){e.stopPropagation();baRun()}
 else if(a==='bafil'){e.stopPropagation();BA.f=v;baPaint()}
 else if(a==='bamore'){e.stopPropagation();BA.show[v]=(BA.show[v]||15)+30;baPaint()}
 else if(a==='bago'){e.stopPropagation();const [tf,d,st,k,dr]=v.split('|');UI.btS=st;UI.btTf=tf;UI.btP=+d;UI.btRR=+k;UI.btDir=+(dr||0);saveUI();BT7=null;SHEET=['bt'];render();btGo()}},true);
// кнопка в окне проверки на истории и рядом с ней на экране графика
const _bs20=btSheet;btSheet=function(){return _bs20().replace('<div id="btres">',`<button class="btn2" style="width:100%;margin-top:8px;padding:10px" data-a="ballopen">${ic('rank',18)} Тест всех систем — какая лучше для ${UI.sym}</button><div id="btres">`)};
const _tv20=termView;termView=function(){return _tv20().replace(/(<button class="chip[^"]*" data-a="bt7open">)/,`<button class="chip" data-a="ballopen">${ic('rank',18)} Тест всех систем</button>$1`)};
render();
