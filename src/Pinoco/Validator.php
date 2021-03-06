<?php
/**
 * Pinoco: makes existing static web site dynamic transparently.
 * Copyright 2010-2011, Hisateru Tanaka <tanakahisateru@gmail.com>
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP Version 5
 *
 * @category   Framework
 * @author     Hisateru Tanaka <tanakahisateru@gmail.com>
 * @copyright  Copyright 2010-2011, Hisateru Tanaka <tanakahisateru@gmail.com>
 * @license    MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @version    0.5.2
 * @link       https://github.com/tanakahisateru/pinoco
 * @filesource
 * @package    Pinoco
 */

/**
 */
require_once dirname(__FILE__) . '/VarsList.php';

/**
 * Procedual varidation utility.
 * <code>
 * $validator = new Pinoco_Validator($data);
 * $validator->check('name')->is('not-empty')->is('max-length 255');
 * $validator->check('age')->is('not-empty')->is('integer')
 *                         ->is('>= 21', 'Adult only.');
 * if($validator->failed) {
 *   if($validator->result->name->valid) {
 *     echo $validator->result->name->message
 *   }
 *   if($validator->result->age->valid) {
 *     echo $validator->result->age->message
 *   }
 * }
 * </code>
 *
 * Builtin tests:
 *   pass, fail, empty, not-empty, max-length, min-length, in a,b,c, not-in a,b,c,
 *   numeric, integer, alpha, alpha-numeric, == n, != n, > n, >= n, < n,  <= n,
 *   match /regexp/, not-match /regexp/, email, url
 *
 * @package Pinoco
 * @property-read Pinoco_Vars $result;
 * @property-read Pinoco_Vars $errors;
 * @property-read boolean $succeeded;
 * @property-read boolean $failed;
 */
class Pinoco_Validator extends Pinoco_DynamicVars {

    protected $_tests;
    protected $_filters;
    protected $_messages;

    private $_target;
    private $_result;
    private $_errors;
    private $_values;

    /**
     * Constructor
     * @param string $target
     * @param string $message
     */
    public function __construct($target, $messages=array())
    {
        parent::__construct();
        
        $this->_tests = array();
        $this->_setupBuiltinTests();
        
        $this->_filters = array();
        $this->_setupBuiltinFilters();
        
        $this->_messages = array();
        $this->overrideErrorMessages($messages);
        
        $this->_target = $target;
        $this->_result = new Pinoco_Vars();
        $this->_errors = null;
        $this->_values = null;
    }
    
    private function _setupBuiltinTests()
    {
        // builtin testers
        $this->defineValidityTest('pass', array($this, '_testPassComplex'),
            "Valid.", true);
        $this->defineValidityTest('fail', array($this, '_testFailComplex'),
            "Invalid.", true);
        $this->defineValidityTest('empty', array($this, '_testEmptyComplex'),
            "Leave as empty.", true);
        $this->defineValidityTest('not-empty', array($this, '_testNotEmptyComplex'),
            "Reqierd.", true);
        $this->defineValidityTest('max-length', array($this, '_testMaxLength'),
            "In {param} letters.");
        $this->defineValidityTest('min-length', array($this, '_testMinLength'),
            "At least {param} letters.");
        $this->defineValidityTest('in', array($this, '_testIn'),
            "Coose in {param}.");
        $this->defineValidityTest('not-in', array($this, '_testNotIn'),
            "Choose else of {param}.");
        $this->defineValidityTest('numeric', array($this, '_testNumeric'),
            "By number.");
        $this->defineValidityTest('integer', array($this, '_testInteger'),
            "By integer number.");
        $this->defineValidityTest('alpha', array($this, '_testAlpha'),
            "Alphabet only.");
        $this->defineValidityTest('alpha-numeric', array($this, '_testAlphaNumeric'),
            "Alphabet or number.");
        $this->defineValidityTest('==', array($this, '_testEqual'),
            "Shuld equal to {param}.");
        $this->defineValidityTest('!=', array($this, '_testNotEqual'),
            "Should not equal to {param}.");
        $this->defineValidityTest('>', array($this, '_testGreaterThan'),
            "Greater than {param}.");
        $this->defineValidityTest('>=', array($this, '_testGreaterThanOrEqual'),
            "Greater than or equals to {param}.");
        $this->defineValidityTest('<', array($this, '_testLessorThan'),
            "Lessor than {param}.");
        $this->defineValidityTest('<=', array($this, '_testLessorThanOrEqual'),
            "Lessor than or equals to {param}.");
        $this->defineValidityTest('match', array($this, '_testMatch'),
            "Invalid pattern.");
        $this->defineValidityTest('not-match', array($this, '_testNotMatch'),
            "Not allowed pattern.");
        $this->defineValidityTest('email', array($this, '_testEmail'),
            "Email only.");
        $this->defineValidityTest('url', array($this, '_testUrl'),
            "URL only.");
    }
    
    private function _setupBuiltinFilters()
    {
        // builtin filters
        $this->defineFilter('trim', array($this, '_filterTrim'));
        $this->defineFilter('ltrim', array($this, '_filterLtrim'));
        $this->defineFilter('rtrim', array($this, '_filterRtrim'));
    }
    
    /**
     * Defines custom test
     * @param string $testName
     * @param callable $callback
     * @param string $message
     * @param boolean $complex
     * @return void
     */
    public function defineValidityTest($testName, $callback, $message, $complex=false)
    {
        $this->_tests[$testName] = array(
            'callback' => $callback,
            'message' => $message,
            'complex' => $complex,
        );
    }
    
    /**
     * Defines custom filter
     * @param string $filterName
     * @param callable $callback
     * @param boolean $complex
     * @return void
     */
    public function defineFilter($filterName, $callback, $complex=false)
    {
        $this->_filters[$filterName] = array(
            'callback' => $callback,
            'complex' => $complex,
        );
    }
    
    /**
     * Overrides error messages
     * @param array $messages
     * @return void
     */
    public function overrideErrorMessages($messages)
    {
        foreach($messages as $test=>$msg) {
            $this->_messages[$test] = $msg;
        }
    }
    
    /**
     * Resolve error message by test name.
     * @param string $testName
     * @return string
     */
    public function getMessageFor($testName)
    {
        if(isset($this->_messages[$testName])) {
            return $this->_messages[$testName];
        }
        else if(isset($this->_tests[$testName])) {
            return $this->_tests[$testName]['message'];
        }
        else {
            return 'not registered';
        }
    }
    
    /**
     * Check existence and fetch value at the same time.
     * (called by self and varidation context)
     * @param string $name
     * @return array
     */
    public function fetchExistenceAndValue($name)
    {
        //type check
        if($this->_target instanceof Pinoco_Vars) {
            $exists = $this->_target->has($name);
            $value = $this->_target->get($name);
        }
        else if($this->_target instanceof Pinoco_List) {
            $exists = intval($field) < $this->_target->count();
            $value = $exists ? $this->_target[$name] : null;
        }
        else if(is_array($this->_target)) {
            $exists = isset($this->_target[$name]);
            $value = $exists ? $this->_target[$name] : null;
        }
        else if(is_object($this->_target)) {
            $exists = isset($this->_target->$name);
            $value = $exists ? $this->_target->$name : null;
        }
        else {
            return null;
        }
        return array($exists, $value);
    }
    
    /**
     * Executes validation test.
     * (called by varidation context)
     * @param string $field
     * @param boolean $filtered
     * @param mixed $filteredValue
     * @param string $testName
     * @param array $param
     * @return array
     */
    public function execValidityTest($field, $filtered, $filteredValue, $testName, $param)
    {
        $this->_errors = null;
        $this->_values = null;
        if(isset($this->_tests[$testName])) {
            $callback = $this->_tests[$testName]['callback'];
            $complex = $this->_tests[$testName]['complex'];
            $params = array($param);
        }
        else if(is_callable($testName)) {
            $callback = $testName;
            $complex = false;
            $params = $param ? explode(' ', $param) : array();
        }
        else {
            // test method not registered
            return array(false, null);
        }
        
        // fetch
        if($filtered) {
            $exists = true;
            $value = $filteredValue;
        }
        else {
            if(($r = $this->fetchExistenceAndValue($field)) === null) {
                return array(false, null);
            }
            list($exists, $value) = $r;
        }
        
        if($complex) {
            // complex test: full information presented
            //               and should be checked if empty or not
            $args = array($this->_target, $field, $exists, $value);
        }
        else {
            // simple test: empty always success
            if(!$exists || empty($value) && !($value === "0" || $value === 0 || $value === false)) {
                return array(true, $value);
            }
            $args = array($value);
        }
        foreach($params as $p) {
            $args[] = $p;
        }
        return array(call_user_func_array($callback, $args), $value);
    }
    
    /**
     * Executes filter.
     * (called by varidation context)
     * @param string $field
     * @param boolean $filtered
     * @param mixed $filteredValue
     * @param mixed $filterName
     * @param array $param
     * @return array
     */
    public function execFilter($field, $filtered, $filteredValue, $filterName, $param)
    {
        $this->_errors = null;
        $this->_values = null;
        if(isset($this->_filters[$filterName])) {
            $callback = $this->_filters[$filterName]['callback'];
            $complex = $this->_filters[$filterName]['complex'];
            $params = array($param);
        }
        else if(is_callable($filterName)) {
            $callback = $filterName;
            $complex = false;
            $params = $param ? explode(' ', $param) : array();
        }
        else {
            return array(true, null);
        }
        
        // fetch
        if($filtered) {
            $exists = true;
            $value = $filteredValue;
        }
        else {
            if(($r = $this->fetchExistenceAndValue($field)) === null) {
                return array(true, null);
            }
            list($exists, $value) = $r;
        }
        
        if($complex) {
            // complex filter: full information presented
            //               and should be checked if empty or not
            $args = array($this->_target, $field, $exists, $value);
        }
        else {
            // simple filter: empty passes through
            if(!$exists || empty($value) && !($value === "0" || $value === 0 || $value === false)) {
                return array(true, $value);
            }
            $args = array($value);
        }
        foreach($params as $p) {
            $args[] = $p;
        }
        return array(true, call_user_func_array($callback, $args));
    }
    
    /**
     * Returns independent validation context.
     * @param string $name
     * @param string $label
     * @return Pinoco_ValidatorContext
     */
    public function contextFor($name, $label=false)
    {
        return new Pinoco_ValidatorContext($this, $name, $label);
    }
    
    /**
     * Starts named property check.
     * @param string $name
     * @param string $label
     * @return Pinoco_ValidatorContext
     */
    public function check($name, $label=false)
    {
        $this->_errors = null;
        $this->_values = null;
        if(!$this->_result->has($name)) {
            $this->_result->set($name, $this->contextFor($name, $label));
        }
        return $this->_result->get($name);
    }
    
    /**
     * Clears previsous result and restarts named property check.
     * @param string $name
     * @param string $label
     * @return Pinoco_ValidatorContext
     */
    public function recheck($name, $label=false)
    {
        $this->_errors = null;
        $this->_values = null;
        $this->_result->set($name, $this->contextFor($name, $label));
        return $this->_result->get($name);
    }
    
    /**
     * Clears previsous result.
     * @param string $name
     * @return void
     */
    public function uncheck($name)
    {
        $this->_errors = null;
        $this->_values = null;
        if($this->_result->has($name)) {
            $this->_result->remove($name);
        }
    }
    
    /**
     * Exports test all results.
     * @return Pinoco_Vars
     */
    public function get_result()
    {
        return $this->_result;
    }
    
    /**
     * Exports test results only failed.
     * @return Pinoco_Vars
     */
    public function get_errors()
    {
        if($this->_errors === null) {
            $this->_errors = new Pinoco_Vars();
            foreach($this->_result->keys() as $field) {
                $result = $this->_result->get($field);
                if($result->invalid) {
                    $this->_errors->set($field, $result);
                }
            }
        }
        return $this->_errors;
    }
    
    /**
     * Exports test results only failed.
     * @return Pinoco_Vars
     */
    public function get_values()
    {
        if($this->_values === null) {
            $this->_values = new Pinoco_Vars();
            foreach($this->_result->keys() as $field) {
                $result = $this->_result->get($field);
                $this->_values->set($field, $result->value);
            }
        }
        return $this->_values;
    }
    
    /**
     * Returns which all tests succeeded or not.
     * @return boolean
     */
    public function get_valid()
    {
        return ($this->get_errors()->count() == 0);
    }
    
    /**
     * Returns which validator has one or more failed tests.
     * @return boolean
     */
    public function get_invalid()
    {
        return !$this->get_valid();
    }
    
    /////////////////////////////////////////////////////////////////////
    // builtin tests
    private function _testPassComplex($target, $name, $exists, $value)
    {
        return true;
    }
    private function _testFailComplex($target, $name, $exists, $value)
    {
        return false;
    }
    private function _testEmptyComplex($target, $name, $exists, $value)
    {
        if(!$exists || $value === null) { return true; }
        if($value === "0" || $value === 0 || $value === false) { return false; }
        return empty($value);
    }
    private function _testNotEmptyComplex($target, $name, $exists, $value)
    {
        return !$this->_testEmptyComplex($target, $name, $exists, $value);
    }
    
    private function _testMaxLength($value, $param=0)
    {
        return strlen(strval($value)) <= $param;
    }
    private function _testMinLength($value, $param=0)
    {
        return strlen(strval($value)) >= $param;
    }
    private function _testIn($value, $param='')
    {
        $as = explode(',', $param);
        foreach($as as $a) {
            if($value == trim($a)) { return true; }
        }
        return false;
    }
    private function _testNotIn($value, $param='')
    {
        return !$this->_testIn($value, $param);
    }
    private function _testNumeric($value)
    {
        return is_numeric($value);
    }
    private function _testInteger($value)
    {
        return is_integer(0 + $value);
    }
    private function _testAlpha($value)
    {
        return ctype_alpha($value);
    }
    private function _testAlphaNumeric($value)
    {
        return ctype_alnum($value);
    }
    private function _testEqual($value, $param=null)
    {
        return $value == $param;
    }
    private function _testNotEqual($value, $param=null)
    {
        return !$this->_testEqual($value, $param);
    }
    private function _testGreaterThan($value, $param=0)
    {
        return $value > $param;
    }
    private function _testGreaterThanOrEqual($value, $param=0)
    {
        return $value >= $param;
    }
    private function _testLessorThan($value, $param=0)
    {
        return $value < $param;
    }
    private function _testLessorThanOrEqual($value, $param=0)
    {
        return $value <= $param;
    }
    private function _testMatch($value, $param='/^$/')
    {
        return preg_match($param, $value);
    }
    private function _testNotMatch($value, $param='/^$/')
    {
        return !$this->_testMatch($value, $param);
    }
    private function _testEmail($value)
    {
        return preg_match('/@[A-Z0-9][A-Z0-9_-]*(\.[A-Z0-9][A-Z0-9_-]*)*$/i', $value);
    }
    private function _testUrl($value)
    {
        return preg_match('/^[A-Z]+:\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)*):?(\d+)?\/?/i', $value);
    }

    /////////////////////////////////////////////////////////////////////
    // builtin filters
    private function _filterTrim($value)
    {
        return trim($value);
    }
    private function _filterLtrim($value)
    {
        return ltrim($value);
    }
    private function _filterRtrim($value)
    {
        return rtrim($value);
    }
}

/**
 * @package Pinoco
 * @property-read string $test
 * @property-read boolean $valid
 * @property-read boolean $invalid
 * @property-read string $message
 */
class Pinoco_ValidatorContext extends Pinoco_DynamicVars {
    
    private $_validator;
    private $_name;
    private $_label;
    
    private $_filtered;
    private $_filteredValue;
    
    private $_valid;
    private $_test;
    private $_message;
    
    /**
     * Constructor
     * @param Pinoco_Validator $validator
     * @param string $name
     * @param string $label
     */
    public function __construct($validator, $name, $label=false)
    {
        parent::__construct();
        $this->_validator = $validator;
        $this->_name = $name;
        $this->_label = $label ? $label : $name;
        
        $this->_filtered = false;
        $this->_filteredValue = null;
        
        $this->_valid = true;
        $this->_test = null;
        $this->_message = null;
    }
    
    /**
     * Test target value.
     * @return mixed
     */
    public function get_value()
    {
        if($this->_filtered) {
            return $this->_filteredValue;
        }
        else {
            if(($r = $this->_validator->fetchExistenceAndValue($this->_name)) === null) {
                return null;
            }
            list($exists, $value) = $r;
            return $exists ? $value : null;
        }
    }
    
    /**
     * Failed test.
     * @return string
     */
    public function get_test()
    {
        return $this->_test;
    }
    
    /**
     * is valid or not.
     * @return boolean
     */
    public function get_valid()
    {
        return $this->_valid;
    }
    
    /**
     * inverse of valid.
     * @return boolean
     */
    public function get_invalid()
    {
        return !$this->_valid;
    }

    /**
     * Error message for the first failed check.
     * @return string
     */
    public function get_message()
    {
        return $this->_message;
    }
    
    private function buildMessage($template, $param, $value, $label)
    {
        if(is_callable($template)) {
            return call_user_func($template, $param, $value, $label);
        }
        if(is_string($template)) {
            return str_replace(
                array('{param}', '{value}', '{label}'),
                array(strval($param), strval($value), $label),
                $template
            );
        }
    }
    
    /**
     * Check the field by specified test.
     * @param string $test
     * @param string $message
     * @return Pinoco_ValidatorContext
     */
    public function is($test, $message=false)
    {
        if(!$this->_valid) {
            return $this;
        }
        $param = explode(' ', trim($test));
        $testName = array_shift($param);
        $param = count($param) == 0 ? null : implode(' ', $param);
        list($result, $value) = $this->_validator->execValidityTest(
            $this->_name, $this->_filtered, $this->_filteredValue, $testName, $param
        );
        if(!$result) {
            $this->_test = $test;
            $this->_valid = false;
            $template = $message ? $message : $this->_validator->getMessageFor($testName);
            $this->_message = $this->buildMessage($template, $param, $value, $this->_label);
        }
        return $this;
    }
    
    /**
     * Converts value format for trailing statements.
     * @param mixed $filter
     * @return Pinoco_ValidatorContext
     */
    public function filter($filter)
    {
        if(!$this->_valid) {
            return $this;
        }
        $param = explode(' ', trim($filter));
        $filterName = array_shift($param);
        $param = count($param) == 0 ? null : implode(' ', $param);
        list($filtered, $value) = $this->_validator->execFilter(
            $this->_name, $this->_filtered, $this->_filteredValue, $filterName, $param
        );
        if($filtered) {
            $this->_filtered = $this->_filtered || true;
            $this->_filteredValue = $value;
        }
        return $this;
    }
}
