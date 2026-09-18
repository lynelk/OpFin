# Reusable task-guide template

Use this structure for customer help, staff procedures, training and UAT. Replace the bracketed prompts with verified information; do not publish this template as a finished manual.

## Document control

Task ID: [stable identifier]. Audience/role: [who may perform the task]. Owner/reviewer: [named accountable people]. Source commit: [full SHA]. Build/environment/channel: [exact values]. Review date: [date]. Availability: [LIVE / LIMITED PILOT / PLANNED / UNVERIFIED, with evidence].

## What you will achieve

[One practical outcome, expressed without technical terminology.]

## Before you start

[Required account state, permissions, prior steps, supported device/channel and synthetic training data. Explain any provider or capability prerequisite.]

## Complete the task

| Step | User action using the application's exact label | Expected screen or state | Evidence reference |
| --- | --- | --- | --- |
| 1 | [Action] | [Observable result] | [Source/test/build evidence] |
| 2 | [Action] | [Observable result] | [Source/test/build evidence] |

## Check the result

[Explain how the user knows the task completed. Distinguish pending from completed financial states and give the correct place to inspect status or receipts.]

## When something goes wrong

| Situation | Safe user action | Staff escalation | What must not happen |
| --- | --- | --- | --- |
| Invalid information | [Correction] | [When needed] | [No bypass] |
| Permission denied | [Approved access path] | [Owner] | [No shared credentials] |
| Connection interrupted | [Resume/status check] | [Reference to collect] | [No blind duplicate money request] |
| Provider pending/unavailable | [Check status] | [Operational route] | [No claim of finality] |
| Accessibility assistance needed | [Supported assistance] | [Support route] | [No PIN/OTP sharing or weaker identity checks] |

## Trainer exercise and acceptance record

Give one realistic synthetic scenario, a learner task and an observable pass criterion. Record the actual result, tester, date, build, evidence and defect reference. Include validation, permission, interruption/retry and recovery cases, not only the happy path.

## Source and change impact

Link the current journey, endpoint contract, operational rule and relevant test. Record which screenshots, FAQs, in-app help, translations and training modules need an update when this task changes. Never copy production identity documents, tokens or customer information into the guide.
