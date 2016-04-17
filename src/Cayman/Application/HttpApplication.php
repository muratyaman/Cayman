<?php
/**
 * File for HTTP Application class
 */

namespace Cayman\Application;

use Cayman\Application;
use Cayman\Exception;
use Cayman\AppInput;
use Cayman\AppOutput;

/**
 * Class for HTTP Application
 * 
 */
class HttpApplication extends Application
{
    
    /**
     * Load input
     * @param array $serverData
     * @param array $inputData
     * @return Input
     */
    function loadInput(array $serverData = [], array $inputData = [])
    {
        $input = new AppInput();
        $input->setData($inputData);
        
        $apiPrefix = $this->getSettings()->application->api_prefix;
        $apiPrefix = !empty($apiPrefix) && is_string($apiPrefix) ? $apiPrefix : '';// '/api/v1'
        
        $method  = strtolower($serverData['REQUEST_METHOD']);// e.g. 'GET'
        $uri     = strtolower($serverData['REQUEST_URI']);   // e.g. '/api/v1/account/user/index'
        if ($uri == '/') {
            $uri = $apiPrefix . '/index/index';//default entry point
        }
        $apiPrefixLen = strlen($apiPrefix);
        if (substr($uri, 0, $apiPrefixLen) == $apiPrefix) {// starts with same prefix
            $uri = substr($uri, $apiPrefixLen);//take the rest
        }
        
        do {
            // custom /module/service/action/UUID
            if ($matches   = $this->matchModuleServiceActionUuid($uri)){
                $command   = $matches['module'] . '/' . $matches['service'];
                $action    = $matches['action'];
                $contextId = $matches['uuid'];
                break;
            }
            // custom /module/service/action/INTEGER
            if ($matches   = $this->matchModuleServiceActionInt($uri)){
                $command   = $matches['module'] . '/' . $matches['service'];
                $action    = $matches['action'];
                $contextId = $matches['integer'];
                break;
            }
            // custom /service/action
            if ($matches   = $this->matchServiceAction($uri)){
                $command   = $matches['service'];
                $action    = $matches['action'];
                break;
            }
            
            switch ($method) {
                case 'get':
                    // get /module/service/UUID retrieve
                    if ($matches   = $this->matchModuleServiceUuid($uri)){
                        $command   = $matches['module'] . '/' . $matches['service'];
                        $action    = 'retrieve';
                        $contextId = $matches['uuid'];
                        break;
                    }
                    // get /module/service/INT retrieve
                    if ($matches   = $this->matchModuleServiceInt($uri)){
                        $command   = $matches['module'] . '/' . $matches['service'];
                        $action    = 'retrieve';
                        $contextId = $matches['integer'];
                        break;
                    }
                    // get custom /module/service/action
                    if ($matches = $this->matchModuleServiceAction($uri)){
                        $command = $matches['module'] . '/' . $matches['service'];
                        $action  = $matches['action'];
                        break;
                    }
                    // get custom /module/service index
                    if ($matches = $this->matchModuleService($uri)){
                        $command = $matches['module'] . '/' . $matches['service'];
                        $action  = 'index';
                        break;
                    }
                    throw new Exception('Invalid get request');
                case 'post':
                    // post custom /module/service/action
                    if ($matches = $this->matchModuleServiceAction($uri)){
                        $command = $matches['module'] . '/' . $matches['service'];
                        $action  = $matches['action'];
                        break;
                    }
                    // post /module/service create
                    if ($matches = $this->matchModuleService($uri)){
                        $command = $matches['module'] . '/' . $matches['service'];
                        $action  = 'create';
                        break;
                    }
                    throw new Exception('Invalid post request');
                case 'put':
                    // put /module/service/UUID update
                    if ($matches   = $this->matchModuleServiceUuid($uri)){
                        $command   = $matches['module'] . '/' . $matches['service'];
                        $action    = 'update';
                        $contextId = $matches['uuid'];
                        break;
                    }
                    // put /module/service/INTEGER update
                    if ($matches   = $this->matchModuleServiceInt($uri)){
                        $command   = $matches['module'] . '/' . $matches['service'];
                        $action    = 'update';
                        $contextId = $matches['integer'];
                        break;
                    }
                    throw new Exception('Invalid put request');
                case 'delete':
                    // delete /module/service/UUID
                    if ($matches   = $this->matchModuleServiceUuid($uri)){
                        $command   = $matches['module'] . '/' . $matches['service'];
                        $action    = 'delete';
                        $contextId = $matches['uuid'];
                        break;
                    }
                    // delete /module/service/INTEGER
                    if ($matches   = $this->matchModuleServiceInt($uri)){
                        $command   = $matches['module'] . '/' . $matches['service'];
                        $action    = 'delete';
                        $contextId = $matches['integer'];
                        break;
                    }
                    throw new Exception('Invalid delete request');
            }
        } while(false);//run once
        
        $input->setService($command);
        $input->setAction($action);
        
        if (isset($contextId)) {
            $input->setContextId($contextId);
        }
        
        return $input;
    }
    
    const PATTERN_BASE_UUID     = '\w{8}-\w{4}-\w{4}-\w{4}-\w{12}';// 8chars-4chars-4chars-4chars-12chars
    const PATTERN_BASE_ALPHANUM = '[a-z]+[a-z0-9]+';// starts with a letter, a word
    const PATTERN_BASE_DIGITS   = '[0-9]+';

    const PATTERN_MODULE  = '(?P<module>'  . self::PATTERN_BASE_ALPHANUM . ')';
    const PATTERN_SERVICE = '(?P<service>' . self::PATTERN_BASE_ALPHANUM . ')';
    const PATTERN_ACTION  = '(?P<action>'  . self::PATTERN_BASE_ALPHANUM . ')';
    const PATTERN_UUID    = '(?P<uuid>'    . self::PATTERN_BASE_UUID     . ')';
    const PATTERN_INTEGER = '(?P<integer>' . self::PATTERN_BASE_DIGITS   . ')';
    
    /**
     * Match /Module/Service/Action/Uuid
     * @param string $uri
     * @return array | null
     */
    protected function matchModuleServiceActionUuid($uri)
    {
        $result  = null;
        $matches = [];
        $pattern = sprintf('@^/%s/%s/%s/%s$@', self::PATTERN_MODULE, self::PATTERN_SERVICE, self::PATTERN_ACTION, self::PATTERN_UUID);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
    
    /**
     * Match /Module/Service/Uuid
     * @param string $uri
     * @return array | null
     */
    protected function matchModuleServiceUuid($uri)
    {
        $result  = null;
        $matches = [];
        $pattern = sprintf('@^/%s/%s/%s$@', self::PATTERN_MODULE, self::PATTERN_SERVICE, self::PATTERN_UUID);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
    
    /**
     * Match /Module/Service/Action
     * @param string $uri
     * @return array | null
     */
    protected function matchModuleServiceAction($uri)
    {
        $result  = null;
        $matches = [];
        $pattern = sprintf('@^/%s/%s/%s$@', self::PATTERN_MODULE, self::PATTERN_SERVICE, self::PATTERN_ACTION);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
    
    /**
     * Match /Module/Service/Action/Integer
     * @param string $uri
     * @return array | null
     */
    protected function matchModuleServiceActionInt($uri)
    {
        $result  = null;
        $matches = [];        
        $pattern = sprintf('@^/%s/%s/%s/%s$@', self::PATTERN_MODULE, self::PATTERN_SERVICE, self::PATTERN_ACTION, self::PATTERN_INTEGER);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
    
    /**
     * Match /Module/Service/Integer
     * @param string $uri
     * @return array | null
     */
    protected function matchModuleServiceInt($uri)
    {
        $result  = null;
        $matches = [];        
        $pattern = sprintf('@^/%s/%s/%s$@', self::PATTERN_MODULE, self::PATTERN_SERVICE, self::PATTERN_INTEGER);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
    
    /**
     * Match /Module/Service - assume default action
     * @param string $uri
     * @return array | null
     */
    protected function matchModuleService($uri)
    {
        $result  = null;
        $matches = [];        
        $pattern = sprintf('@^/%s/%s$@', self::PATTERN_MODULE, self::PATTERN_SERVICE);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
    
    /**
     * Match /Service/Action
     * @param string $uri
     * @return array | null
     */
    protected function matchServiceAction($uri)
    {
        $result  = null;
        $matches = [];
        $pattern = sprintf('@^/%s/%s$@', self::PATTERN_SERVICE, self::PATTERN_ACTION);
        if (preg_match($pattern, $uri, $matches)) {
            $result = $matches;
        }
        return $result;
    }
}