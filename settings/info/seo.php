<?php

if (!file_exists(DESIGNSYSTEM.'/assets/js/admin/sys.js')) {
	require PLUGINPATH.'/fiCMS-seo-sea/deprecated/settings/info/seo.php';
	return;
}
if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

$seo = [
	'visibility'=>new \ficmsSeoSea\Visibility($user['language']),
	'lead_sources'=>new \ficmsSeoSea\LeadSources($user['language']),
	'inbox'=>new \ficmsSeoSea\LeadInbox(),
	'output'=>['result'=>[]],
	'google_account_ref'=>trim((string) ($site['seo_google_account_ref'] ?? 'seo')),
	'meta_account_ref'=>trim((string) ($site['seo_meta_account_ref'] ?? 'default')),
	'property'=>trim((string) ($site['seo_google_search_property'] ?? '')),
	'days'=>max(7,min(180,intval($site['seo_google_search_days'] ?? 90))),
	'summary'=>['keywords'=>0,'impressions'=>0,'clicks'=>0],
	'properties'=>['result'=>false,'items'=>[],'error'=>''],
	'pages'=>['result'=>false,'items'=>[],'error'=>''],
	'forms'=>['result'=>false,'items'=>[],'error'=>''],
	'sources'=>\ficmsSeoSea\State::read('lead-sources'),
	'leads'=>\ficmsSeoSea\State::read('leads'),
	'last_inbox'=>\ficmsSeoSea\State::read('last-inbox-result'),
	'meta_page_id'=>trim((string) ($site['seo_meta_page_id'] ?? '')),
	'meta_form_id'=>trim((string) ($site['seo_meta_form_id'] ?? '')),
	'meta_form_name'=>trim((string) ($site['seo_meta_form_name'] ?? '')),
	'account_options'=>['google'=>[],'meta'=>[]]
];
$seo['keywords'] = $seo['visibility']->normalizeList($site['seo_google_search_keywords'] ?? '');
$seo['brand_terms'] = $seo['visibility']->normalizeList($site['seo_google_search_brand_terms'] ?? '');
foreach (['google','meta'] as $seo['provider']) {
	$seo['account_options'][$seo['provider']][] = ['name'=>'default','value'=>'default'];
	if (class_exists('\oauth\OAuth') && method_exists('\oauth\OAuth','accounts')) foreach (\oauth\OAuth::accounts($seo['provider']) as $seo['account_option']) $seo['account_options'][$seo['provider']][] = [
		'name'=>trim((string) (($seo['account_option']['provider_label'] ?? ucfirst($seo['provider'])).' / '.($seo['account_option']['account_ref'] ?? 'default'))),
		'value'=>trim((string) ($seo['account_option']['account_ref'] ?? 'default')) ?: 'default'
	];
}
foreach ([['google',$seo['google_account_ref']],['meta',$seo['meta_account_ref']]] as $seo['selected_account']) if ($seo['selected_account'][1] !== '' && !in_array($seo['selected_account'][1],array_column($seo['account_options'][$seo['selected_account'][0]],'value'),true)) array_unshift($seo['account_options'][$seo['selected_account'][0]],['name'=>$seo['selected_account'][1],'value'=>$seo['selected_account'][1]]);

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$seo['action'] = (string) ($_POST['action'] ?? '');
	if ($seo['action'] == 'save_visibility') {
		$seo['google_account_ref'] = trim((string) ($_POST['seo_google_account_ref'] ?? $seo['google_account_ref'])) ?: 'seo';
		$seo['property'] = trim((string) ($_POST['seo_google_search_property'] ?? ''));
		$seo['keywords'] = $seo['visibility']->normalizeList($_POST['seo_google_search_keywords'] ?? '');
		$seo['brand_terms'] = $seo['visibility']->normalizeList($_POST['seo_google_search_brand_terms'] ?? '');
		$seo['days'] = max(7,min(180,intval($_POST['seo_google_search_days'] ?? 90)));
		foreach (['seo_google_account_ref'=>$seo['google_account_ref'],'seo_google_search_property'=>$seo['property'],'seo_google_search_keywords'=>helper__json_stringify($seo['keywords']),'seo_google_search_brand_terms'=>helper__json_stringify($seo['brand_terms']),'seo_google_search_days'=>$seo['days']] as $seo['name'] => $seo['value']) parseSetting($seo['name'],$seo['value'],true,$user['id'],str_contains($seo['name'],'keywords') || str_contains($seo['name'],'brand_terms') ? 1 : 0);
		$seo['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}
	if (!isset($_POST['handled']) && $seo['action'] == 'enable_meta_form') {
		$seo['meta_account_ref'] = trim((string) ($_POST['seo_meta_account_ref'] ?? $seo['meta_account_ref'])) ?: 'default';
		$seo['meta_page_id'] = trim((string) ($_POST['seo_meta_page_id'] ?? ''));
		$seo['meta_form_id'] = trim((string) ($_POST['seo_meta_form_id'] ?? ''));
		$seo['meta_form_name'] = trim((string) ($_POST['seo_meta_form_name'] ?? ''));
		foreach (['seo_meta_account_ref'=>$seo['meta_account_ref'],'seo_meta_page_id'=>$seo['meta_page_id'],'seo_meta_form_id'=>$seo['meta_form_id'],'seo_meta_form_name'=>$seo['meta_form_name']] as $seo['name'] => $seo['value']) parseSetting($seo['name'],$seo['value'],true,$user['id']);
		$seo['output']['result'] = $seo['lead_sources']->enableMetaForm($seo['meta_account_ref'],$seo['meta_page_id'],$seo['meta_form_id'],$seo['meta_form_name']);
		$seo['sources'] = \ficmsSeoSea\State::read('lead-sources');
		$_POST['handled'] = true;
	}
	if (!isset($_POST['handled']) && $seo['action'] == 'process_inbox') {
		$seo['process_result'] = $seo['inbox']->processMeta();
		\ficmsSeoSea\State::write('last-inbox-result',['ran_at'=>intval($_SERVER['now'] ?? time()),'trigger'=>'settings','result'=>$seo['process_result']]);
		$seo['last_inbox'] = \ficmsSeoSea\State::read('last-inbox-result');
		$seo['leads'] = \ficmsSeoSea\State::read('leads');
		$seo['output']['result'] = array_merge(['result'=>true],$seo['process_result']);
		$_POST['handled'] = true;
	}
}

$seo['ui'] = new \ficms\Ui($settings['key'],'seo',$user['language']);
$seo['ui']->clear();
$seo['google_missing_scopes'] = $seo['visibility']->missingScopes($seo['google_account_ref']);
$seo['google_account'] = $seo['visibility']->account($seo['google_account_ref']);
$seo['ui']->item('google-account',['label'=>language__get($user['language'],'_seo_google_account'),'subtitle'=>$seo['google_account'] ? (!$seo['google_missing_scopes'] ? language__get($user['language'],'_seo_google_connected') : language__get($user['language'],'_seo_google_missing_scope')) : language__get($user['language'],'_seo_google_missing_account'),'notify'=>!$seo['google_account'] || $seo['google_missing_scopes'] ? 'warning' : false]);
if ($seo['google_account'] && !$seo['google_missing_scopes']) $seo['properties'] = $seo['visibility']->properties($seo['google_account_ref']);
if ($seo['google_account'] && !$seo['google_missing_scopes'] && (!$seo['properties']['result'] || !$seo['properties']['items'])) $seo['ui']->item('properties-error',['label'=>$seo['properties']['error'] ?: language__get($user['language'],'_seo_google_missing_property'),'notify'=>'warning']);
$seo['property_options'] = $seo['properties']['items'];
if ($seo['property'] !== '' && !in_array($seo['property'],array_column($seo['property_options'],'value'),true)) array_unshift($seo['property_options'],['name'=>$seo['property'],'value'=>$seo['property']]);

$seo['ui']->form('visibility',['id'=>$settings['form'].'-visibility','label'=>language__get($user['language'],'_seo_google_title')]);
$seo['ui']->slot($settings['form'].'-visibility-headline',['headline'=>language__get($user['language'],'_seo_google_title')]);
$seo['visibility_body'] = $seo['ui']->slot($settings['form'].'-visibility-body',['clear'=>true]);
$seo['visibility_body']->field('seo_google_account_ref','select',$seo['google_account_ref'],['label'=>language__get($user['language'],'_seo_google_account_ref'),'options'=>$seo['account_options']['google'],'call'=>false]);
$seo['visibility_body']->field('seo_google_search_property',$seo['property_options'] ? 'select' : 'input',$seo['property'],['label'=>language__get($user['language'],'_seo_property'),'options'=>$seo['property_options'],'attrs'=>['placeholder'=>'sc-domain:example.com'],'call'=>false]);
$seo['visibility_body']->field('seo_google_search_keywords','multipicker',$seo['keywords'],['label'=>language__get($user['language'],'_seo_keywords'),'custom'=>true,'attrs'=>['data-seperator'=>'[",","enter"]'],'call'=>false]);
$seo['visibility_body']->field('seo_google_search_brand_terms','multipicker',$seo['brand_terms'],['label'=>language__get($user['language'],'_seo_brand_terms'),'custom'=>true,'attrs'=>['data-seperator'=>'[",","enter"]'],'call'=>false]);
$seo['visibility_body']->field('seo_google_search_days','number',$seo['days'],['label'=>language__get($user['language'],'_seo_days'),'attrs'=>['min'=>7,'max'=>180,'step'=>1],'call'=>false]);
$seo['visibility_submit'] = $seo['ui']->slot($settings['form'].'-visibility-submit-wrapper',['clear'=>true]);
$seo['visibility_submit']->button('save',['label'=>language__get($user['language'],'_seo_save'),'action'=>'save_visibility']);
$seo['visibility_submit']->button('oauth',['label'=>language__get($user['language'],'_seo_oauth_manage'),'call'=>'seo__open_integrations']);

if ($seo['property'] !== '' && $seo['keywords'] && $seo['google_account'] && !$seo['google_missing_scopes']) {
	foreach ($seo['keywords'] as $seo['keyword']) {
		$seo['metrics'] = ['clicks'=>0,'impressions'=>0,'ctr'=>0,'position'=>0];
		$seo['query'] = $seo['visibility']->queryPages($seo['google_account_ref'],$seo['property'],$seo['keyword'],date('Y-m-d',$_SERVER['today'] - ($seo['days'] * 86400)),date('Y-m-d',$_SERVER['today'] - 86400),25);
		foreach ($seo['query']['rows'] as $seo['row']) {
			$seo['metrics']['clicks'] += intval($seo['row']['clicks'] ?? 0);
			$seo['metrics']['impressions'] += intval($seo['row']['impressions'] ?? 0);
			$seo['metrics']['position'] += floatval($seo['row']['position'] ?? 0) * max(1,intval($seo['row']['impressions'] ?? 0));
		}
		if ($seo['metrics']['impressions'] > 0) {
			$seo['metrics']['ctr'] = $seo['metrics']['clicks'] / $seo['metrics']['impressions'];
			$seo['metrics']['position'] /= $seo['metrics']['impressions'];
		}
		$seo['summary']['keywords']++;
		$seo['summary']['clicks'] += $seo['metrics']['clicks'];
		$seo['summary']['impressions'] += $seo['metrics']['impressions'];
		$seo['class'] = $seo['visibility']->queryClass($seo['keyword'],$seo['metrics'],$seo['brand_terms']);
		$seo['keyword_node'] = $seo['ui']->dropdown('keyword-'.$seo['summary']['keywords'],['label'=>$seo['keyword'],'subtitle'=>$seo['class']['label'],'independent'=>true]);
		$seo['keyword_prefix'] = $settings['key'].'-keyword-'.$seo['summary']['keywords'];
		$seo['keyword_stats'] = $seo['keyword_node']->listing('stats',['id'=>$seo['keyword_prefix'].'-stats','kind'=>'statistics']);
		foreach (['impressions','clicks','position'] as $seo['metric']) $seo['keyword_stats']->statistics($seo['metric'],'info',['value'=>round($seo['metrics'][$seo['metric']],1),'label'=>language__get($user['language'],'_seo_metric_'.$seo['metric'])],['id'=>$seo['keyword_prefix'].'-'.$seo['metric']]);
		$seo['keyword_stats']->statistics('ctr','info',['value'=>number_format($seo['metrics']['ctr'] * 100,2,',','.').' %','label'=>language__get($user['language'],'_seo_metric_ctr')],['id'=>$seo['keyword_prefix'].'-ctr']);
		$seo['bars'] = [];
		foreach ($seo['visibility']->contentMatches($seo['keyword']) as $seo['match']) $seo['bars'][] = ['label'=>$seo['match']['url'] !== '' ? $seo['match']['url'] : $seo['match']['page'],'value'=>$seo['match']['score'],'sub'=>language__get($user['language'],'_seo_content_fit')];
		if ($seo['bars']) $seo['keyword_node']->statistics('content-fit','bars',['rows'=>$seo['bars']],['id'=>$seo['keyword_prefix'].'-content-fit','attrs'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_seo_content_fit')]]);
		if ($seo['query']['rows']) {
			$seo['pages_node'] = $seo['keyword_node']->dropdown('pages',['id'=>$seo['keyword_prefix'].'-pages','label'=>language__get($user['language'],'_seo_search_console_pages'),'independent'=>true]);
			foreach ($seo['query']['rows'] as $seo['row']) $seo['pages_node']->item('page-'.$seo['ui']->sequence(),['label'=>(string) ($seo['row']['keys'][0] ?? ''),'subtitle'=>intval($seo['row']['clicks'] ?? 0).' '.language__get($user['language'],'_seo_metric_clicks').' · '.intval($seo['row']['impressions'] ?? 0).' '.language__get($user['language'],'_seo_metric_impressions')]);
		}
		if (!$seo['query']['result']) $seo['keyword_node']->item('error',['id'=>$seo['keyword_prefix'].'-error','label'=>$seo['query']['error'],'notify'=>'warning']);
	}
	$seo['summary_node'] = $seo['ui']->listing('summary',['kind'=>'statistics']);
	foreach (['keywords','impressions','clicks'] as $seo['metric']) $seo['summary_node']->statistics($seo['metric'],'info',['value'=>$seo['summary'][$seo['metric']],'label'=>language__get($user['language'],'_seo_summary_'.$seo['metric'])]);
	$seo['summary_node']->statistics('ctr','info',['value'=>$seo['summary']['impressions'] > 0 ? number_format(($seo['summary']['clicks'] / $seo['summary']['impressions']) * 100,2,',','.').' %' : '-','label'=>language__get($user['language'],'_seo_summary_ctr')]);
} elseif ($seo['property'] == '' || !$seo['keywords']) $seo['ui']->item('empty',['label'=>language__get($user['language'],'_seo_keywords_empty'),'notify'=>'warning']);

$seo['meta_missing_scopes'] = $seo['lead_sources']->missingScopes($seo['meta_account_ref']);
$seo['meta_account'] = $seo['lead_sources']->account($seo['meta_account_ref']);
$seo['ui']->text('leadgen',language__get($user['language'],'_seo_leads_title'));
$seo['ui']->item('meta-account',['label'=>language__get($user['language'],'_seo_meta_account'),'subtitle'=>$seo['meta_account'] ? (!$seo['meta_missing_scopes'] ? language__get($user['language'],'_seo_meta_connected') : language__get($user['language'],'_seo_meta_missing_scope')) : language__get($user['language'],'_seo_meta_missing_account'),'notify'=>!$seo['meta_account'] || $seo['meta_missing_scopes'] ? 'warning' : false]);
if ($seo['meta_account'] && !$seo['meta_missing_scopes']) $seo['pages'] = $seo['lead_sources']->pages($seo['meta_account_ref']);
$seo['page_options'] = [];
foreach ($seo['pages']['items'] as $seo['page']) $seo['page_options'][] = ['name'=>trim((string) ($seo['page']['name'] ?? $seo['page']['id'] ?? '')),'value'=>trim((string) ($seo['page']['id'] ?? ''))];
if ($seo['meta_page_id'] !== '' && !in_array($seo['meta_page_id'],array_column($seo['page_options'],'value'),true)) array_unshift($seo['page_options'],['name'=>$seo['meta_page_id'],'value'=>$seo['meta_page_id']]);
if ($seo['meta_page_id'] !== '' && $seo['meta_account'] && !$seo['meta_missing_scopes']) $seo['forms'] = $seo['lead_sources']->forms($seo['meta_account_ref'],$seo['meta_page_id']);
$seo['form_options'] = [];
foreach ($seo['forms']['items'] as $seo['form']) $seo['form_options'][] = ['name'=>trim((string) ($seo['form']['name'] ?? $seo['form']['id'] ?? '')),'value'=>trim((string) ($seo['form']['id'] ?? ''))];
if ($seo['meta_form_id'] !== '' && !in_array($seo['meta_form_id'],array_column($seo['form_options'],'value'),true)) array_unshift($seo['form_options'],['name'=>$seo['meta_form_id'],'value'=>$seo['meta_form_id']]);

$seo['ui']->form('leads',['id'=>$settings['form'].'-leads','label'=>language__get($user['language'],'_seo_leads_title')]);
$seo['ui']->slot($settings['form'].'-leads-headline',['headline'=>language__get($user['language'],'_seo_leads_title')]);
$seo['lead_body'] = $seo['ui']->slot($settings['form'].'-leads-body',['clear'=>true]);
foreach ([
	['seo_meta_account_ref','select',$seo['meta_account_ref'],$seo['account_options']['meta'],'_seo_meta_account_ref'],
	['seo_meta_page_id',$seo['page_options'] ? 'select' : 'input',$seo['meta_page_id'],$seo['page_options'],'_seo_meta_page'],
	['seo_meta_form_id',$seo['form_options'] ? 'select' : 'input',$seo['meta_form_id'],$seo['form_options'],'_seo_meta_form'],
	['seo_meta_form_name','input',$seo['meta_form_name'],[],'_seo_meta_form_name']
] as $seo['field']) $seo['lead_body']->field($seo['field'][0],$seo['field'][1],$seo['field'][2],['label'=>language__get($user['language'],$seo['field'][4]),'options'=>$seo['field'][3],'call'=>false]);
$seo['lead_submit'] = $seo['ui']->slot($settings['form'].'-leads-submit-wrapper',['clear'=>true]);
$seo['lead_submit']->button('enable',['label'=>language__get($user['language'],'_seo_meta_enable'),'action'=>'enable_meta_form']);
$seo['lead_submit']->button('oauth',['label'=>language__get($user['language'],'_seo_oauth_manage'),'call'=>'seo__open_integrations']);

$seo['lead_counts'] = ['total'=>count($seo['leads']),'new'=>0];
foreach ($seo['leads'] as $seo['lead']) if (($seo['lead']['status'] ?? '') == 'new') $seo['lead_counts']['new']++;
$seo['lead_stats'] = $seo['ui']->listing('lead-stats',['kind'=>'statistics']);
foreach (['sources'=>count($seo['sources']),'total'=>$seo['lead_counts']['total'],'new'=>$seo['lead_counts']['new'],'pending'=>$seo['inbox']->pending('meta')] as $seo['metric'] => $seo['value']) $seo['lead_stats']->statistics($seo['metric'],'info',['value'=>$seo['value'],'label'=>language__get($user['language'],'_seo_leads_'.$seo['metric'])]);
$seo['ui']->button('process-inbox',['label'=>language__get($user['language'],'_seo_leads_process'),'action'=>'process_inbox']);
if ($seo['last_inbox']) $seo['ui']->item('last-inbox',['label'=>language__get($user['language'],'_seo_leads_last_run'),'subtitle'=>format__date_relative(intval($seo['last_inbox']['ran_at'] ?? 0),'relative',$user['language'],true)]);
if ($seo['sources']) {
	$seo['sources_node'] = $seo['ui']->dropdown('sources',['label'=>language__get($user['language'],'_seo_leads_sources'),'independent'=>true]);
	foreach ($seo['sources'] as $seo['source_key'] => $seo['source']) $seo['sources_node']->item('source-'.$seo['ui']->sequence(),['label'=>trim((string) ($seo['source']['name'] ?? '')) ?: $seo['source_key'],'subtitle'=>trim((string) ($seo['source']['account_ref'] ?? '')),'notify'=>trim((string) ($seo['source']['last_error'] ?? '')) !== '' ? 'warning' : false]);
} else $seo['ui']->item('sources-empty',['label'=>language__get($user['language'],'_seo_leads_none'),'notify'=>'warning']);
if ($seo['leads']) {
	$seo['recent'] = $seo['ui']->dropdown('recent',['label'=>language__get($user['language'],'_seo_leads_recent'),'independent'=>true]);
	foreach (array_slice(array_reverse($seo['leads'],true),0,10,true) as $seo['lead_key'] => $seo['lead']) $seo['recent']->item('lead-'.$seo['ui']->sequence(),['label'=>trim((string) ($seo['lead']['fields']['email'] ?? $seo['lead']['fields']['full_name'] ?? $seo['lead_key'])),'subtitle'=>trim((string) ($seo['lead']['created_time'] ?? ''))]);
} else $seo['ui']->item('recent-empty',['label'=>language__get($user['language'],'_seo_leads_no_recent')]);

$seo['ui']->emit($settings);
if ($seo['output']['result']) {
	if (!isset($settings['output']['result'])) $settings['output']['result'] = [];
	$settings['output']['result'] = array_merge($settings['output']['result'],$seo['output']['result']);
}
unset($seo);
