import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:opfin/brand/brand_colors.dart';

/// A provider-independent, intentionally ephemeral money planning exercise.
///
/// No network, auth, analytics, payment or local persistence is performed here.
/// Figures entered here are never authoritative account or cash balances.
class MoneyCheckProjection {
  const MoneyCheckProjection._();

  static int remaining({
    required int available,
    required int bills,
    required int essentials,
  }) {
    if (available < 0 || bills < 0 || essentials < 0) {
      throw ArgumentError('Money check values must be non-negative.');
    }
    return available - bills - essentials;
  }
}

class OfflineMoneyCheckScreen extends StatefulWidget {
  const OfflineMoneyCheckScreen({super.key});

  @override
  State<OfflineMoneyCheckScreen> createState() =>
      _OfflineMoneyCheckScreenState();
}

class _OfflineMoneyCheckScreenState extends State<OfflineMoneyCheckScreen> {
  final _available = TextEditingController();
  final _bills = TextEditingController();
  final _essentials = TextEditingController();
  final _money = NumberFormat('#,##0', 'en_US');

  @override
  void dispose() {
    _available.dispose();
    _bills.dispose();
    _essentials.dispose();
    super.dispose();
  }

  int? _read(TextEditingController controller) {
    final raw = controller.text.trim();
    if (raw.isEmpty) return null;
    // Input is restricted to 12 digits for safe, comprehensible UGX amounts.
    if (!RegExp(r'^\d{1,12}$').hasMatch(raw)) return null;
    return int.tryParse(raw);
  }

  String _ugx(int value) =>
      'UGX ${value < 0 ? '-' : ''}${_money.format(value.abs())}';

  Widget _amountField({
    required String title,
    required String example,
    required TextEditingController controller,
  }) =>
      TextField(
        controller: controller,
        keyboardType: TextInputType.number,
        inputFormatters: [
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(12),
        ],
        onChanged: (_) => setState(() {}),
        decoration: InputDecoration(
          labelText: title,
          hintText: example,
          prefixText: 'UGX ',
          border: const OutlineInputBorder(),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final available = _read(_available);
    final bills = _read(_bills);
    final essentials = _read(_essentials);
    final ready = available != null && bills != null && essentials != null;
    final difference = ready
        ? MoneyCheckProjection.remaining(
            available: available,
            bills: bills,
            essentials: essentials,
          )
        : null;

    return Scaffold(
      appBar: AppBar(title: const Text('Quick money check')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Icon(Icons.offline_bolt_outlined,
                color: OpFinColors.indigo, size: 38),
            const SizedBox(height: 12),
            const Text(
              'Plan your next move, even offline.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            const Text(
              'Enter your own estimates for the period until your next income. '
              'No login, mobile data, financial partner or bank connection is needed.',
              textAlign: TextAlign.center,
              style: TextStyle(color: OpFinColors.muted, height: 1.4),
            ),
            const SizedBox(height: 24),
            _amountField(
              title: 'Money available now',
              example: 'e.g. 350000',
              controller: _available,
            ),
            const SizedBox(height: 14),
            _amountField(
              title: 'Bills due before next income',
              example: 'e.g. 150000',
              controller: _bills,
            ),
            const SizedBox(height: 14),
            _amountField(
              title: 'Everyday essentials before next income',
              example: 'e.g. 120000',
              controller: _essentials,
            ),
            const SizedBox(height: 20),
            if (difference != null)
              Card(
                color: OpFinColors.periwinkle,
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Estimated planning difference',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _ugx(difference),
                        style: const TextStyle(
                          fontSize: 26,
                          color: OpFinColors.ink,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        difference < 0
                            ? 'Your estimated bills and essentials exceed the money you entered. '
                              'Review upcoming commitments and consider what can be adjusted.'
                            : difference == 0
                                ? 'Your estimated money exactly covers the items entered. '
                                  'There is no allowance for anything missing.'
                                : 'This is what remains after the bills and essentials you entered. '
                                  'Check whether other costs are still missing.',
                        style: const TextStyle(
                          color: OpFinColors.ink,
                          height: 1.45,
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(18),
                  child: Text(
                    'Enter all three amounts to see a simple estimate. '
                    'Use 0 where an amount is genuinely zero.',
                  ),
                ),
              ),
            const SizedBox(height: 14),
            const Text(
              'This is a temporary, local calculation. It is not a verified balance, '
              'a guaranteed safe-to-spend figure, a credit offer or financial advice. '
              'Figures are not saved and will be cleared when you leave this screen.',
              style: TextStyle(color: OpFinColors.muted, height: 1.4),
            ),
          ],
        ),
      ),
    );
  }
}
