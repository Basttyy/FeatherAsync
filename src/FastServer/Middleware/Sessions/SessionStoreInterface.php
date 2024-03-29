<?php
namespace Basttyy\FastServer\Middleware\Sessions;

interface SessionStoreInterface {
  
  public function find( $sid, $fn );
  public function save( $sid, $session, $fn );
  public function destroy( $sid, $fn );
}
