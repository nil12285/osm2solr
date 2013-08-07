<?php

function vardump($data,$die=true)
{
    echo "<pre>\n";
    var_dump($data);
    echo '</pre>';        
    if($die)
        exit;
}


function printr($data,$die=true)
{
    echo "<pre>\n";
    print_r($data);
    echo '</pre>';
    if($die)
        exit;
}


function slugify($text)
{
  // replace all non letters or digits with -
  $text = preg_replace('/\W+/', '-', $text);

  // trim and lowercase
  $text = strtolower(trim($text, '-'));
  return $text;
}



