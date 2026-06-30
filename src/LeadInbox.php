<?php

namespace ficmsSeoSea;

class LeadInbox {
	public function processMeta() {
		return $this->processProvider('meta');
	}

	public function processProvider($provider) {
		$result = ['result'=>true,'files'=>0,'events'=>0,'imported'=>0,'duplicates'=>0,'failed'=>0,'errors'=>[]];
		foreach (glob(PLUGINPATH.'/oauth/webhooks/inbox/'.preg_replace('/[^a-z0-9_-]/i','',trim((string) $provider)).'/*.json',GLOB_NOSORT) ?: [] as $file) {
			$result['files']++;
			$this->processFile($provider,$file,$result);
		}
		$result['result'] = $result['failed'] == 0;
		return $result;
	}

	public function pending($provider = 'meta') {
		return count(glob(PLUGINPATH.'/oauth/webhooks/inbox/'.preg_replace('/[^a-z0-9_-]/i','',trim((string) $provider)).'/*.json',GLOB_NOSORT) ?: []);
	}

	protected function processFile($provider, $file, &$result) {
		$failed = $result['failed'];
		$payload = json_decode(file_get_contents($file),true);
		if (!is_array($payload)) {
			$this->fail($result,$file,'payload_invalid');
			return;
		}
		foreach (($payload['events'] ?? []) as $event) {
			$result['events']++;
			$this->processEvent($provider,$event,$result);
		}
		$this->archive($file,$result['failed'] > $failed ? 'failed' : 'processed');
	}

	protected function processEvent($provider, $event, &$result) {
		$value = is_array($event['change']['value'] ?? null) ? $event['change']['value'] : [];
		if (trim((string) ($value['leadgen_id'] ?? '')) == '' || trim((string) ($value['page_id'] ?? '')) == '') {
			$this->fail($result,'','event_invalid');
			return;
		}
		$source = $this->source($provider,$value['page_id'],$value['form_id'] ?? '');
		if (empty($source)) {
			$this->fail($result,'','source_missing');
			return;
		}
		if ($provider == 'meta') $this->processMetaLead($value,$source,$result);
	}

	protected function processMetaLead($value, $source, &$result) {
		if (!class_exists('\meta\Meta')) {
			$this->fail($result,'','meta_unavailable');
			return;
		}
		$meta = new \meta\Meta($source['account_ref']);
		$pageAccessToken = $meta->pageAccessToken($source['page_id']);
		if ($pageAccessToken == '') {
			$this->fail($result,'','page_access_token_missing');
			return;
		}
		$lead = $meta->lead($value['leadgen_id'],$pageAccessToken);
		if (!is_array($lead)) {
			$this->fail($result,'','lead_fetch_failed');
			return;
		}
		$save = (new Leads())->upsertMeta($lead,$source);
		if (empty($save['result'])) {
			$this->fail($result,'',$save['error'] ?? 'lead_save_failed');
			return;
		}
		$result['imported']++;
		$result['duplicates'] += intval($save['duplicate'] ?? 0);
	}

	protected function source($provider, $pageId, $formId = '') {
		$sources = State::read('lead-sources');
		foreach ([State::sourceKey($provider,$pageId,$formId),State::sourceKey($provider,$pageId,'')] as $key) {
			if (isset($sources[$key]) && intval($sources[$key]['ac'] ?? 0) == 1) return $sources[$key];
		}
		return [];
	}

	protected function fail(&$result, $file, $error) {
		$result['failed']++;
		$result['errors'][] = $error;
		if ($file != '') $this->archive($file,'failed');
	}

	protected function archive($file, $state) {
		$target = PLUGINPATH.'/'.State::PLUGIN.'/data/inbox/'.preg_replace('/[^a-z0-9_-]/i','',trim((string) $state)).'/'.basename($file);
		if (!\helper__files_mkdir($target,true)) return false;
		return rename($file,$target);
	}
}
