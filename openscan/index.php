
<html>
<head>
<script type="text/javascript" src="../javascript/jquery.js"></script>
<script type="text/javascript" src="../javascript/jquery.autocomplete.js"></script>
<link rel="stylesheet" type="text/css" href="autocomplete.css" />    

<script type="text/javascript">
$().ready(function() {         
   
 $("#tags").autocomplete("server.php", {                                                                                     
                max: 5,                                                                            
                highlight: false,                                                                  
                multiple: true,                                                                    
                multipleSeparator: " ",                                                            
                scroll: true,                                                                      
                scrollHeight: 300  }); 
});      

function refresh(e)
{
  // capture the key pressed.. not used here, but could be useful for eg. pressing the escape key
  if(!e)
    var e=window.event;

  var frame=document.getElementById("restframe");
  var txt=document.getElementById("rest").value;

  var src="http://vision.dbc.dk/~pjo/webservices/openscan/server.php?terms=true";
  var query="field=dc.title&limit=10&lower="+txt+"&prefix="+txt;
  var url=src+"&"+query;
  frame.innerHTML="<iframe src="+url+" width='50%' height='300px' style='padding:5px'></iframe>";
 
}

</script>
<style>
input[type=text]
{
display:block;
margin:10px;

}
</style>
</head>
<body>
<h2>Eksempel på brug af openscan web-service</h2>
<p>
Webservicen er beskrevet som wsdl. Du kan se definitionen (WSDL) <a href="openscan.wsdl">HER</a><br/>
  Du kan se skemadfinitionen (XSD) <a href="openscan.xsd">HER</a>
</p>
Dette felt bruger jquery-autocomplete plugin.
<input id="tags" type="text"/>

 Dette felt bruger rest.Her er ikke implementeret autocomplete på inputfeltet, men du kan se 
resultatet som xml i i-framen efterhånden som du taster.
<input type="text" id="rest"  onkeyup="javascript:refresh(event)" />
i-frame med REST-resultat
<div id="restframe" style="margin-bottom:20px">
  <iframe width='50%' height='300px' style='padding:5px'> 
</iframe>
</div>

Dette felt bruger soap. Tast nogle bogstaver og klik på 'GO' for at se resultatet i rammen herunder.
<form action="index.php" method="post">
  <input type="text" name="input"  <?php $val=$_POST['input'];if($val) echo "value=$val";?> style="display:inline"/>
<input type="submit" value="GO"/>
</form>
Ramme med SOAP resultat
<div style="width:50%;border:1px solid #CCCCCC;padding:5px;min-height:145px">
  <?php echo htmlspecialchars(soap_request());?>
</div>

</body>
</html>
<?php
  function soap_request()
  {
    // use php soap extension to handle the soap-call. Set trace=>true to be able to see 
    // the xml returned by soap-server
    $client=new SoapClient("openscan.wsdl",array("trace"=>true));
    // get the input-field
    $lower = $_POST['input'];
    // if no input is given soap-call makes no sense; return
    if( ! $lower )
      return;
    // prepare request.
    $Request = array();
    $Request["field"]="dc.title";
    $Request["limit"]=10;
    $Request["lower"]=$lower;
    $client->openScan($Request);
    // for example purpose we simply return the raw xml.
    return $client->__getLastResponse();
  }
?>
