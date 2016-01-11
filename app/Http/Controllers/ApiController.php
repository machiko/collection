<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Request;
use Yangqi\Htmldom\Htmldom;
use App\NewsUrl;
use App\NewsContent;
use App\NewsSimilar;
use App\UrlDomain;

class ApiController extends Controller
{
    /**
     * [getCurl curl api]
     * @param  Request $request [網址]
     * @return [type]           [內容]
     */
    public function getCurl(Request $request) {
        $url_arr = [];
        $url_clear_arr = [];
        $url = $request->input('url');
        $content_trim_arr = [];
        $domain_arr = [];
        $host_arr = [];
        $title_arr = [];
        // $org_arr = [];
        $ids_arr = [];

        if (!isset($url))
            return 0;
        // 如果丟進來不是 array, 組成 array
        if (!is_array($url)) {
            array_push($url_arr, $url);
        }
        else {
            $url_arr = $url;
        }

        // 分析 array's url
        foreach ($url_arr as $index => $url) {
            $clear_url = $this->clearUrl($url);
            $url = $clear_url['url'];
            $reg_str = $clear_url['reg_str'];
            $content_str = $clear_url['dom_str'];
            $title_str = $clear_url['title_str'];
            $remove_str_arr = $clear_url['remove_str_arr'];
            $curl_useragent = $clear_url['curl_useragent'];
            // $org_str = $clear_url['org_str'];
            $domain = $clear_url['domain'];
            $host = $clear_url['host'];
            $news_url = new NewsUrl;
            $news_url_inst = $news_url->where('url', $url)->first();

            // 尋找是否已有資料，沒有才執行 parser
            if ($news_url_inst == null || $news_url_inst->newscontent->content == '') {
                //----- curl start -----//
                $ch = curl_init();
                // $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                // $curl_useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0';
                // dd($curl_useragent);
                $options = array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT => $curl_useragent,
                    // CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4',
                    // CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0',
                    CURLOPT_HEADER => false,
                    CURLOPT_TIMEOUT_MS => 3500,
                    // CURLOPT_CONNECTTIMEOUT_MS => 100,
                    CURLOPT_SSL_VERIFYPEER => FALSE,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_REFERER => $url
                );
                // dd($curl_useragent);
                curl_setopt_array($ch, $options);
                // $curl_output = curl_exec($ch);
                $curl_output = $this->curl_exec_utf8($ch);

                if (curl_errno($ch))
                {
                    // echo 'error:' . curl_error($ch);
                    // return response()->json(['message' => 'error'])->setCallback($request->input('callback'));
                    continue;
                }

                curl_close($ch);
                //----- curl end -----//
                // $html = new Htmldom($url);
                $html = new Htmldom($curl_output);

                // 如果沒有內容的話，略過。繼續執行下一個
                if (empty($html->nodes)) {
                    continue;
                }

                $title = '';

                if ($host != 'default') {
                    // $origin_output = is_null($html->find($content_str, 0))?'': $html->find($content_str, 0)->innertext;
                    if (is_null($html->find($content_str, 0)) || empty($html->find($content_str, 0))) {
                        $origin_output = '';
                    }
                    else {
                        $origin_output = $html->find($content_str, 0);
                        foreach ($remove_str_arr as $remove_str) {
                            foreach ($origin_output->find($remove_str) as $remove_inst) {
                                $remove_inst->outertext = '';
                            }
                        }
                        $origin_output = $origin_output->innertext;
                    }
                    // $origin_output = is_null($html->find($content_str, 0))?'': $html->find($content_str, 0);
                    $title = empty($html->find($title_str, 0))?'': trim($html->find($title_str, 0)->plaintext);
                    // dd($html->find('title', 0)->plaintext);
                    // dd($remove_str_arr[1]);
                    // dd(117, $reg_str, $title, $origin_output);
                    // $org = $html->find($org_str, 0)->innertext;
                }
                else {
                    $origin_output = '';
                }

                if ($reg_str != '') {
                    preg_match($reg_str, $origin_output, $metaContentsMatches);
                    // dd(127, $reg_str, $origin_output, $metaContentsMatches);
                }

                $content_trim = '';
                if ($domain != 'default' && $origin_output != '' && !empty($metaContentsMatches)) {
                    $find = array('/<script.*>.*<\/script>/','/<[^>]*>/', '/\t/', '/,/');
                    $replace = array('', '', '', '');
                    $content_clean_tags =  preg_replace($find, $replace, $metaContentsMatches);
                    // $content_clean_tags = preg_replace('/<[^>]*>/', '', $metaContentsMatches);
                    // dd(134, $metaContentsMatches);
                    preg_match('/(.*)/s', $content_clean_tags[0], $content);
                    $content_trim = trim(preg_replace('/&nbsp;/', '', $content[1]));
                }
                // dd(138, $content_trim);
                // insert to database

                if ($news_url_inst == null) {
                    $news_url->url = $url;
                    $news_content = new NewsContent;
                    $news_url->save();
                    $news_content->url_id = $news_url->id;
                    $news_content->article = $title;
                    // $news_content->author = '';
                    $news_content->content = $content_trim;
                    $news_content->save();
                    $url_id = $news_content->url_id;
                }
                else if ($news_url_inst->newscontent->content == '') {
                    $news_url_inst->newscontent->article = $title;
                    $news_url_inst->newscontent->content = $content_trim;
                    $news_url_inst->newscontent->save();
                    $url_id = $news_url_inst->id;
                }

            }
            else {
                $url_id = $news_url->where('url', $url)->pluck('id');
                $content_trim = $news_url->find($url_id)->newscontent->content;
                $title = trim($news_url->find($url_id)->newscontent->article);
            }

            array_push($domain_arr, $domain);
            array_push($host_arr, $host);
            array_push($title_arr, $title);
            // array_push($org_arr, $org);
            array_push($ids_arr, $url_id);
            array_push($content_trim_arr, $content_trim);
            array_push($url_clear_arr, $url);

        }

        return response()->json(['domain' => $domain_arr, 'host' => $host_arr, 'content' => $content_trim_arr, 'url' => $url_clear_arr,
            'id' => $ids_arr, 'title' => $title_arr, 'message' => 'ok'])->setCallback($request->input('callback'));
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
        $remove_str_arr = [];
        $domain = 'default';
        $curl_useragent = 'Google Bot';
        // preg_match('/http[s]?:\/\/(.*\.)?(.*)\.(com|net|mg|tw)/', $url, $filter_domain);
        // 過濾出正確網址
        // 例如 http://61.219.29.200/gb/health.cna.com.tw/healthnews/20151029S008.aspx
        // http://health.cna.com.tw/healthnews/20151029S008.aspx
        preg_match('/http[s]?:\/\/(.*\/)?.*\.(com|net|mg|tw)/', $url, $filter_url);
        if (!empty($filter_url[1])) {
            // dd(addslashes($filter_url[1]), $url);
            // dd($filter_url[1]);
            $filter_url[1] = preg_replace("/\//", "\/", $filter_url[1]);
            $url = preg_replace("/$filter_url[1]/", "", $url);
        }

        // $filter_domain = $this->parseURL($url);
        $filter_domain = $this->getDoamin($url);

        if (!empty($filter_domain)) {
            $domain = $filter_domain['domain'];
            $subdomain = $filter_domain['subdomain'];
            $host = $filter_domain['host'];

            switch ($host) {
                case 'udn':
                    $title_str = 'h2[id=story_art_title]';
                    $content_str = 'div[id=story_body_content]';
                    // $reg_str = '/<div id=\"story\".*class=\"area\">(.+?)<\/div>/s';
                    $reg_str = '/<p>(.+)。[<p>|<\/p>]/s'; // which genius write this code, damm
                    $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                    if ($subdomain == 'blog') {
                        $title_str = 'div[class=article_topic]';
                        $content_str = 'div[id=article_show_content]';
                        $reg_str = '/(.+<\/p>)/s';
                    }
                    break;
                case 'yahoo':
                    // for yahoo
                    $title_str = 'h1[class=headline]';
                    $content_str = 'div[id=mediaarticlebody]';
                    // $reg_str = '/<!-- google_ad_section_start -->(.+?)<!-- google_ad_section_end -->/s';
                    $reg_str = '/<p.*>(.+)<\/p>/s';
                    $find = array('/news\//', '/mobi/', '/\/home/', '/\/tech/', '/\/sports/');
                    $replace = array('', 'news', '', '', '');
                    // $org_str = 'span[class=provider]';
                    $url = preg_replace($find, $replace, $url);
                    $remove_str_arr = ['div[class=yog-col]'];
                    break;
                case 'yam':
                    $title_str = 'li[class=title]';
                    $content_str = 'div[id=news_content]';
                    $reg_str = '/<p\s.+>(.+)。/s';
                    $url = preg_replace('/_pic|\?pic=\d/', '', $url);
                    break;
                case 'uho':
                    $title_str = 'div[id=art_title] div[class=till]';
                    $content_str = 'div[class=contout]';
                    $reg_str = '/<p\s.+>(.+)。/s';
                    break;
                case 'pchome':
                    $title_str = 'span[id=iCliCK_SafeGuard]';
                    $content_str = 'div[id=newsContent]';
                    $reg_str = '/<div calss=\"article_text\">(.+?)<\/div>/s';
                    break;
                case 'nownews':
                    $title_str = 'h1[itemprop=headline]';
                    $content_str = 'div[class=story_content]';
                    // $reg_str = '/<div class=\"story_content\".*>(.+?)<div class=\"page_nav\"/s';
                    $reg_str = '/<p>(.+)。<\/p>/s';
                    $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                    break;
                case 'secretchina':
                    $title_str = 'div[id=content] h2';
                    $content_str = 'div[class=articlebody]';
                    $reg_str = '/<p>(.+)。<\/p>/s';
                    break;
                case 'appledaily':
                    $title_str = 'h1[id=h1]';
                    $content_str = 'div[class=articulum]';
                    $reg_str = '/<p\s.*>(.+)<\/p>/s';
                    // $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                    break;
                case 'bayvoice':
                    $title_str = 'h1[id=single_title]';
                    $content_str = 'article[id=content-body]';
                    $reg_str = '/<p\s.*>(.+)<\/p>/s';
                    $url = preg_replace('/gb/s', 'b5', $url);
                    break;
                case 'msn':
                    $content_str = 'section[class=articlebody]';
                    $reg_str = '/<p\s.*>(.+)<\/p>/s';
                    break;
                case 'top1health':
                    $title_str = 'h2[itemprop=name]';
                    $content_str = 'div[class=content]';
                    $reg_str = '/<p>(.+)。<\/p>/s';
                    break;
                case 'times-bignews':
                    $content_str = 'div[class=news_content]';
                    $reg_str = '/<p>(.+)。<\/p>/s';
                    break;
                case 'cna':
                    $title_str = 'div[class=news_title]';
                    $content_str = 'section[itemprop=articleBody]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'sina':
                    $title_str = 'div[id=articles] h1';
                    $content_str = 'div[class=pcont]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'new0':
                    $title_str = 'h1[itemprop=headline]';
                    $content_str = 'span[itemprop=articleBody]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'url':
                    $title_str = 'h1[class=entry-title]';
                    $content_str = 'div[class=td-paragraph-padding-1] p';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'epochtimes':
                    $title_str = 'h1[class=entry-title]';
                    $content_str = 'div[class=td-paragraph-padding-1]';
                    $reg_str = '/<span class="author_box_01">(.)+/s';
                    break;
                case 'commonhealth':
                    $title_str = 'h2[class=darkGreen]';
                    $content_str = 'div[class=commonOutData]';
                    $reg_str = '/<div class="commonArticle">(.+)。/s';
                    break;
                case 'storm':
                    $title_str = 'h1[id=article_title]';
                    $content_str = 'div[class=article-wrapper] article';
                    $reg_str = '/<p>(.+)<\/p>\s*<div class="ad_article_block">/s';
                    break;
                case 'qq':
                    $title_str = 'div[class=hd] h1';
                    $content_str = 'div[id=Cnt-Main-Article-QQ]';
                    $reg_str = '/<p\s.*>(.+)<\/p>/s';
                    break;
                case 'chinatimes':
                    $title_str = 'article header h1';
                    $content_str = 'article[class=clear-fix]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'ltn':
                    $title_str = 'div[class=content] h1';
                    $content_str = 'div[id=newstext]';
                    $remove_str_arr = ['div[class=pic600]', 'span'];
                    $reg_str = '/<p>(.+)<\/p>.*<div id="newsad" class="ad">/s';
                    break;
                case 'ettoday':
                    $title_str = 'h2[class=title]';
                    $content_str = 'div[class=story]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                    break;
                case 'peoplenews':
                    $title_str = 'h1[class=news_title]';
                    $content_str = 'div[id=newscontent]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'theinitium':
                    $title_str = 'h1[class=article-title]';
                    $content_str = 'div[class=article-content]';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'newtalk':
                    $title_str = 'div[class=content_title]';
                    $content_str = 'div[class=news-content]';
                    $remove_str_arr = ['div[class=news_img]'];
                    $reg_str = '/<txt>(.+)<\/txt>/s';
                    break;
                case 'cts':
                    $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                    break;
                case 'setn':
                    $title_str = 'div[class=title] h1';
                    $content_str = 'div[id=Content1]';
                    $remove_str_arr = ['img', 'a', 'div[class=SET_FB]'];
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'tvbs':
                    $title_str = 'div[class=reandr_title] h2';
                    $content_str = 'div[class=textContent]';
                    $remove_str_arr = ['img', 'strong'];
                    $curl_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4';
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'hi-on':
                    $title_str = 'div[class=title]';
                    $content_str = 'div[id=message]';
                    $remove_str_arr = ['a'];
                    $reg_str = '/<p>(.+)<\/p>/s';
                    break;
                case 'knowing':
                    $title_str = 'title';
                    $content_str = 'div[class=content]';
                    $remove_str_arr = ['ul[id=knowing_links]'];
                    $reg_str = '/<p\s.*>(.+)<\/p>/s';
                    break;
                default:
                    $host = 'default';
                    break;
            }
        }

        // 處理 url encode
        $find = array('/%3[a,A]/', '/%2[f,F]/', '/%3[f,F]/', '/%3[d,D]/', '/%26/');
        $replace = array(':', '/', '?', '=', '&');
        $url =  preg_replace($find, $replace, urlencode($url));

        // $url_utf8_encode_str = '/([\\x{4e00}-\\x{9fa5}]+)/u';
        return array('url' => $url, 'reg_str' => $reg_str, 'domain' => $domain, 'host' => $host,
            'dom_str' => $content_str, 'title_str' => $title_str, 'remove_str_arr' => $remove_str_arr,
            'curl_useragent' => $curl_useragent);
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

    /**
     * [postNewsSimilars 儲存 餘弦值]
     * @param  Request $request [傳 url_id、value 進來]
     * @return [type]           [description]
     */
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

    /**
     * [parseURL 解析 url]
     * @param  [type] $url [description]
     * @return [type]      [description]
     */
    // public function parseURL($url) {
    //     $parsed_url = [];

    //     if ($url == null || strlen($url) == 0 )
    //         return $parsed_url;

    //     $protocol_i = strpos($url, '://');
    //     $parsed_url['protocol'] = substr($url, 0, $protocol_i);
    //     $remaining_url = substr($url, $protocol_i + 3, strlen($url));
    //     $domain_i = strpos($remaining_url, '/');
    //     $domain_i = (!$domain_i) ? strlen($remaining_url) : $domain_i;
    //     $parsed_url['domain'] = substr($remaining_url, 0, $domain_i);
    //     $parsed_url['path'] = $domain_i == -1 || $domain_i + 1 == strlen($remaining_url) ? null : substr($remaining_url, $domain_i + 1, strlen($remaining_url));

    //     $domain_parts = explode('.', $parsed_url['domain']);

    //     $ch = curl_init();
    //     $options = array(
    //         CURLOPT_URL => "http://whoiz.herokuapp.com/lookup.json?url=".$parsed_url['domain'],
    //         CURLOPT_RETURNTRANSFER => true,
    //         // CURLOPT_USERAGENT => $curl_useragent,
    //         // CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.125 Safari/533.4',
    //         // CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0',
    //         // CURLOPT_HEADER => false,
    //         // CURLOPT_SSL_VERIFYPEER => FALSE,
    //         // CURLOPT_FOLLOWLOCATION => true,
    //         // CURLOPT_REFERER => $url
    //     );

    //     curl_setopt_array($ch, $options);
    //     $curl_output = curl_exec($ch);

    //     curl_close($ch);

    //     $whois = json_decode($curl_output);
    //     $parsed_url['domain'] = $whois->domain;
    //     // $parsed_url['available'] = $whois->available?;
    //     dd($parsed_url, $whois);
    //     return json_decode($curl_output);
    //     switch (count($domain_parts)) {
    //         case 2:
    //             $parsed_url['subdomain'] = null;
    //             $parsed_url['host'] = $domain_parts[0];
    //             $parsed_url['tld'] = $domain_parts[1];
    //             break;
    //         case 3:
    //             $parsed_url['subdomain'] = $domain_parts[0];
    //             $parsed_url['host'] = $domain_parts[1];
    //             $parsed_url['tld'] = $domain_parts[2];
    //             break;
    //         case 4:
    //             $parsed_url['subdomain'] = $domain_parts[0];
    //             $parsed_url['host'] = $domain_parts[1];
    //             $parsed_url['tld'] = $domain_parts[2] + '.' + $domain_parts[3];
    //             break;
    //         case 5:
    //             $parsed_url['subdomain'] = $domain_parts[0];
    //             $parsed_url['host'] = $domain_parts[1];
    //             $parsed_url['tld'] = $domain_parts[2] + '.' + $domain_parts[3];
    //             break;
    //         default:
    //             $parsed_url['subdomain'] = null;
    //             $parsed_url['host'] = $domain_parts[0];
    //             $parsed_url['tld'] = $domain_parts[1];
    //             break;
    //     }

    //     $parsed_url['parent_domain'] = $parsed_url['host'] + '.' + $parsed_url['tld'];

    //     return $parsed_url;
    // }

    /**
     * [getDoamin 從 whoisxmlapi 取得 domain]
     * @param  [type] $url [description]
     * @return [type]      [description]
     */
    public function getDoamin($url) {
        $username="machiko";
        $password="qazxsw";
        $parsed_url = [];
        $protocol_i = strpos($url, '://');
        // $parsed_url['protocol'] = substr($url, 0, $protocol_i);
        $remaining_url = substr($url, $protocol_i + 3, strlen($url));
        $domain_i = strpos($remaining_url, '/');
        $domain_i = (!$domain_i) ? strlen($remaining_url) : $domain_i;
        $parsed_url['url'] = substr($remaining_url, 0, $domain_i);
        $parsed_url['path'] = $domain_i == -1 || $domain_i + 1 == strlen($remaining_url) ? null : substr($remaining_url, $domain_i + 1, strlen($remaining_url));


        $url_domains = new UrlDomain;
        $url_domains_inst = $url_domains->where('url', $parsed_url['url'])->first();

        if ($url_domains_inst == null) {
            $contents = file_get_contents("http://www.whoisxmlapi.com/whoisserver/WhoisService?domainName=".$parsed_url['url']."&cmd=GET_DN_AVAILABILITY&username=$username&password=$password&outputFormat=JSON");
            $res = json_decode($contents);

            if ($res) {
                if (property_exists($res, 'ErrorMessage')) {
                    // echo $res->ErrorMessage->msg;
                    // return $res->ErrorMessage->msg;
                }
                else {
                    $domainInfo = $res->DomainInfo;
                    if ($domainInfo) {
                        $domain = $domainInfo->domainName;
                        $parsed_url['domain'] = $domain;
                        $parsed_url['subdomain'] = explode('.'.$domain, $parsed_url['url'])[0];
                        if ($parsed_url['subdomain'] == $domain) {
                            $parsed_url['subdomain'] = '';
                        }
                        $parsed_url['host'] = explode('.', $domain)[0];
                        // echo "Domain name: " . print_r($domainInfo->domainName,1) ."<br/>";
                        // echo "Domain Availability: " .print_r($domainInfo->domainAvailability,1) ."<br/>";
                    }
                }
            }

            // store to database
            $url_domains->url = $parsed_url['url'];
            $url_domains->domain = $parsed_url['domain'];
            $url_domains->subdomain = $parsed_url['subdomain'];
            $url_domains->host = $parsed_url['host'];
            $url_domains->save();
        }
        else {
            $parsed_url['domain'] = $url_domains_inst->domain;
            $parsed_url['subdomain'] = $url_domains_inst->subdomain;
            $parsed_url['host'] = $url_domains_inst->host;
        }

        return $parsed_url;
    }

    /** The same as curl_exec except tries its best to convert the output to utf8 **/
    /*
     * [curl_exec_utf8 description]
     * @param  [type] $ch [description]
     * @return [type]     [description]
     */
    public function curl_exec_utf8($ch) {
        $data = curl_exec($ch);
        if (!is_string($data)) return $data;

        unset($charset);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        /* 1: HTTP Content-Type: header */
        preg_match( '@([\w/+]+)(;\s*charset=(\S+))?@i', $content_type, $matches );
        if ( isset( $matches[3] ) )
            $charset = $matches[3];

        /* 2: <meta> element in the page */
        if (!isset($charset)) {
            preg_match( '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $data, $matches );
            if ( isset( $matches[3] ) )
                $charset = $matches[3];
        }

        /* 3: <xml> element in the page */
        if (!isset($charset)) {
            preg_match( '@<\?xml.+encoding="([^\s"]+)@si', $data, $matches );
            if ( isset( $matches[1] ) )
                $charset = $matches[1];
        }

        /* 4: PHP's heuristic detection */
        if (!isset($charset)) {
            $encoding = mb_detect_encoding($data);
            if ($encoding)
                $charset = $encoding;
        }

        /* 5: Default for HTML */
        if (!isset($charset)) {
            if (strstr($content_type, "text/html") === 0)
                $charset = "ISO 8859-1";
        }

        /* Convert it if it is anything but UTF-8 */
        /* You can change "UTF-8"  to "UTF-8//IGNORE" to
           ignore conversion errors and still output something reasonable */
        if (isset($charset) && strtoupper($charset) != "UTF-8")
            // debug($charset, mb_convert_encoding($data, 'utf-8', 'GBK,UTF-8,ASCII'));
            // 處理簡體網站
            if ($charset == 'GB2312' || $charset == 'gb2312') {
                $data = mb_convert_encoding($data, 'utf-8', 'GBK,UTF-8,ASCII');
            }
            else if ($charset == 'big5' || $charset == 'BIG5') {
                $data = mb_convert_encoding($data, "utf-8", $charset);
            }
            else {
                $data = iconv($charset, 'UTF-8', $data);
            }

            // dd($data);

        return $data;
    }

}
