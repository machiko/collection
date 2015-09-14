<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;
use Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class ApiController extends Controller
{
    public function getCurl()
    {
        $url = Request::input('url');
        // $url = "http://www.taiwanlottery.com.tw/lotto/DailyCash/history.aspx";
		$ch = curl_init();

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => "Google Bot",
            CURLOPT_HEADER => false
        );

        curl_setopt_array($ch, $options);
	    $output = curl_exec($ch);
        
	    curl_close($ch);

	    // Find the table
	    /*preg_match_all("/<table.*?>.*?<\/[\s]*table>/s", $output, $tablesMatches);*/
        /*preg_match("/<div.*?id=\"mediaarticlebody\".*?>(.*?)<\/div>/s", $output, $metaContentsMatches);*/
        $content_trim = '';
        // 過濾 url domain
        preg_match("/http[s]?:\/\/.*\.(.*)\.com/", $url, $filter_domain);

        switch ($filter_domain[1])
        {
            case 'udn':
                $domain = 'udn';
                break;
            case 'yahoo':
                // for yahoo
                $domain = 'yahoo';
                preg_match("/<!-- google_ad_section_start -->(.*?)<!-- google_ad_section_end -->/s", $output, $metaContentsMatches);
                $content_clean_tags = preg_replace("/<[^>]*>/", "", $metaContentsMatches);
                preg_match("/(.*。)/s", $content_clean_tags[0], $content);
                $content_trim = preg_replace("/&nbsp;/", "", $content[1]);
                break;
            default:
                $domain = 'default';
                break;
        }

	    /*preg_match("/<meta.*?name=\"description\".*?content=\"(.*?)\".*?>|<meta.*?content=\"(.*?)\".*?name=\"description\".*?>/i", $output, $metaContentsMatches);*/
	    /*preg_match("/<meta.*?name=\"description\".*?content=\"(.*?)\".*?>|<meta.*?content=\"(.*?)\".*?name=\"description\".*?>/i", $output, $metaContentsMatches);*/
	    $tables = array();
        // return string($output);
        // echo html_entity_decode($output);
        return response()->json(['content' => $content_trim, 'domain' => $domain]);
        // return response()->json(['content' => $content_clean_tags[0]]);
        // return response()->json(['content' => $output]);

    }
}
