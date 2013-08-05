<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

//ini_set('memory_limit', -1);

require_once 'helper.php';
require_once 'OsmParser.php';

$checkpoint = 1000;

$usage = "Usage: php osm2solrdoc.php --osm-file=new-jersey-latest.osm [--checkpoint=10000]\n\n";
$osmFile = $test = '';

unset($argv[0]);
foreach($argv as $arg)
{
    if(strpos($arg,'=') > 0)
        list($param, $value) = explode('=', $arg);
    else
        $param = $arg;
        
    switch($param) {
        
        case '--checkpoint':
            $checkpoint = $value;
        break;
        
        case '--osm-file':
            $osmFile = $value;
        break;
        
        case '--test':
            $test = $value;
        break;


        default:
            die($usage);
        
    }
}


$osmParser = new OSMParser();

if($test) {
    $osmParser->test();
}

define('CHECK_POINT',$checkpoint);
if($osmFile) {
    $osmParser->parse($osmFile);
}




?>