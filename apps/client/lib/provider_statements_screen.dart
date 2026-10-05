import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:opfin/services/statement_intelligence_api.dart';

/// Plain-language wording for the server's assurance codes. Unsupported or
/// unreadable documents are never described as suspicious.
String statementLabel(String dimension, String? code) {
  const labels = <String, Map<String, String>>{
    'extraction': {
      'pending': 'Still reading',
      'complete': 'Read in full',
      'partial': 'Only partly read',
      'not_yet_supported': 'This layout is not supported yet',
      'not_processed': 'Not opened',
      'unreadable': 'Could not be read',
    },
    'financial_consistency': {
      'pending': 'Still checking',
      'consistent': 'Balances and totals add up',
      'discrepancies': 'Some figures do not add up',
      'unable_to_assess': 'Not enough information to check',
    },
    'source_authenticity': {
      'unconfirmed': 'Not confirmed by your provider',
    },
    'review': {
      'pending': 'Waiting for the checks',
      'no_issues_detected_by_executed_checks': 'No issues found by our checks',
      'needs_review': 'Some items need a closer look',
      'not_applicable': 'No review needed',
      'resolved_with_reasons': 'Reviewed and resolved',
      'rejected_with_reasons': 'Reviewed: cannot be used',
      'resubmission_requested': 'Please upload a complete statement',
      'original_requested': 'Please upload the original statement',
      'issuer_verification_requested': 'Being confirmed with your provider',
      'escalated': 'With a senior reviewer',
      'appealed': 'Your appeal is being reviewed',
      'superseded_by_resubmission': 'Replaced by a newer upload',
    },
  };
  return labels[dimension]?[code] ??
      (code ?? 'Not recorded').replaceAll('_', ' ');
}

class ProviderStatementsScreen extends StatefulWidget {
  const ProviderStatementsScreen({super.key, required this.space});

  final Map<String, dynamic> space;

  @override
  State<ProviderStatementsScreen> createState() =>
      _ProviderStatementsScreenState();
}

class _ProviderStatementsScreenState extends State<ProviderStatementsScreen> {
  late Future<List<Map<String, dynamic>>> _statements;

  int get _spaceId => (widget.space['id'] as num).toInt();

  @override
  void initState() {
    super.initState();
    _statements = StatementIntelligenceApi.statements(_spaceId);
  }

  Future<void> _reload() async {
    setState(() => _statements = StatementIntelligenceApi.statements(_spaceId));
    await _statements;
  }

  Future<void> _upload() async {
    final created = await Navigator.push<int>(
      context,
      MaterialPageRoute(
        builder: (_) => ProviderStatementUploadScreen(space: widget.space),
      ),
    );
    if (!mounted) return;
    await _reload();
    if (created != null && mounted) _open(created);
  }

  void _open(int id) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            ProviderStatementDetailScreen(space: widget.space, statementId: id),
      ),
    ).then((_) {
      if (mounted) _reload();
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Provider statements')),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _upload,
          icon: const Icon(Icons.upload_file),
          label: const Text('Upload statement'),
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _statements,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('${snapshot.error}'),
                ),
              );
            }
            final items = snapshot.data ?? const [];
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: [
                  const Text(
                    'Upload statements from your bank or mobile money provider to see your money in and out. '
                    'Results are checks on the document, not confirmation from your provider.',
                  ),
                  const SizedBox(height: 16),
                  if (items.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('You have not uploaded a statement yet.'),
                      ),
                    ),
                  ...items.map((item) {
                    final assurance =
                        (item['assurance'] as Map?)?.cast<String, dynamic>();
                    return Card(
                      child: ListTile(
                        leading: const Icon(Icons.description_outlined),
                        title: Text(
                            '${item['period_start']} to ${item['period_end']}'),
                        subtitle: Text(
                            statementLabel('review', assurance?['review'])),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => _open((item['id'] as num).toInt()),
                      ),
                    );
                  }),
                ],
              ),
            );
          },
        ),
      );
}

class ProviderStatementDetailScreen extends StatefulWidget {
  const ProviderStatementDetailScreen({
    super.key,
    required this.space,
    required this.statementId,
    this.load,
  });

  final Map<String, dynamic> space;
  final int statementId;

  /// Replaces the network loader in tests.
  final Future<Map<String, dynamic>> Function()? load;

  @override
  State<ProviderStatementDetailScreen> createState() =>
      _ProviderStatementDetailScreenState();
}

class _ProviderStatementDetailScreenState
    extends State<ProviderStatementDetailScreen> {
  late Future<Map<String, dynamic>> _detail;
  final String _appealKey = StatementIntelligenceApi.newKey('appeal');

  int get _spaceId => (widget.space['id'] as num).toInt();

  Future<Map<String, dynamic>> _fetch() =>
      widget.load?.call() ??
      StatementIntelligenceApi.statement(_spaceId, widget.statementId);

  @override
  void initState() {
    super.initState();
    _detail = _fetch();
  }

  Future<void> _reload() async {
    setState(() => _detail = _fetch());
    await _detail;
  }

  Future<void> _appeal() async {
    final reason = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Appeal this decision'),
        content: TextField(
          controller: reason,
          maxLines: 4,
          decoration: const InputDecoration(
            labelText: 'Why do you think the decision is wrong?',
            helperText: 'You can appeal once. A different reviewer decides.',
          ),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Send appeal')),
        ],
      ),
    );
    if (send != true || reason.text.trim().isEmpty) return;
    try {
      await StatementIntelligenceApi.appeal(
          _spaceId, widget.statementId, reason.text.trim(), _appealKey);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Your appeal has been sent.')));
      await _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  Future<void> _replace() async {
    final created = await Navigator.push<int>(
      context,
      MaterialPageRoute(
        builder: (_) => ProviderStatementUploadScreen(
            space: widget.space, supersedesStatementId: widget.statementId),
      ),
    );
    if (created != null && mounted) Navigator.pop(context);
  }

  Future<void> _withdraw() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Stop using this statement?'),
        content: const Text(
            'OpFin will stop analysing it. The original is deleted after the retention period.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Keep')),
          FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Stop using it')),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await StatementIntelligenceApi.withdraw(_spaceId, widget.statementId);
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Statement results')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _detail,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('${snapshot.error}'),
                ),
              );
            }
            final detail = snapshot.data ?? const {};
            final assurance =
                (detail['assurance'] as Map?)?.cast<String, dynamic>() ??
                    const {};
            final signals = (assurance['document_signals'] as List?) ?? const [];
            final findings =
                ((detail['analysis'] as Map?)?['findings'] as List?) ??
                    const [];
            final reviews = (detail['reviews'] as List?) ?? const [];
            final review = assurance['review']?.toString();
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (detail['status_explanation'] != null)
                    Text(detail['status_explanation'].toString(),
                        style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  Card(
                    child: Column(
                      children: [
                        for (final dimension in const [
                          'extraction',
                          'financial_consistency',
                          'source_authenticity',
                          'review',
                        ])
                          ListTile(
                            title: Text(const {
                              'extraction': 'Reading the statement',
                              'financial_consistency': 'Balance checks',
                              'source_authenticity': 'Provider confirmation',
                              'review': 'Review',
                            }[dimension]!),
                            subtitle: Text(statementLabel(
                                dimension, assurance[dimension]?.toString())),
                          ),
                      ],
                    ),
                  ),
                  if (findings.isNotEmpty)
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.rule_outlined),
                        title: Text(findings.length == 1
                            ? '1 item to check'
                            : '${findings.length} items to check'),
                        subtitle: const Text(
                            'Items found by the balance checks. They are reasons to look closer, not a judgement.'),
                      ),
                    ),
                  if (signals.isNotEmpty)
                    const Card(
                      child: ListTile(
                        leading: Icon(Icons.info_outline),
                        title: Text('This file was edited or re-saved'),
                        subtitle: Text(
                            'That can be normal. A reviewer may ask for the original from your provider.'),
                      ),
                    ),
                  if (detail['next_action'] != null)
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.flag_outlined),
                        title: const Text('Next step'),
                        subtitle: Text(detail['next_action'].toString()),
                      ),
                    ),
                  if (reviews.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text('Review history',
                        style: Theme.of(context).textTheme.titleSmall),
                    for (final entry in reviews.whereType<Map>())
                      ListTile(
                        dense: true,
                        title: Text(entry['decision']
                            .toString()
                            .replaceAll('_', ' ')),
                        subtitle: Text(entry['decided_at']?.toString() ?? ''),
                      ),
                  ],
                  const SizedBox(height: 16),
                  if (detail['can_appeal'] == true)
                    FilledButton.icon(
                      onPressed: _appeal,
                      icon: const Icon(Icons.gavel_outlined),
                      label: const Text('Appeal this decision'),
                    ),
                  if (review == 'resubmission_requested' ||
                      review == 'original_requested')
                    FilledButton.icon(
                      onPressed: _replace,
                      icon: const Icon(Icons.upload_file),
                      label: const Text('Upload a replacement'),
                    ),
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: _withdraw,
                    child: const Text('Stop using this statement'),
                  ),
                ],
              ),
            );
          },
        ),
      );
}

/// A picked statement file. [path] is null when the platform gives no file path.
class PickedStatement {
  const PickedStatement({required this.name, required this.size, this.path});

  final String name;
  final int size;
  final String? path;
}

/// Opens the system document picker. No storage or media permission is needed.
Future<PickedStatement?> pickStatementFile() async {
  final file = await FilePicker.pickFile(
    type: FileType.custom,
    allowedExtensions: const ['pdf', 'csv'],
  );
  if (file == null) return null;
  final size = (await file.length()) ?? 0;
  return PickedStatement(name: file.name, size: size, path: file.path);
}

typedef StatementUploader = Future<Map<String, dynamic>> Function({
  required int spaceId,
  required int issuerVersionId,
  required String currency,
  required String periodStart,
  required String periodEnd,
  required String accountReference,
  required String authorityReference,
  required String authorityExpiresAt,
  required String filePath,
  required String fileName,
  required String idempotencyKey,
  int? supersedesStatementId,
});

class ProviderStatementUploadScreen extends StatefulWidget {
  const ProviderStatementUploadScreen({
    super.key,
    required this.space,
    this.supersedesStatementId,
    this.loadIssuers,
    this.pickFile = pickStatementFile,
    this.uploader = StatementIntelligenceApi.upload,
    this.today,
  });

  final Map<String, dynamic> space;
  final int? supersedesStatementId;
  final Future<List<Map<String, dynamic>>> Function()? loadIssuers;
  final Future<PickedStatement?> Function() pickFile;
  final StatementUploader uploader;

  /// Fixed date for tests.
  final DateTime? today;

  @override
  State<ProviderStatementUploadScreen> createState() =>
      _ProviderStatementUploadScreenState();
}

class _ProviderStatementUploadScreenState
    extends State<ProviderStatementUploadScreen> {
  final _form = GlobalKey<FormState>();
  final _account = TextEditingController();
  final _authority = TextEditingController(text: 'I am the account owner');
  final String _key = StatementIntelligenceApi.newKey('statement');
  late final DateTime _today = widget.today ?? DateTime.now();
  late Future<List<Map<String, dynamic>>> _issuers;
  int? _issuer;
  DateTime? _start;
  DateTime? _end;
  late DateTime _expires = _today.add(const Duration(days: 90));
  PickedStatement? _file;
  bool _confirmed = false;
  bool _busy = false;
  String? _error;

  int get _spaceId => (widget.space['id'] as num).toInt();

  @override
  void initState() {
    super.initState();
    _issuers = widget.loadIssuers?.call() ??
        StatementIntelligenceApi.issuers(_spaceId);
  }

  @override
  void dispose() {
    _account.dispose();
    _authority.dispose();
    super.dispose();
  }

  String _date(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  Future<void> _pickDate(String which) async {
    final initial = switch (which) {
      'start' => _start ?? _today.subtract(const Duration(days: 30)),
      'end' => _end ?? _today,
      _ => _expires,
    };
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: which == 'expires'
          ? _today.add(const Duration(days: 1))
          : DateTime(_today.year - 3),
      lastDate:
          which == 'expires' ? _today.add(const Duration(days: 365)) : _today,
    );
    if (picked == null) return;
    setState(() {
      if (which == 'start') _start = picked;
      if (which == 'end') _end = picked;
      if (which == 'expires') _expires = picked;
    });
  }

  Future<void> _chooseFile() async {
    final file = await widget.pickFile();
    if (file == null) return;
    setState(() => _file = file);
  }

  String? _validate() {
    if (_issuer == null) return 'Choose your bank or mobile money provider.';
    if (_start == null || _end == null) {
      return 'Choose the first and last date of the statement.';
    }
    if (_end!.isBefore(_start!)) {
      return 'The last date must be on or after the first date.';
    }
    final file = _file;
    if (file == null || file.path == null) {
      return 'Choose the statement file (PDF or CSV).';
    }
    final pdf = file.name.toLowerCase().endsWith('.pdf');
    if (file.size > (pdf ? StatementIntelligenceApi.maxPdfBytes : StatementIntelligenceApi.maxCsvBytes)) {
      return pdf
          ? 'PDF statements can be up to 10 MB. Choose a shorter period.'
          : 'This file is too large.';
    }
    if (!_confirmed) {
      return 'Confirm that you may share this account for analysis.';
    }
    return null;
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    final problem = _validate();
    setState(() => _error = problem);
    if (problem != null) return;
    setState(() => _busy = true);
    try {
      final created = await widget.uploader(
        spaceId: _spaceId,
        issuerVersionId: _issuer!,
        currency: widget.space['currency']?.toString() ?? 'UGX',
        periodStart: _date(_start!),
        periodEnd: _date(_end!),
        accountReference: _account.text.trim(),
        authorityReference: _authority.text.trim(),
        authorityExpiresAt: _date(_expires),
        filePath: _file!.path!,
        fileName: _file!.name,
        idempotencyKey: _key,
        supersedesStatementId: widget.supersedesStatementId,
      );
      if (!mounted) return;
      Navigator.pop(context, (created['id'] as num?)?.toInt());
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = '$error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: Text(widget.supersedesStatementId == null
              ? 'Upload a statement'
              : 'Upload a replacement'),
        ),
        body: FutureBuilder<List<Map<String, dynamic>>>(
          future: _issuers,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            final issuers = snapshot.data ?? const [];
            return Form(
              key: _form,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Card(
                    child: ListTile(
                      leading: Icon(Icons.lock_outline),
                      title: Text('Never share your PIN, password or OTP'),
                      subtitle: Text(
                          'Download the statement from your provider and upload the file. OpFin never asks for these.'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (issuers.isEmpty)
                    const Text(
                        'No provider is approved for statement uploads in your country yet.')
                  else
                    DropdownButtonFormField<int>(
                      initialValue: _issuer,
                      isExpanded: true,
                      decoration: const InputDecoration(
                          labelText: 'Bank or mobile money provider'),
                      items: [
                        for (final issuer in issuers)
                          DropdownMenuItem(
                            value: (issuer['id'] as num).toInt(),
                            child: Text(issuer['legal_name'].toString(),
                                overflow: TextOverflow.ellipsis),
                          ),
                      ],
                      onChanged: (value) => setState(() => _issuer = value),
                    ),
                  const SizedBox(height: 12),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('First date on the statement'),
                    subtitle: Text(
                        _start == null ? 'Choose a date' : _date(_start!)),
                    trailing: const Icon(Icons.calendar_today_outlined),
                    onTap: () => _pickDate('start'),
                  ),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Last date on the statement'),
                    subtitle:
                        Text(_end == null ? 'Choose a date' : _date(_end!)),
                    trailing: const Icon(Icons.calendar_today_outlined),
                    onTap: () => _pickDate('end'),
                  ),
                  TextFormField(
                    controller: _account,
                    decoration: const InputDecoration(
                      labelText: 'Account name or number',
                      helperText:
                          'Stored encrypted. This is not your PIN or password.',
                    ),
                    validator: (value) => (value ?? '').trim().isEmpty
                        ? 'Enter the account name or number.'
                        : null,
                  ),
                  TextFormField(
                    controller: _authority,
                    decoration: const InputDecoration(
                        labelText: 'Your authority to share this account'),
                    validator: (value) => (value ?? '').trim().isEmpty
                        ? 'Say why you may share this account.'
                        : null,
                  ),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Allow analysis until'),
                    subtitle: Text(_date(_expires)),
                    trailing: const Icon(Icons.calendar_today_outlined),
                    onTap: () => _pickDate('expires'),
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _chooseFile,
                    icon: const Icon(Icons.attach_file),
                    label: Text(_file == null
                        ? 'Choose PDF or CSV file'
                        : 'File: ${_file!.name}'),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _confirmed,
                    onChanged: (value) =>
                        setState(() => _confirmed = value ?? false),
                    title: const Text(
                        'This is my account, or I am authorised to share it, for financial analysis in this Space.'),
                  ),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Text(_error!,
                          style: TextStyle(
                              color: Theme.of(context).colorScheme.error)),
                    ),
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: _busy || issuers.isEmpty ? null : _submit,
                    child: Text(_busy ? 'Uploading…' : 'Upload statement'),
                  ),
                ],
              ),
            );
          },
        ),
      );
}
