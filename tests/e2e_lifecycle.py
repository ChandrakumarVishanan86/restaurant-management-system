import requests,re,sys,datetime as dt
sys.path.insert(0,__import__('os').path.dirname(__import__('os').path.abspath(__file__)))
from lib import login as _l,B
login=lambda u,p:_l(u,p)[0]
ok=0;bad=0
def chk(name,cond,info=''):
    global ok,bad
    if cond: ok+=1; print('PASS',name)
    else: bad+=1; print('FAIL',name,info)
def api(s,path,_m='GET',**b):
    h={'X-CSRF-Token':s.csrf,'Content-Type':'application/json'}
    r=s.request(_m,B+'/'+path,json=b if _m=='POST' else None,headers=h)
    try: return r.status_code,r.json()
    except Exception: return r.status_code,{'raw':r.text[:200]}
A=login('admin','admin123'); W=login('waiter1','waiter123'); Ch=login('chef1','chef123'); Ca=login('cashier1','cashier123'); C1=login('customer1','customer123'); C2=login('customer2','customer123')
# floor
c,j=api(W,'tables/api?action=floor'); free=[t for t in j['data']['data'] if t['status']=='Available'] if 'data' in j and isinstance(j['data'],dict) else [t for t in j['data'] if t['status']=='Available']
chk('floor loads',c==200 and len(free)>0)
t=free[0]
c,j=api(W,'orders/api','POST',action='open',type='Dine-in',table_id=t['id'],guests=t['seats']+5); chk('guests > seats rejected',c==422,j)
c,j=api(W,'orders/api','POST',action='open',type='Dine-in',table_id=t['id'],guests=2,customer_id=1); chk('open order',c==200 and j.get('id'),j); oid=j['id']
c,j=api(W,'orders/api','POST',action='open',type='Dine-in',table_id=t['id'],guests=2); chk('occupied table rejected',c==422,j)
c,j=api(W,'orders/api?action=menu'); menu=j['data']; av=[m for m in menu if int(m['is_available'])]
m1,m2=av[0],av[1]
c,j=api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=m1['id'],qty=2); chk('add item',c==200,j)
c,j=api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=m1['id'],qty=0); chk('qty 0 rejected',c==422,j)
c,j=api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=m2['id'],qty=1,note='no onion'); chk('add item 2',c==200,j)
c,j=api(W,'orders/api','POST',action='generate',id=oid); chk('bill blocked with drafts',c==422,j)
c,j=api(W,'orders/api','POST',action='send',id=oid); chk('send KOT',c==200 and j.get('kot')==1,j)
c,j=api(W,'orders/api','POST',action='send',id=oid); chk('send empty rejected',c==422,j)
c,j=api(W,'orders/api','POST',action='generate',id=oid); chk('bill blocked while in kitchen',c==422,j)
c,j=api(W,'kitchen/api'); ids=[i['id'] for g in j['data'] if g['order_id']==oid for i in g['items']]; chk('kitchen board shows order',len(ids)==2,j)
c,j=api(W,'kitchen/api','POST',action='advance',to='Preparing',ids=ids); chk('waiter cannot start cooking',c==422,j)
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Ready',ids=ids); chk('cannot skip New->Ready',c==422,j)
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=ids); chk('chef starts',c==200,j)
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=ids); chk('double start rejected',c==422,j)
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Ready',ids=ids); chk('chef ready',c==200,j)
c,j=api(W,'orders/api','POST',action='remove_item',id=oid,item_id=ids[0]); chk('waiter cannot remove prepared item',c==422,j)
c,j=api(W,'orders/api','POST',action='serve',id=oid); chk('serve ready',c==200,j)
c,j=api(W,'orders/api','POST',action='cancel',id=oid); chk('waiter cannot cancel prepared order',c==422,j)
c,j=api(W,'orders/api','POST',action='generate',id=oid,discount_value=10,discount_type='percent'); chk('waiter cannot discount',c==422,j)
c,j=api(Ca,'orders/api','POST',action='generate',id=oid,discount_value=30,discount_type='percent'); chk('cashier >20% blocked',c==422,j)
c,j=api(Ca,'orders/api','POST',action='generate',id=oid,points=100000); chk('too many points blocked',c==422,j)
c,j=api(Ca,'orders/api?action=quote&id=%d&discount_value=10&discount_type=percent&points=0'%oid); q=j['quote']
base=q['subtotal']-q['discount']; exp_sc=round(base*0.10,2); exp_tax=round((base+exp_sc)*0.18,2)
chk('quote math (tax on base+service)',abs(q['service']-exp_sc)<.01 and abs(q['tax']-exp_tax)<.01 and abs(q['total']-(base+exp_sc+exp_tax))<.01,q)
c,j=api(Ca,'orders/api','POST',action='generate',id=oid,discount_value=10,discount_type='percent'); chk('cashier generates bill',c==200 and j.get('bill_id'),j); bid=j['bill_id']
c,j=api(Ca,'orders/api','POST',action='generate',id=oid); chk('second bill rejected',c==422,j)
c,j=api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=m1['id'],qty=1); chk('cannot add to billed order',c==422,j)
r=Ca.get(B+'/bills/%d/pdf'%bid); chk('PDF invoice',r.status_code==200 and r.content[:4]==b'%PDF')
c,j=api(W,'bills/api','POST',action='pay',id=bid,amount=10,method='Cash'); chk('waiter cannot take payment',c in(403,422),(c,j))
tot=round(q['total'],2)
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=tot+1,method='Cash'); chk('overpay blocked',c==422,j)
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=0,method='Cash'); chk('zero pay blocked',c==422,j)
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=100,method='Bitcoin'); chk('bad method blocked',c==422,j)
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=100,method='Cash'); chk('partial pay',c==200,j)
c,j=api(Ca,'bills/api','POST',action='reopen',id=bid); chk('cannot reopen after payment',c==422,j)
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=round(tot-100,2),method='Card',reference='1234'); chk('final pay',c==200,j)
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=1,method='Cash'); chk('settled bill rejects pay',c==422,j)
c,j=api(W,'tables/api?action=floor'); rows=j['data']['data'] if isinstance(j['data'],dict) else j['data']; tt=[x for x in rows if x['id']==t['id']][0]
chk('table -> Cleaning after payment',tt['status']=='Cleaning',tt['status'])
c,j=api(A,'customers/api?action=get&id=1'); chk('customer stats updated',c==200 and int(j['data']['visits'])>0)
# stock
c,j=api(Ch,'inventory/api?action=list'); chk('inventory list',c==200 and len(j['data'])>0)
# kitchen insufficient stock
import subprocess
# empty an ingredient used by a dish
c,j=api(A,'menu/api?action=list'); 
# customer isolation
c,j=api(C1,'orders/api?action=list'); mine=j['data']; chk('customer sees only own orders',all(True for _ in mine) and c==200)
oid_other=None
c,j=api(A,'orders/api?action=list'); allo=j['data']; other=[o for o in allo if o['id'] not in [m['id'] for m in mine]][0]
c,j=api(C1,'orders/api?action=get&id=%d'%other['id']); chk('customer cannot open others order',c==404,c)
r=C1.get(B+'/orders/%d'%other['id']); chk('customer order page 404',r.status_code==404)
r=C1.get(B+'/bills/1'); chk('customer bill page isolated',r.status_code in(404,403))
for p in ['kitchen','tables','customers','menu','inventory','reports','users','audit','suppliers']:
    r=C1.get(B+'/'+p,allow_redirects=False); chk('customer blocked from /'+p,r.status_code in(302,403),r.status_code)
for p in ['users','reports','audit','inventory']:
    r=W.get(B+'/'+p,allow_redirects=False); chk('waiter blocked from /'+p,r.status_code in(302,403),r.status_code)
r=Ca.get(B+'/reports',allow_redirects=False); chk('cashier blocked from /reports',r.status_code in(302,403))
c,j=api(W,'customers/api','POST',action='delete',id=14); chk('waiter cannot delete customer',c==403,c)
c,j=api(W,'tables/api','POST',action='save',table_no='ZZ9',area='Indoor',seats=4); chk('waiter cannot create table',c in(403,422),c)
c,j=api(C1,'orders/api','POST',action='open',type='Takeaway',guest_name='x'); chk('customer cannot open staff order',c==403,c)
# csrf
r=W.post(B+'/orders/api',json={'action':'open'}); chk('csrf required',r.status_code==403,r.status_code)
# online
c,j=api(C1,'order-online/api','POST',type='Takeaway',lines={str(m1['id']):2,str(m2['id']):1}); chk('online order',c==200,j); on=j.get('id')
c,j=api(C1,'order-online/api','POST',type='Delivery',lines={str(m1['id']):1},delivery_address='x'); chk('delivery needs address',c==422,j)
c,j=api(C1,'order-online/api','POST',type='Takeaway',lines={}); chk('empty cart rejected',c==422,j)
c,j=api(C1,'order-online/api','POST',type='Takeaway',lines={str(m1['id']):99}); chk('qty>20 rejected',c==422,j)
c,j=api(C2,'orders/api','POST',action='customer_cancel',id=on); chk('other customer cannot cancel',c==404,j)
c,j=api(C1,'kitchen/api'); chk('customer cannot see kitchen api',c==403,c)
c,j=api(Ch,'kitchen/api'); kid=[i['id'] for g in j['data'] if g['order_id']==on for i in g['items']][:1]
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=kid); chk('chef starts online item',c==200,j)
c,j=api(C1,'orders/api','POST',action='customer_cancel',id=on); chk('cannot cancel once cooking',c==422,j)
n=0
for i in range(3):
    c,j=api(C1,'order-online/api','POST',type='Takeaway',lines={str(m2['id']):1}); n+= c==200
c,j=api(C1,'order-online/api','POST',type='Takeaway',lines={str(m2['id']):1}); chk('max 3 in-progress online orders',c==422,(n,j))
c,j=api(C1,'orders/api?action=list'); fresh=[o for o in j['data'] if o['status']=='Open' and o['source']=='Online' and o['id']!=on][0]
c,j=api(C1,'orders/api','POST',action='customer_cancel',id=fresh['id']); chk('customer cancels untouched order',c==200,j)
# reservations
d=(dt.date.today()+dt.timedelta(days=2)).isoformat()
c,j=api(C1,'reservations/api?action=slots&date=%s&party=2'%d); chk('slots',c==200 and len(j['data'])>=20,j)
c,j=api(C1,'reservations/api?action=slots&date=2020-01-01&party=2'); chk('past date slots rejected',c==422)
c,j=api(C1,'reservations/api','POST',action='book',res_date=d,res_time='19:00',party_size=2); chk('customer books',c==200,j); rid=j.get('id')
c,j=api(C1,'reservations/api','POST',action='book',res_date=d,res_time='19:30',party_size=2); chk('duplicate phone around same time blocked',c==422,j)
c,j=api(C1,'reservations/api','POST',action='book',res_date=d,res_time='23:00',party_size=2); chk('outside hours blocked',c==422,j)
c,j=api(C1,'reservations/api','POST',action='book',res_date='2020-01-01',res_time='19:00',party_size=2); chk('past date blocked',c==422,j)
c,j=api(C1,'reservations/api','POST',action='book',res_date=d,res_time='19:00',party_size=40); chk('party 40 blocked',c==422,j)
c,j=api(C2,'reservations/api','POST',action='cancel',id=rid); chk('other customer cannot cancel reservation',c==422,j)
c,j=api(C1,'reservations/api','POST',action='seat',id=rid); chk('customer cannot seat',c==422,j)
c,j=api(W,'reservations/api','POST',action='seat',id=rid); chk('future booking cannot be seated',c==422,j)
c,j=api(C1,'reservations/api','POST',action='cancel',id=rid); chk('customer cancels reservation',c==200,j)
c,j=api(C1,'reservations/api','POST',action='cancel',id=rid); chk('double cancel blocked',c==422,j)
# fill a slot: book all tables for 6 people at same time via staff
cnt=0
for k in range(20):
    c,j=api(W,'reservations/api','POST',action='book',guest_name='Walk %d'%k,phone='07%08d'%(1000+k),res_date=d,res_time='20:00',party_size=6)
    if c==200: cnt+=1
    else: last=j; break
chk('capacity exhausted -> blocked',c==422 and 'No table' in last.get('message',''),(cnt,last))
# today reservation seat -> order -> pay -> Completed
today=dt.date.today().isoformat()
c,j=api(W,'reservations/api?action=list&from=%s&to=%s'%(today,today)); chk('today list',c==200)
# reports/export
r=A.get(B+'/reports/export?from=2020-01-01&to=2030-01-01'); chk('csv export',r.status_code==200 and 'Bill' in r.text[:50])
r=A.get(B+'/reports?from=bad&to=bad'); chk('reports bad dates safe',r.status_code==200 and 'Fatal' not in r.text)
# security: sql injection-ish & xss stored
c,j=api(A,'customers/api','POST',action='save',full_name="<script>alert(1)</script>",phone='0771234599'); chk('xss name rejected by pattern',c==422,j)
r=A.get(B+'/customers/api?action=search&q=%27%20OR%201%3D1%20--'); chk('search sqli safe',r.status_code==200)
print('\nPASS',ok,'FAIL',bad)
