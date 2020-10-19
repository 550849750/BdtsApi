<?php

/**
 * 自定义钩子
 *
 */


\Phpcmf\Hooks::on('module_content_after', function($data, $old) {
    // 内容发布或者修改之后
    if ($data[1]['status'] == 9) {
        // 9表示审核通过的
        \Phpcmf\Service::M('bdts', 'bdts')->module_bdts(
            MOD_DIR,
            $data[1]['url'],
            $old ? 'edit' : 'add' //
        );
    }
});
