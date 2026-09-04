# Repository and runtime boundaries

OpFin uses one repository but retains independent build, deployment and security boundaries.

- API changes do not automatically expose server secrets to web or Flutter.
- Web and Flutter do not access the database directly.
- API, worker, scheduler and web remain separate deployments.
- Mobile store build numbers remain independent of backend-only releases.
- Contract changes require coordinated API, web and client validation.
