// ИТК Трейдинг — архив анализов (10.11.2026).
// Каждый тест систем (одна монета, много монет) и каждая проверка системы сохраняются на сервере сами.
// Во вкладке «Анализ → Архив» их можно открыть снова, скачать файлом (чтобы переслать) или удалить.
"use strict";
ICP.archive="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5zM5.12 5l.81-1h12l.94 1H5.12z";
ICP.download="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z";
let ARCH=null,ARCHBUSY=false,ARCHVIEW=null;
// ---------- сжатие, чтобы большие тесты помещались ----------
async function gzPack(txt){if(typeof CompressionStream==='undefined')return null;try{const s=new Blob([txt]).stream().pipeThrough(new CompressionStream('gzip'));const b=new Uint8Array(await new Response(s).arrayBuffer());let bin='';for(let i=0;i<b.length;i+=8192)bin+=String.fromCharCode.apply(null,b.subarray(i,i+8192));return btoa(bin)}catch(_){return null}}
async function gzUnpack(b64){const bin=atob(b64),u=new Uint8Array(bin.length);for(let i=0;i<bin.length;i++)u[i]=bin.charCodeAt(i);const s=new Blob([u]).stream().pipeThrough(new DecompressionStream('gzip'));return await new Response(s).text()}
const r2=v=>typeof v==='number'?Math.round(v*1000)/1000:v;
// ---------- что сохраняем ----------
function packBA(B){const multi=B.kind==='multi';
 const rows=B.rows.map(g=>multi?Object.assign({},g,{coins:g.coins.map(x=>[x.sym,x.n,r2(x.ex),r2(x.sum),x.pos,r2(x.dd),r2(x.stop)])}):Object.assign({},g,{parts:(g.parts||[]).map(r2)}));
 return {kind:multi?'multi':'single',v:1,t:B.t,sym:B.sym,mkt:B.mkt,coins:B.coins,skip:B.skip,fl:B.fl,tfs:multi?BA10_TF.slice():BA_TF.slice(),rows}}
function unpackBA(d){const multi=d.kind==='multi';const rows=d.rows.map(g=>multi?Object.assign({},g,{coins:g.coins.map(a=>({sym:a[0],n:a[1],ex:a[2],sum:a[3],pos:a[4],dd:a[5],stop:a[6],tdays:g.tdays}))}):g);
 return {kind:multi?'multi':undefined,sym:d.sym,mkt:d.mkt,coins:d.coins,skip:d.skip||[],fl:d.fl,t:d.t,rows,show:{st:15,p0:15,p1:15,p2:15,neg:15,bad:15,few:10,m7:15,m5:15,m0:10},arch:1}}
const flTxt=fl=>{fl=fl||{};return [fl.htf?'по старшему тренду':'',fl.adx?'без боковика':'',fl.maker?'лимитные заявки':'',fl.hrs?'активные часы':'',fl.vol?'крупные свечи':'',fl.opt?'подбор настроек':'',fl.sm>1?'стоп и тейк ×'+String(fl.sm).replace('.',','):''].filter(Boolean).join(' · ')};
function baMeta(B){const multi=B.kind==='multi';let best=null;
 if(multi){const t=B.rows.filter(g=>g.act&&g.plus/g.act>=0.7&&g.trades>=60&&g.avgEx>=0.3).sort((a,b)=>(b.stable-a.stable)||(b.avgEx-a.avgEx))[0];if(t)best={sname:t.sname,tf:t.tf,k:t.k,dir:t.dir,txt:`в плюсе ${t.plus} из ${t.act}, ${pp(t.avgEx,2)} на сделку`}}
 else{const t=B.rows.filter(x=>x.n>=10&&x.sum>0&&x.pos===4).sort((a,b)=>b.sum-a.sum)[0];if(t)best={sname:t.sname,tf:t.tf,k:t.k,dir:t.dir,txt:`вслепую ${pp(t.sum)}, сделок ${t.n}`}}
 return {kind:multi?'multi':'single',sym:B.sym,mkt:B.mkt,n:multi?(B.coins||[]).length:1,tfs:multi?BA10_TF.slice():BA_TF.slice(),fl:Object.assign({},B.fl||{},{hrs:!!UI.btHrs,vol:!!UI.btVol,opt:!!UI.btOpt,sm:typeof SMul==='function'?SMul():1}),rows:B.rows.length,best}}
async function archSave(kind,title,meta,data,id){try{const txt=JSON.stringify(data);const gz=txt.length>200000?await gzPack(txt):null;
  const j=await tdapi('an_save',{kind,title,meta,data:gz||txt,enc:gz?'gz64':'json',id:id||''});ARCH=null;return j.id}catch(e){toast('Анализ не сохранился: '+(e.message||'нет связи'));return null}}
// ---------- автосохранение ----------
async function saveBA(){const B=BA;if(!B||B.busy||!B.rows||B.arch)return;const multi=B.kind==='multi',m=baMeta(B),ttl=multi?`Тест на ${m.n} монетах · ${m.tfs.map(baTfn).join(', ')}`:`Тест ${B.sym} · все системы`;
 const id=await archSave(m.kind,ttl,m,packBA(B));if(id){B.arch=1;B.archId=id;toast('Тест сохранён в «Анализ → Архив»')}}
const _bar30=baRun;baRun=async function(){await _bar30();await saveBA()};
const _b1030=ba10Run;ba10Run=async function(){await _b1030();await saveBA()};
let SYSLAST=null;
const _bg30=btGo;btGo=async function(){await _bg30();const B=BT7;if(!B||!B.A||!B.s)return;
 const k=B.K==null?3:B.K,cfg={strat:B.s.id,sname:B.s.name,sym:B.sym,mkt:B.mkt,tf:B.tf,days:B.days,k,dir:B.dir||0,fl:{htf:!!UI.btHtf,adx:!!UI.btAdx,maker:B.fee===0.02,hrs:!!UI.btHrs,vol:!!UI.btVol,opt:!!UI.btOpt,honest:!!UI.btHonest}};
 const data={kind:'sys',v:1,t:Date.now(),cfg,A:B.A,honest:B.honest||null,prm:B.prm||null,candles:B.candles,t0:B.t0,t1:B.t1};
 const key=JSON.stringify([cfg.strat,cfg.sym,cfg.mkt,cfg.tf,cfg.days]);const same=SYSLAST&&SYSLAST.key===key&&Date.now()-SYSLAST.at<10*60e3;
 const meta={kind:'sys',sym:B.sym,mkt:B.mkt,tf:B.tf,sname:B.s.name,k,dir:cfg.dir,fl:cfg.fl,n:B.A.n,ex:B.A.ex,sum:B.A.ex*B.A.n,pos:B.honest?B.honest.pos:null};
 const id=await archSave('sys',`${B.s.name} · ${B.sym} · ${baTfn(B.tf)} · ${baMode(k)}`,meta,data,same?SYSLAST.id:'');if(id)SYSLAST={key,id,at:Date.now()}};
// результаты проверки на 10 монетах тоже добавляем к записи проверки
const _cr30=coinsRun;coinsRun=async function(){await _cr30();const e=$('#coinres');if(!e||!SYSLAST)return;
 try{const rows=[...e.querySelectorAll('tr')].slice(1).map(tr=>[...tr.children].map(td=>td.textContent));const j=await tdapi('an_get',{id:SYSLAST.id});let d=j.item.enc==='gz64'?JSON.parse(await gzUnpack(j.item.data)):JSON.parse(j.item.data);d.coins10=rows;
  await archSave('sys',j.item.title,j.item.meta,d,SYSLAST.id)}catch(_){}};
// ---------- вкладка «Архив» ----------
async function archLoad(){if(ARCHBUSY)return;ARCHBUSY=true;try{const j=await tdapi('an_list',{});ARCH=j.items||[]}catch(e){ARCH={err:e.message||'нет связи'}}ARCHBUSY=false;archPaint()}
const KN={multi:'Много монет',single:'Одна монета',sys:'Проверка системы'};
function archRow(x){const m=x.meta||{},d=new Date(x.created_at*1000).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'});
 const sub=x.kind==='sys'?`${baDirN(m.dir||0)} · ${m.n} сделок · на сделку <span class="${m.ex>=0?'g':'r'}">${pp(m.ex||0,2)}</span>${m.pos!=null?` · в плюсе ${m.pos} из 4 частей`:''}`
  :`${m.rows} вариантов${m.best?` · лучшая: <b>${m.best.sname}</b>, ${baTfn(m.best.tf)}, ${baMode(m.best.k)} — ${m.best.txt}`:' · устойчивых систем не нашлось'}`;
 return `<div class="plan" style="cursor:default;padding:8px 10px"><div class="row" style="margin:0;align-items:center"><b style="flex:1">${esc(x.title)}</b><span class="mu sm">${d}</span></div>
 <div class="sm mu" style="margin-top:2px">${KN[x.kind]||x.kind}${m.mkt?' · '+(m.mkt==='fut'?'фьючерсы':'спот'):''}${flTxt(m.fl)?' · '+flTxt(m.fl):''} · ${fmt(x.size/1024,0)} КБ</div>
 <div class="sm" style="margin-top:2px">${sub}</div>
 <div class="row" style="margin:6px 0 0;gap:6px"><button class="btn2 sm" style="flex:1" data-a="arcopen" data-v="${x.id}">Открыть</button><button class="btn2 sm" style="flex:1" data-a="arcdl" data-v="${x.id}">${ic('download',16)} Скачать</button><button class="btn2 sm" data-a="arcdel" data-v="${x.id}">${ic('trash',16)}</button></div></div>`}
function archBody(){if(ARCHVIEW)return sysView(ARCHVIEW);if(!ARCH){archLoad();return '<p class="mu">Загружаю архив…</p>'}if(ARCH.err)return `<p class="err">Не удалось загрузить архив: ${ARCH.err}</p>`;
 const f=UI.arcF||'all',L=ARCH.filter(x=>f==='all'||x.kind===f);
 return `<div class="pct" style="margin:0 0 8px">${[['all','Все'],['multi','Много монет'],['single','Одна монета'],['sys','Проверки']].map(q=>`<button class="${f===q[0]?'on':''}" data-a="arcf" data-v="${q[0]}">${q[1]}</button>`).join('')}</div>
 ${L.length?L.map(archRow).join(''):'<p class="hint">Пока пусто. Каждый тест и каждая проверка системы сохраняются сюда сами.</p>'}
 <p class="hint">Здесь хранятся все тесты и проверки. «Скачать» сохраняет анализ файлом — его можно переслать в чат и разобрать ещё раз. ${L.length?`<button class="btn2 sm" data-a="arcall">${ic('download',16)} Скачать весь архив одним файлом</button>`:''}</p>`}
function sysView(d){const c=d.cfg,A=d.A,H=d.honest;
 return `<div class="row" style="margin:0 0 8px"><button class="btn2 sm" data-a="arcback">← Архив</button></div><div class="wbox"><b>${c.sname}</b> <span class="mu sm">${c.sym} · ${c.mkt==='fut'?'фьючерсы':'спот'} · свечи ${baTfn(c.tf)} · ${baDirN(c.dir)} · ${baMode(c.k)}${flTxt(c.fl)?' · '+flTxt(c.fl):''} · ${new Date(d.t).toLocaleString('ru-RU')}</span></div>
 ${H?`<p class="sm">Вслепую: ${new Date(H.tm*1000).toLocaleDateString('ru-RU')} — ${new Date(d.t1*1000).toLocaleDateString('ru-RU')}. По четвертям: ${(H.parts||[]).map(v=>`<span class="${v>0?'g':'r'}">${pp(v)}</span>`).join(' · ')}</p>`:''}
 ${d.prm?`<p class="sm">Подобранные настройки: ${Object.entries(d.prm).map(([k,v])=>k+' = '+v).join(', ')}</p>`:''}
 <div class="sgrid"><div><small>Сделок</small>${A.n}</div><div><small>Прибыльных</small>${A.n?fmt(A.win/A.n*100,0):0}%</div><div><small>В среднем на сделку</small><span class="${A.ex>=0?'g':'r'}">${pp(A.ex,2)}</span></div><div><small>Итог (сумма сделок)</small><span class="${A.ex>=0?'g':'r'}">${pp(A.ex*A.n)}</span></div><div><small>Наибольшая просадка</small><span class="r">−${fmt(A.dd,1)}%</span></div><div><small>Стоп в среднем</small>−${pf(A.stop||0)}%</div></div>
 ${d.coins10?`<h3 style="margin:12px 0 4px">На 10 монетах</h3><table class="bt7">${d.coins10.map(r=>`<tr>${r.map(x=>`<td>${esc(x)}</td>`).join('')}</tr>`).join('')}</table>`:''}
 <button class="go b" style="margin-top:10px" data-a="arcrerun">Проверить заново на свежих данных</button>`}
function archPaint(){const e=$('#labarch');if(e)e.innerHTML=archBody()}
// вкладка в «Анализе»
LABT.push(['arch','archive','Архив']);
const _lv30=labView;labView=function(){if((UI.labTab||'tests')!=='arch')return _lv30();const h=_lv30();return h+`<div class="card"><h3 style="margin:0 0 8px">${ic('archive',20)} Архив анализов</h3><div id="labarch">${archBody()}</div></div>`};
async function archGet(id){const j=await tdapi('an_get',{id});const it=j.item;const txt=it.enc==='gz64'?await gzUnpack(it.data):it.data;return {it,d:JSON.parse(txt),txt}}
function saveFile(name,txt,type){const b=new Blob([txt],{type:type||'application/json'});
 if(navigator.canShare&&typeof File!=='undefined'){try{const f=new File([b],name,{type:b.type});if(navigator.canShare({files:[f]})){navigator.share({files:[f],title:name}).catch(()=>{});return}}catch(_){}}
 const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download=name;document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},2000)}
const fname=(it)=>`itc_analiz_${new Date(it.created_at*1000).toISOString().slice(0,16).replace(/[-:T]/g,'')}_${it.kind}.json`;
window.addEventListener('click',async e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
 if(!['arcf','arcopen','arcdl','arcdel','arcall','arcback','arcrerun'].includes(a))return;e.stopPropagation();e.preventDefault();
 if(a==='arcf'){UI.arcF=v;saveUI();archPaint()}
 else if(a==='arcback'){ARCHVIEW=null;archPaint()}
 else if(a==='arcopen'){el.textContent='Открываю…';try{const {it,d}=await archGet(v);
   if(d.kind==='sys'){ARCHVIEW=d;archPaint();window.scrollTo(0,0)}else{BA=unpackBA(d);UI.labTab='tests';saveUI();render();window.scrollTo(0,0);toast('Открыт тест от '+new Date(it.created_at*1000).toLocaleString('ru-RU'))}}catch(err){toast('Не удалось открыть: '+(err.message||''));archPaint()}}
 else if(a==='arcdl'){el.textContent='Готовлю файл…';try{const {it,txt}=await archGet(v);saveFile(fname(it),JSON.stringify(Object.assign({title:it.title,saved:new Date(it.created_at*1000).toISOString(),meta:it.meta},{data:JSON.parse(txt)}),null,1))}catch(err){toast('Не удалось скачать: '+(err.message||''))}archPaint()}
 else if(a==='arcall'){el.textContent='Собираю архив…';try{const out=[];for(const x of ARCH){const {it,txt}=await archGet(x.id);out.push({title:it.title,saved:new Date(it.created_at*1000).toISOString(),kind:it.kind,meta:it.meta,data:JSON.parse(txt)})}
   saveFile(`itc_analiz_vse_${new Date().toISOString().slice(0,10)}.json`,JSON.stringify(out))}catch(err){toast('Не удалось собрать архив: '+(err.message||''))}archPaint()}
 else if(a==='arcdel'){if(!confirm('Удалить этот анализ из архива? Вернуть его будет нельзя.'))return;try{await tdapi('an_del',{id:v});ARCH=(ARCH||[]).filter(x=>x.id!==v)}catch(_){}archPaint()}
 else if(a==='arcrerun'){const c=ARCHVIEW&&ARCHVIEW.cfg;if(!c)return;UI.btS=c.strat;UI.btTf=c.tf;UI.btP=c.days;UI.btRR=c.k;UI.btDir=c.dir;['htf','adx','hrs','vol','opt'].forEach(k=>UI['bt'+k[0].toUpperCase()+k.slice(1)]=c.fl&&c.fl[k]?1:0);UI.btMaker=c.fl&&c.fl.maker?1:0;UI.btHonest=c.fl&&c.fl.honest===false?0:1;
  if(c.sym!==UI.sym||c.mkt!==UI.mkt){UI.sym=c.sym;UI.mkt=c.mkt}ARCHVIEW=null;BT7=null;saveUI();UI.view='lab';UI.labTab='sys';render();btGo()}},true);
// архивный тест в «Тестах» — с пометкой, что он из архива
const _bl30=baList;baList=function(){const h=_bl30();return BA&&BA.arch&&!BA.busy?`<p class="hint" style="margin-top:0">${ic('archive',16)} ${BA.archId?'Тест сохранён в архив.':'Это тест из архива от '+new Date(BA.t).toLocaleString('ru-RU')+'.'}</p>`+h:h};
render();
