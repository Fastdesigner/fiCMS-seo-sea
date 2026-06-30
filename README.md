# fiCMS SEO/SEA

SEO, SEA, visibility and lead management plugin for fiCMS.

## Ownership

This plugin owns business workflows:

- Search Console based SEO visibility analysis
- keyword and landing page opportunities
- SEA campaign and lead visibility workflows
- Meta lead retrieval workflows
- cross-provider reporting for search, ads and leads

Provider transport stays in dependency plugins:

- `google` owns Google OAuth metadata and Google API helpers.
- `meta` owns Meta OAuth metadata and Graph API helpers.
- `oauth` owns connection storage, refresh and handoff.

## OAuth

OAuth connections are created system-wide in the fiCMS integrations settings. This plugin only selects an existing connection and stores workflow-specific targets such as Search Console property, Meta Page/Form IDs or campaign scope.

## Initial Dependencies

```json
{
  "google": "PLUGINPATH/google",
  "meta": "PLUGINPATH/meta"
}
```

Google feature scopes should be requested by the consuming workflow, for example:

```php
$scopes = \google\Scopes::searchConsole();
```

Meta lead scopes should be requested by the consuming workflow, for example:

```php
$scopes = \meta\Scopes::leads();
```
