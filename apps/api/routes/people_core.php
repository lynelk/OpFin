<?php

use App\Http\Controllers\Api\PeopleBiometricEventController;
use App\Http\Controllers\Api\PeopleOperationsController;
use App\Http\Controllers\Api\PeoplePayrollController;
use App\Http\Controllers\Api\PeopleWorkforceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('people/spaces/{space}')
    ->where(['space' => '[0-9]+'])
    ->group(function (): void {
        Route::get('employees', [PeopleWorkforceController::class, 'employees']);
        Route::post('employees', [PeopleWorkforceController::class, 'createEmployee']);

        Route::get('leave/policies', [PeopleWorkforceController::class, 'leavePolicies']);
        Route::post('leave/policies', [PeopleWorkforceController::class, 'createLeavePolicy']);
        Route::post('leave/policies/{policy}/approve', [PeopleWorkforceController::class, 'approveLeavePolicy'])->whereNumber('policy');
        Route::get('leave/requests', [PeopleWorkforceController::class, 'leaveRequests']);
        Route::post('leave/requests', [PeopleWorkforceController::class, 'requestLeave']);
        Route::post('leave/requests/{leave}/decide', [PeopleWorkforceController::class, 'decideLeave'])->whereNumber('leave');

        Route::get('attendance', [PeopleWorkforceController::class, 'attendance']);
        Route::post('attendance', [PeopleWorkforceController::class, 'clock']);
        Route::post('attendance/{event}/approve', [PeopleWorkforceController::class, 'approveClock'])->whereNumber('event');

        Route::get('biometrics/connectors', [PeopleWorkforceController::class, 'biometricConnectors']);
        Route::post('biometrics/connectors', [PeopleWorkforceController::class, 'registerBiometricConnector']);
        Route::post('biometrics/connectors/{connector}/bindings', [PeopleBiometricEventController::class, 'configureBinding'])->whereNumber('connector');
        Route::post('biometrics/connectors/{connector}/bindings/{binding}/approve', [PeopleBiometricEventController::class, 'approveBinding'])
            ->whereNumber('connector')->whereNumber('binding');

        Route::get('payroll/policies', [PeoplePayrollController::class, 'policies']);
        Route::post('payroll/policies', [PeoplePayrollController::class, 'createPolicy']);
        Route::post('payroll/policies/{policy}/approve', [PeoplePayrollController::class, 'approvePolicy'])->whereNumber('policy');
        Route::get('payroll/periods', [PeoplePayrollController::class, 'periods']);
        Route::post('payroll/periods', [PeoplePayrollController::class, 'createPeriod']);
        Route::get('payroll/periods/{period}/lines', [PeoplePayrollController::class, 'lines'])->whereNumber('period');
        Route::post('payroll/periods/{period}/statutory-preview', [PeoplePayrollController::class, 'previewStatutory'])->whereNumber('period');
        Route::post('payroll/periods/{period}/lines', [PeoplePayrollController::class, 'createLine'])->whereNumber('period');
        Route::post('payroll/periods/{period}/prepare', [PeoplePayrollController::class, 'prepare'])->whereNumber('period');
        Route::post('payroll/periods/{period}/approve', [PeoplePayrollController::class, 'approve'])->whereNumber('period');
        Route::post('payroll/periods/{period}/lock', [PeoplePayrollController::class, 'lock'])->whereNumber('period');
        Route::get('payroll/my-payslips', [PeoplePayrollController::class, 'myPayslips']);

        Route::get('assets/custody', [PeopleOperationsController::class, 'assets']);
        Route::post('assets/custody', [PeopleOperationsController::class, 'assignAsset']);
        Route::post('assets/custody/{custody}/acknowledge', [PeopleOperationsController::class, 'acknowledgeAsset'])->whereNumber('custody');
        Route::post('assets/custody/{custody}/return', [PeopleOperationsController::class, 'returnAsset'])->whereNumber('custody');

        Route::get('kpis', [PeopleOperationsController::class, 'kpis']);
        Route::post('kpis', [PeopleOperationsController::class, 'defineKpi']);
        Route::post('kpis/{kpi}/report', [PeopleOperationsController::class, 'reportKpi'])->whereNumber('kpi');
        Route::post('kpis/{kpi}/approve', [PeopleOperationsController::class, 'approveKpi'])->whereNumber('kpi');

        Route::get('appraisals', [PeopleOperationsController::class, 'appraisals']);
        Route::post('appraisals', [PeopleOperationsController::class, 'createAppraisal']);
        Route::post('appraisals/{appraisal}/approve', [PeopleOperationsController::class, 'approveAppraisal'])->whereNumber('appraisal');

        Route::get('rewards', [PeopleOperationsController::class, 'rewards']);
        Route::post('rewards', [PeopleOperationsController::class, 'nominateReward']);
        Route::post('rewards/{reward}/approve', [PeopleOperationsController::class, 'approveReward'])->whereNumber('reward');

        Route::get('field-assignments', [PeopleOperationsController::class, 'fieldAssignments']);
        Route::post('field-assignments', [PeopleOperationsController::class, 'assignFieldWork']);
        Route::post('field-assignments/{task}/submit', [PeopleOperationsController::class, 'submitFieldWork'])->whereNumber('task');
        Route::post('field-assignments/{task}/approve', [PeopleOperationsController::class, 'approveFieldWork'])->whereNumber('task');
    });

Route::middleware(['throttle:api'])->post(
    'people/device-ingest/{space}/{connector}',
    [PeopleBiometricEventController::class, 'ingest']
)->whereNumber('space')->whereNumber('connector');

Route::middleware(['auth:sanctum', 'throttle:api', 'role:platform_admin,operations'])->post(
    'admin/people/spaces/{space}/biometrics/connectors/{connector}/approve',
    [PeopleBiometricEventController::class, 'approveConnector']
)->whereNumber('space')->whereNumber('connector');
