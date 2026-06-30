<?php

namespace ficmsSeoSea;

class LeadSources {
	protected $language = 'de';

	public function __construct($language = 'de') {
		$this->language = trim((string) $language) !== '' ? trim((string) $language) : 'de';
	}

	public function requiredScopes() {
		return class_exists('\meta\Scopes') ? \meta\Scopes::leads() : ['pages_show_list','pages_read_engagement','pages_read_user_content','leads_retrieval'];
	}

	public function account($accountRef) {
		if (!class_exists('\oauth\OAuth')) return false;
		return \oauth\OAuth::account_load('meta',$accountRef);
	}

	public function missingScopes($accountRef) {
		$account = $this->account($accountRef);
		if (!is_array($account)) return $this->requiredScopes();
		return array_values(array_diff($this->requiredScopes(),is_array($account['scopes'] ?? null) ? $account['scopes'] : []));
	}

	public function pages($accountRef) {
		if (($error = $this->connectionError($accountRef)) != '') return ['result'=>false,'items'=>[],'error'=>$error];
		if (!class_exists('\meta\Meta')) return ['result'=>false,'items'=>[],'error'=>'meta_unavailable'];
		$meta = new \meta\Meta($accountRef);
		$response = $meta->pages();
		if (!is_array($response)) return ['result'=>false,'items'=>[],'error'=>$this->lastError($meta)];
		return ['result'=>true,'items'=>is_array($response['data'] ?? null) ? $response['data'] : [],'error'=>''];
	}

	public function forms($accountRef, $pageId) {
		if (($error = $this->connectionError($accountRef)) != '') return ['result'=>false,'items'=>[],'error'=>$error];
		if (!class_exists('\meta\Meta')) return ['result'=>false,'items'=>[],'error'=>'meta_unavailable'];
		$meta = new \meta\Meta($accountRef);
		$pageAccessToken = $meta->pageAccessToken($pageId);
		if ($pageAccessToken == '') return ['result'=>false,'items'=>[],'error'=>'page_access_token_missing'];
		$response = $meta->leadForms($pageId,$pageAccessToken);
		if (!is_array($response)) return ['result'=>false,'items'=>[],'error'=>$this->lastError($meta)];
		return ['result'=>true,'items'=>is_array($response['data'] ?? null) ? $response['data'] : [],'error'=>''];
	}

	public function enableMetaForm($accountRef, $pageId, $formId, $name = '') {
		if (($error = $this->connectionError($accountRef)) != '') return ['result'=>false,'error'=>$error];
		if (!class_exists('\meta\Meta')) return ['result'=>false,'error'=>'meta_unavailable'];
		if (!class_exists('\oauth\OAuth')) return ['result'=>false,'error'=>'oauth_unavailable'];
		$meta = new \meta\Meta($accountRef);
		$pageAccessToken = $meta->pageAccessToken($pageId);
		if ($pageAccessToken == '') return ['result'=>false,'error'=>'page_access_token_missing'];
		if (!$meta->subscribeLeadgen($pageId,$pageAccessToken)) return ['result'=>false,'error'=>'meta_subscribe_failed','meta'=>$meta->last()];
		$route = \oauth\OAuth::webhook_route_register('meta',$accountRef,[
			'page_id'=>$pageId,
			'form_id'=>$formId,
			'plugin'=>State::PLUGIN,
			'workflow'=>'leads'
		]);
		if (!$route) return ['result'=>false,'error'=>\oauth\OAuth::last_error()];
		return $this->saveSource([
			'provider'=>'meta',
			'account_ref'=>trim((string) $accountRef),
			'page_id'=>trim((string) $pageId),
			'form_id'=>trim((string) $formId),
			'name'=>trim((string) $name),
			'route_id'=>trim((string) ($route['route']['id'] ?? $route['id'] ?? '')),
			'last_error'=>'',
			'ac'=>1
		]);
	}

	public function saveSource($source) {
		$sources = State::read('lead-sources');
		$key = State::sourceKey($source['provider'] ?? '',$source['page_id'] ?? '',$source['form_id'] ?? '');
		if (trim((string) ($source['provider'] ?? '')) == '' || trim((string) ($source['page_id'] ?? '')) == '') return ['result'=>false,'error'=>'source_invalid'];
		$sources[$key] = array_merge($sources[$key] ?? [],$source,[
			'created_at'=>intval($sources[$key]['created_at'] ?? ($_SERVER['now'] ?? time())),
			'updated_at'=>intval($_SERVER['now'] ?? time())
		]);
		return State::write('lead-sources',$sources) ? ['result'=>true,'key'=>$key,'source'=>$sources[$key]] : ['result'=>false,'error'=>'source_save_failed'];
	}

	protected function lastError($client) {
		if (!is_object($client) || !method_exists($client,'last')) return 'request_failed';
		$last = $client->last();
		return trim((string) ($last['error'] ?? '')) != '' ? trim((string) $last['error']) : 'request_failed';
	}

	protected function connectionError($accountRef) {
		$account = $this->account($accountRef);
		if (!is_array($account)) return 'account_missing';
		return array_values(array_diff($this->requiredScopes(),is_array($account['scopes'] ?? null) ? $account['scopes'] : [])) ? 'scope_missing' : '';
	}
}
