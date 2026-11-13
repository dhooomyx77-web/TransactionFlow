"""Black-box API tests. Requires isolated test DB and server on 8091.
Uses Python standard library only; refuses the normal demo port.
"""
import json, urllib.request, urllib.error, http.cookiejar, pathlib, csv, io
BASE='http://127.0.0.1:8091/api.php?'
root=pathlib.Path(__file__).resolve().parents[1]
password=(root/'var/local-access.txt').read_text().split('Password: ')[1].splitlines()[0]
passed=[]
def check(name, condition):
    if not condition: raise AssertionError(name)
    passed.append(name)
class Client:
    def __init__(self):
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf=''
    def req(self, action='transactions', method='GET', data=None, token=True):
        headers={'Content-Type':'application/json'}
        if token: headers['X-CSRF-Token']=self.csrf
        r=urllib.request.Request(BASE+'action='+action,data=json.dumps(data).encode() if data is not None else None,headers=headers,method=method)
        try: res=self.opener.open(r)
        except urllib.error.HTTPError as e: res=e
        raw=res.read()
        if 'text/csv' in res.headers.get('Content-Type',''): return res.status,raw
        return res.status,json.loads(raw)
    def login(self, role):
        _,s=self.req('session');self.csrf=s['csrf']
        code,s=self.req('login','POST',{'username':role,'password':password})
        check('login '+role,code==200);self.csrf=s['csrf']
a=Client()
check('unauthenticated denied',a.req()[0]==401)
_,s=a.req('session');a.csrf=s['csrf']
check('login CSRF enforced',a.req('login','POST',{'username':'admin','password':password},False)[0]==403)
a.login('admin')
record={'transaction_number':'TEST-001','applicant_name':'اسم تجريبي','transaction_type':'طلب تجريبي','status':'new','notes':'ملاحظات عربية'}
check('write CSRF enforced',a.req(method='POST',data=record,token=False)[0]==403)
check('empty fields rejected',a.req(method='POST',data={**record,'applicant_name':' '})[0]==422)
check('invalid status rejected',a.req(method='POST',data={**record,'status':'wrong'})[0]==422)
check('oversize field rejected',a.req(method='POST',data={**record,'transaction_number':'a'*31})[0]==422)
check('invalid field type rejected',a.req(method='POST',data={**record,'notes':[]})[0]==422)
code,result=a.req(method='POST',data=record);check('create record',code==201);rid=result['id']
check('duplicate rejected',a.req(method='POST',data=record)[0]==409)
code,result=a.req();check('list and persistence',code==200 and result['items'][0]['id']==rid)
check('stats counted',sum(result['stats'].values())==1)
check('search Arabic',a.req('transactions&q='+urllib.parse.quote('اسم'))[1]['total']==1)
check('literal wildcard search',a.req('transactions&q=%25')[1]['total']==0)
check('SQL injection search safe',a.req('transactions&q='+urllib.parse.quote("' OR 1=1 --"))[1]['total']==0)
check('status filter',a.req('transactions&status=completed')[1]['total']==0)
check('bad filter rejected',a.req('transactions&status=wrong')[0]==422)
updated={**record,'id':rid,'version':1,'status':'completed','notes':'=1+1','applicant_name':'<script>alert(1)</script>'}
check('update succeeds',a.req(method='PUT',data=updated)[0]==200)
check('stale update prevented',a.req(method='PUT',data=updated)[0]==409)
check('stale delete prevented',a.req(method='DELETE',data={'id':rid,'version':1})[0]==409)
code,result=a.req('export');check('CSV BOM',code==200 and result.startswith(b'\xef\xbb\xbf'))
rows=list(csv.reader(io.StringIO(result.decode('utf-8-sig'))));check('CSV formula neutralized',rows[1][4]=="'=1+1")
v=Client();v.login('viewer');check('viewer can read',v.req()[0]==200)
check('viewer cannot create',v.req(method='POST',data={**record,'transaction_number':'TEST-002'})[0]==403)
check('viewer cannot update',v.req(method='PUT',data={**updated,'version':2})[0]==403)
check('viewer cannot delete',v.req(method='DELETE',data={'id':rid,'version':2})[0]==403)
check('viewer cannot audit',v.req('audit')[0]==403)
e=Client();e.login('editor');check('editor cannot delete',e.req(method='DELETE',data={'id':rid,'version':2})[0]==403)
check('editor can create',e.req(method='POST',data={**record,'transaction_number':'TEST-002'})[0]==201)
check('editor can update',e.req(method='PUT',data={**updated,'version':2})[0]==200)
check('delete missing record',a.req(method='DELETE',data={'id':99999999,'version':1})[0]==404)
check('admin delete',a.req(method='DELETE',data={'id':rid,'version':3})[0]==200)
check('audit transactions recorded',len(a.req('audit')[1]['items'])==5)
for i in range(16):
    check('pagination seed '+str(i),a.req(method='POST',data={**record,'transaction_number':f'PAGE-{i:03}'})[0]==201)
code,result=a.req('transactions&page=2');check('pagination second page',result['page']==2 and len(result['items'])==2)
check('export follows filter',len(list(csv.reader(io.StringIO(a.req('export&q=PAGE-')[1].decode('utf-8-sig')))))==17)
check('unknown action',a.req('unknown')[0]==404)
check('method rejected',a.req(method='PATCH',data={})[0]==405)
check('logout',a.req('logout','POST',{})[0]==200)
check('session cleared',a.req()[0]==401)
lock=Client();_,s=lock.req('session');lock.csrf=s['csrf']
for _ in range(5): lock.req('login','POST',{'username':'viewer','password':'wrong'})
check('account lockout',lock.req('login','POST',{'username':'viewer','password':password})[0]==401)
report={'passed':len(passed),'tests':passed}
(root/'var/test-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(report,ensure_ascii=False,indent=2))
