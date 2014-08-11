<?php

/* 
 * This Class takes in a valid PHP file and rewrites it to avoid hash identification
 * Usage is 
 * PhpSh3llMorpher.php originalSh3ll.php <newFileName> <encode>
 */
require_once 'PhpSyntaxParser.php';
$randNameMinLen = 4;
$randNameMaxLen = 13;
$mutation_chance = 0.5;

$newFilename = "";
$chars = array("a","b","c","d","e","f","g","h","i"
        ,"j","k","l","m","n","o","p","q","r","t","u","v"
    ,"w","x","y","z",0,1,2,3,4,5,6,7,8,9);

$usage = "php ".basename(__FILE__)." {/path/to/some_sh311.php} [optional outFileName]\n";

if(!isset($argv[1])){
    print $usage;
    exit(-1);
}
$orig = $argv[1];
if(!file_exists($orig)){
    print $orig . " non-existent";
    exit(-1);
}
if(!is_readable($orig)){
    print $orig . " not readable";
    exit(-1);
}
if(!isset($argv[2])){
    $newFilename = randomizeFileName($randNameMinLen,$randNameMaxLen,$chars);
    print "Using Random name: {$newFilename}\n";
}else{
    $newFilename = $argv[2];
}

$Out = $newFilename;
$parser;
try{
    $parser = new PhpSyntaxParser($orig, $mutation_chance);
} catch (Exception $e){
    print "There was an error creating the parser:\n\t{$e->getMessage()}\n";
}

$parser->parseFunctionCalls();
$functions = $parser->getFunctionNames();
foreach ($functions as $target){
    $parser->morphFunction($target);
}
exit(0);

/*************
 * FUNCTIONS *
 *************/

/**
 * Create a random string between min and max length using the charset
 * @param int $min > 0
 * @param int $max < 20
 * @param array $charset
 * @return String
 */
function randomizeFileName($min, $max, $charset){
    $i = 0;
    $str = "";
    $randLen = rand($min, $max);
    for($i=0;$i<$randLen;$i++){
        $randspot= array_rand($charset);
    
        $chr = $charset[$randspot];
        $str .= $chr;
    }
    return $str.".php";
}