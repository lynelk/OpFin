import 'package:flutter/material.dart';
import 'contracts.dart';

class ClubFieldEditor extends StatelessWidget {
  const ClubFieldEditor({super.key, required this.field, required this.value, required this.onChanged,
    required this.catalogue, this.enabled = true});
  final Map<String, dynamic> field;
  final dynamic value;
  final ValueChanged<dynamic> onChanged;
  final Map<String, dynamic> catalogue;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final label = field['label']?.toString() ?? field['key'].toString();
    if (field['type'] == 'array') {
      final entries = value is List ? List<dynamic>.from(value as List) : <dynamic>[];
      return Card(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(label, style: Theme.of(context).textTheme.titleMedium),
        for (var index = 0; index < entries.length; index++)
          Padding(padding: const EdgeInsets.symmetric(vertical: 10), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('$label: row ${index + 1}'),
            if (field['items_type'] == 'integer')
              ClubFieldEditor(field: {...field, 'type': 'integer', 'key': 'member', 'required': true}, value: entries[index], catalogue: catalogue,
                enabled: enabled, onChanged: (next) { final copy = List<dynamic>.from(entries); copy[index] = next; onChanged(copy); })
            else
              for (final child in clubRows(field['items']))
                ClubFieldEditor(key: ValueKey('${field['key']}-$index-${child['key']}'), field: child,
                  value: entries[index] is Map ? (entries[index] as Map)[child['key']] : null, catalogue: catalogue, enabled: enabled,
                  onChanged: (next) { final copy = List<dynamic>.from(entries); copy[index] = {...Map<String, dynamic>.from(entries[index] as Map), child['key'] as String: next}; onChanged(copy); }),
            TextButton(onPressed: enabled ? () { final copy = List<dynamic>.from(entries)..removeAt(index); onChanged(copy); } : null,
              child: Text('Remove row ${index + 1}')),
          ])),
        OutlinedButton.icon(onPressed: !enabled || entries.length >= 1000 ? null : () => onChanged([...entries,
          field['items_type'] == 'integer' ? '' : clubEmptyFields(clubRows(field['items']))]), icon: const Icon(Icons.add), label: Text('Add $label row')),
      ])));
    }
    final options = <Map<String, String>>[];
    if (field['enum'] is List) {
      options.addAll((field['enum'] as List).map((item) => {'value': item.toString(), 'label': clubLabel(item.toString())}));
    } else if (field['source'] is String) {
      for (final row in clubRows(catalogue[field['source']])) {
        final id = field['key'] == 'account_code' ? row['code'] : row['id'] ?? row['treasury_account_id'] ?? row['user_id'];
        if (id != null && !options.any((option) => option['value'] == id.toString())) {
          options.add({'value': id.toString(), 'label': (row['name'] ?? row['account_name'] ?? row['reference'] ?? row['code'] ?? id).toString()});
        }
      }
    }
    final shown = value?.toString() ?? '';
    final decoration = InputDecoration(labelText: '$label${field['required'] == true ? ' *' : ' (optional)'}', border: const OutlineInputBorder());
    if (options.isNotEmpty) {
      return Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: DropdownButtonFormField<String>(
        key: ValueKey('${field['key']}-$shown'), initialValue: options.any((option) => option['value'] == shown) ? shown : null,
        isExpanded: true, decoration: decoration,
        items: options.map((option) => DropdownMenuItem(value: option['value'], child: Text('${option['label']} · ${option['value']}', overflow: TextOverflow.ellipsis))).toList(),
        onChanged: enabled ? (next) => onChanged(next ?? '') : null));
    }
    return Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: _ClubTextInput(
      value: shown, enabled: enabled, decoration: decoration.copyWith(
        hintText: field['type'] == 'date' ? 'YYYY-MM-DD' : field['type'] == 'month' ? 'YYYY-MM' : null),
      keyboardType: field['type'] == 'integer' ? TextInputType.number : TextInputType.text,
      maxLength: field['maxLength'] is int ? field['maxLength'] as int : 1000, onChanged: onChanged));
  }
}

class ClubDataView extends StatelessWidget {
  const ClubDataView({super.key, required this.value, this.label = 'Record'});
  final dynamic value;
  final String label;
  @override Widget build(BuildContext context) {
    if (value == null) return const Text('Not recorded');
    if (value is List) {
      final list = value as List;
      if (list.isEmpty) return const Text('No records for this selection.');
      return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [for (var i = 0; i < list.length; i++)
        ExpansionTile(title: Text('$label ${i + 1}'), childrenPadding: const EdgeInsets.all(12),
          children: [ClubDataView(value: list[i], label: label)])]);
    }
    if (value is Map) return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: (value as Map).entries.map((entry) =>
      Padding(padding: const EdgeInsets.symmetric(vertical: 6), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(clubLabel(entry.key.toString()), style: const TextStyle(fontWeight: FontWeight.w700)),
        ClubDataView(value: entry.value, label: clubLabel(entry.key.toString())),
      ]))).toList());
    return SelectableText(value is bool ? ((value as bool) ? 'Yes' : 'No') : value.toString());
  }
}

/// Controlled fields remain correct after a preceding array row is removed.
class _ClubTextInput extends StatefulWidget {
  const _ClubTextInput({required this.value, required this.enabled, required this.decoration,
    required this.keyboardType, required this.maxLength, required this.onChanged});
  final String value;
  final bool enabled;
  final InputDecoration decoration;
  final TextInputType keyboardType;
  final int maxLength;
  final ValueChanged<String> onChanged;
  @override State<_ClubTextInput> createState() => _ClubTextInputState();
}
class _ClubTextInputState extends State<_ClubTextInput> {
  late final TextEditingController controller;
  @override void initState() { super.initState(); controller = TextEditingController(text: widget.value); }
  @override void didUpdateWidget(covariant _ClubTextInput oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (controller.text != widget.value) {
      controller.value = TextEditingValue(text: widget.value,
        selection: TextSelection.collapsed(offset: widget.value.length));
    }
  }
  @override void dispose() { controller.dispose(); super.dispose(); }
  @override Widget build(BuildContext context) => TextFormField(controller: controller, enabled: widget.enabled,
    decoration: widget.decoration, keyboardType: widget.keyboardType,
    maxLength: widget.maxLength, onChanged: widget.onChanged);
}
