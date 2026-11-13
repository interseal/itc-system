// Стратегии и индикаторы. Каждая стратегия: {id, name, about, params:[{k,n,v}], run(c,p)} -> массив сигналов
// сигнал: {i, t:'long'|'short'|'exit'}; c = [{time,open,high,low,close,volume}]
(function(){
const ema=(a,n)=>{const k=2/(n+1),o=[];let e=null;for(let i=0;i<a.length;i++){e=e===null?a[i]:a[i]*k+e*(1-k);o.push(i<n-1?null:e)}return o};
const sma=(a,n)=>{const o=[];let s=0;for(let i=0;i<a.length;i++){s+=a[i];if(i>=n)s-=a[i-n];o.push(i<n-1?null:s/n)}return o};
const sd=(a,n)=>{const m=sma(a,n);return a.map((_,i)=>{if(m[i]===null)return null;let s=0;for(let j=i-n+1;j<=i;j++)s+=(a[j]-m[i])**2;return Math.sqrt(s/n)})};
const rsi=(a,n)=>{const o=[null];let g=0,l=0;for(let i=1;i<a.length;i++){const d=a[i]-a[i-1],up=Math.max(d,0),dn=Math.max(-d,0);if(i<=n){g+=up/n;l+=dn/n;o.push(i<n?null:100-100/(1+g/(l||1e-9)))}else{g=(g*(n-1)+up)/n;l=(l*(n-1)+dn)/n;o.push(100-100/(1+g/(l||1e-9)))}}return o};
const atr=(c,n)=>{const tr=c.map((x,i)=>i?Math.max(x.high-x.low,Math.abs(x.high-c[i-1].close),Math.abs(x.low-c[i-1].close)):x.high-x.low);const o=[];let a=null;for(let i=0;i<tr.length;i++){if(i<n-1){o.push(null);continue}a=a===null?tr.slice(0,n).reduce((s,v)=>s+v,0)/n:(a*(n-1)+tr[i])/n;o.push(a)}return o};
const cross=(a,b,i)=>a[i-1]!==null&&b[i-1]!==null&&a[i-1]<=b[i-1]&&a[i]>b[i];
const P=(k,n,v)=>({k,n,v});
const S=[
{id:'ema',name:'Пересечение EMA',about:'Трендовая. Быстрая средняя пересекает медленную снизу вверх — покупка, сверху вниз — продажа. Хорошо работает на сильных трендах, в боковике даёт много ложных входов.',
 params:[P('f','Быстрая EMA',9),P('s','Медленная EMA',21)],
 lines:(c,p)=>{const cl=c.map(x=>x.close);return [{n:'EMA '+p.f,col:'#4fc3f7',v:ema(cl,p.f)},{n:'EMA '+p.s,col:'#ffb74d',v:ema(cl,p.s)}]},
 run(c,p){const cl=c.map(x=>x.close),f=ema(cl,p.f),s=ema(cl,p.s),o=[];for(let i=1;i<c.length;i++){if(cross(f,s,i))o.push({i,t:'long'});else if(cross(s,f,i))o.push({i,t:'short'})}return o}},
{id:'st',name:'Супертренд',about:'Трендовая. Линия по волатильности (ATR): цена закрылась выше линии — покупка, ниже — продажа. Популярна на 4-часовом и дневном графике биткоина.',
 params:[P('n','Период ATR',10),P('m','Множитель',3)],
 calc(c,p){const a=atr(c,p.n),up=[],dn=[],dir=[];for(let i=0;i<c.length;i++){if(a[i]===null){up.push(null);dn.push(null);dir.push(0);continue}const m=(c[i].high+c[i].low)/2;let u=m-p.m*a[i],d=m+p.m*a[i];if(up[i-1]!=null&&c[i-1].close>up[i-1])u=Math.max(u,up[i-1]);if(dn[i-1]!=null&&c[i-1].close<dn[i-1])d=Math.min(d,dn[i-1]);up.push(u);dn.push(d);let r=dir[i-1]||1;if(r===1&&c[i].close<u)r=-1;else if(r===-1&&c[i].close>d)r=1;dir.push(r)}return {up,dn,dir}},
 lines(c,p){const x=this.calc(c,p);return [{n:'Супертренд',col:'#00bcd4',v:x.dir.map((d,i)=>d===1?x.up[i]:d===-1?x.dn[i]:null)}]},
 run(c,p){const x=this.calc(c,p),o=[];for(let i=1;i<c.length;i++){if(x.dir[i-1]&&x.dir[i]!==x.dir[i-1])o.push({i,t:x.dir[i]===1?'long':'short'})}return o}},
{id:'rsi',name:'RSI: отскок по тренду',about:'Контртрендовая с фильтром. Покупка, когда RSI выходит из перепроданности, а цена выше длинной EMA; продажа — выход из перекупленности ниже EMA. Выход — RSI возвращается к 50.',
 params:[P('n','Период RSI',14),P('lo','Перепроданность',30),P('hi','Перекупленность',70),P('t','Фильтр EMA',200)],
 lines:(c,p)=>[{n:'EMA '+p.t,col:'#90a4ae',v:ema(c.map(x=>x.close),p.t)}],
 run(c,p){const cl=c.map(x=>x.close),r=rsi(cl,p.n),e=ema(cl,p.t),o=[];let pos=0;for(let i=1;i<c.length;i++){if(r[i]===null||r[i-1]===null||e[i]===null)continue;if(pos!==1&&r[i-1]<p.lo&&r[i]>=p.lo&&cl[i]>e[i]){o.push({i,t:'long'});pos=1}else if(pos!==-1&&r[i-1]>p.hi&&r[i]<=p.hi&&cl[i]<e[i]){o.push({i,t:'short'});pos=-1}else if(pos===1&&r[i]>=50+(p.hi-50)/2){o.push({i,t:'exit'});pos=0}else if(pos===-1&&r[i]<=50-(50-p.lo)/2){o.push({i,t:'exit'});pos=0}}return o}},
{id:'don',name:'Пробой канала (черепахи)',about:'Трендовая. Покупка при пробое максимума за N свечей, продажа при пробое минимума. Выход — пробой короткого канала в обратную сторону.',
 params:[P('n','Канал входа',20),P('x','Канал выхода',10)],
 hl(c,n,i){let h=-1e18,l=1e18;for(let j=i-n;j<i;j++){h=Math.max(h,c[j].high);l=Math.min(l,c[j].low)}return [h,l]},
 lines(c,p){const h=[],l=[];for(let i=0;i<c.length;i++){if(i<p.n){h.push(null);l.push(null);continue}const r=this.hl(c,p.n,i);h.push(r[0]);l.push(r[1])}return [{n:'Верх канала',col:'#66bb6a',v:h},{n:'Низ канала',col:'#f23645',v:l}]},
 run(c,p){const o=[];let pos=0;for(let i=p.n;i<c.length;i++){const [h,l]=this.hl(c,p.n,i),[xh,xl]=this.hl(c,p.x,i);if(pos!==1&&c[i].close>h){o.push({i,t:'long'});pos=1}else if(pos!==-1&&c[i].close<l){o.push({i,t:'short'});pos=-1}else if(pos===1&&c[i].close<xl){o.push({i,t:'exit'});pos=0}else if(pos===-1&&c[i].close>xh){o.push({i,t:'exit'});pos=0}}return o}},
{id:'macd',name:'MACD',about:'Трендовая. Линия MACD пересекает сигнальную снизу вверх — покупка, сверху вниз — продажа. Фильтр: покупки только выше нуля, продажи — ниже.',
 params:[P('f','Быстрая',12),P('s','Медленная',26),P('g','Сигнальная',9)],
 run(c,p){const cl=c.map(x=>x.close),f=ema(cl,p.f),s=ema(cl,p.s),m=cl.map((_,i)=>f[i]===null||s[i]===null?null:f[i]-s[i]),st=m.findIndex(v=>v!==null),sg=Array(st).fill(null).concat(ema(m.slice(st),p.g)),o=[];let pos=0;for(let i=st+p.g;i<c.length;i++){if(cross(m,sg,i)){if(m[i]<0&&pos===-1){o.push({i,t:'exit'});pos=0}else if(m[i]>=0){o.push({i,t:'long'});pos=1}}else if(cross(sg,m,i)){if(m[i]>0&&pos===1){o.push({i,t:'exit'});pos=0}else if(m[i]<=0){o.push({i,t:'short'});pos=-1}}}return o}},
{id:'bb',name:'Полосы Боллинджера',about:'Контртрендовая, для боковика. Цена закрылась ниже нижней полосы и вернулась внутрь — покупка; выше верхней и вернулась — продажа. Выход — средняя линия.',
 params:[P('n','Период',20),P('k','Ширина (отклонений)',2)],
 bands(c,p){const cl=c.map(x=>x.close),m=sma(cl,p.n),d=sd(cl,p.n);return {m,u:m.map((v,i)=>v===null?null:v+p.k*d[i]),l:m.map((v,i)=>v===null?null:v-p.k*d[i])}},
 lines(c,p){const b=this.bands(c,p);return [{n:'Средняя',col:'#90a4ae',v:b.m},{n:'Верхняя',col:'#42a5f5',v:b.u},{n:'Нижняя',col:'#42a5f5',v:b.l}]},
 run(c,p){const b=this.bands(c,p),o=[];let pos=0;for(let i=p.n;i<c.length;i++){const x=c[i].close,y=c[i-1].close;if(pos!==1&&y<b.l[i-1]&&x>b.l[i]){o.push({i,t:'long'});pos=1}else if(pos!==-1&&y>b.u[i-1]&&x<b.u[i]){o.push({i,t:'short'});pos=-1}else if(pos===1&&x>=b.m[i]){o.push({i,t:'exit'});pos=0}else if(pos===-1&&x<=b.m[i]){o.push({i,t:'exit'});pos=0}}return o}}
];
// Проверка на истории: вход по закрытию сигнальной свечи, комиссия fee% за каждую сделку в одну сторону
function backtest(c,sig,o){const fee=(o.fee||0.05)/100,spot=o.spot;let pos=0,ep=0,eq=1,peak=1,dd=0;const tr=[];const close=(i)=>{const r=pos===1?c[i].close/ep:2-c[i].close/ep/* №482 · шорт: от цены входа */;const net=r*(1-fee)*(1-fee);eq*=net;tr.push({from:o.from,i0:o.i0,i,side:pos,pnl:(net-1)*100});pos=0};
 for(const s of sig){const i=s.i;if(s.t==='exit'){if(pos)close(i)}else{const want=s.t==='long'?1:-1;if(pos===want)continue;if(pos)close(i);if(want===-1&&spot)continue;pos=want;ep=c[i].close;o.i0=i}peak=Math.max(peak,eq);dd=Math.max(dd,1-eq/peak)}
 const open=pos?{side:pos,ep,i0:o.i0,pnl:((pos===1?c[c.length-1].close/ep:ep/c[c.length-1].close)-1)*100}:null;
 const w=tr.filter(t=>t.pnl>0).length;return {n:tr.length,win:tr.length?w/tr.length*100:0,ret:(eq-1)*100,dd:dd*100,hold:(c[c.length-1].close/c[0].close-1)*100,trades:tr,open}}
window.ITS={list:S,backtest,ind:{ema,sma,rsi,atr}};
})();
