<?php

namespace Basttyy\FastServer;

use Basttyy\FastServer\Middleware\Router;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Basttyy\FastServer\Middleware\Cascade;
use Evenement\EventEmitter;
use Psr\Http\Message\ServerRequestInterface;

class App extends EventEmitter {
  private $router;
  private $middleware = array();
  private $settings;
  private $router_mounted = false;

  public $http;
  public $socket;
  public $loop;
  
  /**
   * Passes an array of settings to initialize Settings with.
   *
   * @param array $options the settings for the app
   * @return App
   * @author Beau Collins
   **/
  public function __construct( $options = array() ){
    
    $defaults = array(
      'view_path' => realpath('.') . '/views',
      'env' => getenv("PHLUID_ENV") ?: 'development'
    );
    $this->settings = new Settings( array_merge( $defaults, $options ) );
    $this->router = new Router();
  }
  
  public function createServer(string $uri = '127.0.0.1', HttpServer $http = null ){
    if ( $http === null ) {
      $this->socket = $socket = new SocketServer($uri);
      $this->http = $http = new HttpServer(function (ServerRequestInterface $http_request) {
        $app = $this;
        $request = new Request( $http_request );
        $response = new Response( $http_response, $request );
        $app( $request, $response );
      });
    }
    $http->on( 'request', function( $http_request, $http_response ){

      
    });
    return $this;
  }
  
  public function listen( $port, $host = '127.0.0.1' ){
    if ( !$this->http ) {
      $this->createServer();
    }
    $this->socket->listen( $port, $host );
    $this->loop->run();
    return $this;
  }
  
  /**
   * Retrieve a setting
   *
   * @param string $key 
   * @return mixed
   * @author Beau Collins
   */
  public function __get( $key ){
    return $this->settings->__get( $key );
  }
  
  /**
   * Set a setting
   *
   * @param string $key the setting name
   * @param mixed $value value to set
   * @author Beau Collins
   */
  public function __set( $key, $value ){
    return $this->settings->__set( $key, $value );
  }
  
  /**
   * An app is just a specialized middleware
   *
   * @param string $request 
   * @return void
   * @author Beau Collins
   */
  public function __invoke( $request, $response, $next = null ){
    
    $response->setOptions( array(
      'view_path' => $this->view_path,
      'default_layout' => $this->default_layout
    ) );
    
    if ( $this->router_mounted === false ) $this->inject( $this->router );
    
    $this->emit( 'start', array( $request, $response) );
    
    $middlewares = $this->middleware;
    $cascade = new Cascade( $middlewares );
    $cascade( $request, $response, function( $request, $response, $next ){
      $this->emit( 'end', array( $request, $response ) );
      $next();
    } );
    
  }

  /**
   * Adds the given middleware to the app's middleware stack. Returns $this for
   * chainable calls.
   *
   * @param Middleware $middleware 
   * @return App
   * @author Beau Collins
   */
  public function inject( $middleware ){
    if ( $middleware === $this->router ) $this->router_mounted = true;
    array_push( $this->middleware, $middleware );
    return $this;
  }
  
  /**
   * Configures a route give the HTTP request method, calls Router::route
   * returns $this for chainable calls
   *
   * Example:
   *
   *  $app->on( 'GET', '/profile/:username', function( $req, $res, $next ){
   *    $res->renderText( "Hello {$req->param('username')}");
   *  });
   *
   * @param string $method GET, POST or other HTTP method
   * @param string $path the matching path, refer to Router::route for options
   * @param invocable $closure an invocable object/function that conforms to Middleware
   * @return App
   * @author Beau Collins
   */
  public function handle( $method, $path, $filters, $action = null ){
    return $this->route( new RequestMatcher( $method, $path ), $filters, $action );
  }
  
  /**
   * Chainable call to the router's route method
   *
   * @param invocable $matcher 
   * @param invocable or array $filters 
   * @param invocable $action 
   * @return App
   * @author Beau Collins
   */
  public function route( $matcher, $filters, $action = null ){
    $this->router->route( $matcher, $filters, $action );
    return $this;
  }
  
  /**
   * Adds a route matching a "GET" request to the given $path. Returns $this so
   * it is chainable.
   *
   * @param string $path 
   * @param invocable or array $filters compatible function/invocable
   * @param invocable $closure compatible function/invocable
   * @return App
   * @author Beau Collins
   */
  public function get( $path, $filters, $action = null ){
    return $this->handle( 'GET', $path, $filters, $action );
  }
  
  /**
   * Adds a route matching a "POST" request to the given $path. Returns $this so
   * it is chainable.
   *
   * @param string $path 
   * @param invocable or array $filters compatible function/invocable
   * @param invocable $closure compatible function/invocable
   * @return App
   * @author Beau Collins
   */
  public function post( $path, $filters, $action = null ){
    return $this->handle( 'POST', $path, $filters, $action );
  }
  
  public function configure( $env, $callback = null ){
    if( func_num_args() == 2 ){
      $env = is_array( $env ) ? $env : [$env];
    } else {
      // no environment specific so always run
      $callback( $this );
      return;
    }
    
    if( in_array( $this->env, $env ) ){
      $callback( $this );
    }
  }
}
