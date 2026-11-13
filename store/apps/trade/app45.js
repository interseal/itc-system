Object.assign(ICP,{percent:"M7.5 11C9.43 11 11 9.43 11 7.5S9.43 4 7.5 4 4 5.57 4 7.5 5.57 11 7.5 11zm0-5C8.33 6 9 6.67 9 7.5S8.33 9 7.5 9 6 8.33 6 7.5 6.67 6 7.5 6zM4.0025 18.5831 18.5835 4.0022l1.4142 1.4142L5.4167 19.9973zM16.5 13c-1.93 0-3.5 1.57-3.5 3.5s1.57 3.5 3.5 3.5 3.5-1.57 3.5-3.5-1.57-3.5-3.5-3.5zm0 5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z",payments:"M19 14V6c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zm-2 0H3V6h14v8zm-7-7c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm13 0v11c0 1.1-.9 2-2 2H4v-2h17V7h2z"});/* №472 · значки сортировки */
// Alex Trade — «Вся биржа» (11.11.2026): проверка всех монет Binance по всем стратегиям и свечам — пачками,
// с продолжением с того же места, итогами «самые прибыльные» и выгрузкой файла для разбора в чате с Claude.
"use strict";
let FULL={scope:200,groups:['scalp','fast','slow'],fl:{htf:0,adx:0,hrs:0,vol:0,maker:0,opt:0,sm:1},running:0,prog:null,top:null,busy:''};try{Object.assign(FULL,JSON.parse(localStorage.getItem('fullset')||'{}'),{running:0,prog:null,top:null,busy:''});FULL.fl=Object.assign({htf:0,adx:0,hrs:0,vol:0,maker:0,opt:0,sm:1},FULL.fl||{})}catch(_){}
let FULLQ=0,FWL=null;
const fSave=()=>{try{localStorage.setItem('fullset',JSON.stringify({scope:FULL.scope,groups:FULL.groups,fl:FULL.fl}))}catch(_){}};
// свои фильтры для проверки всей биржи (по умолчанию все выключены, только честная проверка)
const FLK=[['htf','btHtf','По старшему тренду'],['adx','btAdx','Без боковика'],['maker','btMaker','Лимитные заявки'],['hrs','btHrs','Активные часы'],['vol','btVol','Крупные свечи'],['opt','btOpt','Подбор настроек']];
function fApply(){const keep={btHonest:UI.btHonest,btSM:UI.btSM};FLK.forEach(([k,u])=>{keep[u]=UI[u];UI[u]=FULL.fl[k]?1:0});UI.btHonest=1;UI.btSM=FULL.fl.sm||1;return keep}
function fRestore(keep){Object.assign(UI,keep)}
const fTfs=()=>FULL.groups.flatMap(g=>SPD10[g]);
async function fCoins(){if(!CAT){try{await catInit()}catch(_){}}if(!CAT){await catLoad()}if(!CAT)return [];const L=CAT.coins.slice().sort((a,b)=>a.r-b.r);return (FULL.scope?L.slice(0,FULL.scope):L).map(x=>x.s)}
function fSig(){const k=UI.fam,kf=fApply();UI.fam='all';try{return cgSig()}finally{UI.fam=k;fRestore(kf)}}
async function fProg(){const syms=await fCoins(),tfs=fTfs(),sig=fSig();let have=new Set();try{const j=await capi('res_list',{sig});(j.items||[]).forEach(x=>{if(Date.now()/1000-x.t<7*86400&&x.t>=(+FULL.since||0))have.add(x.sym+'|'+x.tf)})}catch(_){}
 const S=new Set(syms);let done=0;have.forEach(k=>{const [s,t]=k.split('|');if(S.has(s)&&tfs.includes(t))done++});FULL.prog={done,total:syms.length*tfs.length,coins:syms.length,have,at:Date.now()};return FULL.prog}
const _cfd45=cgFromDB;cgFromDB=async function(open){if(FULLQ)return;return _cfd45(open)};
async function fRun(){if(FULL.running)return;FULL.running=1;FULLQ=1;FULL.t0=Date.now();FULL.d0=null;const keep=[UI.fam,UI.spd,UI.tfs,UI.cgskip],kf=fApply();UI.fam='all';UI.cgskip=1;
 try{if(navigator.wakeLock)FWL=await navigator.wakeLock.request('screen')}catch(_){}
 try{const syms=await fCoins();await fProg();FULL.d0=FULL.prog.done;fPaint();
  for(const g of FULL.groups){const tfs=SPD10[g];
   for(let i=0;i<syms.length&&FULL.running;i+=20){const chunk=syms.slice(i,i+20).filter(s=>tfs.some(t=>!FULL.prog.have.has(s+'|'+t)));if(!chunk.length)continue;
    UI.spd=g;UI.tfs=tfs.slice();CSEL=new Set(chunk);FULL.busy=`${SPN[g]}: монеты ${i+1}–${Math.min(i+20,syms.length)} из ${syms.length}`;fPaint();
    await cgRun();if(!FULL.running)break;await fProg();fPaint()}}}
 catch(e){toast('Проверка остановилась: '+(e.message||''))}
 finally{FULL.running=0;FULLQ=0;FULL.busy='';[UI.fam,UI.spd,UI.tfs,UI.cgskip]=keep;fRestore(kf);saveUI();try{FWL&&FWL.release()}catch(_){}FWL=null;fPaint();toast('Проверка всей биржи остановлена — всё посчитанное сохранено')}}
function fStop(){FULL.running=0;CGJOB++;CGB=null;fPaint()}
// ---------- итоги по базе ----------
async function fTop(){const syms=await fCoins(),tfs=fTfs(),sig=fSig(),nm=Object.fromEntries(ITS.list.map(s=>[s.id,s.name]));const best=[],agg=new Map(),perCoin=[];let k=0;
 FULL.busy='Собираю итоги из базы…';fPaint();
 let miss=0;/* №470 · пачки по 20 монет; не получилось — пачка делится пополам, монеты не пропадают молча */
 const getRes=async(part)=>{try{const j=await capi('res_get',{sig,syms:part,tfs});return j.items||[]}catch(e){if(part.length<=1){miss+=part.length;return []}const h=Math.ceil(part.length/2);return (await getRes(part.slice(0,h))).concat(await getRes(part.slice(h)))}};
 for(let i=0;i<syms.length;i+=20){FULL.busy=`Собираю итоги из базы: ${Math.min(i+20,syms.length)} из ${syms.length} монет`;fPaint();
  const items=await getRes(syms.slice(i,i+20));
  for(const it of items){let d=it.data;if(typeof d==='string')try{d=JSON.parse(d)}catch(_){continue}if(!d||!d.e)continue;const td=Math.max(1,+d.tdays||1),loc=[];
   for(const e of d.e){const [st,kk,dr,n,ex,pos,dd,stop]=e,sum=n*ex,mo=sum/td*30;
    if(n>=3){const key=it.tf+'|'+st+'|'+kk+'|'+dr;let a=agg.get(key);if(!a){a={tf:it.tf,st,k:kk,dir:dr,coins:0,plus:0,stab:0,sum:0,n:0,td:0};agg.set(key,a)}a.coins++;if(sum>0)a.plus++;if(pos>=3&&sum>0)a.stab++;a.sum+=sum;a.n+=n;a.td+=td}
    if(n>=10&&pos>=3&&ex>0)best.push({sym:it.sym,tf:it.tf,st,k:kk,dir:dr,n,ex,pos,dd,sum,mo,td,stop});if(n>=5)loc.push([st,kk,dr,n,ex,pos,dd,stop,Math.round(sum*100)/100,Math.round(mo*100)/100])}
   loc.sort((a,b)=>b[9]-a[9]);perCoin.push({sym:it.sym,tf:it.tf,tdays:td,top:loc.slice(0,10)})}
  if(best.length>3000){best.sort((a,b)=>b.ex-a.ex);const k1=best.slice(0,600);best.sort((a,b)=>b.mo-a.mo);const k2=best.slice(0,600);best.sort((a,b)=>b.sum-a.sum);const k3=best.slice(0,600);best.length=0;best.push(...new Set(k1.concat(k2,k3)))}}
 const bestMo=best.slice().sort((a,b)=>b.mo-a.mo).slice(0,100),bestTot=best.slice().sort((a,b)=>b.sum-a.sum).slice(0,100);best.sort((a,b)=>b.ex-a.ex);/* №472 · сверху — самые прибыльные в % на сделку */const minC=Math.max(Math.min(10,Math.ceil(syms.length*0.5)),Math.round(syms.length*0.3));
 const sys=[...agg.values()].filter(a=>a.coins>=minC).map(a=>Object.assign(a,{share:a.plus/a.coins,avg:a.sum/a.coins,mo:a.sum/a.coins/(a.td/a.coins)*30})).sort((a,b)=>(b.share-a.share)||(b.mo-a.mo));
 FULL.top={best:best.slice(0,100),bestMo,bestTot,sys:sys.slice(0,40),sysAll:sys,perCoin,nm,at:Date.now(),coins:syms.length,minC};FULL.busy='';fPaint();if(miss)toast(`Не удалось получить итоги по ${miss} монетам — остальные собраны`)}
const dirN=d=>baDirN(d||0),rowTxt=x=>`${FULL.top.nm[x.st]||x.st} · ${baTfn(x.tf)} · ${dirN(x.dir)} · ${baMode(x.k)}`;
function fExport(){const T=FULL.top;if(!T)return;const txt=JSON.stringify({about:'Alex Trade — проверка всей биржи Binance. Ставка $100 на сделку, без плеча, комиссия вычтена, честная проверка (вслепую).',
  date:new Date(T.at).toISOString(),coins:T.coins,settings:{mkt:UI.mkt,filters:flTxt(FULL.fl)||'только честная проверка, без фильтров'},
  legend:{e:'[стратегия, правило k (1-3 = 1:k, 0 = стоп+сигнал, 4 = уровни, 5 = правила стратегии), направление (0 оба, 1 лонг, -1 шорт), сделок, % на сделку, частей в плюсе из 4, просадка %, стоп %, сумма % (=$ при $100), $ в месяц]'},
  strategies:T.nm,systems:T.sysAll.slice(0,400).map(a=>({tf:a.tf,strategy:T.nm[a.st]||a.st,id:a.st,k:a.k,dir:a.dir,coins:a.coins,plus:a.plus,stable:a.stab,avg_usd_per_coin:+a.avg.toFixed(2),usd_month_per_coin:+a.mo.toFixed(2),trades:a.n})),
  best:T.best,perCoin:T.perCoin});
 saveFile('itk_vsya_birzha_'+new Date().toISOString().slice(0,10)+'.json',txt);toast('Файл сохранён — пришлите его в чат Claude')}
// ---------- экран ----------
// №480 · длина серии из 30 сделок (дней) и «размер» системы
const serD=x=>30*(x.td||0)/Math.max(1,x.n||0);function szOf(x){const d=serD(x);return d<=30?'S':d<=90?'M':d<=180?'L':'XL'}
// №479 · пары из «лучших 10 на каждой монете» — чтобы фильтры видели все варианты, а не только общий топ-100
function pcPool(T){if(T.__pc)return T.__pc;const o=[];(T.perCoin||[]).forEach(p=>(p.top||[]).forEach(e=>{const [st,k,dr,n,ex,pos,dd,stop,sum,mo]=e;if(n>=10&&pos>=3&&ex>0)o.push({sym:p.sym,tf:p.tf,st,k,dir:dr,n,ex,pos,dd,sum,mo,td:p.tdays,stop})}));Object.defineProperty(T,'__pc',{value:o,enumerable:false});return o}
// №476 · сколько шла проверка и сколько займёт серия из 30 сделок — как в «Архиве»
function f476(n,d){const per=n/Math.max(1,d)*30;return `<p class="sm" style="margin:6px 0 0">${ic('history',14)} Проверка вслепую шла <b>${fmtDur(d)}</b> · ≈ ${fmt(per,per<10?1:0)} сделок в месяц на монету · серия из 30 сделок ≈ <b>${per>0?fmtDur(30/per*30):'—'}</b></p>`}
function fBody(){const P=FULL.prog,mk=(v,cur,a,t)=>`<button class="${cur===v?'on':''}" data-a="${a}" data-v="${v}">${t}</button>`,pc=P&&P.total?P.done/P.total*100:0;
 let eta='';if(FULL.running&&P&&FULL.d0!=null&&P.done>FULL.d0){const per=(Date.now()-FULL.t0)/(P.done-FULL.d0),left=(P.total-P.done)*per/60e3;eta=left<60?Math.round(left)+' мин':fmt(left/60,1)+' ч'}
 else if(P){const left=(P.total-P.done)*0.3;eta='≈ '+(left<60?Math.round(left)+' мин':fmt(left/60,0)+' ч')}
 let h=`<div class="card"><h3 style="margin:0 0 4px">${ic('coins',20)} Проверка всей биржи</h3><p class="hint" style="margin-top:0">Все монеты × все стратегии × все свечи. Идёт пачками по 20 монет, каждая сразу сохраняется в базу — можно остановить и продолжить в любой момент, уже проверенное за неделю не пересчитывается. Пока идёт проверка, экран не гаснет — держите приложение открытым (лучше на ноутбуке).</p>
 <div class="row"><label>Монеты</label><div class="pct" style="margin:0;flex:1">${[[100,'Топ-100'],[200,'Топ-200'],[0,'Все']].map(([v,t])=>mk(v,FULL.scope,'fsc',t)).join('')}</div></div>
 <div class="row"><label>Свечи</label><div class="pct" style="margin:0;flex:1">${['scalp','fast','slow'].map(g=>`<button class="${FULL.groups.includes(g)?'on':''}" data-a="fgr" data-v="${g}">${SPN[g]}</button>`).join('')}</div></div>
 <div class="row" style="align-items:flex-start"><label>Фильтры</label><div class="pct" style="margin:0;flex:1;flex-wrap:wrap"><button class="on" style="cursor:default">Честная (вслепую)</button>${FLK.map(([k,,t])=>`<button class="${FULL.fl[k]?'on':''}" data-a="ffl" data-v="${k}">${t}</button>`).join('')}</div></div>
 <div class="row"><label>Стоп и тейк длиннее</label><div class="pct" style="margin:0;flex:1">${[[1,'Обычные'],[1.5,'×1,5'],[2,'×2'],[3,'×3']].map(([v,t])=>`<button class="${(FULL.fl.sm||1)===v?'on':''}" data-a="fsm" data-v="${v}">${t}</button>`).join('')}</div></div>
 <p class="sm mu" style="margin:0 0 6px">Системы — все ${ITS.list.length}. Эти фильтры действуют только здесь и сами выключены: так у каждой стратегии достаточно сделок для честного итога. Настройки в «Монетах» и «Тестах» не меняются.</p>
 ${P?`<div style="height:10px;border-radius:5px;background:var(--ln);margin:6px 0"><div style="height:10px;border-radius:5px;width:${pc.toFixed(1)}%;background:var(--ac)"></div></div><div class="sm">Готово <b>${P.done.toLocaleString('ru-RU')}</b> из ${P.total.toLocaleString('ru-RU')} (монета × свечи) · ${fmt(pc,1)}% · осталось ${eta}</div>`:'<p class="sm mu">Считаю, сколько уже проверено…</p>'}
 ${FULL.running?`<div class="go" style="background:var(--pan2);border:2px solid var(--ac);color:var(--tx);cursor:default;display:flex;align-items:center;gap:10px;text-align:left;margin-top:8px"><span class="spin39"></span><span style="flex:1"><b>Идёт проверка…</b><br><span class="sm" style="font-weight:400">${esc(FULL.busy)}${CGB&&CGB.busy?'<br>'+esc(CGB.busy):''}</span></span></div><button class="btn2" data-a="fstop" style="width:100%;margin-top:6px;padding:10px">Остановить</button>`
  :`<button class="go b" data-a="frun" style="font-size:16px;margin-top:8px">${ic('play_arrow',20)} ${P&&P.done?'Продолжить проверку':'Начать проверку всей биржи'}</button>`}
 <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:8px"><button class="btn2" data-a="ftop" ${FULL.running?'disabled':''}>${ic('rank',18)} Самые прибыльные по базе</button>${FULL.top?`<button class="btn2" data-a="fexp">${ic('download',18)} Выгрузить для Claude</button>`:''}</div>
 ${!FULL.running&&FULL.busy?`<p class="mu sm">${esc(FULL.busy)}</p>`:''}</div>`;
 const T=FULL.top;if(!T)return h;const SS=FULL.sSort||'tot',PS=FULL.pSort||'tot',DS=FULL.dSel||'all',DF=x=>DS==='all'||String(x.dir)===DS;/* №478 · направление */
 /* №480 · размер по длине серии из 30 сделок: S до 30 дн., M 1–3 мес., L 3–6 мес., XL больше 6 мес. */const SZ=x=>!FULL.size||szOf(x)===FULL.size;
 /* №479 · фильтры пар: длина серии, доход с $1 000, надёжность */const FS=+(FULL.fSer||0),FI=+(FULL.fInc||0),FP=+(FULL.fPos||0),PF=x=>{if(!FS&&!FI&&!FP)return true;const td=x.td||0;if(td<14)return false;if(FS&&30*td/Math.max(1,x.n)>FS)return false;if(FI&&x.mo*10<FI)return false;if(FP&&x.pos<4)return false;return true};
 const fb=(a,cur,list)=>list.map(([v,t])=>`<button class="btn2" data-a="${a}" data-v="${v}" style="${String(cur)===String(v)?'background:var(--ac);color:#fff':''}">${t}</button>`).join('');
 const PFB=`<div class="sm mu" style="margin:2px 0 4px">Серия из 30 сделок не дольше</div><div class="row" style="gap:6px;margin:0 0 6px;flex-wrap:wrap">${fb('fser',FS,[[0,'Любая'],[14,'2 недели'],[21,'3 недели'],[30,'Месяц']])}</div>
 <div class="sm mu" style="margin:2px 0 4px">Доход с $1 000 в месяц не меньше</div><div class="row" style="gap:6px;margin:0 0 6px;flex-wrap:wrap">${fb('finc',FI,[[0,'Любой'],[100,'$100'],[300,'$300'],[500,'$500']])}</div>
 <div class="sm mu" style="margin:2px 0 4px">Надёжность</div><div class="row" style="gap:6px;margin:0 0 6px;flex-wrap:wrap">${fb('fpos',FP,[[0,'В плюсе 3 из 4 частей'],[1,'Только 4 из 4']])}</div>
 ${FS||FI||FP?`<p class="hint" style="margin:0 0 8px">С фильтрами показываются только проверки не короче 2 недель. Найдено: <b>${FULL.pfN==null?'…':FULL.pfN}</b></p>`:''}`;
 const DB=`<div class="row" style="gap:6px;margin:0 0 8px;flex-wrap:wrap">${[['all','Все'],['1','Только лонг'],['-1','Только шорт'],['0','Лонг и шорт']].map(([v,t])=>`<button class="btn2" data-a="fdir" data-v="${v}" style="${DS===v?'background:var(--ac);color:#fff':''}">${t}</button>`).join('')}</div>`;/* №477 · сортировка по итогу с $1 000 */
 h+=`<div class="card"><h3 style="margin:0 0 4px">${ic('flag',20)} Системы, которые работают на большинстве монет</h3><p class="hint" style="margin-top:0">Проверено ${T.coins} монет. Показаны системы, у которых есть сделки хотя бы на ${T.minC} монетах. ${SS==='share'?'Сверху — где больше всего монет в плюсе.':SS==='mo'?'Сверху — самый большой доход в месяц. Только системы, которые в плюсе хотя бы на 60% монет.':'Сверху — самый большой итог «Если вложить $1 000» за всю проверку. Только системы, которые в плюсе хотя бы на 60% монет.'}</p>
 <div class="row" style="gap:6px;margin:0 0 8px;flex-wrap:wrap"><button class="btn2" data-a="fsys" data-v="tot" style="${SS==='tot'?'background:var(--ac);color:#fff':''}">${ic('wallet',18)} По итогу с $1 000</button><button class="btn2" data-a="fsys" data-v="mo" style="${SS==='mo'?'background:var(--ac);color:#fff':''}">${ic('payments',18)} В месяц</button><button class="btn2" data-a="fsys" data-v="share" style="${SS==='share'?'background:var(--ac);color:#fff':''}">${ic('flag',18)} По числу монет в плюсе</button></div>
 ${DB}${(SS==='share'?(DS==='all'&&!FULL.size?T.sys:(T.sysAll||T.sys)).filter(x=>DF(x)&&SZ(x)):(T.sysAll||T.sys).filter(a=>a.share>=0.6&&a.sum>0&&DF(a)&&SZ(a)).slice().sort(SS==='mo'?(a,b)=>b.mo-a.mo:(a,b)=>b.sum/b.coins-a.sum/a.coins)).slice(0,20).map((a,i)=>`<div class="plan ${a.share>=0.6&&a.mo>0?'long':'short'}" style="cursor:default;padding:8px 10px"><div class="row" style="margin:0;align-items:center"><b style="flex:1">${i+1}. ${esc(rowTxt(a))}</b><b>${USC(a.mo)}<span class="mu sm" style="font-weight:400"> в мес. на монету</span></b></div>
  <div class="sm">В плюсе <b class="${a.share>=0.6?'g':'r'}">${a.plus} из ${a.coins}</b> монет (${fmt(a.share*100,0)}%) · устойчиво на ${a.stab} · сделок ${a.n.toLocaleString('ru-RU')} · в среднем ${USC(a.avg)} на монету за период при $100 на сделку</div>
  ${f476(a.n/Math.max(1,a.coins),a.td/Math.max(1,a.coins))}${k1000(a.sum,a.coins,a.td/Math.max(1,a.coins),k1000n(a.coins))}</div>`).join('')}</div>`;
 h+=`<div class="card"><h3 style="margin:0 0 4px">Самые прибыльные пары «монета + система»</h3><p class="hint" style="margin-top:0">Только устойчивые (в плюсе минимум в 3 из 4 частей) и от 10 сделок. Сверху — ${PS==='ex'?'самые прибыльные в % на сделку':PS==='mo'?'самый большой доход в месяц':'самый большой итог «Если вложить $1 000» за всю проверку'}.</p>
 <div class="row" style="gap:6px;margin:0 0 8px;flex-wrap:wrap"><button class="btn2" data-a="fsrt" data-v="tot" style="${PS==='tot'?'background:var(--ac);color:#fff':''}">${ic('wallet',18)} По итогу с $1 000</button><button class="btn2" data-a="fsrt" data-v="mo" style="${PS==='mo'?'background:var(--ac);color:#fff':''}">${ic('payments',18)} В месяц</button><button class="btn2" data-a="fsrt" data-v="ex" style="${PS==='ex'?'background:var(--ac);color:#fff':''}">${ic('percent',18)} % на сделку</button></div>
 ${DB}${PFB}${(()=>{const seen={},pool=[].concat(T.best||[],T.bestMo||[],T.bestTot||[],pcPool(T)).filter(x=>{const k=[x.sym,x.tf,x.st,x.k,x.dir].join('|');if(seen[k])return 0;return seen[k]=1});const L=pool.filter(DF).filter(PF).filter(SZ);FULL.pfN=L.length;return L.sort(PS==='ex'?(a,b)=>b.ex-a.ex:PS==='mo'?(a,b)=>b.mo-a.mo:(a,b)=>b.sum-a.sum)})().filter((u=>x=>{const k=x.sym+'|'+x.tf+'|'+x.st+'|'+x.dir;if(u[k])return 0;return u[k]=1})({})).slice(0,40).map((x,i)=>`<div class="plan long" style="cursor:default;padding:8px 10px"><div class="row" style="margin:0;align-items:center"><b style="flex:1">${i+1}. ${x.sym} · ${esc(rowTxt(x))}</b><b class="g">+${fmt(x.ex,2)}%<span class="mu sm" style="font-weight:400"> на сделку</span></b></div>
  <div class="sm">Сделок ${x.n} · в плюсе в ${x.pos} из 4 частей · просадка −${fmt(x.dd||0,1)}%</div>
  ${f476(x.n,x.td)}${k1000(x.sum,1,x.td,k1000n(1))}<button class="btn2" style="width:100%;margin-top:8px;padding:9px" data-a="fbot" data-v="${[x.sym,x.tf,x.st,x.k,x.dir,x.stop||''].join('|')}">${ic('bot',18)} Запустить бота на демо-счёте</button><button class="btn2" style="width:100%;margin-top:6px;padding:9px;color:#f23645;border-color:#f23645" data-a="fbotlive" data-v="${[x.sym,x.tf,x.st,x.k,x.dir,x.stop||'',x.dd||''].join('|')}">${ic('wallet',18)} Торговать на Binance</button></div>`).join('')}
 <p class="hint">Отдельная монета с отличным результатом может быть везением. Надёжнее системы из верхнего списка — они работают на многих монетах сразу. Файл «Выгрузить для Claude» содержит всё это подробно — пришлите его в чат, и я разберу.</p></div>`;
 return h}
function fPaint(){const e=$('#labfull');if(e)e.innerHTML=fBody()}
LABT.splice(4,0,['full','coins','Вся биржа']);
const _lv45=labView;labView=function(){if((UI.labTab||'tests')!=='full')return _lv45();if(!FULL.prog&&!FULL.running&&!FULL.pl){FULL.pl=1;fProg().then(()=>{FULL.pl=0;fPaint()}).catch(()=>{FULL.pl=0})}
 const h=_lv45(),i=h.indexOf('<div class="labtabs">'),j=i>=0?h.indexOf('</div>',i)+6:0;return h.slice(0,j)+`<div id="labfull">${fBody()}</div>`};
setInterval(()=>{if(FULL.running&&(UI.labTab||'')==='full'&&UI.view==='lab')fPaint()},3000);
window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;if(!['fsc','fgr','frun','fstop','ftop','fexp','ffl','fsm','fsrt','fsys','fdir','fser','finc','fpos'].includes(a))return;e.stopPropagation();e.preventDefault();
 if(a==='frun'){fRun();return}if(a==='fstop'){fStop();return}if(a==='ftop'){fTop();return}if(a==='fexp'){fExport();return}if(a==='fsrt'){FULL.pSort=v;fPaint();return}if(a==='fdir'){FULL.dSel=v;fPaint();return}if(a==='fser'){FULL.fSer=+v;fPaint();fPaint();return}if(a==='finc'){FULL.fInc=+v;fPaint();fPaint();return}if(a==='fpos'){FULL.fPos=+v;fPaint();fPaint();return}if(a==='fsys'){FULL.sSort=v;fPaint();return}if(FULL.running)return;
 if(a==='ffl')FULL.fl[v]=FULL.fl[v]?0:1;else if(a==='fsm')FULL.fl.sm=+v;else if(a==='fsc')FULL.scope=+v;else if(a==='fgr'){const g=FULL.groups;FULL.groups=g.includes(v)?g.filter(x=>x!==v):g.concat(v);if(!FULL.groups.length)FULL.groups=[v]}
 fSave();FULL.prog=null;FULL.top=null;fPaint();fProg().then(fPaint)},true);
render();
