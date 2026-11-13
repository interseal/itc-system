// ИТК Трейдинг — стратегии по идеям с TradingView (11.11.2026).
// Код чужих авторов не копируем: правила входа и выхода записаны заново по описаниям идей.
// Те же правила повторены на сервере (api/tdemo_lib.php, td_s_tv*), чтобы серия торговала так же, как проверка.
(function(){
const P=(k,n,v)=>({k,n,v});
const cl=c=>c.map(x=>x.close);
const E=(a,n)=>{const k=2/(n+1),o=[];let e=null;for(const v of a){if(v==null){o.push(e);continue}e=e==null?v:v*k+e*(1-k);o.push(e)}return o};
const S=(a,n)=>{const o=[];let s=0,q=0;for(let i=0;i<a.length;i++){const v=a[i]||0;s+=v;if(i>=n)s-=a[i-n]||0;o.push(i>=n-1?s/n:null)}return o};
const W=(a,n)=>{const o=[],d=n*(n+1)/2;for(let i=0;i<a.length;i++){if(i<n-1||a[i-n+1]==null){o.push(null);continue}let s=0;for(let j=0;j<n;j++)s+=a[i-j]*(n-j);o.push(s/d)}return o};
const ATR=(c,n)=>{const o=[];let a=null;for(let i=0;i<c.length;i++){const tr=i?Math.max(c[i].high-c[i].low,Math.abs(c[i].high-c[i-1].close),Math.abs(c[i].low-c[i-1].close)):c[i].high-c[i].low;a=i<n?(a==null?tr:a+tr):a;if(i===n-1)a=a/n;else if(i>=n)a=(a*(n-1)+tr)/n;o.push(i>=n-1?a:null)}return o};
const RSI=(x,n)=>{const o=Array(x.length).fill(null);let g=0,l=0;for(let i=1;i<x.length;i++){const d=x[i]-x[i-1],up=d>0?d:0,dn=d<0?-d:0;if(i<=n){g+=up;l+=dn;if(i===n){g/=n;l/=n;o[i]=l===0?100:100-100/(1+g/l)}}else{g=(g*(n-1)+up)/n;l=(l*(n-1)+dn)/n;o[i]=l===0?100:100-100/(1+g/l)}}return o};
const HH=(c,i,a,b,k)=>{let h=-1e18;for(let j=a;j<=b;j++)h=Math.max(h,c[j][k]);return h};
const LL=(c,i,a,b,k)=>{let h=1e18;for(let j=a;j<=b;j++)h=Math.min(h,c[j][k]);return h};
// направление → сигналы при смене (1 — лонг, −1 — шорт, 0 — вне рынка)
const flips=(D,from)=>{const o=[];let p=0;for(let i=from;i<D.length;i++){const d=D[i]||0;if(d!==p){if(d===0)o.push({i,t:'exit'});else o.push({i,t:d>0?'long':'short'});p=d}}return o};
const TV=' Идея из открытых стратегий TradingView, правила записаны заново.';
ITS.list.push(
{id:'tvut',name:'Следящий стоп UT',about:'Линия стопа идёт за ценой на расстоянии нескольких средних размахов свечи. Цена пересекла линию — вход в эту сторону.',
 info:{how:'Самая популярная стратегия-сигнал на TradingView. Подтягивающийся стоп на расстоянии «ключ × ATR» от цены; когда цена его пересекает, тренд сменился.'+TV,buy:'Цена закрылась выше линии стопа (была ниже).',sell:'Цена закрылась ниже линии стопа.',exit:'Обратный сигнал или стоп 2 ATR.',best:'Трендовые монеты, свечи 15 минут — 4 часа.'},
 params:[P('a','Ключ (множитель ATR)',1),P('n','ATR, свечей',10)],
 run(c,p){const a=+p.a||1,n=p.n|0||10,A=ATR(c,n),x=cl(c),D=[];let ts=null;
  for(let i=0;i<c.length;i++){if(A[i]==null){D.push(0);continue}const L=a*A[i];if(ts==null){ts=x[i]-L;D.push(0);continue}const pv=x[i-1],pt=ts;
   if(x[i]>pt&&pv>pt)ts=Math.max(pt,x[i]-L);else if(x[i]<pt&&pv<pt)ts=Math.min(pt,x[i]+L);else ts=x[i]>pt?x[i]-L:x[i]+L;
   D.push(x[i]>pt&&pv<=pt?1:x[i]<pt&&pv>=pt?-1:(D[i-1]||0))}return flips(D,n+1)}},
{id:'tvrf',name:'Фильтр диапазона',about:'Сглаженная линия, которая двигается только при заметном движении цены. Цена над растущей линией — лонг, под падающей — шорт.',
 info:{how:'Range Filter: средний размах свечи сглаживается, и линия-фильтр сдвигается, только когда цена ушла дальше этого размаха. Мелкий шум отсекается.'+TV,buy:'Линия растёт, цена выше линии — впервые.',sell:'Линия падает, цена ниже линии — впервые.',exit:'Обратный сигнал или стоп 2 ATR.',best:'Свечи 15 минут — 4 часа.'},
 params:[P('n','Период, свечей',100),P('m','Множитель размаха',3)],
 run(c,p){const n=p.n|0||100,m=+p.m||3,x=cl(c),d=x.map((v,i)=>i?Math.abs(v-x[i-1]):0),r=E(E(d,n),n*2-1).map(v=>v*m),D=[];let f=x[0],up=0,dn=0,st=0;
  for(let i=0;i<c.length;i++){const pf=f;f=x[i]>pf?(x[i]-r[i]<pf?pf:x[i]-r[i]):(x[i]+r[i]>pf?pf:x[i]+r[i]);up=f>pf?up+1:f<pf?0:up;dn=f<pf?dn+1:f>pf?0:dn;
   if(x[i]>f&&up>0)st=1;else if(x[i]<f&&dn>0)st=-1;D.push(i<n?0:st)}return flips(D,n)}},
{id:'tvce',name:'Выход Шандельера',about:'Стоп тянется за максимумом (в лонге) или минимумом (в шорте) на 3 ATR. Пробой стопа — разворот в обратную сторону.',
 info:{how:'Chandelier Exit: линия лонга = максимум за N свечей − 3 ATR, линия шорта = минимум + 3 ATR; линии только подтягиваются. Цена пробила линию — сделка переворачивается.'+TV,buy:'Цена закрылась выше линии шорта.',sell:'Цена закрылась ниже линии лонга.',exit:'Переворот или стоп 3 ATR.',best:'Сильные тренды, свечи 1–4 часа.'},
 params:[P('n','Свечей',22),P('m','Множитель ATR',3)],
 run(c,p){const n=p.n|0||22,m=+p.m||3,A=ATR(c,n),D=[];let ls=null,ss=null,dir=0;
  for(let i=0;i<c.length;i++){if(i<n||A[i]==null){D.push(0);continue}let l=HH(c,i,i-n+1,i,'high')-m*A[i],s=LL(c,i,i-n+1,i,'low')+m*A[i];
   if(ls!=null&&c[i-1].close>ls)l=Math.max(l,ls);if(ss!=null&&c[i-1].close<ss)s=Math.min(s,ss);
   if(ss!=null&&c[i].close>ss)dir=1;else if(ls!=null&&c[i].close<ls)dir=-1;ls=l;ss=s;D.push(dir)}return flips(D,n+1)}},
{id:'tvssl',name:'Канал по максимумам и минимумам',about:'Две средние — по максимумам и по минимумам свечей. Цена закрылась выше верхней — лонг, ниже нижней — шорт.',
 info:{how:'SSL Channel: средняя максимумов и средняя минимумов за N свечей. Их «перекрест» показывает смену тренда.'+TV,buy:'Закрытие выше средней максимумов.',sell:'Закрытие ниже средней минимумов.',exit:'Обратный сигнал или стоп за минимумом/максимумом 10 свечей.',best:'Свечи 15 минут — 4 часа.'},
 params:[P('n','Свечей',10)],
 run(c,p){const n=p.n|0||10,H=S(c.map(x=>x.high),n),L=S(c.map(x=>x.low),n),D=[];let h=0;for(let i=0;i<c.length;i++){if(H[i]==null){D.push(0);continue}if(c[i].close>H[i])h=1;else if(c[i].close<L[i])h=-1;D.push(h)}return flips(D,n)}},
{id:'tvwt',name:'Волновой тренд',about:'Осциллятор волн: вход, когда он разворачивается в зоне перепроданности (лонг) или перекупленности (шорт).',
 info:{how:'WaveTrend: отклонение цены от её средней, сглаженное и нормированное. Две линии; их пересечение в крайней зоне — разворот.'+TV,buy:'Быстрая линия пересекла медленную снизу вверх ниже уровня −53.',sell:'Пересекла сверху вниз выше уровня +53.',exit:'Обратное пересечение линий в любой зоне или стоп за экстремумом 10 свечей.',best:'Боковики и откаты, свечи 15 минут — 4 часа.'},
 params:[P('n1','Канал, свечей',10),P('n2','Сглаживание, свечей',21),P('z','Крайняя зона',53)],
 run(c,p){const n1=p.n1|0||10,n2=p.n2|0||21,z=+p.z||53,ap=c.map(x=>(x.high+x.low+x.close)/3),es=E(ap,n1),d=E(ap.map((v,i)=>Math.abs(v-es[i])),n1),ci=ap.map((v,i)=>d[i]?(v-es[i])/(0.015*d[i]):0),w1=E(ci,n2),w2=S(w1,4),o=[];let pos=0;
  for(let i=n1+n2+4;i<c.length;i++){if(w2[i]==null||w2[i-1]==null)continue;const up=w1[i]>w2[i]&&w1[i-1]<=w2[i-1],dn=w1[i]<w2[i]&&w1[i-1]>=w2[i-1];
   if(up&&w1[i]<-z){o.push({i,t:'long'});pos=1}else if(dn&&w1[i]>z){o.push({i,t:'short'});pos=-1}else if((up&&pos<0)||(dn&&pos>0)){o.push({i,t:'exit'});pos=0}}return o}},
{id:'tvhull',name:'Средняя Халла',about:'Быстрая и гладкая средняя Халла: начала расти — лонг, начала падать — шорт.',
 info:{how:'Hull Suite: средняя Халла почти без запаздывания. Цвет линии меняется, когда она разворачивается (сравнение с её значением 2 свечи назад).'+TV,buy:'Средняя Халла выше, чем 2 свечи назад (впервые).',sell:'Ниже, чем 2 свечи назад (впервые).',exit:'Обратный сигнал или стоп 2 ATR.',best:'Тренды, свечи 1–4 часа.'},
 params:[P('n','Длина средней',55)],
 run(c,p){const n=p.n|0||55,x=cl(c),a=W(x,Math.max(2,Math.floor(n/2))),b=W(x,n),r=a.map((v,i)=>v!=null&&b[i]!=null?2*v-b[i]:null),H=W(r,Math.max(2,Math.floor(Math.sqrt(n)))),D=H.map((v,i)=>i<2||v==null||H[i-2]==null?0:v>H[i-2]?1:v<H[i-2]?-1:0);
  for(let i=1;i<D.length;i++)if(D[i]===0&&H[i]!=null)D[i]=D[i-1];return flips(D,n)}},
{id:'tvqqe',name:'RSI с подвижным коридором',about:'Сглаженный RSI с подвижными границами: RSI пробил верхнюю границу — лонг, нижнюю — шорт.',
 info:{how:'QQE: RSI сглаживается, а вокруг него строится коридор по его же «волатильности». Пробой коридора — смена тренда.'+TV,buy:'Сглаженный RSI пробил верхнюю границу.',sell:'Пробил нижнюю границу.',exit:'Обратный сигнал или стоп 2 ATR.',best:'Свечи 15 минут — 4 часа.'},
 params:[P('n','RSI, свечей',14),P('sf','Сглаживание',5),P('q','Ширина коридора',4.238)],
 run(c,p){const n=p.n|0||14,sf=p.sf|0||5,q=+p.q||4.238,R=RSI(cl(c),n),M=E(R,sf),tr=M.map((v,i)=>i&&v!=null&&M[i-1]!=null?Math.abs(v-M[i-1]):null),dar=E(E(tr,2*n-1),2*n-1).map(v=>v==null?null:v*q),D=[];let lb=null,sb=null,t=0;
  for(let i=0;i<c.length;i++){if(M[i]==null||dar[i]==null||i<n*3){D.push(0);continue}const nl=M[i]-dar[i],ns=M[i]+dar[i],pl=lb,ps=sb;
   lb=pl!=null&&M[i-1]>pl&&M[i]>pl?Math.max(pl,nl):nl;sb=ps!=null&&M[i-1]<ps&&M[i]<ps?Math.min(ps,ns):ns;if(ps!=null&&M[i]>ps)t=1;else if(pl!=null&&M[i]<pl)t=-1;D.push(t)}return flips(D,n*3)}},
{id:'tvst200',name:'Супертренд + средняя 200',about:'Самая популярная трендовая схема: сигнал Супертренда, но лонг — только выше средней 200, шорт — только ниже.',
 info:{how:'Супертренд (ATR 10 × 3) показывает направление; средняя за 200 свечей — большой тренд. Сделки только по большому тренду.'+TV,buy:'Супертренд развернулся вверх, цена выше средней 200.',sell:'Супертренд развернулся вниз, цена ниже средней 200.',exit:'Супертренд развернулся против сделки или стоп 2 ATR.',best:'Свечи 1–4 часа, трендовые монеты.'},
 params:[P('n','ATR, свечей',10),P('m','Множитель',3),P('t','Средняя, свечей',200)],
 run(c,p){const n=p.n|0||10,m=+p.m||3,tl=p.t|0||200,A=ATR(c,n),T=E(cl(c),tl),o=[];let up=null,dn=null,d=1,pos=0;
  for(let i=0;i<c.length;i++){if(A[i]==null)continue;const h=(c[i].high+c[i].low)/2,u=h-m*A[i],l=h+m*A[i],pu=up,pd=dn;up=pu!=null&&c[i-1].close>pu?Math.max(u,pu):u;dn=pd!=null&&c[i-1].close<pd?Math.min(l,pd):l;
   const pd0=d;if(pd!=null&&c[i].close>pd)d=1;else if(pu!=null&&c[i].close<pu)d=-1;if(i<tl)continue;
   if(d!==pd0){if(pos&&pos!==d){o.push({i,t:'exit'});pos=0}if(d>0&&c[i].close>T[i]){o.push({i,t:'long'});pos=1}else if(d<0&&c[i].close<T[i]){o.push({i,t:'short'});pos=-1}}}return o}},
{id:'tvsweep',name:'Снятие ликвидности и возврат',about:'Цена прокалывает прошлый максимум или минимум (снимает стопы) и закрывается обратно. Вход против прокола.',
 info:{how:'Над прошлыми максимумами стоят стопы продавцов, под минимумами — покупателей. Прокол с возвратом означает, что крупные игроки собрали эти стопы и разворачивают цену.'+TV,buy:'Минимум свечи ниже минимума N свечей, а закрытие — обратно выше.',sell:'Максимум свечи выше максимума N свечей, закрытие — обратно ниже.',exit:'Стоп за тенью прокола, тейк в 2 раза дальше стопа.',best:'Свечи 15 минут — 4 часа.'},
 params:[P('n','Уровень, свечей',20)],
 run(c,p){const n=p.n|0||20,o=[];for(let i=n;i<c.length;i++){const h=HH(c,i,i-n,i-1,'high'),l=LL(c,i,i-n,i-1,'low');if(c[i].high>h&&c[i].close<h)o.push({i,t:'short'});else if(c[i].low<l&&c[i].close>l)o.push({i,t:'long'})}return o}},
{id:'tvfvg',name:'Снятие ликвидности + разрыв',about:'После прокола уровня появляется свеча-разрыв (цена не перекрылась с соседними свечами) в обратную сторону — вход по развороту.',
 info:{how:'Fair Value Gap — «разрыв справедливой цены»: между максимумом свечи и минимумом через одну остаётся пустое место. Разрыв после снятия ликвидности подтверждает разворот.'+TV,buy:'В последние 10 свечей был прокол минимума, и появился разрыв вверх (минимум свечи выше максимума свечи две назад).',sell:'Был прокол максимума и появился разрыв вниз.',exit:'Стоп за экстремумом 5 свечей, тейк в 2 раза дальше стопа.',best:'Свечи 5 минут — 1 час.'},
 params:[P('n','Уровень, свечей',20),P('w','Ждать разрыв, свечей',10)],
 run(c,p){const n=p.n|0||20,w=p.w|0||10,o=[];let sh=-1e9,sl=-1e9;for(let i=n;i<c.length;i++){const h=HH(c,i,i-n,i-1,'high'),l=LL(c,i,i-n,i-1,'low');if(c[i].high>h)sh=i;if(c[i].low<l)sl=i;
   if(i-sl<=w&&i-sl>=1&&c[i].low>c[i-2].high){o.push({i,t:'long'});sl=-1e9}else if(i-sh<=w&&i-sh>=1&&c[i].high<c[i-2].low){o.push({i,t:'short'});sh=-1e9}}return o}},
{id:'tvdon',name:'Пробой старого Дончиана + денежный поток',about:'Пробой канала, построенного по свечам чуть раньше (без последних), и только если деньги идут в ту же сторону (индекс денежного потока MFI).',
 info:{how:'Канал Дончиана (максимум и минимум за N свечей) сдвинут на несколько свечей назад — так пробой надёжнее. MFI — RSI с учётом объёма: выше 50 деньги входят, ниже — выходят.'+TV,buy:'Закрытие выше верха сдвинутого канала, MFI выше 50.',sell:'Закрытие ниже низа канала, MFI ниже 50.',exit:'Цена вернулась за середину канала или стоп 2 ATR.',best:'Тренды, свечи 1–4 часа.'},
 params:[P('n','Канал, свечей',20),P('d','Сдвиг назад, свечей',5),P('m','MFI, свечей',14)],
 run(c,p){const n=p.n|0||20,d=p.d|0||5,m=p.m|0||14,tp=c.map(x=>(x.high+x.low+x.close)/3),o=[];let pos=0;
  const mfi=i=>{let pf=0,nf=0;for(let j=i-m+1;j<=i;j++){const f=tp[j]*(c[j].volume||0);if(tp[j]>tp[j-1])pf+=f;else if(tp[j]<tp[j-1])nf+=f}return nf===0?100:100-100/(1+pf/nf)};
  for(let i=n+d+m+1;i<c.length;i++){const u=HH(c,i,i-n-d,i-d-1,'high'),l=LL(c,i,i-n-d,i-d-1,'low'),mid=(u+l)/2,x=c[i].close;
   if(pos>0&&x<mid){o.push({i,t:'exit'});pos=0}else if(pos<0&&x>mid){o.push({i,t:'exit'});pos=0}
   if(!pos){const f=mfi(i);if(x>u&&f>50){o.push({i,t:'long'});pos=1}else if(x<l&&f<50){o.push({i,t:'short'});pos=-1}}}return o}},
{id:'tvrsi70',name:'Пробой RSI 70',about:'Покупка силы: RSI пересёк 70 снизу вверх — лонг (ставка на продолжение, а не на откат). Зеркально — шорт при пробое 30 вниз.',
 info:{how:'Обычно RSI выше 70 считают перекупленностью, но в сильном тренде это начало импульса. Стратегия идёт за силой.'+TV,buy:'RSI(14) пересёк 70 снизу вверх.',sell:'RSI пересёк 30 сверху вниз.',exit:'RSI вернулся к 50 или стоп 3 ATR.',best:'Дневные и 4-часовые свечи.'},
 params:[P('n','RSI, свечей',14),P('hi','Верхний уровень',70),P('lo','Нижний уровень',30)],
 run(c,p){const n=p.n|0||14,hi=+p.hi||70,lo=+p.lo||30,R=RSI(cl(c),n),o=[];let pos=0;for(let i=n+1;i<c.length;i++){if(R[i]==null||R[i-1]==null)continue;
   if(pos>0&&R[i]<50){o.push({i,t:'exit'});pos=0}else if(pos<0&&R[i]>50){o.push({i,t:'exit'});pos=0}
   if(!pos){if(R[i]>hi&&R[i-1]<=hi){o.push({i,t:'long'});pos=1}else if(R[i]<lo&&R[i-1]>=lo){o.push({i,t:'short'});pos=-1}}}return o}},
{id:'tvvor',name:'Разворот по объёму (эксперимент)',about:'Цена делает новый минимум (максимум) за N свечей на всплеске объёма, а следующая свеча разворачивается — вход в разворот.',
 info:{how:'Осциллятор объёма — насколько быстрая средняя объёма выше медленной. Всплеск объёма на экстремуме часто означает «выдох» движения. Экспериментальная идея.'+TV,buy:'Новый минимум 20 свечей при объёме на 20% выше обычного, следующая свеча закрылась выше предыдущей.',sell:'Новый максимум при всплеске объёма, следующая свеча закрылась ниже.',exit:'Стоп за экстремумом 5 свечей, тейк в 2 раза дальше стопа.',best:'Свечи 15 минут — 4 часа.'},
 params:[P('n','Экстремум, свечей',20),P('v','Всплеск объёма, %',20)],
 run(c,p){const n=p.n|0||20,v=+p.v||20,vo=c.map(x=>x.volume||0),f=E(vo,5),s=E(vo,10),O=f.map((x,i)=>s[i]?(x-s[i])/s[i]*100:0),o=[];
  for(let i=n+1;i<c.length;i++){const j=i-1,h=HH(c,j,j-n,j-1,'high'),l=LL(c,j,j-n,j-1,'low');if(O[j]<v)continue;
   if(c[j].low<l&&c[i].close>c[j].close)o.push({i,t:'long'});else if(c[j].high>h&&c[i].close<c[j].close)o.push({i,t:'short'})}return o}},
{id:'tvabs',name:'Ловушка поглощения (эксперимент)',about:'У вершины покупают очень сильно, но цена почти не идёт — продавец всё поглощает. Вход против толпы.',
 info:{how:'Перекос покупок/продаж свечи оценивается по объёму и тому, где закрылась свеча. Большой перекос при маленьком ходе цены у экстремума — признак крупного встречного игрока. Экспериментальная идея.'+TV,buy:'У минимума 20 свечей продажи вдвое сильнее обычного, а свеча короче 0,7 ATR.',sell:'У максимума 20 свечей покупки вдвое сильнее обычного, а свеча короче 0,7 ATR.',exit:'Стоп за свечой, тейк в 2 раза дальше стопа.',best:'Свечи 5 минут — 1 час.'},
 params:[P('n','Экстремум, свечей',20),P('k','Перекос, во сколько раз',2)],
 run(c,p){const n=p.n|0||20,k=+p.k||2,A=ATR(c,14),dl=c.map(x=>{const r=x.high-x.low;return r>0?(x.volume||0)*(x.close-x.open)/r:0}),o=[];
  for(let i=n;i<c.length;i++){if(A[i]==null)continue;let s=0;for(let j=i-n;j<i;j++)s+=Math.abs(dl[j]);const av=s/n,rg=c[i].high-c[i].low;if(!av||rg>=0.7*A[i])continue;
   const h=HH(c,i,i-n,i-1,'high'),l=LL(c,i,i-n,i-1,'low');if(dl[i]>k*av&&c[i].high>=h)o.push({i,t:'short'});else if(dl[i]<-k*av&&c[i].low<=l)o.push({i,t:'long'})}return o}});
})();
