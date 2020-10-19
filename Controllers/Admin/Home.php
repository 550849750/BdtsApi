<?php namespace Phpcmf\Controllers\Admin;

class Home extends \Phpcmf\Common
{

    public function __construct(...$params)
    {
        parent::__construct(...$params);
        \Phpcmf\Service::V()->assign([
            'menu' => \Phpcmf\Service::M('auth')->_admin_menu(
                [
                    '百度推送设置' => ['bdts/home/index', 'fa fa-internet-explorer'],
                    '推送日志' => ['bdts/home/log_index', 'fa fa-calendar'],
                    'help' => ['672'],
                ]
            ),
        ]);
    }

    // 插件设置
    public function index() {

        if (IS_AJAX_POST) {

            $post = \Phpcmf\Service::L('Input')->post('data', true);
            if ($post['bdts']) {
                $bdts = [];
                foreach ($post['bdts'] as $i => $t) {
                    if (isset($t['site'])) {
                        if (!$t['site']) {
                            $this->_json(0, dr_lang('域名必须填写'));
                        }
                        $bdts[$i]['site'] = $t['site'];
                    } else {
                        if (!$t['token']) {
                            $this->_json(0, dr_lang('token必须填写'));
                        }
                        $bdts[$i-1]['token'] = $t['token'];
                    }
                }
                $post['bdts'] = $bdts;
            }

            \Phpcmf\Service::M('bdts', 'bdts')->setConfig($post);
            \Phpcmf\Service::L('Input')->system_log('设置百度推送工具');
            exit($this->_json(1, dr_lang('操作成功')));
        }

        $page = intval(\Phpcmf\Service::L('Input')->get('page'));

        \Phpcmf\Service::V()->assign([
            'page' => $page,
            'data' => \Phpcmf\Service::M('bdts', 'bdts')->getConfig(),
            'form' => dr_form_hidden(['page' => $page]),
            'module' => \Phpcmf\Service::M('Module')->All(1),
        ]);
        \Phpcmf\Service::V()->display('config.html');
    }

    public function log_index() {

        $data = $list = [];
        $file = WRITEPATH.'bdts_log.php';
        if (is_file(WRITEPATH.'bdts_log.php')) {
            $data = explode(PHP_EOL, str_replace(array(chr(13), chr(10)), PHP_EOL, file_get_contents($file)));
            $data = $data ? array_reverse($data) : [];
            unset($data[0]);
            $page = max(1, (int)\Phpcmf\Service::L('input')->get('page'));
            $limit = ($page - 1) * SYS_ADMIN_PAGESIZE;
            $i = $j = 0;
            foreach ($data as $v) {
                if ($i >= $limit && $j < SYS_ADMIN_PAGESIZE) {
                    $list[] = $v;
                    $j ++;
                }
                $i ++;
            }
        }

        $total = $data ? max(0, count($data) - 1) : 0;

        \Phpcmf\Service::V()->assign(array(
            'list' => $list,
            'total' => $total,
            'mypages'	=> \Phpcmf\Service::L('input')->page(\Phpcmf\Service::L('Router')->url('bdts/home/log_index'), $total, 'admin')
        ));
        \Phpcmf\Service::V()->display('log.html');
    }

    public function del() {

        @unlink(WRITEPATH.'bdts_log.php');

        exit($this->_json(1, dr_lang('操作成功')));
    }

    public function add() {

        $mid = dr_safe_filename($_GET['mid']);
        $ids = \Phpcmf\Service::L('input')->get_post_ids();
        if (!$ids) {
            $this->_json(0, dr_lang('所选数据不存在'));
        } elseif (!$mid) {
            $this->_json(0, dr_lang('模块参数不存在'));
        }

        $this->_module_init($mid);

        $data = \Phpcmf\Service::M()->table(SITE_ID.'_'.$mid)->where_in('id', $ids)->getAll();
        if (!$data) {
            $this->_json(0, dr_lang('所选数据为空'));
        }

        $ct = 0;
        foreach ($data as $t) {
            \Phpcmf\Service::M('bdts', 'bdts')->module_bdts($mid, $t['url'], 'edit');
            $ct++;
        }

        exit($this->_json(1, dr_lang('共批量%s个URL', $ct)));
    }

}
