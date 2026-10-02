from pathlib import Path

p = Path('apps/api/tests/Feature/PayrollDeductionWorkflowTest.php')
s = p.read_text()
old = "            'currency' => 'UGX', 'status' => 'contracting',"
assert s.count(old) == 1
s = s.replace(old, old + "\n            'contract_snapshot' => ['test_fixture' => true],\n            'contract_hash' => hash('sha256', json_encode(['test_fixture' => true], JSON_THROW_ON_ERROR)),")
p.write_text(s)

p = Path('apps/api/tests/Feature/EssentialsDurableCollectionsTest.php')
s = p.read_text()
old = "$this->assertContains('essentials_collection_reconciliation', $result['active_obligations']);"
assert s.count(old) == 1
s = s.replace(old, "$this->assertContains('essentials_collection_reconciliation', array_column($result['active_obligations'], 'code'));\n        $this->assertDatabaseMissing('support_cases', ['customer_id' => $this->customer->id, 'category' => 'account_deletion']);")
p.write_text(s)

# An invalid credential attempt must not reuse an earlier account's flashed details.
p = Path('apps/api/app/Http/Controllers/Api/AuthController.php')
s = p.read_text()
old = "        if (! $user || ! Hash::check($credential, $user->password)) {\n            return back()->with('error', 'User details provided are invalid.');"
assert old in s
s = s.replace(old, "        if (! $user || ! Hash::check($credential, $user->password)) {\n            $request->session()->forget('deletion_blockers');\n            return back()->with('error', 'User details provided are invalid.');")
p.write_text(s)
print('Known targeted-test fixture issues corrected; production date validation is unchanged.')
