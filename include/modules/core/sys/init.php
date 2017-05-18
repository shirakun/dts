<?php

namespace sys
{	
	//$mode决定给玩家显示哪个界面
	//$command是玩家提交的命令也是act()判断的依据
	//$db是数据库类
	//$plock文件锁的文件
	//$url如果存在，ajax将会直接跳转
	//$uip其他要传给界面的变量请写在这里
	global $mode, $command, $db, $plock, $url, $uip;
	//玩家数据池，fetch的时候先判断池里存不存在，如果有则优先调用池里的；
	//万一以后pdata_pool要变成引用呢？所以多一个origin池
	global $pdata_pool, $pdata_origin_pool; $pdata_origin_pool = $pdata_pool = array();
	
	function init()
	{
		global $gtablepre, $tablepre, $wtablepre, $room_prefix, $room_id, $moveut, $moveutmin;
		global ${$gtablepre.'user'}, ${$gtablepre.'pass'}, $___MOD_SRV;
		if (isset($_COOKIE))
		{
			$_COOKIE=gstrfilter($_COOKIE);
			foreach ($_COOKIE as $key => $value)
			{
				if ($key==$gtablepre.'user' || $key==$gtablepre.'pass')
				{
					$$key=$value;
				}
			}
		}
		
		ob_clean();
		ob_start();
		
		//数据库初始化，且只初始化1次
		global $db; 
		if (!isset($db)) $db = init_dbstuff();
		
		//游戏时间变量初始化
		date_default_timezone_set('Etc/GMT');
		global $now; $now = time() + $moveut*3600 + $moveutmin*60;   
		global $sec,$min,$hour,$day,$month,$year,$wday;
		list($sec,$min,$hour,$day,$month,$year,$wday) = explode(',',date("s,i,H,j,n,Y,w",$now));
		
		//从玩家提交的信息（一般是$_POST）里获取用户名和密码
		global $___LOCAL_INPUT__VARS__INPUT_VAR_LIST;
		if (isset($___LOCAL_INPUT__VARS__INPUT_VAR_LIST[$gtablepre.'user']))
			${$gtablepre.'user'}=$___LOCAL_INPUT__VARS__INPUT_VAR_LIST[$gtablepre.'user'];
		if (isset($___LOCAL_INPUT__VARS__INPUT_VAR_LIST[$gtablepre.'pass']))
			${$gtablepre.'pass'}=$___LOCAL_INPUT__VARS__INPUT_VAR_LIST[$gtablepre.'pass'];
			
		//进入当前用户房间判断
		$room_prefix = '';
		if (isset($___LOCAL_INPUT__VARS__INPUT_VAR_LIST['___GAME_ROOMID']))
		{
			$room_prefix = ((string)$___LOCAL_INPUT__VARS__INPUT_VAR_LIST['___GAME_ROOMID']);
		}
		else  
		{
			if (isset(${$gtablepre.'user'}))
			{
				$result = $db->query("SELECT roomid FROM {$gtablepre}users where username='".${$gtablepre.'user'}."'");
				if ($db->num_rows($result))
				{
					$rarr = $db->fetch_array($result);
					$room_prefix = $rarr['roomid'];
				}
			}
		}

		//$room_status = 0;
		$room_id = 0;
		if(strpos($room_prefix,'s')===0) $room_id = substr($room_prefix,1);
		
		//判断所在房间是否存在/是否已经关闭，如果不存在或关闭则将玩家所在房间调整为0（主游戏）
		global $gameinfo; 
		$gameinfo = NULL;
		//$room_status = 0;
		$result = $db->query("SELECT * FROM {$gtablepre}game where groomid='".$room_id."'");
		if ($db->num_rows($result))
		{
			$gameinfo = $db->fetch_array($result);
			//如果房间关闭则退出到主房间
			if ($gameinfo['groomstatus']==0) {
				$room_prefix = '';
				$room_id = 0;
				$gameinfo = NULL;
			}
			//如果房间是开启状态，但游戏在结束状态，则把房间状态设为打开
			elseif ($gameinfo['groomstatus'] && $gameinfo['gamestate']==0 && $room_prefix!='' && $room_prefix[0]=='s')
			{
				$db->query("UPDATE {$gtablepre}game SET groomstatus=1 WHERE groomid='$room_id'");
				$gameinfo['groomstatus'] = 1;
//				$room_prefix = '';
//				$room_id = 0;
//				$gameinfo = NULL;
			}
		}
		else
		{
			$room_prefix = '';
			$room_id = 0;
			$gameinfo = NULL;
		}
		//如果之前没读到房间的gameinfo，则读主游戏的gameinfo
		if(!$room_id || empty($gameinfo)){
			$result = $db->query("SELECT * FROM {$gtablepre}game where groomid='0'");
			$gameinfo = $db->fetch_array($result);
		}
		//$gameinfo初始化，初次global这些变量，其余与load_gameinfo功能相同
		foreach ($gameinfo as $key => $value)
		{
			global ${$key};
			${$key}=$value;
		}
		$arealist = explode(',',$arealist);
		
		//为$tablepre赋值，之后除game表之外的数据库操作都被引入对应前缀的数据表
		$tablepre = get_tablepre();
//		if($room_prefix) $tablepre = $gtablepre.$room_prefix.'_';
//		else $tablepre = $gtablepre;
		
		if ($room_prefix=='') $wtablepre = $gtablepre;
		else $wtablepre = $gtablepre.($room_prefix[0]);
		
		//room_auto_init();//新建房间时，自动初始化房间表
		//实际上不应该放在这里，应该只在新建房间时调用
		
		//当前用户名和密码变量初始化
		global $cuser, $cpass;
		$cuser = ${$gtablepre.'user'};
		$cpass = ${$gtablepre.'pass'};
		
		//这里实在没办法，一堆文件都直接引用mode和command这两个来自input的变量，但又不能让所有文件都依赖input…… 只能恶心一下了……
		global $mode, $command, $___MOD_SRV;
		if ($___MOD_SRV)
		{
			global $___LOCAL_INPUT__VARS__mode, $___LOCAL_INPUT__VARS__command;
			global $___LOCAL_INPUT__VARS__INPUT_VAR_LIST;
			if (isset($___LOCAL_INPUT__VARS__INPUT_VAR_LIST['mode']))
				$mode = $___LOCAL_INPUT__VARS__INPUT_VAR_LIST['mode'];
			else  $mode=$___LOCAL_INPUT__VARS__mode;
			if (isset($___LOCAL_INPUT__VARS__INPUT_VAR_LIST['command']))
				$command = $___LOCAL_INPUT__VARS__INPUT_VAR_LIST['command'];
			else  $command=$___LOCAL_INPUT__VARS__command;
		}
		else
		{
			global $___LOCAL_INPUT__VARS__mode, $___LOCAL_INPUT__VARS__command;
			$mode=$___LOCAL_INPUT__VARS__mode;
			$command=$___LOCAL_INPUT__VARS__command;
		}
	}
	
	function get_tablepre($room_id=0){//根据房间id生成$tablepre，单纯是统一用
		if (eval(__MAGIC__)) return $___RET_VALUE;
		global $gtablepre;
		if(!$room_id) global $room_id;
		$room_id = (int)$room_id;
		if(!$room_id) return $gtablepre;
		else return $gtablepre.'s'.$room_id.'_';
	}
}

?>