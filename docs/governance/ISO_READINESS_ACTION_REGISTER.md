# OpFin ISO Readiness Action Register

Status: Controlled internal implementation register  
Established: 25 September 2026  
Language: English (United Kingdom)

This register turns the 25 September 2026 ISO-readiness review into controlled work. It is not an ISO certificate and does not assert conformity where operating evidence has not been verified. The companion [policy proposal](INTEGRATED_MANAGEMENT_SYSTEM.md) is not effective until its adoption evidence is recorded.

## Status model

Work state uses only: Open; In progress; Blocked; Complete.

Evidence maturity uses only: Not assessed; Designed; Implemented; Tested; Deployed; Effectiveness verified; Not applicable.

These are separate dimensions. A task can be in progress with only designed evidence. Complete requires the stated closure evidence, not merely a merge. An external dependency belongs in the closure-evidence column; it is not an undeclared status. Never convert implementation or a passing test into an unsupported certification claim.

## Actions

| ID | Action | Owner | Work state | Evidence maturity | Closure evidence |
| --- | --- | --- | --- | --- | --- |
| IMS-01 | Assess the requested ISO 9001:2026 QMS target and adopt the verified applicable baseline and integrated management-system scope | Executive / Quality | Open | Designed | Issuer-verified edition/publication/transition evidence, approved scope, policy, context/interested-parties register, process map, objectives and management-review evidence |
| IMS-02 | Maintain applicability for ISO 9001, ISO/IEC 27001, ISO/IEC 27000, ISO/IEC 20000-1, ISO/IEC 27032, ISO 22301, ISO 20022, ISO 8583, ISO 9362 and ISO 32212 | Compliance | Open | Designed | Verified editions/amendments and clause/reference-level applicability decisions, rationale and evidence links |
| IMS-03 | Assess privacy management against ISO/IEC 27701 and supporting cloud/privacy controls | Privacy / Security | Open | Designed | Privacy scope, processing inventory, controller/processor roles, retention, rights and supplier evidence |
| IMS-04 | Close the separately recorded credential-log incident | Security | Open | Not assessed | Incident record, affected-secret analysis, containment/rotation evidence, impact assessment, remediation and independent closure review |
| IMS-05 | Enforce protected-main/repository governance or an approved equivalent control | Repository owner / Engineering | Blocked | Not assessed | Authorised repository-admin settings; protected branch/ruleset evidence or an approved compensating review/change control |
| IMS-06 | Complete PR #118 exact-Space and account-closure hardening after review closure | Engineering / Finance / Security | In progress | Tested | Independent approval, current-candidate checks, documentation reconciliation and verified merge record; earlier tests alone do not complete integration |
| IMS-07 | Complete PR #113 financial-control hardening after review closure | Engineering / Finance / Risk | In progress | Implemented | Independent approval, all applicable candidate-specific tests/audits and verified merge record |
| IMS-08 | Reconcile current documentation with verified repair evidence while preserving history | Documentation owner | Open | Not assessed | Current-state/API/manual references updated; dated evidence unchanged |
| IMS-09 | Establish one control/evidence register across management-system areas | Quality / Security / Operations | Open | Designed | Requirement, owner, implementation, test, evidence, result, exception and review-date records |
| IMS-10 | Establish nonconformity, corrective action, root-cause, improvement, feedback and complaint-trend registers | Quality / Operations | Open | Designed | Registers populated and corrective-action effectiveness demonstrated |
| IMS-11 | Establish ISMS evidence | Security | Open | Designed | Asset inventory, classification, risk assessment/treatment, Statement of Applicability, access review, supplier risk, vulnerability and incident records |
| IMS-12 | Establish the service catalogue and service-management evidence | Operations / Engineering | Open | Designed | Owners, criticality, service objectives, dependencies, recovery objectives, support/escalation, monitoring and incident/problem/change records |
| IMS-13 | Establish business-continuity and recovery evidence | Operations / Security | Open | Designed | Business-impact analysis, continuity plans, approved recovery objectives, backup/restore exercises and corrective actions |
| IMS-14 | Treat ISO 20022/8583/9362 as profile/interface-specific requirements | Payments / Architecture | Open | Designed | Actual scheme/profile/version register, validation evidence and no universal-conformance claim |
| IMS-15 | Determine ISO 32212 applicability to controlled or influenced lending/investment/insurance activities | Executive / Compliance | Open | Not assessed | Verified reference, documented applicability decision and any required transition-plan evidence |
| IMS-16 | Determine software-quality, accessibility, AI and PCI applicability | Product / Security / Compliance | Open | Not assessed | Applicable ISO/IEC 25010 criteria; WCAG/native accessibility evidence; ISO/IEC 42001 inventory/scope; PCI DSS scope decision |
| IMS-17 | Maintain legal/regulatory/channel obligations by entity, country, product, partner and channel | Legal / Compliance | Open | Designed | Verified obligations, licences/registrations/approvals, owners, evidence and renewal dates |
| IMS-18 | Run internal audit and management review before external conformity/certification claims | Executive / Internal audit | Open | Not assessed | Audit programme/results, management-review minutes, corrective-action effectiveness and certification-scope decision |

## Control record

Record the control identifier, verified source/edition/clause where licensed text permits, requirement summary, applicability rationale, scope, accountable owner and implementer, risk/opportunity, implementation, automated/manual tests, operating frequency, evidence location, reviewer/date, evidence maturity and result. Record exceptions with residual risk, approver and expiry.

For software release evidence, record the exact candidate and environment. For organisational evidence, identify the relevant subject, organisational scope and period instead. A policy is not proof that a control operates.

## QMS implementation direction

The proposed QMS shall cover leadership accountability, ethical quality culture, strategic alignment, separate consideration of risks and opportunities, competence/awareness, customer feedback, complaints, supplier quality, corrective action, internal audit, management review, measurement and continual improvement. Map those proposed controls to the verified applicable standard before claiming conformity.

Approve quality objectives from actual baselines. Candidate measures include reconciliation accuracy/ageing, escaped financial defects, critical-journey failures, complaint handling, provider exceptions, accessibility acceptance and corrective-action effectiveness. Do not invent ISO thresholds.

## Claim rules

Do not describe OpFin as certified without verified certification evidence for the defined scope. Do not infer compliance from code, documents, scans, dashboards or a deployment. Distinguish documented, implemented, tested, deployed, effective and independently certified states. ISO alignment does not replace applicable legal/regulatory obligations.

Publication class and navigation are recorded in the [publication register](../PUBLICATION_REGISTER.md) and [documentation hub](../README.md).
