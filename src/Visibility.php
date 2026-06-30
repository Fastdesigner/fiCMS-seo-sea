<?php

namespace ficmsSeoSea;

class Visibility {
	protected $language = 'de';

	public function __construct($language = 'de') {
		$this->language = trim((string) $language) !== '' ? trim((string) $language) : 'de';
	}

	public function requiredScopes() {
		return class_exists('\google\Scopes') ? \google\Scopes::searchConsole() : ['https://www.googleapis.com/auth/webmasters.readonly'];
	}

	public function account($accountRef = 'seo') {
		if (!class_exists('\oauth\OAuth')) return false;
		return \oauth\OAuth::account_load('google',$accountRef);
	}

	public function missingScopes($accountRef = 'seo') {
		$account = $this->account($accountRef);
		if (!is_array($account)) return $this->requiredScopes();
		return array_values(array_diff($this->requiredScopes(),is_array($account['scopes'] ?? null) ? $account['scopes'] : []));
	}

	public function properties($accountRef = 'seo') {
		if (($error = $this->connectionError($accountRef)) != '') return ['result'=>false,'items'=>[],'error'=>$error];
		if (!class_exists('\google\SearchConsole')) return ['result'=>false,'items'=>[],'error'=>'google_unavailable'];
		$console = new \google\SearchConsole($accountRef);
		$sites = $console->sites();
		if (!is_array($sites)) return ['result'=>false,'items'=>[],'error'=>$this->lastError($console)];
		$result = ['result'=>true,'items'=>[],'error'=>''];
		foreach ($sites['siteEntry'] ?? [] as $site) {
			$siteUrl = trim((string) ($site['siteUrl'] ?? ''));
			$permission = trim((string) ($site['permissionLevel'] ?? ''));
			if ($siteUrl == '') continue;
			$result['items'][] = [
				'name'=>$siteUrl.($permission != '' ? ' ('.$permission.')' : ''),
				'value'=>$siteUrl
			];
		}
		return $result;
	}

	public function queryPages($accountRef, $property, $query, $startDate, $endDate, $limit = 25) {
		if (!class_exists('\google\SearchConsole')) return ['result'=>false,'rows'=>[],'error'=>'google_unavailable'];
		$console = new \google\SearchConsole($accountRef);
		$response = $console->queryPages($property,$query,$startDate,$endDate,$limit);
		if (!is_array($response)) return ['result'=>false,'rows'=>[],'error'=>$this->lastError($console)];
		return ['result'=>true,'rows'=>is_array($response['rows'] ?? null) ? $response['rows'] : [],'error'=>''];
	}

	public function normalizeList($value) {
		return array_values(array_filter(array_unique(array_map('trim',\helper__json_convert($value)))));
	}

	public function queryClass($query, $metrics, $brandTerms) {
		$brand_score = 0;
		$noise_score = 0;
		$query_lower = mb_strtolower((string) $query);
		foreach ($brandTerms as $brand_term) {
			$brand_term = trim(mb_strtolower((string) $brand_term));
			if ($brand_term !== '' && strpos($query_lower,$brand_term) !== false) $brand_score += 40;
		}
		if (($metrics['position'] ?? 99) <= 2) $brand_score += 10;
		if (($metrics['ctr'] ?? 0) >= .6) $brand_score += 20;
		if (($metrics['clicks'] ?? 0) >= 100 && ($metrics['ctr'] ?? 0) >= .8) $noise_score += 20;
		if ($brand_score >= 50 && $noise_score >= 20) return ['label'=>\language__get($this->language,'_seo_query_navigation_noise'),'tone'=>'warning'];
		if ($brand_score >= 50) return ['label'=>\language__get($this->language,'_seo_query_brand'),'tone'=>'good'];
		if (($metrics['impressions'] ?? 0) >= 100 && ($metrics['ctr'] ?? 0) < .01 && ($metrics['position'] ?? 99) <= 10) return ['label'=>\language__get($this->language,'_seo_query_snippet'),'tone'=>'warning'];
		if (($metrics['impressions'] ?? 0) >= 50 && ($metrics['position'] ?? 99) > 10) return ['label'=>\language__get($this->language,'_seo_query_ranking'),'tone'=>'warning'];
		return ['label'=>\language__get($this->language,'_seo_query_generic'),'tone'=>'neutral'];
	}

	public function contentMatches($query, $limit = 5) {
		global $tables;
		$matches = [];
		if (!isset($tables['pages'])) return $matches;
		$fetch = \mysqlQuery("SELECT `id`,`pid`,`tid`,`lid`,`djs`,`ojs` FROM ".$tables['pages']." WHERE `cid` = 0");
		while ($data = \mysqlFetchAssoc($fetch)) {
			$text = '';
			foreach (['djs','ojs'] as $field) $text .= $this->textExtract(\helper__json_convert($data[$field] ?? ''));
			$url = (isset($_SERVER['Router']) && is_object($_SERVER['Router'])) ? $_SERVER['Router']->getParsedUrl($data['pid'].'-'.$data['tid'],$data['lid']) : '';
			$score = $this->fitScore($query,$text,$url);
			if ($score <= 0) continue;
			$matches[] = [
				'url'=>$url,
				'lid'=>$data['lid'],
				'score'=>$score,
				'page'=>$data['pid'].'-'.$data['tid'].'-'.$data['lid']
			];
		}
		usort($matches,function($a,$b) {
			return $b['score'] <=> $a['score'];
		});
		return array_slice($matches,0,$limit);
	}

	protected function fitScore($query, $text, $url) {
		$query = trim(mb_strtolower((string) $query));
		$text = trim(mb_strtolower(strip_tags((string) $text)));
		$url = trim(mb_strtolower((string) $url));
		if ($query == '' || $text == '') return 0;
		$score = min(50,substr_count($text,$query) * 25);
		foreach (array_filter(explode(' ',$query)) as $token) {
			if (mb_strlen($token) < 4) continue;
			$score += min(20,substr_count($text,$token) * 4);
			if ($url !== '' && strpos($url,$token) !== false) $score += 5;
		}
		if ($url !== '' && strpos($url,str_replace(' ','-',$query)) !== false) $score += 15;
		return min(100,$score);
	}

	protected function textExtract($value) {
		if (!is_array($value)) return ' '.$value;
		$text = '';
		foreach ($value as $child) $text .= $this->textExtract($child);
		return $text;
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
