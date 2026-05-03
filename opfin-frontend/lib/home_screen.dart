import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/faq_screen.dart';
import 'package:opfin/loan_applications_screen.dart';
import 'package:opfin/products_screen.dart';
import 'package:opfin/profile_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:opfin/constants.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  HomeScreenState createState() => HomeScreenState();
}

class HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;

  static final List<Widget> _pages = <Widget>[
    const HomeWidget(),
    const LoanApplicationsScreen(),
    const ProfileScreen(),
    const FaqsScreen(),
  ];

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black),
        title: const Text(
          'OpFin',
          style: TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
        ),
        centerTitle: false,
        foregroundColor: Colors.black,
      ),
      body: _pages[_selectedIndex],
      bottomNavigationBar: BottomNavigationBar(
        backgroundColor: Colors.white,
        selectedItemColor: Colors.black,
        unselectedItemColor: Colors.grey[500],
        selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold),
        unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.normal),
        items: const <BottomNavigationBarItem>[
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(
            icon: Icon(Icons.assignment),
            label: 'Applications',
          ),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: 'Profile'),
          BottomNavigationBarItem(
            icon: Icon(Icons.help_outline),
            label: 'FAQs',
          ),
        ],
        currentIndex: _selectedIndex,
        onTap: _onItemTapped,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),
    );
  }
}

class HomeWidget extends StatefulWidget {
  const HomeWidget({super.key});

  @override
  State<HomeWidget> createState() => _HomeWidgetState();
}

class _HomeWidgetState extends State<HomeWidget> {
  Future<Map<String, int>>? _statsFuture;
  String? _userName;
  bool _isVerified = false;
  int _balance = 0;
  String? _phoneNumber;
  final formatter = NumberFormat('#,##0', 'en_US');

  List<Map<String, dynamic>> _recentApplications = [];

  @override
  void initState() {
    super.initState();
    _statsFuture = fetchUserStats();
    _loadUserInfo();
    getLoanBalance();
  }

  Future<void> _loadUserInfo() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    setState(() {
      _userName = prefs.getString('name') ?? "Guest User";
      _isVerified = prefs.getString('nin_status') == 'VALID';
      _phoneNumber = prefs.getString('phone') ?? "";
    });
  }

  Future<Map<String, int>> fetchUserStats() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    int? userId = prefs.getInt('user_id');
    String? token = prefs.getString('access_token');
    final response = await http.get(
      Uri.parse('$apiUrl/loan-applications/$userId'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
    if (response.statusCode == 200) {
      final data = jsonDecode(response.body)['data'] as List;
      int total = data.length;
      int disbursed = data.where((a) => a['status'] == 'Disbursed').length;
      int rejected = data.where((a) => a['status'] == 'Rejected').length;
      int pending = data.where((a) => a['status'] == 'Pending').length;
      _recentApplications = data.take(5).cast<Map<String, dynamic>>().toList();
      setState(() {
        _recentApplications = _recentApplications;
      });
      return {
        'total': total,
        'disbursed': disbursed,
        'rejected': rejected,
        'pending': pending,
      };
    } else {
      throw Exception('Failed to load stats');
    }
  }

  Future<void> getLoanBalance() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    int? userId = prefs.getInt('user_id');
    String? token = prefs.getString('access_token');
    final response = await http.get(
      Uri.parse('$apiUrl/loan-balance/$userId'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
    if (response.statusCode == 200) {
      final Map<String, dynamic> data = jsonDecode(response.body);
      setState(() {
        _balance = data['outstandingAmount'];
      });
    } else {
      throw Exception('Failed to load loan balance');
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 40),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 🔹 Greeting section
            Row(
              children: [
                const CircleAvatar(
                  radius: 28,
                  backgroundColor: Colors.black,
                  child: Icon(Icons.person, color: Colors.white, size: 28),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            "Hi, $_userName",
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 22,
                              color: Colors.black,
                            ),
                          ),
                          const SizedBox(width: 8),
                          if (_isVerified)
                            const Icon(Icons.verified,
                                color: Colors.green, size: 22),
                        ],
                      ),
                      if (_phoneNumber != null && _phoneNumber!.isNotEmpty)
                        Text(
                          _phoneNumber!,
                          style: const TextStyle(
                            fontSize: 14,
                            color: Colors.black54,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 32),

            // 🔹 Outstanding Balance Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              margin: const EdgeInsets.only(bottom: 24),
              decoration: BoxDecoration(
                color: (_balance > 0 ? Colors.red : Colors.green)
                    .withValues(alpha: .08),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: (_balance > 0 ? Colors.red : Colors.green)
                      .withValues(alpha: .25),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.account_balance_wallet,
                    color: (_balance > 0 ? Colors.red : Colors.green),
                    size: 30,
                  ),
                  const SizedBox(width: 14),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        "Outstanding Loan Balance",
                        style: TextStyle(fontSize: 14, color: Colors.black54),
                      ),
                      Text(
                        "${formatter.format(_balance)}/=",
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: (_balance > 0 ? Colors.red : Colors.green),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // 🔹 Loan Stats
            const Text(
              'Loan Applications',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 18,
                color: Colors.black,
              ),
            ),
            const SizedBox(height: 16),

            FutureBuilder<Map<String, int>>(
              future: _statsFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: Center(
                      child: CircularProgressIndicator(color: Colors.black),
                    ),
                  );
                } else if (snapshot.hasError) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: Text(
                      "Failed to load stats.",
                      style: TextStyle(
                        color: Colors.red,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  );
                } else if (!snapshot.hasData) {
                  return const SizedBox();
                }

                final stats = snapshot.data!;

                return GridView.count(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  crossAxisCount: 2,
                  crossAxisSpacing: 16,
                  mainAxisSpacing: 16,
                  childAspectRatio: 2.2,
                  children: [
                    _QuickInfoCard(
                      label: "Total",
                      value: "${stats['total'] ?? 0}",
                      icon: Icons.list_alt,
                      color: const Color(0xFF0D47A1), // Bold Royal Blue
                    ),
                    _QuickInfoCard(
                      label: "Pending",
                      value: "${stats['pending'] ?? 0}",
                      icon: Icons.hourglass_empty,
                      color: const Color(0xFFF9A825), // Bold Gold
                    ),
                    _QuickInfoCard(
                      label: "Disbursed",
                      value: "${stats['disbursed'] ?? 0}",
                      icon: Icons.check_circle,
                      color: const Color(0xFF2E7D32), // Bold Green
                    ),
                    _QuickInfoCard(
                      label: "Rejected",
                      value: "${stats['rejected'] ?? 0}",
                      icon: Icons.cancel,
                      color: const Color(0xFFC62828), // Bold Red
                    ),
                  ],
                );
              },
            ),

            const SizedBox(height: 32),

            // 🔹 Recent Applications
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  "Recent Applications",
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 18,
                    color: Colors.black,
                  ),
                ),
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(
                        vertical: 12, horizontal: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  icon: const Icon(Icons.monetization_on_outlined,
                      color: Colors.white),
                  label: const Text(
                    'Apply',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ProductsScreen(),
                      ),
                    );
                  },
                )
              ],
            ),
            const SizedBox(height: 12),

            if (_recentApplications.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 10),
                child: Text(
                  "You haven't made any loan applications yet.",
                  style: TextStyle(color: Colors.grey),
                ),
              ),

            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _recentApplications.length,
              itemBuilder: (context, index) {
                final app = _recentApplications[index];
                return Container(
                  margin: const EdgeInsets.symmetric(vertical: 6),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  decoration: BoxDecoration(
                    color: Colors.grey[100],
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey[300]!),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.assignment,
                          color: Colors.black, size: 28),
                      const SizedBox(width: 12),

                      // Title + status
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              "Application #${app['id']}",
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 15,
                                color: Colors.black,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              "${app['status']}",
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: app['status'] == 'Rejected'
                                    ? Colors.red
                                    : app['status'] == 'Pending'
                                        ? Colors.orange
                                        : app['status'] == 'Disbursed'
                                            ? Colors.blue
                                            : Colors.green,
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Amount
                      Text(
                        app['amount'] != null
                            ? "${formatter.format(int.parse(app['amount']))}/="
                            : "",
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: Colors.black,
                          fontSize: 16,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickInfoCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _QuickInfoCard({
    required this.label,
    this.value = '0',
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: .3)),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                    color: color,
                  ),
                ),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 10,
                    color: Colors.black87,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
