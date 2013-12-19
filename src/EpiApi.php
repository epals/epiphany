<?php
class EpiApi
{
  private static $instance;
  private $routes = array();
  private $regexes= array();

  const internal = 'private';
  const external = 'public';

  /**
   * get('/', 'function');
   * @name  get
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $path
   * @param mixed $callback
   */
  public function get($route, $callback, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpGet);
    if($visibility === self::external)
      getRoute()->get($route, $callback, true);
  }

  public function post($route, $callback, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpPost);
    if($visibility === self::external)
      getRoute()->post($route, $callback, true);
  }
  
  public function put($route, $callback, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpPut);
    if($visibility === self::external)
      getRoute()->put($route, $callback, true);
  }
  
  public function delete($route, $callback, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpDelete);
    if($visibility === self::external)
      getRoute()->delete($route, $callback, true);
  }

  public function invoke($route, $httpMethod = EpiRoute::httpGet, $params = array())
  {
    $routeDef = $this->getRoute($route, $httpMethod);

    // this is ugly but required if internal and external calls are to work
    $tmps = array();
    foreach($params as $type => $value)
    {
      $tmps[$type] = $GLOBALS[$type];
      $GLOBALS[$type] = $value;
    }

    $retval = call_user_func_array($routeDef['callback'], $routeDef['args']);

    // restore sanity
    foreach($tmps as $type => $value)
      $GLOBALS[$type] = $value; 

    return $retval;
  }

  /**
   * EpiApi::getRoute($route); 
   * @name  getRoute
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @method getRoute
   * @static method
   */
  public function getRoute($route, $httpMethod)
  {
    foreach($this->regexes as $ind => $regex)
    {
      if(preg_match($regex, $route, $arguments))
      {
        array_shift($arguments);
        $def = $this->routes[$ind];
        if($httpMethod != $def['httpMethod'])
        {
          continue;
        }
        else if(is_array($def['callback']) && method_exists($def['callback'][0], $def['callback'][1]))
        {
          if(Epi::getSetting('debug'))
            getDebug()->addMessage(__CLASS__, sprintf('Matched %s : %s : %s : %s', $httpMethod, $this->route, json_encode($def['callback']), json_encode($arguments)));
          return array('callback' => $def['callback'], 'args' => $arguments, 'postprocess' => true);
        }
        else if(function_exists($def['callback']))
        {
          if(Epi::getSetting('debug'))
            getDebug()->addMessage(__CLASS__, sprintf('Matched %s : %s : %s : %s', $httpMethod, $this->route, json_encode($def['callback']), json_encode($arguments)));
          return array('callback' => $def['callback'], 'args' => $arguments, 'postprocess' => true);
        }

        EpiException::raise(new EpiException('Could not call ' . json_encode($def) . " for route {$regex}"));
      }
    }
    EpiException::raise(new EpiException("Could not find route {$this->route} from {$_SERVER['REQUEST_URI']}"));
  }

  /**
   * addRoute('/', 'function', 'GET');
   * @name  addRoute
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $path
   * @param mixed $callback
   * @param mixed $method
   */
  private function addRoute($route, $callback, $method)
  {
    $this->routes[] = array('httpMethod' => $method, 'path' => $route, 'callback' => $callback);
    $this->regexes[]= "#^{$route}\$#";
  }

  /**
   * load('api_config.ini')
   * @name load
   * @author Steve Mulligan <smulligan@corp.epals.com>
   * @param string $api_config_ini_filename
   */
  public function load($file)
  {
    $file = Epi::getPath('config') . "/{$file}";
    if(!file_exists($file))
    {
      EpiException::raise(new EpiException("Config file ({$file}) does not exist"));
      break; // need to simulate same behavior if exceptions are turned off
    }

    $parsed_array = parse_ini_file($file, true);
    foreach($parsed_array as $route)
    {
      $method = strtolower($route['method']);
      $vis = strtolower($route['visibility']);
      // default visibiltiy is false.  you MUST explcitly allow external access by adding visibility = external to the ini file

      $visibility = self::internal;
      if ($vis == "external") $visibility = self::external;

      if(isset($route['class']) && isset($route['function']))
        $this->$method($route['path'], array($route['class'], $route['function']), $visibility);
      if(isset($route['instance']) && isset($route['function']))
        $this->$method($route['path'], array(new $route['instance'](), $route['function']), $visibility);
      elseif(isset($route['function']))
        $this->$method($route['path'], $route['function'], $visibility);
    }
  }

}

function getApi()
{
  static $api;
  if(!$api)
    $api = new EpiApi();

  return $api;
}
