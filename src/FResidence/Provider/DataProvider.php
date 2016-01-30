<?php
namespace FResidence\Provider;

use FResidence\Main;

interface DataProvider
{
	public function __construct(Main $main);
	public function addResidence($startpos,$endpos,$owner,$name,$level);
	
	public function getAllResidences();
	public function getResidence($resid);
	
	public function removeResidence($resid);
	public function removeResidencesByOwner($owner);
	
	public function queryResidenceByName($name);
	public function queryResidenceByPosition($pos);
	public function queryResidencesByOwner($owner);
	
	public function getConfig();
	
	public function save();
	public function close($save);
	public function reload($save);
}
