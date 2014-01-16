<?php
$dbConfig = include('db_config.php');

$appConfig =  array(
    // 调试页
    'SHOW_PAGE_TRACE' =>true,

    // 默认模块和Action
    'MODULE_ALLOW_LIST' => array('Home'),
    'DEFAULT_MODULE' => 'Home',

    // 开启布局
    'LAYOUT_ON' => true,
    'LAYOUT_NAME' => 'Public/layout',

    // error和success重定向
    // 'TMPL_ACTION_ERROR' => 'Public:error',
    // 'TMPL_ACTION_SUCCESS' => 'Public:success',
);

return array_merge($appConfig, $dbConfig);