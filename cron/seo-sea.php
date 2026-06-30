<?php

if (!$site['onsite']) return;

$seo = [
	'inbox'=>new \ficmsSeoSea\LeadInbox(),
	'result'=>[]
];

$seo['result'] = $seo['inbox']->processMeta();
\ficmsSeoSea\State::write('last-inbox-result',[
	'ran_at'=>intval($_SERVER['now'] ?? time()),
	'trigger'=>'cron',
	'result'=>$seo['result']
]);

unset($seo);
