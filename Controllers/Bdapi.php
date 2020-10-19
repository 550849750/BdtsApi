<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/10/16
 * Time: 16:55
 */

namespace Phpcmf\Controllers;

use Phpcmf\Library\Input;
use think\facade\Db;

class Bdapi extends \Phpcmf\App
{
    /**
     * 请求对象
     * @var Input
     */
    protected $input;
    protected $module_name;
    protected $main_table;//内容主表
    protected $tag_table;//tag表
    protected $quota;
    protected $mip;

    public function __construct(... $params)
    {
        parent::__construct($params);
        $this->input = new Input();
        $this->module_name = $this->input->request('module');
        !$this->module_name && exit('模块名不能为空');
        if ($this->input->request('auth') != 'a5f#d3#d5g@d5g*1a2&') {
            exit('全局变量错误');
        }
        //每日配额量
        $this->quota = $this->input->request('quota');
        !$this->quota && exit('缺少配额参数');
        //初始化模块
        $this->_module_init($this->module_name);
        $this->main_table = \Phpcmf\Service::M()->dbprefix(SITE_ID . '_' . $this->module_name);
        $this->tag_table = \Phpcmf\Service::M()->dbprefix(SITE_ID . '_tag');
        $this->mip = $this->input->request('mip');//是否mip域名
        //检查表字段是否存在
        if (!\Phpcmf\Service::M()->db->fieldExists('push_num', $this->main_table)) {
            \Phpcmf\Service::M()->query('ALTER TABLE `' . $this->main_table . '` ADD `push_num` INT(10) DEFAULT 0 COMMENT \'百度api推送次数\'');
        }
        if (!\Phpcmf\Service::M()->db->fieldExists('push_num', $this->tag_table)) {
            \Phpcmf\Service::M()->query('ALTER TABLE `' . $this->tag_table . '` ADD `push_num` INT(10) DEFAULT 0 COMMENT \'百度api推送次数\'');
        }
    }

    /**
     * 外部推送接口
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function push()
    {
        //本次推送记录数量
        $num = floor(intval($this->quota) / 24);
        $items = $this->get_all_url($num);
        foreach ($items as $item) {
            list($url, $old) = explode('----', $item, 2);
            sleep(random_int(0, 3));//推送延时
            $this->push_url($url, $old, $this->mip);
        }
        exit('推送成功,本次推送：' . $num . '条记录。');
    }


    /**
     * 获取所有待推送的url
     * @param int $num
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function get_all_url(int $num)
    {
        //需要先聚合查询 取出最小的push_num值
        $main_push_num = Db::table($this->main_table)->min('push_num', true);
        $tag_push_num = Db::table($this->tag_table)->min('push_num', true);
        //取得结果集
        $rows = $this->get_rows($num, $main_push_num, $tag_push_num);
        $urls = [];
        foreach ($rows as $row) {
            //tag表
            if (array_key_exists('code', $row) && array_key_exists('pcode', $row)) {
                $urls[] = '/title/' . $row['code'] . '.html' . '----' . $row['push_num'];
                //自增字段值
                Db::table($this->tag_table)->where('id', $row['id'])->inc('push_num')->update();
            } else {
                $urls[] = $row['url'] . '----' . $row['push_num'];
                Db::table($this->main_table)->where('id', $row['id'])->inc('push_num')->update();
            }
        }
        return array_unique($urls);
    }

    /**
     *获取结果集
     * @param int $num 结果集数量
     * @param int $i_main push_num起始值
     * @param int $i_tag push_num起始值
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function get_rows(int $num, $i_main = 0, $i_tag = 0)
    {
        $fist = $this->main_table;
        $fist_i = $i_main;
        $secondary = $this->tag_table;
        $secondary_i = $i_tag;
        //如果tag表push_num较小则交换查询表顺序
        if ($i_main >= $i_tag) {
            list($fist, $secondary) = [$secondary, $fist];
            list($fist_i, $secondary_i) = [$secondary_i, $fist_i];
        }
        $rows = [];
        $items = Db::table($fist)->where('push_num', $fist_i)->limit($num)->select();
        if (!$items->isEmpty()) {
            $rows += $items->toArray();
        }
        $count = $items->count();
        if ($count < $num) {
            //主表url数量不足  查询tag表
            $num = $num - $count;
            $tag_items = Db::table($secondary)->where('push_num', $secondary_i)->limit($num)->select();
            if (!$tag_items->isEmpty()) {
                $rows += $tag_items->toArray();
            }
            $tag_count = $tag_items->count();
            if ($tag_count < $num) {
                $num = $num - $tag_count;
            } else {
                $num = 0;//数量足够
            }
        } else {
            $num = 0;//数量足够
        }
        //url数量不足 递归查询
        if ($num > 0) {
            $i_main++;
            $i_tag++;
            $rows1 = $this->get_rows($num, $i_main, $i_tag);
            $rows += $rows1;
        }
        return $rows;
    }

    /**
     * 通过api进行推送
     * @param $url  相对路径url
     * @param bool $old 是否已推送过
     * @param bool $mip 是否mip域名
     */
    protected function push_url($url, $old = false, $mip = false)
    {
        \Phpcmf\Service::M('bdts', 'bdts')->module_bdts(
            $this->module_name,
            $url,
            $old ? 'edit' : 'add',
            $mip ? true : false
        );
    }


}