# Architecture

`fiCMS-seo-sea` is a workflow plugin, not an API transport plugin.

## Boundaries

- OAuth account lifecycle: `oauth`
- Google provider metadata and Google API clients: `google`
- Meta provider metadata and Graph API clients: `meta`
- SEO/SEA/lead workflow state: `fiCMS-seo-sea`

## Planned Modules

- `visibility`: Search Console properties, queries, pages and opportunity scoring.
- `keywords`: tracked keyword sets and landing page mapping.
- `campaigns`: SEA provider/campaign views.
- `leads`: provider lead sources, forms and retrieval state.
- `reports`: combined SEO/SEA/lead reporting.

## State

Runtime state should stay in plugin-owned `data/*.json` files until the workflow needs database-backed querying.
