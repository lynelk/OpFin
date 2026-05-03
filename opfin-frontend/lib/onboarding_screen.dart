import 'package:flutter/material.dart';
import 'package:opfin/register_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _controller = PageController();
  int _currentIndex = 0;

  final List<_OnboardModel> pages = [
    _OnboardModel(
      title: "Fast & Easy Loans",
      description: "Apply for loans instantly with a seamless process.",
      icon: Icons.speed,
    ),
    _OnboardModel(
      title: "Track Everything",
      description: "Monitor repayments, interest, and deadlines easily.",
      icon: Icons.track_changes,
    ),
    _OnboardModel(
      title: "Secure & Reliable",
      description: "Your financial data is protected at all times.",
      icon: Icons.security,
    ),
  ];

  void _next() async {
    if (_currentIndex < pages.length - 1) {
      _controller.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      await prefs.setBool("seenOnboarding", true);
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const RegisterScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: pages.length,
                onPageChanged: (i) => setState(() => _currentIndex = i),
                itemBuilder: (_, i) {
                  final p = pages[i];
                  return Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(p.icon, size: 120, color: Colors.black),
                        const SizedBox(height: 40),
                        Text(
                          p.title,
                          style: const TextStyle(
                            fontSize: 28,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          p.description,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontSize: 18,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),

            // Dot Indicators
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(
                pages.length,
                (i) => AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  margin: const EdgeInsets.symmetric(horizontal: 5),
                  height: 10,
                  width: _currentIndex == i ? 28 : 10,
                  decoration: BoxDecoration(
                    color: _currentIndex == i ? Colors.black : Colors.grey,
                    borderRadius: BorderRadius.circular(20),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 30),

            // Next / Get Started Button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _next,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    backgroundColor: Colors.black,
                  ),
                  child: Text(
                    _currentIndex == pages.length - 1 ? "Get Started" : "Next",
                    style: const TextStyle(fontSize: 18),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }
}

class _OnboardModel {
  final String title;
  final String description;
  final IconData icon;

  _OnboardModel(
      {required this.title, required this.description, required this.icon});
}
