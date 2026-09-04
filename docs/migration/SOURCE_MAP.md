# Monorepo source map

| Source | Imported commit | Destination |
| --- | --- | --- |
| `lynelk/OpFin-BE` | `fda182cc2d3d741c5f9b462e5d80d5caea6e594a` | `apps/api` |
| `lynelk/OpFin-FE` Next.js root | `1ef9a0e9a4766ba802afc97f9b91f366620d053a` | `apps/web` |
| `lynelk/OpFin-FE/opfin-frontend` | `1ef9a0e9a4766ba802afc97f9b91f366620d053a` | `apps/client` |

The import uses non-squashed Git subtree merges, so both source histories remain reachable in the monorepo graph. Nested source CI configuration was replaced with one root control plane.
