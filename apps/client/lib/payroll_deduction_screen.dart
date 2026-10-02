import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/payroll_deduction_api.dart';

const bool payrollDeductionEnabled = bool.fromEnvironment(
  'OPFIN_PAYROLL_DEDUCTION_ENABLED',
  defaultValue: false,
);

class PayrollDeductionScreen extends StatefulWidget {
  const PayrollDeductionScreen({super.key});

  @override
  State<PayrollDeductionScreen> createState() => _PayrollDeductionScreenState();
}

class _PayrollDeductionScreenState extends State<PayrollDeductionScreen> {
  late Future<_PayrollSnapshot> _snapshot;
  final _money = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _snapshot = _load();
  }

  Future<_PayrollSnapshot> _load() async {
    final values = await Future.wait([
      PayrollDeductionApi.capability(),
      PayrollDeductionApi.cases(),
      PayrollDeductionApi.financingApplications(),
    ]);
    return _PayrollSnapshot(
      provider: values[0] as Map<String, dynamic>,
      cases: values[1] as List<Map<String, dynamic>>,
      applications: values[2] as List<Map<String, dynamic>>,
    );
  }

  Future<void> _refresh() async {
    setState(() => _snapshot = _load());
    await _snapshot;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Payroll deduction')),
      body: FutureBuilder<_PayrollSnapshot>(
        future: _snapshot,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(snapshot.error.toString(),
                        textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton(
                        onPressed: _refresh, child: const Text('Try again')),
                  ],
                ),
              ),
            );
          }

          final data = snapshot.data!;
          final machineActive =
              data.provider['machine_interface_active'] == true;
          final eligible = data.applications.where((application) {
            final product =
                (application['product'] as Map?)?.cast<String, dynamic>() ??
                    <String, dynamic>{};
            return product['family'] == 'salary_finance' &&
                !data.cases.any((item) =>
                    item['financing_application_id'] == application['id']);
          }).toList();

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(
                  'Salary-linked repayment',
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Track affordability, reservation, vote approval, payroll submission and payment reconciliation in one place.',
                ),
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(machineActive
                            ? Icons.verified_outlined
                            : Icons.sync_problem_outlined),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            machineActive
                                ? 'The approved payroll provider connection is active.'
                                : 'The payroll workflow is available for controlled processing, but the live PDMS machine connection is not active. Provider evidence is handled by authorised operations staff.',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (payrollDeductionEnabled && eligible.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: () => _startCase(eligible),
                    icon: const Icon(Icons.add),
                    label: const Text('Start payroll deduction'),
                  ),
                ],
                const SizedBox(height: 20),
                Text('My payroll cases',
                    style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 10),
                if (data.cases.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'No payroll deduction case is active. Eligible salary-linked finance remains subject to affordability, provider processing and vote approval.',
                      ),
                    ),
                  )
                else
                  ...data.cases.map(_caseCard),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _caseCard(Map<String, dynamic> item) {
    final status = item['status']?.toString() ?? 'unknown';
    final affordable = _asInt(item['affordable_amount_minor']);
    final requested = _asInt(item['requested_deduction_minor']);
    final canReserve =
        {'affordable', 'amendment_required'}.contains(status) && affordable > 0;
    final canCancel = const {
      'affordability_pending',
      'buyoff_quote_required',
      'unaffordable',
      'affordable',
      'reservation_pending',
      'reservation_failed',
      'reserved',
      'vote_approval_pending',
      'vote_rejected',
      'amendment_required',
    }.contains(status);

    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.account_balance_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    item['vote_name']?.toString().isNotEmpty == true
                        ? item['vote_name'].toString()
                        : 'Government payroll deduction',
                    style: const TextStyle(
                        fontSize: 17, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(_statusLabel(status),
                style: const TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 4),
            Text(_statusDescription(status)),
            if (affordable > 0) ...[
              const SizedBox(height: 10),
              Text(
                  'Verified affordable deduction: UGX ${_money.format(affordable)}'),
            ],
            if (requested > 0)
              Text('Requested deduction: UGX ${_money.format(requested)}'),
            if ((item['rejection_reason']?.toString() ?? '').isNotEmpty) ...[
              const SizedBox(height: 10),
              Text('Action needed: ${item['rejection_reason']}'),
            ],
            if (canReserve || canCancel) ...[
              const SizedBox(height: 14),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  if (canReserve)
                    FilledButton(
                      onPressed: () => _requestReservation(item),
                      child: const Text('Agree & request reservation'),
                    ),
                  if (canCancel)
                    OutlinedButton(
                      onPressed: () => _cancel(item),
                      child: const Text('Cancel process'),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _startCase(List<Map<String, dynamic>> applications) async {
    var selected = applications.first;
    final voteCode = TextEditingController();
    final voteName = TextEditingController();
    final employmentReference = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Start payroll deduction'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<Map<String, dynamic>>(
                  value: selected,
                  decoration: const InputDecoration(
                      labelText: 'Salary-linked finance application'),
                  items: applications.map((application) {
                    final product = (application['product'] as Map?)
                            ?.cast<String, dynamic>() ??
                        <String, dynamic>{};
                    final label = product['name']?.toString() ??
                        application['reference']?.toString() ??
                        'Finance application';
                    return DropdownMenuItem(
                        value: application, child: Text(label));
                  }).toList(),
                  onChanged: (value) {
                    if (value != null) setDialogState(() => selected = value);
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: voteName,
                  decoration: const InputDecoration(
                      labelText: 'Government vote or employer name'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: voteCode,
                  decoration:
                      const InputDecoration(labelText: 'Vote code (optional)'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: employmentReference,
                  decoration: const InputDecoration(
                    labelText: 'Payroll or employment reference (optional)',
                    helperText:
                        'OpFin stores only a protected reference fingerprint.',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Not now')),
            FilledButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Continue')),
          ],
        ),
      ),
    );

    if (confirmed != true || !mounted) return;
    try {
      await PayrollDeductionApi.startCase(
        financingApplicationId: _asInt(selected['id']),
        voteCode: voteCode.text,
        voteName: voteName.text,
        employmentReference: employmentReference.text,
      );
      await _refresh();
    } catch (error) {
      if (mounted) _error(error);
    }
  }

  Future<void> _requestReservation(Map<String, dynamic> item) async {
    final affordable = _asInt(item['affordable_amount_minor']);
    final amount = TextEditingController(text: affordable.toString());
    final agreement = TextEditingController();
    var authorised = false;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Payroll undertaking'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: amount,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Monthly deduction (UGX)',
                    helperText:
                        'Verified maximum: UGX ${_money.format(affordable)}',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: agreement,
                  decoration: const InputDecoration(
                      labelText: 'Agreement reference (optional)'),
                ),
                const SizedBox(height: 12),
                CheckboxListTile(
                  value: authorised,
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  onChanged: (value) =>
                      setDialogState(() => authorised = value == true),
                  title: const Text(
                    'I authorise payroll deduction for repayment under the agreed salary-linked finance terms and understand that final activation requires payroll approval.',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel')),
            FilledButton(
              onPressed:
                  authorised ? () => Navigator.pop(dialogContext, true) : null,
              child: const Text('Authorise'),
            ),
          ],
        ),
      ),
    );

    if (confirmed != true || !mounted) return;
    final requested = int.tryParse(amount.text.replaceAll(',', '').trim());
    if (requested == null || requested <= 0 || requested > affordable) {
      _error('Enter a deduction amount within the verified affordable limit.');
      return;
    }

    try {
      await PayrollDeductionApi.authoriseReservation(
        caseId: _asInt(item['id']),
        requestedDeductionMinor: requested,
        agreementReference: agreement.text,
      );
      await _refresh();
    } catch (error) {
      if (mounted) _error(error);
    }
  }

  Future<void> _cancel(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cancel payroll deduction?'),
        content: const Text(
            'This requests cancellation. Any submitted or confirmed reservation stays open until the provider release is verified. Financial and audit records are retained where required.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Keep process')),
          FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Cancel process')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    try {
      await PayrollDeductionApi.cancel(_asInt(item['id']));
      await _refresh();
    } catch (error) {
      if (mounted) _error(error);
    }
  }

  void _error(Object error) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
    );
  }

  int _asInt(dynamic value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;

  String _statusLabel(String status) => switch (status) {
        'affordability_pending' => 'Affordability check pending',
        'buyoff_quote_required' => 'Buy-off quotation required',
        'unaffordable' => 'Not affordable at present',
        'affordable' => 'Affordability confirmed',
        'reservation_pending' => 'Reservation pending',
        'reservation_failed' => 'Reservation needs attention',
        'reserved' => 'Payroll reservation confirmed',
        'vote_approval_pending' => 'Awaiting vote approval',
        'deduction_approved' => 'Payroll deduction approved',
        'vote_rejected' => 'Payroll deduction not approved',
        'payroll_submitted' => 'Submitted for payroll run',
        'reconciliation_pending' => 'Payment reconciliation pending',
        'amendment_required' => 'Deduction amendment required',
        'reconciliation_exception' => 'Reconciliation review required',
        'reconciled' => 'Payment reconciled',
        'expired' => 'Reservation expired',
        'cancellation_pending' => 'Awaiting payroll reservation release',
        'cancelled' => 'Process cancelled',
        _ => status.replaceAll('_', ' '),
      };

  String _statusDescription(String status) => switch (status) {
        'cancellation_pending' =>
          'OpFin is waiting for evidenced provider release. Your servicing access remains available and this reservation is not yet closed.',
        'affordability_pending' =>
          'OpFin is waiting for verified payroll affordability evidence.',
        'buyoff_quote_required' =>
          'A buy-off quotation is needed before affordability can be confirmed.',
        'unaffordable' =>
          'The payroll affordability check did not support the requested deduction.',
        'affordable' =>
          'You may authorise a deduction up to the verified amount.',
        'reservation_pending' =>
          'Your undertaking has been recorded and payroll reservation confirmation is pending.',
        'reservation_failed' =>
          'The reservation was not confirmed. Authorised operations staff will review the reason.',
        'reserved' =>
          'The deduction amount is reserved while key facts are submitted for approval.',
        'vote_approval_pending' =>
          'The responsible government vote is reviewing the submitted key facts.',
        'deduction_approved' =>
          'The deduction is approved for inclusion in the payroll submission cycle.',
        'vote_rejected' =>
          'Approval was not granted. Review the recorded reason and reservation status before another request.',
        'payroll_submitted' =>
          'The approved deduction has been included in the payroll submission cycle.',
        'reconciliation_pending' =>
          'Payroll feedback was successful and OpFin is matching the recovered payment.',
        'amendment_required' =>
          'Payroll feedback requires the deduction request to be corrected or resubmitted.',
        'reconciliation_exception' =>
          'The expected and recovered amounts do not yet match.',
        'reconciled' =>
          'The recovered payroll amount matches the expected deduction for the recorded period.',
        'expired' =>
          'The reservation window ended before the process completed.',
        'cancelled' => 'This payroll deduction process is no longer active.',
        _ => 'Payroll deduction status is being tracked by OpFin.',
      };
}

class _PayrollSnapshot {
  const _PayrollSnapshot({
    required this.provider,
    required this.cases,
    required this.applications,
  });

  final Map<String, dynamic> provider;
  final List<Map<String, dynamic>> cases;
  final List<Map<String, dynamic>> applications;
}
