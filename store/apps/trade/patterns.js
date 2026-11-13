// ИТК Трейдинг — свечные паттерны и фигуры технического анализа
// PAT.detect(c) → {cands:[{i,id,name,dir,str,about}], figs:[{id,name,dir,pts:[[i,p]],lines:[[[i,p],[i,p]]],brk,target,about}]}
(function(){
const ema=(a,n)=>{const k=2/(n+1),o=[];let e=null;for(let i=0;i<a.length;i++){e=e===null?a[i]:a[i]*k+e*(1-k);o.push(e)}return o};
const CAND={
 hammer:['Молот','Длинная нижняя тень после падения: покупатели выкупили снижение. Сигнал на разворот вверх.'],
 star:['Падающая звезда','Длинная верхняя тень после роста: продавцы сбили цену. Сигнал на разворот вниз.'],
 engB:['Бычье поглощение','Зелёная свеча полностью «съела» прошлую красную. Сильный сигнал на рост.'],
 engS:['Медвежье поглощение','Красная свеча полностью «съела» прошлую зелёную. Сильный сигнал на падение.'],
 mstar:['Утренняя звезда','Три свечи: падение, пауза, уверенный рост. Разворот вверх.'],
 estar:['Вечерняя звезда','Три свечи: рост, пауза, уверенное падение. Разворот вниз.'],
 soldiers:['Три белых солдата','Три растущие свечи подряд с закрытием у максимумов. Сила покупателей.'],
 crows:['Три чёрные вороны','Три падающие свечи подряд с закрытием у минимумов. Сила продавцов.'],
 pierce:['Просвет в облаках','После красной свечи зелёная открылась ниже и закрылась выше середины. Разворот вверх.'],
 cloud:['Завеса из тёмных облаков','После зелёной свечи красная открылась выше и закрылась ниже середины. Разворот вниз.'],
 haramiB:['Бычий харами','Маленькая зелёная свеча внутри большой красной: падение выдыхается.'],
 haramiS:['Медвежий харами','Маленькая красная свеча внутри большой зелёной: рост выдыхается.'],
 doji:['Доджи','Открытие почти равно закрытию: нерешительность. Внимание к следующей свече.']};
function candles(c){const out=[],cl=c.map(x=>x.close),e20=ema(cl,20);
 const B=x=>Math.abs(x.close-x.open),R=x=>Math.max(1e-12,x.high-x.low),U=x=>x.high-Math.max(x.open,x.close),L=x=>Math.min(x.open,x.close)-x.low,up=x=>x.close>x.open,dn=x=>x.close<x.open;
 const avgB=i=>{let s=0,n=0;for(let j=Math.max(0,i-14);j<i;j++){s+=B(c[j]);n++}return n?s/n:B(c[i])};
 for(let i=5;i<c.length;i++){const x=c[i],p=c[i-1],q=c[i-2],downT=cl[i-1]<e20[i-1]&&cl[i-1]<cl[i-5],upT=cl[i-1]>e20[i-1]&&cl[i-1]>cl[i-5],ab=avgB(i),add=(id,dir,str)=>out.push({i,id,name:CAND[id][0],about:CAND[id][1],dir,str});
  if(downT&&L(x)>=2*B(x)&&U(x)<=Math.max(B(x)*0.5,R(x)*0.1)&&B(x)>R(x)*0.05)add('hammer',1,2);
  if(upT&&U(x)>=2*B(x)&&L(x)<=Math.max(B(x)*0.5,R(x)*0.1)&&B(x)>R(x)*0.05)add('star',-1,2);
  if(downT&&dn(p)&&up(x)&&x.close>=p.open&&x.open<=p.close&&B(x)>B(p)*1.05)add('engB',1,3);
  if(upT&&up(p)&&dn(x)&&x.close<=p.open&&x.open>=p.close&&B(x)>B(p)*1.05)add('engS',-1,3);
  if(dn(q)&&B(q)>ab&&B(p)<B(q)*0.4&&up(x)&&x.close>(q.open+q.close)/2&&cl[i-2]<e20[i-2])add('mstar',1,3);
  if(up(q)&&B(q)>ab&&B(p)<B(q)*0.4&&dn(x)&&x.close<(q.open+q.close)/2&&cl[i-2]>e20[i-2])add('estar',-1,3);
  if(up(x)&&up(p)&&up(q)&&x.close>p.close&&p.close>q.close&&U(x)<B(x)*0.3&&U(p)<B(p)*0.3&&B(x)>ab*0.7&&B(p)>ab*0.7&&B(q)>ab*0.7&&!upT)add('soldiers',1,2);
  if(dn(x)&&dn(p)&&dn(q)&&x.close<p.close&&p.close<q.close&&L(x)<B(x)*0.3&&L(p)<B(p)*0.3&&B(x)>ab*0.7&&B(p)>ab*0.7&&B(q)>ab*0.7&&!downT)add('crows',-1,2);
  if(downT&&dn(p)&&B(p)>ab&&up(x)&&x.open<p.close&&x.close>(p.open+p.close)/2&&x.close<p.open)add('pierce',1,2);
  if(upT&&up(p)&&B(p)>ab&&dn(x)&&x.open>p.close&&x.close<(p.open+p.close)/2&&x.close>p.open)add('cloud',-1,2);
  if(downT&&dn(p)&&B(p)>ab*1.2&&up(x)&&x.open>p.close&&x.close<p.open&&B(x)<B(p)*0.5)add('haramiB',1,1);
  if(upT&&up(p)&&B(p)>ab*1.2&&dn(x)&&x.open<p.close&&x.close>p.open&&B(x)<B(p)*0.5)add('haramiS',-1,1);
  if(B(x)<=R(x)*0.08&&R(x)>ab*0.8&&(upT||downT))add('doji',0,1)}
 return out}
// точки разворота (локальные максимумы и минимумы)
function pivots(c,k){const H=[],L=[];for(let i=k;i<c.length-k;i++){let h=true,l=true;for(let j=i-k;j<=i+k;j++){if(j===i)continue;if(c[j].high>c[i].high)h=false;if(c[j].low<c[i].low)l=false}if(h)H.push([i,c[i].high]);if(l)L.push([i,c[i].low])}return {H,L}}
const near=(a,b,t)=>Math.abs(a-b)/((a+b)/2)<=t;
const lineAt=(l,i)=>l[0][1]+(l[1][1]-l[0][1])*(i-l[0][0])/((l[1][0]-l[0][0])||1);
function figures(c,k){k=k||5;const {H,L}=pivots(c,k),out=[],n=c.length;
 const brkBelow=(from,lvl)=>{for(let i=from;i<n;i++)if(c[i].close<(typeof lvl==='function'?lvl(i):lvl))return i;return -1},brkAbove=(from,lvl)=>{for(let i=from;i<n;i++)if(c[i].close>(typeof lvl==='function'?lvl(i):lvl))return i;return -1};
 // двойная вершина / дно
 for(let a=0;a<H.length-1;a++){const h1=H[a],h2=H[a+1];if(h2[0]-h1[0]<6||!near(h1[1],h2[1],0.015))continue;let lo=null;for(const l of L)if(l[0]>h1[0]&&l[0]<h2[0]&&(!lo||l[1]<lo[1]))lo=l;if(!lo||(Math.max(h1[1],h2[1])-lo[1])/lo[1]<0.03)continue;
  const b=brkBelow(h2[0]+k,lo[1]);if(b>=0&&b-h2[0]>40)continue;out.push({id:'dtop',name:'Двойная вершина',dir:-1,pts:[[h1[0]-Math.min(5,h1[0]),c[Math.max(0,h1[0]-5)].close],h1,lo,h2,[b>=0?b:n-1,b>=0?c[b].close:c[n-1].close]],lines:[[[lo[0],lo[1]],[Math.min(n-1,(b>=0?b:n-1)+5),lo[1]]]],brk:b,target:lo[1]-(Math.max(h1[1],h2[1])-lo[1]),about:'Цена дважды упёрлась в один уровень и не пробила его. Пробой «шеи» (минимума между вершинами) вниз — сигнал на продажу; цель — высота фигуры вниз.'})}
 for(let a=0;a<L.length-1;a++){const l1=L[a],l2=L[a+1];if(l2[0]-l1[0]<6||!near(l1[1],l2[1],0.015))continue;let hi=null;for(const h of H)if(h[0]>l1[0]&&h[0]<l2[0]&&(!hi||h[1]>hi[1]))hi=h;if(!hi||(hi[1]-Math.min(l1[1],l2[1]))/hi[1]<0.03)continue;
  const b=brkAbove(l2[0]+k,hi[1]);if(b>=0&&b-l2[0]>40)continue;out.push({id:'dbot',name:'Двойное дно',dir:1,pts:[[Math.max(0,l1[0]-5),c[Math.max(0,l1[0]-5)].close],l1,hi,l2,[b>=0?b:n-1,b>=0?c[b].close:c[n-1].close]],lines:[[[hi[0],hi[1]],[Math.min(n-1,(b>=0?b:n-1)+5),hi[1]]]],brk:b,target:hi[1]+(hi[1]-Math.min(l1[1],l2[1])),about:'Цена дважды оттолкнулась от одного уровня. Пробой «шеи» (максимума между донышками) вверх — сигнал на покупку; цель — высота фигуры вверх.'})}
 // голова и плечи
 for(let a=0;a<H.length-2;a++){const s1=H[a],hd=H[a+1],s2=H[a+2];if(!(hd[1]>s1[1]*1.01&&hd[1]>s2[1]*1.01&&near(s1[1],s2[1],0.03)))continue;const l1=L.filter(l=>l[0]>s1[0]&&l[0]<hd[0]).sort((x,y)=>x[1]-y[1])[0],l2=L.filter(l=>l[0]>hd[0]&&l[0]<s2[0]).sort((x,y)=>x[1]-y[1])[0];if(!l1||!l2)continue;const nl=[l1,l2],b=brkBelow(s2[0]+k,i=>lineAt(nl,i));if(b>=0&&b-s2[0]>40)continue;
  out.push({id:'hs',name:'Голова и плечи',dir:-1,pts:[s1,l1,hd,l2,s2],lines:[[l1,[Math.min(n-1,(b>=0?b:n-1)+5),lineAt(nl,Math.min(n-1,(b>=0?b:n-1)+5))]]],brk:b,target:lineAt(nl,hd[0])-(hd[1]-lineAt(nl,hd[0])),about:'Три вершины, средняя — выше. Пробой линии шеи вниз — разворот тренда вниз; цель — расстояние от головы до шеи.'})}
 for(let a=0;a<L.length-2;a++){const s1=L[a],hd=L[a+1],s2=L[a+2];if(!(hd[1]<s1[1]*0.99&&hd[1]<s2[1]*0.99&&near(s1[1],s2[1],0.03)))continue;const h1=H.filter(h=>h[0]>s1[0]&&h[0]<hd[0]).sort((x,y)=>y[1]-x[1])[0],h2=H.filter(h=>h[0]>hd[0]&&h[0]<s2[0]).sort((x,y)=>y[1]-x[1])[0];if(!h1||!h2)continue;const nl=[h1,h2],b=brkAbove(s2[0]+k,i=>lineAt(nl,i));if(b>=0&&b-s2[0]>40)continue;
  out.push({id:'ihs',name:'Перевёрнутая голова и плечи',dir:1,pts:[s1,h1,hd,h2,s2],lines:[[h1,[Math.min(n-1,(b>=0?b:n-1)+5),lineAt(nl,Math.min(n-1,(b>=0?b:n-1)+5))]]],brk:b,target:lineAt(nl,hd[0])+(lineAt(nl,hd[0])-hd[1]),about:'Три минимума, средний — ниже. Пробой линии шеи вверх — разворот тренда вверх; цель — расстояние от головы до шеи.'})}
 // треугольники и клинья по последним точкам разворота
 const tri=(hs,ls)=>{if(hs.length<2||ls.length<2)return;const st=Math.min(hs[0][0],ls[0][0]),en=Math.max(hs[1][0],ls[1][0]);if(en-st<10)return;const top=[hs[0],hs[1]],bot=[ls[0],ls[1]],dh=(hs[1][1]-hs[0][1])/hs[0][1],dl=(ls[1][1]-ls[0][1])/ls[0][1];
  let id,name,dir,about;if(Math.abs(dh)<0.01&&dl>0.01){id='asc';name='Восходящий треугольник';dir=1;about='Ровный верх и растущие минимумы: покупатели давят на сопротивление. Чаще пробивается вверх.'}
  else if(Math.abs(dl)<0.01&&dh<-0.01){id='desc';name='Нисходящий треугольник';dir=-1;about='Ровный низ и снижающиеся максимумы: продавцы давят на поддержку. Чаще пробивается вниз.'}
  else if(dh<-0.01&&dl>0.01){id='sym';name='Симметричный треугольник';dir=0;about='Максимумы снижаются, минимумы растут — цена сжимается. Сигнал — в сторону пробоя.'}
  else if(dh>0.01&&dl>0.01&&dl>dh){id='wedgeR';name='Восходящий клин';dir=-1;about='Цена растёт, но всё медленнее — линии сходятся вверх. Обычно пробивается вниз.'}
  else if(dh<-0.01&&dl<-0.01&&dh<dl){id='wedgeF';name='Нисходящий клин';dir=1;about='Цена падает, но всё медленнее — линии сходятся вниз. Обычно пробивается вверх.'}
  else return;
  let b=-1,bd=0;for(let i=en+k;i<n;i++){if(c[i].close>lineAt(top,i)*1.002){b=i;bd=1;break}if(c[i].close<lineAt(bot,i)*0.998){b=i;bd=-1;break}}if(b>=0&&b-en>30)return;const end=Math.min(n-1,(b>=0?b:n-1)+3),hgt=hs[0][1]-ls[0][1];
  out.push({id,name,dir:b>=0?bd:dir,expect:dir,pts:[],lines:[[top[0],[end,lineAt(top,end)]],[bot[0],[end,lineAt(bot,end)]]],brk:b,target:b>=0?c[b].close+bd*hgt:null,about})};
 tri(H.slice(-2),L.slice(-2));if(H.length>=3&&L.length>=3)tri(H.slice(-3,-1),L.slice(-3,-1));
 // убираем пересекающиеся дубликаты одного вида
 const seen={};return out.filter(f=>{const k=f.id+':'+(f.pts[2]||f.lines[0][0])[0];if(seen[k])return false;seen[k]=1;return true})}
function detect(c){return {cands:candles(c),figs:figures(c,c.length>300?5:4)}}
// стратегия «Фигуры и паттерны»: вход по пробою фигуры или по сильному свечному паттерну по тренду; выход — противоположный сигнал
const strat={id:'pat',name:'Фигуры и паттерны',about:'Покупает и продаёт по фигурам (двойная вершина и дно, голова и плечи, треугольники, клинья) — в момент пробоя, и по сильным свечным паттернам (поглощение, звёзды, молот) по направлению тренда. Выход — противоположный сигнал.',
 params:[{k:'mins',n:'Сила свечного паттерна (1–3)',v:3},{k:'figs',n:'Учитывать фигуры (1 — да, 0 — нет)',v:1},{k:'cnd',n:'Учитывать свечи (1 — да, 0 — нет)',v:1}],
 run(c,p){const d=detect(c),ev=[];if(p.cnd)d.cands.forEach(x=>{if(x.dir&&x.str>=p.mins)ev.push({i:x.i,t:x.dir>0?'long':'short'})});if(p.figs)d.figs.forEach(f=>{if(f.brk>=0&&f.dir)ev.push({i:f.brk,t:f.dir>0?'long':'short'})});
  ev.sort((a,b)=>a.i-b.i);const o=[];let last=null;ev.forEach(x=>{if(x.t!==last){o.push(x);last=x.t}});return o}};
window.PAT={detect,CAND,strat};
if(window.ITS&&!ITS.list.find(s=>s.id==='pat'))ITS.list.push(strat);
})();
