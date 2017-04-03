<?php
namespace FResidence\exception;

class FResidenceException extends \Exception
{
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
