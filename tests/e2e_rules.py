import sys,datetime as dt,subprocess
sys.path.insert(0,__import__('os').path.dirname(__import__('os').path.abspath(__file__)))
exec(open(__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'e2e_lifecycle.py')).read().split('# floor')[0])
def sql(q): return subprocess.run(['mysql','-uroot','-N','restaurant_management','-e',q],capture_output=True,text=True).stdout.strip()
c,j=api(W,'tables/api?action=floor'); rows=j['data']['data'] if isinstance(j['data'],dict) else j['data']; free=[t for t in rows if t['status']=='Available']
c,j=api(W,'orders/api?action=menu'); menu=j['data']
# pick dish with a recipe, drain its ingredient
mid=sql("SELECT menu_item_id FROM recipes r JOIN menu_items m ON m.id=r.menu_item_id WHERE m.is_available=1 LIMIT 1"); ing=sql("SELECT ingredient_id,qty FROM recipes WHERE menu_item_id=%s LIMIT 1"%mid).split()
before=float(sql("SELECT stock FROM ingredients WHERE id=%s"%ing[0]))
c,j=api(W,'orders/api','POST',action='open',type='Takeaway',guest_name='Stock Test',guests=1); oid=j['id']
api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=int(mid),qty=2); api(W,'orders/api','POST',action='send',id=oid)
c,j=api(Ch,'kitchen/api'); ids=[i['id'] for g in j['data'] if g['order_id']==oid for i in g['items']]
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=ids); chk('start cooking',c==200,j)
after=float(sql("SELECT stock FROM ingredients WHERE id=%s"%ing[0])); chk('recipe stock deducted',abs((before-after)-float(ing[1])*2)<0.002,(before,after,ing))
chk('usage movement logged',int(sql("SELECT COUNT(*) FROM stock_movements WHERE ingredient_id=%s AND type='Usage' AND reference LIKE 'Cooked for %%'"%ing[0]))>0)
# insufficient stock
sql("UPDATE ingredients SET stock=0 WHERE id=%s"%ing[0]); sql("INSERT INTO stock_movements (ingredient_id,type,qty,reference) SELECT %s,'Adjustment',-%f,'test' "%(ing[0],after) )
api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=int(mid),qty=1); api(W,'orders/api','POST',action='send',id=oid)
c,j=api(Ch,'kitchen/api'); ids2=[i['id'] for g in j['data'] if g['order_id']==oid for i in g['items'] if i['status']=='New']
c,j=api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=ids2); chk('insufficient stock blocks cooking',c==422 and 'Not enough' in j['message'],j)
# sold out toggle
c,j=api(Ch,'menu/api','POST',action='toggle',id=int(mid),available=0); chk('chef marks sold out',c==200,j)
c,j=api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=int(mid),qty=1); chk('sold out cannot be ordered',c==422,j)
c,j=api(C1,'order-online/api','POST',type='Takeaway',lines={mid:1}); chk('sold out cannot be ordered online',c==422,j)
api(Ch,'menu/api','POST',action='toggle',id=int(mid),available=1)
# cancel order by admin with prepared food; waiter cannot
c,j=api(W,'orders/api','POST',action='cancel',id=oid); chk('waiter cannot cancel (food prepared)',c==422,j)
c,j=api(A,'orders/api','POST',action='cancel',id=oid); chk('admin cancels',c==200,j)
api(Ch,'inventory/api','POST',action='receive',id=int(ing[0]),qty=500,unit_cost=100)
# loyalty: points redeem + reopen refund
sql("UPDATE customers SET loyalty_points=200 WHERE id=2")
t=free[0]; c,j=api(W,'orders/api','POST',action='open',type='Dine-in',table_id=t['id'],guests=2,customer_id=2); oid=j['id']
m=[x for x in menu if int(x['is_available']) and float(x['price'])>=800][0]
api(W,'orders/api','POST',action='add_item',id=oid,menu_item_id=m['id'],qty=1); api(W,'orders/api','POST',action='send',id=oid)
c,j=api(Ch,'kitchen/api'); ids=[i['id'] for g in j['data'] if g['order_id']==oid for i in g['items']]
c1,_=api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=ids); c2,_=api(Ch,'kitchen/api','POST',action='advance',to='Ready',ids=ids)
c,j=api(Ca,'orders/api','POST',action='generate',id=oid,points=50); chk('bill with points',c==200,(c1,c2,j)); bid=j.get('bill_id')
chk('points deducted',sql("SELECT loyalty_points FROM customers WHERE id=2")=='150')
c,j=api(Ca,'bills/api','POST',action='reopen',id=bid); chk('reopen unpaid bill',c==200,j)
chk('points refunded',sql("SELECT loyalty_points FROM customers WHERE id=2")=='200')
chk('order reopened',sql("SELECT status FROM orders WHERE id=%d"%oid)=='Open')
c,j=api(Ca,'orders/api','POST',action='generate',id=oid,points=50); bid=j['bill_id']; tot=float(sql("SELECT total FROM bills WHERE id=%d"%bid))
c,j=api(Ca,'bills/api','POST',action='pay',id=bid,amount=tot,method='Online',reference='TXN1'); chk('full payment',c==200,j)
chk('points earned (1 per Rs100)',sql("SELECT loyalty_points FROM customers WHERE id=2")==str(150+int(tot//100)),sql("SELECT loyalty_points FROM customers WHERE id=2"))
# reservation today seat -> pay -> completed
sql("INSERT INTO reservations (res_no,customer_id,guest_name,phone,party_size,res_date,res_time,table_id,source) SELECT 'RSV-T001',3,full_name,phone,2,CURDATE(),'23:00',(SELECT id FROM dining_tables WHERE status='Available' AND seats>=2 ORDER BY seats LIMIT 1),'Staff' FROM customers WHERE id=3")
rid=int(sql("SELECT id FROM reservations WHERE res_no='RSV-T001'"))
c,j=api(W,'reservations/api','POST',action='seat',id=rid); chk('seat reservation opens order',c==200 and j.get('order_id'),j); o2=j.get('order_id')
chk('reservation Seated',sql("SELECT status FROM reservations WHERE id=%d"%rid)=='Seated')
c,j=api(W,'reservations/api','POST',action='no_show',id=rid); chk('seated cannot be no-show',c==422,j)
api(W,'orders/api','POST',action='add_item',id=o2,menu_item_id=menu[0]['id'],qty=1); api(W,'orders/api','POST',action='send',id=o2)
c,j=api(Ch,'kitchen/api'); ids=[i['id'] for g in j['data'] if g['order_id']==o2 for i in g['items']]
api(Ch,'kitchen/api','POST',action='advance',to='Preparing',ids=ids); api(Ch,'kitchen/api','POST',action='advance',to='Ready',ids=ids)
c,j=api(Ca,'orders/api','POST',action='generate',id=o2); b2=j['bill_id']; tot=float(sql("SELECT total FROM bills WHERE id=%d"%b2)); api(Ca,'bills/api','POST',action='pay',id=b2,amount=tot,method='Cash')
chk('reservation Completed after payment',sql("SELECT status FROM reservations WHERE id=%d"%rid)=='Completed')
# move table
c,j=api(W,'tables/api?action=floor'); rows=j['data']['data'] if isinstance(j['data'],dict) else j['data']; fr=[t for t in rows if t['status']=='Available' and t['seats']>=2]
c,j=api(W,'orders/api','POST',action='open',type='Dine-in',table_id=fr[0]['id'],guests=2); o3=j['id']
c,j=api(W,'orders/api','POST',action='move',id=o3,table_id=fr[1]['id']); chk('move table',c==200,j)
chk('old table freed',sql("SELECT status FROM dining_tables WHERE id=%d"%fr[0]['id'])=='Available')
c,j=api(W,'orders/api','POST',action='cancel',id=o3); chk('cancel empty order frees table',c==200 and sql("SELECT status FROM dining_tables WHERE id=%d"%fr[1]['id'])=='Available',j)
# inventory actions
c,j=api(Ch,'inventory/api','POST',action='receive',id=int(ing[0]),qty=10,unit_cost=100); chk('receive stock',c==200,j)
c,j=api(Ch,'inventory/api','POST',action='wastage',id=int(ing[0]),qty=9999,reference='x'); chk('wastage > stock blocked',c==422,j)
c,j=api(Ch,'inventory/api','POST',action='adjust',id=int(ing[0]),new_stock=-1,reference='x'); chk('negative adjust blocked',c==422,j)
print('\nPASS',ok,'FAIL',bad)
