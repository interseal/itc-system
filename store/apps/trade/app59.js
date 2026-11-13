// Alex Trade — №505 · ещё больше инструментов рисования (12.11.2026). Свой код.
// Всего около 70 инструментов в 9 группах: линии, каналы и вилы, Фибоначчи, Ганн, фигуры-паттерны,
// волны Эллиотта, циклы, прогноз и измерения (VWAP от точки, профиль объёма участка), фигуры, текст и метки.
"use strict";
Object.assign(ICP,{
 dwchan:"M3 16.5l12-12 1.4 1.4-12 12zM7.6 19.6l12-12L21 9 9 21z",
 dwpat:"M23 8c0 1.1-.9 2-2 2-.18 0-.35-.02-.51-.07l-3.56 3.55c.05.16.07.34.07.52 0 1.1-.9 2-2 2s-2-.9-2-2c0-.18.02-.36.07-.52l-2.55-2.55c-.16.05-.34.07-.52.07s-.36-.02-.52-.07l-4.55 4.56c.05.16.07.33.07.51 0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2c.18 0 .35.02.51.07l4.56-4.55C8.02 9.36 8 9.18 8 9c0-1.1.9-2 2-2s2 .9 2 2c0 .18-.02.36-.07.52l2.55 2.55c.16-.05.34-.07.52-.07s.36.02.52.07l3.55-3.56C19.02 8.35 19 8.18 19 8c0-1.1.9-2 2-2s2 .9 2 2z",
 dwwave:"M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z",
 dwcyc:"M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z",
 dwgann:"M3 3h18v18H3V3zm2 2v14h14V5H5zm1 13L18 6l.7.7L6.7 18.7z",
 dwfc:"M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"});
// ---------- новые группы ----------
DWG.length=0;[
 ['cur','cursor','Курсор',null],
 ['lines','trend','Линии',[['tr','Линия тренда',2],['ray','Луч',2],['info','Инфо-линия',2],['ext','Продлённая линия',2],['angle','Угол тренда',2],['h','Горизонтальная линия',1],['hray','Горизонтальный луч',1],['v','Вертикальная линия',1],['cross','Перекрестие',1],['arrow','Стрелка',2]]],
 ['chans','dwchan','Каналы и вилы',[['chan','Параллельный канал',3],['regch','Канал регрессии',2],['flatch','Канал с плоским краем',3],['pitch','Вилы',3],['schiff','Вилы Шиффа',3],['mschiff','Изменённые вилы Шиффа',3],['ipitch','Внутренние вилы',3]]],
 ['fibs','fib','Фибоначчи',[['fib','Уровни коррекции',2],['fibx','Расширение по тренду',3],['fibch','Канал Фибоначчи',3],['fibt','Временные зоны',2],['fibtx','Временное расширение по тренду',3],['fibfan','Веер',2],['fibcirc','Круги',2],['fibarc','Дуги',2]]],
 ['gann','dwgann','Ганн',[['gannbox','Коробка Ганна',2],['gannsq','Квадрат Ганна',2],['gannfan','Веер Ганна',2]]],
 ['pats','dwpat','Фигуры-паттерны',[['xabcd','Паттерн XABCD',5],['cypher','Паттерн «Сайфер»',5],['abcd','Паттерн ABCD',4],['hs','Голова и плечи',7],['tripat','Треугольник',5],['three','Три движения',7]]],
 ['waves','dwwave','Волны Эллиотта',[['ew5','Импульс 1-2-3-4-5',6],['ewabc','Коррекция A-B-C',4],['ewtri','Треугольник A-B-C-D-E',6],['ewwxy','Двойная тройка W-X-Y',4],['ewwxz','Тройная тройка W-X-Y-X-Z',6]]],
 ['cycles','dwcyc','Циклы',[['cyclin','Циклические линии',2],['tcyc','Временные циклы',2],['sine','Синусоида',2]]],
 ['meas','dwfc','Прогноз и измерения',[['long','Позиция «Лонг»',1],['short','Позиция «Шорт»',1],['fcast','Прогноз',2],['prange','Диапазон цены',2],['drange','Диапазон дат',2],['rul','Цена и время вместе',2],['avwap','VWAP от точки',1],['vprof','Профиль объёма участка',2]]],
 ['shapes','dwrect','Фигуры и кисти',[['brush','Кисть',0],['marker','Маркер-выделитель',0],['rect','Прямоугольник',2],['rrect','Повёрнутый прямоугольник',3],['circ','Круг',2],['ell','Эллипс',2],['tri','Треугольник',3],['curve','Кривая',3],['arc','Дуга',3]]],
 ['texts','text','Текст и метки',[['txt','Подпись',1],['note','Выноска',2],['cmt','Комментарий',1],['plab','Метка цены',1],['pnote','Заметка с ценой',2],['sign','Табличка',1],['flag','Флажок',1],['mup','Стрелка вверх',1],['mdn','Стрелка вниз',1],['mlt','Стрелка влево',1],['mrt','Стрелка вправо',1]]]
].forEach(g=>DWG.push(g));
Object.keys(DWN).forEach(k=>delete DWN[k]);DWG.forEach(g=>g[3]&&g[3].forEach(t=>DWN[t[0]]={n:t[1],k:t[2],g:g[0]}));
(function(){const st=document.createElement('style');st.textContent='.dwfly{max-height:min(70vh,520px);overflow-y:auto}';document.head.appendChild(st)})();
// ---------- помощники ----------
const RAT=(x,y)=>Math.abs(y)>1e-12?Math.abs(x/y):0;
const fibL=[0,.236,.382,.5,.618,.786,1,1.618,2.618];
function curveP(x0,y0,cx,cy,x1,y1,n){const o=[];for(let i=0;i<=n;i++){const t=i/n,u=1-t;o.push(u*u*x0+2*u*t*cx+t*t*x1,u*u*y0+2*u*t*cy+t*t*y1)}return o}
function arcP(cx,cy,r,a0,a1,n){const o=[];for(let i=0;i<=n;i++){const a=a0+(a1-a0)*i/n;o.push(cx+r*Math.cos(a),cy+r*Math.sin(a))}return o}
// подписи точек паттернов — кружками
const lab=(out,x,y,s,col,up)=>out.push({k:'t',x,y:y+(up?-10:20),s,al:'center',bg:1,col});
const upOf=(P,i)=>{const a=P[i-1]||P[i+1],b=P[i+1]||P[i-1];return !(P[i].y>Math.min(a.y,b.y))};
// ---------- рисунки ----------
const _dp59=dwPrims;dwPrims=function(o){if(!o||!o.pts)return [];const k=o.k;
 if(!['info','angle','cross','regch','flatch','schiff','mschiff','ipitch','fibch','fibtx','fibcirc','fibarc','gannbox','gannsq','gannfan','xabcd','cypher','abcd','hs','tripat','three','ew5','ewabc','ewtri','ewwxy','ewwxz','cyclin','tcyc','sine','fcast','avwap','vprof','marker','rrect','circ','curve','arc','cmt','plab','pnote','sign','mlt','mrt'].includes(k))return _dp59(o);
 const P=o.pts.map(q=>({x:dX(q.t),y:dY(q.p),p:q.p,t:q.t}));if(P.some(q=>q.x==null||q.y==null))return [];
 const [a,b,c]=P,W=plotW(),H=LH(),out=[],S=(x1,y1,x2,y2,st,col)=>out.push({k:'s',v:[x1,y1,x2,y2],st,col}),T=(x,y,s,al,bg,col)=>out.push({k:'t',x,y,s,al,bg,col}),L=(v,extra)=>out.push(Object.assign({k:'l',v},extra||{}));
 const pct=(v,base)=>base?(v-base)/base*100:0,bars=(t1,t2)=>Math.round(Math.abs(t2l(t2)-t2l(t1)));
 const poly=()=>{for(let i=1;i<P.length;i++)S(P[i-1].x,P[i-1].y,P[i].x,P[i].y)};
 switch(k){
  case 'info':{S(a.x,a.y,b.x,b.y);const ch=pct(b.p,a.p),n=bars(a.t,b.t),ang=Math.atan2(a.y-b.y,b.x-a.x)*180/Math.PI;T(b.x+6,b.y+(b.y<a.y?-8:16),`${ch>=0?'+':''}${fmt(ch,2)}% · ${fp(b.p-a.p)} · ${n} ${plW(n,'свеча','свечи','свечей')} · ${fmt(ang,1)}°`,'left',1);break}
  case 'angle':{S(a.x,a.y,b.x,b.y);const r=Math.min(70,Math.hypot(b.x-a.x,b.y-a.y)),dir=b.x>=a.x?1:-1;S(a.x,a.y,a.x+dir*r,a.y,2);const an=Math.atan2(b.y-a.y,b.x-a.x);L(arcP(a.x,a.y,r*.6,dir>0?0:Math.PI,an,16));T(a.x+dir*(r+6),a.y+4,`${fmt(Math.abs(Math.atan2(a.y-b.y,Math.abs(b.x-a.x))*180/Math.PI),1)}°`,dir>0?'left':'right',1);break}
  case 'cross':S(0,a.y,W,a.y);S(a.x,0,a.x,H);out.push({k:'ax',y:a.y,s:fp(a.p)});break;
  case 'regch':{let i1=Math.round(t2l(a.t)),i2=Math.round(t2l(b.t));if(i1>i2)[i1,i2]=[i2,i1];i1=Math.max(0,i1);i2=Math.min(C.length-1,i2);if(i2-i1<2){S(a.x,a.y,b.x,b.y,2);break}
   let n=0,sx=0,sy=0,sxx=0,sxy=0;for(let i=i1;i<=i2;i++){const y=C[i].close;n++;sx+=i;sy+=y;sxx+=i*i;sxy+=i*y}const m=(n*sxy-sx*sy)/((n*sxx-sx*sx)||1),q0=(sy-m*sx)/n;let ss=0;for(let i=i1;i<=i2;i++){const d=C[i].close-(q0+m*i);ss+=d*d}const sd=Math.sqrt(ss/n);
   const X=i=>CH.timeScale().logicalToCoordinate(i),Yp=v=>dY(v);[[0,0],[2,1],[-2,1]].forEach(([kk,st])=>{const y1=Yp(q0+m*i1+kk*sd),y2=Yp(q0+m*i2+kk*sd);if(y1!=null&&y2!=null)S(X(i1),y1,X(i2),y2,st)});
   const yt=Yp(q0+m*i1+2*sd),yb=Yp(q0+m*i1-2*sd),yt2=Yp(q0+m*i2+2*sd),yb2=Yp(q0+m*i2-2*sd);if([yt,yb,yt2,yb2].every(v=>v!=null))out.push({k:'g',v:[X(i1),yt,X(i2),yt2,X(i2),yb2,X(i1),yb],fill:1});break}
  case 'flatch':{S(a.x,a.y,b.x,b.y);S(a.x,c.y,b.x,c.y);out.push({k:'g',v:[a.x,a.y,b.x,b.y,b.x,c.y,a.x,c.y],fill:1});break}
  case 'schiff':case 'mschiff':case 'ipitch':{const o0=k==='schiff'?{x:a.x,y:(a.y+b.y)/2}:k==='mschiff'?{x:(a.x+b.x)/2,y:(a.y+b.y)/2}:{x:a.x,y:a.y};const mx=(b.x+c.x)/2,my=(b.y+c.y)/2,dx=mx-o0.x,dy=my-o0.y;
   S(...extend(o0.x,o0.y,mx,my));S(...extend(b.x,b.y,b.x+dx,b.y+dy));S(...extend(c.x,c.y,c.x+dx,c.y+dy));S(b.x,b.y,c.x,c.y,2);S(a.x,a.y,o0.x,o0.y,2);
   if(k==='ipitch'){[[.5,b],[.5,c]].forEach(([f,z])=>{const ix=mx+(z.x-mx)*f,iy=my+(z.y-my)*f;S(...extend(ix,iy,ix+dx,iy+dy),1)})}break}
  case 'fibch':{const off=c.y-(a.y+(b.y-a.y)*((c.x-a.x)/((b.x-a.x)||1)));fibL.forEach(f=>{S(...extend(a.x,a.y+off*f,b.x,b.y+off*f),f===0||f===1?0:2);T(a.x-4,a.y+off*f+4,(f*100).toFixed(1)+'%','right')});break}
  case 'fibtx':{const u=t2l(b.t)-t2l(a.t),lc=t2l(c.t);poly();[0,.382,.5,.618,1,1.382,1.618,2,2.618].forEach(f=>{const x=CH.timeScale().logicalToCoordinate(lc+u*f);if(x==null||x<0||x>W)return;S(x,0,x,H,f===1?0:2);T(x+3,14,String(f),'left')});break}
  case 'fibcirc':{const r=Math.hypot(b.x-a.x,b.y-a.y);S(a.x,a.y,b.x,b.y,2);[.236,.382,.5,.618,.786,1,1.618].forEach(f=>out.push({k:'e',v:[a.x,a.y,r*f,r*f]}));break}
  case 'fibarc':{const r=Math.hypot(b.x-a.x,b.y-a.y),up=a.y<b.y;S(a.x,a.y,b.x,b.y,2);[.382,.5,.618,1].forEach(f=>{L(arcP(b.x,b.y,r*f,up?Math.PI:0,up?2*Math.PI:Math.PI,40));T(b.x,b.y+(up?-r*f-4:r*f+14),(f*100).toFixed(1)+'%','center')});break}
  case 'gannbox':case 'gannsq':{let x1=Math.min(a.x,b.x),x2=Math.max(a.x,b.x),y1=Math.min(a.y,b.y),y2=Math.max(a.y,b.y);if(k==='gannsq'){const s=Math.max(x2-x1,y2-y1);x2=x1+s;y2=y1+s}
   out.push({k:'r',v:[x1,y1,x2-x1,y2-y1],fill:1});[.25,.382,.5,.618,.75].forEach(f=>{S(x1+(x2-x1)*f,y1,x1+(x2-x1)*f,y2,2);S(x1,y1+(y2-y1)*f,x2,y1+(y2-y1)*f,2)});S(x1,y1,x2,y2);S(x1,y2,x2,y1);
   if(k==='gannsq'){out.push({k:'e',v:[(x1+x2)/2,(y1+y2)/2,(x2-x1)/2,(y2-y1)/2]})}break}
  case 'gannfan':{const dx=b.x-a.x,dy=b.y-a.y;[[1,8],[1,4],[1,3],[1,2],[1,1],[2,1],[3,1],[4,1],[8,1]].forEach(([u,v])=>{const tx=u>=v?a.x+dx:a.x+dx*u/v,ty=u>=v?a.y+dy*v/u:a.y+dy;S(...extend(a.x,a.y,tx,ty),u===v?0:2);T(tx+3,ty+(dy>0?12:-4),u+'×'+v,'left')});break}
  case 'xabcd':case 'cypher':case 'abcd':case 'three':{const names=k==='abcd'?['A','B','C','D']:k==='three'?['0','1','A','2','B','3','']:['X','A','B','C','D'];poly();
   if(k!=='abcd'&&k!=='three'){out.push({k:'g',v:[P[0].x,P[0].y,P[1].x,P[1].y,P[2].x,P[2].y],fill:1});out.push({k:'g',v:[P[2].x,P[2].y,P[3].x,P[3].y,P[4].x,P[4].y],fill:1});S(P[0].x,P[0].y,P[2].x,P[2].y,2);S(P[2].x,P[2].y,P[4].x,P[4].y,2);S(P[1].x,P[1].y,P[3].x,P[3].y,2);S(P[0].x,P[0].y,P[4].x,P[4].y,2);
    const d=(i,j)=>P[j].p-P[i].p,mid=(i,j)=>[(P[i].x+P[j].x)/2,(P[i].y+P[j].y)/2];[[0,2,RAT(d(1,2),d(0,1))],[1,3,RAT(d(2,3),d(1,2))],[2,4,RAT(d(3,4),d(2,3))],[0,4,RAT(d(1,4),d(0,1))]].forEach(([i,j,r])=>{const m=mid(i,j);T(m[0],m[1],fmt(r,3),'center',1)})}
   else if(k==='abcd'){const r1=RAT(P[2].p-P[1].p,P[1].p-P[0].p),r2=RAT(P[3].p-P[2].p,P[2].p-P[1].p);S(P[0].x,P[0].y,P[2].x,P[2].y,2);S(P[1].x,P[1].y,P[3].x,P[3].y,2);T((P[0].x+P[2].x)/2,(P[0].y+P[2].y)/2,fmt(r1,3),'center',1);T((P[1].x+P[3].x)/2,(P[1].y+P[3].y)/2,fmt(r2,3),'center',1)}
   P.forEach((q,i)=>names[i]&&lab(out,q.x,q.y,names[i],o.c,upOf(P,i)));break}
  case 'hs':{poly();const n1=P[2],n2=P[4];S(...extend(n1.x,n1.y,n2.x,n2.y,true),1);const nm=['','Левое плечо','','Голова','','Правое плечо',''];P.forEach((q,i)=>nm[i]&&lab(out,q.x,q.y,nm[i],o.c,upOf(P,i)));break}
  case 'tripat':{poly();S(...extend(P[0].x,P[0].y,P[2].x,P[2].y),1);S(...extend(P[1].x,P[1].y,P[3].x,P[3].y),1);out.push({k:'g',v:P.flatMap(q=>[q.x,q.y]),fill:1});['A','B','C','D','E'].forEach((s,i)=>lab(out,P[i].x,P[i].y,s,o.c,upOf(P,i)));break}
  case 'ew5':case 'ewabc':case 'ewtri':case 'ewwxy':case 'ewwxz':{poly();const nm={ew5:['','1','2','3','4','5'],ewabc:['','A','B','C'],ewtri:['','A','B','C','D','E'],ewwxy:['','W','X','Y'],ewwxz:['','W','X','Y','X','Z']}[k];
   P.forEach((q,i)=>{if(!nm[i])return;const up=upOf(P,i);out.push({k:'e',v:[q.x,q.y+(up?-14:14),9,9],fill:3});T(q.x,q.y+(up?-10:18),nm[i],'center',0,o.c)});break}
  case 'cyclin':{const la=t2l(a.t),u=t2l(b.t)-la;if(Math.abs(u)<.5)break;const vr=CH.timeScale().getVisibleLogicalRange()||{from:la,to:la};const n0=u>0?Math.max(0,Math.floor((vr.from-la)/u)):0;for(let n=n0;n<n0+300;n++){const x=CH.timeScale().logicalToCoordinate(la+u*n);if(x==null||x>W||(u<0&&x<0))break;if(x>=0)S(x,0,x,H,n<2?0:2)}break}
  case 'tcyc':{const la=t2l(a.t),u=t2l(b.t)-la;if(Math.abs(u)<.5)break;const vr=CH.timeScale().getVisibleLogicalRange()||{from:la,to:la};const n0=u>0?Math.max(0,Math.floor((vr.from-la)/u)-1):0;for(let n=n0;n<n0+150;n++){const x0=CH.timeScale().logicalToCoordinate(la+u*n),x1=CH.timeScale().logicalToCoordinate(la+u*(n+1));if(x0==null||x1==null||x0>W)break;if(Math.max(x0,x1)<0)continue;const r=Math.abs(x1-x0)/2;L(arcP((x0+x1)/2,a.y,r,Math.PI,2*Math.PI,24))}break}
  case 'sine':{const per=(b.x-a.x)*2||1,amp=(b.y-a.y),v=[];for(let x=Math.max(0,Math.min(a.x,b.x)-per*2);x<=W;x+=3)v.push(x,a.y+amp*(1-Math.cos(Math.PI*(x-a.x)/(per/2)))/2*1);L(v);break}
  case 'fcast':{S(a.x,a.y,b.x,b.y,1);out.push({k:'e',v:[b.x,b.y,6,6],fill:2});out.push({k:'e',v:[a.x,a.y,4,4],fill:2});const ch=pct(b.p,a.p),n=bars(a.t,b.t);T(b.x,b.y+(b.y<a.y?-12:20),`Прогноз ${ch>=0?'+':''}${fmt(ch,2)}% · ${fp(b.p)} · через ${n} ${plW(n,'свечу','свечи','свечей')}`,'center',1,ch>=0?'#089981':'#f23645');break}
  case 'avwap':{let i0=Math.max(0,Math.round(t2l(a.t)));if(i0>=C.length)break;let pv=0,vv=0;const v=[];for(let i=i0;i<C.length;i++){const c2=C[i],tp=(c2.high+c2.low+c2.close)/3;pv+=tp*c2.volume;vv+=c2.volume;const x=CH.timeScale().logicalToCoordinate(i),y=dY(vv?pv/vv:tp);if(x!=null&&y!=null)v.push(x,y)}
   if(v.length>=4){L(v);T(v[v.length-2]+4,v[v.length-1]-6,'VWAP '+fp(pv/vv),'left',1);out.push({k:'e',v:[v[0],v[1],4,4],fill:2})}break}
  case 'vprof':{let i1=Math.round(t2l(a.t)),i2=Math.round(t2l(b.t));if(i1>i2)[i1,i2]=[i2,i1];i1=Math.max(0,i1);i2=Math.min(C.length-1,i2);if(i2<=i1)break;let lo=1e18,hi=-1e18;for(let i=i1;i<=i2;i++){lo=Math.min(lo,C[i].low);hi=Math.max(hi,C[i].high)}
   const N=24,bin=Array(N).fill(0),up=Array(N).fill(0),st=(hi-lo)/N||1;for(let i=i1;i<=i2;i++){const c2=C[i],tp=(c2.high+c2.low+c2.close)/3,j=Math.min(N-1,Math.floor((tp-lo)/st));bin[j]+=c2.volume;if(c2.close>=c2.open)up[j]+=c2.volume}
   const x1=CH.timeScale().logicalToCoordinate(i1),x2=CH.timeScale().logicalToCoordinate(i2);if(x1==null||x2==null)break;const wmax=(x2-x1)*.7,mx=Math.max(...bin)||1,poc=bin.indexOf(mx);
   out.push({k:'r',v:[x1,dY(hi),x2-x1,dY(lo)-dY(hi)],fill:0});for(let j=0;j<N;j++){const ya=dY(lo+st*(j+1)),yb=dY(lo+st*j);if(ya==null||yb==null)continue;const h=Math.max(1,yb-ya-1),len=bin[j]/mx*wmax,lu=up[j]/mx*wmax;
    out.push({k:'r',v:[x1,ya,lu,h],fill:1,col:j===poc?'#f0b90b':'#089981',fa:.5,nos:1});out.push({k:'r',v:[x1+lu,ya,len-lu,h],fill:1,col:j===poc?'#f0b90b':'#f23645',fa:.45,nos:1})}
   const yp=dY(lo+st*(poc+.5));if(yp!=null){S(x1,yp,x2,yp,1,'#f0b90b');T(x2+4,yp+4,'Больше всего объёма '+fp(lo+st*(poc+.5)),'left',1,'#f0b90b')}break}
  case 'marker':L(P.flatMap(q=>[q.x,q.y]),{lw:14,ga:.35});break;
  case 'rrect':{const dx=b.x-a.x,dy=b.y-a.y,len=Math.hypot(dx,dy)||1,nx=-dy/len,ny=dx/len,w=(c.x-a.x)*nx+(c.y-a.y)*ny;out.push({k:'g',v:[a.x,a.y,b.x,b.y,b.x+nx*w,b.y+ny*w,a.x+nx*w,a.y+ny*w],fill:1});break}
  case 'circ':{const r=Math.hypot(b.x-a.x,b.y-a.y);out.push({k:'e',v:[a.x,a.y,r,r],fill:1});break}
  case 'curve':L(curveP(a.x,a.y,c.x,c.y,b.x,b.y,30));break;
  case 'arc':{const v=curveP(a.x,a.y,2*c.x-(a.x+b.x)/2,2*c.y-(a.y+b.y)/2,b.x,b.y,30);L(v.concat([a.x,a.y]),{cl:1});L(v);break}
  case 'cmt':{const t=o.txt||'Комментарий';T(a.x+8,a.y-14,t,'left',1,o.c);S(a.x,a.y,a.x+8,a.y-12);break}
  case 'plab':{T(a.x+8,a.y+4,fp(a.p),'left',1,o.c);out.push({k:'g',v:[a.x,a.y,a.x+8,a.y-6,a.x+8,a.y+6],fill:2});break}
  case 'pnote':{out.push({k:'e',v:[a.x,a.y,4,4],fill:2});S(a.x,a.y,b.x,b.y,1);T(b.x,b.y,fp(a.p)+(o.txt?' · '+o.txt:''),'center',1,o.c);break}
  case 'sign':{const t=o.txt||'Табличка';T(a.x,a.y-30,t,'center',1,o.c);S(a.x,a.y,a.x,a.y-26);break}
  case 'mlt':out.push({k:'g',v:[a.x,a.y,a.x+14,a.y-8,a.x+14,a.y+8],fill:2});break;
  case 'mrt':out.push({k:'g',v:[a.x,a.y,a.x-14,a.y-8,a.x-14,a.y+8],fill:2});break}
 return out};
// заливка кружков у волн: fill 3 — тёмный кружок с цветной обводкой
const _pt59=dwPaint;dwPaint=function(g,o,sel){if(!['ew5','ewabc','ewtri','ewwxy','ewwxz','vprof'].includes(o.k))return _pt59(g,o,sel);
 const ps=dwPrims(o),circles=ps.filter(p=>p.k==='e'&&p.fill===3),bars=ps.filter(p=>p.nos);const rest=ps.filter(p=>!(p.k==='e'&&p.fill===3)&&!p.nos);
 const save=dwPrims;dwPrims=()=>rest;try{_pt59(g,o,sel)}finally{dwPrims=save}
 bars.forEach(p=>{g.globalAlpha=p.fa||.4;g.fillStyle=p.col;g.fillRect(...p.v);g.globalAlpha=1});
 circles.forEach(p=>{g.beginPath();g.arc(p.v[0],p.v[1],p.v[2],0,7);g.fillStyle='#131722';g.fill();g.lineWidth=1.5;g.strokeStyle=o.c;g.stroke()});
 ps.filter(p=>p.k==='t'&&!p.bg).forEach(p=>{g.font='bold 12px Roboto,Arial,sans-serif';g.textAlign='center';g.fillStyle=o.c;g.fillText(p.s,p.x,p.y)})};
// начальная толщина для «тонких» инструментов
const _mk59=dwMake;dwMake=function(k,pts){const o=_mk59(k,pts);if(['fibch','fibtx','fibcirc','fibarc','gannbox','gannsq','gannfan','cyclin','tcyc','regch','vprof'].includes(k))o.w=1;if(k==='marker'){o.c=o.c==='#2962ff'?'#ffd54f':o.c}return o};
render();
