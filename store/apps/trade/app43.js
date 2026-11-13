// ИТК Трейдинг — архив: сразу видно, прибыльная система или нет, и кнопка «Повторить» (11.11.2026).
"use strict";
ICP.redo=ICP.replay||"M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z";
function arcVerdict(x){const m=x.meta||{};
 if(x.kind==='sys'){if(!m.n)return ['mu','Сделок не было'];if(m.ex>0&&m.n>=20&&(m.pos==null||m.pos>=3))return ['g','Прибыльная · рекомендуется'];if(m.ex>0)return ['y','В плюсе, но неустойчиво · только демо'];return ['r','Убыточная · не рекомендуется']}
 if(!m.rows)return ['mu','Тест прервался · данных нет'];if(m.best)return ['g','Есть прибыльная система · рекомендуется'];return ['r','Прибыльной системы нет · не рекомендуется']}
const ARCC={g:'var(--gr)',r:'var(--rd)',y:'#d29922',mu:'var(--mu)'};
const _ar43=archRow;archRow=function(x){const h=_ar43(x),[k,t]=arcVerdict(x),col=ARCC[k];
 return h.replace('<div class="plan" style="cursor:default;padding:8px 10px">',`<div class="plan" style="cursor:default;padding:8px 10px;border-left:4px solid ${col};background:linear-gradient(90deg,${k==='g'?'rgba(8,153,129,.10)':k==='r'?'rgba(242,54,69,.08)':'transparent'},transparent 60%)">`)
  .replace(/(<\/b><span class="mu sm">[^<]*<\/span><\/div>)/,`$1<div style="margin-top:4px"><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700;color:#fff;background:${col}">${t}</span></div>`)
  .replace(`<button class="btn2 sm" data-a="arcdel"`,`<button class="btn2 sm" style="flex:1" data-a="arcrep" data-v="${x.id}">${ic('redo',16)} Повторить</button><button class="btn2 sm" data-a="arcdel"`)};
async function arcRepeat(id,el){if(el)el.textContent='Готовлю…';let it,d;try{({it,d}=await archGet(id))}catch(e){toast('Не удалось открыть: '+(e.message||''));if(el)el.textContent='Повторить';return}
 if(d.kind==='sys'){ARCHVIEW=d;const b=document.createElement('button');b.dataset.a='arcrerun';b.style.display='none';document.body.appendChild(b);b.click();b.remove();return}
 const fl=d.fl||{},m=it.meta||{},mf=m.fl||{};UI.btHtf=fl.htf?1:0;UI.btAdx=fl.adx?1:0;UI.btMaker=fl.maker?1:0;UI.btHrs=mf.hrs?1:0;UI.btVol=mf.vol?1:0;UI.btOpt=mf.opt?1:0;UI.btSM=mf.sm||1;UI.btHonest=1;UI.fam='all';
 if(d.mkt)UI.mkt=d.mkt;const tfs=[...new Set((d.rows||[]).map(r=>r.tf))];if(tfs.length){UI.spd=tfGrp(tfs[0]);UI.tfs=tfs}
 if(d.kind==='multi'){CSEL=new Set(d.coins||[]);UI.cpre='';UI.cgskip=0;UI.labTab='coins';saveUI();render();window.scrollTo(0,0);toast('Повторяю тот же тест на свежих данных');cgRun()}
 else{UI.sym=d.sym||UI.sym;if(typeof C!=='undefined')C=[];BA=null;UI.labTab='tests';saveUI();render();window.scrollTo(0,0);toast('Повторяю тест '+UI.sym+' на свежих данных');baRun()}}
window.addEventListener('click',e=>{const el=e.target.closest('[data-a="arcrep"]');if(!el)return;e.stopPropagation();e.preventDefault();arcRepeat(el.dataset.v,el)},true);
// в названии теста — свечи, которые реально проверялись
const _bm43=baMeta;baMeta=function(B){const m=_bm43(B);try{const t=[...new Set((B.rows||[]).map(r=>r.tf))];if(t.length)m.tfs=t}catch(_){}return m};
// ---------- длительность: сколько времени шла проверка и сколько займёт серия ----------
function fmtDur(d){d=Math.max(0,Math.round(d));if(d<60)return dayW(d);let m=Math.floor(d/30);const r=d-m*30,y=Math.floor(m/12);m-=y*12;return (y?y+(y===1?' год':y<5?' года':' лет'):'')+(m?(y?' ':'')+m+' мес.':'')+(r&&!y?' '+r+' дн.':'')}
function bestRow(rows){return (rows||[]).filter(g=>g.act&&g.plus/g.act>=0.7&&g.trades>=60&&g.avgEx>=0.3).sort((a,b)=>(b.stable-a.stable)||(b.avgEx-a.avgEx))[0]}
function durTxt43(D){if(!D)return '';if(D.kind==='sys'){const per=D.n/Math.max(1,D.hd||D.d)*30;return `Проверка шла <b>${fmtDur(D.d)}</b>${D.hd?` (вслепую ${fmtDur(D.hd)})`:''} · ${D.n} сделок, ≈ ${fmt(per,per<10?1:0)} в месяц · серия из 30 сделок ≈ <b>${per>0?fmtDur(30/per*30):'—'}</b>`}
 const per=D.trades/Math.max(1,D.act)/Math.max(1,D.d)*30;return `Проверка вслепую шла <b>${fmtDur(D.d)}</b> · ≈ ${fmt(per,per<10?1:0)} сделок в месяц на монету · серия из 30 сделок ≈ <b>${per>0?fmtDur(30/per*30):'—'}</b>`}
function durOf(kind,data){try{if(kind==='sys'){const t1=data.t1,t0=data.t0,tm=data.honest&&data.honest.tm,A=data.A||{};return {kind:'sys',d:(t1-t0)/86400,hd:tm?(t1-tm)/86400:0,n:A.n||0,tot:(A.ex||0)*(A.n||0),nc:1}}
 if(kind==='multi'){const B=unpackBA(data),g=bestRow(B.rows);return g?{kind:'multi',d:g.tdays||0,trades:g.trades,act:g.act,tot:g.coins.reduce((a,x)=>a+(+x.sum||0),0),nc:g.coins.length||1}:null}
 if(kind==='single'){const t=(data.rows||[]).filter(x=>x.n>=10&&x.sum>0&&x.pos===4).sort((a,b)=>b.sum-a.sum)[0];return t?{kind:'single',d:t.tdays||0,tot:t.sum,nc:1,n:t.n}:null}}catch(_){}return null}
// ---------- «Если вложить $1000» ----------
function k1000(tot,nc,days,note){const pr=tot/100*1000/Math.max(1,nc),pc=pr/10,mo=days>0?pc/days*30:0,col=pr>=0?'var(--gr)':'var(--rd)';
 return `<div style="margin-top:8px;padding:10px 12px;border-radius:8px;border:2px solid ${col};background:${pr>=0?'rgba(8,153,129,.12)':'rgba(242,54,69,.10)'}">
 <div class="sm mu">${ic('wallet',14)} Если вложить $1 000</div>
 <div style="font-size:20px;font-weight:700;margin-top:2px">$1 000 → <span style="color:${col}">${'$'+Math.round(1000+pr).toLocaleString('ru-RU')}</span></div>
 <div class="sm"><b style="color:${col}">${USD(pr)} (${pc>=0?'+':''}${fmt(pc,1)}%)</b> за <b>${fmtDur(days)}</b>${mo?` · ≈ ${mo>=0?'+':''}${fmt(mo,1)}% в месяц`:''}</div>
 <div class="sm mu" style="margin-top:2px">${note}</div></div>`}
const k1000n=nc=>nc>1?`Депозит делится поровну на ${nc} монет (по ${USD(1000/nc,false)} на сделку), без плеча, комиссия вычтена.`:'На каждую сделку ставится весь депозит, без плеча, комиссия вычтена.';
const _as43=archSave;archSave=async function(kind,title,meta,data,id){const D=durOf(kind,data);return _as43(kind,title,D?Object.assign({},meta,{dur:D}):meta,data,id)};
let ADUR={};try{ADUR=JSON.parse(localStorage.getItem('arcdur2')||'{}')}catch(_){}
const ADQ=[];let ADBUSY=false;
async function adNext(){if(ADBUSY)return;ADBUSY=true;while(ADQ.length){const id=ADQ.shift();if(ADUR[id]!==undefined)continue;try{const {it,d}=await archGet(id);ADUR[id]=durOf(it.kind,d)||0}catch(_){ADUR[id]=0}
  try{localStorage.setItem('arcdur2',JSON.stringify(ADUR))}catch(_){}const e=document.getElementById('ad_'+id);if(e)e.innerHTML=ADUR[id]?adHtml(ADUR[id]):''}ADBUSY=false}
function adHtml(D){return (D.kind!=='single'?`${ic('history',14)} ${durTxt43(D)}`:'')+(D.tot!=null?k1000(D.tot,D.nc||1,D.kind==='sys'?(D.hd||D.d):D.d,k1000n(D.nc||1)):'')}
const _ar43b=archRow;archRow=function(x){const h=_ar43b(x),m=x.meta||{};if(x.kind==='multi'&&!m.rows)return h;
 let D=m.dur&&m.dur.tot!=null?m.dur:ADUR[x.id];const need=x.kind==='sys'||m.best;if(D===undefined&&need){if(!ADQ.includes(x.id))ADQ.push(x.id);setTimeout(adNext,50)}
 const line=D?adHtml(D):D===undefined&&need?'<span class="mu">Считаю длительность и доход…</span>':'';if(!line)return h;
 return h.replace('<div class="row" style="margin:6px 0 0;gap:6px">',`<div class="sm" id="ad_${x.id}" style="margin-top:4px">${line}</div><div class="row" style="margin:6px 0 0;gap:6px">`)};
// то же в «Итогах» и в выводе теста
const durRow=g=>{const u=baUsd(g),per=(g.trades||0)/Math.max(1,g.act||1)/Math.max(1,u.d)*30;return `<p class="sm" style="margin:6px 0 0">${ic('history',14)} Проверка вслепую шла <b>${fmtDur(u.d)}</b> · ≈ ${fmt(per,per<10?1:0)} сделок в месяц на монету · серия из 30 сделок ≈ <b>${per>0?fmtDur(30/per*30):'—'}</b></p>`};
const g1000=g=>k1000(g.coins.reduce((a,x)=>a+(+x.sum||0),0),g.coins.length||1,baUsd(g).d,k1000n(g.coins.length||1));
const _rc43=resCard;resCard=function(r){const h=_rc43(r),b=r.top&&r.top[0];if(!b)return h;const i=h.indexOf('<p class="sm" style="margin:6px 0 0"><b>');return i>=0?h.slice(0,i)+durRow(b)+g1000(b)+h.slice(i):h};
const _bk43=baKey;baKey=function(B){const h=_bk43(B),R=B.rows||[],ok=R.filter(g=>g.act&&g.plus/g.act>=0.6&&g.trades>=20).map(g=>({g,u:baUsd(g)})).filter(x=>x.u.mo>0).sort((a,b)=>b.u.mo-a.u.mo);if(!ok.length)return h;
 const k='<p class="sm" style="margin:8px 0 0">Считаем просто';const i=h.indexOf(k);return i>=0?h.slice(0,i)+durRow(ok[0].g)+g1000(ok[0].g)+h.slice(i):h};
// одиночная проверка системы
const _brs43=btRes;btRes=function(){const h=_brs43(),B=BT7;if(!B||!B.A||B.busy||B.err||!B.A.n)return h;const t0=B.honest&&B.honest.tm?B.honest.tm:B.t0,d=Math.max(1,((B.t1||0)-(t0||0))/86400),box=k1000(B.A.ex*B.A.n,1,d,k1000n(1));
 const a=h.indexOf('Ставка $100 на сделку, без плеча'),e=a>=0?h.indexOf('</b></div></div>',a):-1;return e>=0?h.slice(0,e+16)+box+h.slice(e+16):box+h};
render();
