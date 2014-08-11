<?php

/*
 * This class handles parsing text for php elements such as variables, functions, 
 * and requires. It creates a map of the location of these elements in the original file
 * and can produce a new string that is either polymorphed or metamorphed
 */

class PhpSyntaxParser {

    public $function_list = array();
    public $morphed_list = array();
    private $_originalFileContent;
    public $variabe_list;
    //Set some system variables
    private $minFunctionNameLen = 6;
    private $maxFunctionNameLen = 16;
    private $minParamNameLen = 3;
    private $maxParamNameLen = 8;
    private $paramCount = 9; //Max number of params per function supported
    public $mutation_chance = .5;

    public function __construct($file, $mutation_chance) {

        if (!file_exists($file) || !is_readable($file)) {
            throw new Exception("$file Unreachable");
        }
        if (!$this->validatePhpFile($file)) {
            throw new Exception("Invalid Syntax in: {$file}");
        }
        $content = file_get_contents($file);
        $this->mutation_chance = $mutation_chance;
        $this->_originalFileContent = $content;
    }

    /**
     * Uses the command line to validate a PHP files syntax
     * before trying to parse it.
     * @param String $file
     * @return boolean
     */
    private function validatePhpFile($file) {
        $validateCmd = "php -l {$file}";
        $res = -1;
        $out = array();
        exec($validateCmd, $out, $res);
        $validated = false;
        foreach ($out as $line) {
            if (stripos($line, "No syntax errors") >= 0) {
                $validated = true;
                break;
            }
        }
        return $validated;
    }

    /**
     * Regex all the Function Names and paramerters from the
     * previously loaded, and validated, php file.
     * Supports up to 9 parameters. If you used more than that...don't.
     * 
     * $match[0] = full call
     * $match[1] = Scope
     * $match[2] function name
     * $match[3-11] Call Parameters
     * 
     */
    public function parseFunctionCalls() {
        $lines = explode("\n", $this->_originalFileContent);
        $regex = '/([a-zA-Z]{0,})[\s]{0,}function[\s]{1,}([\&]{0,}[a-zA-Z0-9,_]{1,})[\s]{0,}\(';
        for ($numParams = 0; $numParams < $this->paramCount; $numParams++) {
            $regex .= '([\&]{0,}[\$]{0,1}[a-zA-Z0-9,_]{0,}[\s]{0,}[\=]{0,}[\s]{0,}[a-zA-Z0-9,_]{0,})[\,]{0,}[\s]{0,}';
        }
        $regex .= '\)[\s,]{0,}\{/';

        foreach ($lines as $line) {
            $match = array();
            preg_match($regex, $line, $match);
            $cnt = count($match);
            if ($cnt > 0) {
                $fullCall = $match[0]; //Full Regex match
                $scope = $match[1]; //Scope of function
                $funcName = $match[2]; //Function name
                $funcInfo = array(
                    "scope" => $scope,
                    "full_call" => $fullCall
                );

                $callParams = array();
                for ($s = 3; $s < count($match); $s++) {
                    if ($match[$s] == "") {
                        continue;
                    }
                    $paramString = $match[$s];
                    $default = $this->paramDefault($paramString);
                    if (isset($default[1])) {
                        $callParams[] = array("name" => $default[0], "default" => $default[1]);
                    } else {
                        $callParams[] = array("name" => $default[0], "default" => 'required');
                    }
                }
                $funcInfo["parameters"] = $callParams;
                $codeBlock = trim($this->identifyFunctionBlock($fullCall));
                $funcInfo['code_block'] = $codeBlock;
                $this->function_list[$funcName] = $funcInfo;
            }
        }
    }

    public function getFunctionNames() {
        if (empty($this->function_list)) {
            throw new Exception("No Functions Parsed");
        }
        return array_keys($this->function_list);
    }

    public function paramDefault($paramString) {
        if (strpos($paramString, "=") === false) {
            return array(trim($paramString));
        }
        $parts = explode("=", $paramString);
        return array(trim($parts[0]), trim($parts[1]));
    }

    public function morphFunction($funcName) {
        if (empty($this->function_list)) {
            throw new Execption("No Functions to rename");
        }
        $orig_func = $this->function_list[$funcName];
        $new_func = array();
        $new_func['code_block'] = $orig_func['code_block'];

        //Randomize function name and parameters. Then make sure to rename the parmater
        //Uses in the code block as well.
        $new_func_name = $this->randomizeName($this->minFunctionNameLen, $this->maxFunctionNameLen);
        print "Function {$funcName} renamed to: {$new_func_name}\n";
        $new_params = array();
        if (!empty($orig_func['parameters'])) {
            foreach ($orig_func['parameters'] as $k => $v) {
                $new_param_name = "$" . $this->randomizeName($this->minParamNameLen, $this->maxParamNameLen);
                $new_func['code_block'] = str_replace($v['name'], $new_param_name, $new_func['code_block']);
                $new_params[] = array("name" => $new_param_name, "default" => $v['default']);
            }
        }

        $variables = $this->getVarsFromCodeBlock($orig_func['code_block']);
        $new_func['scope'] = $orig_func['scope'];
        $new_func['parameters'] = $new_params;
        $new_call = $this->callFromFuncArray($new_func_name, $new_func);
        print "New call: {$new_call}\n";
        foreach ($variables as $chng) {
            $new_param_name = "$" . $this->randomizeName($this->minParamNameLen, $this->maxParamNameLen);
            $new_func['code_block'] = str_replace($chng, $new_param_name, $new_func['code_block']);
        }
        print "\n".$new_func['code_block']."\n";
        print "\n";
        $this->function_list[$funcName]['morphed_to'] = $new_func_name;
        $this->morphed_list[$new_func_name] = $new_func;
    }

    public function callFromFuncArray($funcName, $funcArray) {
        $new_call = $funcArray['scope'] . " function " . $funcName . "(";
        $paramStr = "";
        foreach ($funcArray['parameters'] as $param) {
            if ($param['default'] == 'required') {
                $paramStr .= $param['name'] . ",";
            } else {
                $paramStr .= $param['name'] . " = " . $param['default'];
            }
        }
        $cleanStr = substr($paramStr, 0, strlen($paramStr) - 2); //Remove last , from param string
        $new_call .= $cleanStr . "){";
        return $new_call;
    }

    public function identifyFunctionBlock($functionCallString) {
        $stack = array();
        $code = "";
        $offset = strpos($this->_originalFileContent, $functionCallString);
        if ($offset === false) {
            throw new Exception("No Definition matching: {$functionCallString}");
        }
        $start = $offset + strlen($functionCallString);
        $substring = substr($this->_originalFileContent, $start);
        for ($i = 0; $i < strlen($substring); $i++) {

            $chr = $substring[$i];
            if ($chr == "{") {
                //Start of inner block
                array_push($stack, $chr);
                $code .= $chr;
            } else if ($chr == "}" && !empty($stack)) {
                //Close of inner block
                array_pop($stack);
                $code .= $chr;
            } else if ($chr == "}" && empty($stack)) {
                $i = strlen($substring) + 1;
            } else {
                $code .= $chr;
            }
        }
        return $code;
    }

    public function parseVariableAssignment($str = null, $thisReq) {
        $fish = $str;
        $new = $thisReq;
    }

    public function randomizeName($min, $max) {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i",
            "j", "k", "l", "m", "n", "o", "p", "q", "r",
            "t", "u", "v", "w", "x", "y", "z",
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9
        );
        $i = 0;
        $str = "";
        $randLen = rand($min, $max);
        $first = rand(0, 25); //First char must be alpha
        $str .= $chars[$first];
        for ($i = 1; $i < $randLen; $i++) {
            $randspot = array_rand($chars);

            $chr = $chars[$randspot];
            $str .= $chr;
        }
        return $str;
    }

    public function invertBooleanChecks($str) {
        $fish = $str;
    }

    public function getVarsFromCodeBlock($codeBlock) {
        print "Finding Variables in \n" . $codeBlock . "\n";
        $lines = explode("\n", $codeBlock);
        $varAssignRegex = "/[\s]{0,}([$][a-zA-Z,_]{1,}[a-zA-Z,_,0-9]{0,})[\s]{0,}[.]{0,}[=]{1,}[\s]{0,}([\d,a-zA-z,_,$]{0,}[\d,a-zA-z,_,$]{0,})/";
        $functionVars = array();

        $varParts = array();
        preg_match_all($varAssignRegex, $codeBlock, $varParts, PREG_SET_ORDER);

        foreach ($varParts as $call) {
            $functionVars[] = $call[1];
        }
        return $functionVars;
    }

    public function getFunctionDetails($functionName) {
        return @$this->function_list[$functionName];
    }

}
