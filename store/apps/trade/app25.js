// ИТК Трейдинг — «Тест всех систем на 10 монетах» (10.11.2026).
// Ищем не лучшую систему для одной монеты (это легко подогнать), а ту, что зарабатывает на большинстве монет.
// Свечи 1 час, 4 часа, 1 день; честная проверка (подбор на первой половине, итог — на второй); одни и те же правила для всех монет.
"use strict";
const BA10_TF=['1h','4h','1d'],BA10_DAYS={'1h':365,'4h':365,'1d':730};
const CGROUPS=[['Топ-10',['BTC','ETH','SOL','XRP','BNB','DOGE','ADA','AVAX','LINK','LTC']],['Крупные',['TRX','DOT','BCH','NEAR','ATOM','UNI','APT','ARB','OP','FIL']],['Новые сети',['SUI','INJ','TIA','SEI','AAVE','ETC','XLM','HBAR','ICP','TON']],
 ['ИИ и игры',['FET','RENDER','GRT','IMX','LDO','SAND','MANA','AXS','GALA','CRV']],['Молодые',['WIF','ORDI','JUP','PYTH','ENA','WLD','STX','ALGO','VET','THETA']],['Разные',['RUNE','DYDX','EGLD','KAVA','ZEC','DASH','COMP','SNX','ONDO','TAO']]];
const cgSel=()=>{const g=Array.isArray(UI.cgs)&&UI.cgs.length?UI.cgs:[0];return g.filter(i=>CGROUPS[i])};
const BAC=()=>cgSel().flatMap(i=>CGROUPS[i][1]);
const cgOf=sym=>{const g=CGROUPS.find(x=>x[1].includes(sym));return g?g[0]:''};
function cgChips(){const S=cgSel();return `<div class="row" style="align-items:flex-start"><label>Монеты</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${CGROUPS.map((g,i)=>`<button class="${S.includes(i)?'on':''}" data-a="cgrp" data-v="${i}">${g[0]}</button>`).join('')}<button data-a="cgrp" data-v="all">Все 60</button></div></div>
 <p class="hint" style="margin-top:0">Выбрано ${S.length*10} монет: ${S.map(i=>CGROUPS[i][0]).join(', ')}. Примерно ${S.length*5}–${S.length*12} минут.</p>`}
async function ba10Run(){const job=++BAJOB,COINS=BAC(),T0=Date.now(),mkt=UI.mkt,fee=mkt==='fut'?0.05:0.1,spot=mkt==='spot',keep=[UI.btRR,UI.btDir,UI.btHonest],DIRS=spot?[1]:[0,1,-1],agg={},skip=[];
 BA={busy:'Начинаю…',kind:'multi',rows:[]};baPaint();UI.btHonest=1;
 try{for(let ci=0;ci<COINS.length;ci++){const sym=COINS[ci];const eta=ci?` · осталось около ${Math.max(1,Math.round((Date.now()-T0)/ci*(COINS.length-ci)/60e3))} мин`:'';
   for(const tf of BA10_TF){const days=BA10_DAYS[tf];let c;
    try{c=await histK(mkt,sym,tf,days,300,(k,t)=>{if(job!==BAJOB)return;BA.busy=`Монета ${ci+1} из ${COINS.length} · ${sym} · загружаю свечи ${baTfn(tf)} — ${k} из ${t}${eta}`;baPaint()})}catch(e){skip.push(sym+' '+baTfn(tf));continue}
    if(job!==BAJOB)return;if(c.length<350){skip.push(sym+' '+baTfn(tf));continue}
    BTTF=tf;HTFCUR=null;if(UI.btHtf){try{HTFCUR=await htfLoad(mkt,sym,tf,days)}catch(_){}}
    const n=Math.round(days*86400e3/TFMS(tf)),from=Math.max(300,c.length-n),tdays=(c[c.length-1].time-c[from+Math.floor((c.length-from)/2)].time)/86400;
    for(const s of famList()){if(job!==BAJOB)return;BA.busy=`Монета ${ci+1} из ${COINS.length} · ${sym} · свечи ${baTfn(tf)} · ${s.name}${eta}`;baPaint();await baYield();
     const p={};s.params.forEach(q=>p[q.k]=q.v);let sg;try{sg=s.run(c,p)}catch(_){continue}
     for(const dr of DIRS)for(const k of BA_MODES){UI.btRR=k;UI.btDir=dr;let r;try{r=btRun(c,s,from,spot,fee,sg)}catch(_){continue}const H=r.honest||{},key=tf+'|'+s.id+'|'+dr+'|'+k;
      const g=agg[key]||(agg[key]={tf,strat:s.id,sname:s.name,dir:dr,k,days,coins:[]});g.coins.push({sym,n:r.A.n,ex:r.A.ex,sum:r.A.ex*r.A.n,pos:H.pos||0,dd:r.A.dd,stop:r.A.stop,tdays})}}}}
 }finally{[UI.btRR,UI.btDir,UI.btHonest]=keep}
 if(job!==BAJOB)return;
 const rows=Object.values(agg).map(g=>{const cs=g.coins,tr=cs.reduce((a,x)=>a+x.n,0),act=cs.filter(x=>x.n>=3);
  return Object.assign(g,{nc:cs.length,act:act.length,plus:act.filter(x=>x.sum>0).length,stable:act.filter(x=>x.sum>0&&x.pos>=3).length,trades:tr,
   avgEx:tr?cs.reduce((a,x)=>a+x.ex*x.n,0)/tr:0,avgSum:act.length?act.reduce((a,x)=>a+x.sum,0)/act.length:0,worst:act.length?Math.min(...act.map(x=>x.sum)):0,
   tdays:cs.length?cs[0].tdays:0})}).filter(g=>g.act>=5);
 // одинаковые строки (например, «лонг и шорт» и «только шорт» с одинаковым итогом) не повторяем
 const seen={},uniq=rows.filter(g=>{const k=g.tf+'|'+g.strat+'|'+g.k+'|'+g.coins.map(x=>x.n+':'+x.sum.toFixed(3)).join(',');if(seen[k])return false;seen[k]=1;return true});
 uniq.sort((a,b)=>(b.plus/b.act-a.plus/a.act)||(b.stable-a.stable)||(b.avgEx-a.avgEx));
 BA={kind:'multi',coins:COINS,rows:uniq,t:Date.now(),skip,mkt,fl:{htf:!!UI.btHtf,adx:!!UI.btAdx,maker:!!UI.btMaker&&!spot},show:{m7:15,m5:15,m0:10}};
 try{LS.set('ballres10',{kind:'multi',coins:COINS,rows:uniq.slice(0,120),t:BA.t,skip,mkt,fl:BA.fl})}catch(_){}baPaint()}
function ba10Row(g){const share=g.act?g.plus/g.act:0,cls=share>=0.7?'g':share>=0.5?'':'r';
 return `<div class="plan ${g.avgSum>0?'long':'short'}" data-a="bago" data-v="${g.tf}|${g.days}|${g.strat}|${g.k}|${g.dir||0}" style="padding:8px 10px">
 <div class="row" style="margin:0;align-items:center"><b style="flex:1">${g.sname}</b><b class="${cls}">в плюсе ${g.plus} из ${g.act}</b></div>
 <div class="sm mu" style="margin-top:2px">Свечи ${baTfn(g.tf)} · <b>${baDirN(g.dir||0)}</b> · ${baMode(g.k)} · вслепую за ${fmt(g.tdays,0)} дн.</div>
 <div class="sm" style="margin-top:2px">Устойчиво (3–4 части из 4) на ${g.stable} монетах · сделок всего ${g.trades} · на сделку <span class="${g.avgEx>=0?'g':'r'}">${pp(g.avgEx,2)}</span> · в среднем на монету <span class="${g.avgSum>=0?'g':'r'}">${pp(g.avgSum)}</span> · худшая монета <span class="${g.worst>=0?'g':'r'}">${pp(g.worst)}</span></div>
 ${g.coins.length<=12?`<div class="sm" style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px">${g.coins.map(x=>`<span style="padding:1px 6px;border-radius:6px;border:1px solid var(--ln)" class="${x.n<3?'mu':x.sum>0?'g':'r'}">${x.sym} ${x.n<3?'—':pp(x.sum,0)}</span>`).join('')}</div>`
  :`<div class="sm" style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px">${[...new Set(g.coins.map(x=>cgOf(x.sym)))].map(gn=>{const cs=g.coins.filter(x=>cgOf(x.sym)===gn&&x.n>=3),pl=cs.filter(x=>x.sum>0).length;return `<span style="padding:1px 6px;border-radius:6px;border:1px solid var(--ln)" class="${cs.length&&pl/cs.length>=0.7?'g':pl/cs.length>=0.5?'':'r'}">${gn} ${pl}/${cs.length}</span>`}).join('')}</div>
   ${(()=>{const L=g.coins.filter(x=>x.n>=3&&x.sum<=0);return L.length?`<div class="sm" style="margin-top:4px">В минусе: <span class="r">${L.map(x=>x.sym+' '+pp(x.sum,0)).join(', ')}</span></div>`:''})()}`}
 <button class="btn2 sm" style="margin-top:6px;padding:4px 10px" data-a="depopen" data-v="${g.tf}|${g.strat}|${g.dir||0}|${g.k}">${ic('wallet',16)} Сколько это на депозит</button></div>`}
function ba10List(){const B=BA;if(B.busy)return `<p class="mu">${B.busy}</p><p class="hint">Тест на многих монетах идёт долго — около 5–12 минут на каждые 10 монет. Не закрывайте это окно и не выключайте экран.</p>`;
 const R=B.rows,m7=R.filter(g=>g.plus/g.act>=0.7),m5=R.filter(g=>g.plus/g.act>=0.5&&g.plus/g.act<0.7),m0=R.filter(g=>g.plus/g.act<0.5),fl=B.fl||{},fs=[fl.htf?'по старшему тренду':'',fl.adx?'без боковика':'',fl.maker?'лимитные заявки':''].filter(Boolean);
 const grp=(id,title,hint,arr,color)=>arr.length?`<h3 style="margin:14px 0 4px" class="${color}">${title} · ${arr.length}</h3><p class="hint" style="margin-top:0">${hint}</p>${arr.slice(0,(B.show[id]||15)).map(ba10Row).join('')}${arr.length>(B.show[id]||15)?`<button class="btn2" style="width:100%;padding:10px" data-a="bamore" data-v="${id}">Показать ещё (${arr.length-(B.show[id]||15)})</button>`:''}`:'';
 const top=m7.filter(g=>g.trades>=60&&g.avgEx>=0.3).sort((a,b)=>(b.stable-a.stable)||(b.avgEx-a.avgEx))[0];
 return `<div class="wbox"><b>${(B.coins||COINS10).length} монет</b> <span class="mu sm">${(B.coins||COINS10).length>10?[...new Set((B.coins||COINS10).map(cgOf))].join(', '):(B.coins||COINS10).join(', ')} · ${B.mkt==='fut'?'фьючерсы':'спот'} · честная проверка${fs.length?' · '+fs.join(' · '):''} · ${new Date(B.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}</span></div>
 ${top?`<div class="serban" data-a="bago" data-v="${top.tf}|${top.days}|${top.strat}|${top.k}|${top.dir||0}"><b>${ic('verified',16)} Лучшая для всех монет: ${top.sname}</b> <button class="btn2 sm" style="padding:2px 8px" data-a="depopen" data-v="${top.tf}|${top.strat}|${top.dir||0}|${top.k}">${ic('wallet',14)} На депозит</button><div class="sm">Свечи ${baTfn(top.tf)}, ${baDirN(top.dir||0)}, ${baMode(top.k)}. Вслепую в плюсе на ${top.plus} монетах из ${top.act}, устойчиво на ${top.stable}; ${top.trades} сделок, в среднем ${pp(top.avgEx,2)} на сделку.</div></div>`
  :'<p class="err">Системы, которая честно зарабатывает хотя бы на 70% монет с запасом от +0,3% на сделку, не нашлось. Это важный вывод: простые индикаторы сами по себе после комиссий преимущества не дают.</p>'}
 ${B.skip&&B.skip.length?`<p class="hint">Не удалось загрузить: ${B.skip.join(', ')}.</p>`:''}
 ${grp('m7','Работают на большинстве монет','В плюсе вслепую на 70% монет и больше.',m7,'g')}
 ${grp('m5','Работают на половине монет','В плюсе на 50–69% монет.',m5,'')}
 ${grp('m0','Работают на отдельных монетах','Меньше половины монет в плюсе — это подгонка под отдельные монеты.',m0,'r')}
 <p class="hint">Для каждой системы правила одни и те же на всех монетах; стоп подбирается на первой половине истории каждой монеты, а итог — по второй. Монеты, где меньше 3 сделок, не считаются. Нажмите на строку — откроется проверка для текущей монеты.</p>
 ${fChips('ba')}${cgChips()}<button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="ba10start">Пройти тест на выбранных монетах заново</button>
 <button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="bastart">Тест только для ${UI.sym}</button>`}
const _bl25=baList;baList=function(){const B=BA;if(B&&B.kind==='multi')return ba10List();let h=_bl25();
 const btn=`<button class="go b" style="margin-top:8px" data-a="ba10start">${ic('coins',18)} Тест на многих монетах — найти систему для всех</button>`;
 if(!B)return h.replace('<p class="hint">Займёт 3–6 минут.</p>',`<p class="hint">Займёт 3–6 минут.</p>${btn}<p class="hint">Тест на многих монетах (свечи 1 час, 4 часа и 1 день) ищет систему, которая зарабатывает на большинстве монет, а не только на одной.</p>${cgChips()}`);
 if(!B.busy)h=h.replace('data-a="bastart">Пройти тест заново</button>',`data-a="bastart">Пройти тест заново</button>${btn}`);return h};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a;
 if(a==='ba10start'){e.stopPropagation();ba10Run()}
 else if(a==='cgrp'){e.stopPropagation();const v=el.dataset.v;let S=cgSel();if(v==='all')S=CGROUPS.map((_,i)=>i);else{const i=+v;S=S.includes(i)?S.filter(x=>x!==i):S.concat(i).sort();if(!S.length)S=[0]}UI.cgs=S;saveUI();baPaint()}
 else if(a==='ballopen'&&!BA){const o=LS.get('ballres10',null);if(o&&o.mkt===UI.mkt){BA=Object.assign(o,{show:{m7:15,m5:15,m0:10}});render()}}},true);
render();
