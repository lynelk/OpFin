@extends('layouts.app')

@section('title', 'Privacy Policy - ' . config('app.name'))

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h1 class="h4 mb-0">Privacy Policy</h1>
                    </div>

                    <div class="card-body">
                        <p class="text-muted">Last updated: {{ now()->format('F j, Y') }}</p>

                        <div class="policy-content">
                            <h2 class="h5 mt-4">1. Introduction</h2>
                            <p>{{ config('app.name') }} ("we," "our," or "us") is committed to protecting your privacy.
                                This Privacy Policy explains how we collect, use, share, and protect your information when
                                you use our loan services through the mobile application.</p>

                            <h2 class="h5 mt-4">2. Information We Collect</h2>
                            <p>We may collect the following types of personal data:</p>
                            <ul>
                                <li>Personal details (name, date of birth, national ID/passport)</li>
                                <li>Contact details (phone number, email, address)</li>
                                <li>Employment and financial information (salary, bank account, credit/loan history)</li>
                                <li>Device and usage data (IP address, app activity, device type)</li>
                            </ul>

                            <h2 class="h5 mt-4">3. How We Use Your Information</h2>
                            <p>Your personal information is used for the following purposes:</p>
                            <ul>
                                <li>To process and evaluate loan applications</li>
                                <li>To verify identity (KYC) and comply with legal obligations (AML/CFT)</li>
                                <li>To manage your account and transactions</li>
                                <li>To communicate updates and service-related messages</li>
                                <li>To improve our services and perform analytics</li>
                            </ul>

                            <h2 class="h5 mt-4">4. Sharing of Information</h2>
                            <p>We may share your data with:</p>
                            <ul>
                                <li>Regulatory authorities as required by law</li>
                                <li>Credit reference bureaus</li>
                                <li>Third-party service providers (payment processors, KYC verification partners)</li>
                            </ul>
                            <p>We do not sell your personal data to third parties.</p>

                            <h2 class="h5 mt-4">5. Data Retention</h2>
                            <p>We retain your personal data for as long as necessary to comply with legal, tax, and
                                regulatory obligations.
                                Loan and financial records are kept for at least 7 years as required by law.</p>

                            <h2 class="h5 mt-4">6. Data Security</h2>
                            <p>We implement appropriate technical and organizational measures to protect your personal
                                information from unauthorized access, loss, misuse, or disclosure.</p>

                            <h2 class="h5 mt-4">7. Your Rights</h2>
                            <p>You may have the following rights under applicable law:</p>
                            <ul>
                                <li>Access, update, or correct your personal data</li>
                                <li>Request deletion of your personal data (subject to legal obligations)</li>
                                <li>Restrict or object to certain processing activities</li>
                                <li>Request a copy of your data (data portability)</li>
                            </ul>

                            <h2 class="h5 mt-4">8. Account Deletion</h2>
                            <p>You may request to delete your account at any time. When your account is deleted:</p>
                            <ul>
                                <li>Your personal information will be anonymized where possible</li>
                                <li>Financial and legal records will be retained as required by law</li>
                                <li>Pending loan applications or transactions will be cancelled</li>
                            </ul>
                            <p><a href="{{ route('account.delete') }}" class="btn btn-sm btn-outline-danger">Delete My
                                    Account</a></p>

                            <h2 class="h5 mt-4">9. International Data Transfers</h2>
                            <p>We do not transfer your personal data outside Uganda unless required for processing payments
                                or storage, and only with adequate safeguards in place.</p>

                            <h2 class="h5 mt-4">10. Updates to This Policy</h2>
                            <p>We may update this Privacy Policy from time to time. The updated version will be posted on
                                this page with a revised "Last updated" date.</p>

                            <h2 class="h5 mt-4">11. Contact Us</h2>
                            <p>If you have any questions about this Privacy Policy, please contact us:</p>
                            <address>
                                {{ config('app.name') }}<br>
                                Kampala, Uganda<br>
                                Email: {{ config('app.email') }}
                            </address>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
