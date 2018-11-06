<?php

use Curl\Curl;

class Weibo
{
    public static function get($url)
    {
        (new Vgather)->catch_html($url);

        $genvisitor = "https://passport.weibo.com/visitor/genvisitor";
        $genvisitorData = 'cb=gen_callback&fp=' . urlencode('{"os":"1","browser":"Chrome69,0,3497,81","fonts":"undefined","screenInfo":"1920*1080*24","plugins":"Portable Document Format::internal-pdf-viewer::Chrome PDF Plugin|::mhjfbmdgcfjbbpaeojofohoefgiehjai::Chrome PDF Viewer|::internal-nacl-plugin::Native Client"}');

        $visitor = 'https://passport.weibo.com/visitor/visitor?a=restore&cb=restore_back&from=weibo&_rand=0.2665468362213905';
        $curl = new Curl();
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $curl->setOpt(CURLOPT_PROXY, '127.0.0.1:8888');
        $curl->post($genvisitor, $genvisitorData);

        if ($curl->error) {
            throw new Exception('Error: ' . $curl->errorCode . ': ' . $curl->errorMessage);
        } else {
            $dom = new DOMDocument();
            @$dom->loadHTML($curl->response);
            $xpath = new DOMXPath($dom);
            $nodeList = $xpath->query('//span[@suda-uatrack="key=tblog_profile_new&value=tab_photos"]');
            $result = [];
            /** @var DOMNodeList $nodeList */
            /** @var DOMElement $node */
            foreach ($nodeList as $node) {
                $href = $node->getAttribute('href');
                $dom = \Dom::getDom('http://jos.jd.com' . $href);
                $txt = $dom->getElementById('txt');
                if (strpos($txt->nodeValue, 'vendorCode') !== false) {
                    $result[] = ['http://jos.jd.com' . $href, $txt->nodeValue];
                }
            }
        }

    }
}