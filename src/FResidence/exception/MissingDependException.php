<?php
namespace FResidence\exception;

class MissingDependException extends FResidenceException
{
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
