<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once 'helper.php';

$checkpoint = 1000;

$usage = "Usage: php citystatezip2solr.php --file=US.csv [--checkpoint=10000]\n\n";
$file = $test = '';

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
        
        case '--file':
            $file = $value;
        break;
        
        default:
            die($usage);
        
    }
}

if($file)
{
    $doc = null;
    $solrDocs = array();
    $line = (double)1;
    $solr = new SolrClient(array('hostname' => 'localhost', 'port' => '8983'));
    $usZips = array();
    
    ini_set('auto_detect_line_endings',TRUE);
    
    /**
     * Store individual zip to solr
     * and store entier sets in $usZip array();
     **/
    $fp = fopen($file,'r');
    while ( ($data = fgetcsv($fp) ) !== FALSE ) {
        $solrDoc = new SolrInputDocument();
            
        if($line == 1) {
            foreach($data as $k=>$v) {
                $doc->$k = $v;
            }
        } else {
            $solrDoc->addField('id', 'postalcode-'.$data[0]);
            foreach($data as $k=>$v) {
                //printr($doc);
                if($doc->$k != 'latitude' && $doc->$k != 'longitude' && $doc->$k != 'location')
                    $solrDoc->addField($doc->$k, $v);
                
                $usZips[$data[3]][$data[1]][$data[0]][$doc->$k] = $v;
                
            }
            
            $solrDoc->addField('location', "{$data[5]},{$data[6]}");
            //printr($solrDoc->toArray());
            try{
                $solrRes = $solr->addDocument($solrDoc);
                //printr( $solrRes );
                $solrRes = null;
            } catch(SolrClientException $e) {
                //print_r($solrDocs->toArray());      
                echo "\n\n Error Fail to process: \n";
                printr( $e->getMessage());
                echo "\n";
                echo "\n---------------------\n\n\n";
            }
            
            //$solrDocs[] = $solrDoc;            
        }
        
        echo $line++;
    }
    
    
    #calculate State Centerpoint and push it to solr
    $cityStateDocs = array();
    foreach($usZips as $state=>$cities) {
        
        $stLat = $stLon = 0;
        foreach($cities as $city=>$zips) {
            $cityLat = $cityLon = 0;
            $zips = array_values($zips);
            
            foreach($zips as $k=>$value) {
                $cityLat += $value['latitude'];
                $cityLon += $value['longitude'];
            }
            
            $cityLat = $cityLat / count($zips);
            $cityLon = $cityLon / count($zips);
            
            $solrCityDoc = new SolrInputDocument();
            $solrCityDoc->addField('id', 'city-'.slugify($city).'-'.count($zips[0]));
            $solrCityDoc->addField('city',$city);
            $solrCityDoc->addField('state',$state);
            $solrCityDoc->addField('location',"{$cityLat},{$cityLon}");
            $cityStateDocs[] = $solrCityDoc;
            
            //$solrRes = $solr->addDocument($solrCityDoc);
            //echo $solrRes->getHttpStatusMessage() . "\n";
            $stLat += $cityLat;
            $stLon += $cityLon;
        }
        
        $stLat = $stLat / count($cities);
        $stLon = $stLon / count($cities);
        
        $solrStateDoc = new SolrInputDocument();
        $solrStateDoc->addField('id', 'state-'.$state.'-'.count($cities));
        $solrStateDoc->addField('state',$state);
        $solrStateDoc->addField('location',"{$stLat},{$stLon}");
        $cityStateDocs[] = $solrStateDoc;
        //$solrRes = $solr->addDocument($solrStateDoc);
        //echo $solrRes->getHttpStatusMessage() . "\n";
    }
    
    try{
        $solrRes = $solr->addDocuments($cityStateDocs);
        print_r( $solrRes->getHttpStatusMessage() );
        $solrRes = null;
    } catch(SolrClientException $e) {
        //print_r($solrDocs->toArray());      
        echo "\n\n Error Fail to process: \n";
        printr( $e->getMessage());
        echo "\n";
        echo "\n---------------------\n\n\n";
    }
    
    ini_set('auto_detect_line_endings',FALSE);
    fclose($fp);
    
}

echo "\nProcess finished\n\n";
exit;

?>