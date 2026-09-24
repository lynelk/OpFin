import 'package:flutter/material.dart';
import 'package:opfin/brand/brand_colors.dart';

class OpFinAuthScaffold extends StatelessWidget {
  const OpFinAuthScaffold({
    super.key,
    required this.eyebrow,
    required this.title,
    required this.description,
    required this.child,
    this.showBackButton = false,
  });

  final String eyebrow;
  final String title;
  final String description;
  final Widget child;
  final bool showBackButton;

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: OpFinColors.ivory,
        body: Stack(
          children: [
            const Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      OpFinColors.periwinkle,
                      OpFinColors.ivory,
                      OpFinColors.ivory,
                    ],
                    stops: [0, .48, 1],
                  ),
                ),
              ),
            ),
            Positioned(
              right: -94,
              top: -76,
              child: IgnorePointer(
                child: Opacity(
                  opacity: .055,
                  child: Image.asset(
                    'assets/brand/opfin-symbol.png',
                    width: 300,
                    excludeFromSemantics: true,
                  ),
                ),
              ),
            ),
            SafeArea(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: [
                  SizedBox(
                    height: 52,
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        if (showBackButton)
                          Align(
                            alignment: Alignment.centerLeft,
                            child: IconButton(
                              tooltip: 'Back',
                              onPressed: () => Navigator.maybePop(context),
                              icon: const Icon(Icons.arrow_back),
                            ),
                          ),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Image.asset(
                              'assets/brand/opfin-symbol.png',
                              height: 38,
                              excludeFromSemantics: true,
                            ),
                            const SizedBox(width: 10),
                            const Text(
                              'OpFin',
                              style: TextStyle(
                                color: OpFinColors.indigo,
                                fontSize: 20,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 26),
                  Align(
                    alignment: Alignment.topCenter,
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 560),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
                        decoration: BoxDecoration(
                          color: OpFinColors.white,
                          border: Border.all(color: OpFinColors.line),
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: OpFinColors.indigoStrong.withAlpha(24),
                              blurRadius: 34,
                              offset: const Offset(0, 16),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              eyebrow.toUpperCase(),
                              style: const TextStyle(
                                color: OpFinColors.indigo,
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1.15,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Semantics(
                              header: true,
                              child: Text(
                                title,
                                style: const TextStyle(
                                  color: OpFinColors.ink,
                                  fontSize: 28,
                                  height: 1.15,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              description,
                              style: const TextStyle(
                                color: OpFinColors.muted,
                                fontSize: 16,
                                height: 1.55,
                              ),
                            ),
                            const SizedBox(height: 28),
                            child,
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
}
