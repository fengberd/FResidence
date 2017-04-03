<?php
namespace FResidence\exception;

class ResidenceInstantiationException extends FResidenceException
{
	public function __construct($message)
	{
		parent::__construct($message);
	}
}
