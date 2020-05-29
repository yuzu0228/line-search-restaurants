<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\Event\MessageEvent\TextMessage;

use App\Services\Gurunavi;
use App\Services\RestaurantBubbleBuilder;

use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;

class LineBotController extends Controller
{
    public function index()
    {
        return view('linebot.index');
    }

    public function restaurants(Request $request)
    {

        Log::debug($request->header());
        Log::debug($request->input());

        //インスタンス化
        $httpClient = new CurlHTTPClient(env('LINE_ACCESS_TOKEN'));
        $lineBot = new LINEBot($httpClient, ['channelSecret' => env('LINE_CHANNEL_SECRET')]);

        //シグネチャの検証
        $signature = $request->header('x-line-signature');
        if (!$lineBot->validateSignature($request->getContent(), $signature)) {
            abort(400, 'Invalid signature');
        }

        //メッセージボディからイベントを取り出す
        $events = $lineBot->parseEventRequest($request->getContent(), $signature);
        Log::debug($events);

        //foreachで取り出し、TextMessageクラスのインスタンスか判定
        foreach ($events as $event) {
            if (!($event instanceof TextMessage)) {
                Log::debug('Non text message has come');
                continue;
            }

            //App\Services\gurunaviクラスのsearchRestaurantsメソッドにlineからのリクエストメッセージを戻り値として渡す
            $gurunavi = new Gurunavi();
            $gurunaviResponse = $gurunavi->searchRestaurants($event->getText());

            //errorの場合
            if (array_key_exists('error', $gurunaviResponse)) {
                $replyText = $gurunaviResponse['error'][0]['message'];
                $replyToken = $event->getReplyToken();
                $lineBot->replyText($replyToken, $replyText);
                continue;
            }

            $bubbles = [];
            foreach ($gurunaviResponse['rest'] as $restaurant) {
                $bubble = RestaurantBubbleBuilder::builder();
                $bubble->setContents($restaurant);
                $bubbles[] = $bubble;
            }

            $carousel = CarouselContainerBuilder::builder();
            $carousel->setContents($bubbles);

            /*今回はflex messageなので、MessageBuilderクラスのタイプは
            FlexMessageBuilderを使う */
            $flex = FlexMessageBuilder::builder(); //builderメソッドは、空のインスタンスを生成
            $flex->setAltText('飲食店検索結果'); //setAltTextメソッドは、FlexMessageBuilderインスタンスのプロパティaltTextに文字列を代入, flexmessageはトーク一覧画面でのメッセージ内容はltTextの内容が表示される
            $flex->setContents($carousel);

            /*テキスト以外のタイプのメッセージでの返信はreplyMessageメソッドを使う。
            単純なテキストでの返信であればreplyTextメソッドを使う
            第一引数：応答トークン、第二：MessageBuilderクラスのインスタンス*/
            $lineBot->replyMessage($event->getReplyToken(), $flex);
        }
    }
}
