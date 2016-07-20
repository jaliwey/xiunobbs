<?php

chdir('../');

define('DEBUG', 1);
define('APP_NAME', 'bbs_install');

$conf = (@include './conf/conf.default.php');

include './xiunophp/xiunophp.php';
include './model.inc.php';
include './install/install.func.php';

$browser = get__browser();
check_browser($browser);

$action = param('action');

// 安装初始化检测,放这里
is_file('./conf/conf.php') AND empty($action) AND !DEBUG AND message(0, jump('程序已经安装过了，如需重新安装，请删除 conf/conf.php ！', '../'));

// 第一步，阅读
if(empty($action)) {
	include "./install/view/index.htm";
} elseif($action == 'env') {
	if($method == 'GET') {
		$succeed = 1;
		$env = $write = array();
		get_env($env, $write);
		include "./install/view/env.htm";
	} else {
	
	}
} elseif($action == 'db') {
	
	if($method == 'GET') {
		
		$succeed = 1;
		$mysql_support = function_exists('mysql_connect');
		$pdo_mysql_support = extension_loaded('pdo_mysql');
		(!$mysql_support && !$pdo_mysql_support) AND message(0, '当前 PHP 环境不支持 mysql 和 pdo_mysql，无法继续安装。');

		include "./install/view/db.htm";
		
	} else {
		
		$type = param('type');	
		$host = param('host');	
		$name = param('name');	
		$user = param('user');
		$pass = param('pass');
		$force = param('force');
		
		$adminemail = param('adminemail');
		$adminuser = param('adminuser');
		$adminpass = param('adminpass');
		
		empty($host) AND message('host', '数据库主机不能为空。');
		empty($name) AND message('name', '数据库名不能为空。');
		empty($user) AND message('user', '用户名不能为空。');
		empty($adminpass) AND message('adminpass', '管理员密码不能为空！');
		empty($adminemail) AND message('adminemail', '管理员密码不能为空！');
		
		// 设置超时尽量短一些
		set_time_limit(60);
		ini_set('mysql.connect_timeout',  5);
		ini_set('default_socket_timeout', 5); 

		print_r($conf['db']['mysql']['master']);
		var_dump($pass);
		$conf['db']['type'] = $type;	
		$conf['db']['mysql']['master']['host'] = $host;
		$conf['db']['mysql']['master']['name'] = $name;
		$conf['db']['mysql']['master']['user'] = $user;
		$conf['db']['mysql']['master']['pass'] = 123;
		var_dump($conf['db']['mysql']['master']['pass']);
		print_r($conf['db']['mysql']['master']);
		exit;
		echo $conf['db']['mysql']['master']['pass'];
		print_r($conf['db']['mysql']['master']);exit;
		$conf['db']['pdo_mysql']['master']['host'] = $host;
		$conf['db']['pdo_mysql']['master']['name'] = $name;
		$conf['db']['pdo_mysql']['master']['user'] = $user;
		$conf['db']['pdo_mysql']['master']['pass'] = $pass;
		
		print_r($_POST);
		print_r($conf['db']);exit;
		//print_r($conf['db']);
		$db = db_new($conf['db']);
		!db_connect() AND message('host', "errno: $errno, errstr:$errstr");
		
		//$u = db_find_one('user', array(), array());
		//print_r($u);exit;
		message(0, '安装成功');
	}
}


?>