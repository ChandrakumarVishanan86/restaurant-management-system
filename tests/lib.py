import requests,re,sys
import os
B=os.environ.get('BASE','http://localhost:8080/restaurant_management')
def login(u,p):
    s=requests.Session(); r=s.get(B+'/login'); t=re.search(r'name="csrf_token" value="([^"]+)"',r.text).group(1)
    r=s.post(B+'/login',data={'username':u,'password':p,'csrf_token':t},allow_redirects=True); s.csrf=None
    m=re.search(r'csrf:\s*"([^"]+)"|csrf:\s*\'([^\']+)\'',r.text); s.csrf=(m.group(1) or m.group(2)) if m else None
    return s,r
