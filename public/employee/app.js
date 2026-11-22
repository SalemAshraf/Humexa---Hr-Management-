(()=>{
const T={en:{title:'Employee attendance',email:'Email',pass:'Password',signin:'Sign in',signout:'Sign out',today:'Today',notin:'Not checked in',inn:'Checked in',outt:'Checked out',ci:'Check in',co:'Check out',bs:'Start break',be:'End break',hist:'Recent days',date:'Date',in:'In',outc:'Out',loc:'Location is sent with check-in when your company asks for it.',busy:'Please wait...',nolocation:'Location is needed by your company policy. Allow location access and try again.',recorded:'Recorded.',fail:'Something went wrong. Try again.',offline:'No connection. Your action was not recorded.',lang:'العربية',empty:'No records yet.',worked:'Worked (h:mm)',late:'Late',min:'min'},
ar:{title:'حضور الموظف',email:'الإيميل',pass:'كلمة السر',signin:'دخول',signout:'خروج',today:'النهارده',notin:'لسه ما سجلتش حضور',inn:'حاضر',outt:'سجلت انصراف',ci:'تسجيل حضور',co:'تسجيل انصراف',bs:'بدء استراحة',be:'إنهاء استراحة',hist:'آخر الأيام',date:'التاريخ',in:'حضور',outc:'انصراف',loc:'الموقع بيتبعت مع تسجيل الحضور لو الشركة طالباه.',busy:'استنى شوية...',nolocation:'سياسة الشركة بتطلب الموقع. اسمح بالوصول للموقع وجرب تاني.',recorded:'اتسجل.',fail:'حصلت مشكلة. جرب تاني.',offline:'مفيش اتصال. العملية ما اتسجلتش.',lang:'English',empty:'مفيش سجلات لسه.',worked:'ساعات الشغل',late:'تأخير',min:'دقيقة'}};
let L=localStorage.getItem('lang')||((navigator.language||'en').startsWith('ar')?'ar':'en');
const $=s=>document.querySelector(s),app=$('#app');
const t=k=>T[L][k];const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const tok=()=>localStorage.getItem('tok');
async function api(path,opt={}){const h={Accept:'application/json'};if(tok())h.Authorization='Bearer '+tok();if(opt.body)h['Content-Type']='application/json';
 let r;try{r=await fetch('/api/v1'+path,{method:opt.method||'GET',headers:h,body:opt.body?JSON.stringify(opt.body):undefined})}catch(e){throw new Error(t('offline'))}
 const j=await r.json().catch(()=>({}));if(r.status===401){localStorage.removeItem('tok');render();throw new Error('401')}
 if(!r.ok)throw new Error(j.errors?Object.values(j.errors).flat()[0]:(j.message||t('fail')));return j}
function pos(){return new Promise(res=>{if(!navigator.geolocation)return res(null);navigator.geolocation.getCurrentPosition(p=>res({latitude:p.coords.latitude,longitude:p.coords.longitude,accuracy:Math.round(p.coords.accuracy)}),()=>res(null),{enableHighAccuracy:true,timeout:10000})})}
function note(m,ok){const n=$('#note');if(n)n.innerHTML=m?`<div class="msg ${ok?'ok':'err'}">${esc(m)}</div>`:''}
function chrome(){document.documentElement.lang=L;document.documentElement.dir=L==='ar'?'rtl':'ltr';$('#lang').textContent=t('lang');const o=$('#out');o.hidden=!tok();o.textContent=t('signout')}
async function render(){chrome();if(!tok())return login();
 try{const [s,h]=await Promise.all([api('/attendance/status'),api('/attendance/history?per_page=7')]);dash(s.data,h.data||[])}catch(e){if(e.message!=='401'){app.innerHTML=`<div class="card"><div class="msg err">${esc(e.message)}</div></div>`}}}
function login(){app.innerHTML=`<div class="card"><h2>${t('title')}</h2><div id="note"></div><form id="f"><label>${t('email')}</label><input name="email" type="email" autocomplete="username" required><label>${t('pass')}</label><input name="password" type="password" autocomplete="current-password" required><button class="btn">${t('signin')}</button></form></div>`;
 $('#f').onsubmit=async e=>{e.preventDefault();const d=new FormData(e.target);note(t('busy'),true);try{const j=await api('/auth/login',{method:'POST',body:{email:d.get('email'),password:d.get('password'),device_name:'Afrad web app'}});localStorage.setItem('tok',j.data.token);render()}catch(x){note(x.message)}}}
function dash(s,hist){const st=s.state,active=s.active_session;const brk=active&&(active.breaks||[]).some(b=>!b.ended_at);
 const label=st==='checked_in'?t('inn'):st==='checked_out'?t('outt'):t('notin');
 let btns='';if(st==='checked_in'){btns=`<button class="btn out" data-a="co">${t('co')}</button><button class="btn alt" data-a="${brk?'be':'bs'}">${brk?t('be'):t('bs')}</button>`}else{btns=`<button class="btn go" data-a="ci">${t('ci')}</button>`}
 const hm=m=>Math.floor((m||0)/60)+':'+String((m||0)%60).padStart(2,'0');const rows=hist.map(r=>`<tr><td>${esc(String(r.attendance_date).slice(0,10))}</td><td>${hm(r.total_worked_minutes)}</td><td>${r.late_minutes?esc(r.late_minutes)+' '+t('min'):'-'}</td></tr>`).join('');
 app.innerHTML=`<div class="card"><div class="muted">${t('today')} · ${esc(s.date)}</div><div class="state">${label}</div><div id="note"></div>${btns}<p class="muted">${t('loc')}</p></div><div class="card"><b>${t('hist')}</b>${rows?`<table><tr><th>${t('date')}</th><th>${t('worked')}</th><th>${t('late')}</th></tr>${rows}</table>`:`<p class="muted">${t('empty')}</p>`}</div>`;
 app.querySelectorAll('[data-a]').forEach(b=>b.onclick=()=>act(b.dataset.a,active));}
async function act(a,active){note(t('busy'),true);try{
 if(a==='ci'||a==='co'){const p=await pos();const body=p||{};if(!p){/* server decides if location is required */}
  await api('/attendance/'+(a==='ci'?'check-in':'check-out'),{method:'POST',body:{...body,client_timestamp:new Date().toISOString()}})}
 else await api('/attendance/breaks/'+(a==='bs'?'start':'end'),{method:'POST',body:{attendance_session_id:active.id}});
 await render();note(t('recorded'),true)}catch(e){note(/location/i.test(e.message)?t('nolocation'):e.message)}}
$('#lang').onclick=()=>{L=L==='ar'?'en':'ar';localStorage.setItem('lang',L);render()};
$('#out').onclick=async()=>{try{await api('/auth/logout',{method:'POST'})}catch(e){}localStorage.removeItem('tok');render()};
if('serviceWorker'in navigator)navigator.serviceWorker.register('/employee/sw.js',{scope:'/employee/'}).catch(()=>{});
render();})();
