# Railway infrastructure governance

OpFin may use only the Railway services listed in `ops/railway/topology-policy.json`.

Creating a new Railway project, environment, service, database, volume, replica group or persistent validation workload requires explicit approval from the workspace owner before the mutation occurs. A feature request, bug fix, release request or test request is not permission to create infrastructure.

Validation and build jobs belong in ephemeral CI. Persistent Railway validation services are forbidden.

Workspace cost controls remain Railway Agent hard limit **USD 0** and compute hard limit **USD 10** for the current operating phase.

Any approved topology change must update the allow-list in a separately reviewable change stating the reason, expected cost and expected lifetime of the new resource.
