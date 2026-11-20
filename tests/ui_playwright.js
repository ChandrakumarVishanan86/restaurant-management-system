const { chromium } = require('playwright');
const B=process.env.BASE||'http://localhost:8080/restaurant_management', OUT='docs/screenshots/';
const fs=require('fs'),N='/tmp/cdn/node_modules/';
const MAP=[[/bootstrap@5.3.3\/dist\/js\/bootstrap.bundle.min.js/,N+'bootstrap/dist/js/bootstrap.bundle.min.js','application/javascript'],[/bootstrap@5.3.3\/dist\/css\/bootstrap.min.css/,N+'bootstrap/dist/css/bootstrap.min.css','text/css'],
[/bootstrap-icons@1.11.3\/font\/bootstrap-icons.min.css/,N+'bootstrap-icons/font/bootstrap-icons.min.css','text/css'],[/bootstrap-icons.*\.woff2/,null,'font/woff2'],
[/jquery-3.7.1.min.js/,N+'jquery/dist/jquery.min.js','application/javascript'],[/jquery.dataTables.min.js/,N+'datatables.net/js/jquery.dataTables.min.js','application/javascript'],
[/dataTables.bootstrap5.min.js/,N+'datatables.net-bs5/js/dataTables.bootstrap5.min.js','application/javascript'],[/dataTables.bootstrap5.min.css/,N+'datatables.net-bs5/css/dataTables.bootstrap5.min.css','text/css'],
[/flatpickr.min.js/,N+'flatpickr/dist/flatpickr.min.js','application/javascript'],[/flatpickr.min.css/,N+'flatpickr/dist/flatpickr.min.css','text/css'],[/chart.umd.min.js/,N+'chart.js/dist/chart.umd.js','application/javascript']];
async function cdn(ctx){ await ctx.route(/^https:\/\/(cdn|code|fonts)\./,async r=>{const u=r.request().url(); const m=MAP.find(x=>x[0].test(u)); if(m&&m[1]) return r.fulfill({body:fs.readFileSync(m[1]),contentType:m[2]}); if(m&&/woff2/.test(u)){const f=N+'bootstrap-icons/font/fonts/bootstrap-icons.woff2'; return r.fulfill({body:fs.readFileSync(f),contentType:'font/woff2'});} r.abort(); }); }
(async()=>{
 const br=await chromium.launch(); const errs=[]; const steps=[]; const ok=(n,c)=>{steps.push((c?'PASS ':'FAIL ')+n)};
 async function sess(u,p,vp={width:1440,height:900}){ const ctx=await br.newContext({viewport:vp,deviceScaleFactor:1}); await cdn(ctx); const pg=await ctx.newPage();
   pg.on('pageerror',e=>errs.push(u+' '+pg.url()+' PAGEERR '+e.message)); pg.on('console',m=>{ if(m.type()==='error' && !/net::ERR|Failed to load resource/.test(m.text())) errs.push(u+' '+pg.url()+' CONSOLE '+m.text()); });
   pg.on('response',r=>{ if(r.status()>=500) errs.push('HTTP '+r.status()+' '+r.url()); });
   await pg.goto(B+'/login'); await pg.fill('[name=username]',u); await pg.fill('[name=password]',p); await Promise.all([pg.waitForNavigation(),pg.click('button[type=submit]')]); return pg; }
 const snap=async(pg,path,name,wait=1200,full=false)=>{ await pg.goto(B+'/'+path,{waitUntil:'networkidle'}); await pg.waitForTimeout(wait); await pg.screenshot({path:OUT+name+'.png',fullPage:full}); };
 const lc=await br.newContext({viewport:{width:1440,height:900}}); await cdn(lc); const lg=await lc.newPage(); await lg.goto(B+'/login',{waitUntil:'networkidle'}); await lg.screenshot({path:OUT+'login.png'});
 const a=await sess('admin','admin123'); await a.waitForTimeout(2200); await a.screenshot({path:OUT+'dashboard-admin.png'});
 for (const [p,n,w] of [['tables','floor-plan'],['orders','orders'],['kitchen','kitchen-display'],['reservations','reservations'],['bills','billing'],['customers','customers'],['customers/1','customer-profile'],['menu','menu-recipes'],['inventory','inventory'],['reports','reports',2500],['suppliers','suppliers'],['users','users'],['audit','audit-log'],['tables/manage','manage-tables']]) await snap(a,p,n,w||1200);
 // --- waiter flow through the UI
 const w=await sess('waiter1','waiter123'); await w.waitForTimeout(1200); await w.screenshot({path:OUT+'dashboard-waiter.png'});
 await w.goto(B+'/tables',{waitUntil:'networkidle'}); await w.waitForTimeout(800);
 const free=await w.$('.table-card.tc-success'); ok('free table card exists',!!free); await free.click(); await w.waitForTimeout(700); await w.screenshot({path:OUT+'open-table.png'});
 await w.fill('#otGuests, [name=guests]','2').catch(()=>{}); 
 const go=await w.$('#otGo, #btnOpenOrder, .modal.show button.btn-gradient'); await Promise.all([w.waitForNavigation({timeout:8000}).catch(()=>{}),go.click()]); await w.waitForLoadState('networkidle');
 ok('order screen opened',/orders\/\d+/.test(w.url()));
 await w.waitForTimeout(1000);
 const cards=await w.$$('.menu-card:not(.soldout)'); ok('menu cards render',cards.length>10);
 for (let i=0;i<2;i++){ await w.locator('.menu-card:not(.soldout)').nth(i*3).click(); await w.waitForTimeout(400); await w.click('#nmAdd'); await w.waitForTimeout(700); }
 await w.screenshot({path:OUT+'pos-order.png'});
 ok('draft lines',(await w.$$('.line-item')).length===2);
 await w.click('#bSend'); await w.waitForTimeout(900); ok('sent to kitchen',(await w.$$('.badge-soft-info')).length>=2);
 await w.screenshot({path:OUT+'pos-sent.png'});
 const orderUrl=w.url();
 // --- chef
 const c=await sess('chef1','chef123'); await c.waitForTimeout(1500); await c.screenshot({path:OUT+'dashboard-chef.png'});
 await c.goto(B+'/kitchen',{waitUntil:'networkidle'}); await c.waitForTimeout(800);
 await c.screenshot({path:OUT+'kitchen-chef.png'});
 for (let k=0;k<6;k++){ const b=await c.$('button[data-to=Preparing]'); if(!b) break; await b.click(); await c.waitForTimeout(700); }
 await c.waitForTimeout(500);
 for (let k=0;k<12;k++){ const b=await c.$('button[data-to=Ready]'); if(!b) break; await b.click(); await c.waitForTimeout(700); }
 ok('chef cooked tickets',(await c.$$('button[data-to=Ready]')).length===0);
 // --- waiter serves + bills? cashier bills
 await w.goto(orderUrl,{waitUntil:'networkidle'}); await w.waitForTimeout(800);
 const sv=await w.$('#bServe'); ok('serve button after ready',!!sv); if(sv){ await sv.click(); await w.waitForTimeout(900); }
 const ca=await sess('cashier1','cashier123'); await ca.waitForTimeout(1800); await ca.screenshot({path:OUT+'dashboard-cashier.png'});
 await ca.goto(orderUrl,{waitUntil:'networkidle'}); await ca.waitForTimeout(800);
 const bb=await ca.$('#bBill'); ok('bill button',!!bb); await bb.click(); await ca.waitForTimeout(600); await ca.fill('#bdVal','10'); await ca.selectOption('#bdType','percent'); await ca.waitForTimeout(900); await ca.screenshot({path:OUT+'bill-modal.png'});
 await Promise.all([ca.waitForNavigation({timeout:8000}).catch(()=>{}),ca.click('#bdGo')]); await ca.waitForLoadState('networkidle'); ok('bill page',/bills\/\d+/.test(ca.url())); await ca.waitForTimeout(600);
 await ca.screenshot({path:OUT+'bill-view.png',fullPage:true});
 await ca.click('#payForm button.btn-gradient'); await ca.waitForTimeout(1500); ok('paid badge',(await ca.content()).includes('Paid')); await ca.screenshot({path:OUT+'bill-paid.png',fullPage:true});
 // --- reservations booking modal
 await a.goto(B+'/reservations',{waitUntil:'networkidle'}); await a.click('#btnBook'); await a.waitForTimeout(1200); const pill=await a.$('.slot-pill:not(.full)'); ok('slots shown',!!pill); if(pill) await pill.click(); await a.screenshot({path:OUT+'book-table.png'});
 // --- customer portal
 const cu=await sess('customer1','customer123'); await cu.waitForTimeout(1500); await cu.screenshot({path:OUT+'dashboard-customer.png',fullPage:true});
 await cu.goto(B+'/order-online',{waitUntil:'networkidle'}); await cu.waitForTimeout(800);
 await cu.locator('.menu-card:not(.soldout)').nth(1).click(); await cu.locator('.menu-card:not(.soldout)').nth(5).click(); await cu.waitForTimeout(400); await cu.screenshot({path:OUT+'order-online.png'});
 await Promise.all([cu.waitForNavigation({timeout:8000}).catch(()=>{}),cu.click('#cGo')]); await cu.waitForLoadState('networkidle'); await cu.waitForTimeout(1200); ok('online order placed',/orders\/\d+/.test(cu.url())); await cu.screenshot({path:OUT+'order-tracker.png'});
 await snap(cu,'reservations','my-reservations');
 // --- mobile
 const m=await sess('waiter1','waiter123',{width:390,height:844}); await snap(m,'tables','mobile-floor'); await snap(m,'dashboard','mobile-dashboard');
 const ov=await m.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2); ok('no horizontal overflow on mobile',!ov);
 console.log(steps.join('\n')); console.log('ERRORS',errs.length); errs.slice(0,15).forEach(e=>console.log(e)); await br.close();
})();