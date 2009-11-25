<?php
define(ENDCODE,"zzz");
class cache_client
{
  // to avoid abuse of cache_client
  private function __construct(){}

  public static function set($obj)
  {
    $obj = array("cmd"=>"set","cache"=>$obj); 
    $ret=self::send($obj);

    return $ret;
  }

  public static function get($key)
  {
    $obj = array("cmd"=>"get","key"=>$key);
   
    if( $ret=self::send($obj) )
      return $ret->data;

    return false;  
  }

  public static function dump()
  {
    $obj = array("cmd"=>"dump");
    if( $response = self::send($obj) )
      return $response;

    return false;
  }

  public static function kill()
  {
    $obj = array("cmd"=>"kill");
    if( !self::send($obj) )
      return false;
  }

  private function send($obj)
  {
    if( ! $socket = self::get_socket() )
      return false;
    
    $write = json_encode($obj).ENDCODE;
    fwrite($socket,$write);
    
    if( ! $response=self::read($socket) )
      return false;
  
    fclose($socket);         
    
    if( $resp = json_decode($response) )
      return $resp;
    else
      return $response;   
  }

  private static function get_socket($domain='127.0.0.1', $port=5000)
  {
    if( $socket = fsockopen($domain,  $port) )
      return $socket;

    return false;
  }

 
  private static function read($socket)
  {    
    while(  $buf = fread($socket, 1024))
      {
       if( substr($buf,-3)==ENDCODE )
	 {
	   $ret.=substr($buf,0,strlen($buf)-3);
	   break;
	 }

        $ret.=$buf;      
      }

     if( $ret == "" || $ret == "na" )
       return false;

    return $ret;
  }

}


class cache_object
{
  public $key;
  public $time_to_live;
  public $timestamp;
  public $data;

  public function __construct($key,$data,$time_to_live=5)
  {
    $this->key=$key;
    $this->time_to_live=$time_to_live;
    $this->timestamp=time();
    $this->data=$data;    
  } 
 }
?>