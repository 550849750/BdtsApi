<?php

return [

    [
        'name' => '批量百度主动推送',// 批量权限是插件的链接名称
        'icon' => 'fa fa-paw', // 图标
        'url' => 'javascript:dr_ajax_submit(\''.dr_url('bdts/home/add', ['mid' => '{mid}']).'\', \'myform\');',  // 这个是单击的执行的js动作，建议使用自定义js函数
        'uri' => 'bdts/home/add',
    ],

];