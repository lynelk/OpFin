import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/services/inclusive_finance_api.dart';

class InclusiveFinanceScreen extends StatefulWidget {
  const InclusiveFinanceScreen({super.key});

  @override
  State<InclusiveFinanceScreen> createState() => _InclusiveFinanceScreenState();
}

class _InclusiveFinanceScreenState extends State<InclusiveFinanceScreen> {
  late Future<Map<String, dynamic>> _state;
  final _money = NumberFormat('#,##0', 'en_US');

  @override
  void initState() {
    super.initState();
    _state = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final values = await Future.wait([
      InclusiveFinanceApi.profile(),
      InclusiveFinanceApi.capability(),
      InclusiveFinanceApi.fairTreatment(),
      InclusiveFinanceApi.programmes(),
      InclusiveFinanceApi.supportInstruments(),
    ]);
    final capability = values[1];
    final guidance = (capability['guidance'] as List?) ?? const [];
    if (guidance.isNotEmpty) {
      try {
        await InclusiveFinanceApi.recordCapabilityEvent(
          eventType: 'guidance_shown',
          context: {'guidance_count': guidance.length},
        );
      } catch (_) {
        // Evidence capture must never block the customer's financial journey.
      }
    }
    return {
      'profile': values[0],
      'capability': capability,
      'fair_treatment': values[2],
      'programmes': values[3],
      'support_instruments': values[4],
    };
  }

  Future<void> _reload() async {
    setState(() => _state = _load());
    await _state;
  }

  String _moneyText(dynamic value) {
    final number = value is num ? value.toInt() : int.tryParse('$value') ?? 0;
    return 'UGX ${_money.format(number)}';
  }

  String _titleCase(String value) => value
      .split('_')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');

  Future<void> _setMeasurementConsent(bool enabled) async {
    await InclusiveFinanceApi.updateProfile(
      programmeMeasurementConsent: enabled,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          enabled
              ? 'Programme measurement enabled. Voluntary inclusion details remain outside credit decisioning.'
              : 'Programme measurement disabled and stored voluntary inclusion attributes were cleared.',
        ),
      ),
    );
    await _reload();
  }

  Future<void> _editInclusionDetails(Map<String, dynamic> existing) async {
    String? gender = existing['gender']?.toString();
    String? ageCohort = existing['age_cohort']?.toString();
    String? disabilityStatus = existing['disability_status']?.toString();
    String? displacementStatus = existing['refugee_or_displaced_status']?.toString();
    String? ruralUrban = existing['rural_urban']?.toString();
    String? employmentCategory = existing['employment_category']?.toString();
    String? firstFormalBorrower = existing['first_time_formal_borrower'] == true
        ? 'yes'
        : existing['first_time_formal_borrower'] == false
            ? 'no'
            : null;

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Voluntary inclusion details'),
          content: SizedBox(
            width: 480,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'These details are optional. OpFin uses them for accessibility, programme eligibility where applicable and aggregate inclusion reporting. They are not credit-risk inputs.',
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: gender,
                    decoration: const InputDecoration(labelText: 'Gender'),
                    items: const [
                      DropdownMenuItem(value: 'female', child: Text('Female')),
                      DropdownMenuItem(value: 'male', child: Text('Male')),
                      DropdownMenuItem(value: 'another_identity', child: Text('Another identity')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => gender = value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: ageCohort,
                    decoration: const InputDecoration(labelText: 'Age group'),
                    items: const [
                      DropdownMenuItem(value: '18_24', child: Text('18–24')),
                      DropdownMenuItem(value: '25_34', child: Text('25–34')),
                      DropdownMenuItem(value: '35_44', child: Text('35–44')),
                      DropdownMenuItem(value: '45_54', child: Text('45–54')),
                      DropdownMenuItem(value: '55_plus', child: Text('55+')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => ageCohort = value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: disabilityStatus,
                    decoration: const InputDecoration(labelText: 'Disability inclusion'),
                    items: const [
                      DropdownMenuItem(value: 'person_with_disability', child: Text('Person with a disability')),
                      DropdownMenuItem(value: 'no_disability_declared', child: Text('No disability declared')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => disabilityStatus = value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: displacementStatus,
                    decoration: const InputDecoration(labelText: 'Refugee or displacement status'),
                    items: const [
                      DropdownMenuItem(value: 'refugee_or_displaced', child: Text('Refugee or displaced person')),
                      DropdownMenuItem(value: 'not_refugee_or_displaced', child: Text('Neither')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => displacementStatus = value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: ruralUrban,
                    decoration: const InputDecoration(labelText: 'Area'),
                    items: const [
                      DropdownMenuItem(value: 'rural', child: Text('Rural')),
                      DropdownMenuItem(value: 'peri_urban', child: Text('Peri-urban')),
                      DropdownMenuItem(value: 'urban', child: Text('Urban')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => ruralUrban = value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: employmentCategory,
                    decoration: const InputDecoration(labelText: 'Employment category'),
                    items: const [
                      DropdownMenuItem(value: 'salaried', child: Text('Salaried')),
                      DropdownMenuItem(value: 'self_employed', child: Text('Self-employed')),
                      DropdownMenuItem(value: 'informal_worker', child: Text('Informal worker')),
                      DropdownMenuItem(value: 'student', child: Text('Student')),
                      DropdownMenuItem(value: 'not_currently_employed', child: Text('Not currently employed')),
                      DropdownMenuItem(value: 'other', child: Text('Other')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => employmentCategory = value),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: firstFormalBorrower,
                    decoration: const InputDecoration(labelText: 'First-time formal borrower'),
                    items: const [
                      DropdownMenuItem(value: 'yes', child: Text('Yes')),
                      DropdownMenuItem(value: 'no', child: Text('No')),
                      DropdownMenuItem(value: 'prefer_not_to_say', child: Text('Prefer not to say')),
                    ],
                    onChanged: (value) => setDialogState(() => firstFormalBorrower = value),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'You can change or withdraw these details later. Withdrawing programme-measurement consent clears the stored voluntary inclusion attributes.',
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save details'),
            ),
          ],
        ),
      ),
    );

    if (submitted != true) return;

    final attributes = <String, dynamic>{
      if (gender != null) 'gender': gender,
      if (ageCohort != null) 'age_cohort': ageCohort,
      if (disabilityStatus != null) 'disability_status': disabilityStatus,
      if (displacementStatus != null)
        'refugee_or_displaced_status': displacementStatus,
      if (ruralUrban != null) 'rural_urban': ruralUrban,
      if (employmentCategory != null) 'employment_category': employmentCategory,
      if (firstFormalBorrower == 'yes') 'first_time_formal_borrower': true,
      if (firstFormalBorrower == 'no') 'first_time_formal_borrower': false,
    };

    await InclusiveFinanceApi.updateProfile(
      programmeMeasurementConsent: true,
      measurementAttributes: attributes,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Voluntary inclusion details updated.')),
    );
    await _reload();
  }

  Future<void> _enrol(int programmeId, String programmeName) async {
    await InclusiveFinanceApi.enrol(programmeId);
    await InclusiveFinanceApi.recordCapabilityEvent(
      eventType: 'action_taken',
      interventionCode: 'PROGRAMME_ENROLMENT',
      outcome: {'programme_id': programmeId},
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Enrolled in $programmeName.')),
    );
    await _reload();
  }

  Future<void> _addSupportInstrument() async {
    var type = 'salary_undertaking';
    final provider = TextEditingController();
    final reference = TextEditingController();
    final value = TextEditingController();

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Add credit support evidence'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: const InputDecoration(labelText: 'Type'),
                  items: const [
                    DropdownMenuItem(value: 'salary_undertaking', child: Text('Salary undertaking')),
                    DropdownMenuItem(value: 'employer_guarantee', child: Text('Employer guarantee')),
                    DropdownMenuItem(value: 'group_guarantee', child: Text('Group guarantee')),
                    DropdownMenuItem(value: 'savings_pledge', child: Text('Savings pledge')),
                    DropdownMenuItem(value: 'receivable', child: Text('Receivable')),
                    DropdownMenuItem(value: 'insurance_guarantee', child: Text('Insurance guarantee')),
                    DropdownMenuItem(value: 'warehouse_receipt', child: Text('Warehouse receipt')),
                    DropdownMenuItem(value: 'asset_evidence', child: Text('Asset evidence')),
                    DropdownMenuItem(value: 'development_guarantee', child: Text('Development guarantee')),
                    DropdownMenuItem(value: 'other', child: Text('Other')),
                  ],
                  onChanged: (next) {
                    if (next != null) setDialogState(() => type = next);
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: provider,
                  decoration: const InputDecoration(
                    labelText: 'Provider or issuer',
                    helperText: 'Required for externally verified collateral such as warehouse receipts.',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: reference,
                  decoration: const InputDecoration(labelText: 'External reference'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: value,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Estimated value in UGX'),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Adding evidence does not automatically approve credit. It must be independently verified and recognised by the relevant product policy.',
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Add evidence'),
            ),
          ],
        ),
      ),
    );

    if (submitted != true) {
      provider.dispose();
      reference.dispose();
      value.dispose();
      return;
    }

    final providerName = provider.text;
    final externalReference = reference.text;
    final valueMinor = int.tryParse(value.text.replaceAll(',', '').trim());
    provider.dispose();
    reference.dispose();
    value.dispose();

    await InclusiveFinanceApi.addSupportInstrument(
      instrumentType: type,
      providerName: providerName,
      externalReference: externalReference,
      valueMinor: valueMinor,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Credit support evidence added for verification.')),
    );
    await _reload();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Financial resilience')),
        body: RefreshIndicator(
          onRefresh: _reload,
          child: FutureBuilder<Map<String, dynamic>>(
            future: _state,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return ListView(
                  children: const [
                    SizedBox(height: 240),
                    Center(child: CircularProgressIndicator()),
                  ],
                );
              }
              if (snapshot.hasError) {
                return ListView(
                  padding: const EdgeInsets.all(24),
                  children: [
                    const SizedBox(height: 100),
                    const Icon(Icons.cloud_off_outlined, size: 48),
                    const SizedBox(height: 12),
                    Text(snapshot.error.toString(), textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton(onPressed: _reload, child: const Text('Try again')),
                  ],
                );
              }

              final data = snapshot.data ?? {};
              final profile =
                  (data['profile'] as Map?)?.cast<String, dynamic>() ?? {};
              final capability =
                  (data['capability'] as Map?)?.cast<String, dynamic>() ?? {};
              final reputation =
                  (capability['financial_reputation'] as Map?)
                          ?.cast<String, dynamic>() ??
                      {};
              final position =
                  (capability['financial_position'] as Map?)
                          ?.cast<String, dynamic>() ??
                      {};
              final fair =
                  (data['fair_treatment'] as Map?)?.cast<String, dynamic>() ?? {};
              final programmesData =
                  (data['programmes'] as Map?)?.cast<String, dynamic>() ?? {};
              final programmes = (programmesData['programmes'] as List?) ?? const [];
              final supportData =
                  (data['support_instruments'] as Map?)
                          ?.cast<String, dynamic>() ??
                      {};
              final instruments =
                  (supportData['instruments'] as List?) ?? const [];
              final guidance = (capability['guidance'] as List?) ?? const [];
              final consent =
                  profile['programme_measurement_consent'] == true;

              return ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Semantics(
                    header: true,
                    child: const Text(
                      'Build resilience, not just borrowing history',
                      style: TextStyle(fontSize: 25, fontWeight: FontWeight.w800),
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'OpFin combines practical financial guidance, formal financial reputation, responsible credit controls and optional programme participation.',
                  ),
                  const SizedBox(height: 18),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Financial reputation',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          Text(
                            _titleCase(reputation['stage']?.toString() ?? 'not ready'),
                            style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 8),
                          Text(reputation['explanation']?.toString() ?? ''),
                          const SizedBox(height: 12),
                          Text('Cleared loans: ${reputation['cleared_loans'] ?? 0}'),
                          Text(
                            'Positive credit reports submitted: ${reputation['positive_credit_reports_submitted'] ?? 0}',
                          ),
                        ],
                      ),
                    ),
                  ),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Your current credit position',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 10),
                          Text('Amount due: ${_moneyText(position['amount_due_minor'])}'),
                          Text('Total outstanding: ${_moneyText(position['total_outstanding_minor'])}'),
                          if (position['next_due_date'] != null)
                            Text('Next due date: ${position['next_due_date']}'),
                        ],
                      ),
                    ),
                  ),
                  if (guidance.isNotEmpty)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(18),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Useful next steps',
                                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                            const SizedBox(height: 8),
                            ...guidance.whereType<Map>().map((item) {
                              final g = item.cast<String, dynamic>();
                              return ListTile(
                                contentPadding: EdgeInsets.zero,
                                leading: const Icon(Icons.check_circle_outline),
                                title: Text(g['title']?.toString() ?? ''),
                                subtitle: Text(g['text']?.toString() ?? ''),
                                onTap: () => InclusiveFinanceApi.recordCapabilityEvent(
                                  eventType: 'guidance_opened',
                                  interventionCode: g['code']?.toString(),
                                ),
                              );
                            }),
                          ],
                        ),
                      ),
                    ),
                  Card(
                    child: Column(
                      children: [
                        SwitchListTile(
                          value: consent,
                          onChanged: _setMeasurementConsent,
                          title: const Text('Help measure inclusion outcomes'),
                          subtitle: const Text(
                            'Optional. Programme reporting may use voluntary inclusion information in aggregate. These attributes are kept outside credit-risk decisioning.',
                          ),
                        ),
                        if (consent)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                            child: SizedBox(
                              width: double.infinity,
                              child: OutlinedButton.icon(
                                onPressed: () => _editInclusionDetails(
                                  (profile['measurement_attributes'] as Map?)
                                          ?.cast<String, dynamic>() ??
                                      <String, dynamic>{},
                                ),
                                icon: const Icon(Icons.tune),
                                label: const Text('Update voluntary inclusion details'),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Fair treatment',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 8),
                          Text(fair['explanation']?.toString() ??
                              'Programme measurement attributes are excluded from credit decisioning.'),
                          const SizedBox(height: 8),
                          const Text(
                            'Identity, consent, approved credit data, affordability and governed product rules remain the basis for credit decisions.',
                          ),
                        ],
                      ),
                    ),
                  ),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Inclusive finance programmes',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 8),
                          if (programmes.isEmpty)
                            const Text('No open programmes are available to your account right now.')
                          else
                            ...programmes.whereType<Map>().map((item) {
                              final programme = item.cast<String, dynamic>();
                              final eligibility =
                                  (programme['eligibility'] as Map?)
                                          ?.cast<String, dynamic>() ??
                                      <String, dynamic>{};
                              final eligibilityStatus =
                                  eligibility['status']?.toString() ?? 'eligible';
                              final joined =
                                  programme['enrolment_status'] == 'enrolled';
                              final subtitle = eligibilityStatus == 'incomplete'
                                  ? 'More voluntary or verified eligibility information is needed.'
                                  : eligibilityStatus == 'ineligible'
                                      ? 'This account does not currently meet the programme criteria.'
                                      : programme['code']?.toString() ?? '';
                              return ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(programme['name']?.toString() ?? 'Programme'),
                                subtitle: Text(subtitle),
                                trailing: joined
                                    ? const Chip(label: Text('Joined'))
                                    : FilledButton.tonal(
                                        onPressed: eligibilityStatus == 'eligible'
                                            ? () => _enrol(
                                                  (programme['id'] as num).toInt(),
                                                  programme['name']?.toString() ?? 'programme',
                                                )
                                            : null,
                                        child: const Text('Join'),
                                      ),
                              );
                            }),
                        ],
                      ),
                    ),
                  ),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Expanded(
                                child: Text('Alternative credit support',
                                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                              ),
                              TextButton.icon(
                                onPressed: _addSupportInstrument,
                                icon: const Icon(Icons.add),
                                label: const Text('Add'),
                              ),
                            ],
                          ),
                          const Text(
                            'Salary undertakings, guarantees, savings pledges, receivables, warehouse receipts and other evidence can be recorded for independent verification.',
                          ),
                          const SizedBox(height: 8),
                          if (instruments.isEmpty)
                            const Text('No support evidence has been added.')
                          else
                            ...instruments.whereType<Map>().map((item) {
                              final instrument = item.cast<String, dynamic>();
                              return ListTile(
                                contentPadding: EdgeInsets.zero,
                                leading: const Icon(Icons.verified_user_outlined),
                                title: Text(_titleCase(instrument['instrument_type']?.toString() ?? 'other')),
                                subtitle: Text(
                                  '${_titleCase(instrument['verification_status']?.toString() ?? 'pending')}${instrument['external_reference'] == null ? '' : ' · ${instrument['external_reference']}'}',
                                ),
                                trailing: instrument['value_minor'] == null
                                    ? null
                                    : Text(_moneyText(instrument['value_minor'])),
                              );
                            }),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'OpFin remains a financial operating and inclusive-finance platform. Business operations such as POS, inventory and purchasing are not part of this experience.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 24),
                ],
              );
            },
          ),
        ),
      );
}
