<?php

declare(strict_types=1);

namespace App\Services\ProductFactory;

use App\Models\FinancialProduct;
use App\Models\LegalProductPassport;
use App\Models\ProductTemplate;
use App\Models\ShariaApproval;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Versioned Product Factory (PF-001). Platform staff configure financing products for partners from
 * approved templates. Every approval needs a second person, approved versions never change, the
 * actual lender, funder and principal are always explicit, and a product reaches customers only with
 * an approved Legal Product Passport (and, for Islamic products, an approved Sharia approval).
 * Sharia approvals are never created here: they come from the separate governance record.
 */
final class ProductFactoryService
{
    public function __construct(private readonly ProductGuardrails $guardrails, private readonly AuditLogger $audit) {}

    public function createTemplate(User $actor, array $data, string $key): ProductTemplate
    {
        $this->requireAdministrator($actor);
        $existing = ProductTemplate::query()->where('created_by', $actor->id)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            abort_unless($existing->code === $data['code'], 409, 'This Idempotency-Key was used for a different template.');

            return $existing;
        }
        $problems = $this->guardrails->templateProblems($data['family'], $data['rail'], $data['contract_types'], $data['guardrails']);
        abort_if($problems !== [], 422, implode(' ', $problems));

        return DB::transaction(function () use ($actor, $data, $key): ProductTemplate {
            $version = (int) ProductTemplate::query()->where('code', $data['code'])->lockForUpdate()->max('version') + 1;
            $template = ProductTemplate::query()->create([
                'reference' => (string) Str::uuid(), 'code' => $data['code'], 'version' => $version, 'name' => $data['name'],
                'family' => $data['family'], 'rail' => $data['rail'], 'contract_types' => array_values($data['contract_types']),
                'guardrails' => $data['guardrails'], 'policy_reference' => $data['policy_reference'], 'status' => 'draft',
                'created_by' => $actor->id, 'idempotency_key' => $key,
            ]);
            $this->audit->record('product_factory.template_created', $actor, $template, ['code' => $template->code, 'version' => $version]);

            return $template;
        });
    }

    public function approveTemplate(User $actor, ProductTemplate $template): ProductTemplate
    {
        $this->requireAdministrator($actor);

        return DB::transaction(function () use ($actor, $template): ProductTemplate {
            $template = ProductTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            abort_unless($template->status === 'draft', 409, 'Only a draft template can be approved.');
            abort_if((int) $template->created_by === (int) $actor->id, 403, 'A different administrator must approve a template.');
            $template->forceFill(['status' => 'active', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->audit->record('product_factory.template_approved', $actor, $template, ['code' => $template->code, 'version' => $template->version]);

            return $template;
        });
    }

    public function retireTemplate(User $actor, ProductTemplate $template): ProductTemplate
    {
        $this->requireAdministrator($actor);
        abort_if($template->status === 'retired', 409, 'This template is already retired.');
        $template->forceFill(['status' => 'retired', 'retired_at' => now()])->save();
        $this->audit->record('product_factory.template_retired', $actor, $template, ['code' => $template->code, 'version' => $template->version]);

        return $template;
    }

    public function createPassport(User $actor, array $data, string $key): LegalProductPassport
    {
        $existing = LegalProductPassport::query()->where('created_by', $actor->id)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            abort_unless($existing->regulated_activity === $data['regulated_activity'], 409, 'This Idempotency-Key was used for a different passport.');

            return $existing;
        }
        $passport = LegalProductPassport::query()->create([...$data, 'reference' => (string) Str::uuid(), 'status' => 'draft',
            'created_by' => $actor->id, 'idempotency_key' => $key]);
        $this->audit->record('product_factory.passport_created', $actor, $passport, ['regulated_activity' => $passport->regulated_activity]);

        return $passport;
    }

    public function approvePassport(User $actor, LegalProductPassport $passport, array $data): LegalProductPassport
    {
        $this->requireAdministrator($actor);

        return DB::transaction(function () use ($actor, $passport, $data): LegalProductPassport {
            $passport = LegalProductPassport::query()->whereKey($passport->id)->lockForUpdate()->firstOrFail();
            abort_unless($passport->status === 'draft', 409, 'Only a draft passport can be approved.');
            abort_if((int) $passport->created_by === (int) $actor->id, 403, 'A different administrator must approve a Legal Product Passport.');
            $passport->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(),
                'licence_or_approval_reference' => $data['licence_or_approval_reference'], 'evidence_reference' => $data['evidence_reference'],
                'effective_from' => $data['effective_from'] ?? now(), 'effective_to' => $data['effective_to'] ?? null])->save();
            $this->audit->record('product_factory.passport_approved', $actor, $passport, ['evidence_reference' => $passport->evidence_reference]);

            return $passport;
        });
    }

    /** Revocation takes effect at once: product matching requires an approved passport. */
    public function revokePassport(User $actor, LegalProductPassport $passport, string $reason): LegalProductPassport
    {
        $this->requireAdministrator($actor);
        abort_if($passport->status === 'revoked', 409, 'This passport is already revoked.');
        $passport->forceFill(['status' => 'revoked', 'revoked_by' => $actor->id, 'revoked_at' => now(), 'effective_to' => now()])->save();
        $this->audit->record('product_factory.passport_revoked', $actor, $passport, ['reason' => $reason]);

        return $passport;
    }

    public function createProduct(User $actor, array $data, string $key): FinancialProduct
    {
        $existing = FinancialProduct::query()->where('created_by', $actor->id)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            abort_unless($existing->code === $data['code'], 409, 'This Idempotency-Key was used for a different product.');

            return $existing;
        }
        abort_if(FinancialProduct::query()->where('code', $data['code'])->exists(), 409, 'This product code exists. Revise it to create a new version.');
        $template = $this->activeTemplate((int) $data['product_template_id']);
        $this->assertProduct($template, $data);

        return DB::transaction(function () use ($actor, $data, $key, $template): FinancialProduct {
            $product = FinancialProduct::query()->create([...$this->productAttributes($template, $data), 'reference' => (string) Str::uuid(),
                'code' => $data['code'], 'version' => 1, 'status' => 'draft', 'created_by' => $actor->id, 'idempotency_key' => $key]);
            $this->audit->record('product_factory.product_created', $actor, $product, ['code' => $product->code, 'version' => 1]);

            return $product;
        });
    }

    public function updateDraft(User $actor, FinancialProduct $product, array $data): FinancialProduct
    {
        return DB::transaction(function () use ($actor, $product, $data): FinancialProduct {
            $product = FinancialProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($product->status === 'draft', 409, 'Only a draft can be changed. Revise an approved product to create a new version.');
            $template = $this->activeTemplate((int) $product->product_template_id);
            $merged = [...$this->editable($product), ...$data];
            $this->assertProduct($template, $merged);
            $product->forceFill($this->productAttributes($template, $merged))->save();
            $this->audit->record('product_factory.product_updated', $actor, $product, ['code' => $product->code, 'version' => $product->version]);

            return $product;
        });
    }

    public function submit(User $actor, FinancialProduct $product): FinancialProduct
    {
        return $this->transition($product, 'draft', function (FinancialProduct $locked) use ($actor): array {
            return ['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now()];
        }, $actor, 'product_submitted');
    }

    public function approve(User $actor, FinancialProduct $product): FinancialProduct
    {
        $this->requireAdministrator($actor);

        return $this->transition($product, 'submitted', function (FinancialProduct $locked) use ($actor): array {
            abort_if(in_array((int) $actor->id, [(int) $locked->created_by, (int) $locked->submitted_by], true), 403,
                'A different administrator from the maker and submitter must approve a product.');

            return ['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()];
        }, $actor, 'product_approved');
    }

    public function reject(User $actor, FinancialProduct $product, string $reason): FinancialProduct
    {
        $this->requireAdministrator($actor);

        return $this->transition($product, 'submitted', fn (): array => ['status' => 'draft', 'submitted_by' => null, 'submitted_at' => null],
            $actor, 'product_rejected', ['reason' => $reason]);
    }

    /** Puts an approved version live and retires the previous live version of the same code. */
    public function activate(User $actor, FinancialProduct $product): FinancialProduct
    {
        $this->requireAdministrator($actor);

        return $this->transition($product, 'approved', function (FinancialProduct $locked): array {
            $template = ProductTemplate::query()->find($locked->product_template_id);
            abort_unless($template?->status === 'active', 409, 'The template has been retired. Revise this product onto an active template.');
            $this->assertPassport($locked);
            $this->assertShariaPosition($locked);
            // Serialises concurrent activations of the same code so only one version ends up live.
            FinancialProduct::query()->where('code', $locked->code)->orderBy('id')->lockForUpdate()->get(['id']);
            FinancialProduct::query()->where('code', $locked->code)->where('id', '!=', $locked->id)->where('status', 'live')
                ->update(['status' => 'retired', 'retired_at' => now(), 'effective_to' => now(), 'updated_at' => now()]);

            return ['status' => 'live', 'effective_from' => now(), 'effective_to' => null];
        }, $actor, 'product_activated');
    }

    public function retire(User $actor, FinancialProduct $product): FinancialProduct
    {
        $this->requireAdministrator($actor);

        return $this->transition($product, 'live', fn (): array => ['status' => 'retired', 'retired_at' => now(), 'effective_to' => now()],
            $actor, 'product_retired');
    }

    /** New draft version from an existing one; the source stays exactly as approved. */
    public function revise(User $actor, FinancialProduct $source, array $data, string $key): FinancialProduct
    {
        $existing = FinancialProduct::query()->where('created_by', $actor->id)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            abort_unless($existing->code === $source->code, 409, 'This Idempotency-Key was used for a different product.');

            return $existing;
        }
        $template = $this->activeTemplate((int) ($data['product_template_id'] ?? $source->product_template_id));
        $merged = [...$this->editable($source), ...$data];
        $this->assertProduct($template, $merged);

        return DB::transaction(function () use ($actor, $source, $template, $merged, $key): FinancialProduct {
            $version = (int) FinancialProduct::query()->where('code', $source->code)->lockForUpdate()->max('version') + 1;
            $product = FinancialProduct::query()->create([...$this->productAttributes($template, $merged), 'reference' => (string) Str::uuid(),
                'code' => $source->code, 'version' => $version, 'status' => 'draft', 'supersedes_product_id' => $source->id,
                'created_by' => $actor->id, 'idempotency_key' => $key]);
            $this->audit->record('product_factory.product_revised', $actor, $product, ['code' => $product->code, 'version' => $version, 'from' => $source->version]);

            return $product;
        });
    }

    private function transition(FinancialProduct $product, string $from, callable $changes, User $actor, string $event, array $metadata = []): FinancialProduct
    {
        return DB::transaction(function () use ($product, $from, $changes, $actor, $event, $metadata): FinancialProduct {
            $locked = FinancialProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === $from, 409, 'This action needs a product that is '.$from.'; it is '.$locked->status.'.');
            $locked->forceFill($changes($locked))->save();
            $this->audit->record('product_factory.'.$event, $actor, $locked, [...$metadata, 'code' => $locked->code, 'version' => $locked->version]);

            return $locked;
        });
    }

    private function assertProduct(ProductTemplate $template, array $data): void
    {
        $problems = $this->guardrails->productProblems($template, (string) ($data['contract_type'] ?? ''), (array) ($data['parameters'] ?? []), (array) ($data['disclosure'] ?? []));
        foreach (['lender_reference' => 'lender', 'funder_reference' => 'funder', 'principal_reference' => 'contracting principal'] as $field => $label) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $problems[] = "Name the actual {$label}.";
            }
        }
        $passport = LegalProductPassport::query()->find($data['legal_product_passport_id'] ?? 0);
        if ($passport === null || $passport->status === 'revoked') {
            $problems[] = 'Choose a Legal Product Passport that has not been revoked.';
        }
        if ($template->rail === 'CONVENTIONAL' && ! empty($data['sharia_approval_id'])) {
            $problems[] = 'A conventional product cannot carry a Sharia approval.';
        }
        abort_if($problems !== [], 422, implode(' ', $problems));
    }

    private function assertPassport(FinancialProduct $product): void
    {
        $passport = LegalProductPassport::query()->find($product->legal_product_passport_id);
        abort_unless($passport !== null && $passport->status === 'approved' && $passport->revoked_at === null
            && ($passport->effective_from === null || $passport->effective_from->lte(now()))
            && ($passport->effective_to === null || $passport->effective_to->gt(now())), 422,
            'Activation needs an approved, current Legal Product Passport.');
        abort_unless($passport->jurisdiction === $product->jurisdiction, 422, 'The passport covers a different jurisdiction.');
        abort_if($passport->partner_id !== null && (int) $passport->partner_id !== (int) $product->partner_id, 422,
            'The passport belongs to a different partner.');
    }

    private function assertShariaPosition(FinancialProduct $product): void
    {
        if ($product->rail !== 'ISLAMIC') {
            return;
        }
        $approval = ShariaApproval::query()->find($product->sharia_approval_id);
        abort_unless($approval !== null && $approval->status === 'approved'
            && ($approval->effective_from === null || $approval->effective_from->lte(now()))
            && ($approval->expires_at === null || $approval->expires_at->gt(now())), 422,
            'An Islamic product needs an approved, current Sharia approval from the governance record. The Product Factory cannot create one.');
    }

    private function activeTemplate(int $id): ProductTemplate
    {
        $template = ProductTemplate::query()->find($id);
        abort_unless($template?->status === 'active', 422, 'Choose an approved, active product template.');

        return $template;
    }

    private function productAttributes(ProductTemplate $template, array $data): array
    {
        return ['product_template_id' => $template->id, 'name' => $data['name'], 'rail' => $template->rail, 'family' => $template->family,
            'contract_type' => $data['contract_type'], 'jurisdiction' => 'UG', 'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            'partner_id' => $data['partner_id'] ?? null, 'legal_product_passport_id' => $data['legal_product_passport_id'],
            'sharia_approval_id' => $data['sharia_approval_id'] ?? null, 'customer_classes' => $data['customer_classes'] ?? null,
            'parameters' => $data['parameters'], 'disclosure' => $data['disclosure'],
            'asset_supplier_requirements' => ['asset_classes' => $data['parameters']['asset_classes'] ?? []],
            'policy_versions' => ['template' => $template->code.'@'.$template->version, 'policy_reference' => $template->policy_reference],
            'lender_reference' => $data['lender_reference'], 'funder_reference' => $data['funder_reference'],
            'principal_reference' => $data['principal_reference']];
    }

    private function editable(FinancialProduct $product): array
    {
        return ['name' => $product->name, 'contract_type' => $product->contract_type, 'currency' => $product->currency,
            'partner_id' => $product->partner_id, 'legal_product_passport_id' => $product->legal_product_passport_id,
            'sharia_approval_id' => $product->sharia_approval_id, 'customer_classes' => $product->customer_classes,
            'parameters' => $product->parameters, 'disclosure' => $product->disclosure, 'lender_reference' => $product->lender_reference,
            'funder_reference' => $product->funder_reference, 'principal_reference' => $product->principal_reference];
    }

    private function requireAdministrator(User $actor): void
    {
        abort_unless($actor->role === User::ROLE_PLATFORM_ADMIN, 403, 'This step needs a platform administrator.');
    }
}
