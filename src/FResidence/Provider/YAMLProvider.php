<?php
namespace FResidence\Provider;

use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\level\Position;

use FResidence\Main;

class YAMLProvider implements DataProvider
{
	private $config;
	
	public function __construct(Main $main)
	{
		@mkdir($main->getDataFolder());
		$this->config=new Config($main->getDataFolder()."residence.yml",Config::YAML,array(
			"Residences"=>array()));
	}
	
	public function addResidence($startpos,$endpos,$owner,$name)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$owner=strtolower($owner);
		$old=$this->config->get("Residences");
		$old[count($old).""]=array(
			"name"=>$name,
			"start"=>array(
				"x"=>(int)$startpos->getX(),
				"y"=>(int)$startpos->getY(),
				"z"=>(int)$startpos->getZ(),
				"level"=>$startpos->getLevel()->getFolderName()),
			"end"=>array(
				"x"=>(int)$endpos->getX(),
				"y"=>(int)$endpos->getY(),
				"z"=>(int)$endpos->getZ(),
				"level"=>$endpos->getLevel()->getFolderName()),
			"owner"=>$owner,
			"metadata"=>array(
				"havePermissionPlayers"=>array(),
				"permission"=>array(),
				"time"=>-1,
				"message"=>array(
					"enter"=>"欢迎来到 %name ,这里是 %owner 的领地",
					"leave"=>"你离开了 %name",
					"permission"=>"[FResidence] 你没有权限使用这块领地")));
		$this->config->set("Residences",$old);
		$this->config->save();
	}
	
	public function getResidenceMessage($res,$msgid,$default="获取数据错误")
	{
		return "§e".isset($res["metadata"]["message"][$msgid])?$res["metadata"]["message"][$msgid]:$default;
	}
	
	public function setResidenceMessage($resid,$msgid,$message)
	{
		$old=$this->config->get("Residences");
		$old[$resid]["metadata"]["message"][$msgid]=$message;
		$this->config->set("Residences",$old);
		$this->config->save();
	}
	
	public function getAllResidences()
	{
		return $this->config->get("Residences");
	}
	
	public function removeResidenceByPosition($pos)
	{
		$old=$this->config->get("Residences");
		foreach($old as $key=>$res)
		{
			if($res["start"]["level"]===$pos->getLevel()->getFolderName() && $this->in_residence($res,$pos))
			{
				unset($old[$key]);
				$this->config->set("Residences",$old);
				$this->config->save();
				unset($res,$pos,$old);
				return true;
			}
		}
		unset($res,$pos,$key,$old);
		return false;
	}
	
	public function removeResidenceByID($resid)
	{
		$old=$this->config->get("Residences");
		if(isset($old["$resid"]))
		{
			$ret=true;
		}
		else
		{
			$ret=false;
		}
		unset($old["$resid"]);
		$this->config->set("Residences",$old);
		$this->config->save();
		unset($old,$resid);
		return $ret;
	}
	
	public function removeResidenceByOwner($owner)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$owner=strtolower($owner);
		$cou=0;
		$old=$this->config->get("Residences");
		foreach($this->getAllResidences() as $key=>$res)
		{
			if($res["owner"]===$owner)
			{
				unset($old[$key]);
				$cou++;
			}
		}
		$this->config->set("Residences",$old);
		$this->config->save();
		unset($res,$pos,$key,$old);
		return $cou;
	}
	
	public function queryResidenceByPosition($pos,$lvName="")
	{
		$x=$pos->getX();
		$y=$pos->getY();
		$z=$pos->getZ();
		if($lvName=="")
		{
			$lvName=$pos->getLevel()->getFolderName();
		}
		foreach($this->getAllResidences() as $key=>$res)
		{
			if($res["start"]["level"]===$lvName)
			{
				$start=$res["start"];
				$end=$res["end"];
				if((($x<=$start["x"] && $x>=$end["x"]) || ($x>=$start["x"] && $x<=$end["x"])) && (($y<=$start["y"] && $y>=$end["y"]) || ($y>=$start["y"] && $y<=$end["y"])) && (($z<=$start["z"] && $z>=$end["z"]) || ($z>=$start["z"] && $z<=$end["z"])))
				{
					unset($res,$pos);
					return $key;
				}
				/*if($this->in_residence($res,$pos))
				{
					unset($res,$pos);
					return $key;
				}*/
			}
		}
		unset($res,$pos,$key);
		return false;
	}
	
	public function getResidenceByID($ID)
	{
		if($ID===false)
		{
			return false;
		}
		return isset($this->getAllResidences()[$ID])?$this->getAllResidences()[$ID]:false;
	}
	
	public function queryResidenceByName($name)
	{
		foreach($this->getAllResidences() as $key=>$res)
		{
			if($res["name"]===$name)
			{
				unset($res,$pos,$name);
				return $key;
			}
		}
		unset($res,$key,$name);
		return false;
	}
	
	public function queryResidencesByOwner($owner)
	{
		if($owner instanceof Player)
		{
			$owner=$owner->getName();
		}
		$owner=strtolower($owner);
		$ret=array();
		foreach($this->getAllResidences() as $key=>$res)
		{
			if($res["owner"]===$owner)
			{
				$ret["$key"]=$res;
			}
		}
		unset($res,$pos,$key);
		return $ret;
	}
	
	public function getConfig()
	{
		return $this->config;
	}
	
	public function reload()
	{
		@mkdir($main->getDataFolder());
		$this->config=new Config($main->getDataFolder()."residence.yml",Config::YAML);
	}
	
	public function close()
	{
		unset($this);
	}
	
	public function in_residence($res,$pos)
	{
		foreach($this->getResidenceVector3Array($res["start"],$res["end"]) as $vec)
		{
			if($vec->equals($pos))
			{
				unset($res,$pos,$vec);
				return true;
			}
		}
		unset($res,$pos,$vec);
		return false;
	}
	
	public function getResidenceVector3Array($pos1,$pos2)
	{
		if($pos1 instanceof Vector3)
		{
			$x1=$pos1->getX();
			$y1=$pos1->getY();
			$z1=$pos1->getZ();
		}
		else
		{
			$x1=$pos1["x"];
			$y1=$pos1["y"];
			$z1=$pos1["z"];
		}
		if($pos2 instanceof Vector3)
		{
			$x2=$pos2->getX();
			$y2=$pos2->getY();
			$z2=$pos2->getZ();
		}
		else
		{
			$x2=$pos2["x"];
			$y2=$pos2["y"];
			$z2=$pos2["z"];
		}
		$return=array();
		$lowestX=min($x1,$x2);
		$lowestY=min($y1,$y2);
		$lowestZ=min($z1,$z2);
		$highestX=max($x1,$x2);
		$highestY=max($y1,$y2);
		$highestZ=max($z1,$z2);
		for($x=$lowestX;$x<=$highestX;$x++)
		{
			for($y=$lowestY;$y<=$highestY;$y++)
			{
				for($z=$lowestZ;$z<=$highestZ;$z++)
				{
					$return[]=new Vector3($x,$y,$z);
				}
			}
		}
		unset($pos1,$pos2,$x1,$x2,$y1,$y2,$z1,$z2,$x,$y,$z,$lowestX,$lowestY,$lowestZ,$highestX,$highestY,$highestZ);
		return $return;
	}
}
?>
