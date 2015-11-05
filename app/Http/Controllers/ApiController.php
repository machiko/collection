<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Request;
use Yangqi\Htmldom\Htmldom;
use App\NewsUrl;
use App\NewsContent;
use App\NewsSimilar;

class ApiController extends Controller
{
    /**
     * [getCurl curl api]
     * @param  Request $request [網址]
     * @return [type]           [內容]
     */
    public function getCurl(Request $request) {
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
        $title_arr = [];
        // $org_arr = [];
        $ids_arr = [];

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
            // debug($clear_url);
            $clear_url = $this->clearUrl($url);
            $url = $clear_url['url'];
            $reg_str = $clear_url['reg_str'];
            $content_str = $clear_url['dom_str'];
            $title_str = $clear_url['title_str'];
            // $org_str = $clear_url['org_str'];
            $domain = $clear_url['domain'];
            $news_url = new NewsUrl;

            // 尋找是否已有資料，沒有才執行 parser
            if ($news_url->where('url', $url)->first() == null)
            {
                //----- curl start -----//
                $ch = curl_init();

                $options = array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    // CURLOPT_USERAGENT => 'Google Bot',
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4',
                    CURLOPT_HEADER => false,
                    CURLOPT_SSL_VERIFYPEER => FALSE,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_REFERER => $url
                );

                curl_setopt_array($ch, $options);
                $curl_output = curl_exec($ch);

                curl_close($ch);
                //----- curl end -----//
                // $html = new Htmldom($url);
                $html = new Htmldom($curl_output);
                // 如果沒有內容的話，略過。繼續執行下一個
                if (empty($html->nodes)) {
                    continue;
                }
                $title = '';
                if ($domain != 'default') {
                    $origin_output = is_null($html->find($content_str, 0))?'': $html->find($content_str, 0)->innertext;
                    $title = empty($html->find($title_str, 0))?'': trim($html->find($title_str, 0)->plaintext);
                    // $org = $html->find($org_str, 0)->innertext;
                    dd($title, $title_str, $origin_output, $content_str);
                }
                if ($reg_str != '')
                {
                    // debug($reg_str);
                    preg_match($reg_str, $origin_output, $metaContentsMatches);
                }

                $content_trim = '';
                if ($domain != 'default' && $origin_output != '' && !empty($metaContentsMatches))
                {
                    $content_clean_tags = preg_replace('/<[^>]*>/', '', $metaContentsMatches);
                    preg_match('/(.*。)/s', $content_clean_tags[0], $content);
                    $content_trim = preg_replace('/&nbsp;/', '', $content[1]);
                }
            }
            else
            {

            }

            // insert to database
            // $news_url = new NewsUrl;

            // 尋找是否已有資料，沒有才存入資料庫
            if ($news_url->where('url', $url)->first() == null)
            {
                $news_url->url = $url;
                $news_url->save();
                $news_content = new NewsContent;
                $news_content->url_id = $news_url->id;
                $news_content->article = $title;
                // $news_content->author = '';
                $news_content->content = $content_trim;
                $news_content->save();
                $url_id = $news_content->id;
            }
            else
            {
                $url_id = $news_url->where('url', $url)->pluck('id');
                $content_trim = $news_url->find($url_id)->newscontent->content;
                $title = trim($news_url->find($url_id)->newscontent->article);
            }

            array_push($domain_arr, $domain);
            array_push($title_arr, $title);
            // array_push($org_arr, $org);
            array_push($ids_arr, $url_id);
            array_push($content_trim_arr, $content_trim);
            array_push($url_clear_arr, $url);

        }


        /*preg_match("/<meta.*?name=\"description\".*?content=\"(.*?)\".*?>|<meta.*?content=\"(.*?)\".*?name=\"description\".*?>/i", $origin_output, $metaContentsMatches);*/
        /*preg_match("/<meta.*?name=\"description\".*?content=\"(.*?)\".*?>|<meta.*?content=\"(.*?)\".*?name=\"description\".*?>/i", $origin_output, $metaContentsMatches);*/
        // $tables = array();
        // return string($origin_output);
        // echo html_entity_decode($origin_output);
        return response()->json(['domain' => $domain_arr, 'content' => $content_trim_arr, 'url' => $url_clear_arr,
            'id' => $ids_arr, 'title' => $title_arr])->setCallback($request->input('callback'));
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
        $content_str = '';
        $title_str = '';
        $org_str = '';

        preg_match('/http[s]?:\/\/(.*\.)?(.*)\.(com|net)/', $url, $filter_domain);
        switch ($filter_domain[2])
        {
            case 'udn':
                $domain = 'udn';
                $title_str = 'h2[id=story_art_title]';
                $content_str = 'div[id=story_body_content]';
                // $reg_str = '/<div id=\"story\".*class=\"area\">(.+?)<\/div>/s';
                $reg_str = '/<p>(.+)。<\/p>/s';

                // 處理 url encode
                $find = array('/%3[a,A]/', '/%2[f,F]/');
                $replace = array(':', '/');
                $url =  preg_replace($find, $replace, urlencode($url));
                break;
            case 'yahoo':
                // for yahoo
                $domain = 'yahoo';
                $title_str = 'h1[class=headline]';
                $content_str = 'div[id=mediaarticlebody]';
                $reg_str = '/<!-- google_ad_section_start -->(.+?)<!-- google_ad_section_end -->/s';

                $find = array('/news\//', '/mobi/', '/\/home/');
                $replace = array('', 'news', '');
                // $org_str = 'span[class=provider]';
                $url =  preg_replace($find, $replace, $url);
                break;
            case 'tvbs':
                $domain = 'tvbs';
                break;
            case 'yam':
                $domain = 'yam';
                $title_str = 'li[class=title]';
                $content_str = 'div[id=news_content]';
                $reg_str = '/<p\s.+>(.+)。<\/p>/s';
                $url = preg_replace('/_pic|\?pic=\d/', '', $url);
                break;
            case 'uho':
                $domain = 'uho';
                break;
            case 'people':
                $domain = 'people';
                break;
            case 'pchome':
                $domain = 'pchome';
                $content_str = 'div[class=article_text]';
                $reg_str = '/<div calss=\"article_text\">(.+?)<\/div>/s';
                break;
            case 'nownews':
                $domain = 'nownews';
                $content_str = 'div[class=story_content]';
                // $reg_str = '/<div class=\"story_content\".*>(.+?)<div class=\"page_nav\"/s';
                $reg_str = '/<p>(.+)。<\/p>/s';
                break;
            case 'secretchina':
                $domain = 'secretchina';
                $content_str = 'div[class=articlebody]';
                $reg_str = '/<p>(.+)。<\/p>/s';
                break;
            case 'appledaily':
                $domain = 'appledaily';
                $content_str = 'div[class=articulum]';
                $reg_str = '/<p\s.*>(.+)<\/p>/s';
                break;
            case 'bayvoice':
                $domain = 'bayvoice';
                $content_str = 'article[id=content-body]';
                $reg_str = '/<p\s.*>(.+)<\/p>/s';
                $url = preg_replace('/gb/s', 'b5', $url);
                break;
            case 'msn':
                $domain = 'msn';
                $content_str = 'section[class=articlebody]';
                $reg_str = '/<p\s.*>(.+)<\/p>/s';
                // 處理 url encode
                $find = array('/%3[a,A]/', '/%2[f,F]/');
                $replace = array(':', '/');
                $url =  preg_replace($find, $replace, urlencode($url));
                break;
            case 'top1health':
                $domain = 'top1health';
                $content_str = 'div[class=content]';
                $reg_str = '/<p>(.+)。<\/p>/s';
                break;
            case 'times-bignews':
                $domain = 'times-bignews';
                $content_str = 'div[class=news_content]';
                $reg_str = '/<p>(.+)。<\/p>/s';
                break;
            case 'cna':
                $domain = 'cna';
                $title_str = 'div[class=news_title]';
                $content_str = 'section[itemprop=articleBody]';
                $reg_str = '/<p>(.+)<\/p>/s';
                break;
            case 'sina':
                $domain = 'sina';
                $title_str = 'div[id=articles] h1';
                $content_str = 'div[class=pcont]';
                $reg_str = '/<p>(.+)<\/p>/s';
                break;
            case 'new0':
                $domain = 'news0';
                $title_str = 'h1[itemprop=headline]';
                $content_str = 'span[itemprop=articleBody]';
                $reg_str = '/<p>(.+)<\/p>/s';
                // 處理 url encode
                $find = array('/%3[a,A]/', '/%2[f,F]/');
                $replace = array(':', '/');
                $url =  preg_replace($find, $replace, urlencode($url));
                break;
            default:
                $domain = 'default';
                break;
        }

        // $url_utf8_encode_str = '/([\\x{4e00}-\\x{9fa5}]+)/u';
        return array('url' => $url, 'reg_str' => $reg_str, 'domain' => $domain,
            'dom_str' => $content_str, 'title_str' => $title_str);
    }

    /**
     * [postUrl 儲存 url]
     * @return [type] [description]
     */
    public function postUrl(Request $request) {
        $news_url = new NewsUrl;
        $news_content = new NewsContent;
        $url = $request->input('url');
        $content = $request->input('content');

        $news_url->url = $url;
        $news_url->save();

        $url_id = $news_url->id;
        $news_content->url_id = $url_id;
        $news_content->content = $content;
        $news_content->save();
    }

    public function postNewsSimilars(Request $request) {
        $url_ids = $request->input('url_ids');
        $value = $request->input('value');
        $news_similars = new NewsSimilar;

        // 判斷是否已存在資料，未存在時才儲存
        if (count($news_similars->where('url_ids', $url_ids)->get()) == 0) {
            $news_similars->url_ids = $url_ids;
            $news_similars->value = $value;
            $news_similars->save();
        }
    }
}
