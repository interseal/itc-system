// №529: нажатая кнопка быстрого выбора подсвечена, «Отмечено монет: N», кнопка запуска — сразу под настройками, список монет — внизу.
// Alex Trade — №528 (13.11.2026): одна система — сразу на многих монетах, на демо-счёте или на Binance.
// У каждой системы в «Вся биржа» кнопка «Запустить на лучших монетах». Приложение берёт из базы результат этой системы
// по всем проверенным монетам, сортирует монеты от лучших к худшим и открывает окно пакетного запуска:
// выбрать 20 / 50 / 70 / 100 лучших или все устойчивые, ставку, число сделок и где торговать — демо или Binance.
// Сервер ведёт на каждой монете свою серию, а вместе они видны одним пакетом в «Анализ → Серии».
"use strict";
(function(){
 if(window.__ITC_ROBOT)return;
 const MAXC=100;
 const sysKey=a=>[a.tf,a.st,a.k,a.dir||0].join('|');
 // ---------- кнопка у каждой системы ----------
 const _fb64=fBody;
 fBody=function(){let h=_fb64();const T=FULL.top;if(!T||FULL.running||FULL.busy)return h;
  const i0=h.indexOf('Системы, которые работают на большинстве монет'),i1=h.indexOf('<div class="card"><h3 style="margin:0 0 4px">Самые прибыльные пары');
  if(i0<0||i1<0||i1<i0)return h;
  const map={};(T.sysAll||T.sys||[]).forEach(a=>{const t=esc(rowTxt(a));if(!map[t])map[t]=a});
  let sec=h.slice(i0,i1);if(!sec.endsWith('</div>'))return h;sec=sec.slice(0,-6);
  const parts=sec.split('<div class="plan ');
  for(let j=1;j<parts.length;j++){const m=parts[j].match(/<b style="flex:1">\d+\. ([^<]*)<\/b>/);const a=m&&map[m[1]];if(!a)continue;
   const p=parts[j],e=p.lastIndexOf('</div>');if(e<0)continue;
   parts[j]=p.slice(0,e)+`<button class="go b" style="margin-top:8px;padding:10px;display:flex;align-items:center;justify-content:center;gap:6px" data-a="fpk" data-v="${sysKey(a)}">${ic('rocket',18)} Запустить на лучших монетах</button>`+p.slice(e)}
  return h.slice(0,i0)+parts.join('<div class="plan ')+'</div>'+h.slice(i1)};

 // ---------- окно «собираю монеты» ----------
 let LD=null;
 function ldShow(t){if(!LD){LD=document.createElement('div');LD.style.cssText='position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';document.body.appendChild(LD)}
  LD.innerHTML=`<div class="bz63" style="max-width:420px;width:100%;background:var(--pan,#1e222d)"><span class="sp63"></span><div class="sm" style="flex:1">${t}</div></div>`}
 function ldHide(){if(LD){LD.remove();LD=null}}

 // ---------- результат системы по каждой монете ----------
 async function sysCoins(a){const syms=await fCoins(),sig=fSig(),out=[];let done=0;
  const getRes=async part=>{try{const j=await capi('res_get',{sig,syms:part,tfs:[a.tf]});return j.items||[]}catch(e){if(part.length<=1)return [];const h=Math.ceil(part.length/2);return (await getRes(part.slice(0,h))).concat(await getRes(part.slice(h)))}};
  const packs=[];for(let i=0;i<syms.length;i+=20)packs.push(syms.slice(i,i+20));
  let qi=0;const work=async()=>{while(qi<packs.length){const p=packs[qi++];const items=await getRes(p);
   for(const it of items){if(it.tf!==a.tf)continue;let d=it.data;if(typeof d==='string')try{d=JSON.parse(d)}catch(_){continue}if(!d||!d.e)continue;
    const e=d.e.find(x=>x[0]===a.st&&+x[1]===+a.k&&+x[2]===+(a.dir||0));if(!e)continue;const [,,,n,ex,pos,dd,stop]=e;
    out.push({sym:it.sym,n:+n,ex:+ex,sum:n*ex,pos:+pos,dd:+dd||0,stop:+stop||0,td:Math.max(1,+d.tdays||1)})}
   done+=p.length;ldShow(`Собираю результаты системы по монетам: <b>${Math.min(done,syms.length)} из ${syms.length}</b>`)}};
  await Promise.all([work(),work(),work(),work()]);
  const st=x=>x.n>=3&&x.sum>0&&x.pos>=3;
  out.sort((p,q)=>(st(q)-st(p))||(q.sum-p.sum));return out}

 async function open(v){const [tf,st,k,dir]=v.split('|');const T=FULL.top;if(!T)return;
  const a=(T.sysAll||T.sys||[]).find(x=>sysKey(x)===v)||{tf,st,k:+k,dir:+dir};
  ldShow('Собираю результаты системы по монетам…');let cs;
  try{cs=await sysCoins(a)}catch(e){ldHide();toast('Не удалось получить результаты из базы');return}ldHide();
  if(!cs.length){toast('В базе нет результатов этой системы по монетам');return}
  const act=cs.filter(x=>x.n>=3),g={tf:a.tf,strat:a.st,dir:+(a.dir||0),k:+a.k,sname:(T.nm&&T.nm[a.st])||a.st,coins:cs,
   tdays:act.length?act.reduce((s,x)=>s+x.td,0)/act.length:30,trades:act.reduce((s,x)=>s+x.n,0),act:act.length,plus:act.filter(x=>x.sum>0).length};
  pkOpenG(g,UI.mkt,Object.assign({},FULL.fl||{}));
  const stab=cs.filter(x=>x.n>=3&&x.sum>0&&x.pos>=3);
  PK.big=1;PK.stab=stab.length;PK.pick=stab.length>=50?50:'';PK.live=0;PK.ok=0;PK.sel=new Set(stab.slice(0,Math.min(50,stab.length)).map(x=>x.sym));
  if(!PK.sel.size)PK.sel=new Set(cs.filter(x=>x.sum>0).slice(0,50).map(x=>x.sym));
  PK.n=pkRecN().n;const r=pkRec();PK.amt=r.amt>=10?r.amt:10;pkRe()}

 // ---------- Binance: ключ и баланс ----------
 async function liveOn(){if(PK.mkt!=='fut'){toast('На Binance пакет торгует только на фьючерсах. Переключите рынок на «Фьючерсы» и проверьте биржу заново.');return}
  PK.live=1;PK.ok=0;PK.key=null;PK.bal=null;PK.lerr='';pkRe();
  try{const j=await tapi('tr_keys');const L=(j.keys||[]).filter(x=>x.kind==='fut');if(!L.length){PK.lerr='Ключ Binance для фьючерсов не подключён. Подключите его в «Портфеле» и вернитесь сюда.'}else PK.key=L[0]}catch(_){PK.lerr='Не удалось получить ключи Binance — нет связи с сервером.'}
  if(PK.key&&window.bnBal){try{PK.bal=await window.bnBal(true)}catch(_){}if(PK.bal&&PK.bal.wallet>0){PK.dep=Math.round(PK.bal.wallet);const r=pkRec();PK.amt=r.amt>=10?r.amt:10}}
  pkRe()}

 function liveBox(){const P=PK,fut=P.mkt==='fut',seg=(on,a,t,col)=>`<button class="btn2" data-a="${a}" style="flex:1;padding:10px;${on?`background:${col};color:#fff;border-color:transparent`:''}">${t}</button>`;
  let h=`<div class="row" style="gap:6px;margin:0 0 6px"><label style="flex:none;min-width:0">Где торговать</label></div><div class="row" style="gap:6px;margin:0 0 8px">${seg(!P.live,'pkdemo',`${ic('bot',18)} Демо-счёт`,'var(--ac)')}${seg(P.live,'pklive',`${ic('wallet',18)} Binance`,'#f23645')}</div>`;
  if(!P.live)return h+(fut?'':'<p class="hint" style="margin-top:0">На Binance пакет торгует только на фьючерсах.</p>');
  const N=P.sel.size,amt=+P.amt||0,lev=P.lev||1,need=amt*N/lev;
  h+=`<div class="wbox" style="border:2px solid #f23645">`;
  if(P.lerr)h+=`<p class="sm r" style="margin:0">${esc(P.lerr)}</p>`;
  else if(!P.key)h+=`<div class="row" style="margin:0;align-items:center;gap:8px"><span class="sp63"></span><span class="sm">Проверяю ключ и баланс Binance…</span></div>`;
  else{const B=P.bal;
   h+=`<div class="sm"><b>Настоящие деньги.</b> Ключ: <b>${esc(P.key.name||'Binance фьючерсы')}</b>${B&&B.wallet>0?` · на счёте $${fmt(B.wallet,0)}, свободно $${fmt(B.avail,0)}`:''}.</div>
   <div class="sm" style="margin-top:4px">Сервер сам открывает и закрывает сделки на Binance по правилам системы на каждой отмеченной монете: вход по рынку, стоп и тейк ставятся на бирже сразу и работают круглосуточно. Рядом идёт такая же серия на демо — итоги можно сравнивать.</div>
   ${B&&B.avail>0&&need>B.avail?`<p class="sm r" style="margin:6px 0 0"><b>Залога может не хватить.</b> Если сделки откроются сразу на всех ${N} монетах, нужно до $${fmt(need,0)}, а свободно $${fmt(B.avail,0)}. Часть сделок тогда не откроется. Уменьшите ставку или число монет.</p>`:''}
   <div class="sm mu" style="margin-top:4px">Нужен односторонний режим позиций на Binance (One-way). Сделки могут закрываться в минус.</div>
   <label style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;cursor:pointer"><input type="checkbox" data-a="pkok" ${P.ok?'checked':''} style="width:20px;height:20px;flex:none;margin-top:2px"><span class="sm"><b>Подтверждаю торговлю.</b> Это настоящие деньги на моём счёте Binance.</span></label>`}
  return h+'</div>'}

 function pickBox(){const P=PK,cs=P.g.coins,plus=cs.filter(x=>x.sum>0),b=(n,t)=>{const on=String(P.pick)===String(n);return `<button class="btn2 sm" data-a="pkpick" data-v="${n}" style="${on?'background:var(--ac);color:#fff;border-color:var(--ac)':''}">${on?ic('cbon',16)+' ':''}${t}</button>`};
  return `<div class="wbox"><b>${ic('flag',18)} Быстрый выбор монет</b><div class="sm mu" style="margin:2px 0 6px">Монеты ниже стоят от лучших к худшим по проверке. Устойчивые — в плюсе в обеих половинах проверки: таких ${P.stab}.</div>
   <div class="row" style="gap:6px;margin:0;flex-wrap:wrap">${[20,50,70,100].filter(n=>n<=Math.max(20,plus.length)).map(n=>b(n,'Лучшие '+n)).join('')}${P.stab?b('stab',`Все устойчивые (${Math.min(P.stab,MAXC)})`):''}${b('none','Снять все')}</div>
   <div class="sm" style="margin-top:8px;font-size:15px">Отмечено монет: <b style="color:var(--ac)">${P.sel.size}</b>. Список монет — внизу, под кнопкой запуска.</div>${P.sel.size>MAXC?`<p class="sm r" style="margin:6px 0 0">Отмечено ${P.sel.size} монет — за раз можно запустить не больше ${MAXC}.</p>`:''}</div>`}

 const _pks64=pkSheet;
 pkSheet=function(){let h=_pks64();const P=PK;if(!P)return h;
  if(P.big){const k=h.indexOf('<div class="row" style="align-items:center;margin-bottom:4px"><label style="flex:1">Монеты');
   if(k>=0){const em='«Все» — отметить все.</p>',k2=h.indexOf(em,k);
    if(k2>0){const list=h.slice(k,k2+em.length);h=h.slice(0,k)+pickBox()+h.slice(k2+em.length);
     const hm='«Анализ → Серии».</p>',k3=h.indexOf(hm);const L=`<h3 style="margin:16px 0 6px">${ic('flag',18)} Монеты</h3>`+list;
     h=k3>0?h.slice(0,k3+hm.length)+L+h.slice(k3+hm.length):h+L}else h=h.slice(0,k)+pickBox()+h.slice(k)}}
  const j=h.indexOf('<div id="pkrecb">');if(j>=0)h=h.slice(0,j)+liveBox()+h.slice(j);
  if(P.live){const block=!P.key||!P.ok;
   h=h.replace(/data-a="pkgo"([^>]*)>/,(m,r)=>`data-a="pkgo"${r.replace(/\sdisabled/,'')}${block?' disabled':''} style="background:#f23645;color:#fff${block?';opacity:.45':''}">`);
   h=h.replace('Пока на демо-деньгах.','Торговля идёт на настоящем счёте Binance.')}
  return h};

 const _go64=pkGo;
 pkGo=async function(){const P=PK;if(!P)return;
  if(P.sel.size>MAXC){P.err=[`За раз можно запустить не больше ${MAXC} монет — снимите лишние`];pkRe();return}
  if(P.live){if(!P.key||!P.ok)return;if(P.mkt!=='fut'){P.err=['На Binance пакет торгует только на фьючерсах'];pkRe();return}window.__pkLive=P.key.id}
  try{await _go64()}finally{window.__pkLive=''}};
 const _cap64=tdapi;
 tdapi=function(a,d){if(a==='td_start'&&window.__pkLive&&d&&d.gid){d=Object.assign({},d,{live:window.__pkLive,grp:('Binance · '+(d.grp||'')).slice(0,120)})}return _cap64(a,d)};

 window.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el)return;const a=el.dataset.a,v=el.dataset.v;
  if(PK&&PK.big&&(a==='pkc'||a==='pkall'))PK.pick='';
  if(!['fpk','pkpick','pklive','pkdemo','pkok'].includes(a))return;e.stopPropagation();
  if(a!=='pkok')e.preventDefault();
  if(a==='fpk'){open(v);return}
  if(!PK)return;const x=$('#pka');if(x)PK.amt=+x.value||PK.amt;
  if(a==='pkpick'){const cs=PK.g.coins;PK.pick=v;PK.sel=new Set(v==='none'?[]:v==='stab'?cs.filter(c=>c.n>=3&&c.sum>0&&c.pos>=3).slice(0,MAXC).map(c=>c.sym):cs.filter(c=>c.sum>0).slice(0,+v).map(c=>c.sym));const r=pkRec();if(r.amt>=10)PK.amt=r.amt;pkRe();toast(v==='none'?'Все монеты сняты':`Отмечено монет: ${PK.sel.size}`)}
  else if(a==='pklive'){if(!PK.live)liveOn()}
  else if(a==='pkdemo'){PK.live=0;PK.ok=0;pkRe()}
  else if(a==='pkok'){PK.ok=el.checked?1:0;setTimeout(pkRe,0)}},true);
 try{fPaint()}catch(_){}
})();
