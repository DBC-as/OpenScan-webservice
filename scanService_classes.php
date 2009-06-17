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
}
class scanResponse
{
	public $term;//term
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
"term"=>"term",
"fieldType"=>"fieldType");
?>
