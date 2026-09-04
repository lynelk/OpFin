import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/connected_financial_life_screen.dart';
import 'package:opfin/faq_screen.dart';
import 'package:opfin/loan_applications_screen.dart';
import 'package:opfin/peer_lending_screen.dart';
import 'package:opfin/products_screen.dart';
import 'package:opfin/profile_screen.dart';
import 'package:opfin/services/user_session.dart';

bool get appStorePeerLendingEnabled => !Platform.isIOS || const bool.fromEnvironment('OPFIN_APP_STORE_PEER_LENDING_ENABLED', defaultValue: false);

class BorrowMobileScreen extends StatelessWidget {
  const BorrowMobileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text('Borrow', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        const Text('Choose a funding route without learning internal lender or product configuration.'),
        const SizedBox(height: 20),
        _ActionCard(
          icon: Icons.account_balance_wallet_outlined,
          title: 'Check credit options',
          description: 'Tell OpFin what you need, then review a formal offer before accepting anything.',
          action: 'Start request',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProductsScreen())),
        ),
        if (appStorePeerLendingEnabled)
          _ActionCard(
            icon: Icons.handshake_outlined,
            title: 'Borrow from investors',
            description: 'Ask verified investors to fund an independently reviewed marketplace request.',
            action: 'Open marketplace',
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PeerLendingScreen())),
          ),
        _ActionCard(
          icon: Icons.receipt_long_outlined,
          title: 'My loan applications',
          description: 'Track submitted credit requests without starting another application.',
          action: 'View requests',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoanApplicationsScreen())),
        ),
      ],
    );
  }
}

class SaveMobileScreen extends StatefulWidget {
  const SaveMobileScreen({super.key});

  @override
  State<SaveMobileScreen> createState() => _SaveMobileScreenState();
}

class _SaveMobileScreenState extends State<SaveMobileScreen> {
  late Future<List<Map<String, dynamic>>> _goals;
  final _formatter = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _goals = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required.');
    final response = await http.get(Uri.parse('$apiUrl/savings/goals'), headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'});
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to load savings.');
    }
    final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    return (data['goals'] as List? ?? const []).whereType<Map>().map((item) => item.cast<String, dynamic>()).toList();
  }

  int _money(dynamic value) => value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  Future<void> _refresh() async {
    setState(() => _goals = _load());
    await _goals;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _goals,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        if (snapshot.hasError) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [Text(snapshot.error.toString(), textAlign: TextAlign.center), const SizedBox(height: 12), FilledButton(onPressed: _refresh, child: const Text('Try again'))])));
        final goals = snapshot.data ?? const <Map<String, dynamic>>[];
        final confirmed = goals.fold<int>(0, (sum, goal) => sum + _money(goal['confirmed_balance_minor']));
        final available = goals.fold<int>(0, (sum, goal) => sum + _money(goal['available_balance_minor']));
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(padding: const EdgeInsets.all(20), children: [
            const Text('Save', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text('Build resilience around clear goals and partner-confirmed savings positions.'),
            const SizedBox(height: 20),
            Row(children: [Expanded(child: _StatCard(label: 'Confirmed', value: 'UGX ${_formatter.format(confirmed)}')), const SizedBox(width: 10), Expanded(child: _StatCard(label: 'Available', value: 'UGX ${_formatter.format(available)}'))]),
            const SizedBox(height: 16),
            if (goals.isEmpty)
              const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('No savings goals yet. Savings products remain subject to an activated regulated partner.')))
            else
              ...goals.map((goal) => Card(
                child: ListTile(
                  title: Text(goal['name']?.toString() ?? 'Savings goal', style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text('Confirmed UGX ${_formatter.format(_money(goal['confirmed_balance_minor']))} · Available UGX ${_formatter.format(_money(goal['available_balance_minor']))}'),
                  trailing: Text(goal['status']?.toString() ?? ''),
                ),
              )),
            const SizedBox(height: 16),
            const Text('Only partner-confirmed money is shown as saved. Pending collections are deliberately excluded.'),
          ]),
        );
      },
    );
  }
}

class GrowMobileScreen extends StatefulWidget {
  const GrowMobileScreen({super.key});

  @override
  State<GrowMobileScreen> createState() => _GrowMobileScreenState();
}

class _GrowMobileScreenState extends State<GrowMobileScreen> {
  late Future<Map<String, dynamic>> _workspace;

  @override
  void initState() {
    super.initState();
    _workspace = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final token = await UserSession.getAccessToken();
    if (token == null || token.isEmpty) throw Exception('Secure session is required.');
    final response = await http.get(Uri.parse('$apiUrl/investments/workspace'), headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'});
    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Unable to load investment workspace.');
    }
    return (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _workspace,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        final data = snapshot.data ?? <String, dynamic>{};
        final products = data['products'] as List? ?? const [];
        final orders = data['orders'] as List? ?? const [];
        final suitabilityReady = data['suitability'] != null;
        return ListView(padding: const EdgeInsets.all(20), children: [
          const Text('Grow', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text(appStorePeerLendingEnabled
              ? 'Choose between provider investments and peer lending, with each risk model kept clear.'
              : 'Use regulated provider investment products once the licensed provider and custody arrangement are activated.'),
          const SizedBox(height: 20),
          _ActionCard(
            icon: Icons.trending_up,
            title: 'Provider investments',
            description: snapshot.hasError
                ? 'Provider investment workspace is not available right now.'
                : '${products.length} activated product(s), ${orders.length} order(s). ${suitabilityReady ? 'Suitability profile ready.' : 'Complete suitability before investing.'}',
            action: 'Investment status',
            onTap: () => _showProviderInvestmentNotice(context, suitabilityReady),
          ),
          if (appStorePeerLendingEnabled)
            _ActionCard(
              icon: Icons.handshake_outlined,
              title: 'Peer lending',
              description: 'Lend directly through independently reviewed marketplace opportunities with risk and expected return shown first.',
              action: 'Open marketplace',
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PeerLendingScreen())),
            ),
          const Card(child: Padding(padding: EdgeInsets.all(16), child: Text('Expected returns are not guaranteed. Risk, provider responsibility and settlement state remain visible before and after you commit money.'))),
        ]);
      },
    );
  }

  void _showProviderInvestmentNotice(BuildContext context, bool suitabilityReady) {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Provider investments'),
        content: Text(suitabilityReady
            ? 'Your suitability profile is ready. Product order activation remains subject to the licensed investment and custody partner.'
            : 'Complete suitability before placing provider investment orders. Live ordering remains subject to the licensed investment and custody partner.'),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close'))],
      ),
    );
  }
}

class MoreMobileScreen extends StatelessWidget {
  const MoreMobileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(padding: const EdgeInsets.all(20), children: [
      const Text('More', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
      const SizedBox(height: 8),
      const Text('Occasional tasks live here so everyday financial journeys stay focused.'),
      const SizedBox(height: 20),
      _MenuTile(icon: Icons.hub_outlined, title: 'Connected financial life', subtitle: 'Accounts, household, business and community context.', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ConnectedFinancialLifeScreen()))),
      _MenuTile(icon: Icons.person_outline, title: 'Profile & security', subtitle: 'Review your account and identity information.', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProfileScreen()))),
      _MenuTile(icon: Icons.help_outline, title: 'Help & support', subtitle: 'Find answers and support guidance.', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FaqsScreen()))),
      if (appStorePeerLendingEnabled)
        _MenuTile(icon: Icons.handshake_outlined, title: 'Peer lending', subtitle: 'Borrow from investors or fund reviewed requests.', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PeerLendingScreen()))),
    ]);
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({required this.icon, required this.title, required this.description, required this.action, required this.onTap});
  final IconData icon;
  final String title;
  final String description;
  final String action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 14),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 30),
        const SizedBox(height: 10),
        Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        const SizedBox(height: 6),
        Text(description),
        const SizedBox(height: 12),
        FilledButton(onPressed: onTap, child: Text(action)),
      ]),
    ),
  );
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(children: [Text(value, style: const TextStyle(fontWeight: FontWeight.bold)), const SizedBox(height: 4), Text(label)])));
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({required this.icon, required this.title, required this.subtitle, required this.onTap});
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(child: ListTile(leading: Icon(icon), title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)), subtitle: Text(subtitle), trailing: const Icon(Icons.chevron_right), onTap: onTap));
}
