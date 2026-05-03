import 'package:flutter/material.dart';

class ButtonStyles {
  /// A reusable elevated button style for consistent design.
  static ButtonStyle customButtonStyle() {
    return ElevatedButton.styleFrom(
      backgroundColor: Colors.black, // Button background color
      foregroundColor: Colors.white, // Text color
      padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12.0),
      ),
      elevation: 5,
      textStyle: const TextStyle(
        fontSize: 16.0,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  /// A reusable Text Button style for consistent design.
  static ButtonStyle customTextButtonStyle() {
    return TextButton.styleFrom(
      foregroundColor: Colors.black, // Text color
      padding: const EdgeInsets.symmetric(vertical: 12.0),
      textStyle: const TextStyle(
        fontSize: 14.0,
        fontWeight: FontWeight.w600,
      ),
    );
  }
}
