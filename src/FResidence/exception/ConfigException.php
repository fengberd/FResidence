<?php
namespace FResidence\exception;

class ConfigException extends FResidenceException
{
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
