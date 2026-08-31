import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:opfin/connected_financial_life_screen.dart';
import 'package:opfin/faq_screen.dart';
import 'package:opfin/loan_applications_screen.dart';
import 'package:opfin/products_screen.dart';
import 'package:opfin/profile_screen.dart';
import 'package:opfin/services/user_session.dart';
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
    const ConnectedFinancialLifeScreen(),
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
          BottomNavigationBarItem(icon: Icon(Icons.assignment), label: 'Borrow'),
          BottomNavigationBarItem(icon: Icon(Icons.hub_outlined), label: 'Connected'),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: 'Profile'),
          BottomNavigationBarItem(icon: Icon(Icons.help_outline), label: 'Help'),
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
    final prefs = await SharedPreferences.getInstance();
    final phone = await UserSession.getPhone();
    final ninStatus = await UserSession.getNinStatus();
    if (!mounted) return;
    setState(() {
      _userName = prefs.getString('name') ?? "Guest User";
      _isVerified = ninStatus == 'VALID';
      _phoneNumber = phone ?? "";
    });
  }

  Future<Map<String, int>> fetchUserStats() async {
    final userId = await UserSession.getUserId();
    final token = await UserSession.getAccessToken();
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
      final recent = data.take(5).cast<Map<String, dynamic>>().toList();
      if (mounted) {
        setState(() {
          _recentApplications = recent;
        });
      }
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
    final userId = await UserSession.getUserId();
    final token = await UserSession.getAccessToken();
    final response = await http.get(
      Uri.parse('$apiUrl/loan-balance/$userId'),
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
    if (response.statusCode == 200) {
      final Map<String, dynamic> data = jsonDecode(response.body);
      if (!mounted) return;
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

            const Text(
              "Loan Applications",
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 20,
                color: Colors.black,
              ),
            ),
            const SizedBox(height: 16),
            FutureBuilder<Map<String, int>>(
              future: _statsFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                } else if (snapshot.hasError) {
                  return Text("Error: ${snapshot.error}");
                } else if (snapshot.hasData) {
                  final stats = snapshot.data!;
                  return Row(
                    children: [
                      _buildStatCard("Total", stats['total'] ?? 0, Colors.blue),
                      const SizedBox(width: 8),
                      _buildStatCard("Pending", stats['pending'] ?? 0, Colors.orange),
                      const SizedBox(width: 8),
                      _buildStatCard("Disbursed", stats['disbursed'] ?? 0, Colors.green),
                      const SizedBox(width: 8),
                      _buildStatCard("Rejected", stats['rejected'] ?? 0, Colors.red),
                    ],
                  );
                }
                return const SizedBox();
              },
            ),

            const SizedBox(height: 32),
            const Text(
              "Recent Applications",
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 20),
            ),
            const SizedBox(height: 12),
            if (_recentApplications.isEmpty)
              const Text("No recent applications.")
            else
              ..._recentApplications.map(
                (app) => Card(
                  margin: const EdgeInsets.symmetric(vertical: 6),
                  child: ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text("Application #${app['id'] ?? ''}"),
                    subtitle: Text("Status: ${app['status'] ?? 'Unknown'}"),
                  ),
                ),
              ),

            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const ProductsScreen()),
                  );
                },
                icon: const Icon(Icons.add),
                label: const Text("Apply for a Loan"),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  foregroundColor: Colors.white,
                  backgroundColor: Colors.black,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatCard(String label, int count, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: .2)),
        ),
        child: Column(
          children: [
            Text(
              "$count",
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: const TextStyle(fontSize: 12, color: Colors.black54),
            ),
          ],
        ),
      ),
    );
  }
}
