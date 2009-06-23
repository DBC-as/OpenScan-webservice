<?php

/**                                                                   
 *                                                                    
 * This file is part of OpenScan.                                 
 * Copyright © 2009, Dansk Bibliotekscenter a/s,                      
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043            
 *                                                                    
 * OpenScan is free software: you can redistribute it and/or modify 
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or          
 * (at your option) any later version.                                        
 *                                                                            
 * OpenScan is distributed in the hope that it will be useful,              
 * but WITHOUT ANY WARRANTY; without even the implied warranty of             
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the              
 * GNU Affero General Public License for more details.                        
 *                                                                            
 * You should have received a copy of the GNU Affero General Public License   
 * along with OpenSearch.  If not, see <http://www.gnu.org/licenses/>.        
*/                      

// required for making remote calls
require_once("ws_lib/curl_class.php");
// required for handling ini-file
require_once("ws_lib/inifile_class.php");
// required for class-mapping
require_once("scanService_classes.php");
// required for handling xml
require_once("ws_lib/xml_func_class.php");
// required for logging
require_once("ws_lib/verbose_class.php");


// initialize openscan_server
$server = openscan_server::get_instance("openscan.ini");
// handle the request
$server->handle_request();

/**
 * The server class for OpenScan webservice. Class is implemented as singleton instance.
 */
class openscan_server
{
  private static $instance;
  private $config;
  private $verbose;

  public static function get_instance($inifile)
  {
    if( !isset($instance) )
      $instance = new openscan_server($inifile);
    return $instance;
  }

  private function __construct($inifile)
  {
    // get cofiguration
    $this->config=new inifile($inifile);
    if( !$this->config )
      die( "could not initialize configuration" );
    // set verbose for logging
    $this->verbose= new verbose($this->config->get_value("logfile", "setup"),$this->config->get_value("verbose", "setup"));
   
    // remember to disable caching of wsdl while developing - if not you can get some VERY confusing
    // results when doing soap-requests; enable caching when in production
    ini_set('soap.wsdl_cache_enabled',0); 
  }  
 
  /**
   * Handle the request according to parameters set by client  
   */
  public function handle_request()
  {
    if( isset($_GET["q"]) )
      {
	$this->j_query($_GET["q"]); 
	return;
      }
    elseif( isset($_GET["HowRU"]) )
      {
	$this->HowRU();
	return;
      }
    elseif( isset($GLOBALS['HTTP_RAW_POST_DATA']) )
      {  	
	$this->soap_request(); 
	return;
      }       
    elseif( !empty($_SERVER['QUERY_STRING']) )
      {
        $response = $this->rest_request();
      }
    else // no valid request was made; generate an error
      {
	$this->send_error();
	return;
      }

    // if we get to this point request was REST; all other cases return.
    if( isset($response) )
      {
	$this->handle_response($response);
	return;
      }
    else
      {
	$this->send_error();
      }
  }

  /**
   * Make a response with an error
   * @param message; The message to send as error
   * @return; echoes scanResponse-object as xml.
   */
  private function send_error($message=null)
  {
    // make a nice response to be polite
    $response=new scanResponse();
    // set default message
    if( !isset($message) )
      $message = "Please give me something to scan for like: ?field=title&limit=10&lower=harry&outputType=XML";

    $response->error =xml_func::UTF8($message);
    // return message as xml
    header('Content-type:text/xml;charset=UTF-8');
    echo  xml_func::object_to_xml($response);   
  }

  /**
   * Handle the response according to outputType and callback parameters given by client  
   * @param response; the response to handle
   * @return; echoes the response
   */
  private function handle_response($response)
  { 
    // get outputtype
    $type = $_GET['outputType'];
    // callback 
    $callback = $_GET['callback'];

    // set default type (XML)
    if( empty($type) )
      $type = "XML";

    // lowercase type variable to be nice
    $type = strtolower($type);

    switch($type)
      {
      case "xml":
	header('Content-type:text/xml;charset=UTF-8');
	echo  xml_func::object_to_xml($response);
	break;
      case "json":
	if( empty($callback) )
	  echo json_encode($response);
	else
	  echo "&& ".json_encode($response)." &&";
	break;	
      default:
	$this->send_error("Please give me correct outputtype: XML or JSON");
	break;
      }       
  }

  /**
   * HowRU function makes a test request from parameters in ini-file.
   */
  protected function HowRU()
  {
    // get test parmaeters from config-file
    $testarray = $this->config->get_section("test");
    $request = new scanRequest();
    $request->limit=$testarray["limit"];    
    $request->field=$testarray["field"];
    $request->lower=$testarray["lower"];
    if( $response = $this->openScan($request) )
      echo 'great';
    else
      echo 'not too good, i got an error from openScan-function';
  }

  /**
   * J-query autocomplete plugin hardcodes searchparameter as 'q'. Make a response the J-query way
   * @param $q ; The term to scan for
   * @return nothing, echoes result for j-query
   */
  private function j_query($q)
  {
    ////////////////
    // For JQUERY //
    ////////////////
    
    // TODO it must be possible to pass parameters such as limit and field for jquery. For now they are hardcoded
    
    $url=$this->config->get_value("baseurl","setup")."&terms.fl=dc.title&terms.lower=".$q."&terms.prefix=".$q."&limit=10";
    
    $xml=$this->get_xml($url,$statuscode);
    if( $statuscode != 200 )
      {
	$this->verbose->log(FATAL,"j_query::".$statuscode );
	exit;
      }
    $nodelist=$this->get_nodelist($xml); 
    
    // iterate results
    if( $nodelist->length >= 1 )
      foreach($nodelist as $node)
	{
	  // echo the j-query way e.g name|value
	  echo $node->getAttribute('name')."|".$node->nodeValue."\n";
	}
  }

  /** Handle url-driven requests (REST)
   *  @return response; response mapped to scanResponse-object 
   */
  protected function rest_request()
  {   
    //////////////
    // FOR REST //
    //////////////
    // get the query
    $querystring =  $_SERVER['QUERY_STRING'];   
    // map the query to solr fields
    $query = $this->map_url($querystring);
    // set url
    $url = $this->config->get_value("baseurl","setup").$query;
    
    // get the xml
    $xml=$this->get_xml($url,$statuscode);
    if( $statuscode != 200 )
      {
	//TODO log
	$this->verbose->log(FATAL,"rest_request::".$statuscode );
	exit;
      }
    // map xml to object    
    $response = $this->parse_response($xml);

    return $response;   
  }

  /**
   * Use php soap-extension to handle the soaprequest  
   */
  protected function soap_request()
  {

    ///////////////////
    // FOR SOAP ///////
    ///////////////////   

    $params = array("trace"=>true, "classmap"=>$classmap);
    $soap_server = new SoapServer($this->config->get_value("wsdl","setup"),$params);
    $soap_server->setObject($this);
    $soap_server->handle();
  }

  /** Function for handling soap-request 
   *  @param request; The request mapped to scanRequest-object
   *  @return response; Response mapped to scanResponse-object, false if something went wrong
   */
  public function openScan($request)
  {
    // make an url for request
    if( !$query=$this->get_query($request) )
      {
	$this->verbose->log(WARNING,"openScan:224::could not set query for solr");
	return false;
      }

    $url=$this->config->get_value("baseurl","setup").$query;
	  
    if( !$xml=$this->get_xml($url,$statuscode) )
      return false;

    if( $statuscode != 200 )   
      { 
	// TODO log xml with verbose
	$this->verbose->log(WARNING,"openScan:234::HTTP-errorcode from solr:".$statuscode);
	return false;
      }
    
    $response=$this->parse_response($xml);    
    return $response;  
    }

  protected function callback()
  {
  }

  /** Return a list of nodes holding result from autocomplete-request
   *  @param xml; The xml to get nodelist from
   *  @return nodelist; A list of nodes holding result; false if something went wrong
   */
  private function get_nodelist(&$xml)
  {
    // parse the result
    $dom = new DOMDocument('1.0', 'UTF-8');
    
    if (!$dom->LoadXML($xml) )
    {
      $this->verbose->log(WARNING,"get_nodelist:255::Could not load XML");
      return false;
    }    
    
    $xpath=new DOMXPath($dom);
    $query="/response/lst[@name='terms']/lst/int";
    $nodelist=$xpath->query($query);
    
    return $nodelist;
  }

  /** Parse xml and map to scanResponse-object 
   *  @param xml; the xml to parse
   *  @return response; xml mapped to scanResponse object.
   */
  private function parse_response(&$xml)
  {
    $response = new scanResponse();
    if( !$nodelist=$this->get_nodelist($xml) )
      return false;
    
    foreach( $nodelist as $node )
      {
	$term = new term();
	$term->name=xml_func::UTF8($node->getAttribute("name"));
	$term->hitCount= $node->nodeValue;
	$response->term[]=$term;
      }    
    return $response;
  }


  /** Get xml from solr/autocomplete interface. Set statuscode for remote-call 
   *  @param url; url and query-parameters for solr-interface
   *  @param statuscode; The statuscode to be set.
   *  @return xml; The response from solr/autocomplete
   */
  private function get_xml($url,&$statuscode)
  {
    // use curl class to retrieve results
    $curl=new curl();
    $curl->set_url($url);
    $xml=$curl->get();
    $statuscode=$curl->get_status('http_code');
    
    return $xml;
  }

  /** Map parameters in rest-url for solr-request 
   *  e.g. field -> terms.fl, maxFrequence -> terms.maxcount etc.   
   *  @param url; The request to map
   *  @return ret; Given request mapped for solr/autocomplete interface
   */
  private function map_url(&$url)
  {
    $prefix="terms.";
    $parts = explode('&',$url);
    foreach($parts as $part)
      {
	$query = explode('=',$part);
	if( !isset($query[1]) || !isset($query[0]) )
	  continue;
	
	switch($query[0])
	  {
	  case "field":
	    $ret.="&".$prefix."fl=".$query[1];
	    break;
	  case "limit":
	    $ret.="&".$prefix."limit=".$query[1];
	    break;
	  case "lower":
	    $ret.="&".$prefix."lower=".$query[1];
	    break;
	  case "prefix":
	    $ret.="&".$prefix."prefix=".$query[1];
	    break;
	  case "maxFrequency":
	    $ret.="&".$prefix."maxcount=".$query[1];
	  break;
	  case "minFrequency":
	    $ret.="&".$prefix."mincount=".$query[1];
	    break;
	  case "upper":
	    $ret.="&".$prefix."upper=".$query[1];
	    break;
	  default:
	    break;
	    
	  }
      }
    return $ret;
  } 

  /** Parse scanRequest-object and map to query-parameters 
   *  @param request; scanRequest-object
   *  @return ret; Given request mapped to url-parameters
   */
  private function get_query($request)
  {
    $prefix="&terms.";
    // field and limit are the only required values 
    if( ! $request->field || ! $request->limit )
      return false;
    
    $ret.= $prefix."fl=".$request->field;
    $ret.= $prefix."limit=".$request->limit;
    
    if( $request->lower )
      $ret.= $prefix."lower=".$request->lower;
    
    if( $request->minFrequency )
      $ret.= $prefix."mincount=".$request->minFrequency;
    
    if( $request->maxFrequency )
      $ret.= $prefix."maxcount=".$request->maxFrequency;
    
    if( $request->prefix )
      $ret.= $prefix."prefix=".$request->prefix;
    
    if( $request->upper )
      $ret.= $prefix."upper=".$request->upper;
    
    return $ret;          
  }
 
}


?>