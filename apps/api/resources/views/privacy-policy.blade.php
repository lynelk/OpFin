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
                        <p class="text-muted">Last updated: September 3, 2026</p>

                        <h2 class="h5 mt-4">1. Scope</h2>
                        <p>{{ config('app.name') }} provides financial wellbeing, responsible credit, savings, protection, investment and peer-lending journeys. This policy explains how personal information is collected, used, shared, retained and deleted.</p>

                        <h2 class="h5 mt-4">2. Information we collect</h2>
                        <ul>
                            <li>Identity and contact information, including name, phone number and identity-verification evidence.</li>
                            <li>Financial, employment, affordability, credit, transaction and repayment information supplied by you or permitted providers.</li>
                            <li>Consent, security, support, device and service-usage information required to operate and protect the service.</li>
                            <li>Information about savings, protection, investments or peer-lending relationships when those products are activated.</li>
                        </ul>

                        <h2 class="h5 mt-4">3. How we use information</h2>
                        <ul>
                            <li>Provide and secure your account and requested financial services.</li>
                            <li>Verify identity, assess eligibility and affordability, service obligations and provide customer support.</li>
                            <li>Process payments through disclosed payment providers and reconcile transaction evidence.</li>
                            <li>Meet legal, regulatory, accounting, fraud-prevention, credit-reporting and consumer-protection obligations.</li>
                            <li>Improve service quality using information lawfully available for that purpose.</li>
                        </ul>

                        <h2 class="h5 mt-4">4. Sharing</h2>
                        <p>Information is shared only where necessary with disclosed payment, identity, credit-reference, savings, insurance, investment, lending, infrastructure and professional-service providers, or with public authorities where required by law. OpFin does not sell personal information to advertisers.</p>

                        <h2 class="h5 mt-4">5. Retention</h2>
                        <p>Personal information is retained only for as long as required for the purpose for which it was collected and for applicable legal, regulatory, accounting, fraud-prevention, dispute and audit obligations. Different record categories can therefore have different retention periods. When a retention period ends, information is deleted or irreversibly anonymized according to the applicable retention schedule.</p>

                        <h2 class="h5 mt-4">6. Your choices and rights</h2>
                        <ul>
                            <li>Review or correct appropriate account information.</li>
                            <li>Review and revoke purpose-specific consent where the law and product permit.</li>
                            <li>Request access, restriction, deletion or other data-subject rights available under applicable law.</li>
                            <li>Delete your OpFin account from the web or mobile application.</li>
                        </ul>

                        <h2 class="h5 mt-4">7. Account deletion</h2>
                        <p>You can initiate account deletion in OpFin under <strong>More → Delete account</strong>. You must re-enter your current password and explicitly confirm deletion.</p>
                        <p>If there are no active regulated or financial obligations, the active account is deleted, optional customer context is removed, active consents are revoked and direct profile identifiers are anonymized. Records that must be retained for legal, regulatory, accounting, credit-reporting, fraud-prevention, dispute or audit purposes are isolated from active use and retained only for the required period.</p>
                        <p>If an active loan, savings position, protection policy, peer-lending position or other regulated obligation prevents immediate closure, OpFin records an account-deletion case and keeps the account available only as needed to service or close that obligation safely. The deletion request does not need to be submitted again.</p>
                        <p><a href="{{ route('account.delete') }}" class="btn btn-sm btn-outline-danger">Web account deletion</a></p>

                        <h2 class="h5 mt-4">8. Security</h2>
                        <p>OpFin uses access controls, encrypted transport, protected credentials, audit evidence and financial integrity controls designed to protect customer information and transactions.</p>

                        <h2 class="h5 mt-4">9. International processing</h2>
                        <p>Where a service provider processes information outside Uganda, OpFin applies the contractual, legal and technical safeguards required for that processing.</p>

                        <h2 class="h5 mt-4">10. Contact</h2>
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
@endsection
