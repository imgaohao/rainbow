<?php

use Curl\Curl;

class Weibo
{
    protected $save_path;

    const DOMAIN = 'https://www.weibo.com';

    public function __construct($config)
    {
        if (isset($config['save_path'])) {
            $this->setSavePath($config['save_path']);
        }
    }

    /**
     * @return mixed
     */
    public function getSavePath()
    {
        return $this->save_path;
    }

    /**
     * @param mixed $save_path
     * @return Weibo
     */
    public function setSavePath($save_path)
    {
        $path = $save_path . '/weibo/';
        file_exists($path) || mkdir($path);
        $this->save_path = $path;
        return $this;
    }

    /**
     * @param $url
     * @throws Exception
     */
    public function get($url)
    {
        $content = $this->catch_html($url);
        preg_match('/[^"]+\bphotos\b[^"]+/', $content, $matches);
        $photo_url = str_replace('\\', '', self::DOMAIN . $matches[0]);
        $content = $this->catch_html($photo_url);

        preg_match('/<title>(.+)的微博.+<\/title>/', $content, $matches);
        $name = iconv('utf-8', 'gbk', $matches[1]);
        $dir = $this->getSavePath() . $name . '/';
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        preg_match_all('/(?:https:)?\/\/(?:wx3|wxt)\.sinaimg\.cn[^"]+/', $content, $matches);
        foreach ($matches[0] as $match) {
            if (strpos($match, 'https') !== 0) {
                $match = "https:{$match}";
            }
            preg_match('/[^\/]+\.jpg/', $match, $m);
            file_put_contents($dir . $m[0], file_get_contents($match));
        }

        preg_match_all('/http:\/\/video\.weibo\.com[^"]+/', $content, $matches);
        $videos = array_unique($matches[0]);
        foreach ($videos as $video) {
            $content = $this->catch_html($video);
            preg_match('/video-sources="([^"]+)"/', $content, $matches);
            $list = preg_split('/&\d+=/', str_replace('fluency=', '', urldecode($matches[1])));
            $video_url = end($list);
            preg_match('/[^\/]+\.mp4/', $video_url, $m);
            @file_put_contents($dir . $m[0], @file_get_contents($video_url));
        }
    }

    /**
     *
     * 由于weibo防爬虫需要获得访客身份cookie后在访问,大致流程如下。
     * 获得tid->请求一个访客cookie->请求一个跨域cookie->抓去数据。
     * @param string $url
     * @author maskxu
     * @throws Exception
     */
    public function catch_html($url)
    {
        //获得tid
        static $xcookie;
        if (!$xcookie) {
            $xcookie_path = $this->getSavePath() . 'xcookie';
            if (!file_exists($xcookie_path)) {
                $tid = $this->_get_tid();
                $cookie = $this->_get_cookie($tid, $sub, $subp);
                if (empty($sub)) //sub 可能会获取失败,原因未知,可能是因为频繁访问.
                {
                    throw new Exception("Get Sub error", 1);
                }
                $xcookie = $this->_get_crosscookie($sub, $subp, $cookie);
                file_put_contents($xcookie_path, $xcookie);
            } else {
                $xcookie = file_get_contents($xcookie_path);
            }
        }
        $content = $this->_curl_data($url, "GET", "", true, $xcookie, false);
        file_put_contents($this->getSavePath() . 'content.html', $content);
        return str_replace('\\', '', $content);
    }

    //获得tid
    private function _get_tid()
    {
        $postdata['cb'] = "gen_callback";
        $postdata['fp'] = '{"os":"1","browser":"Chrome61,0,3163,100","fonts":"undefined","screenInfo":"1920*1080*24","plugins":"Portable Document Format::internal-pdf-viewer::Chrome PDF Plugin|::mhjfbmdgcfjbbpaeojofohoefgiehjai::Chrome PDF Viewer|::internal-nacl-plugin::Native Client|Enables Widevine licenses for playback of HTML audio/video content. (version: 1.4.8.1008)::widevinecdmadapter.dll::Widevine Content Decryption Module"}';
        $url = "https://passport.weibo.com/visitor/genvisitor";
        $content = $this->_curl_data($url, "POST", $postdata);
        preg_match('/"tid":"(.*)",/i', $content, $matches);
        $tid = stripcslashes($matches[1]);
        return $tid;
    }

    //第一次获得cookie. sub,subp,cookie是第二次获得跨域cookie必须的.
    private function _curl_data($url, $method = "GET", $postdata = "", $ssl = true, &$cookie = "", $show_header = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36');//模拟UA
        if ($show_header)
            curl_setopt($ch, CURLOPT_HEADER, 1);  //show header 为了拿到cookie
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);//使用post提交数据
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);//设置 post提交的数据
        }
        if ($ssl) {
            // curl_setopt($ch, CURLOPT_SSLVERSION, 3); //设定SSL版本
            // 对认证证书来源的检查，0表示阻止对证书的合法性的检查。
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        if ($cookie != "") {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//关闭直接输出
        $data = curl_exec($ch);
        if ($show_header) {
            preg_match_all('|Set-Cookie: (.*);|U', $data, $results);
            if (count($results) > 1)
                $cookie = implode(';', $results[1]);
        }
        curl_close($ch);
        return $data;
    }

    //第二次获得跨域xcookie
    private function _get_cookie($tid, &$sub, &$subp)
    {
        $url = "https://passport.weibo.com/visitor/visitor?";
        $url .= 'a=incarnate&t=' . urlencode($tid) . '&w=1&c=095&gc=&cb=crossdomain&from=weibo&_rand=0.' . rand();
        $cookie = "";
        $content = $this->_curl_data($url, "GET", "", true, $cookie);
        preg_match('/"sub":"(.*)",/i', $content, $matches);
        $sub = $matches[1];
        preg_match('/"subp":"(.*)"}/i', $content, $matches);
        $subp = $matches[1];
        return $cookie;
    }

    private function _get_crosscookie($sub, $subp, $cookie)
    {
        $url = "https://passport.weibo.com/visitor/visitor?";
        $url .= 'a=crossdomain&cb=return_back&s=' . $sub . '&sp=' . $subp . '&w=2&from=weibo&rand=0.' . rand();
        $content = $this->_curl_data($url, "GET", "", true, $cookie);
        return $cookie;
    }
}