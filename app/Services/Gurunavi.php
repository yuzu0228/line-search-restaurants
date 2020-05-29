<?php

namespace App\Services;

use GuzzleHttp\Client;

class Gurunavi
{
    private const RESTAURANTS_SEARCH_API_URL = 'https://api.gnavi.co.jp/RestSearchAPI/v3/';

    public function searchRestaurants(string $word): array
    {
        //guzzle使う
        //インスタンス化して、getメソッドで指定したurlに対してgetリクエストを送る
        //第二引数は連想配列でさらにqueryをキーとする連想配列でリクエストパラメータを指定
        $client = new Client();
        $response = $client
            ->get(self::RESTAURANTS_SEARCH_API_URL, [
                'query' => [
                    'keyid' => env('GURUNAVI_ACCESS_KEY'),
                    'freeword' => str_replace(' ', ',', $word),
                ],
                //guzzleのエラー時の例外投げつけを無効
                'http_errors' => false,
            ]);
            
        //jsonで返却されるからレスポンスボディをデコード
        return json_decode($response->getBody()->getContents(), true);
    }
}