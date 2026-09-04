@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="container-fluid px-4">
        <h1 class="mt-4">Dashboard</h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">Overview</li>
        </ol>

        <div class="row">
            <div class="col-xl-12 col-md-12">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-normal">Money movement boundary</h6>
                                <h4 class="mb-0">{{ $paymentBoundary }}</h4>
                                <small>Provider balances and payment-rail operations are managed in CPay, not OpFin.</small>
                            </div>
                            <i class="bi bi-shield-check fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><h6 class="fw-normal">Total Institutions</h6><h4 class="mb-0">{{ $institutionCount }}</h4></div>
                            <i class="bi bi-buildings fs-1"></i>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between"><a class="small text-white stretched-link" href="{{ route('institutions.index') }}">View Details</a><div class="small text-white"><i class="bi bi-chevron-right"></i></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-success text-white mb-4">
                    <div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="fw-normal">Total Loans</h6><h4 class="mb-0">{{ $loansCount }}</h4></div><i class="bi bi-check-circle fs-1"></i></div></div>
                    <div class="card-footer d-flex align-items-center justify-content-between"><a class="small text-white stretched-link" href="{{ route('loans.index') }}">View Details</a><div class="small text-white"><i class="bi bi-chevron-right"></i></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-info text-white mb-4">
                    <div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="fw-normal">Total Users</h6><h4 class="mb-0">{{ $userCount }}</h4></div><i class="bi bi-people fs-1"></i></div></div>
                    <div class="card-footer d-flex align-items-center justify-content-between"><a class="small text-white stretched-link" href="{{ route('users.index') }}">View Details</a><div class="small text-white"><i class="bi bi-chevron-right"></i></div></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-dark text-white mb-4">
                    <div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="fw-normal">Loan Products</h6><h4 class="mb-0">{{ $loanProductCount }}</h4></div><i class="bi bi-cash-coin fs-1"></i></div></div>
                    <div class="card-footer d-flex align-items-center justify-content-between"><a class="small text-white stretched-link" href="{{ route('loan-products.index') }}">View Details</a><div class="small text-white"><i class="bi bi-chevron-right"></i></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-buildings me-1"></i>Recent Applications</div>
                    <div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Name</th><th>Status</th><th>Created</th></tr></thead><tbody>
                    @foreach ($recentApplications as $application)
                        <tr><td>{{ $application->user?->name }}</td><td><span class="badge bg-{{ $application->status == 'Disbursed' ? 'success' : ($application->status == 'Rejected' ? 'danger' : ($application->status == 'Pending' ? 'secondary' : 'info')) }}">{{ $application->status }}</span></td><td>{{ $application->created_at->format('M d, Y') }}</td></tr>
                    @endforeach
                    </tbody></table></div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-people me-1"></i>Recent Transactions</div>
                    <div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Name</th><th>Amount</th><th>Type</th><th>Status</th><th>Created</th></tr></thead><tbody>
                    @foreach ($recentTransactions as $transaction)
                        <tr><td>{{ $transaction->user?->name }}</td><td>{{ $transaction->amount }}</td><td>{{ $transaction->type }}</td><td><span class="badge bg-{{ $transaction->status == 'PENDING' ? 'secondary' : ($transaction->status == 'SUCCESSFUL' ? 'success' : ($transaction->status == 'FAILED' ? 'danger' : 'info')) }}">{{ $transaction->status }}</span></td><td>{{ $transaction->created_at?->format('M d, Y') }}</td></tr>
                    @endforeach
                    </tbody></table></div></div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100"><div class="card-header bg-white"><div class="d-flex justify-content-between align-items-center"><h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-people me-1"></i>User Growth (Last 6 Months)</h6><button class="btn btn-sm btn-outline-primary refresh-user-chart"><i class="bi bi-arrow-clockwise"></i></button></div></div><div class="card-body"><canvas id="userGrowthChart" height="250"></canvas></div></div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100"><div class="card-header bg-white"><div class="d-flex justify-content-between align-items-center"><h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-cash-stack me-1"></i>Loans Disbursed (Last 6 Months)</h6><button class="btn btn-sm btn-outline-primary refresh-loans-chart"><i class="bi bi-arrow-clockwise"></i></button></div></div><div class="card-body"><canvas id="loansDisbursedChart" height="250"></canvas></div></div>
            </div>
        </div>

    @section('scripts')
        <script>
            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
            const loansDisbursedCtx = document.getElementById('loansDisbursedChart').getContext('2d');
            const userGrowthChart = new Chart(userGrowthCtx, {type: 'bar', data: {labels: [], datasets: []}, options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}});
            const loansDisbursedChart = new Chart(loansDisbursedCtx, {type: 'bar', data: {labels: [], datasets: [{label: 'Number of Loans', data: [], backgroundColor: 'rgba(54, 162, 235, 0.7)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1, yAxisID: 'y'}, {label: 'Total Amount', data: [], backgroundColor: 'rgba(75, 192, 192, 0.7)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1, type: 'line', yAxisID: 'y1'}]}, options: {responsive: true, interaction: {mode: 'index', intersect: false}, scales: {y: {type: 'linear', display: true, position: 'left', beginAtZero: true}, y1: {type: 'linear', display: true, position: 'right', beginAtZero: true, grid: {drawOnChartArea: false}}}}});
            function loadLoansDisbursedData() { fetch('{{ route('home.loans-disbursed.chart') }}').then(response => response.json()).then(data => { loansDisbursedChart.data.labels = data.labels; loansDisbursedChart.data.datasets[0].data = data.counts; loansDisbursedChart.data.datasets[1].data = data.amounts; loansDisbursedChart.update(); }); }
            function loadUserGrowthData() { fetch('{{ route('home.user-growth.chart') }}').then(response => response.json()).then(data => { userGrowthChart.data = {labels: data.labels, datasets: [{label: 'New Users', data: data.data, backgroundColor: 'rgba(54, 162, 235, 0.7)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1}]}; userGrowthChart.update(); }); }
            loadUserGrowthData(); loadLoansDisbursedData();
            document.querySelector('.refresh-user-chart').addEventListener('click', loadUserGrowthData);
            document.querySelector('.refresh-loans-chart').addEventListener('click', loadLoansDisbursedData);
            setInterval(() => { loadUserGrowthData(); loadLoansDisbursedData(); }, 300000);
        </script>
    @endsection
</div>
@endsection
