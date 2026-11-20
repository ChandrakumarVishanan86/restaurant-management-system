"""Smoke test: every page, for every role (no PHP errors, HTTP 200)."""
import re
from lib import login, B
pages={'admin':['dashboard','tables','tables/manage','orders','kitchen','reservations','bills','customers','reports','menu','menu/categories','inventory','suppliers','users','audit'],
'waiter1':['dashboard','tables','orders','kitchen','reservations','customers'],'chef1':['dashboard','kitchen','menu','inventory','suppliers'],'cashier1':['dashboard','orders','bills','reservations','kitchen'],
'customer1':['dashboard','order-online','orders','reservations','bills']}
pw={'admin':'admin123','waiter1':'waiter123','chef1':'chef123','cashier1':'cashier123','customer1':'customer123'}
for u,ps in pages.items():
    s,r=login(u,pw[u]); print(u,'login',r.status_code,r.url.split('/')[-1],'csrf',bool(s.csrf))
    for p in ps:
        r=s.get(B+'/'+p); bad=r.status_code!=200 or 'Fatal' in r.text or 'Warning:' in r.text or 'Notice:' in r.text or 'Deprecated' in r.text
        print('  ',p,r.status_code,'BAD' if bad else 'ok', (re.search(r'(Fatal error|Warning|Notice|Deprecated):[^<]{0,200}',r.text) or [''])[0] if bad else '')
