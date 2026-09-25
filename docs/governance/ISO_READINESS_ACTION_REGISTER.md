# OpFin ISO Readiness Action Register

Status: Controlled implementation register  
Established: 25 September 2026  
Language: English (United Kingdom)

This register turns the 25 September 2026 ISO-readiness review into controlled work. It is not an ISO certificate and does not assert conformity where operating evidence has not been verified.

## Status model

Use only: Not assessed; Designed; Implemented; Tested; Deployed; Effectiveness verified; Not applicable.

Never convert implementation or a passing test into an unsupported certification or compliance claim.

## Immediate actions

| ID | Action | Owner | Current status | Closure evidence |
|---|---|---|---|---|
| IMS-01 | Adopt ISO 9001:2026 as the QMS baseline and reconcile the integrated management-system scope | Executive / Quality | Designed | Approved scope, quality policy, context/interested-parties register, process map, objectives and management-review evidence |
| IMS-02 | Maintain the applicability register for ISO 9001, ISO/IEC 27001, ISO/IEC 27000, ISO/IEC 20000-1, ISO/IEC 27032, ISO 22301, ISO 20022, ISO 8583, ISO 9362 and ISO 32212 | Compliance | Designed | Clause/reference-level applicability decisions with rationale and evidence links |
| IMS-03 | Add privacy-management assessment against ISO/IEC 27701 and supporting cloud/privacy controls | Privacy / Security | Designed | Privacy scope, processing inventory, controller/processor roles, retention, rights and supplier evidence |
| IMS-04 | Close the separately recorded credential-log incident | Security | Open | Incident record, affected-secret analysis, containment/rotation evidence, impact assessment, remediation and independent closure review |
| IMS-05 | Enforce protected-main/repository governance or an approved equivalent control | Repository owner / Engineering | Open external setting | Protected branch/ruleset evidence or documented compensating control with approved change/review enforcement |
| IMS-06 | Complete PR #118 exact-Space and account-closure hardening only after all review findings are resolved | Engineering / Finance / Security | In progress | Clean independent review, exact-candidate test evidence, documentation reconciliation and merge record |
| IMS-07 | Complete PR #113 financial-control hardening only after all review findings are resolved | Engineering / Finance / Risk | In progress | Clean independent review, full applicable test/audit evidence and merge record |
| IMS-08 | Reconcile current documentation with later verified repair evidence while preserving dated historical records | Documentation owner | Open | Current-state/API/manual references updated; historical evidence unchanged |
| IMS-09 | Establish one control/evidence register across quality, security, privacy, service management and continuity | Quality / Security / Operations | Designed | Control records with requirement, owner, implementation, test, evidence, result, exception and review dates |
| IMS-10 | Establish nonconformity, CAPA, root-cause, improvement, customer-feedback and complaint-trend registers | Quality / Operations | Designed | Registers populated and corrective-action effectiveness demonstrated |
| IMS-11 | Establish ISMS evidence: asset inventory, classification, risk register/treatment plan, SoA, access review, supplier risk, vulnerability and incident management | Security | Designed | Approved current registers and recurring review evidence |
| IMS-12 | Establish service catalogue and service-management evidence | Operations / Engineering | Designed | Service owners, criticality, SLOs, dependencies, RTO/RPO, support/escalation, monitoring/runbooks and incident/problem/change records |
| IMS-13 | Establish business-continuity and recovery evidence | Operations / Security | Designed | BIA, continuity plans, approved RTO/RPO, backup/restore exercises and lessons/CAPA |
| IMS-14 | Treat ISO 20022/8583/9362 as profile/interface-specific requirements | Payments / Architecture | Designed | Actual scheme/profile/version register, validation evidence and no unsupported universal-conformance claim |
| IMS-15 | Determine ISO 32212 applicability to lending/investment/insurance activities controlled or influenced by OpFin | Executive / Compliance | Not assessed | Documented applicability decision and, where applicable, transition-plan evidence |
| IMS-16 | Add software-quality, accessibility, AI and PCI applicability decisions | Product / Security / Compliance | Not assessed | ISO/IEC 25010 acceptance criteria; WCAG/native accessibility evidence; ISO/IEC 42001 AI inventory decision; PCI DSS scope decision |
| IMS-17 | Maintain a separate legal/regulatory/channel obligations register by entity, country, product, partner and channel | Legal / Compliance | Designed | Verified obligations, licences/registrations/approvals, owners, evidence and renewal dates |
| IMS-18 | Run internal audit and management review before any external conformity/certification claim | Executive / Internal audit | Not started | Audit programme/results, management-review minutes, CAPA closure and certification-scope decision |

## Control record

Each applicable control must record:

- control ID;
- source standard/reference and clause where licensed text permits;
- requirement summary;
- applicability rationale;
- scope and systems;
- accountable owner and implementer;
- risk/opportunity addressed;
- implementation description;
- automated and manual test;
- operating frequency;
- evidence location;
- reviewer;
- last tested date;
- result/status;
- exception or residual risk;
- exception approver and expiry.

A policy is not proof that a control operates. Retain both design evidence and operating-effectiveness evidence.

## ISO 9001:2026 implementation direction

The QMS shall explicitly cover leadership accountability, ethical quality culture, policy alignment to OpFin's strategy, separate consideration of risks and opportunities, competence/awareness, customer feedback and complaints, supplier quality, nonconformity/CAPA, internal audit, management review, measurement and continual improvement.

Quality objectives must be approved from actual service baselines. Candidate measures include reconciliation accuracy/ageing, escaped financial defects, failed critical journeys, complaint handling, provider exceptions, accessibility acceptance and corrective-action effectiveness. Do not invent ISO thresholds.

## Claim rules

- Do not call OpFin or the application “ISO certified” unless an appropriately accredited certification body has certified the defined organisational/service scope.
- Do not claim blanket compliance from code, documents, a test suite, a scan, a dashboard or a successful deployment.
- Distinguish documented, implemented, tested, deployed, operating-effectiveness verified and independently certified states.
- ISO alignment does not replace Ugandan or other applicable legal/regulatory obligations.
