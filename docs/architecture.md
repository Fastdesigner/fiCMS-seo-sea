# Architecture

`fiCMS-seo-sea` is a workflow plugin, not an API transport plugin.

## Boundaries

- OAuth account lifecycle: `oauth`
- Webhook proxy route registration and signed delivery: `oauth`
- Google provider metadata and Google API clients: `google`
- Meta provider metadata and Graph API clients: `meta`
- SEO/SEA/lead workflow state: `fiCMS-seo-sea`

The provider plugins should stay reusable for reviews, business profile, ads or future workflows. `fiCMS-seo-sea` may depend on their helper methods, but it should not move workflow state back into those dependencies.

## Modules

### Visibility

Owner for the existing `fiCMS-seo` idea:

- select a Google OAuth account reference,
- request `\google\Scopes::searchConsole()`,
- list Search Console properties,
- store tracked keywords and brand terms,
- query page performance per keyword,
- compare query intent with local fiCMS page content,
- classify opportunities.

Consumer callsite:

```php
$visibility = new \ficmsSeoSea\Visibility($user['language']);
$properties = $visibility->properties('seo');
$rows = $visibility->queryPages('seo',$property,$keyword,$startDate,$endDate);
```

### LeadSources

Owner for provider source setup:

- select a Meta OAuth account reference,
- request `\meta\Scopes::leads()`,
- list accessible Pages through `\meta\Meta::pages()`,
- list forms through `\meta\Meta::leadForms()`,
- subscribe the Page to `leadgen`,
- register the route through `\oauth\OAuth::webhook_route_register()`,
- store source state locally.

Consumer callsite:

```php
$sources = new \ficmsSeoSea\LeadSources($user['language']);
$result = $sources->enableMetaForm($accountRef,$pageId,$formId,$formName);
```

### LeadInbox

Owner for processing signed webhook forwards:

- read `system/plugins/oauth/webhooks/inbox/meta/*.json`,
- extract lead events,
- match `provider + page_id + form_id` against lead sources,
- fetch lead details through `\meta\Meta::lead()`,
- normalize field data,
- write deduped lead records,
- move processed/failed payloads into workflow-owned state.

Consumer callsite:

```php
$inbox = new \ficmsSeoSea\LeadInbox();
$result = $inbox->processMeta();
```

### Leads

Owner for local lead records:

- deduplicate by `provider + lead_id`,
- preserve raw provider IDs,
- normalize common fields without dropping unknown fields,
- track status: `new`, `processed`, `failed`, `ignored`,
- retain retry information and last error.

The first version should not guess CRM handoff rules. It should expose clean lead data for admin review and later handoff.

### Reports

Owner for admin summaries:

- visibility summary: impressions, clicks, CTR, average position,
- keyword opportunity list,
- lead source health,
- lead count by source/form/campaign,
- recent import errors.

## State Files

Use plugin-owned JSON files first:

```text
system/plugins/fiCMS-seo-sea/data/keywords.json
system/plugins/fiCMS-seo-sea/data/lead-sources.json
system/plugins/fiCMS-seo-sea/data/leads.json
system/plugins/fiCMS-seo-sea/data/lead-errors.json
system/plugins/fiCMS-seo-sea/data/lead-inbox-processed.json
```

The helper boundary is strict: file helper calls get relative project paths.

```php
helper__files_write('system/plugins/fiCMS-seo-sea/data/lead-sources.json',$sources,true,true);
```

## Data Shapes

Lead source:

```json
{
	"meta:PAGE_ID:FORM_ID": {
		"provider": "meta",
		"account_ref": "default",
		"page_id": "PAGE_ID",
		"form_id": "FORM_ID",
		"name": "Website Kontaktformular",
		"route_id": "optional-oauth-route-id",
		"created_at": 0,
		"updated_at": 0,
		"last_sync_at": 0,
		"last_error": "",
		"ac": 1
	}
}
```

Lead record:

```json
{
	"meta:LEAD_ID": {
		"provider": "meta",
		"lead_id": "LEAD_ID",
		"source_key": "meta:PAGE_ID:FORM_ID",
		"page_id": "PAGE_ID",
		"form_id": "FORM_ID",
		"campaign_id": "CAMPAIGN_ID",
		"adset_id": "ADSET_ID",
		"ad_id": "AD_ID",
		"platform": "facebook",
		"created_time": "2026-06-30T08:00:00+0000",
		"fields": {
			"email": "lead@example.com",
			"full_name": "Max Muster"
		},
		"raw": {},
		"status": "new",
		"created_at": 0,
		"updated_at": 0,
		"last_error": ""
	}
}
```

## First Cut

1. Port `fiCMS-seo` Search Console behavior into `Visibility`.
2. Add a settings/info panel that shows Google visibility and Meta lead source setup in one workflow view.
3. Add `LeadSources::enableMetaForm()` for Page/Form subscription and OAuth route registration.
4. Add a cron/admin worker for `LeadInbox::processMeta()`.
5. Add reporting over local leads and source health.

## Deferred

- Campaign creation or budget management.
- Database-backed lead search.
- CRM handoff automation.
- Bidirectional provider sync beyond lead retrieval.
