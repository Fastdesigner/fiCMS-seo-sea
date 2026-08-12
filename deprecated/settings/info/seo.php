<?php

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

$seo = [
	'output'=>['lists'=>[],'result'=>[],'datalists'=>[]],
	'visibility'=>new \ficmsSeoSea\Visibility($user['language']),
	'lead_sources'=>new \ficmsSeoSea\LeadSources($user['language']),
	'inbox'=>new \ficmsSeoSea\LeadInbox(),
	'google_account_ref'=>trim((string) ($site['seo_google_account_ref'] ?? 'seo')),
	'meta_account_ref'=>trim((string) ($site['seo_meta_account_ref'] ?? 'default')),
	'property'=>trim((string) ($site['seo_google_search_property'] ?? '')),
	'keywords'=>[],
	'brand_terms'=>[],
	'days'=>max(7,min(180,intval($site['seo_google_search_days'] ?? 90))),
	'items'=>[],
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
	if (class_exists('\oauth\OAuth') && method_exists('\oauth\OAuth','accounts')) foreach (\oauth\OAuth::accounts($seo['provider']) as $seo['account_option']) {
		$seo['account_options'][$seo['provider']][] = ['name'=>trim((string) (($seo['account_option']['provider_label'] ?? ucfirst($seo['provider'])).' / '.($seo['account_option']['account_ref'] ?? 'default'))),'value'=>trim((string) ($seo['account_option']['account_ref'] ?? 'default')) ?: 'default'];
	}
}
foreach ([['google',$seo['google_account_ref']],['meta',$seo['meta_account_ref']]] as $seo['selected_account']) {
	if ($seo['selected_account'][1] !== '' && !in_array($seo['selected_account'][1],array_column($seo['account_options'][$seo['selected_account'][0]],'value'),true)) array_unshift($seo['account_options'][$seo['selected_account'][0]],['name'=>$seo['selected_account'][1],'value'=>$seo['selected_account'][1]]);
}

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$seo['action'] = (string) ($_POST['action'] ?? '');

	if ($seo['action'] == 'save_visibility') {
		$seo['google_account_ref'] = trim((string) ($_POST['seo_google_account_ref'] ?? $seo['google_account_ref'])) ?: 'seo';
		$seo['property'] = trim((string) ($_POST['seo_google_search_property'] ?? ''));
		$seo['keywords'] = $seo['visibility']->normalizeList($_POST['seo_google_search_keywords'] ?? '');
		$seo['brand_terms'] = $seo['visibility']->normalizeList($_POST['seo_google_search_brand_terms'] ?? '');
		$seo['days'] = max(7,min(180,intval($_POST['seo_google_search_days'] ?? 90)));
		\system__settings('seo_google_account_ref',$seo['google_account_ref'],true,$user['id']);
		\system__settings('seo_google_search_property',$seo['property'],true,$user['id']);
		\system__settings('seo_google_search_keywords',helper__json_stringify($seo['keywords']),true,$user['id'],1);
		\system__settings('seo_google_search_brand_terms',helper__json_stringify($seo['brand_terms']),true,$user['id'],1);
		\system__settings('seo_google_search_days',$seo['days'],true,$user['id']);
		$seo['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $seo['action'] == 'enable_meta_form') {
		$seo['meta_account_ref'] = trim((string) ($_POST['seo_meta_account_ref'] ?? $seo['meta_account_ref'])) ?: 'default';
		$seo['meta_page_id'] = trim((string) ($_POST['seo_meta_page_id'] ?? ''));
		$seo['meta_form_id'] = trim((string) ($_POST['seo_meta_form_id'] ?? ''));
		$seo['meta_form_name'] = trim((string) ($_POST['seo_meta_form_name'] ?? ''));
		\system__settings('seo_meta_account_ref',$seo['meta_account_ref'],true,$user['id']);
		\system__settings('seo_meta_page_id',$seo['meta_page_id'],true,$user['id']);
		\system__settings('seo_meta_form_id',$seo['meta_form_id'],true,$user['id']);
		\system__settings('seo_meta_form_name',$seo['meta_form_name'],true,$user['id']);
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

$seo['google_missing_scopes'] = $seo['visibility']->missingScopes($seo['google_account_ref']);
$seo['google_account'] = $seo['visibility']->account($seo['google_account_ref']);
$seo['items'][] = ['id'=>$settings['key'].'-google-account','description'=>language__get($user['language'],'_seo_google_account'),'subtitle'=>$seo['google_account'] ? (empty($seo['google_missing_scopes']) ? language__get($user['language'],'_seo_google_connected') : language__get($user['language'],'_seo_google_missing_scope')) : language__get($user['language'],'_seo_google_missing_account'),'attributes'=>!$seo['google_account'] || !empty($seo['google_missing_scopes']) ? ['data-notify'=>'warning'] : []];

if ($seo['google_account'] && empty($seo['google_missing_scopes'])) $seo['properties'] = $seo['visibility']->properties($seo['google_account_ref']);
if ($seo['google_account'] && empty($seo['google_missing_scopes']) && !$seo['properties']['result']) $seo['items'][] = ['id'=>$settings['key'].'-properties-error','tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>$seo['properties']['error'] == '' ? language__get($user['language'],'_seo_google_missing_property') : $seo['properties']['error']];
if ($seo['google_account'] && empty($seo['google_missing_scopes']) && $seo['properties']['result'] && empty($seo['properties']['items'])) $seo['items'][] = ['id'=>$settings['key'].'-properties-empty','tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>language__get($user['language'],'_seo_google_missing_property')];

$seo['property_options'] = $seo['properties']['items'];
if ($seo['property'] !== '' && !in_array($seo['property'],array_column($seo['property_options'],'value'),true)) array_unshift($seo['property_options'],['name'=>$seo['property'],'value'=>$seo['property']]);
$seo['visibility_form'] = [
	['id'=>$settings['key'].'-google-account-ref','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'select','option'=>'seo_google_account_ref','name'=>language__get($user['language'],'_seo_google_account_ref'),'value'=>$seo['google_account_ref'],'options'=>$seo['account_options']['google']]],
	['id'=>$settings['key'].'-property','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>!empty($seo['property_options']) ? 'select' : 'input','option'=>'seo_google_search_property','name'=>language__get($user['language'],'_seo_property'),'value'=>$seo['property'],'options'=>$seo['property_options'],'attributes'=>['placeholder'=>'sc-domain:example.com']]],
	['id'=>$settings['key'].'-keywords','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'multipicker','option'=>'seo_google_search_keywords','name'=>language__get($user['language'],'_seo_keywords'),'value'=>$seo['keywords'],'attributes'=>['data-custom'=>'true','data-seperator'=>'[",","enter"]']]],
	['id'=>$settings['key'].'-brand','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'multipicker','option'=>'seo_google_search_brand_terms','name'=>language__get($user['language'],'_seo_brand_terms'),'value'=>$seo['brand_terms'],'attributes'=>['data-custom'=>'true','data-seperator'=>'[",","enter"]']]],
	['id'=>$settings['key'].'-days','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'number','option'=>'seo_google_search_days','name'=>language__get($user['language'],'_seo_days'),'value'=>$seo['days'],'attributes'=>['min'=>7,'max'=>180,'step'=>1]]]
];
$seo['items'][] = ['id'=>$settings['key'].'-visibility-config','tag'=>'form','classes'=>['forms__wrapper'],'items'=>create__form($settings['form'].'-visibility',$seo['visibility_form'],language__get($user['language'],'_seo_google_title'),language__get($user['language'],'_seo_save'),['load'=>['action'=>'save_visibility']],[],language__get($user['language'],'_seo_oauth_manage'),['load'=>['function'=>'seo__open_integrations']])];

if ($seo['property'] !== '' && !empty($seo['keywords']) && $seo['google_account'] && empty($seo['google_missing_scopes'])) {
	$seo['end_date'] = date('Y-m-d',$_SERVER['today'] - 86400);
	$seo['start_date'] = date('Y-m-d',$_SERVER['today'] - ($seo['days'] * 86400));
	foreach ($seo['keywords'] as $seo['keyword']) {
		$seo['rows'] = [];
		$seo['metrics'] = ['clicks'=>0,'impressions'=>0,'ctr'=>0,'position'=>0];
		$seo['position_weight'] = 0;
		$seo['query'] = $seo['visibility']->queryPages($seo['google_account_ref'],$seo['property'],$seo['keyword'],$seo['start_date'],$seo['end_date'],25);
		foreach ($seo['query']['rows'] as $seo['row']) {
			$seo['page_metrics'] = ['page'=>$seo['row']['keys'][0] ?? '','clicks'=>intval($seo['row']['clicks'] ?? 0),'impressions'=>intval($seo['row']['impressions'] ?? 0),'ctr'=>floatval($seo['row']['ctr'] ?? 0),'position'=>floatval($seo['row']['position'] ?? 0)];
			$seo['metrics']['clicks'] += $seo['page_metrics']['clicks'];
			$seo['metrics']['impressions'] += $seo['page_metrics']['impressions'];
			$seo['position_weight'] += $seo['page_metrics']['position'] * max(1,$seo['page_metrics']['impressions']);
			$seo['rows'][] = $seo['page_metrics'];
		}
		if ($seo['metrics']['impressions'] > 0) {
			$seo['metrics']['ctr'] = $seo['metrics']['clicks'] / $seo['metrics']['impressions'];
			$seo['metrics']['position'] = $seo['position_weight'] / $seo['metrics']['impressions'];
		}
		$seo['summary']['keywords']++;
		$seo['summary']['clicks'] += $seo['metrics']['clicks'];
		$seo['summary']['impressions'] += $seo['metrics']['impressions'];
		$seo['class'] = $seo['visibility']->queryClass($seo['keyword'],$seo['metrics'],$seo['brand_terms']);
		$seo['matches'] = $seo['visibility']->contentMatches($seo['keyword']);
		$seo['detail_items'] = [
			['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['metrics']['impressions'],'label'=>language__get($user['language'],'_seo_metric_impressions')]],
			['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['metrics']['clicks'],'label'=>language__get($user['language'],'_seo_metric_clicks')]],
			['type'=>'statistics','chart'=>'info','values'=>['value'=>number_format($seo['metrics']['ctr'] * 100,2,',','.').' %','label'=>language__get($user['language'],'_seo_metric_ctr')]],
			['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['metrics']['position'] > 0 ? number_format($seo['metrics']['position'],1,',','.') : '-','label'=>language__get($user['language'],'_seo_metric_position')]],
			['id'=>$settings['key'].'-keyword-'.$seo['summary']['keywords'].'-class','tag'=>'font','classes'=>['forms__item'],'attributes'=>$seo['class']['tone'] == 'warning' ? ['data-notify'=>'warning'] : [],'description'=>$seo['class']['label']]
		];
		$seo['bars'] = [];
		foreach ($seo['matches'] as $seo['match']) $seo['bars'][] = ['label'=>($seo['match']['url'] !== '' ? $seo['match']['url'] : $seo['match']['page']),'value'=>$seo['match']['score'],'sub'=>language__get($user['language'],'_seo_content_fit')];
		if (!empty($seo['bars'])) $seo['detail_items'][] = statistics__bars_chart($user['language'],'_seo_content_fit',$seo['bars']);
		if (!empty($seo['rows'])) {
			$seo['page_items'] = [];
			foreach ($seo['rows'] as $seo['row']) $seo['page_items'][] = ['description'=>$seo['row']['page'],'subtitle'=>$seo['row']['clicks'].' Klicks - '.$seo['row']['impressions'].' Impressionen - '.number_format($seo['row']['ctr'] * 100,2,',','.').' % CTR - Position '.number_format($seo['row']['position'],1,',','.')];
			$seo['detail_items'][] = create__dropdown($settings['key'].'-keyword-'.$seo['summary']['keywords'].'-pages',language__get($user['language'],'_seo_search_console_pages'),$seo['page_items']);
		}
		if (!$seo['query']['result']) $seo['detail_items'][] = ['tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>'Search Console: '.$seo['query']['error']];
		$seo['items'][] = create__dropdown($settings['key'].'-keyword-'.$seo['summary']['keywords'],$seo['keyword'],$seo['detail_items'],['subtitle'=>$seo['class']['label'],'list'=>false]);
	}
	array_splice($seo['items'],1,0,[['id'=>$settings['key'].'-summary','classes'=>['statistics__wrapper'],'items'=>[
		['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['summary']['keywords'],'label'=>language__get($user['language'],'_seo_summary_keywords')]],
		['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['summary']['impressions'],'label'=>language__get($user['language'],'_seo_summary_impressions')]],
		['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['summary']['clicks'],'label'=>language__get($user['language'],'_seo_summary_clicks')]],
		['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['summary']['impressions'] > 0 ? number_format(($seo['summary']['clicks'] / $seo['summary']['impressions']) * 100,2,',','.').' %' : '-','label'=>language__get($user['language'],'_seo_summary_ctr')]]
	]]]);
} else if ($seo['property'] == '' || empty($seo['keywords'])) {
	$seo['items'][] = ['id'=>$settings['key'].'-empty','tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>language__get($user['language'],'_seo_keywords_empty')];
}

$seo['meta_missing_scopes'] = $seo['lead_sources']->missingScopes($seo['meta_account_ref']);
$seo['meta_account'] = $seo['lead_sources']->account($seo['meta_account_ref']);
$seo['items'][] = create__dropdown($settings['key'].'-leadgen',language__get($user['language'],'_seo_leads_title'),[],['list'=>false]);
$seo['items'][] = ['id'=>$settings['key'].'-meta-account','description'=>language__get($user['language'],'_seo_meta_account'),'subtitle'=>$seo['meta_account'] ? (empty($seo['meta_missing_scopes']) ? language__get($user['language'],'_seo_meta_connected') : language__get($user['language'],'_seo_meta_missing_scope')) : language__get($user['language'],'_seo_meta_missing_account'),'attributes'=>!$seo['meta_account'] || !empty($seo['meta_missing_scopes']) ? ['data-notify'=>'warning'] : []];

if ($seo['meta_account'] && empty($seo['meta_missing_scopes'])) $seo['pages'] = $seo['lead_sources']->pages($seo['meta_account_ref']);
$seo['page_options'] = [];
foreach ($seo['pages']['items'] as $seo['page']) $seo['page_options'][] = ['name'=>trim((string) ($seo['page']['name'] ?? $seo['page']['id'] ?? '')),'value'=>trim((string) ($seo['page']['id'] ?? ''))];
if ($seo['meta_page_id'] !== '' && !in_array($seo['meta_page_id'],array_column($seo['page_options'],'value'),true)) array_unshift($seo['page_options'],['name'=>$seo['meta_page_id'],'value'=>$seo['meta_page_id']]);
if ($seo['meta_page_id'] !== '' && $seo['meta_account'] && empty($seo['meta_missing_scopes'])) $seo['forms'] = $seo['lead_sources']->forms($seo['meta_account_ref'],$seo['meta_page_id']);
$seo['form_options'] = [];
foreach ($seo['forms']['items'] as $seo['form']) $seo['form_options'][] = ['name'=>trim((string) ($seo['form']['name'] ?? $seo['form']['id'] ?? '')),'value'=>trim((string) ($seo['form']['id'] ?? ''))];
if ($seo['meta_form_id'] !== '' && !in_array($seo['meta_form_id'],array_column($seo['form_options'],'value'),true)) array_unshift($seo['form_options'],['name'=>$seo['meta_form_id'],'value'=>$seo['meta_form_id']]);
$seo['lead_form'] = [
	['id'=>$settings['key'].'-meta-account-ref','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'select','option'=>'seo_meta_account_ref','name'=>language__get($user['language'],'_seo_meta_account_ref'),'value'=>$seo['meta_account_ref'],'options'=>$seo['account_options']['meta']]],
	['id'=>$settings['key'].'-meta-page','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>!empty($seo['page_options']) ? 'select' : 'input','option'=>'seo_meta_page_id','name'=>language__get($user['language'],'_seo_meta_page'),'value'=>$seo['meta_page_id'],'options'=>$seo['page_options']]],
	['id'=>$settings['key'].'-meta-form','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>!empty($seo['form_options']) ? 'select' : 'input','option'=>'seo_meta_form_id','name'=>language__get($user['language'],'_seo_meta_form'),'value'=>$seo['meta_form_id'],'options'=>$seo['form_options']]],
	['id'=>$settings['key'].'-meta-form-name','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'input','option'=>'seo_meta_form_name','name'=>language__get($user['language'],'_seo_meta_form_name'),'value'=>$seo['meta_form_name']]]
];
$seo['items'][] = ['id'=>$settings['key'].'-lead-config','tag'=>'form','classes'=>['forms__wrapper'],'items'=>create__form($settings['form'].'-leads',$seo['lead_form'],language__get($user['language'],'_seo_leads_title'),language__get($user['language'],'_seo_meta_enable'),['load'=>['action'=>'enable_meta_form']],[],language__get($user['language'],'_seo_oauth_manage'),['load'=>['function'=>'seo__open_integrations']])];
if ($seo['meta_account'] && empty($seo['meta_missing_scopes']) && !$seo['pages']['result']) $seo['items'][] = ['id'=>$settings['key'].'-pages-error','tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>$seo['pages']['error'] ?: language__get($user['language'],'_seo_meta_missing_page')];
if ($seo['meta_page_id'] !== '' && $seo['meta_account'] && empty($seo['meta_missing_scopes']) && !$seo['forms']['result']) $seo['items'][] = ['id'=>$settings['key'].'-forms-error','tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>$seo['forms']['error'] ?: language__get($user['language'],'_seo_meta_missing_form')];

$seo['lead_counts'] = ['total'=>0,'new'=>0];
foreach ($seo['leads'] as $seo['lead']) {
	$seo['lead_counts']['total']++;
	if (($seo['lead']['status'] ?? '') == 'new') $seo['lead_counts']['new']++;
}
$seo['last_result'] = is_array($seo['last_inbox']['result'] ?? null) ? $seo['last_inbox']['result'] : ['imported'=>0,'failed'=>0,'events'=>0];
$seo['items'][] = ['id'=>$settings['key'].'-lead-stats','classes'=>['statistics__wrapper'],'items'=>[
	['type'=>'statistics','chart'=>'info','values'=>['value'=>count($seo['sources']),'label'=>language__get($user['language'],'_seo_leads_sources')]],
	['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['lead_counts']['total'],'label'=>language__get($user['language'],'_seo_leads_total')]],
	['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['lead_counts']['new'],'label'=>language__get($user['language'],'_seo_leads_new')]],
	['type'=>'statistics','chart'=>'info','values'=>['value'=>$seo['inbox']->pending('meta'),'label'=>language__get($user['language'],'_seo_leads_pending')]]
]];
$seo['items'][] = ['id'=>$settings['key'].'-process-inbox','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button'],'description'=>language__get($user['language'],'_seo_leads_process'),'actions'=>['load'=>['action'=>'process_inbox']]];
if (!empty($seo['last_inbox'])) $seo['items'][] = ['id'=>$settings['key'].'-last-inbox','description'=>language__get($user['language'],'_seo_leads_last_run'),'subtitle'=>format__date_relative(intval($seo['last_inbox']['ran_at'] ?? 0),'relative',$user['language'],true).' · '.$seo['last_result']['events'].' Events · '.$seo['last_result']['imported'].' importiert · '.$seo['last_result']['failed'].' Fehler','attributes'=>intval($seo['last_result']['failed'] ?? 0) > 0 ? ['data-notify'=>'warning'] : []];

$seo['source_items'] = [];
foreach ($seo['sources'] as $seo['source_key'] => $seo['source']) $seo['source_items'][] = ['description'=>trim((string) ($seo['source']['name'] ?? '')) !== '' ? trim((string) $seo['source']['name']) : $seo['source_key'],'subtitle'=>'Page '.$seo['source']['page_id'].' · Form '.$seo['source']['form_id'].' · '.$seo['source']['account_ref'],'attributes'=>trim((string) ($seo['source']['last_error'] ?? '')) != '' ? ['data-notify'=>'warning'] : []];
$seo['items'][] = !empty($seo['source_items']) ? create__dropdown($settings['key'].'-sources',language__get($user['language'],'_seo_leads_sources'),$seo['source_items']) : ['id'=>$settings['key'].'-sources-empty','tag'=>'font','classes'=>['forms__item'],'attributes'=>['data-notify'=>'warning'],'description'=>language__get($user['language'],'_seo_leads_none')];

$seo['recent_items'] = [];
foreach (array_slice(array_reverse($seo['leads'],true),0,10,true) as $seo['lead_key'] => $seo['lead']) $seo['recent_items'][] = ['description'=>trim((string) ($seo['lead']['fields']['email'] ?? $seo['lead']['fields']['full_name'] ?? $seo['lead_key'])),'subtitle'=>trim(($seo['lead']['created_time'] ?? '').' · Form '.($seo['lead']['form_id'] ?? '').' · Kampagne '.($seo['lead']['campaign_id'] ?? ''),' · ')];
$seo['items'][] = !empty($seo['recent_items']) ? create__dropdown($settings['key'].'-recent-leads',language__get($user['language'],'_seo_leads_recent'),$seo['recent_items']) : ['id'=>$settings['key'].'-recent-empty','tag'=>'font','classes'=>['forms__item'],'description'=>language__get($user['language'],'_seo_leads_no_recent')];

$seo['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','clear'=>true,'items'=>$seo['items']];
foreach ($seo['output'] as $seo['key'] => $seo['value']) {
	if (empty($seo['value'])) continue;
	if (!isset($settings['output'][$seo['key']])) $settings['output'][$seo['key']] = [];
	$settings['output'][$seo['key']] = array_merge($settings['output'][$seo['key']],$seo['value']);
}

unset($seo);

