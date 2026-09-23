import 'package:flutter/material.dart';
import 'package:opfin/services/inclusive_finance_api.dart';

class ProgrammeCheckInScreen extends StatefulWidget {
  const ProgrammeCheckInScreen({super.key});

  @override
  State<ProgrammeCheckInScreen> createState() => _ProgrammeCheckInScreenState();
}

class _ProgrammeCheckInScreenState extends State<ProgrammeCheckInScreen> {
  late Future<Map<String, dynamic>> _state;

  @override
  void initState() {
    super.initState();
    _state = InclusiveFinanceApi.dueProgrammeCheckIns();
  }

  Future<void> _reload() async {
    setState(() => _state = InclusiveFinanceApi.dueProgrammeCheckIns());
    await _state;
  }

  Future<void> _completeInstrument(Map<String, dynamic> instrument) async {
    final questions = (instrument['questions'] as List?)?.whereType<Map>().toList() ?? const [];
    if (questions.isEmpty) return;

    final schedule = (instrument['schedule'] as Map?)?.cast<String, dynamic>();
    final scheduleId = (schedule?['id'] as num?)?.toInt();
    if (scheduleId != null) {
      await InclusiveFinanceApi.markProgrammeFollowUpOpened(scheduleId)
          .catchError((_) => <String, dynamic>{});
    }

    final values = <int, dynamic>{};
    final controllers = <int, TextEditingController>{};

    for (final raw in questions) {
      final question = raw.cast<String, dynamic>();
      final id = (question['id'] as num).toInt();
      final type = question['answer_type']?.toString() ?? 'text';
      if (['text', 'integer', 'decimal', 'currency_minor'].contains(type)) {
        controllers[id] = TextEditingController();
      }
    }

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(instrument['name']?.toString() ?? 'Programme check-in'),
          content: SizedBox(
            width: 560,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (instrument['description'] != null) ...[
                    Text(instrument['description'].toString()),
                    const SizedBox(height: 14),
                  ],
                  const Text(
                    'Programme answers are measurement-only and do not change your credit score, price or limit.',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 16),
                  ...questions.map((raw) {
                    final question = raw.cast<String, dynamic>();
                    final id = (question['id'] as num).toInt();
                    final type = question['answer_type']?.toString() ?? 'text';
                    final options = (question['options'] as List?)
                            ?.map((value) => value.toString())
                            .toList() ??
                        const <String>[];
                    final prompt = question['prompt']?.toString() ?? 'Question';
                    final required = question['required'] == true;
                    final fallback = question['translation_fallback'] == true;

                    Widget field;
                    if (type == 'boolean') {
                      field = DropdownButtonFormField<bool>(
                        initialValue: values[id] as bool?,
                        decoration: InputDecoration(
                          labelText: required ? prompt + ' *' : prompt,
                        ),
                        items: const [
                          DropdownMenuItem(value: true, child: Text('Yes')),
                          DropdownMenuItem(value: false, child: Text('No')),
                        ],
                        onChanged: (value) =>
                            setDialogState(() => values[id] = value),
                      );
                    } else if (type == 'single_choice') {
                      field = DropdownButtonFormField<String>(
                        initialValue: values[id]?.toString(),
                        decoration: InputDecoration(
                          labelText: required ? prompt + ' *' : prompt,
                        ),
                        items: options
                            .map(
                              (option) => DropdownMenuItem(
                                value: option,
                                child: Text(option),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setDialogState(() => values[id] = value),
                      );
                    } else if (type == 'multi_choice') {
                      final selected = ((values[id] as List?) ?? const [])
                          .map((item) => item.toString())
                          .toSet();
                      field = Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            required ? prompt + ' *' : prompt,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          ...options.map(
                            (option) => CheckboxListTile(
                              contentPadding: EdgeInsets.zero,
                              value: selected.contains(option),
                              title: Text(option),
                              onChanged: (checked) {
                                final next = {...selected};
                                if (checked == true) {
                                  next.add(option);
                                } else {
                                  next.remove(option);
                                }
                                setDialogState(
                                  () => values[id] = next.toList(),
                                );
                              },
                            ),
                          ),
                        ],
                      );
                    } else {
                      field = TextField(
                        controller: controllers[id]!,
                        keyboardType: ['integer', 'decimal', 'currency_minor']
                                .contains(type)
                            ? const TextInputType.numberWithOptions(decimal: true)
                            : TextInputType.text,
                        decoration: InputDecoration(
                          labelText: required ? prompt + ' *' : prompt,
                          helperText: question['help_text']?.toString(),
                        ),
                      );
                    }

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          field,
                          if (fallback)
                            const Padding(
                              padding: EdgeInsets.only(top: 4),
                              child: Text(
                                'English shown because a reviewed translation is not yet available.',
                                style: TextStyle(fontSize: 12),
                              ),
                            ),
                        ],
                      ),
                    );
                  }),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Not now'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Save check-in'),
            ),
          ],
        ),
      ),
    );

    if (submitted != true) {
      for (final controller in controllers.values) {
        controller.dispose();
      }
      return;
    }

    final answers = <Map<String, dynamic>>[];
    for (final raw in questions) {
      final question = raw.cast<String, dynamic>();
      final id = (question['id'] as num).toInt();
      final type = question['answer_type']?.toString() ?? 'text';
      dynamic value = values[id];

      if (controllers.containsKey(id)) {
        final rawText = controllers[id]!.text.trim();
        if (rawText.isNotEmpty) {
          value = ['integer', 'currency_minor'].contains(type)
              ? int.tryParse(rawText.replaceAll(',', '')) ?? rawText
              : type == 'decimal'
                  ? double.tryParse(rawText.replaceAll(',', '')) ?? rawText
                  : rawText;
        }
      }

      if (value != null && (!(value is List) || value.isNotEmpty)) {
        answers.add({'question_id': id, 'value': value});
      }
    }

    for (final controller in controllers.values) {
      controller.dispose();
    }

    try {
      await InclusiveFinanceApi.submitProgrammeCheckIn(
        instrumentId: (instrument['id'] as num).toInt(),
        scheduleId: scheduleId,
        answers: answers,
        locale: questions.isNotEmpty
            ? questions.first['requested_locale']?.toString()
            : null,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Programme check-in saved.')),
      );
      await _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Programme check-ins')),
        body: RefreshIndicator(
          onRefresh: _reload,
          child: FutureBuilder<Map<String, dynamic>>(
            future: _state,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return ListView(
                  children: const [
                    SizedBox(height: 220),
                    Center(child: CircularProgressIndicator()),
                  ],
                );
              }

              if (snapshot.hasError) {
                return ListView(
                  padding: const EdgeInsets.all(24),
                  children: [
                    const SizedBox(height: 80),
                    const Icon(Icons.cloud_off_outlined, size: 48),
                    const SizedBox(height: 12),
                    Text(snapshot.error.toString(), textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: _reload,
                      child: const Text('Try again'),
                    ),
                  ],
                );
              }

              final data = snapshot.data ?? {};
              final instruments = (data['instruments'] as List?)
                      ?.whereType<Map>()
                      .toList() ??
                  const [];

              return ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  const Text(
                    'Short check-ins, only when they are due',
                    style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    data['translation_policy']?.toString() ??
                        'Reviewed translations are used where available. English is the fallback.',
                  ),
                  const SizedBox(height: 18),
                  if (instruments.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(18),
                        child:
                            Text('You have no programme check-in due right now.'),
                      ),
                    )
                  else
                    ...instruments.map((raw) {
                      final instrument = raw.cast<String, dynamic>();
                      final programme =
                          (instrument['programme'] as Map?)
                                  ?.cast<String, dynamic>() ??
                              {};
                      final schedule =
                          (instrument['schedule'] as Map?)
                                  ?.cast<String, dynamic>() ??
                              {};
                      final stage = schedule['measurement_stage'] ??
                          instrument['default_measurement_stage'] ??
                          'check-in';
                      final due = schedule['due_at'];

                      return Card(
                        child: Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                instrument['name']?.toString() ??
                                    'Programme check-in',
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(programme['name']?.toString() ?? ''),
                              const SizedBox(height: 8),
                              Text(
                                'Stage: ' +
                                    stage.toString() +
                                    (due == null ? '' : ' · Due ' + due.toString()),
                              ),
                              const SizedBox(height: 12),
                              SizedBox(
                                width: double.infinity,
                                child: FilledButton(
                                  onPressed: () =>
                                      _completeInstrument(instrument),
                                  child: const Text('Complete check-in'),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    }),
                  const SizedBox(height: 16),
                  const Text(
                    'Programme answers are separated from credit decisioning. Leaving a programme does not remove historical evidence that must be retained for legitimate audit and aggregate reporting.',
                    textAlign: TextAlign.center,
                  ),
                ],
              );
            },
          ),
        ),
      );
}
