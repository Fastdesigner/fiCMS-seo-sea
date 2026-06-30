# Handoff: Meta Lead Webhooks

`fiCMS-seo-sea` owns the lead workflow. OAuth owns transport, proxy routing and signed delivery. The `meta` plugin owns Graph API calls.

## Setup Flow

When an admin enables Meta leads for a Page/Form, `fiCMS-seo-sea` should:

1. Select an existing OAuth account from the system integrations.
2. Resolve the Page access token through `\meta\Meta`.
3. Subscribe the Meta app to Page lead events.
4. Register the proxy route through `\oauth\OAuth`.
5. Store the local workflow source in this plugin.

Example:

```php
$meta = new \meta\Meta($accountRef);
$pageAccessToken = $meta->pageAccessToken($pageId);

if ($pageAccessToken == '') return ['result'=>false,'error'=>'page_access_token_missing'];

$subscribed = $meta->subscribeLeadgen($pageId,$pageAccessToken);
if (!$subscribed) return ['result'=>false,'error'=>'meta_subscribe_failed','meta'=>$meta->last()];

$route = \oauth\OAuth::webhook_route_register('meta',$accountRef,[
	'page_id'=>$pageId,
	'form_id'=>$formId,
	'plugin'=>'fiCMS-seo-sea',
	'workflow'=>'leads'
]);

if (!$route) return ['result'=>false,'error'=>\oauth\OAuth::last_error()];
```

## Local State

Store workflow-owned source state in `PLUGINPATH/fiCMS-seo-sea/data/lead-sources.json`.

Suggested shape:

```json
{
	"meta:PAGE_ID:FORM_ID": {
		"provider": "meta",
		"account_ref": "default",
		"page_id": "PAGE_ID",
		"form_id": "FORM_ID",
		"name": "Website Kontaktformular",
		"created_at": 0,
		"updated_at": 0,
		"last_sync_at": 0,
		"ac": 1
	}
}
```

## Receive Flow

The customer CMS receives signed forwards at:

```text
/oauth.php?action=webhook_receive&provider=meta
```

OAuth verifies the proxy signature and writes the payload to:

```text
PLUGINPATH/oauth/webhooks/inbox/meta/*.json
```

`fiCMS-seo-sea` should process that inbox in a cron or admin-triggered worker:

```php
foreach (glob(PLUGINPATH.'/oauth/webhooks/inbox/meta/*.json', GLOB_NOSORT) ?: [] as $file) {
	$payload = json_decode(file_get_contents($file),true);
	if (!is_array($payload)) continue;

	foreach (($payload['events'] ?? []) as $event) {
		$value = $event['change']['value'] ?? [];
		$leadId = trim((string) ($value['leadgen_id'] ?? ''));
		$pageId = trim((string) ($value['page_id'] ?? ''));
		$formId = trim((string) ($value['form_id'] ?? ''));

		if ($leadId == '' || $pageId == '') continue;
	}
}
```

For each event, lookup the matching lead source by `provider + page_id + form_id`, fetch details through the `meta` plugin, then write lead workflow state locally.

```php
$meta = new \meta\Meta($source['account_ref']);
$pageAccessToken = $meta->pageAccessToken($source['page_id']);
$lead = $meta->lead($leadId,$pageAccessToken);
```

## Boundaries

- OAuth does not know what a Meta lead means.
- The proxy never forwards to every customer installation.
- The proxy route key is `provider + page_id + form_id`.
- A Page/Form route is unique by default.
- `fiCMS-seo-sea` decides lead deduplication, field mapping, CRM handoff and retry status.
