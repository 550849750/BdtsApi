<?php namespace Phpcmf\Model\Bdts;

class Bdts extends \Phpcmf\Model
{

    private $zzurl = [
        'add' => 'http://data.zz.baidu.com/urls',
        'edit' => 'http://data.zz.baidu.com/update',
        'del' => 'http://data.zz.baidu.com/del',
    ];


    private $zzconfig;

    // 配置信息
    public function getConfig()
    {

        if ($this->zzconfig) {
            return $this->zzconfig;
        }

        if (is_file(WRITEPATH . 'config/bdts.php')) {
            $this->zzconfig = require WRITEPATH . 'config/bdts.php';
            return $this->zzconfig;
        }

        return [];
    }

    // 配置信息
    public function setConfig($data)
    {

        \Phpcmf\Service::L('Config')->file(WRITEPATH . 'config/bdts.php', '站长配置文件', 32)->to_require($data);

    }


    /**
     * 进行百度推送
     * @param string $mid 模块名
     * @param string $url 相对路径
     * @param string $action 是否首次推送
     * @param bool $mip 是否mip域名
     */
    public function module_bdts($mid, $url, $action = 'add', $mip = false)
    {

        $config = $this->getConfig();
        if (!$config) {
            log_message('error', '百度推送配置为空，不能推送');
            return;
        } elseif (!in_array($mid, $config['use'])) {
            log_message('error', '模块【' . $mid . '】百度推送配置没有开启，不能推送');
            return;
        }

        // pc域名
        $purl = dr_url_prefix($url, $mid, SITE_ID, 0);
        $uri = parse_url($purl);
        $site = $uri['host'];
        if (!$site) {
            log_message('error', '百度推送没有获取到内容url（' . $purl . '）的host值，不能推送');
            return;
        }

        // 获取移动端域名
        $murl = dr_url_prefix('test.html', $mid, SITE_ID, 1); // test.html防止本身是绝对路径
        $uri = parse_url($murl);
        $m_site = $uri['host'];
        if ($m_site && $m_site != $site) {
            $murl = str_replace($site, $m_site, $purl); // 替换移动端url
        } else {
            $m_site = '';
        }


        // 百度主动推送部分
        if ($config['bdts']) {
            $token = '';
            $m_token = '';
            foreach ($config['bdts'] as $t) {
                if ($t['site'] == $site && !$token) {
                    $token = $t['token'];
                }
                if ($m_site && $t['site'] == $m_site && !$m_token) {
                    $m_token = $t['token'];
                }
            }
            if ($token) {
                // pc域名
                if (strpos($purl, SITE_URL) === false) {
                    @file_put_contents(WRITEPATH . 'bdts_log.php', date('Y-m-d H:i:s') . ' PC端[' . $purl . '] - 域名规范或者域名不是PC域名（' . SITE_URL . '） - 未推送 ' . PHP_EOL, FILE_APPEND);
                } else {
                    $api = $this->zzurl[$action] . '?site=' . $site . '&token=' . $token;
                    //mip域名推送地址
                    $mip && $api .= '&type=mip';
                    $urls = [$purl];
                    $ch = curl_init();
                    $options = array(
                        CURLOPT_URL => $api,
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POSTFIELDS => implode("\n", $urls),
                        CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
                    );
                    curl_setopt_array($ch, $options);
                    $rt = json_decode(curl_exec($ch), true);
                    if ($rt['error']) {
                        // 错误日志
                        @file_put_contents(WRITEPATH . 'bdts_log.php', date('Y-m-d H:i:s') . ' PC端[' . $purl . '] - 失败 - ' . $rt['message'] . PHP_EOL, FILE_APPEND);
                    } else {
                        // 推送成功
                        @file_put_contents(WRITEPATH . 'bdts_log.php', date('Y-m-d H:i:s') . ' PC端[' . $purl . '] - 成功' . PHP_EOL, FILE_APPEND);
                    }
                }

            }
            if ($m_token && $m_site) {
                // 移动端
                if (strpos($murl, SITE_MURL) === false) {
                    @file_put_contents(WRITEPATH . 'bdts_log.php', date('Y-m-d H:i:s') . ' 移动端[' . $murl . '] - 域名规范或者域名不是移动端域名（' . SITE_MURL . '） - 未推送 ' . PHP_EOL, FILE_APPEND);
                } else {
                    $api = $this->zzurl[$action] . '?site=' . $m_site . '&token=' . $m_token;
                    //mip域名推送地址
                    $mip && $api .= '&type=mip';
                    $urls = [$murl];
                    $ch = curl_init();
                    $options = array(
                        CURLOPT_URL => $api,
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POSTFIELDS => implode("\n", $urls),
                        CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
                    );
                    curl_setopt_array($ch, $options);
                    $rt = json_decode(curl_exec($ch), true);
                    if ($rt['error']) {
                        // 错误日志
                        @file_put_contents(WRITEPATH . 'bdts_log.php', date('Y-m-d H:i:s') . ' 移动端[' . $murl . '] - 失败 - ' . $rt['message'] . PHP_EOL, FILE_APPEND);
                    } else {
                        // 推送成功
                        @file_put_contents(WRITEPATH . 'bdts_log.php', date('Y-m-d H:i:s') . ' 移动端[' . $murl . '] - 成功' . PHP_EOL, FILE_APPEND);
                    }
                }
            }

        }

    }


}