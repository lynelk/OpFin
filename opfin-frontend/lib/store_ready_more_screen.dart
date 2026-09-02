import 'package:flutter/material.dart';
import 'package:opfin/account_delete_screen.dart';
import 'package:opfin/connected_financial_life_screen.dart';
import 'package:opfin/faq_screen.dart';
import 'package:opfin/peer_lending_screen.dart';
import 'package:opfin/profile_screen.dart';

class StoreReadyMoreMobileScreen extends StatelessWidget {
  const StoreReadyMoreMobileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text('More', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        const Text('Account controls and occasional tasks live here so everyday financial journeys stay focused.'),
        const SizedBox(height: 20),
        _MenuTile(
          icon: Icons.hub_outlined,
          title: 'Connected financial life',
          subtitle: 'Accounts, household, business and community context.',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ConnectedFinancialLifeScreen())),
        ),
        _MenuTile(
          icon: Icons.person_outline,
          title: 'Profile & security',
          subtitle: 'Review your account and identity information.',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProfileScreen())),
        ),
        _MenuTile(
          icon: Icons.handshake_outlined,
          title: 'Peer lending',
          subtitle: 'Review independently governed marketplace opportunities.',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PeerLendingScreen())),
        ),
        _MenuTile(
          icon: Icons.help_outline,
          title: 'Help & support',
          subtitle: 'Find answers and support guidance.',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FaqsScreen())),
        ),
        const SizedBox(height: 20),
        const Text('Privacy & account', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        const Card(
          child: Padding(
            padding: EdgeInsets.all(14),
            child: Text('Privacy policy: https://opfin-api-production.up.railway.app/privacy-policy'),
          ),
        ),
        _MenuTile(
          icon: Icons.delete_outline,
          title: 'Delete account',
          subtitle: 'Delete your OpFin account in-app and review regulated retention before confirming.',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AccountDeleteScreen())),
        ),
      ],
    );
  }
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({required this.icon, required this.title, required this.subtitle, required this.onTap});

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
        child: ListTile(
          leading: Icon(icon),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
          subtitle: Text(subtitle),
          trailing: const Icon(Icons.chevron_right),
          onTap: onTap,
        ),
      );
}
