import 'dart:convert';
import 'dart:math';

String clubRequestKey() {
  final random = Random.secure();
  return 'club-${List.generate(16, (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0')).join()}';
}
String clubDate(DateTime date) => '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
String clubToday() => clubDate(DateTime.now().toUtc().add(const Duration(hours: 3)));
String clubLabel(String value) => value.replaceAll('_', ' ').replaceAll('minor', '(minor units)').replaceAll('micro', '(micro-units)');
List<Map<String, dynamic>> clubRows(dynamic value) => value is List ? value.whereType<Map>().map((row) => row.cast<String, dynamic>()).toList() : [];
int clubInteger(dynamic value, [int minimum = 0]) {
  final text = (value ?? '').toString().trim();
  if (!RegExp(r'^[0-9]+$').hasMatch(text)) throw const FormatException('Enter a whole number without commas or decimals.');
  final number = int.tryParse(text);
  if (number == null || number < minimum || number > 9007199254740991) throw const FormatException('The number is outside the supported range.');
  return number;
}
String clubIsoDate(String text) {
  final parsed = DateTime.tryParse(text);
  if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text) || parsed == null || clubDate(parsed) != text) {
    throw const FormatException('Choose a real calendar date.');
  }
  return text;
}
Map<String, dynamic> clubEmptyFields(List<Map<String, dynamic>> fields) => {
  for (final field in fields) field['key'] as String: field['type'] == 'array' ? <dynamic>[] : '',
};
Map<String, dynamic> clubValidate(List<Map<String, dynamic>> fields, Map<String, dynamic> raw) {
  final result = <String, dynamic>{};
  for (final field in fields) {
    final key = field['key'] as String;
    final value = raw[key];
    final label = field['label']?.toString() ?? key;
    if (value == null || value == '') {
      if (field['required'] == true) throw FormatException('$label is required.');
      continue;
    }
    switch (field['type']) {
      case 'array':
        if (value is! List || value.length > 1000) throw FormatException('$label has invalid or too many rows.');
        result[key] = value.map((row) {
          if (field['items_type'] == 'integer') return clubInteger(row, 1);
          if (row is! Map) throw FormatException('Invalid $label row.');
          return clubValidate(clubRows(field['items']), row.cast<String, dynamic>());
        }).toList();
        break;
      case 'integer':
        final number = clubInteger(value, field['minimum'] is int ? field['minimum'] as int : 0);
        if (field['maximum'] is num && number > (field['maximum'] as num)) throw FormatException('$label is too large.');
        result[key] = number;
        break;
      case 'string': case 'date': case 'month':
        if (value is! String) throw FormatException('$label must be text.');
        final text = value.trim();
        if (text.length > (field['maxLength'] is int ? field['maxLength'] as int : 1000)) throw FormatException('$label is too long.');
        if (field['enum'] is List && !(field['enum'] as List).contains(text)) throw FormatException('Choose a supported $label.');
        if (field['type'] == 'date') clubIsoDate(text);
        if (field['type'] == 'month') clubIsoDate('$text-01');
        result[key] = text;
        break;
      default: throw FormatException('Unsupported form field: $label. Refresh the application contract.');
    }
  }
  for (final key in raw.keys) {
    if (!fields.any((field) => field['key'] == key)) throw FormatException('Unsupported field: $key');
  }
  return result;
}
Map<String, dynamic> clubCopy(Map<String, dynamic> value) => (jsonDecode(jsonEncode(value)) as Map).cast<String, dynamic>();
