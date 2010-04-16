<?php
/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
*/


require_once("OLS_class_lib/webServiceServer_class.php");
// required for making remote calls
// require_once("OLS_class_lib/curl_class.php");
// required for handling xml
require_once("OLS_class_lib/xml_func_class.php");
// required for caching
//require_once("OLS_class_lib/cache_client_class.php");


class openscan_server extends webServiceServer {
  private static $xsd=null;
  public static $fields=array();

  public function __construct($inifile,$schema=null) {
    //    cache::flush();
    if( $schema ) {
      if( self::$xsd==null ) {
          $dom=new DOMDocument();
          $dom->load($schema);
          self::$xsd=new DOMXPath($dom);
      }
    }
    
    parent::__construct($inifile); 
    if( empty($fields) )
      self::$fields=$this->config->get_value("fields","setup");
   
  }

  public function __destruct() {
    $this->watch->stop("base_class");
  }
 
  public function openScan($params) {
    if (!$this->aaa->has_right("openscan", 500))
      die("authentication_error");

    $terms=$this->terms($params);
   
    $this->watch->start("parse");
    if( $terms )
      foreach( $terms as $term ) {
        $response_xmlobj->scanResponse->_namespace="http://oss.dbc.dk/ns/openscan";
        $response_xmlobj->scanResponse->_value->term[]=$term;
      }
    $this->watch->stop("parse");
    
    $this->watch->start("base_class");
    return $response_xmlobj;
  }

   /** \brief Echos config-settings
   *
   */
  public function show_info() {
    echo "<pre>";
    echo "version             " . $this->config->get_value("version", "setup") . "<br/>";
    echo "log                 " . $this->config->get_value("logfile", "setup") . "<br/>";
    echo "</pre>";
    die();
  }

  private function terms($params) {
    $this->watch->start("solr");
    $data = methods::openscan_request($params,$this->config);
    $this->watch->stop("solr");
    return $data;    
  }
}

$server=new openscan_server("openscan.ini");
$server->handle_request();

class methods {

  /**
     @make a cache_key
   */
  public static function cache_key($params) {
    foreach($params as $key=>$value)
      $cachekey.=$key.$value->_value;

    return $cachekey;
  }

 /** Function for handling scan-request 
   *  @param params; The request mapped to params-object
   *  @return response;an array of terms, false if something went wrong
   */
  public static function openscan_request($params,$config=null) {
    // $fields=openscan_server::$fields;
  
    // make an url for request
    if( !$query=self::get_query($params) ) {
      verbose::log(WARNING,"openScan:224::could not set query for solr");
      return false;
    }

    $url=$config->get_value("baseurl","setup").$query;
    $xml=self::get_xml($url,$statuscode);
    
    if( $statuscode != 200 )   { 
      verbose::log(FATAL,"openscanRequest::HTTP-errorcode from solr:".$statuscode);
      return false;
    }

    return self::parse_response($xml,$error);    
  }  

  /** Parse xml and map to response-array 
   *  @param xml; the xml to parse
   *  @return response; xml mapped to response-array
   */
  private static function parse_response(&$xml,&$error) {
    if( !$nodelist=self::get_nodelist($xml,$error) )
      return false;
    
    $terms=array();
    foreach( $nodelist as $node ) {
      $terms[]=self::get_term($node);
    }    
    return $terms;
  }



  private static function get_term($node) {
    $namespace="http://oss.dbc.dk/ns/openscan";

    $term->_namespace= $namespace;
    $term->_value->name->_value=xml_func::UTF8($node->getAttribute("name"));
    $term->_value->name->_namespace=$namespace;
    $term->_value->hitCount->_value=$node->nodeValue;
    $term->_value->hitCount->_namespace=$namespace;
    return $term;
  }
   

   /** Return a list of nodes holding result from autocomplete-request
   *  @param xml; The xml to get nodelist from
   *  @return nodelist; A list of nodes holding result; false if something went wrong
   */
  private static function get_nodelist(&$xml,&$error) {
    // parse the result
    $dom = new DOMDocument('1.0', 'UTF-8');
    
    if (!$dom->LoadXML($xml) ) {
      $error="get_nodelist::Could not load XML";
      return false;
    }    
    
    $xpath=new DOMXPath($dom);
    $query="/response/lst[@name='terms']/lst/int";
    $nodelist=$xpath->query($query);
    
    return $nodelist;
  }

 
  /** Parse params-object and map to query-parameters 
   *  @param params; params-object
   *  @return ret; given params mapped to url-parameters
   */
  private static function get_query($params) {
    // print_r($fields);
    //exit;
    
    $prefix="&terms.";
    // field and limit are the only required values 
    if( ! $field=$params->field->_value || ! $params->limit->_value )
      return false;

    $field=$params->field->_value;

    // field check
    if( openscan_server::$fields ) {
      $flag=false;
      foreach( openscan_server::$fields as $key=>$val)
        if( $val==$field ) {
          $flag=true;
          break;
        }      
      if( !$flag )
        die( "error in request; field not valid" );
    }
    
    $ret.= $prefix."fl=".$params->field->_value;
    $ret.= $prefix."rows=".$params->limit->_value;
    
    if( $lower=urlencode($params->lower->_value) )
      $ret.= $prefix."lower=".strtolower($lower);

    
    if( $params->minFrequency->_value )
      $ret.= $prefix."mincount=".$params->minFrequency->_value;
    
    if( $params->maxFrequency->_value )
      $ret.= $prefix."maxcount=".$params->maxFrequency->_value;
    
    if( $params->prefix->_value )
      $ret.= $prefix."prefix=".$params->prefix->_value;
    
    if( $params->upper->_value )
      $ret.= $prefix."upper=".$params->upper->_value;
    
    //always sort by index
    $ret.=$prefix."sort=index";  

    return $ret;          
  }

   /** Get xml from solr/autocomplete interface. Set statuscode for remote-call 
   *  @param url; url and query-parameters for solr-interface
   *  @param statuscode; The statuscode to be set.
   *  @return xml; The response from solr/autocomplete
   */
  private static function get_xml($url,&$statuscode) {
    // use curl class to retrieve results
    $curl=new curl();
    $curl->set_url($url);

    $xml=$curl->get();
    
    $statuscode=$curl->get_status('http_code');
    
    return $xml;
  }  
}

?>


