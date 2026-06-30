<?php

namespace ficmsSeoSea;

class Leads {
	public function all() {
		return State::read('leads');
	}

	public function upsertMeta($lead, $source) {
		if (!is_array($lead) || trim((string) ($lead['id'] ?? '')) == '') return ['result'=>false,'error'=>'lead_invalid'];
		$leads = $this->all();
		$leadKey = State::leadKey('meta',$lead['id']);
		$duplicate = isset($leads[$leadKey]) ? 1 : 0;
		$leads[$leadKey] = array_merge($leads[$leadKey] ?? [],[
			'provider'=>'meta',
			'lead_id'=>trim((string) $lead['id']),
			'source_key'=>State::sourceKey('meta',$lead['page_id'] ?? ($source['page_id'] ?? ''),$lead['form_id'] ?? ($source['form_id'] ?? '')),
			'page_id'=>trim((string) ($lead['page_id'] ?? ($source['page_id'] ?? ''))),
			'form_id'=>trim((string) ($lead['form_id'] ?? ($source['form_id'] ?? ''))),
			'campaign_id'=>trim((string) ($lead['campaign_id'] ?? '')),
			'adset_id'=>trim((string) ($lead['adset_id'] ?? '')),
			'ad_id'=>trim((string) ($lead['ad_id'] ?? '')),
			'platform'=>trim((string) ($lead['platform'] ?? '')),
			'created_time'=>trim((string) ($lead['created_time'] ?? '')),
			'fields'=>$this->fieldData($lead['field_data'] ?? []),
			'raw'=>$lead,
			'status'=>$leads[$leadKey]['status'] ?? 'new',
			'created_at'=>intval($leads[$leadKey]['created_at'] ?? ($_SERVER['now'] ?? time())),
			'updated_at'=>intval($_SERVER['now'] ?? time()),
			'last_error'=>''
		]);
		return State::write('leads',$leads) ? ['result'=>true,'duplicate'=>$duplicate,'key'=>$leadKey] : ['result'=>false,'error'=>'lead_save_failed'];
	}

	public function fieldData($fieldData) {
		$fields = [];
		if (!is_array($fieldData)) return $fields;
		foreach ($fieldData as $field) {
			$name = trim((string) ($field['name'] ?? ''));
			if ($name == '') continue;
			$fields[$name] = implode(', ',array_map('strval',is_array($field['values'] ?? null) ? $field['values'] : []));
		}
		return $fields;
	}
}
