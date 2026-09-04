<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - Admin Panel</title>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    {{-- Custom Styles --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body class="d-flex" style="min-height:100vh;">
    {{-- Sidebar --}}
    <div class="sidebar d-flex flex-column p-3">
        <h5 class="mb-4">
            <a href="{{ url('/') }}" class="text-white text-decoration-none">Loan System</a>
        </h5>

        @auth
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="{{ route('home') }}" class="nav-link {{ request()->is('home*') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->is('users*') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="#"
                        class="nav-link dropdown-toggle {{ request()->is('loan-applications*') ? 'active' : '' }}"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-file-earmark-text me-2"></i> Applications
                    </a>

                    <ul class="dropdown-menu sidebar-dropdown">
                        <li>
                            <a class="dropdown-item {{ request()->is('loan-applications/disbursed') ? 'active' : '' }}"
                                href="{{ route('loan-applications.index', ['status' => 'Disbursed']) }}">
                                Disbursed
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->is('loan-applications/rejected') ? 'active' : '' }}"
                                href="{{ route('loan-applications.index', ['status' => 'Rejected']) }}">
                                Rejected
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="{{ route('loan-products.index') }}"
                        class="nav-link {{ request()->is('loan-products*') ? 'active' : '' }}">
                        <i class="bi bi-cash-coin me-2"></i> Loan Products
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle {{ request()->is('loans*') ? 'active' : '' }}"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-wallet2 me-2"></i> Loans
                    </a>

                    <ul class="dropdown-menu sidebar-dropdown">
                        <li>
                            <a class="dropdown-item {{ request()->is('loans/disbursed') ? 'active' : '' }}"
                                href="{{ route('loans.index', ['status' => 'Disbursed']) }}">
                                Disbursed
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->is('loans/cleared') ? 'active' : '' }}"
                                href="{{ route('loans.index', ['status' => 'Cleared']) }}">
                                Cleared
                            </a>
                        </li>
                    </ul>
                </li>


                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle {{ request()->is('transactions*') ? 'active' : '' }}"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-arrow-left-right me-2"></i> Transactions
                    </a>

                    <ul class="dropdown-menu sidebar-dropdown">
                        <li>
                            <a class="dropdown-item {{ request()->is('transactions/successful') ? 'active' : '' }}"
                                href="{{ route('transactions.index', ['status' => 'Successful']) }}">
                                Successful
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->is('transactions/pending') ? 'active' : '' }}"
                                href="{{ route('transactions.index', ['status' => 'Pending']) }}">
                                Pending
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->is('transactions/failed') ? 'active' : '' }}"
                                href="{{ route('transactions.index', ['status' => 'Failed']) }}">
                                Failed
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="{{ route('accounts.index') }}"
                        class="nav-link {{ request()->is('accounts*') ? 'active' : '' }}">
                        <i class="bi bi-bank me-2"></i> Accounts
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('chat.index') }}" class="nav-link {{ request()->is('chats*') ? 'active' : '' }}">
                        <i class="bi bi-chat-dots me-2"></i> Chat
                    </a>
                </li>

                {{-- <li class="nav-item">
                    <a href="{{ route('float-management.index') }}"
                        class="nav-link {{ request()->is('float-management*') ? 'active' : '' }}">
                        <i class="bi bi-droplet me-2"></i> Float MGT
                    </a>
                </li> --}}

                @if (Auth::user()->role == 'Super')
                    <li class="nav-item">
                        <a href="{{ route('institutions.index') }}"
                            class="nav-link {{ request()->is('institutions*') ? 'active' : '' }}">
                            <i class="bi bi-buildings me-2"></i> Institutions
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#"
                            class="nav-link dropdown-toggle {{ request()->is('sms-messages*') ? 'active' : '' }}"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-chat-left-text me-2"></i> SMS Messages
                        </a>

                        <ul class="dropdown-menu sidebar-dropdown">
                            <li>
                                <a class="dropdown-item {{ request()->is('sms-messages/sent') ? 'active' : '' }}"
                                    href="{{ route('sms-messages.index', ['status' => 'Sent']) }}">
                                    Sent
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('sms-messages/pending') ? 'active' : '' }}"
                                    href="{{ route('sms-messages.index', ['status' => 'Pending']) }}">
                                    Pending
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ request()->is('sms-messages/failed') ? 'active' : '' }}"
                                    href="{{ route('sms-messages.index', ['status' => 'Failed']) }}">
                                    Failed
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif
            </ul>
        @endauth
    </div>

    {{-- Main content --}}
    <div class="main-content d-flex flex-column flex-grow-1" style="min-height:100vh;">
        {{-- Topbar --}}
        <div class="topbar px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">@yield('title')</h5>
            @auth
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle"
                        id="userDropdown" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i>
                        {{ Auth::user()->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item" type="submit">Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth
        </div>

        {{-- Page content --}}
        <div class="container-fluid py-4 px-4 d-flex flex-column flex-grow-1"> @yield('content')
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @yield('scripts')
</body>

</html>
