<?php

date_default_timezone_set('Etc/GMT-8');
if (count($argv) < 1){
    exit('file no pass');
}

$file = $argv[1];

include __DIR__.'/../lib/Parser.php';

$result = [];

function parse($file){
    global $result;
    if (is_dir($file)){
        $dir = dir($file);
        while(false != ($entry = $dir->read())){
            if ($entry == '.' || $entry == '..') continue;
            $path = $dir->path;
            if (is_dir($path.'/'.$entry)){
                //parse($path.'/'.$entry);
            }elseif(is_file($path.'/'.$entry) && substr($entry, -4) == '.php'){
                $ret = (new Parser(file_get_contents($path.'/'.$entry)))->getFileTree();
                $result[] = [
                    'file' => $path.'/'.$entry,
                    'parse' => $ret
                ];
            }
        }
    }else{
        $ret = (new Parser(file_get_contents($file)))->getFileTree();
        $result[] = [
            'file' => $path.'/'.$entry,
            'parse' => $ret
        ];
    }
}

parse($file);

print_r($result);
