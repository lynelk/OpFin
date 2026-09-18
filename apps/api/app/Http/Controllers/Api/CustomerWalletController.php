<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerPhoneNumber;
use App\Models\CustomerWallet;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerWalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Wallets loaded.', [
            'wallets' => CustomerWallet::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->orderByDesc('is_default_disbursement')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number_id' => 'required|integer|exists:customer_phone_numbers,id',
            'provider' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $phone = CustomerPhoneNumber::query()
            ->whereKey($request->integer('phone_number_id'))
            ->where('user_id', $request->user()->id)
            ->whereNotNull('verified_at')
            ->first();

        if (! $phone) {
            return ApiResponse::error('Use a verified phone number for this wallet.', 422);
        }

        $wallet = CustomerWallet::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'provider' => (string) $request->input('provider'),
                'msisdn' => $phone->phone,
            ],
            [
                'phone_number_id' => $phone->id,
                'status' => 'active',
                'verified_at' => $phone->verified_at,
            ],
        );

        return ApiResponse::success('Wallet saved.', ['wallet' => $wallet], 201);
    }

    public function setDefault(CustomerWallet $wallet, Request $request): JsonResponse
    {
        if ((int) $wallet->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validator = Validator::make($request->all(), [
            'use_for' => ['required', Rule::in(['disbursement', 'repayment', 'both'])],
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $useFor = (string) $request->input('use_for');
        DB::transaction(function () use ($wallet, $useFor) {
            if (in_array($useFor, ['disbursement', 'both'], true)) {
                CustomerWallet::where('user_id', $wallet->user_id)->update(['is_default_disbursement' => false]);
                $wallet->is_default_disbursement = true;
            }
            if (in_array($useFor, ['repayment', 'both'], true)) {
                CustomerWallet::where('user_id', $wallet->user_id)->update(['is_default_repayment' => false]);
                $wallet->is_default_repayment = true;
            }
            $wallet->save();
        });

        return ApiResponse::success('Default wallet updated.', ['wallet' => $wallet->fresh()]);
    }
}
