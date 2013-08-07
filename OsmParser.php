<?php
/**
 * Osm 2 Solr parser
 * @name OSMParser
 **/

require_once 'Osm.php';
class OSMParser 
{
    
    private $solrDoc        = null;       
    private $solr           = null;
    
    
    public function __construct() {
        $this->solr = new SolrClient(array('hostname' => 'localhost', 'port' => '8983'));
    }
    
    /**
     * @param String $path; args the command line arguments
     */
    public function parse($file)
    {
        $node = $way = $relation = null;
        $nodeCount = $wayCount = $relationCount = 0;
        
        libxml_use_internal_errors(true);
        $fp = @fopen($file, "r");
        
        if($fp) 
        {
            $i = 0;
            $xmlDocString = "";
            echo 'Start time : ' . date('Y-m-d H:i:s') . "\n";
            $start_time = microtime();
            $solrDocs = array();
            
            while (($docLine = fgets($fp)) !== false) 
            {
            
                if($i < 3) {
                    $i++;
                    continue;
                }
                
                $xmlDocString .= $docLine;
                
                $xmlDoc = simplexml_load_string($xmlDocString);
                if(!$xmlDoc) {
                    continue;
                } else {
                    $xmlDocString = "";
                    $xmlDocType = $xmlDoc->getName();
                    $this->solrDoc = new SolrInputDocument();

                    switch($xmlDocType) {
                        case 'node' :
                            $node = new Node($xmlDoc);                            
                            $this->parseNode($node);
                            if($i % CHECK_POINT == 0) print 'node-'.$node->id . "\n";
                            $nodeCount++;
                        break;
                        
                        case 'way' :
                            $way = new Way($xmlDoc);
                            $this->parseWay($way);
                            if($i % CHECK_POINT == 0) print 'way-'.$way->id . "\n";
                            $wayCount++;
                        break;
                        
                        case 'relation' :                            
                            $relation = new Relation($xmlDoc);
                            $this->parseRelation($relation);                            
                            if($i % CHECK_POINT == 0) print 'relation-'.$relation->id . "\n";
                            $relationCount++;
                        break;
                    }
                    
                    /**
                     * push document to solr
                     * @todo multiple document push;
                     **/
                     
                    $solrDocs[] = $this->solrDoc;
                    if($i % CHECK_POINT == 0) {
                        try{
                            $solrRes = $this->solr->addDocuments($solrDocs);
                            $solrDocs = array();
                            print $solrRes->getHttpStatusMessage() . "\n";
                            sleep(10);
                        } catch(SolrClientException $e) {
                            print "\n\n Error Fail to process: \n";
                            print $e->getMessage();
                            print "\n";
                            print "\n---------------------\n\n\n";
                        }
                    }
                }
                
                $i++;
                if($i % CHECK_POINT == 0) {
                    print "Time to preocesse ".CHECK_POINT." : " . microtime() - $start_time . "\n";
                    print 'Number of line processed : ' . $i . "\n";                    
                    if($nodeCount > 0) print 'Node Processed : ' . $nodeCount . "\n";
                    if($wayCount > 0) print 'Way Processed : ' . $wayCount . "\n";
                    if($relationCount > 0) print 'Relation Processed : ' . $relationCount . "\n";
                    
                    print "Offset : ". ftell($fp);
                    print "\n\n";
                }
            }
            
            
            if(!feof($fp)) {
                echo "Error: unexpected fgets() fail\n";
            }
            
            fclose($fp);
        
        }        
        
    }
    
    
    /**
     * @name parseNode
     * @param NodeObject $osmNode;
     **/
    private function parseNode(&$osmNode)
    {
    	$standAloneNode = false;
    	$amenity = NULL;
    	
        $this->solrDoc->addField( 'id', "node-{$osmNode->id}" );;
	    $this->solrDoc->addField( 'type', 1 );
	    $this->solrDoc->addField( 'location', "{$osmNode->lat},{$osmNode->lon}" );
        
        /**
         * also keep a cache of all nodes and locations
         * as we don't able to store these many locations in memory lets push it to Solr with location-{node_id}'
         **/
        //$this->pushNodeLocationInfo($osmNode);
        
        #loop over tags        
        if($osmNode->tags) $standAloneNode = $this->parseTags($osmNode->tags);
        
        return $standAloneNode;
    }
    
    
    
    /**
     * @name parseWay
     * @param WayObject $osmDoc;
     **/
    private function parseWay(&$osmDoc)
    {
        $this->solrDoc->addField( 'id', "way-{$osmDoc->id}"  );;
    	$this->solrDoc->addField( 'type', 2 );
        if($osmDoc->tags) $this->parseTags($osmDoc->tags);
        
        $this->setCenterLocationForWay($osmDoc);
        return true;
    }
    
    
    
    
    /**
     * @name parseRelation
     * @param RelationObject $osmDoc;
     **/
    private function parseRelation(&$osmDoc)
    {
        $this->solrDoc->addField( 'id', "relation-{$osmDoc->id}"  );;
    	$this->solrDoc->addField( 'type', 3 );
        if($osmDoc->tags) $this->parseTags($osmDoc->tags);
        
        $this->setCenterLocationForRelation($osmDoc);
            
        return true;
    }
    
    
    
    /**
     * @name parseTags
     * @param Array $tags;
     * @todo Find place, street name, city, states
     **/
    private function parseTags(&$tags)
    {
        $name = $county = $city = $street = $postcode = $housenumber = $cuisine = false;
        
        foreach( $tags as $tag )
    	{
    		if($tag->k == 'name') $name                             = (string) $tag->v;

            if($tag->k == 'cuisine') $cuisine                       = (string) $tag->v;
            if($tag->k == 'amenity') $amenity                       = (string) $tag->v;
            if($tag->k == 'amenity' && $tag->v == 'pub') $cuisine   = 'pub';
            if($tag->k == 'amenity' && $tag->v == 'bar') $cuisine   = 'bar';
            
            if($tag->k == 'tiger:county') $county                   = (string) $tag->v;
            if($tag->k == 'addr:county') $county                    = (string) $tag->v;
            if($tag->k == 'addr:street') $street                    = (string) $tag->v;
            if($tag->k == 'addr:city') $city                        = (string) $tag->v;
            if($tag->k == 'addr:postcode') $postcode                = (string) $tag->v;
            
            if($tag->k == 'addr:housenumber') $housenumber          = (string) $tag->v;
    	}
        
        #name
        if($name) $this->solrDoc->addField('name', trim($name));
        
        #address info
        if($housenumber) $this->solrDoc->addField('housenumber', trim($housenumber));        
        if($street) $this->solrDoc->addField('street', trim($street));
        if($postcode) $this->solrDoc->addField('postcode', trim($postcode));
    	if($city) $this->solrDoc->addField('city', trim($city));
        if($county) $this->solrDoc->addField('county', trim($county));
        
        #some extra info
        if($cuisine) $this->solrDoc->addField('cuisine', trim($cuisine));
    	if($amenity) $this->solrDoc->addField('amenity', trim($amenity));
    }
    
    
    /**
     * push location information to solr
     * @name pushNodeLocationInfo
     * @param NodeObject $node;
     **/
    private function pushNodeLocationInfo(&$node)
    {
        $id = 'location-'.(string) $node->id;        
        $location = new SolrInputDocument();
        $location->addField('id',$id);
        $location->addField('lat',"{$node->lat}");
        $location->addField('lon',"{$node->lon}");
        
        try{
            $this->solr->addDocument($location);
            $location->clear();
        } catch(SolrClientException $e) {
            print "\n\n Error Fail to process: \n";
            print $e->getMessage();
            print "\n";
            print_r($location->toArray());
            print "\n---------------------\n\n\n";
            
         }
        
    }
    
    
    /**
     * fetch location by ref and calculate Lat long for Way and push to Solr
     * finding ceter point of the way
     * @name fetchNodeLocationInfo
     * @param WayObject $way;
     * @todo Find better way to find centre point of way.
     **/
    private function setCenterLocationForWay(&$way)
    {
        if($way->nds) {
            $nodes = array();
            
        	foreach ($way->nds as $nd)
                $nodes[] = 'node-'.(string)$nd->ref;
            
            $nodelocations = $this->getDocumentsByIds($nodes);
            
            if($nodelocations)
                $location = $this->getCenterPoint($nodelocations);
                        
            $this->solrDoc->addField('location', $location['location']['lat'].','.$location['location']['lon']);
            
            if($location['corners'])
                $this->solrDoc->addField('corners',json_encode($location['corners']));
        }
    }
       
    
    
    private function setCenterLocationForRelation(&$relation)
    {
        $lat = $lon = $counter = 0;
        $shape = null;
        $ways = array();
        $nodes = array();
        $relations = array();
                
        if($relation->tags)
            $shape = $this->getShapeTypeFromTag($relation->tags);
        
        foreach($relation->members as $k=>$member) {
            if(!$member->role) $member->role = 'default';            
            
            if($member->type == 'way')
                $ways[$member->role][] = "{$member->type}-{$member->ref}";
            elseif($member->type == 'node')
                $nodes[$member->role][] = "{$member->type}-{$member->ref}";
            elseif($member->type == 'relation')
                $relations[$member->role][] = "{$member->type}-{$member->ref}";
            
                
        }
        
        if(!empty($ways)) 
        {
            $waylocations = $this->getDocumentsByIds($ways['outer']);
            
            if($waylocations) {
                $location = $this->getCenterPoint($waylocations);
            } else {
                $waylocations = $this->getDocumentsByIds($ways['inner']);
                
                if($waylocations) {
                    if($waylocations)
                        $location = $this->getCenterPoint($waylocations);
                    else
                        print $relation->id . "\n";
                } else {                
                    if(!empty($ways['default'])) {
                        $waylocations = $this->getDocumentsByIds($ways['default']);
                        
                        if($waylocations)
                            $location = $this->getCenterPoint($waylocations);
                        else{
                            $location['corners'] = array();
                            $location['location']['lat'] = 0;
                            $location['location']['lon'] = 0;
                        }                    
                    } else {
                        $location['corners'] = array();
                        $location['location']['lat'] = 0;
                        $location['location']['lon'] = 0;
                    }
                }
            }
        } else {
            /**
             * @todo find center point of relations
             * possible values [default,east,west,north,south]
             **/
            $relationLocations = $this->getLocationsOfRelations($relations);
            
            if($relationLocations)
                $location = $this->getCenterPoint($waylocations);
            else {
                $location['corners'] = array();
                $location['location']['lat'] = 0;
                $location['location']['lon'] = 0;
            }
        }
        
        $this->solrDoc->addField('location', $location['location']['lat'].','.$location['location']['lon']);
        
        if($location['corners'])
            $this->solrDoc->addField('corners',json_encode($location['corners']));
    }
    
    
    
    
    private function getLocationsOfRelations(&$relations)
    {
        $relRef = array();
        foreach($relations as $k=>$role) {
            foreach($role as $k=>$relation) {
                $relRef[] = $relation;
            }
        }
        
        return $this->getDocumentsByIds($relRef);
    }
    
    
    
    private function getCenterPoint($locations)
    {
        $corners = array();
        $lat = $lon = $counter = 0;
        
        if(!$locations)
            return array('corners'=>$corners,'location' => array('lat'=>$lat,'lon'=>$lon));
        
        foreach($locations as $k=>$location) {
            $counter++;
            $corners[] = array('lat'=>$location->location_0_coordinate,'lon'=>$location->location_1_coordinate);
    		$lat = (double) $lat + (double) $location->location_0_coordinate;
    		$lon = (double) $lon + (double) $location->location_1_coordinate;
        }
            
        $locLat = (double) ($lat / $counter);
    	$locLon = (double) ($lon / $counter);
        
        return array('corners'=>$corners,'location' => array('lat'=>$locLat,'lon'=>$locLon));
    }
       
    
    private function getShapeTypeFromTag(&$tags)
    {
        foreach($tags as $k=>$tag) {
            if($tag->k == 'type') {
                return (string) $tag->v;
            }
        }
    }
    
    
    
    private function getDocumentInformationById($id)
    {
        $query = new SolrQuery();
        $query->setQuery($query);
        $query->setQuery('id:'.$id);
        $query_response = $this->solr->query($query);
        $res = $query_response->getResponse();
        return $res->response->docs[0];
    }
    
    
    public function getDocumentsByIds($ids=array())
    {
        if(count($ids) > 1) {
            if(count($ids) > 300)
                $ids = array_slice($ids, 0, 500);
            
            $inString = '("'.implode("\",\"",$ids).'")';
            
        } else
            $inString = '"'.$ids[0].'"';
            
        $query = new SolrQuery();
        $query->setQuery($query);
        $query->setRows(count($ids));
        $query->setQuery('id:'.$inString);
        try {
            $query_response = $this->solr->query($query);
            $res = $query_response->getResponse();
            if($res) {
               return $res->response->docs;
            }
        } catch(SolrClientException $e) {
            print $e->getMessage();
        }
        return false;
    }
    
}