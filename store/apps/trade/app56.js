// Alex Trade — №502 · рисование на графике, своё (12.11.2026).
// Свой слой рисунков поверх графика: ~25 инструментов, у каждого рисунка — цвет, толщина, вид линии,
// закрепить, скрыть, копировать, удалить; точки перетаскиваются пальцем; магнит к ценам свечей;
// позиция «Лонг»/«Шорт» с риском и прибылью и кнопкой «В заявку». Рисунки хранятся для каждой монеты.
"use strict";
// ---------- свои окна вопроса (вместо белых системных) ----------
(function(){const st=document.createElement('style');st.textContent=`.uidlg{position:fixed;inset:0;z-index:80;background:#000b;display:flex;align-items:center;justify-content:center;padding:16px}
.uidlg .uib{width:100%;max-width:400px;background:#1e222d;border:1px solid #363a45;border-radius:14px;padding:18px 16px 12px;box-shadow:0 10px 40px #000c;font-family:Roboto,Arial,sans-serif;color:#d1d4dc}
.uidlg h3{margin:0 0 12px;font-size:17px;font-weight:500}.uidlg p{margin:0 0 6px;color:#9598a1;font-size:14px;line-height:1.4}
.uidlg input{width:100%;box-sizing:border-box;background:#131722;border:1px solid #363a45;border-radius:10px;color:#d1d4dc;font:inherit;font-size:16px;padding:12px;outline:none}.uidlg input:focus{border-color:#2962ff}
.uidlg .uia{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}.uidlg button{border:0;border-radius:10px;padding:10px 18px;font:inherit;font-size:15px;cursor:pointer;background:#2a2e39;color:#d1d4dc}
.uidlg button.ok{background:#2962ff;color:#fff}.uidlg button.del{background:#f23645;color:#fff}`;document.head.appendChild(st)})();
function uiDlg(title,text,val,okL,danger){return new Promise(res=>{const d=document.createElement('div');d.className='uidlg';const inp=val!=null;
 d.innerHTML=`<div class="uib"><h3>${esc(title)}</h3>${text?`<p>${esc(text)}</p>`:''}${inp?`<input maxlength="80" value="${esc(val)}">`:''}<div class="uia"><button data-r="0">Отмена</button><button class="${danger?'del':'ok'}" data-r="1">${esc(okL||'Готово')}</button></div></div>`;
 document.body.appendChild(d);const i=d.querySelector('input');if(i){setTimeout(()=>{i.focus();i.select()},50);i.addEventListener('keydown',e=>{if(e.key==='Enter')done(1)})}
 const done=r=>{d.remove();res(inp?(r?i.value:null):!!r)};d.addEventListener('click',e=>{const b=e.target.closest('[data-r]');if(b){e.stopPropagation();done(+b.dataset.r)}else if(e.target===d)done(0)})})}
const uiAsk=(t,v)=>uiDlg(t,'',v==null?'':v,'Готово'),uiOk=(t,x,okL,danger)=>uiDlg(t,x,null,okL||'Да',danger);
Object.assign(ICP,{
 dwrect:"M3 5v14h18V5H3zm16 12H5V7h14v10z",
 dwpos:"M16 17.01V10h-2v7.01h-3L15 21l4-3.99h-3zM9 3L5 6.99h3V14h2V6.99h3L9 3z",
 dwmag:"M4 3h5v4H4zm11 0h5v4h-5zM4 8h5v5c0 1.66 1.34 3 3 3s3-1.34 3-3V8h5v5c0 4.42-3.58 8-8 8s-8-3.58-8-8V8z",
 dweye:"M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z",
 dwcopy:"M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z",
 dwdel:"M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z",
 dwbrush:"M7 14c-1.66 0-3 1.34-3 3 0 1.31-1.16 2-2 2 .92 1.22 2.49 2 4 2 2.21 0 4-1.79 4-4 0-1.66-1.34-3-3-3zm13.71-9.37l-1.34-1.34c-.39-.39-1.02-.39-1.41 0L9 12.25 11.75 15l8.96-8.96c.39-.39.39-1.02 0-1.41z",
 dwlock:"M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z",
 dwedit:"M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 000-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z",
 dworder:"M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"});
// инструменты: [код, название, сколько точек (0 — кисть)]
const DWG=[['cur','cursor','Курсор',null],
 ['lines','trend','Линии',[['tr','Линия тренда',2],['ray','Луч',2],['ext','Продлённая линия',2],['h','Горизонтальная линия',1],['hray','Горизонтальный луч',1],['v','Вертикальная линия',1],['arrow','Стрелка',2],['chan','Параллельный канал',3]]],
 ['fibs','fib','Фибоначчи и вилы',[['fib','Уровни Фибоначчи',2],['fibx','Расширение Фибоначчи',3],['fibfan','Веер Фибоначчи',2],['fibt','Временные зоны Фибоначчи',2],['pitch','Вилы',3]]],
 ['shapes','dwrect','Фигуры',[['rect','Прямоугольник',2],['ell','Эллипс',2],['tri','Треугольник',3],['brush','Кисть — рисовать пальцем',0]]],
 ['meas','ruler','Измерения',[['prange','Диапазон цены',2],['drange','Диапазон дат',2],['rul','Цена и время вместе',2]]],
 ['pos','dwpos','Позиции',[['long','Позиция «Лонг»',1],['short','Позиция «Шорт»',1]]],
 ['texts','text','Текст и значки',[['txt','Подпись',1],['note','Выноска',2],['mup','Стрелка вверх',1],['mdn','Стрелка вниз',1],['flag','Флажок',1]]]];
const DWN={};DWG.forEach(g=>g[3]&&g[3].forEach(t=>DWN[t[0]]={n:t[1],k:t[2],g:g[0]}));
const DWCOL=['#2962ff','#f23645','#089981','#ffd54f','#ff9800','#ab47bc','#00bcd4','#ffffff','#787b86'];
const DWW=[1,2,3,4];
let DT='cur',DTP=[],DSEL=null,DDR=null,DHOV=null,DFLY=null,DVER=0,DSIG='',DUNDO=[];
const DST=Object.assign({c:'#2962ff',w:2,s:0},LS.get('dwst',{}));
UI.dwLast=UI.dwLast||{};
const FIB2=[0,.236,.382,.5,.618,.786,1,1.618,2.618],FIBX=[0,.618,1,1.618,2.618],FIBF=[.236,.382,.5,.618,.786],FIBT=[0,1,2,3,5,8,13,21,34,55,89];
const dwId=()=>'d'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
// ---------- время ↔ место на экране (через номер свечи, работает и правее последней свечи) ----------
const tfS=()=>C.length>1?Math.max(1,C[C.length-1].time-C[C.length-2].time):60;
function t2l(t){const n=C.length;if(!n)return null;if(t>=C[n-1].time)return n-1+(t-C[n-1].time)/tfS();if(t<=C[0].time)return (t-C[0].time)/tfS();let lo=0,hi=n-1;while(hi-lo>1){const m=(lo+hi)>>1;if(C[m].time<=t)lo=m;else hi=m}return lo+(t-C[lo].time)/((C[hi].time-C[lo].time)||1)}
function l2t(l){const n=C.length;if(!n)return 0;if(l>=n-1)return Math.round(C[n-1].time+(l-(n-1))*tfS());if(l<=0)return Math.round(C[0].time+l*tfS());const i=Math.floor(l);return Math.round(C[i].time+(l-i)*(C[i+1].time-C[i].time))}
const dX=t=>{const l=t2l(t);if(l==null)return null;const x=CH.timeScale().logicalToCoordinate(l);return x==null?null:x};
const dY=p=>CS.priceToCoordinate(p);
function dPT(x,y,mag){let l=CH.timeScale().coordinateToLogical(x);if(l==null)return null;let p=CS.coordinateToPrice(y);if(p==null)return null;
 if(mag&&UI.dwMag!==false){const i=Math.round(l);if(i>=0&&i<C.length){const c=C[i];let best=null,bd=14;[c.open,c.high,c.low,c.close].forEach(v=>{const yy=dY(v);if(yy!=null&&Math.abs(yy-y)<bd){bd=Math.abs(yy-y);best=v}});if(best!=null){p=best;l=i}}}
 return {t:l2t(l),p}}
// ---------- хранение ----------
const dwArr=()=>{const k=dkey();let a=DRAW[k];if(!Array.isArray(a))a=DRAW[k]=[];if(C.length&&a.some(o=>!o.pts))a=DRAW[k]=a.map(dwConv).filter(Boolean);return a};
function dwConv(o){if(o.pts)return o;const n=C.length,lt=n?C[n-1].time:0,ft=n>40?C[n-40].time:(n?C[0].time:0);
 if(o.k==='h')return {id:dwId(),k:'h',pts:[{t:lt,p:o.p}],c:'#ffd54f',w:1,s:0,txt:o.t||''};
 if(o.k==='txt')return {id:dwId(),k:'h',pts:[{t:lt,p:o.p}],c:'#90caf9',w:1,s:2,txt:o.t||''};
 if(o.k==='fib')return {id:dwId(),k:'fib',pts:[{t:ft,p:o.a},{t:lt,p:o.b}],c:'#ffb74d',w:1,s:0};
 if(o.k==='tr')return {id:dwId(),k:'tr',pts:[{t:o.t1,p:o.p1},{t:o.t2,p:o.p2}],c:'#ffd54f',w:2,s:0};return null}
function dwSave(push){if(push!==false){DUNDO.push(JSON.stringify(DRAW[dkey()]||[]));if(DUNDO.length>40)DUNDO.shift()}LS.set('draw',DRAW);DVER++}
function dwUndo(){if(!DUNDO.length){toast('Отменять нечего');return}DRAW[dkey()]=JSON.parse(DUNDO.pop());DSEL=null;LS.set('draw',DRAW);DVER++;dwBar()}
// старые линии цены больше не рисуем — всё рисует свой слой
paintDraw=function(){if(!CS)return;DRL.forEach(x=>{try{x.pl?CS.removePriceLine(x.pl):CH.removeSeries(x.se)}catch(e){}});DRL=[];DVER++};
// ---------- геометрия ----------
const LW=()=>{const w=$('#dwc');return w?w.clientWidth:0},LH=()=>{const w=$('#dwc');return w?w.clientHeight:0};
const plotW=()=>{let pw=60;try{pw=CH.priceScale('right').width()}catch(e){}return Math.max(10,LW()-pw)};
function extend(x1,y1,x2,y2,both){const W=plotW(),H=LH();const dx=x2-x1,dy=y2-y1;if(Math.abs(dx)<1e-6&&Math.abs(dy)<1e-6)return [x1,y1,x2,y2];
 const far=4*(W+H)/Math.hypot(dx,dy);const ex=x2+dx*far,ey=y2+dy*far;return both?[x1-dx*far,y1-dy*far,ex,ey]:[x1,y1,ex,ey]}
const lerpP=(o,t)=>{const [a,b]=o.pts;return a.p+(b.p-a.p)*((t2l(t)-t2l(a.t))/((t2l(b.t)-t2l(a.t))||1))};
// примитивы: s — отрезок, r — прямоугольник, e — эллипс, g — многоугольник, l — ломаная, t — текст
function dwPrims(o){if(!o||!o.pts)return [];const P=o.pts.map(q=>({x:dX(q.t),y:dY(q.p),p:q.p,t:q.t}));if(P.some(q=>q.x==null||q.y==null))return [];
 const [a,b,c]=P,W=plotW(),out=[],pr=v=>fp(v),pct=(v,base)=>base?(v-base)/base*100:0,S=(x1,y1,x2,y2,st)=>out.push({k:'s',v:[x1,y1,x2,y2],st}),T=(x,y,s,al,bg,col)=>out.push({k:'t',x,y,s,al,bg,col});
 switch(o.k){
  case 'tr':S(a.x,a.y,b.x,b.y);break;
  case 'ray':S(...extend(a.x,a.y,b.x,b.y));break;
  case 'ext':S(...extend(a.x,a.y,b.x,b.y,true));break;
  case 'arrow':{S(a.x,a.y,b.x,b.y);const an=Math.atan2(b.y-a.y,b.x-a.x),L=10+o.w*2;S(b.x,b.y,b.x-L*Math.cos(an-.45),b.y-L*Math.sin(an-.45));S(b.x,b.y,b.x-L*Math.cos(an+.45),b.y-L*Math.sin(an+.45));break}
  case 'h':S(0,a.y,W,a.y);out.push({k:'ax',y:a.y,s:pr(a.p)});if(o.txt)T(8,a.y-5,o.txt,'left');break;
  case 'hray':S(a.x,a.y,W,a.y);out.push({k:'ax',y:a.y,s:pr(a.p)});if(o.txt)T(a.x+6,a.y-5,o.txt,'left');break;
  case 'v':S(a.x,0,a.x,LH());break;
  case 'chan':{const off=c.y-(a.y+(b.y-a.y)*((c.x-a.x)/((b.x-a.x)||1)));S(a.x,a.y,b.x,b.y);S(a.x,a.y+off,b.x,b.y+off);S(a.x,a.y+off/2,b.x,b.y+off/2,1);out.push({k:'g',v:[a.x,a.y,b.x,b.y,b.x,b.y+off,a.x,a.y+off],fill:1});break}
  case 'fib':{const x1=Math.min(a.x,b.x);FIB2.forEach(f=>{const p=b.p+(a.p-b.p)*f,y=dY(p);if(y==null)return;S(x1,y,W,y,f===0||f===1?0:2);T(x1+4,y-4,(f*100).toFixed(1)+'% · '+pr(p),'left')});break}
  case 'fibx':{const d=b.p-a.p;S(a.x,a.y,b.x,b.y,2);S(b.x,b.y,c.x,c.y,2);FIBX.forEach(f=>{const p=c.p+d*f,y=dY(p);if(y==null)return;S(c.x,y,W,y,f===1?0:2);T(c.x+4,y-4,(f*100).toFixed(1)+'% · '+pr(p),'left')});break}
  case 'fibfan':{FIBF.concat([1]).forEach(f=>{const y=dY(a.p+(b.p-a.p)*f);if(y==null)return;const e=extend(a.x,a.y,b.x,y);S(...e,f===1?0:2);T(b.x+4,y-4,(f*100).toFixed(1)+'%','left')});break}
  case 'fibt':{const la=t2l(a.t),lb=t2l(b.t),u=lb-la;FIBT.forEach(f=>{const x=CH.timeScale().logicalToCoordinate(la+u*f);if(x==null||x<0||x>W)return;S(x,0,x,LH(),f<=1?0:2);T(x+3,14,String(f),'left')});break}
  case 'pitch':{const mx=(b.x+c.x)/2,my=(b.y+c.y)/2;S(...extend(a.x,a.y,mx,my));const dx=mx-a.x,dy=my-a.y;S(...extend(b.x,b.y,b.x+dx,b.y+dy));S(...extend(c.x,c.y,c.x+dx,c.y+dy));S(b.x,b.y,c.x,c.y,2);break}
  case 'rect':out.push({k:'r',v:[Math.min(a.x,b.x),Math.min(a.y,b.y),Math.abs(b.x-a.x),Math.abs(b.y-a.y)],fill:1});break;
  case 'ell':out.push({k:'e',v:[(a.x+b.x)/2,(a.y+b.y)/2,Math.abs(b.x-a.x)/2,Math.abs(b.y-a.y)/2],fill:1});break;
  case 'tri':out.push({k:'g',v:[a.x,a.y,b.x,b.y,c.x,c.y],fill:1});break;
  case 'brush':out.push({k:'l',v:P.flatMap(q=>[q.x,q.y])});break;
  case 'prange':{const xm=(a.x+b.x)/2;out.push({k:'r',v:[Math.min(a.x,b.x),Math.min(a.y,b.y),Math.abs(b.x-a.x),Math.abs(b.y-a.y)],fill:1,col:b.p>=a.p?'#089981':'#f23645'});S(xm,a.y,xm,b.y);const ch=pct(b.p,a.p);T(xm,(b.y<a.y?Math.min(a.y,b.y)-8:Math.max(a.y,b.y)+16),`${ch>=0?'+':''}${fmt(ch,2)}% · ${fp(b.p-a.p)}`,'center',1);break}
  case 'drange':{const ym=(a.y+b.y)/2,n=Math.round(Math.abs(t2l(b.t)-t2l(a.t))),sec=Math.abs(b.t-a.t);out.push({k:'r',v:[Math.min(a.x,b.x),Math.min(a.y,b.y),Math.abs(b.x-a.x),Math.abs(b.y-a.y)],fill:1,col:'#2962ff'});S(a.x,ym,b.x,ym);T((a.x+b.x)/2,Math.max(a.y,b.y)+16,`${n} ${plW(n,'свеча','свечи','свечей')} · ${dwDur(sec)}`,'center',1);break}
  case 'rul':{const ch=pct(b.p,a.p),n=Math.round(Math.abs(t2l(b.t)-t2l(a.t)));out.push({k:'r',v:[Math.min(a.x,b.x),Math.min(a.y,b.y),Math.abs(b.x-a.x),Math.abs(b.y-a.y)],fill:1,col:b.p>=a.p?'#089981':'#f23645'});S(a.x,a.y,b.x,b.y,2);T((a.x+b.x)/2,Math.max(a.y,b.y)+16,`${ch>=0?'+':''}${fmt(ch,2)}% · ${fp(b.p-a.p)} · ${n} ${plW(n,'свеча','свечи','свечей')} · ${dwDur(Math.abs(b.t-a.t))}`,'center',1);break}
  case 'long':case 'short':{const x1=a.x,x2=Math.max(b.x,a.x+20),tp=b.p,sl=c.p,ep=a.p,yt=dY(tp),ys=dY(sl);if(yt==null||ys==null)break;
   /* №509 · позиция без рамок: мягкая заливка зон, тонкая линия входа, подписи — сплошными плашками */
   out.push({k:'r',v:[x1,Math.min(a.y,yt),x2-x1,Math.abs(yt-a.y)],fill:1,col:'#089981',fa:.18,ns:1});out.push({k:'r',v:[x1,Math.min(a.y,ys),x2-x1,Math.abs(ys-a.y)],fill:1,col:'#f23645',fa:.18,ns:1});
   S(x1,a.y,x2,a.y,0,'#9598a1');const sd=o.k==='long'?1:-1,gp=(tp-ep)/ep*100*sd,lp=(ep-sl)/ep*100*sd,rr=lp>0?gp/lp:0,xm=(x1+x2)/2;
   T(xm,yt+(yt<a.y?-7:17),`Цель ${gp>=0?'+':''}${fmt(gp,2)}% · ${pr(tp)}`,'center',2,'#089981');T(xm,ys+(ys>a.y?17:-7),`Стоп −${fmt(Math.abs(lp),2)}% · ${pr(sl)}`,'center',2,'#f23645');
   T(xm,a.y+(yt<a.y?17:-7),`${o.k==='long'?'Лонг':'Шорт'} · вход ${pr(ep)} · прибыль/риск ${fmt(rr,2)}`,'center',2,'#50535e');
   out.push({k:'ax',y:yt,s:pr(tp),col:'#089981'},{k:'ax',y:a.y,s:pr(ep),col:'#50535e'},{k:'ax',y:ys,s:pr(sl),col:'#f23645'});break}
  case 'txt':T(a.x,a.y,o.txt||'Подпись','left',0,o.c,1);break;
  case 'note':{S(a.x,a.y,b.x,b.y);T(b.x,b.y,o.txt||'Выноска','center',1,o.c,1);break}
  case 'mup':out.push({k:'g',v:[a.x,a.y,a.x-8,a.y+14,a.x+8,a.y+14],fill:2});break;
  case 'mdn':out.push({k:'g',v:[a.x,a.y,a.x-8,a.y-14,a.x+8,a.y-14],fill:2});break;
  case 'flag':{S(a.x,a.y,a.x,a.y-26);out.push({k:'g',v:[a.x,a.y-26,a.x+16,a.y-21,a.x,a.y-16],fill:2});break}}
 return out}
function dwDur(s){const m=s/60,h=m/60,d=h/24;return d>=1?fmt(d,d<10?1:0)+' дн.':h>=1?fmt(h,1)+' ч':fmt(m,0)+' мин'}
// ---------- рисуем ----------
function dwDraw(){const cv=$('#dwc'),w=$('#chartWrap');if(!cv||!w||!CS||!CH)return;const W=w.clientWidth-36,H=w.clientHeight,r=devicePixelRatio||1;
 if(cv.width!==Math.round(W*r)||cv.height!==Math.round(H*r)){cv.width=Math.round(W*r);cv.height=Math.round(H*r);cv.style.width=W+'px';cv.style.height=H+'px'}
 const g=cv.getContext('2d');g.setTransform(r,0,0,r,0,0);g.clearRect(0,0,W,H);if(UI.dwHide){dwPreview(g);return}
 g.save();g.beginPath();g.rect(0,0,plotW(),H);g.clip();
 const arr=dwArr();arr.forEach(o=>{if(!o.hid)dwPaint(g,o,o.id===DSEL)});dwPreview(g);g.restore();
 arr.forEach(o=>{if(o.hid)return;dwPrims(o).forEach(p=>{if(p.k!=='ax')return;g.fillStyle=p.col||o.c;g.fillRect(plotW(),p.y-9,W-plotW(),18);g.fillStyle='#fff';g.font='11px Roboto,Arial,sans-serif';g.textAlign='left';g.fillText(p.s,plotW()+4,p.y+4)})})}
function dwPaint(g,o,sel){const col=o.c||DST.c;g.strokeStyle=col;g.fillStyle=col;g.lineWidth=o.w||2;g.lineJoin='round';g.lineCap='round';
 const dash=s=>g.setLineDash(s===1?[8,5]:s===2?[2,4]:[]);
 dwPrims(o).forEach(p=>{dash(p.st!=null?p.st:o.s);
  if(p.k==='s'){g.beginPath();g.moveTo(p.v[0],p.v[1]);g.lineTo(p.v[2],p.v[3]);g.stroke()}
  else if(p.k==='l'){g.beginPath();for(let i=0;i<p.v.length;i+=2)i?g.lineTo(p.v[i],p.v[i+1]):g.moveTo(p.v[i],p.v[i+1]);if(p.ga)g.globalAlpha=p.ga;if(p.lw)g.lineWidth=p.lw;if(p.cl){g.closePath();g.globalAlpha=p.ga||.15;g.fillStyle=p.col||col;g.fill();g.globalAlpha=1}else{if(p.col)g.strokeStyle=p.col;g.stroke();g.strokeStyle=col}g.globalAlpha=1;g.lineWidth=o.w||2}
  else if(p.k==='r'||p.k==='e'||p.k==='g'){g.beginPath();if(p.k==='r')g.rect(...p.v);else if(p.k==='e')g.ellipse(p.v[0],p.v[1],Math.max(1,p.v[2]),Math.max(1,p.v[3]),0,0,7);else{for(let i=0;i<p.v.length;i+=2)i?g.lineTo(p.v[i],p.v[i+1]):g.moveTo(p.v[i],p.v[i+1]);g.closePath()}
   if(p.fill){g.globalAlpha=p.fill===2?1:(p.fa||.15);g.fillStyle=p.col||col;g.fill();g.globalAlpha=1}if(p.fill!==2&&!p.ns){g.strokeStyle=p.col||col;g.stroke();g.strokeStyle=col}}
  else if(p.k==='t'){g.setLineDash([]);g.font=(p.bg?'12px':'13px')+' Roboto,Arial,sans-serif';g.textAlign=p.al||'left';const tw=g.measureText(p.s).width;
   if(p.bg===2){const x0=p.al==='center'?p.x-tw/2-6:p.x-6;g.fillStyle=p.col||'#50535e';g.beginPath();if(g.roundRect)g.roundRect(x0,p.y-14,tw+12,20,4);else g.rect(x0,p.y-14,tw+12,20);g.fill();g.fillStyle='#ffffff';g.fillText(p.s,p.x,p.y);g.fillStyle=col;return}
   if(p.bg){const x0=p.al==='center'?p.x-tw/2-5:p.x-5;g.fillStyle='#131722e6';g.fillRect(x0,p.y-13,tw+10,18);g.strokeStyle=p.col||'#363a45';g.lineWidth=1;g.strokeRect(x0,p.y-13,tw+10,18);g.lineWidth=o.w||2}
   g.fillStyle=p.col||col;g.fillText(p.s,p.x,p.y);g.fillStyle=col}});
 g.setLineDash([]);
 if(sel){o.pts.forEach(q=>{const x=dX(q.t),y=dY(q.p);if(x==null||y==null)return;g.beginPath();g.arc(x,y,6,0,7);g.fillStyle='#131722';g.fill();g.lineWidth=2;g.strokeStyle=o.lock?'#787b86':'#5eb8ff';g.stroke()})}}
function dwPreview(g){if(DT==='cur'||!DTP.length||!DHOV)return;const def=DWN[DT];if(!def)return;const pts=DTP.concat([DHOV]);const need=def.k||2;
 const o=dwMake(DT,pts.length>=need?pts.slice(0,need):dwFill(DT,pts));if(o)dwPaint(g,o,true)}
function dwFill(k,pts){const p=pts.slice();const d=DWN[k];while(p.length<(d.k||2))p.push(Object.assign({},p[p.length-1]));return p}
function dwMake(k,pts){const o={id:dwId(),k,pts:pts.map(q=>({t:q.t,p:q.p})),c:DST.c,w:DST.w,s:DST.s};
 if(k==='long'||k==='short'){const a=pts[0],sd=k==='long'?1:-1,n=Math.max(12,Math.round(((CH.timeScale().getVisibleLogicalRange()||{to:30,from:0}).to-(CH.timeScale().getVisibleLogicalRange()||{from:0}).from)*.25));
  const tr=C.length>20?C.slice(-20).reduce((s,x)=>s+(x.high-x.low),0)/20:a.p*.01;const t2=l2t(t2l(a.t)+n);o.pts=[{t:a.t,p:a.p},{t:t2,p:a.p+sd*tr*3},{t:t2,p:a.p-sd*tr*1.5}];o.w=1}
 if(k==='fib'||k==='fibx'||k==='fibfan'||k==='fibt')o.w=Math.min(o.w,1);return o}
// ---------- попадание пальцем ----------
function dSeg(px,py,x1,y1,x2,y2){const dx=x2-x1,dy=y2-y1,L=dx*dx+dy*dy;let t=L?((px-x1)*dx+(py-y1)*dy)/L:0;t=Math.max(0,Math.min(1,t));return Math.hypot(px-(x1+t*dx),py-(y1+t*dy))}
function dwHit(x,y){const arr=dwArr();const R=12;
 if(DSEL){const o=arr.find(z=>z.id===DSEL);if(o&&!o.hid)for(let i=0;i<o.pts.length;i++){const hx=dX(o.pts[i].t),hy=dY(o.pts[i].p);if(hx!=null&&Math.hypot(hx-x,hy-y)<=R+4)return {o,h:i}}}
 for(let j=arr.length-1;j>=0;j--){const o=arr[j];if(o.hid)continue;for(const p of dwPrims(o)){let hit=false;
  if(p.k==='s')hit=dSeg(x,y,...p.v)<=R;else if(p.k==='l'){for(let i=2;i<p.v.length&&!hit;i+=2)hit=dSeg(x,y,p.v[i-2],p.v[i-1],p.v[i],p.v[i+1])<=R}
  else if(p.k==='r')hit=x>=p.v[0]-6&&x<=p.v[0]+p.v[2]+6&&y>=p.v[1]-6&&y<=p.v[1]+p.v[3]+6;
  else if(p.k==='e')hit=Math.pow((x-p.v[0])/(p.v[2]+6),2)+Math.pow((y-p.v[1])/(p.v[3]+6),2)<=1;
  else if(p.k==='g'){const v=p.v;let mnx=1e9,mxx=-1e9,mny=1e9,mxy=-1e9;for(let i=0;i<v.length;i+=2){mnx=Math.min(mnx,v[i]);mxx=Math.max(mxx,v[i]);mny=Math.min(mny,v[i+1]);mxy=Math.max(mxy,v[i+1])}hit=x>=mnx-6&&x<=mxx+6&&y>=mny-6&&y<=mxy+6}
  else if(p.k==='t'){hit=Math.abs(y-p.y+5)<=12&&Math.abs(x-p.x)<=Math.max(40,p.s.length*3.5)}
  if(hit)return {o,h:-1}}}return null}
// ---------- панель инструментов слева ----------
function dwToolsHtml(){const tb=g=>{if(!g[3])return `<button class="${DT==='cur'?'on':''}" data-a="dwg" data-v="cur" title="Курсор">${ic(g[1],20)}</button>`;
  const last=UI.dwLast[g[0]]||g[3][0][0],on=DWN[DT]&&DWN[DT].g===g[0];return `<button class="${on?'on':''}" data-a="dwg" data-v="${g[0]}" title="${g[2]}">${ic(g[1],20)}<i class="dwdot"></i></button>`};
 return `<div class="tools dwt">${DWG.map(tb).join('')}<span class="dwsep"></span>
 <button class="${UI.dwMag!==false?'on':''}" data-a="dwx" data-v="mag" title="Магнит: точки цепляются к ценам свечей">${ic('dwmag',20)}</button>
 <button class="${UI.dwHide?'on':''}" data-a="dwx" data-v="hide" title="Скрыть все рисунки">${ic('dweye',20)}</button>
 <button data-a="dwx" data-v="undo" title="Отменить">${ic('undo',20)}</button>
 <button data-a="dwx" data-v="clr" title="Удалить все рисунки">${ic('trash',20)}</button></div>
 <div id="dwfly" class="dwfly" style="display:none"></div><div id="dwbar" class="dwbar" style="display:none"></div><canvas id="dwc"></canvas>`}
function dwFly(gk,btn){const f=$('#dwfly');if(!f)return;if(DFLY===gk){f.style.display='none';DFLY=null;return}const g=DWG.find(x=>x[0]===gk);if(!g)return;DFLY=gk;
 f.innerHTML=`<div class="dwfh">${g[2]}</div>`+g[3].map(t=>`<button class="${DT===t[0]?'on':''}" data-a="dwt" data-v="${t[0]}">${esc(t[1])}</button>`).join('');
 const w=$('#chartWrap').getBoundingClientRect(),b=btn.getBoundingClientRect();f.style.display='block';f.style.top=Math.max(0,Math.min(b.top-w.top,w.height-f.offsetHeight-4))+'px'}
function dwSetTool(t){DT=t;DTP=[];DHOV=null;DFLY=null;const f=$('#dwfly');if(f)f.style.display='none';if(t!=='cur'){DSEL=null;const g=DWN[t];if(g)UI.dwLast[g.g]=t;saveUI()}
 $$('.dwt [data-a=dwg]').forEach(b=>b.classList.toggle('on',t==='cur'?b.dataset.v==='cur':!!(DWN[t]&&DWN[t].g===b.dataset.v)));
 const h=$('#toolhint');if(h){const d=DWN[t];if(t==='cur'){h.style.display='none'}else{h.textContent=d.k===0?'Ведите пальцем по графику — рисуется линия':d.k===1?'Нажмите на график — '+d.n.toLowerCase():`${d.n}: нажмите ${d.k} ${plW(d.k,'точку','точки','точек')} (или ведите пальцем от первой ко второй)`;h.style.display='block'}}dwBar();DVER++}
// ---------- панель выделенного рисунка ----------
function dwBar(){const b=$('#dwbar');if(!b)return;const o=DSEL&&dwArr().find(z=>z.id===DSEL);if(!o){b.style.display='none';return}
 const txt=['txt','note','h','hray','cmt','sign','pnote'].includes(o.k),pos=o.k==='long'||o.k==='short';
 b.innerHTML=`<span class="dwn">${esc((DWN[o.k]||{}).n||'Рисунок')}</span>
 <span class="dwcol">${DWCOL.map(c=>`<i data-a="dwc" data-v="${c}" style="background:${c}" class="${o.c===c?'on':''}"></i>`).join('')}</span>
 <button data-a="dww" title="Толщина">${o.w||2} пикс.</button><button data-a="dws" title="Вид линии">${['сплошная','штрих','точки'][o.s||0]}</button>
 ${txt?`<button data-a="dwtxt" title="Текст">${ic('dwedit',16)}</button>`:''}
 ${pos?`<button data-a="dword" title="Перенести в заявку">${ic('dworder',16)} В заявку</button>`:''}
 <button class="${o.lock?'on':''}" data-a="dwlock" title="Закрепить — не двигать случайно">${ic('dwlock',16)}</button>
 <button data-a="dwcp" title="Копировать">${ic('dwcopy',16)}</button><button data-a="dwdel" title="Удалить">${ic('dwdel',16)}</button>`;b.style.display='flex'}
// ---------- экран Терминала: заменяем старую панель ----------
const _tv56=termView;termView=function(){const h=_tv56();return h.replace(/<div class="tools">[\s\S]*?<\/div>/,dwToolsHtml())};
(function(){const st=document.createElement('style');st.textContent=`
#dwc{position:absolute;left:36px;top:0;pointer-events:none;z-index:3}
.dwt{overflow-y:auto;scrollbar-width:none}.dwt::-webkit-scrollbar{display:none}.dwt button{position:relative;flex:none}
.dwt .dwdot{position:absolute;right:2px;bottom:3px;border:3px solid transparent;border-right-color:#787b86;border-bottom-color:#787b86;width:0;height:0}
.dwsep{height:1px;background:var(--ln);margin:3px 4px;flex:none}
.dwfly{position:absolute;left:38px;z-index:8;background:#1e222d;border:1px solid var(--ln);border-radius:10px;padding:4px;min-width:210px;box-shadow:0 6px 24px #0008}
.dwfly .dwfh{font-size:12px;color:var(--mu);padding:4px 10px}.dwfly button{display:block;width:100%;text-align:left;border:0;background:none;color:var(--tx,#d1d4dc);padding:9px 10px;border-radius:8px;font:inherit;font-size:14px;cursor:pointer}
.dwfly button.on,.dwfly button:active{background:#0f2a4d;color:#5eb8ff}
.dwbar{position:absolute;left:42px;right:70px;top:6px;z-index:8;display:flex;flex-wrap:wrap;gap:4px;align-items:center;background:#1e222df2;border:1px solid var(--ln);border-radius:10px;padding:5px 6px;font-size:12px}
.dwbar .dwn{color:var(--mu);margin-right:2px}.dwbar button{border:1px solid var(--ln);background:#131722;color:#d1d4dc;border-radius:7px;padding:4px 7px;font:inherit;font-size:12px;display:flex;align-items:center;gap:3px;cursor:pointer}
.dwbar button.on{border-color:#5eb8ff;color:#5eb8ff}.dwcol{display:flex;gap:3px}.dwcol i{width:18px;height:18px;border-radius:50%;border:2px solid #131722;cursor:pointer;display:block}.dwcol i.on{border-color:#fff}`;document.head.appendChild(st)})();
// ---------- пальцы и мышь ----------
let dwBound=null;
function dwLocal(e){const cv=$('#dwc');const r=cv.getBoundingClientRect();return {x:e.clientX-r.left,y:e.clientY-r.top,inside:e.clientX>=r.left&&e.clientX<=r.left+plotW()&&e.clientY>=r.top&&e.clientY<=r.bottom}}
const dwLockChart=on=>{try{CH.applyOptions({handleScroll:!on,handleScale:!on})}catch(_){}};
function dwBind(){const w=$('#chartWrap');if(!w||w===dwBound)return;dwBound=w;
 w.addEventListener('pointerdown',e=>{if(UI.view!=='term'||!CS||!$('#dwc'))return;if(e.target.closest('.tools,.dwfly,.dwbar,.ol,.olx,.ovbar,button'))return;const q=dwLocal(e);if(!q.inside)return;
  if(DT!=='cur'){e.stopImmediatePropagation();e.preventDefault();dwLockChart(true);try{w.setPointerCapture(e.pointerId)}catch(_){}
   const BR=DWN[DT]&&DWN[DT].k===0;const p=dPT(q.x,q.y,!BR);if(!p)return;
   if(BR){const o=dwMake(DT,[p]);if(DT==='brush')o.w=Math.max(2,o.w);DDR={brush:o};dwArr().push(o);DVER++;return}
   DTP.push(p);DHOV=p;DDR={draw:1,x0:q.x,y0:q.y};const need=DWN[DT].k;if(DTP.length>=need)dwFinish();DVER++;return}
  const hit=dwHit(q.x,q.y);if(!hit){if(DSEL){DSEL=null;dwBar();DVER++}return}
  e.stopImmediatePropagation();e.preventDefault();DSEL=hit.o.id;dwBar();DVER++;if(hit.o.lock)return;
  dwLockChart(true);try{w.setPointerCapture(e.pointerId)}catch(_){}const p0=dPT(q.x,q.y,false);DDR={o:hit.o,h:hit.h,p0,orig:JSON.parse(JSON.stringify(hit.o.pts)),moved:false}},{capture:true,passive:false});
 w.addEventListener('pointermove',e=>{if(!DDR&&!(DT!=='cur'&&DTP.length))return;if(!$('#dwc'))return;const q=dwLocal(e);
  if(DDR&&DDR.brush){const p=dPT(q.x,q.y,false);if(p){const L=DDR.brush.pts,lp=L[L.length-1];if(Math.abs(dX(lp.t)-q.x)+Math.abs(dY(lp.p)-q.y)>3)L.push(p)}DVER++;e.preventDefault();return}
  if(DT!=='cur'&&DTP.length){const p=dPT(q.x,q.y,true);if(p){DHOV=p;DVER++}if(DDR&&DDR.draw)e.preventDefault();return}
  if(DDR&&DDR.o){e.preventDefault();e.stopImmediatePropagation();const p=dPT(q.x,q.y,DDR.h>=0);if(!p)return;DDR.moved=true;const o=DDR.o;
   if(DDR.h>=0){o.pts[DDR.h]={t:p.t,p:p.p};if((o.k==='long'||o.k==='short')&&DDR.h>0){const j=DDR.h===1?2:1;o.pts[j].t=p.t}}
   else{const dl=t2l(p.t)-t2l(DDR.p0.t),dp=p.p-DDR.p0.p;o.pts=DDR.orig.map(z=>({t:l2t(t2l(z.t)+dl),p:z.p+dp}))}DVER++}},{capture:true,passive:false});
 const up=e=>{if(!DDR)return;const d=DDR;DDR=null;dwLockChart(false);
  if(d.brush){if(d.brush.pts.length<2){const a=dwArr();a.splice(a.indexOf(d.brush),1)}else{DUNDO.push(JSON.stringify(dwArr().filter(z=>z!==d.brush)));dwSave(false)}DVER++;return}
  if(d.draw){const q=dwLocal(e);if(DTP.length===1&&Math.hypot(q.x-d.x0,q.y-d.y0)>14&&(DWN[DT]||{}).k>=2){const p=dPT(q.x,q.y,true);if(p){DTP.push(p);if(DTP.length>=DWN[DT].k)dwFinish()}}DVER++;return}
  if(d.o&&d.moved){DUNDO.push(JSON.stringify(dwArr().map(z=>z===d.o?Object.assign({},z,{pts:d.orig}):z)));dwSave(false)}};
 w.addEventListener('pointerup',up,true);w.addEventListener('pointercancel',up,true)}
function dwFinish(){const k=DT,o=dwMake(k,DTP.slice(0,DWN[k].k));DTP=[];DHOV=null;
 if(['txt','note','cmt','sign'].includes(k)){uiAsk(k==='txt'?'Текст подписи':'Текст','').then(t=>{if(!t){dwSetTool('cur');return}o.txt=t.slice(0,80);dwSave();dwArr().push(o);LS.set('draw',DRAW);DSEL=o.id;DVER++;dwSetTool('cur');DSEL=o.id;dwBar()});return}
 dwSave();dwArr().push(o);LS.set('draw',DRAW);DSEL=o.id;DVER++;dwSetTool('cur');DSEL=o.id;dwBar()}
// ---------- кнопки ----------
document.addEventListener('click',e=>{const el=e.target.closest('[data-a]');if(!el||UI.view!=='term')return;const a=el.dataset.a,v=el.dataset.v;const st=()=>{e.stopImmediatePropagation();e.preventDefault()};
 const o=DSEL&&dwArr().find(z=>z.id===DSEL);
 if(a==='dwg'){st();if(v==='cur'){dwSetTool('cur');return}const g=DWN[DT];if(g&&g.g===v&&DFLY!==v){dwFly(v,el);return}dwFly(v,el)}
 else if(a==='dwt'){st();dwSetTool(v)}
 else if(a==='dwx'){st();if(v==='mag'){UI.dwMag=UI.dwMag===false;saveUI();el.classList.toggle('on',UI.dwMag!==false);toast(UI.dwMag!==false?'Магнит включён: точки цепляются к ценам свечей':'Магнит выключен')}
  else if(v==='hide'){UI.dwHide=!UI.dwHide;saveUI();el.classList.toggle('on',!!UI.dwHide);DVER++}
  else if(v==='undo')dwUndo();
  else if(v==='clr'){if(!dwArr().length){toast('Рисунков нет');return}uiOk('Удалить все рисунки?','Все рисунки на графике '+UI.sym+' будут удалены.','Удалить',1).then(y=>{if(y){dwSave();DRAW[dkey()]=[];LS.set('draw',DRAW);DSEL=null;dwBar();DVER++}})}}
 else if(!o)return;
 else if(a==='dwc'){st();dwSave();o.c=v;DST.c=v;LS.set('dwst',DST);LS.set('draw',DRAW);dwBar();DVER++}
 else if(a==='dww'){st();dwSave();o.w=DWW[(DWW.indexOf(o.w||2)+1)%DWW.length];DST.w=o.w;LS.set('dwst',DST);LS.set('draw',DRAW);dwBar();DVER++}
 else if(a==='dws'){st();dwSave();o.s=((o.s||0)+1)%3;DST.s=o.s;LS.set('dwst',DST);LS.set('draw',DRAW);dwBar();DVER++}
 else if(a==='dwtxt'){st();uiAsk('Текст',o.txt||'').then(t=>{if(t!=null){dwSave();o.txt=t.slice(0,80);LS.set('draw',DRAW);DVER++}})}
 else if(a==='dwlock'){st();o.lock=!o.lock;LS.set('draw',DRAW);dwBar();DVER++;toast(o.lock?'Рисунок закреплён':'Рисунок можно двигать')}
 else if(a==='dwcp'){st();dwSave();const n=JSON.parse(JSON.stringify(o));n.id=dwId();n.lock=false;const dl=Math.max(3,Math.round(((CH.timeScale().getVisibleLogicalRange()||{to:40,from:0}).to-((CH.timeScale().getVisibleLogicalRange()||{from:0}).from))*.05));n.pts=n.pts.map(z=>({t:l2t(t2l(z.t)+dl),p:z.p}));dwArr().push(n);LS.set('draw',DRAW);DSEL=n.id;dwBar();DVER++}
 else if(a==='dwdel'){st();dwSave();const arr=dwArr();arr.splice(arr.indexOf(o),1);LS.set('draw',DRAW);DSEL=null;dwBar();DVER++}
 else if(a==='dword'){st();dwToOrder(o)}},true);
// позиция с графика → в форму заявки (вход лимитом, стоп и тейк)
function dwToOrder(o){try{const [a,b,c]=o.pts;F.side=o.k==='long'?1:-1;F.et='lim';F.px=String(+a.p.toPrecision(8));F.slOn=true;F.sl=String(+c.p.toPrecision(8));F.tps=[{px:String(+b.p.toPrecision(8)),pct:100}];
  if(typeof syncForm==='function')syncForm();saveUI();paintOrders();toast('Заявка заполнена: вход, стоп и тейк — проверьте объём и нажмите «'+(F.side>0?'Купить':'Продать')+'»')}catch(e){toast('Не получилось перенести: '+e.message)}}
// ---------- перерисовка, только когда что-то изменилось ----------
(function loop(){try{if(UI.view==='term'&&CS&&CH&&$('#dwc')){dwBind();const lr=CH.timeScale().getVisibleLogicalRange(),ref=C.length?dY(C[C.length-1].close):0,w=$('#chartWrap');
  const sig=[DVER,lr&&lr.from.toFixed(3),lr&&lr.to.toFixed(3),ref&&ref.toFixed(1),w.clientWidth,w.clientHeight,UI.sym,C.length].join('|');if(sig!==DSIG){DSIG=sig;dwDraw()}}}catch(e){}requestAnimationFrame(loop)})();
// при смене монеты — снять выделение и недорисованное
const _rd56=render;render=function(){const s=UI.sym;const r=_rd56();if(dwBar.sym!==s){dwBar.sym=s;DSEL=null;DTP=[];DUNDO=[]}DSIG='';if(UI.view==='term'){dwBar();if(DT!=='cur')dwSetTool(DT)}return r};
render();
