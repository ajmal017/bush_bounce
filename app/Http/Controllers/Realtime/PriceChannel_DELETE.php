<?php
/**
 * Created by PhpStorm.
 * User: slinger
 * Date: 5/21/2018
 * Time: 9:37 PM
 */

namespace App\Http\Controllers\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Class PriceChannel
 * Calculated price channel based on historical data loaded from www.bitfinex.com
 * @package App\Http\Controllers\Realtime
 */


// THIS CLASS IS NOT USED ANYMORE
// \App\Http\Controllers\Realtime\PriceChannel::calculate(); // Calculate price channel
// SHOULD BE USED INSTED

class PriceChannel extends \App\Http\Controllers\Controller
{
    public static function calculateZZZZ() { // $priceChannelPeriod

        /** Clear prive chanel columns in DB */
        //DB::table("asset_1")->update([
        //    'price_channel_high_value' => null,
        //    'price_channel_low_value' => null
        //]);

        //DB::table(env("ASSET_TABLE"))->truncate();

        /** @var int $priceChannelPeriod */
        $priceChannelPeriod = DB::table('settings_realtime')
            ->where('id', 1)
            ->value('price_channel_period');
        //$priceChannelPeriod = $priceChannelPeriod;

        /**
         * @var int elementIndex Loop index. If the price channel period is 5 the loop will go from 0 to 4.
         * The loop is started on each candle while running through all candles in the array.
         */
        $elementIndex = 0;

        /** @var int $priceChannelHighValue Base value for high value search*/
        $priceChannelHighValue = 0;

        /** @var int $priceChannelLowValue Base value for low value search. Really big value is needed at the beginning.
        Then we compare current value with 999999. It is, $priceChannelLowValue = current value*/
        $priceChannelLowValue = 999999;

        /**
         * desc - from big values to small. asc - from small to big
         * in this case: desc. [0] element is the last record in DB. and it's id - quantity of records
         * @var json object $records Contains all DB data (records) in json format
         * IT IS NOT A JSON! IT MOST LIKLEY LARAVEL OBJECT. BUTSCH WATED TO SEND ME THE LINK
         * https://laravel.com/docs/5.6/collections
         */
        $records = DB::table("asset_1")
            ->orderBy('time_stamp', 'desc')
            ->get(); // desc, asc - order. Read the whole table from BD to $records

        /**
         * Calculate price channel max, min
         * First element in the array is the oldest
         * Start from the oldest element in the array which is on the right at the chart. The one on the left at the chart
         */
        foreach ($records as $record) {
            /**
             * Indexex go like this 0,1,2,3,4,5,6 from left to the right
             * We must stop before $requestBars reaches the end of the array
             */
            if ($elementIndex <=
                DB::table('settings_realtime')
                    ->where('id', 1)
                    ->value('request_bars') - $priceChannelPeriod - 1)
            {
                // Go from right to left
                for ($i = $elementIndex ; $i < $elementIndex + $priceChannelPeriod; $i++)
                {
                    //echo "---------------$i for: " . $records[$i]->date . "<br>";

                    /** Find max value in interval */
                    if ($records[$i]->high > $priceChannelHighValue)
                        $priceChannelHighValue = $records[$i]->high;
                    /** Find low value in interval */
                    if ($records[$i]->low < $priceChannelLowValue)
                        $priceChannelLowValue = $records[$i]->low;
                }

                //echo "$elementIndex " . $records[$elementIndex]->date . " " . $priceChannelHighValue . "<br>";


                /** Update high and low values in DB */
                DB::table("asset_1")
                    ->where('time_stamp', $records[$elementIndex]->time_stamp)
                    ->update([
                        'price_channel_high_value' => $priceChannelHighValue,
                        'price_channel_low_value' => $priceChannelLowValue,
                    ]);

                /** Reset high, low price channel values */
                $priceChannelHighValue = 0;
                $priceChannelLowValue = 999999;
            }
            $elementIndex++;
        }
    }
}

