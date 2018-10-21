<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 10/14/2018
 * Time: 6:12 AM
 */

namespace App\Classes\Hitbtc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Basic trading class.
 * Places and moves orders.
 * All events like: order place confirmation, order move, confirmation etc. Are received in ccxtsocket.php
 * them methods of this class are called.
 *
 * In order to move an order thre conditions must be met:
 * 1. Placed order at the best bid/ask price is not the best anymore
 * 2. $this->needToMoveOrder = true;
 * 2. Rate limit. Order can be moved not requentley than certain (adjustable amount of time)
 *
 */
class Trading
{
    private $priceStep = 0.01; // ETHBTC price step 0.000001
    private $priceShift = 0; // How far the limit order will be placed away from the market price. steps
    private $orderId;
    private $orderPlacePrice;
    private $activeOrder = null; // When there is an order present
    private $needToMoveOrder = true;
    private $rateLimitTime = 0; // Replace an order once a second
    private $rateLimitFlag = true; // Enter to the rate limit condition once

    public function __construct()
    {
    }

    /**
     * On each update bid/ask event this method is called.
     * Both parameters are optional. If (bid, null) - buy limit order will be placed.
     * @param   double @bid
     * @param   double @ask
     * @return  void
     */
    public function parseTicker($bid = null, $ask = null){

        ($bid ? $direction = "buy" : $direction = "sell");
        if ($this->activeOrder == null){

            $this->orderId = floor(round(microtime(true) * 1000));
            ($direction == "buy" ? $this->orderPlacePrice = $bid - $this->priceStep * $this->priceShift : $this->orderPlacePrice = $ask + $this->priceStep * $this->priceShift);
            Cache::put('orderObject', new OrderObject(false, $direction, $this->orderPlacePrice , $this->orderId, ""), 5);
            $this->activeOrder = "placed";

            // BD actions
            if($direction == "buy" ){
                DataBase::addOrderRecord($this->orderId);
                DataBase::addOrderInPrice(date("Y-m-d G:i:s"), $this->orderPlacePrice);
            }
            else{
                DataBase::addOrderOutPrice(date("Y-m-d G:i:s"), $this->orderPlacePrice);
            }

        }

        // When order placed, start to move if needed
        if ($this->activeOrder == "new"){

            ($direction == "buy" ? $priceToCheck = $bid - $this->priceStep * $this->priceShift : $priceToCheck = $ask + $this->priceStep * $this->priceShift);
            if ($this->orderPlacePrice != $priceToCheck){

                if ($this->needToMoveOrder){
                    echo "TIME to move the order! \n";

                    if (time() > $this->rateLimitTime || $this->rateLimitFlag){
                        ($direction == "buy" ? $this->orderPlacePrice = $bid - $this->priceStep * $this->priceShift : $this->orderPlacePrice = $ask + $this->priceStep * $this->priceShift);
                        //$tempOrderId = (string)microtime();
                        $tempOrderId = round(microtime(true) * 1000);

                        Cache::put('orderObject', new OrderObject(true,"", $this->orderPlacePrice, $this->orderId, $tempOrderId), 5);
                        $this->orderId = $tempOrderId;
                        $this->needToMoveOrder = false;

                        $this->rateLimitFlag = false;
                        $this->rateLimitTime = time() + 2;
                    }
                    else{
                        dump('Trading.php rate limit');
                    }
                }
            }
        }
    }

    /**
     * Active order state parse.
     * Parse statuses:
     * - new
     * - filled
     * Replaced (moved) order status is handled in parseOrderMove()
     * @param   array @mesage
     * @return  void
     */
    public function parseActiveOrders(array $message){
        /* Order placed
         * As discovered this method is called on each order move! This is incorrect.
         */
        if ($message['params']['clientOrderId'] == $this->orderId && $message['params']['status'] == "new"){
            $this->activeOrder = "new";
        }

        /* Order filled */
        if ($message['params']['clientOrderId'] == $this->orderId && $message['params']['status'] == "filled"){
            $this->activeOrder = "filled"; // Then we can open a new order
            $this->needToMoveOrder = false; // When order is has been filled - don't move it
            echo "Order FILLED! filled price: ";
            echo $message['params']['tradePrice'] . "\n";
            Cache::put('commandExit', true, 5);

            if($message['params']['side'] == "buy"){
                DataBase::addOrderInExecPrice(date("Y-m-d G:i:s", strtotime($message['params']['updatedAt'])), $message['params']['price'], $message['params']['tradeFee']);
            }
            else{
                DataBase::addOrderOutExecPrice(date("Y-m-d G:i:s", strtotime($message['params']['updatedAt'])), $message['params']['price'], $message['params']['tradeFee']);
                DataBase::calculateProfit();
            }

            // rebate: trade fee
            // side - long/short
            // 2018-10-21T15:29:07.523Z
        }
    }

    /**
     * Determines whether an order should be moved or not.
     * Order statuses requested via websocket method call: getOrders.
     * Order statuses cam be delivered as an array ['orderClientId'], ['status'].
     * This array is foreached in ccxtsocket.php, websocketMessageParse().
     * At each iteration clientOrderId is cheched. This action is needed in order to move only specific order because
     * multiple orders can be be active at the same time under the same account, placed by other bots.
     * @param   array $message
     * @return  void
     *
     */
    public function parseOrderMove(array $message){
       echo "Trading.php ---Order move. this-orderId: " . $this->orderId . " message['clientOrderId']: " . $message['clientOrderId'] . "\n";
        //dump($message);

        if($this->orderId == $message['clientOrderId']){
            //dump($message[id]);
            echo "need to move this ID!\n";
            $this->needToMoveOrder = true;
        }
        else{
            //dump('as a single valuse: ' . $message['id']);
            echo "dont need to move this id\n";
        }
    }
}

