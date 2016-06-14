<?php
try
{
    // included for debugging purposes - should be removed in any production code
    ini_set('display_errors', 1);
    date_default_timezone_set("UTC");

    // include the mongodb library
    require 'vendor/autoload.php';


    // connect to the database. replace "mongo-example.continental" with where you imported the sample dataset
    $manager = new MongoDB\Driver\Manager("mongodb://web:yourpasswordgoeshere@10.99.0.78:27017/mongo-example");
    $collection = new MongoDB\Collection($manager, "mongo-example.continental");


    //filter - conditions to limit search results
    $filter = null;
    //limit - optional limit for aggregation results
    $limit = 10000;
    //put_id - whether to add ID to values or not.
    $put_id = true;
    //id - id of requested top section. Like name of continent - "Europe" in this example. 
    $id = "";
    $sort_field = "sum";
    //group_field - field for aggregation grouping, by defult at first we want results grouped by "continent".
    $group_field = "continent";


    //Case if section id is passed along request. This is where we read our 
    //custom made ID and then understand if this is first level of drilldown or 
    //second one. Based on the level of drilldown we set group_field and other 
    //parameters as needed.
    if(isset($_GET["id"]) && $_GET["id"]) {
        $id = $_GET["id"];
        $parts = explode("_",$id);
        $c = count($parts);
        if($c == 1) {
            $group_field = "country";
            $filter = ["continent" => $id];
        } else if($c == 2) {
            $group_field = "name";
            $filter = ["continent" => $parts[0], "country" => $parts[1]];
            //This is our last drilldown level, so we won't set ID to retrieved values:
            $put_id = null;
        }
    }


    // execute the aggregation query against the MongoDB database.
    $queryResult = $collection->aggregate([
        [ '$match' => (object)$filter ],
        [ '$group' => [
            //group by needed field
            '_id' => "$".$group_field,
            //count each group records:
            'count' => [ '$sum' => 1 ],
            'sum' => [ '$sum' => '$value' ]
        ] ],
        //sort -1 means descending, 1 - ascending. 
        [ '$sort' => [ $sort_field => -1 ] ],
        [ '$limit' => $limit ]
    ]);

    //Now build the data in format that is supported by PieChart
    $chartValues = [];
    $count = 0;

    //It is possible to add subvalues to each record, 
    //but in this example we will make piechart to ask for subvalues on each slice click.
    $subvalues = null;
    foreach ($queryResult as $row) {
        $r = ["name" => $row->_id, "value" => $row->sum, "subvalues" => $subvalues];
        //Putting "ID" in chartValues, means that PieChart will ask for subvalues. 
        if($put_id) {
            //Let's construct our own custom ID:
            $val_id = $id ? $id . "_" . $row->_id : $row->_id;  
            $r["id"] = $val_id;
        }   
     
        $chartValues[] = $r;
        $count++;
    }
    
    $chartResult = [
        'subvalues' => $chartValues
    ];


    //Use cache control:
    //header("Cache-Control: no-cache");
    header("Cache-Control: private, max-age=300");



    // write the result to the output
    header("Content-Type: application/json");
    print json_encode($chartResult /*, JSON_PRETTY_PRINT */);


} catch(Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    throw $e;
}
