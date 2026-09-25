import importlib.util
import pathlib
import unittest

path = pathlib.Path(__file__).with_name('server.py')
spec = importlib.util.spec_from_file_location('opfin_mcp', path)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class Stub:
    def __init__(self): self.calls=[]
    def get(self,path,query=None):
        self.calls.append((path,query))
        if path.endswith('agent-tools'):
            return {'tools':[{'name':name,'description':'Read only','inputSchema':{'type':'object','properties':{key:{} for key in args}},'annotations':{'readOnlyHint':True}} for name,args in m.TOOL_ARGUMENTS.items()]}
        return {'source':'documentation','payload':'<script>untrusted()</script>'}

class BridgeTests(unittest.TestCase):
    def setUp(self):
        self.client=Stub();self.bridge=m.Bridge(self.client)
        self.bridge.handle({'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':m.PROTOCOL}})
    def test_initialise(self):
        out=self.bridge.handle({'jsonrpc':'2.0','id':1,'method':'initialize','params':{'protocolVersion':'old'}})
        self.assertEqual(out['result']['protocolVersion'],m.PROTOCOL)
    def test_no_tools_before_initialisation(self):
        self.assertIn('error',m.Bridge(self.client).handle({'jsonrpc':'2.0','id':1,'method':'tools/list'}))
    def test_tool_list(self):
        out=self.bridge.handle({'jsonrpc':'2.0','id':2,'method':'tools/list'})
        self.assertEqual(len(out['result']['tools']),4)
    def test_search_is_only_get_documentation(self):
        self.bridge.call('opfin_search_api',{'query':'repayment'})
        self.assertEqual(self.client.calls[-1],('/api/developer/catalogue',{'q':'repayment','page':1,'limit':20}))
    def test_description_rejects_url(self):
        with self.assertRaises(m.SafeError):self.bridge.call('opfin_describe_operation',{'operation_id':'https://malicious.invalid'})
    def test_guide_rejects_traversal(self):
        with self.assertRaises(m.SafeError):self.bridge.call('opfin_read_guide',{'guide_id':'../../.env'})
    def test_unknown_execution_tool(self):
        with self.assertRaises(m.SafeError):self.bridge.call('execute_payment',{'amount':1})
    def test_extra_arguments_rejected(self):
        with self.assertRaises(m.SafeError):self.bridge.call('opfin_search_api',{'query':'x','url':'x'})
    def test_boolean_page_rejected(self):
        with self.assertRaises(m.SafeError):self.bridge.call('opfin_search_api',{'query':'x','page':True})
    def test_page_limit(self):
        with self.assertRaises(m.SafeError):self.bridge.call('opfin_search_api',{'query':'x','page':1001})
    def test_query_limit(self):
        with self.assertRaises(m.SafeError):self.bridge.call('opfin_search_guides',{'query':'x'*161})
    def test_notifications_do_not_execute(self):
        out=self.bridge.handle({'jsonrpc':'2.0','method':'tools/call','params':{'name':'opfin_search_api','arguments':{'query':'x'}}})
        self.assertIsNone(out);self.assertEqual(self.client.calls,[])
    def test_structured_output_treats_text_as_data(self):
        out=self.bridge.handle({'jsonrpc':'2.0','id':3,'method':'tools/call','params':{'name':'opfin_read_guide','arguments':{'guide_id':'start'}}})
        self.assertFalse(out['result']['isError']);self.assertEqual(out['result']['structuredContent']['payload'],'<script>untrusted()</script>')
    def test_tool_errors_are_explicit(self):
        out=self.bridge.handle({'jsonrpc':'2.0','id':3,'method':'tools/call','params':{'name':'execute_payment','arguments':{}}})
        self.assertTrue(out['result']['isError'])
    def test_unknown_rpc_method(self):
        self.assertEqual(self.bridge.handle({'jsonrpc':'2.0','id':3,'method':'execute'})['error']['code'],-32601)
    def test_invalid_rpc(self):
        self.assertEqual(self.bridge.handle([])['error']['code'],-32600)
    def test_insecure_remote_origin(self):
        with self.assertRaises(m.SafeError):m.ApiClient('http://example.test','synthetic')
    def test_local_http_requires_explicit_setting(self):
        with self.assertRaises(m.SafeError):m.ApiClient('http://127.0.0.1:8000','synthetic')
        self.assertEqual(m.ApiClient('http://127.0.0.1:8000','synthetic',True).origin,'http://127.0.0.1:8000')
    def test_userinfo_and_path_rejected(self):
        for origin in ('https://name:pass@example.test','https://example.test/api','https://example.test?token=secret'):
            with self.assertRaises(m.SafeError):m.ApiClient(origin,'synthetic')
    def test_tokens_with_newlines_rejected(self):
        with self.assertRaises(m.SafeError):m.ApiClient('https://example.test','synthetic\nheader:bad')
    def test_redirects_rejected_without_following(self):
        with self.assertRaises(m.SafeError):m.NoRedirect().redirect_request(None,None,302,'',{},'https://bad.invalid')
    def test_fixed_http_path_allowlist(self):
        client=m.ApiClient('https://example.test','synthetic')
        with self.assertRaises(m.SafeError):client.get('/api/loans/1/repay')
    def test_remote_write_tool_not_exposed(self):
        class Bad(Stub):
            def get(self,*args,**kwargs):return {'tools':[{'name':'execute_payment'}]*4}
        with self.assertRaises(m.SafeError):m.Bridge(Bad()).tools()

if __name__=='__main__':unittest.main()
