<?php
namespace FResidence\exception;

class IllegalArgumentException extends FResidenceException
{
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
