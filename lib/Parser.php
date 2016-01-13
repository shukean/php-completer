<?php

class Parser {

    private $_tokens = [];

    private $_functions = [];
    private $_classes = [];
    private $_constants = [];
    private $_namespace = '';

    private $_token_index = 0;

    public function __construct($code){
        if (!empty($code)){
            $this->_tokens = @token_get_all($code);
        }
    }

    private function getToken(){
        return isset($this->_tokens[$this->_token_index]) ? $this->_tokens[$this->_token_index++] : false;
    }

    private function getTokenName($token = null){
        $token = !$token ? $this->getToken() : $token;
        return is_array($token) ? token_name($token[0]) : $token;
    }

    private function getTokenValue($token = null){
        $token = !$token ? $this->getToken() : $token;
        return is_array($token) ? $token[1] : $token;
    }

    private function getExceptToken($token_name){
        $token = $this->getToken();
        if ($token == false){
            return false;
        }
        if((is_array($token) ? token_name($token[0]) : $token) == $token_name){
            return $token;
        }
        return $this->getExceptToken($token_name);
    }

    private function getCurOffset($offset){
        $index = $this->_token_index + $offset;
        return isset($this->_tokens[$index]) ? $this->_tokens[$index] : false;
    }

    private function parseFunction(){
        if ($this->getTokenName() == 'T_WHITESPACE'){
            $token = $this->getToken();
            if ($this->getTokenName($token) == 'T_STRING'){
                $func = ['name' => $token[1], 'line' => $token[2], 'namespace' => $this->_namespace, 'args' => $this->parseArgs()];
                $this->_functions[] = $func;
            }
        }
    }

    //0 class 1 abstract 2 implements 3 trait
    private function parseClass($type = 0){
        if ($this->getTokenName() == 'T_WHITESPACE'){
            $token = $this->getToken();
            if ($this->getTokenName($token) == 'T_STRING'){
                $class = ['name' => $token[1], 'line' => $token[2], 'type' => $type, 'namespace' => $this->_namespace];
                $methods = [];
                $variables = [];
                $consts = [];
                if (false == $this->getExceptToken('{')){
                    throw new Exception('except { fail');
                }
                $left_bracket = 1;
                while(false !== ($token = $this->getToken()) && $left_bracket > 0){
                    if (!is_array($token)){
                        if ($token == '{') $left_bracket ++;
                        if ($token == '}') $left_bracket --;
                        continue;
                    }

                    switch(token_name($token[0])){
                        case 'T_CLASS':
                            break 2;
                        case 'T_CONSTANT_ENCAPSED_STRING':
                            if ($this->getTokenValue($this->getCurOffset(-3)) == 'define'){
                                $this->_constants[] = [$token[1], $token[2]];
                            }
                            break;
                        case 'T_CONST':
                            $this->getToken();
                            $name_token = $this->getToken();
                            $this->getExceptToken('=');
                            $this->getToken();
                            $value_token = $this->getToken();
                            $consts[] = ['name' => $name_token[1], 'line' => $name_token[2], 'value' => $value_token[1]];
                            break;
                        case 'T_PUBLIC':
                        case 'T_PROTECTED':
                        case 'T_PRIVATE':
                            $this->getToken();
                            $token = $this->getToken();
                            $is_static = $this->getTokenName($token) == 'T_STATIC';
                            if ($is_static){
                                $this->getToken();
                                $token = $this->getToken();
                            }
                            if ($this->getTokenName($token) == 'T_FUNCTION'){
                                if ($this->getTokenName() == 'T_WHITESPACE'){
                                    $token = $this->getToken();
                                    if ($this->getTokenName($token) == 'T_STRING'){
                                        $func = ['name' => $token[1], 'line' => $token[2], 'args' => $this->parseArgs(), 'is_static' => $is_static];
                                        $methods[] = $func;
                                    }
                                }
                            }elseif($this->getTokenName($token) == 'T_VARIABLE'){
                                $variables[] = ['name' => $token[1], 'line' => $token[2], 'is_static' => $is_static];
                            }
                            break;
                    }
                }
                $class['const'] = $consts;
                $class['variable'] = $variables;
                $class['method'] = $methods;
                $this->_classes[] = $class;
            }else{
                throw new Exception('class name is not string');
            }
        }else{
            throw new Exception('class expect last token is space');
        }
    }

    private function parseArgs(){
        $args = [];
        if ($this->getTokenName() == '('){
            $tmp = '';
            while(($name = $this->getTokenValue()) != ')'){
                $tmp .= $name;
                if ($name == ',') {
                   $args[] = $tmp;
                   $tmp = '';
                }
            }
            $args[] = $tmp;
        }
        return implode('', $args);
    }

    public function getFileTree(){
        while(false !== ($token = $this->getToken())){
            if (!is_array($token)){
                continue;
            }

            switch(token_name($token[0])){
                case 'T_NAMESPACE':
                    $this->getToken();
                    $_tmp = '';
                    $token = $this->getToken();
                    while(in_array($this->getTokenName($token), ['T_STRING', 'T_NS_SEPARATOR'])){
                       $_tmp .= $token[1];
                       $token = $this->getToken();
                    }
                    $this->_namespace = $_tmp;
                    break;
                case 'T_CONSTANT_ENCAPSED_STRING':
                    if ($this->getTokenValue($this->getCurOffset(-3)) == 'define'){
                        $this->_constants[] = [$token[1], $token[2]];
                    }
                    break;
                case 'T_FUNCTION':
                    $this->parseFunction();
                    //var_dump($this->getToken());
                    break;
                case 'T_CLASS':
                    $this->parseClass(0);
                    break;
                case 'T_ABSTRACT':
                    $this->getToken();
                    $this->getToken();
                    $this->parseClass(1);
                    break;
                case 'T_INTERFACE':
                    $this->parseClass(2);
                    break;
                case 'T_TRAIT':
                    $this->parseClass(3);
                    break;
            }
        }
        return ['constant' => $this->_constants, 'function' => $this->_functions, 'class' => $this->_classes];
        //print_r($this->_constants);
        //print_r($this->_functions);
        //print_r($this->_classes);
    }


}
