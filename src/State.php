<?php

namespace ficmsSeoSea;

class State {
	public const PLUGIN = 'fiCMS-seo-sea';

	public static function file($name) {
		return PLUGINPATH.'/'.self::PLUGIN.'/data/'.preg_replace('/[^a-z0-9_.-]/i','',trim((string) $name)).'.json';
	}

	public static function read($name) {
		$file = self::file($name);
		if (!is_file($file)) return [];
		$data = json_decode(file_get_contents($file),true);
		return is_array($data) ? $data : [];
	}

	public static function write($name, $data) {
		return \helper__files_write(self::file($name),is_array($data) ? $data : [],true,true);
	}

	public static function sourceKey($provider, $pageId, $formId = '') {
		return trim((string) $provider).':'.trim((string) $pageId).':'.trim((string) $formId);
	}

	public static function leadKey($provider, $leadId) {
		return trim((string) $provider).':'.trim((string) $leadId);
	}
}
