<?php

$version = '';
if (file_exists($file = ROOT_PATH.'/template/wap-v3/src/manifest.json')){
    $content = file_get_contents($file);
    $version = json_decode($content, true)['versionName'];
}

return [
    'app_version' => $version,
    'app_wgturl' => __URL__. '/public/app.wgt',
];

