<?php

//set encoding
mb_internal_encoding("utf-8");

//discover autoloader
$path = "vendor/autoload.php";
for($i=0; $i<4;$i++){
    $depth = "/";
    for($j=0;$j<$i;$j++){
        $depth .= "../";
    }
    $fullPath = __DIR__.$depth.$path;
    if(file_exists($fullPath)){
        require_once $fullPath;
        return;
    }
}