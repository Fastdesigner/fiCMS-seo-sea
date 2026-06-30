# fiCMS SEO/SEA

SEO, SEA, visibility and lead management workflows for fiCMS.

This plugin is the workflow owner. It does not implement provider transport. OAuth account lifecycle, refresh, webhook forwarding and API request preparation stay in dependency plugins.

## Current Build Target

The first usable version should consolidate the existing `fiCMS-seo` Search Console idea and add Meta leadgen as the first SEA/lead workflow:

- SEO visibility from Google Search Console properties, queries and landing pages.
- Keyword and landing page opportunity scoring.
- Meta Page/Form selection for lead forms.
- Meta lead webhook setup through the shared OAuth proxy.
- Lead inbox processing, deduplication and local lead state.
- Combined reporting across visibility, campaign/source and lead outcome.

Campaign management is intentionally not part of the first cut. The first SEA value is lead source visibility: which provider/form/campaign produced a lead and whether fiCMS can act on it.

## Ownership

`fiCMS-seo-sea` owns:

- workflow-specific settings and source selections,
- local state for tracked keywords, lead sources and imported leads,
- lead deduplication and retry status,
- admin-facing reporting and recommendations,
- later CRM handoff decisions.

Dependency plugins own:

- `oauth`: connection storage, refresh, central callback handoff, webhook proxy routes and signed local delivery,
- `google`: Google OAuth provider metadata and Google API helpers,
- `meta`: Meta OAuth provider metadata and Graph API helpers.

## OAuth

Admins create OAuth connections system-wide in the fiCMS integrations settings. `fiCMS-seo-sea` selects an existing connection by account reference and requests additional workflow scopes only where needed.

Search Console workflow:

```php
$scopes = \google\Scopes::searchConsole();
```

Meta lead workflow:

```php
$scopes = \meta\Scopes::leads();
```

OAuth connections are managed in the central `general-integrations` settings panel. This plugin only consumes the selected account reference and reports missing workflow scopes.

## Initial Modules

The first implementation should use small owner classes under `src/`:

- `Visibility`: Search Console properties, keyword metrics and content fit.
- `LeadSources`: Meta account/Page/Form selection and webhook route setup.
- `LeadInbox`: processing `system/plugins/oauth/webhooks/inbox/meta/*.json`.
- `Leads`: normalized local lead records and deduplication.
- `Reports`: cross-workflow summary for the settings/info view.

The settings panel should stay a thin callsite. Example:

```php
$leadSources = new \ficmsSeoSea\LeadSources($user['language']);
$settings['output']['lists'][$settings['key'].'Content'] = [
	'id'=>$settings['key'].'Content',
	'clear'=>true,
	'items'=>$leadSources->settingsItems($site)
];
```

## Local State

Runtime state stays in plugin-owned JSON until reporting needs database-backed querying:

```text
system/plugins/fiCMS-seo-sea/data/keywords.json
system/plugins/fiCMS-seo-sea/data/lead-sources.json
system/plugins/fiCMS-seo-sea/data/leads.json
system/plugins/fiCMS-seo-sea/data/lead-errors.json
```

All fiCMS file helper calls must receive relative project paths such as `system/plugins/fiCMS-seo-sea/data/leads.json`.

## Docs

- [Architecture](docs/architecture.md)
- [Meta lead webhooks](HANDOFF.md)
