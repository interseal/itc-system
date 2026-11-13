// ИТК Трейдинг — честная проверка и фильтры (10.11.2026).
// 1) Честная проверка: стоп подбирается на первой половине истории, а результат считается на второй — которую система «не видела».
// 2) Устойчивость: вторая половина делится на 4 части; рабочая система в плюсе во всех четырёх.
// 3) Проверка на 10 монетах: те же настройки на BTC, ETH, SOL, XRP, BNB, DOGE, ADA, AVAX, LINK, LTC.
// 4) Фильтр старшего тренда: лонги — только когда на старших свечах цена выше средней за 200 свечей, шорты — когда ниже.
// 5) Фильтр боковика: не входить, когда ADX ниже 20 (рынок стоит на месте).
// 6) Лимитные заявки: комиссия фьючерсов 0,02% вместо 0,05% за сторону.
"use strict";
ICP.verified="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.54 6.71 4.74l-3.61.81.34 3.68L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.2 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z";
ICP.coins="M15 4c-4.42 0-8 3.58-8 8s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6zM3 12a5.99 5.99 0 014-5.65V4.26C3.55 5.15 1 8.27 1 12s2.55 6.85 6 7.74v-2.09A5.99 5.99 0 013 12z";
if(UI.btHonest==null)UI.btHonest=1;
const HTF_OF={'1m':'1h','5m':'1h','15m':'4h','30m':'4h','1h':'1d','4h':'1d'};
const HTFN={'1h':'1 час','4h':'4 часа','1d':'1 день'};
let HTFCUR=null;const HTFCACHE={};
async function htfLoad(mkt,sym,tf,days){const h=HTF_OF[tf];if(!h)return null;const key=mkt+sym+h,need=Date.now()/1000-days*86400-200*TFMS(h)/1000;
 const o=HTFCACHE[key];if(o&&o.from<=need&&Date.now()-o.at<10*60e3)return o;
 const c=await histK(mkt,sym,h,days+Math.ceil(TFMS(h)*230/86400e3),0);const e=ITS.ind.ema(c.map(x=>x.close),200);
 const r={key,h,ms:TFMS(h),t:c.map(x=>x.time),cl:c.map(x=>x.close),e,from:c.length?c[0].time:1e18,at:Date.now()};HTFCACHE[key]=r;return r}
function htfDir(H,t,tfms){// направление старшего тренда по уже закрытым старшим свечам на момент закрытия младшей свечи
 const lim=t+tfms/1000-H.ms/1000;let lo=0,hi=H.t.length-1,k=-1;while(lo<=hi){const m=(lo+hi)>>1;if(H.t[m]<=lim){k=m;lo=m+1}else hi=m-1}
 if(k<0||H.e[k]==null)return 0;return H.cl[k]>H.e[k]?1:-1}
function adxArr(c,n){const N=c.length,pd=[0],md=[0],tr=[0];for(let i=1;i<N;i++){const u=c[i].high-c[i-1].high,d=c[i-1].low-c[i].low;pd.push(u>d&&u>0?u:0);md.push(d>u&&d>0?d:0);tr.push(Math.max(c[i].high-c[i].low,Math.abs(c[i].high-c[i-1].close),Math.abs(c[i].low-c[i-1].close)))}
 const w=a=>{const r=Array(N).fill(null);let s=0;for(let i=1;i<N;i++){if(i<=n){s+=a[i];if(i===n)r[i]=s}else{s=s-s/n+a[i];r[i]=s}}return r};const T=w(tr),P=w(pd),M=w(md),A=Array(N).fill(null);let s=0,k=0;
 for(let i=0;i<N;i++){if(!T[i])continue;const p=100*P[i]/T[i],m=100*M[i]/T[i],dx=p+m?100*Math.abs(p-m)/(p+m):0;k++;if(k<n)s+=dx;else if(k===n){s+=dx;A[i]=s/n}else A[i]=(A[i-1]*(n-1)+dx)/n}return A}
// фильтры превращают запрещённый вход в сигнал «выход»: открытая сделка в другую сторону закроется, новая не откроется
function sigFilter(c,sig,tf){let o=sig;
 if(UI.btHtf&&HTFCUR&&HTF_OF[tf]===HTFCUR.h){const ms=TFMS(tf);o=o.map(x=>{if(x.t!=='long'&&x.t!=='short')return x;const d=htfDir(HTFCUR,c[x.i].time,ms);return (x.t==='long'&&d!==1)||(x.t==='short'&&d!==-1)?{i:x.i,t:'exit'}:x})}
 if(UI.btAdx){const A=adxArr(c,14);o=o.map(x=>(x.t==='long'||x.t==='short')&&!(A[x.i]>=20)?{i:x.i,t:'exit'}:x)}
 return o}
let BTTF=null;// время свечи текущего расчёта (нужно фильтру старшего тренда)
const _btr24=btRun;btRun=function(c,s,from,spot,fee,sigAll){
 if(UI.btMaker&&!spot)fee=0.02;
 const p={};s.params.forEach(q=>p[q.k]=q.v);let sg=sigAll||s.run(c,p);if(UI.btHtf||UI.btAdx)sg=sigFilter(c,sg,BTTF||UI.btTf||UI.tf);
 const K=[0,1,2,4,5].includes(UI.btRR)?UI.btRR:3,fit=K<=3;
 if(!UI.btHonest||c.length-from<40){const r=_btr24(c,s,from,spot,fee,sg);r.fee=fee;return r}
 const mid=from+Math.floor((c.length-from)/2);
 // 1) подбор на первой половине
 const kc=BTCAP;BTCAP=null;let r1;try{r1=_btr24(c.slice(0,mid+1),s,from,spot,fee,sg.filter(x=>x.i<mid))}finally{BTCAP=kc}const SL=fit?r1.A.stop:0;
 // 2) проверка на второй половине с найденным стопом
 const run=(a,b)=>{BTFORCE=SL;try{return _btr24(b<c.length?c.slice(0,b+1):c,s,a,spot,fee,b<c.length?sg.filter(x=>x.i<b):sg)}finally{BTFORCE=0}};
 const r=run(mid,c.length);BTCAP=null;
 // «Лучший вариант» и «Применить» — только честный стоп, подобранный на первой половине
 if(fit&&r.R){const b=r.R.find(x=>Math.abs(x.sl-SL)<1e-9);if(b)r.best=b}
 // 3) устойчивость: 4 части второй половины
 const q=Math.floor((c.length-mid)/4),parts=[];if(q>=10)for(let j=0;j<4;j++){const a=mid+j*q,b=j===3?c.length:mid+(j+1)*q;const x=run(a,b);parts.push(x.A.n?x.A.ex*x.A.n:0)}
 BTCAP=kc;r.honest={mid,t0:c[from].time,tm:c[mid].time,sl:SL,fitSum:r1.A.ex*r1.A.n,fitN:r1.A.n,parts,pos:parts.filter(v=>v>0).length};r.fee=fee;r.candles=c.length-mid;return r};
// btGo: подгружаем старшие свечи и помним время свечи
const _bg24=btGo;btGo=async function(){BTTF=UI.btTf||UI.tf;if(UI.btHtf){try{const d=UI.btP||30;HTFCUR=await htfLoad(UI.mkt,UI.sym,BTTF,d)}catch(_){HTFCUR=null}}return _bg24()};
function fChips(scope){const ch=(k,t,on)=>`<button class="${on?'on':''}" data-a="btflt" data-v="${k}" data-s="${scope}">${t}</button>`;
 return `<div class="row" style="align-items:flex-start"><label>Проверка</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap">${ch('btHonest','Честная (вслепую)',UI.btHonest)}${ch('btHtf','По старшему тренду',UI.btHtf)}${ch('btAdx','Без боковика',UI.btAdx)}${UI.mkt==='fut'?ch('btMaker','Лимитные заявки',UI.btMaker):''}</div></div>`}
const _bs24=btSheet;btSheet=function(){return _bs24().replace('<div class="row"><label>Стоп и тейк</label>',fChips('bt')+'<div class="row"><label>Стоп и тейк</label>')};
const fdate=t=>new Date(t*1000).toLocaleDateString('ru-RU',{day:'2-digit',month:'2-digit'});
function honestNote(B){const H=B.honest;if(!H)return '';const lbl=H.pos===4?'<b class="g">устойчиво: в плюсе все 4 части</b>':H.pos>=3?`<b>в плюсе ${H.pos} из 4 частей</b>`:`<b class="r">неустойчиво: в плюсе ${H.pos} из 4 частей</b>`;
 return `<div class="wbox" style="margin-top:8px">${ic('verified',16)} <b>Честная проверка.</b> <span class="sm">${H.sl?`Стоп −${pf(H.sl)}% подобран на ${fdate(H.t0)}–${fdate(H.tm)}. `:'Подбора нет — правило не зависит от истории. '}Все цифры ниже — по ${fdate(H.tm)}–${fdate(B.t1)}: эти свечи система не видела.${H.parts.length?` По четвертям: ${H.parts.map(v=>`<span class="${v>0?'g':'r'}">${pp(v)}</span>`).join(' · ')} — ${lbl}.`:''}</span></div>`}
const _bres24=btRes;btRes=function(){let h=_bres24();const B=BT7;if(!B||!B.A)return h;
 const f=[];if(UI.btHtf)f.push(HTF_OF[B.tf]?'по тренду '+(HTFN[HTF_OF[B.tf]]||''):'старших свечей нет');if(UI.btAdx)f.push('без боковика');if(B.fee===0.02)f.push('лимитные заявки');
 h=h.replace('</span></div>',`${f.length?' · '+f.join(' · '):''}</span></div>${honestNote(B)}`);
 return h.replace('<div id="btmoney">',`<button class="btn2" style="width:100%;margin:10px 0 0;padding:10px" data-a="coinsrun">${ic('coins',18)} Проверить эти настройки на 10 монетах</button><div id="coinres"></div><div id="btmoney">`)};
// ---------- проверка на 10 монетах ----------
const COINS10=['BTC','ETH','SOL','XRP','BNB','DOGE','ADA','AVAX','LINK','LTC'];let CJOB=0;
let CRUN=false;
function coinsBtn(t,busy){const b=$('[data-a=coinsrun]');if(!b)return;b.innerHTML=`${ic(busy?'history':'coins',18)} ${t}`;b.classList.toggle('on',!!busy);b.style.opacity=busy?'.85':''}
async function coinsRun(){if(CRUN){toast('Расчёт уже идёт — дождитесь, пока посчитаются все монеты');return}CRUN=true;try{return await coinsRun0()}finally{CRUN=false;coinsBtn('Проверено на 10 монетах — пересчитать заново')}}
async function coinsRun0(){const job=++CJOB,B=BT7;if(!B||!B.s)return;const el=()=>$('#coinres'),tf=B.tf,days=B.days,mkt=B.mkt,s=B.s,res=[];
 const paint=busy=>{const e=el();if(!e)return;const ok=res.filter(r=>!r.err),plus=ok.filter(r=>r.sum>0).length;
  e.innerHTML=`<h3 style="margin:12px 0 4px">Те же настройки на 10 монетах</h3>${busy?`<p class="mu sm">${busy}</p>`:`<p class="sm" style="margin:0 0 6px">${plus>=7?'<b class="g">Система работает на большинстве монет</b>':plus>=5?'<b>Работает примерно на половине монет</b>':'<b class="r">Работает только на отдельных монетах — похоже на случайность</b>'}: в плюсе ${plus} из ${ok.length}.</p>`}
  <table class="bt7"><tr><th>Монета</th><th>Сделок</th><th>Прибыльных</th><th>На сделку</th><th>Итог</th><th>Части</th></tr>${res.map(r=>r.err?`<tr><td>${r.sym}</td><td colspan="5" class="mu">${r.err}</td></tr>`:`<tr><td>${r.sym}</td><td>${r.n}</td><td>${r.n?fmt(r.win/r.n*100,0):0}%</td><td class="${r.ex>=0?'g':'r'}">${pp(r.ex,2)}</td><td class="${r.sum>=0?'g':'r'}">${pp(r.sum)}</td><td>${r.pos==null?'—':r.pos+'/4'}</td></tr>`).join('')}</table>`};
 for(const [ci,sym] of COINS10.entries()){if(job!==CJOB)return;paint(`Считаю ${sym} — монета ${ci+1} из ${COINS10.length}…`);coinsBtn(`Идёт расчёт: монета ${ci+1} из ${COINS10.length} · ${sym}`,1);
  try{const n=days*86400e3/TFMS(tf),c=await histK(mkt,sym,tf,days,300);if(c.length<350)throw new Error('мало свечей');const from=Math.max(300,c.length-Math.round(n));
   BTTF=tf;if(UI.btHtf){try{HTFCUR=await htfLoad(mkt,sym,tf,days)}catch(_){HTFCUR=null}}
   const r=btRun(c,s,from,mkt==='spot',mkt==='fut'?0.05:0.1);res.push({sym,n:r.A.n,win:r.A.win,ex:r.A.ex,sum:r.A.ex*r.A.n,pos:r.honest?r.honest.pos:null})}
  catch(e){res.push({sym,err:'нет данных'})}}
 if(UI.btHtf){try{HTFCUR=await htfLoad(UI.mkt,UI.sym,tf,days)}catch(_){}}
 if(job===CJOB)paint('')}
// ---------- «Тест всех систем»: честно, с устойчивостью, без коротких периодов ----------
const BA_DAYS={'1m':2,'5m':7,'15m':182,'1h':365,'4h':365,'1d':730};
baRun=async function(){const job=++BAJOB,mkt=UI.mkt,sym=UI.sym,fee=mkt==='fut'?0.05:0.1,spot=mkt==='spot',L=[],keep=UI.btRR,keepD=UI.btDir,keepH=UI.btHonest,DIRS=spot?[1]:[0,1,-1];
 BA={busy:'Начинаю…',sym,mkt,rows:[]};baPaint();UI.btHonest=1;
 try{for(const tf of BA_TF){const days=BA_DAYS[tf]||365;
   let c;try{c=await histK(mkt,sym,tf,days,300,(k,t)=>{if(job!==BAJOB)return;BA.busy=`Загружаю историю: свечи ${baTfn(tf)} — ${k} из ${t}`;baPaint()})}catch(e){BA.skip=(BA.skip||[]).concat(baTfn(tf));continue}
   if(job!==BAJOB)return;if(c.length<350){BA.skip=(BA.skip||[]).concat(baTfn(tf));continue}
   BTTF=tf;HTFCUR=null;if(UI.btHtf){try{HTFCUR=await htfLoad(mkt,sym,tf,days)}catch(_){}}
   const n=Math.round(days*86400e3/TFMS(tf)),from=Math.max(300,c.length-n),tdays=(c[c.length-1].time-c[from+Math.floor((c.length-from)/2)].time)/86400;
   for(const s of famList()){if(job!==BAJOB)return;BA.busy=`Считаю: свечи ${baTfn(tf)} · ${s.name}`;baPaint();await baYield();
    const p={};s.params.forEach(q=>p[q.k]=q.v);let sg;try{sg=s.run(c,p)}catch(_){continue}
    for(const dr of DIRS)for(const k of BA_MODES){UI.btRR=k;UI.btDir=dr;let r;try{r=btRun(c,s,from,spot,fee,sg)}catch(_){continue}const H=r.honest||{};
     L.push({dir:dr,tf,days:BA_DAYS[tf],tdays,pname:'',strat:s.id,sname:s.name,k,stop:r.A.stop,n:r.A.n,win:r.A.win,ret:r.A.ret,dd:r.A.dd,ex:r.A.ex,sum:r.A.ex*r.A.n,day:tdays>0?r.A.ex*r.A.n/tdays:0,fit:H.fitSum||0,fitN:H.fitN||0,pos:H.pos,parts:H.parts||[]})}}}
 }finally{UI.btRR=keep;UI.btDir=keepD;UI.btHonest=keepH}
 if(job!==BAJOB)return;
 // лучшее правило выбираем по первой половине (подбор), а показываем вторую (вслепую) — так нет подгонки
 const best={};L.forEach(x=>{const key=x.tf+'|'+x.strat+'|'+x.dir;if(x.fitN<5)return;if(!best[key]||x.fit>best[key].fit)best[key]=x});
 const rows=Object.values(best).sort((a,b)=>b.day-a.day),fl={htf:!!UI.btHtf,adx:!!UI.btAdx,maker:!!UI.btMaker&&!spot};
 BA={sym,mkt,rows,all:L,t:Date.now(),skip:BA.skip,fl,show:{st:15,p0:15,p1:15,p2:15,neg:15,bad:15,few:10}};LS.set('ballres2',{sym,mkt,rows,t:BA.t,skip:BA.skip,fl});baPaint()};
baRow=function(x){const cls=x.sum>0?'g':'r',wr=x.n?Math.round(x.win/x.n*100):0;
 return `<div class="plan ${x.sum>0?'long':'short'}" data-a="bago" data-v="${x.tf}|${x.days}|${x.strat}|${x.k}|${x.dir||0}" style="padding:8px 10px">
 <div class="row" style="margin:0;align-items:center"><b style="flex:1">${x.sname}</b><b class="${cls}">${pp(x.day,2)} в день</b></div>
 <div class="sm mu" style="margin-top:2px">Свечи ${baTfn(x.tf)} · <b>${baDirN(x.dir||0)}</b> · ${baMode(x.k)}${x.stop?`, стоп ${x.k>=4?'в среднем ':''}−${pf(x.stop)}%`:''}</div>
 <div class="sm" style="margin-top:2px">Вслепую за ${fmt(x.tdays,0)} дн.: <b class="${cls}">${pp(x.sum)}</b> · сделок ${x.n} · прибыльных ${wr}% · на сделку <span class="${x.ex>=0?'g':'r'}">${pp(x.ex,2)}</span> · просадка ${x.dd<0.05?'0%':'−'+fmt(x.dd,1)+'%'}</div>
 ${x.parts&&x.parts.length?`<div class="sm" style="margin-top:2px">По четвертям: ${x.parts.map(v=>`<span class="${v>0?'g':'r'}">${pp(v)}</span>`).join(' · ')}</div>`:''}</div>`};
baList=function(){const B=BA;
 if(!B)return `<p class="hint">Программа проверит все стратегии для ${UI.sym} (${UI.mkt==='fut'?'фьючерсы':'спот'}) на свечах 1 мин, 5 мин, 15 мин, 1 час, 4 часа и 1 день со всеми правилами стопа и тейка${UI.mkt==='fut'?', отдельно лонг и шорт, только лонг и только шорт':''}.</p>
 <p class="hint"><b>Честно:</b> стоп и правило выхода подбираются на первой половине истории, а результат показывается по второй, которую система не видела. Вторая половина делится на 4 части — <b>устойчивые</b> системы в плюсе во всех четырёх.</p>
 ${fChips('ba')}<button class="go b" data-a="bastart">Начать тест ${UI.sym}</button><p class="hint">Займёт 3–6 минут.</p>`;
 if(B.busy)return `<p class="mu">${B.busy}</p><p class="hint">Не закрывайте это окно до конца теста.</p>`;
 const F=B.f==null?'a':B.f,seenR={},RW=B.rows.filter(x=>F==='a'||(x.dir||0)===+F).filter(x=>{const k=x.tf+'|'+x.strat+'|'+x.k+'|'+x.n+'|'+x.sum.toFixed(4);if(seenR[k])return false;seenR[k]=1;return true}),ok=RW.filter(x=>x.n>=10),few=RW.filter(x=>x.n<10),
  stab=ok.filter(x=>x.sum>0&&x.pos===4),rest=ok.filter(x=>!(x.sum>0&&x.pos===4)),pos=rest.filter(x=>x.sum>0),p1=pos.filter(x=>x.pos===3),p2=pos.filter(x=>x.pos!==3),neg=rest.filter(x=>x.sum<=0&&x.sum>-10),bad=rest.filter(x=>x.sum<=-10);
 const grp=(id,title,hint,arr,color)=>arr.length?`<h3 style="margin:14px 0 4px" class="${color}">${title} · ${arr.length}</h3><p class="hint" style="margin-top:0">${hint}</p>${arr.slice(0,(B.show[id]||15)).map(baRow).join('')}${arr.length>(B.show[id]||15)?`<button class="btn2" style="width:100%;padding:10px" data-a="bamore" data-v="${id}">Показать ещё (${arr.length-(B.show[id]||15)})</button>`:''}`:'';
 const top=stab.filter(x=>x.n>=20&&x.tdays>=30).sort((a,b)=>b.sum-a.sum)[0]||stab.filter(x=>x.n>=20).sort((a,b)=>b.sum-a.sum)[0]||stab[0],fl=B.fl||{},fs=[fl.htf?'по старшему тренду':'',fl.adx?'без боковика':'',fl.maker?'лимитные заявки':''].filter(Boolean);
 return `<div class="wbox"><b>${B.sym}</b> <span class="mu sm">${B.mkt==='fut'?'фьючерсы':'спот'} · честная проверка${fs.length?' · '+fs.join(' · '):''} · вариантов: ${(B.all||[]).length||'—'} · ${new Date(B.t).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}</span></div>
 ${top?`<div class="serban" data-a="bago" data-v="${top.tf}|${top.days}|${top.strat}|${top.k}|${top.dir||0}"><b>${ic('verified',16)} Самая надёжная: ${top.sname}</b><div class="sm">Свечи ${baTfn(top.tf)}, ${baDirN(top.dir||0)}, ${baMode(top.k)}. Вслепую ${pp(top.day,2)} в день, за ${fmt(top.tdays,0)} дн. ${pp(top.sum)}, сделок ${top.n}, в плюсе все 4 части. Нажмите — и проверьте её на 10 монетах.</div></div>`:'<p class="err">Устойчивых систем для этой монеты не нашлось: ни одна не была в плюсе во всех четырёх частях проверки вслепую. Попробуйте включить фильтры «По старшему тренду» и «Без боковика», лимитные заявки — или другую монету.</p>'}
 ${B.mkt==='fut'?`<div class="pct" style="margin:10px 0">${[['a','Все'],['0','Лонг и шорт'],['1','Только лонг'],['-1','Только шорт']].map(q=>`<button class="${F===q[0]?'on':''}" data-a="bafil" data-v="${q[0]}">${q[1]}</button>`).join('')}</div>`:''}
 ${B.skip&&B.skip.length?`<p class="hint">Не удалось загрузить свечи: ${B.skip.join(', ')}.</p>`:''}
 ${grp('st','Устойчивые','В плюсе вслепую, и во всех четырёх частях проверки. Только на такие системы стоит смотреть всерьёз.',stab,'g')}
 ${grp('p1','Почти устойчивые','В плюсе, но одна из четырёх частей в минусе.',p1,'g')}
 ${grp('p2','Плюс неустойчивый','В плюсе за счёт одной-двух удачных частей — похоже на случайность.',p2,'')}
 ${grp('neg','Минусовые','Небольшой убыток — до −10% вслепую.',neg,'r')}
 ${grp('bad','Совсем убыточные','Потеряли больше 10% вслепую — по ним не торговать.',bad,'r')}
 ${grp('few','Мало сделок','Меньше 10 сделок на проверке вслепую — вывод ненадёжен.',few,'mu')}
 <p class="hint">Одна и та же ставка на каждую сделку, проценты к ставке, с учётом комиссии. Для каждой стратегии, свечей и направления правило выхода выбрано по первой половине истории, а цифры — по второй.</p>
 ${fChips('ba')}<button class="btn2" style="width:100%;padding:10px;margin-top:6px" data-a="bastart">Пройти тест заново</button>`};
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(a==='btflt'){e.stopPropagation();UI[v]=UI[v]?0:1;saveUI();if(el.dataset.s==='bt'){$('.sheet').outerHTML=btSheet();if(BT7&&BT7.A)btGo()}else baPaint()}
 else if(a==='coinsrun'){e.stopPropagation();coinsRun()}},true);
// старые результаты теста (без честной проверки) больше не показываем
try{LS.del('ballres')}catch(_){}
render();
