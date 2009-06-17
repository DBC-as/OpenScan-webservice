<?php
// required for making request
require_once("ws_lib/curl_class.php");
// required for handling ini-file
require_once("ws_lib/inifile_class.php");
// required for class-mapping
require_once("scanService_classes.php");
// required for handling xml
require_once("ws_lib/xml_func_class.php");


// get cofiguration
$config=new inifile("openscan.ini");
if( !$config )
  die( "could not initialize configuration" );

ini_set('soap.wsdl_cache_enabled',0);   

define(BASEURL,$config->get_value("baseurl","setup"));

// get value to scan for. jquery autocomplete-plugin hardcodes the value passed as 'q'
$q=$_GET["q"];

if( $q )
  {
    ////////////////
    // For JQUERY //
    ////////////////
    
    // set url for request
    // TODO it must be possible to pass parameter such as limit and field in jquery. For now they are hardcoded
   
    $url=BASEURL."&terms.fl=dc.title&terms.lower=".$q."&terms.prefix=".$q."&limit=10";
    
    $xml=get_xml($url);
    $nodelist=get_nodelist($xml); 
   
    // iterate results
    if( $nodelist->length >= 1 )
      foreach($nodelist as $node)
	echo $node->getAttribute('name')."|".$node->nodeValue."\n";
  }


else if( isset($HTTP_RAW_POST_DATA) )
  {
    ///////////////////
    // FOR SOAP ///////
    ///////////////////
    
    $wsdl = $config->get_value("wsdl","setup");
    
    $params = array("trace"=>true, "classmap"=>$classmap);
    $server = new SoapServer($wsdl,$params);
    $server->addFunction("openScan");
    $server->handle(); 
   
  }

else
  {
    //////////////
    // FOR REST //
    //////////////

    // get the query
    $querystring =  $_SERVER['QUERY_STRING'];   
    // map the query to solr fields
    $query = map_url($querystring);
    // set url
    $url = BASEURL.$query;
   
    // get the xml
    $xml=get_xml($url);
    // map xml to object    
    $response = parse_response($xml);
    // print object as xml
    header('Content-type:text/xml;charset=UTF-8');
    echo  xml_func::object_to_xml($response);
   
  }

/** get xml from solr/autocomplete interface */
function get_xml($url)
{
 // use curl class to retrieve results
    $curl=new curl();
    $curl->set_url($url);
    $xml=$curl->get();

    return $xml;
}

/** return a list of nodes holding result from autocomplete-request*/
function get_nodelist(&$xml)
{
    // parse the result
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->LoadXML($xml);
    $xpath=new DOMXPath($dom);
    $query="/response/lst[@name='terms']/lst/int";
    $nodelist=$xpath->query($query);

    return $nodelist;
}

/** function for handling soap-request */
function openScan($request)
{
  // make an url for request
  if( ! $url=BASEURL.get_query($request) )
    {
      // TODO errorhandling
      return false;
    }

  $xml=get_xml($url);
  $response=parse_response($xml);

  return $response;  
}

/** map rest-url for solr-request */
function map_url(&$url)
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
	case "maxFrequence":
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

/** parse xml and map to scanResponse-object */
function parse_response(&$xml)
{
  $response = new scanResponse();
  $nodelist=get_nodelist($xml);

  foreach( $nodelist as $node )
    {
      $term = new term();
      $term->name=xml_func::UTF8($node->getAttribute("name"));
      $term->hitCount= $node->nodeValue;
      $response->term[]=$term;
    }

  return $response;
}

/** parse scanRequest-object and map to query-parameters */
function get_query($request)
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




?>