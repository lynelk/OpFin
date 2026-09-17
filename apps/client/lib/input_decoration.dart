import 'package:opfin/brand/brand_colors.dart';
import 'package:flutter/material.dart';

class InputDecorations {
  InputDecoration inputStyle({
    required String label,
    required IconData icon,
    required String hint,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon, color: OpFinColors.ink),
      labelStyle: const TextStyle(color: OpFinColors.ink),
      hintStyle: const TextStyle(color: OpFinColors.muted),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: OpFinColors.line, width: 1.2),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: OpFinColors.ink, width: 1.4),
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
      ),
    );
  }
}
