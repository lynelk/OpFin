import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:opfin/constants.dart';
import 'package:opfin/services/user_session.dart';

class ConnectedFinancialLifeScreen extends StatefulWidget {
  const ConnectedFinancialLifeScreen({super.key});

  @override
  State<ConnectedFinancialLifeScreen> createState() => _ConnectedFinancialLifeScreenState();
}

class _ConnectedFinancialLifeScreenState extends State<ConnectedFinancialLifeScreen> {
  late Future<Map<String, dynamic>> _workspace;

  @override
  void initState() {
    super.initState();
    _workspace = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required.');
    final response = await http.get(
      Uri.parse('$apiUrl/long-range/overview'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );
    if (response.statusCode != 200) throw Exception('Unable to load connected financial life.');
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  int _count(Map<String, dynamic> data, String key) {
    final value = data[key];
    return value is List ? value.length : 0;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _workspace,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.cloud_off, size: 42),
                const SizedBox(height: 12),
                Text(snapshot.error.toString(), textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(onPressed: () => setState(() => _workspace = _load()), child: const Text('Try again')),
              ]),
            ),
          );
        }
        final data = snapshot.data ?? <String, dynamic>{};
        final household = data['household'];
        final business = data['microbusiness'];
        return RefreshIndicator(
          onRefresh: () async => setState(() => _workspace = _load()),
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              const Text('Connected financial life', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('One identity for your accounts, household, business and community context. Provider evidence remains separate from information you enter yourself.'),
              const SizedBox(height: 20),
              _section('Connected accounts', '${_count(data, 'linked_accounts')} account(s)', Icons.account_balance_wallet_outlined),
              _section('Household', household == null ? 'Not added yet' : 'Household context available', Icons.home_outlined),
              _section('Microbusiness', business == null ? 'Not added yet' : 'Business context available', Icons.storefront_outlined),
              _section('Community finance', '${_count(data, 'community_memberships')} membership(s)', Icons.groups_outlined),
              _section('Asset finance', '${_count(data, 'asset_finance')} request(s)', Icons.devices_other_outlined),
              _section('Participatory finance', '${_count(data, 'participatory_listings')} request(s), ${_count(data, 'participatory_commitments')} commitment(s)', Icons.handshake_outlined),
              _section('Referrals & rewards', '${_count(data, 'referrals')} referral event(s)', Icons.redeem_outlined),
              const SizedBox(height: 16),
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('Money-changing actions are intentionally completed only after fresh step-up authentication and governed CPay provider finality. USSD and assisted channels do not bypass those controls.'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _section(String title, String subtitle, IconData icon) {
    return Card(
      child: ListTile(
        leading: Icon(icon),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(subtitle),
      ),
    );
  }
}
