# System Overview

## Purpose

OpFin is a Uganda-first personal finance platform. The backend must support responsible credit, savings, investments, insurance, employer-linked benefits, KYC, consent, audit logging, mobile money integrations, CRB integrations, and compliance reporting.

This Laravel backend is the system of record for customer identity, consent, financial product state, mobile money workflows, credit decisions, operational admin actions, and compliance evidence.

## Primary Actors

- Member: a consumer using OpFin for credit and broader personal finance services.
- Employer user: an employer or benefits administrator participating in employer-linked offerings.
- Institution admin: an operator for a partner institution.
- OpFin admin: internal operations user.
- Compliance officer: user reviewing audit, KYC, consent, and regulatory reports.
- External provider: mobile money provider, CRB/KYC provider, SMS provider, insurance provider, investment provider, employer/payroll provider.

## Runtime Components

- Laravel API: mobile and partner-facing JSON API.
- Laravel admin portal: operational web interface for OpFin and institution users.
- Database: system-of-record storage for users, institutions, loans, transactions, schedules, credit scores, consent, and audit evidence.
- Queue workers: asynchronous SMS, provider polling, reconciliation, report generation, and callback processing.
- Scheduler: periodic provider status checks and reconciliation jobs.
- External integrations: Airtel Money, MTN MoMo, CRB/NIN, SMS gateways, future insurance, investment, and employer systems.

## Request Flow

1. Client sends request to Laravel route.
2. Middleware authenticates through Sanctum or web session.
3. Validation rejects malformed input before domain logic.
4. Authorization policy decides whether the actor can perform the action.
5. Domain service executes state transition inside a database transaction when financial state changes.
6. Audit log records the action and relevant before/after metadata.
7. External integrations are called through provider adapters.
8. Async jobs handle slow provider calls, notifications, reconciliation, and reporting.

## Financial Safety Principles

- Every financial state transition must be authorized, validated, transactional, and audited.
- Money must be represented in integer minor units only.
- Provider callbacks are hints, not final truth, until verified.
- Ledger postings must be immutable and reversible through correction entries.
- Consent and KYC events must be traceable to actor, purpose, and timestamp.

## Deployment Shape

Minimum production topology:

- Web/API runtime with PHP 8.2+.
- Queue worker runtime.
- Scheduler runtime.
- Managed relational database with backups and point-in-time recovery.
- Centralized logs and metrics.
- Error tracking.
- Secret manager or managed environment variable store.
- Uptime checks against `/up` and deeper readiness checks once implemented.

## Current State

The current repository has strong Laravel domain foundations for users, institutions, loans, transactions, accounts, mobile money, SMS, CRB, and admin views. It still needs stronger boundaries, centralized permissions, audit logging, provider verification, ledger immutability, and operational readiness before it should be treated as production fintech infrastructure.

