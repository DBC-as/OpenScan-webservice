<?php

require_once("OLS_class_lib/webServiceServer_class.php");
// required for making remote calls
require_once("OLS_class_lib/curl_class.php");
// required for handling xml
require_once("OLS_class_lib/xml_func_class.php");


class openscan_server extends webServiceServer 
{
 
  public function openScan($params)
  {
    $terms = methods::openscan_request($params,$this->config,$this->verbose);
 

    foreach( $terms as $term )
      {
        $response_xmlobj->scanResponse->_namespace="http://oss.dbc.dk/ns/openscan";
        $response_xmlobj->scanResponse->_value->term[]=$term;
      }

    return $response_xmlobj;
  }
}

$server=new openscan_server("openscan.ini");
$server->handle_request();

class methods
{
 /** Function for handling scan-request 
   *  @param params; The request mapped to params-object
   *  @return response;an array of terms, false if something went wrong
   */
  public static function openscan_request($params,$config=null,$verbose=null)
  {
    // make an url for request
    if( !$query=self::get_query($params) )
      {
	if( $verbose )
	  $verbose->log(WARNING,"openScan:224::could not set query for solr");
	return false;
      }

    $url=$config->get_value("baseurl","setup").$query;

    $xml=self::get_xml($url,$statuscode);
    if( $statuscode != 200 )   
      { 
	if( $verbose )
	  $verbose->log(WARNING,"openScan:234::HTTP-errorcode from solr:".$statuscode);
	return false;
      }

    return self::parse_response($xml,$error);    
  }  

  /** Parse xml and map to response-array 
   *  @param xml; the xml to parse
   *  @return response; xml mapped to response-array
   */
  private static function parse_response(&$xml,&$error)
  {
    if( !$nodelist=self::get_nodelist($xml,$error) )
	return false;
    
    // TODO make term according to new webservice
    $terms=array();
    foreach( $nodelist as $node )
      {
	$terms[]=self::get_term($node);
      }    
    return $terms;
  }

  private static function get_term($node)
  {
    $term->_namespace= "http://oss.dbc.dk/ns/openscan";
    $term->_value->name->_value=xml_func::UTF8($node->getAttribute("name"));
    $term->_value->name->_namespace="http://oss.dbc.dk/ns/openscan";
    $term->_value->hitCount->_value=$node->nodeValue;
    $term->_value->hitCount->_namespace="http://oss.dbc.dk/ns/openscan";
    return $term;
  }
   

   /** Return a list of nodes holding result from autocomplete-request
   *  @param xml; The xml to get nodelist from
   *  @return nodelist; A list of nodes holding result; false if something went wrong
   */
  private static function get_nodelist(&$xml,&$error)
  {
    // parse the result
    $dom = new DOMDocument('1.0', 'UTF-8');
    
    if (!$dom->LoadXML($xml) )
    {
      $error="get_nodelist:97::Could not load XML";
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
  private static function get_query($params)
  {
    $prefix="&terms.";
    // field and limit are the only required values 
    if( ! $params->field->_value || ! $params->limit->_value )
      return false;
    
    $ret.= $prefix."fl=".$params->field->_value;
    $ret.= $prefix."rows=".$params->limit->_value;
    
    if( $params->lower->_value )
      $ret.= $prefix."lower=".$params->lower->_value;
    
    if( $params->minFrequency->_value )
      $ret.= $prefix."mincount=".$params->minFrequency->_value;
    
    if( $params->maxFrequency->_value )
      $ret.= $prefix."maxcount=".$params->maxFrequency->_value;
    
    if( $params->prefix->_value )
      $ret.= $prefix."prefix=".$params->prefix->_value;
    
    if( $params->upper->_value )
      $ret.= $prefix."upper=".$params->upper->_value;
    
    return $ret;          
  }

   /** Get xml from solr/autocomplete interface. Set statuscode for remote-call 
   *  @param url; url and query-parameters for solr-interface
   *  @param statuscode; The statuscode to be set.
   *  @return xml; The response from solr/autocomplete
   */
  private static function get_xml($url,&$statuscode)
  {
    // use curl class to retrieve results
    $curl=new curl();
    $curl->set_url($url);
  
    $xml=$curl->get();
    
    $statuscode=$curl->get_status('http_code');
    
    return $xml;
  }  
}

?>


