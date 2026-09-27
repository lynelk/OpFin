import 'package:flutter/material.dart';
import 'package:opfin/services/data_usage_ledger.dart';
import 'package:opfin/services/offline_sync_service.dart';
import 'package:opfin/services/sponsored_data_policy.dart';

class DataUsageScreen extends StatefulWidget {
  const DataUsageScreen({super.key});

  @override
  State<DataUsageScreen> createState() => _DataUsageScreenState();
}

class _DataUsageScreenState extends State<DataUsageScreen> {
  Map<String, dynamic>? _summary;
  Map<String, dynamic>? _offline;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final results = await Future.wait([
      DataUsageLedger.currentMonthSummary(),
      OfflineSyncService.pendingSummary(),
    ]);
    if (mounted) {
      setState(() {
        _summary = results[0];
        _offline = results[1];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = _summary;
    final requestBytes = (summary?['request_bytes'] as num?)?.toInt() ?? 0;
    final responseBytes = (summary?['response_bytes'] as num?)?.toInt() ?? 0;
    final sponsored = (summary?['sponsored_bytes'] as num?)?.toInt() ?? 0;
    final normal = (summary?['non_sponsored_bytes'] as num?)?.toInt() ?? 0;
    final unknown = (summary?['unknown_bytes'] as num?)?.toInt() ?? 0;
    final requests = (summary?['request_count'] as num?)?.toInt() ?? 0;
    final failures = (summary?['failure_count'] as num?)?.toInt() ?? 0;
    final pendingEvents =
        (_offline?['event_count'] as num?)?.toInt() ?? 0;
    final pendingBytes =
        (_offline?['queued_bytes'] as num?)?.toInt() ?? 0;
    final lastSync = _offline?['last_successful_sync']?.toString();

    return Scaffold(
      appBar: AppBar(title: const Text('Data & storage')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'Your OpFin data use',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            const Text(
              'These figures measure OpFin application traffic on this device. Your mobile operator remains the authority on whether traffic was charged or sponsored.',
            ),
            const SizedBox(height: 18),
            _MetricCard(
              title: 'This month',
              value: _formatBytes(requestBytes + responseBytes),
              subtitle: '$requests requests · $failures failed',
            ),
            _MetricCard(
              title: 'Downloaded',
              value: _formatBytes(responseBytes),
              subtitle: 'Responses received by OpFin',
            ),
            _MetricCard(
              title: 'Uploaded',
              value: _formatBytes(requestBytes),
              subtitle: 'Requests sent by OpFin',
            ),
            _MetricCard(
              title: 'Pending offline sync',
              value: _formatBytes(pendingBytes),
              subtitle: pendingEvents == 0
                  ? 'Nothing waiting to sync'
                  : '$pendingEvents saved action${pendingEvents == 1 ? '' : 's'} waiting to sync',
            ),
            if (lastSync != null && lastSync.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text('Last successful offline sync: $lastSync'),
              ),
            const SizedBox(height: 10),
            const Text(
              'Sponsorship classification',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            _UsageRow('Operator-confirmed sponsored', sponsored),
            _UsageRow('Outside sponsored boundary', normal),
            _UsageRow('Billing treatment not confirmed', unknown),
            const SizedBox(height: 14),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  SponsoredDataPolicy.carrierConfirmed
                      ? 'This release has an operator-confirmed sponsored-data configuration. External websites, WhatsApp, mapping apps and app-store downloads can still use normal data.'
                      : 'OpFin has not marked this release as operator-confirmed sponsored. Traffic to the approved OpFin API is therefore shown as “billing treatment not confirmed” until the carrier agreement is activated.',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _formatBytes(int bytes) {
    if (bytes < 1024) return '$bytes B';
    final kb = bytes / 1024;
    if (kb < 1024) return '${kb.toStringAsFixed(kb >= 100 ? 0 : 1)} KB';
    final mb = kb / 1024;
    return '${mb.toStringAsFixed(mb >= 100 ? 0 : 1)} MB';
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.title,
    required this.value,
    required this.subtitle,
  });

  final String title;
  final String value;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(subtitle),
                  ],
                ),
              ),
              Text(
                value,
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              ),
            ],
          ),
        ),
      );
}

class _UsageRow extends StatelessWidget {
  const _UsageRow(this.label, this.bytes);
  final String label;
  final int bytes;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        trailing: Text(
          _DataUsageScreenState._formatBytes(bytes),
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
      );
}
