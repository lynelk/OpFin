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
      title: 'Understand your money',
      description: 'See what needs attention and what you can do next.',
      icon: Icons.explore_outlined,
    ),
    _OnboardModel(
      title: 'Build financial resilience',
      description: 'Save toward goals and keep important money commitments visible.',
      icon: Icons.savings_outlined,
    ),
    _OnboardModel(
      title: 'Access finance responsibly',
      description: 'Understand affordability, costs, repayments, and your options before you borrow.',
      icon: Icons.account_balance_wallet_outlined,
    ),
  ];

  Future<void> _next() async {
    if (_currentIndex < pages.length - 1) {
      await _controller.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('seenOnboarding', true);

    if (!mounted) return;

    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const RegisterScreen()),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
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
                  final page = pages[i];
                  return Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(page.icon, size: 120, color: Colors.black),
                        const SizedBox(height: 40),
                        Text(
                          page.title,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontSize: 28,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          page.description,
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
                    _currentIndex == pages.length - 1 ? 'Get Started' : 'Next',
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

  const _OnboardModel({
    required this.title,
    required this.description,
    required this.icon,
  });
}
