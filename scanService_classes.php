<?php
class scanRequest
{
	public $field;//fieldType
	public $limit;//integer
	public $lower;//string
	public $minFrequency;//integer
	public $maxFrequency;//integer
	public $prefix;//string
	public $upper;//string
	public $callback;//string
	public $outputType;//output
}
class scanResponse
{
	public $term;//term
	public $error;//string
}
class output
{
	public $output;//string
}
class term
{
	public $name;//string
	public $hitCount;//string
}
class fieldType
{
	public $fieldType;//string
}
$classmap=array("scanRequest"=>"scanRequest",
"scanResponse"=>"scanResponse",
"output"=>"output",
"term"=>"term",
"fieldType"=>"fieldType");
?>
