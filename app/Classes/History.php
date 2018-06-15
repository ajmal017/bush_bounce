<?php

namespace App\Classes;
use GuzzleHttp\Client; // Guzzle is used to send http headers http://docs.guzzlephp.org/en/stable/
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;


/**
 * Class History
 * Gets history data from www.bitfinex.com and records in to the DB
 * Contains two methods: bars load from current time, history period for specified dated
 * @package App\Http\Controllers
 */

class History
{
    /**
     * Loads historical data to DB from www.bitfinex.com.
     * These bars are shown at the chart when the page is open.
     * Each bar has 5 values: 1526651340000,8095.7,8095.7,8090.1,8090.1
     *
     * @return void
     */

    /** Gets specefied number of bars. This method is called when real-time mode is activated */
    static public function load(){

        /**
         * If initial start is true
         * True is set by default or by Initial start button click at the start page
         * Is Set to false after history data is loaded
         */
        //if ((DB::table('settings_realtime')
        //        ->where('id', env("SETTING_ID"))
        //        ->value('initial_start'))){
        if (true){

            DB::table('asset_1') // Drop all records in the table
                ->truncate();

            $timeframe =
                DB::table('settings_realtime')
                    ->where('id', 1)
                    ->value('time_frame') . "m";

            $asset =
                DB::table('settings_realtime')
                    ->where('id', 1)
                    ->value('symbol');

            /*
            echo "init start: " .
                DB::table('settings_realtime')
                    ->where('id', env("SETTING_ID"))
                    ->value('initial_start') . "<br>";
            */

            /**
             * Create guzzle http client
             * @var GuzzleHttp\Client $api_connection Provides http headers send functionality
             * @link http://docs.guzzlephp.org/en/stable/
             */
            $api_connection = new Client([
                'base_uri' => 'https://api.bitfinex.com/v2/',
                'timeout' => 50 // If make this value small - fatal error occurs
            ]);

            //$restEndpoint = "candles/trade:" . $timeframe . ":t" . $asset . "/hist?limit=20&start=" . $start . "&end=" . $end . "&sort=1";
            $restEndpoint = "candles/trade:" . $timeframe . ":t" . $asset . "/hist?limit=" . DB::table('settings_realtime')->where('id', 1)->value('request_bars'); // Gets bars from the present moment. No dates needed. Values must be reversed befor adding to DB. Otherwise - the chart is not properly rendered, all bars look fat

            // http://docs.guzzlephp.org/en/stable/request-options.html#http-errors
            $response = $api_connection->request('GET', $restEndpoint, ['http_errors' => true ]);

            //echo "GUZZLE reason: " . $response->getReasonPhrase() . "<br>";

            $body = $response->getBody(); // Get the body out of the request
            $json = json_decode($body, true); // Decode JSON. Associative array will be outputted

            if ($response->getStatusCode() == 200) // Request successful
            {
                /** Add candles to DB */
                foreach (array_reverse($json) as $z) { // The first element in array is the youngest - first from the left on the chart. Go through the array backwards. This is the order how points will be read from DB and outputed to the chart
                    DB::table('asset_1')->insert(array( // Record to DB
                        'date' => gmdate("Y-m-d G:i:s", ($z[0] / 1000)), // Date in regular format. Converted from unix timestamp
                        'time_stamp' => $z[0],
                        'open' => $z[1],
                        'close' => $z[2],
                        'high' => $z[3],
                        'low' => $z[4],
                        'volume' => $z[5],
                    ));
                }
            }
            else // Request is not successful. Error code is not 200
            {
                echo "History. Too many request error";
            }

            /** Calculate price channel */
            //PriceChannel::calculate();

            /** Ste Initial start flag to false */
            DB::table('settings_realtime')
                ->where('id', 1)
                ->update([
                    'initial_start' => 0,
                ]);
        }
    }

    /** Gets history data for specified period of time. This method is called when history back testing mode is activated */
    static public function LoadPeriod(){

        // Read start and end dates, timeframe from the DB
        $start = (strtotime(DB::table('settings_realtime')->where('id', 1)->value('history_from')) * 1000); // Timestamp
        $end = (strtotime(DB::table('settings_realtime')->where('id', 1)->value('history_to')) * 1000);
        $timeframe = DB::table('settings_realtime')->where('id', 1)->value('time_frame') . "m"; // "m" - minutes
        $symbol = DB::table('settings_realtime')->where('id', 1)->value('symbol');

        echo "start: " . gmdate ("d-m-Y G:i:s", ($start / 1000)) . " end: " . gmdate ("d-m-Y G:i:s", ($end / 1000)) . "<br>";
        echo "timeframe: " . $timeframe . "<br>";

            // Create guzzle http client
            $api_connection = new Client([
                'base_uri' => 'https://api.bitfinex.com/v2/',
                'timeout' => 50 // If make this value small - fatal error occurs
            ]);

            // Working end point! do not touch it!
            //$restEndpoint = "candles/trade:" . $timeframe . ":tBTCUSD/hist?limit=1000&start=" . $start . "&end=" . $end . "&sort=1";
           // https://api.bitfinex.com/v2/candles/trade:1m:tBTCUSD/hist?limit=1000&start=1518480000000&end=1518566400000&sort=1

            // Break requested time period into pieces and request one by one
            $q = 1;
            // Read star and end date, timeframe from the DB
            //$start = (strtotime(DB::table('assets')->where('id', 1)->value('load_history_start')) * 1000); // Timestamp
            //$end = (strtotime(DB::table('assets')->where('id', 1)->value('load_history_end')) * 1000);

            $tempEnd = $end;
            $dayStep = 1; // Previous value 9. 1 for one day history loading

            while ($tempEnd <= $end){

                echo "<br><br>" . $q . " step: ";
                $tempEnd = $start + 86400000 * $dayStep;
                echo "GAP: " . gmdate ("d-m-Y G:i:s", ($start / 1000)) . " - " . gmdate ("d-m-Y G:i:s", ($tempEnd / 1000)) . "<br>";


                // delete }

                // delete for ($x = 0; $x <= 40; $x += 10) // 3 steps. 20 days each. works fine for 1h timeframe
                // delete {

                echo "<br>***************************************************Step – " . $q;

                // Time fromat: YYYY-MM-DD
                //$restEndpoint = "candles/trade:" . $timeframe . ":t" . strtoupper($tableValue->asset_name) . "/hist?limit=1000&start=" . (strtotime("2017-01-01 +0 day") * 1000) . "&end=" . (strtotime("2017-01-05 +0 day") * 1000) . "&sort=1"; //
                $restEndpoint = "candles/trade:" . $timeframe . ":t" . strtoupper($symbol) . "/hist?limit=1000&start=" . $start . "&end=" . $tempEnd . "&sort=1"; //

                // Create request and assign its result to $response variable
                // $response = $api_connection->request('GET', $restEndpoint, ['headers' => [] ]); // http://docs.guzzlephp.org/en/stable/request-options.html#http-errors
                $response = $api_connection->request('GET', $restEndpoint, ['http_errors' => false ]);

                //echo "<br>GUZZLE ERROR!: ________________________ " . $response->getStatusCode() . "<br>";
                echo "<br>GUZZLE reason: " . $response->getReasonPhrase() . "<br>";

                $body = $response->getBody(); // Get the body out of the request

                $json = json_decode($body, true);

                echo($restEndpoint);
                //dd($json);

                if ($response->getStatusCode() == 200) // Request successful
                {
                    $i = 1;
                    foreach ($json as $z) {

                        echo "loaded bar:\n";
                        echo '<pre>';
                        echo $z[0] . " ";
                        echo $i . " " . gmdate("d-m-Y G:i:s", ($z[0] / 1000));
                        echo '</pre>';
                        $i++;

                        DB::table('asset_1')->insert(array(
                            'date' => gmdate("Y-m-d G:i:s", ($z[0] / 1000)), // Date in regular format. Converted from unix timestamp
                            'time_stamp' => $z[0],
                            'open' => $z[1],
                            'close' => $z[2],
                            'high' => $z[3],
                            'low' => $z[4],
                            'volume' => $z[5],
                        ));
                    }

                    //session()->flash('notif', 'The historical data loaded successfully! ' . $i . ' candles in total. Last loaded date: ');

                    echo "The historical data loaded successfully! ' . $i . ' candles in total";

                } // if 200

                else // Request is not successful. Error code is not 200

                {
                    echo "Request error: too many requests!"; // $response->getReasonPhrase()
                }

                /** Make a staep */
                $start = $start + 86400000 * $dayStep; // 10 days step. 86400000 millesecs - 1 day
                $tempEnd = $tempEnd + 86400000 * $dayStep;
                $q++;

            } // while

    }
}
