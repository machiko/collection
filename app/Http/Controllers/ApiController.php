<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Request;
use Yangqi\Htmldom\Htmldom;

class ApiController extends Controller
{
    /**
     * [getCurl curl api]
     * @param  Request $request [網址]
     * @return [type]           [內容]
     */
    public function getCurl(Request $request)
    {
        // $html = new Htmldom('http://www.nownews.com/n/2015/09/22/1821661');
        // debug($html);

        // Find all images
        // foreach($html->find('img') as $element)
        //        echo $element->src . '<br>';

        // Find all links
        // foreach($html->find('a') as $element)
        // echo $element->href . '<br>';
        // $eles = $html->find('div[class=story_content]');

        // foreach($eles as $e) {
        //     debug($e->innertext);
        // }

        $url_arr = [];
        $url_clear_arr = [];
        $url = $request->input('url');
        $content_trim_arr = [];
        $domain_arr = [];

        // return response()->json(['url' => $url])
        //          ->setCallback($request->input('callback'));

        if (!isset($url))
            return 0;
        // 如果丟進來不是 array, 組成 array
        if (!is_array($url))
        {
            array_push($url_arr, $url);
        }
        else
        {
            $url_arr = $url;
        }

        // 分析 array's url
        foreach ($url_arr as $index => $url)
        {
            // $url = "http://www.taiwanlottery.com.tw/lotto/DailyCash/history.aspx";
            $clear_url = $this->clearUrl($url);
            debug($clear_url);
            $url = $clear_url['url'];
            $reg_str = $clear_url['reg_str'];
            $dom_str = $clear_url['dom_str'];
            $domain = $clear_url['domain'];

            //----- curl start -----//
            // $ch = curl_init();

            // $options = array(
            //     CURLOPT_URL => $url,
            //     CURLOPT_RETURNTRANSFER => true,
            //     // CURLOPT_USERAGENT => 'Google Bot',
            //     CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4',
            //     CURLOPT_HEADER => false,
            //     CURLOPT_SSL_VERIFYPEER => FALSE,
            //     CURLOPT_FOLLOWLOCATION => true,
            //     CURLOPT_REFERER => $url
            // );

            // curl_setopt_array($ch, $options);
            // $origin_output = curl_exec($ch);

            // curl_close($ch);
            // echo($origin_output);
            //----- curl end -----//
            $html = new Htmldom($url);
            if ($domain != 'default') {
                $origin_output = is_null($html->find($dom_str, 0))?'': $html->find($dom_str, 0)->innertext;
            }

            // foreach($eles as $e) {
            //     debug($e->innertext);
            // }

            // Find the table
            /*preg_match_all("/<table.*?>.*?<\/[\s]*table>/s", $origin_output, $tablesMatches);*/
            /*preg_match("/<div.*?id=\"mediaarticlebody\".*?>(.*?)<\/div>/s", $origin_output, $metaContentsMatches);*/
            // 過濾 url domain
            // debug($url);
            if ($reg_str != '')
            {
                // debug($reg_str);
                // debug($origin_output);
                preg_match($reg_str, $origin_output, $metaContentsMatches);
                // debug($metaContentsMatches);
            }

            $content_trim = '';
            if ($domain != 'default' && $origin_output != '' && !empty($metaContentsMatches))
            {
                $content_clean_tags = preg_replace('/<[^>]*>/', '', $metaContentsMatches);
                preg_match('/(.*。)/s', $content_clean_tags[0], $content);
                $content_trim = preg_replace('/&nbsp;/', '', $content[1]);
            }

            array_push($domain_arr, $domain);
            array_push($content_trim_arr, $content_trim);
            array_push($url_clear_arr, $url);

        }


        /*preg_match("/<meta.*?name=\"description\".*?content=\"(.*?)\".*?>|<meta.*?content=\"(.*?)\".*?name=\"description\".*?>/i", $origin_output, $metaContentsMatches);*/
        /*preg_match("/<meta.*?name=\"description\".*?content=\"(.*?)\".*?>|<meta.*?content=\"(.*?)\".*?name=\"description\".*?>/i", $origin_output, $metaContentsMatches);*/
        // $tables = array();
        // return string($origin_output);
        // echo html_entity_decode($origin_output);
        return response()->json(['domain' => $domain_arr, 'content' => $content_trim_arr, 'url' => $url_clear_arr])
                ->setCallback($request->input('callback'));
        // return response()->json(['url' => $url])
        //          ->setCallback($request->input('callback'));
        // return response()->json(['content' => $content_clean_tags[0]]);
        // return response()->json(['content' => $origin_output]);
    }

    /**
     * [clearUrl 過濾 url]
     * @param  [type] $url [description]
     * @return [type]      [過濾後的 url]
     */
    public function clearUrl($url) {
        $reg_str = '';
        $dom_str = '';

        preg_match('/http[s]?:\/\/.*\.(.*)\.(com|net)/', $url, $filter_domain);
        switch ($filter_domain[1])
        {
            case 'udn':
                $domain = 'udn';
                $reg_str = '/<div id=\"story\".*class=\"area\">(.+?)<\/div>/s';
                // 處理 url encode
                $find = array('/%3[a,A]/', '/%2[f,F]/');
                $replace = array(':', '/');
                $url =  preg_replace($find, $replace, urlencode($url));
                $dom_str = 'div[id=story]';
                break;
            case 'yahoo':
                // for yahoo
                $domain = 'yahoo';
                // dd($url);
                $find = array('/news\//', '/mobi/');
                $replace = array('', 'news');
                // preg_match('/mobi/', $url, $mobi);
                // if (count($mobi) == 1)
                // {
                //     // $url =  preg_replace("/.mobi/", "", $url);
                //     $url =  preg_replace($find, $replace, $url);
                // }
                $dom_str = 'div[id=mediaarticlebody]';
                $url =  preg_replace($find, $replace, $url);
                debug($url);
                $reg_str = '/<!-- google_ad_section_start -->(.+?)<!-- google_ad_section_end -->/s';
                break;
            case 'tvbs':
                $domain = 'tvbs';
                break;
            case 'yam':
                $domain = 'yam';
                $url = preg_replace('/_pic|\?pic=\d/', '', $url);
                // $reg_str = '/<div id=\"news_content\">(.+?)<\/div>/s';
                $reg_str = '/<p\s.+>(.+)。<\/p>/s';
                $dom_str = 'div[id=news_content]';
                break;
            case 'uho':
                $domain = 'uho';
                break;
            case 'people':
                $domain = 'people';
                break;
            case 'pchome':
                $domain = 'pchome';
                $reg_str = '/<div calss=\"article_text\">(.+?)<\/div>/s';
                $dom_str = 'div[class=article_text]';
                break;
            case 'nownews':
                $domain = 'nownews';
                // $reg_str = '/<div class=\"story_content\".*>(.+?)<div class=\"page_nav\"/s';
                $reg_str = '/<p>(.+)。<\/p>/s';
                $dom_str = 'div[class=story_content]';
                break;
            case 'secretchina':
                $domain = 'secretchina';
                $reg_str = '/<p>(.+)。<\/p>/s';
                $dom_str = 'div[class=articlebody]';
                break;
            case 'appledaily':
                $domain = 'appledaily';
                $reg_str = '/<p\s.*>(.+)<\/p>/s';
                $dom_str = 'div[class=articulum]';
                break;
            case 'bayvoice':
                $domain = 'bayvoice';
                $url = preg_replace('/gb/s', 'b5', $url);
                $reg_str = '/<p\s.*>(.+)<\/p>/s';
                $dom_str = 'article[id=content-body]';
                break;
            case 'msn':
                $domain = 'msn';
                $reg_str = '/<p\s.*>(.+)<\/p>/s';
                // 處理 url encode
                $find = array('/%3[a,A]/', '/%2[f,F]/');
                $replace = array(':', '/');
                $url =  preg_replace($find, $replace, urlencode($url));
                $dom_str = 'section[class=articlebody]';
                break;
            default:
                $domain = 'default';
                break;
        }

        // $url_utf8_encode_str = '/([\\x{4e00}-\\x{9fa5}]+)/u';
        return array('url' => $url, 'reg_str' => $reg_str, 'domain' => $domain, 'dom_str' => $dom_str);
    }
}
