<?php
/* This class is part of the XP framework
 *
 * $Id$
 */

  uses('webservices.rest.server.routing.RestRoute', 'scriptlet.Preference');

  /**
   * Abstract base class
   *
   */
  class AbstractRestRouter extends Object {
    protected $routes= array();
    
    /**
     * Configure router. Template method - overwrite and implement in subclasses!
     * 
     * @param  string setup
     * @param  string base The base URL
     */
    public function configure($setup, $base= '') {
    }

    /**
     * Add a route
     *
     * @param   webservices.rest.server.routing.RestRoute route
     * @return  webservices.rest.server.routing.RestRoute The added route
     */
    public function addRoute(RestRoute $route) {
      $verb= $route->getVerb();
      if (!isset($this->routes[$verb])) $this->routes[$verb]= array();
      $this->routes[$verb][]= $route;
      return $route;
    }

    /**
     * Returns all routes
     *
     * @return  webservices.rest.server.routing.RestRoute[]
     */
    public function allRoutes() {
      $r= array();
      foreach ($this->routes as $verb => $routes) {
        $r= array_merge($r, $routes);
      }
      return $r;
    }

    /**
     * Return routes for given request and response
     * 
     * @param   string verb
     * @param   string path
     * @param   string type The Content-Type, or NULL
     * @param   scriptlet.Preference accept the "Accept" header's contents
     * @param   string[] supported
     * @return  [:var]
     */
    public function targetsFor($verb, $path, $type, Preference $accept, array $supported= array()) {
      if (!isset($this->routes[$verb])) return array();   // Short-circuit

      // Figure out matching routes
      $path= rtrim($path, '/');
      $matching= $order= array();
      foreach ($this->routes[$verb] as $route) {
        if (!preg_match($route->getPattern(), $path, $segments)) continue;

        // Check input type if specified by client
        if (NULL !== $type) {
          $pref= new Preference($route->getAccepts('*/*'));
          if (NULL === ($input= $pref->match(array($type)))) continue;
          $q= $pref->qualityOf($input, 6);
        } else {
          $input= NULL;
          $q= 0.0;
        }

        // Check output type
        if (NULL === ($output= $accept->match($route->getProduces($supported)))) continue;

        // Found possible candidate
        $matching[]= array(
          'target'   => $route->getTarget(), 
          'segments' => $segments,
          'input'    => $input,
          'output'   => $output
        );
        $order[sizeof($matching)- 1]= $q + $accept->qualityOf($output, 6);
      }

      // Sort by quality
      arsort($order, SORT_NUMERIC);
      $return= array();
      foreach ($order as $offset => $q) {
        $return[]= $matching[$offset];
      }
      return $return;
    }

    /**
     * Creates a string representation
     *
     * @return  string
     */
    public function toString() {
      return $this->getClassName().'@'.xp::stringOf($this->routes);
    }
  }
?>
