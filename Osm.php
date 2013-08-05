<?php

class OSM 
{
    protected $nodes = array();
    protected $ways = array();
    protected $relations = array();
    
    public function __construct($nodes, $ways, $relations) {
        $this->nodes = $nodes;
        $this->ways = $ways;
        $this->relations = $relations;
    }
    
    public function getNodes() {
        return $this->nodes;
    }

    public function getRelations() {
        return $this->relations;
    }

    public function getWays() {
        return $this->ways;
    }
}


class AbstractNode {

    public $id;
    public $visible;
    public $timestamp;
    public $version;
    public $changeset;
    public $user;
    public $uid;

    function __construct(&$xmlObj){
        $this->__init($xmlObj);
    }
    
    
    private function __init(&$xmlObj) {
        
        foreach($xmlObj->attributes() as $k=>$v) {
            $this->{$k} = (string)$v;
        }
        
        foreach($xmlObj->children() as $k=>$child) {
            switch($child->getName()) {
                case 'tag' :
                    $this->tags[] = new Tag($child);                    
                break;
                
                case 'nd' :
                    $this->nds[] = new Nd($child);
                break;
                
                case 'member' :
                    $this->members[] = new Member($child);
                break;
            }
        }
    }
}


class Node extends AbstractNode {

    public $lat;
    public $lon;
    public $tags;
    
    public function __construct($xmlObj) {        
        parent::__construct($xmlObj);
    }

    /*
    public function equals($obj) {
        if(is_null($obj)) return false;
        
        if (getClass() != $obj->getClass()) {
            return false;
        }
        
        final $other = ($this) obj;
        return true;
    }
    */
    
    public function hashCode() 
    {
        $hash = 5;
        $hash = 17 * $hash + (($this->id != null) ? $this->hashCode($this->id) : 0);
        return $hash;
    }
}



class Relation extends AbstractNode 
{

    public $members;
    public $tags;

    public function __construct(&$xmlObj) {
        parent::__construct($xmlObj);
    }
}


class Way extends AbstractNode {

    public $nds;
    public $tags;

    public function __construct($xmlObj) {
        parent::__construct($xmlObj);
    }

    public function isHighway() {
        foreach($this->tags as $k=>$tag) {
            if($tag->highway)
                return true;
        }
        return false;
    }
}



abstract class ChildNode
{
    public function __construct(&$memberObj) {
        foreach($memberObj->attributes() as $key=>$val) {
            $this->{$key} = (string)$val;
        }
    }
}


class Member extends ChildNode {

    public $type;
    public $ref;
    public $role;

    public function __construct(&$memberObj) {
        parent::__construct($memberObj);
    }
}


class Nd extends ChildNode {
    
    public $ref;
    
    public function __construct(&$ndObj) {
        parent::__construct($ndObj);
    }
}


class Tag extends ChildNode {

    public $k;
    public $v;

    public function __construct(&$tagObj) {
        parent::__construct($tagObj);
    }
}