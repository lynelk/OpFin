import 'package:flutter/material.dart';
import 'package:opfin/constants.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

import 'login_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String? name, role, phone, dateOfBirth, ninStatus, nationalId, band, rating;
  int? userId, score;
  double? defaultingPercentage;
  final TextEditingController ninController = TextEditingController();

  @override
  void initState() {
    super.initState();
    getPrefs();
  }

  Future<void> getPrefs() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    setState(() {
      userId = prefs.getInt('user_id');
      name = prefs.getString('name');
      role = prefs.getString('role');
      phone = prefs.getString('phone');
      dateOfBirth = prefs.getString('date_of_birth');
      ninStatus = prefs.getString('nin_status');
      nationalId = prefs.getString('national_id');

      // Credit Score
      score = prefs.getInt('credit_score');
      band = prefs.getString('credit_band');
      rating = prefs.getString('credit_rating');
      defaultingPercentage = prefs.getDouble('defaulting_percentage');
    });
  }

  Future<void> validateNin() async {
    final prefs = await SharedPreferences.getInstance();
    final nin = ninController.text.trim();

    if (userId == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("User ID not found in preferences.")),
      );
      return;
    }
    if (nin.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Please enter a NIN.")),
      );
      return;
    }
    if (nin.length != 14) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("This field expects 14 characters.")),
      );
      return;
    }

    // Show loading indicator
    if (!mounted) return; // since this is State.context

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    try {
      final body = {
        'nin': nin,
        'user_id': userId,
      };
      String url = "$apiUrl/validate-nin";
      String? token = prefs.getString('access_token');
      final response = await http.post(
        Uri.parse(url),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode(body),
      );
      if (!mounted) return;
      Navigator.of(context).pop(); // Close loading

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        final validation = data['data'];

        await prefs.setString('national_id', validation['nin'] ?? '');
        await prefs.setString(
            'date_of_birth', validation['date_of_birth'] ?? '');
        await prefs.setString('nin_status', validation['nin_status'] ?? '');

        getPrefs(); // Refresh UI
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("NIN validated successfully.")),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['message'] ?? "Validation failed.")),
        );
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text("Error: $e")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final profileDetails = {
      'Name': name ?? '',
      'Phone Number': phone ?? '',
      'National ID': nationalId ?? '',
      'Date of Birth': dateOfBirth ?? '',
      'NIN Status': ninStatus ?? '',
      'Credit Score': score != null ? score.toString() : '',
      'Credit Band': band ?? '',
      'Credit Rating': rating ?? '',
      'Defaulting Percentage': defaultingPercentage != null
          ? "${defaultingPercentage!.toStringAsFixed(2)}%"
          : '',
    };

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black),
        title: const Text(
          "Profile",
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: Colors.black,
          ),
        ),
        centerTitle: false,
        foregroundColor: Colors.black,
      ),
      body: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // const Icon(Icons.account_circle, size: 90, color: Colors.black),
              // const SizedBox(height: 28),

              /// PROFILE DETAILS
              ...profileDetails.entries.map((entry) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        entry.key,
                        style: const TextStyle(
                          color: Colors.black,
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                            vertical: 14, horizontal: 16),
                        margin: const EdgeInsets.only(bottom: 18),
                        decoration: BoxDecoration(
                          color: Colors.grey[100],
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.grey[300]!),
                        ),
                        child: Text(
                          entry.value.isNotEmpty ? entry.value : '-',
                          style: const TextStyle(
                            color: Colors.black,
                            fontSize: 16,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  )),

              const SizedBox(height: 10),

              /// NIN VALIDATION
              if (ninStatus != 'VALID') ...[
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerLeft,
                  child: const Text(
                    'Enter NIN',
                    style: TextStyle(
                      color: Colors.black,
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: ninController,
                  decoration: InputDecoration(
                    hintText: 'CM930121003EGE',
                    hintStyle: TextStyle(color: Colors.grey[500]),
                    contentPadding: const EdgeInsets.symmetric(
                        vertical: 14, horizontal: 16),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                      borderSide: BorderSide(color: Colors.grey[300]!),
                    ),
                    filled: true,
                    fillColor: Colors.grey[100],
                  ),
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: validateNin,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.black,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: const Text(
                      'Validate NIN',
                      style:
                          TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
                const SizedBox(height: 28),
              ],

              /// LOGOUT BUTTON
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    SharedPreferences prefs =
                        await SharedPreferences.getInstance();
                    await prefs.clear();
                    if (!context.mounted) return;
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(builder: (_) => const LoginScreen()),
                      (route) => false,
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: const Text(
                    'Logout',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
              ),

              SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
