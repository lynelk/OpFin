import 'package:flutter/material.dart';
import 'package:opfin/services/opfin_people_api.dart';
import 'package:opfin/workspace_access.dart';

class PeopleHubScreen extends StatelessWidget {
  const PeopleHubScreen({super.key, required this.space});

  final Map<String, dynamic> space;

  bool get canHr => WorkspaceAccess.canManagePeople(space);
  bool get canPayroll => WorkspaceAccess.info(space)['can_manage_payroll'] == true;
  bool get canSupervise => WorkspaceAccess.info(space)['can_supervise_people'] == true;

  @override
  Widget build(BuildContext context) {
    final modules = <_PeopleModule>[
      const _PeopleModule('HR and work engagements', Icons.badge_outlined,
          'employees', 'employees'),
      const _PeopleModule('Leave', Icons.calendar_month_outlined,
          'leave/requests', 'requests'),
      const _PeopleModule('Attendance', Icons.schedule_outlined,
          'attendance', 'events'),
      const _PeopleModule('Asset custody', Icons.laptop_outlined,
          'assets/custody', 'records'),
      const _PeopleModule('KPIs and targets', Icons.track_changes_outlined,
          'kpis', 'records'),
      const _PeopleModule('Appraisals', Icons.fact_check_outlined,
          'appraisals', 'records'),
      const _PeopleModule('Rewards', Icons.emoji_events_outlined,
          'rewards', 'records'),
      const _PeopleModule('Field accountability', Icons.assignment_outlined,
          'field-assignments', 'records'),
      const _PeopleModule('My payslips', Icons.receipt_long_outlined,
          'payroll/my-payslips', 'payslips'),
      if (canPayroll)
        const _PeopleModule('Payroll control', Icons.account_balance_wallet_outlined,
            'payroll/periods', 'periods'),
      if (canHr)
        const _PeopleModule('Biometric connectors', Icons.fingerprint,
            'biometrics/connectors', 'connectors'),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('OpFin People')),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        Text(space['name']?.toString() ?? 'My work',
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Text('Your role: ${WorkspaceAccess.role(space)}'),
        const SizedBox(height: 8),
        const Text('Your work records belong to this organisation. '
            'An employer cannot view your personal OpFin financial records.'),
        const SizedBox(height: 16),
        ...modules.map((module) => Card(
          child: ListTile(
            leading: Icon(module.icon),
            title: Text(module.title),
            subtitle: Text(_description(module.path)),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(context, MaterialPageRoute(
              builder: (_) => _PeopleModuleScreen(
                space: space,
                module: module,
                canHr: canHr,
                canSupervise: canSupervise,
                canPayroll: canPayroll,
              ),
            )),
          ),
        )),
        const SizedBox(height: 8),
        const Text('Biometric attendance and statutory payroll calculation '
            'remain unavailable until approved integrations and legally '
            'reviewed policies are activated. Requests and approvals '
            'never initiate financial payments automatically.',
            style: TextStyle(fontSize: 12)),
      ]),
    );
  }

  String _description(String path) {
    if (path == 'payroll/my-payslips') return 'Locked and approved pay records only';
    if (path == 'payroll/periods') return 'Prepare, review and lock payroll runs';
    if (path == 'biometrics/connectors') return 'Privacy-reviewed device configuration';
    if (path == 'leave/requests') return 'Request leave and check approval';
    if (path == 'attendance') return 'Record and review working time';
    if (path == 'assets/custody') return 'Assigned devices and equipment';
    if (path == 'kpis') return 'Targets, results and evidence';
    if (path == 'appraisals') return 'Self-assessment and manager reviews';
    if (path == 'rewards') return 'Recognitions and proposed awards';
    if (path == 'field-assignments') return 'Work orders, evidence and expenses';
    return 'Employment, contracts and roles';
  }
}

class _PeopleModule {
  const _PeopleModule(this.title, this.icon, this.path, this.keyName);

  final String title;
  final IconData icon;
  final String path;
  final String keyName;
}

class _PeopleField {
  const _PeopleField(this.name, this.label, {this.numeric = false, this.initial = ''});

  final String name;
  final String label;
  final bool numeric;
  final String initial;
}

class _PeopleModuleScreen extends StatefulWidget {
  const _PeopleModuleScreen({
    required this.space,
    required this.module,
    required this.canHr,
    required this.canSupervise,
    required this.canPayroll,
  });

  final Map<String, dynamic> space;
  final _PeopleModule module;
  final bool canHr;
  final bool canSupervise;
  final bool canPayroll;

  @override
  State<_PeopleModuleScreen> createState() => _PeopleModuleScreenState();
}

class _PeopleModuleScreenState extends State<_PeopleModuleScreen> {
  late Future<Map<String, dynamic>> _records;

  int get spaceId => (widget.space['id'] as num).toInt();
  String get path => widget.module.path;

  @override
  void initState() {
    super.initState();
    _records = OpFinPeopleApi.load(spaceId, path);
  }

  Future<void> _reload() async {
    setState(() => _records = OpFinPeopleApi.load(spaceId, path));
    await _records;
  }

  bool get canCreate {
    if (path == 'employees' || path == 'biometrics/connectors' ||
        path == 'assets/custody') return widget.canHr;
    if (path == 'payroll/periods') return widget.canPayroll;
    if (path == 'rewards' || path == 'kpis' ||
        path == 'field-assignments') return widget.canSupervise;
    return path == 'leave/requests' || path == 'attendance' ||
        path == 'appraisals';
  }

  List<_PeopleField> _creationFields() {
    switch (path) {
      case 'employees':
        return const [
          _PeopleField('user_id', 'Member user ID', numeric: true),
          _PeopleField('workforce_number', 'Staff or worker number'),
          _PeopleField('engagement_type', 'Engagement type', initial: 'permanent'),
          _PeopleField('job_title', 'Job title'),
          _PeopleField('starts_on', 'Start date YYYY-MM-DD'),
          _PeopleField('department', 'Department (optional)'),
        ];
      case 'leave/requests':
        return const [
          _PeopleField('engagement_id', 'My work engagement ID', numeric: true),
          _PeopleField('leave_type', 'Leave type', initial: 'annual'),
          _PeopleField('starts_on', 'First day YYYY-MM-DD'),
          _PeopleField('ends_on', 'Last day YYYY-MM-DD'),
          _PeopleField('requested_days', 'Requested working days', numeric: true),
        ];
      case 'attendance':
        return const [
          _PeopleField('engagement_id', 'My work engagement ID', numeric: true),
          _PeopleField('direction', 'Clock direction: in or out', initial: 'in'),
        ];
      case 'assets/custody':
        return const [
          _PeopleField('engagement_id', 'Worker engagement ID', numeric: true),
          _PeopleField('financial_asset_id', 'Employer asset ID', numeric: true),
          _PeopleField('issued_on', 'Issued on YYYY-MM-DD'),
        ];
      case 'kpis':
        return const [
          _PeopleField('engagement_id', 'Worker engagement ID', numeric: true),
          _PeopleField('metric', 'Measure'),
          _PeopleField('unit', 'Unit', initial: 'tasks'),
          _PeopleField('target_value', 'Target', numeric: true),
          _PeopleField('period_start', 'Period start YYYY-MM-DD'),
          _PeopleField('period_end', 'Period end YYYY-MM-DD'),
        ];
      case 'appraisals':
        return const [
          _PeopleField('engagement_id', 'My engagement ID', numeric: true),
          _PeopleField('period_start', 'Period start YYYY-MM-DD'),
          _PeopleField('period_end', 'Period end YYYY-MM-DD'),
          _PeopleField('self_assessment', 'Self-assessment'),
        ];
      case 'rewards':
        return const [
          _PeopleField('engagement_id', 'Worker engagement ID', numeric: true),
          _PeopleField('reward_type', 'recognition, bonus or allowance',
              initial: 'recognition'),
          _PeopleField('recognition', 'Reason for recognition'),
          _PeopleField('proposed_amount_minor', 'Proposed amount (0 if non-cash)',
              numeric: true, initial: '0'),
        ];
      case 'field-assignments':
        return const [
          _PeopleField('engagement_id', 'Worker engagement ID', numeric: true),
          _PeopleField('title', 'Assignment title'),
          _PeopleField('instructions', 'Instructions (optional)'),
        ];
      case 'biometrics/connectors':
        return const [
          _PeopleField('vendor', 'Approved device vendor'),
          _PeopleField('external_device_reference', 'Device reference'),
        ];
      default:
        return const [];
    }
  }

  Future<Map<String, dynamic>?> _form(String title, List<_PeopleField> fields) async {
    final controllers = {
      for (final field in fields) field.name: TextEditingController(text: field.initial),
    };
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            ...fields.map((field) => TextField(
              controller: controllers[field.name],
              keyboardType: field.numeric ? TextInputType.number : TextInputType.text,
              decoration: InputDecoration(labelText: field.label),
            )),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Submit')),
        ],
      ),
    );
    final data = <String, dynamic>{};
    for (final field in fields) {
      final value = controllers[field.name]!.text.trim();
      controllers[field.name]!.dispose();
      if (value.isEmpty) continue;
      if (field.numeric) {
        final n = int.tryParse(value);
        if (n == null) return null;
        data[field.name] = n;
      } else {
        data[field.name] = value;
      }
    }
    return accepted == true ? data : null;
  }

  Future<void> _send(String url, Map<String, dynamic> data) async {
    try {
      await OpFinPeopleApi.submit(spaceId, url, data);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Saved for review.')));
      await _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _create() async {
    if (!canCreate) return;
    if (path == 'payroll/periods') {
      final data = await _form('New payroll period', const [
        _PeopleField('period_start', 'Start date YYYY-MM-DD'),
        _PeopleField('period_end', 'End date YYYY-MM-DD'),
        _PeopleField('payroll_policy_id', 'Approved policy ID', numeric: true),
      ]);
      if (data != null) await _send(path, data);
      return;
    }

    final data = await _form('New ${widget.module.title.toLowerCase()} record',
        _creationFields());
    if (data == null) return;
    if (path == 'attendance') {
      data['source'] = 'mobile';
      data['occurred_at'] = DateTime.now().toUtc().toIso8601String();
      data['idempotency_key'] =
          'people-mobile-$spaceId-${DateTime.now().microsecondsSinceEpoch}';
    }
    await _send(path, data);
  }

  List<({String label, String endpoint, List<_PeopleField> fields})> _actions(
      Map<String, dynamic> record) {
    final id = record['id'];
    final status = record['status']?.toString() ?? '';
    if (id == null) return const [];
    final actions = <({String label, String endpoint, List<_PeopleField> fields})>[];
    if (path == 'leave/requests' && widget.canSupervise && status == 'requested') {
      actions.add((label: 'Approve leave', endpoint: '$path/$id/decide',
          fields: const [_PeopleField('decision', 'Decision', initial: 'approved')]));
    }
    if (path == 'attendance' && widget.canSupervise && status == 'submitted') {
      actions.add((label: 'Approve event', endpoint: '$path/$id/approve', fields: const []));
    }
    if (path == 'assets/custody' && status == 'issued') {
      actions.add((label: 'Acknowledge custody',
          endpoint: '$path/$id/acknowledge', fields: const []));
      if (widget.canHr) {
        actions.add((label: 'Record return', endpoint: '$path/$id/return',
            fields: const [_PeopleField('returned_on', 'Return date YYYY-MM-DD')]));
      }
    }
    if (path == 'kpis') {
      if (status == 'draft') {
        actions.add((label: 'Report result', endpoint: '$path/$id/report',
            fields: const [
              _PeopleField('actual_value', 'Actual result', numeric: true),
              _PeopleField('evidence_reference', 'Evidence reference'),
            ]));
      }
      if (status == 'submitted' && widget.canSupervise) {
        actions.add((label: 'Approve KPI', endpoint: '$path/$id/approve', fields: const []));
      }
    }
    if (path == 'appraisals' && status == 'submitted' && widget.canSupervise) {
      actions.add((label: 'Manager review', endpoint: '$path/$id/approve',
          fields: const [_PeopleField('manager_assessment', 'Review and assessment')]));
    }
    if (path == 'rewards' && status == 'nominated' && widget.canHr) {
      actions.add((label: 'Approve nomination', endpoint: '$path/$id/approve', fields: const []));
    }
    if (path == 'field-assignments') {
      if (status == 'assigned') {
        actions.add((label: 'Submit field evidence', endpoint: '$path/$id/submit',
            fields: const [
              _PeopleField('evidence_reference', 'Evidence reference'),
              _PeopleField('claimed_expense_minor', 'Expense claimed (minor units)',
                  numeric: true, initial: '0'),
            ]));
      }
      if (status == 'submitted' && widget.canSupervise) {
        actions.add((label: 'Approve field activity', endpoint: '$path/$id/approve',
            fields: const []));
      }
    }
    if (path == 'payroll/periods' && widget.canPayroll) {
      if (status == 'draft') {
        actions.add((label: 'Prepare payroll', endpoint: '$path/$id/prepare', fields: const []));
      } else if (status == 'prepared') {
        actions.add((label: 'Approve payroll', endpoint: '$path/$id/approve', fields: const []));
      } else if (status == 'approved') {
        actions.add((label: 'Lock payroll', endpoint: '$path/$id/lock', fields: const []));
      }
    }
    return actions;
  }

  Future<void> _act(
      ({String label, String endpoint, List<_PeopleField> fields}) action) async {
    final payload = action.fields.isEmpty
        ? <String, dynamic>{}
        : await _form(action.label, action.fields);
    if (payload == null) return;
    await _send(action.endpoint, payload);
  }

  String _summary(Map<String, dynamic> item) {
    final pieces = <String>[];
    for (final key in [
      'job_title', 'status', 'engagement_type', 'leave_type',
      'starts_on', 'ends_on', 'direction', 'occurred_at',
      'net_minor', 'currency', 'title', 'metric',
      'reward_type', 'recognition', 'vendor', 'external_device_reference',
    ]) {
      final value = item[key];
      if (value != null) pieces.add('$key: $value');
    }
    return pieces.take(4).join(' · ');
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.module.title)),
    floatingActionButton: canCreate
        ? FloatingActionButton.extended(
            onPressed: _create,
            icon: const Icon(Icons.add),
            label: const Text('Add record'))
        : null,
    body: RefreshIndicator(onRefresh: _reload,
      child: FutureBuilder<Map<String, dynamic>>(
        future: _records,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(children: const [
              Padding(padding: EdgeInsets.all(28),
                child: Text('Unable to load work records. Pull down to retry.')),
            ]);
          }
          final records = OpFinPeopleApi.records(
              snapshot.data ?? const {}, widget.module.keyName);
          return ListView(padding: const EdgeInsets.all(18), children: [
            if (records.isEmpty) const Card(
              child: Padding(padding: EdgeInsets.all(20),
                  child: Text('No authorised records available yet.')),
            ),
            ...records.map((record) {
              final actions = _actions(record);
              return Card(child: ListTile(
                title: Text(record['title']?.toString() ??
                    record['job_title']?.toString() ??
                    record['metric']?.toString() ??
                    record['recognition']?.toString() ??
                    'Record #${record['id'] ?? ''}'),
                subtitle: Text(_summary(record)),
                isThreeLine: true,
                trailing: actions.isEmpty
                    ? const Icon(Icons.chevron_right)
                    : PopupMenuButton<int>(
                        tooltip: 'Available actions',
                        onSelected: (index) => _act(actions[index]),
                        itemBuilder: (context) => [
                          for (var index = 0; index < actions.length; index++)
                            PopupMenuItem(value: index,
                                child: Text(actions[index].label)),
                        ],
                      ),
              ));
            }),
            const SizedBox(height: 12),
            const Text('All approvals are checked by the server. Work or '
                'payroll records do not automatically move money, '
                'verify biometric attendance or override statutory rules.',
                style: TextStyle(fontSize: 12)),
          ]);
        },
      ),
    ),
  );
}
