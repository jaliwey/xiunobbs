<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);

$user = user_read($uid);
user_login_check($user);

// 账户（密码、头像），主题
if(empty($action)) {
	
	$header['title'] = '个人中心';
	include './view/htm/my.htm';
	
} elseif($action == 'profile') {
	
	include './view/htm/my_profile.htm';
/*
} elseif($action == 'profile') {
		
	if($method == 'GET') {

		include './view/htm/my_profile.htm';
	
	} else {
		
		$username = param('username');
		$email = param('email');
		!is_username($username, $err) AND message(1, $err);
		!is_email($email, $err) AND message(2, $err);
		$update = array();
		if($username != $user['username']) {
			mb_strlen($username, 'UTF-8') > 32 AND message(1, '用户名 最长为 32 个字符。');
			$u = user_read_by_username($username);
			$u AND message(1, '用户名已经存在，更换其它名字试试。');
			$update['username'] = $username;
		}
		if($email != $user['email']) {
			mb_strlen($email, 'UTF-8') > 40 AND message(1, 'EMAIL 最长为 40 个字符。');
			$u = user_read_by_email($email);
			$u AND message(2, 'Email 已经存在，更换其它 Email 试试。');
			$update['email'] = $email;
		}
		if($update) {
			$r = user_update($uid, $update);
			$r !== FALSE ? message(0, '修改成功') :  message(10, '修改失败');
		} else {
			message(0, '已保存');
		}
	}
*/

} elseif($action == 'password') {
	
	if($method == 'GET') {
		
		include './view/htm/my_password.htm';
		
	} elseif($method == 'POST') {
		
		$password_old = param('password_old');
		$password_new = param('password_new');
		md5($password_old.$user['salt']) != $user['password'] AND message('password_old', '旧密码不正确');
		$password_new = md5($password_new.$user['salt']);
		$r = user_update($uid, array('password'=>$password_new));
		$r !== FALSE ? message(0, '密码修改成功') : message(-1, '密码修改失败');
		
	}
	
} elseif($action == 'thread') {

	$page = param(2, 1);
	$pagesize = 20;
	$totalnum = $user['threads'];
	$pages = pages('my-thread-{page}.htm', $totalnum, $page, $pagesize);
	$threadlist = mythread_find_by_uid($uid, $page, $pagesize);
		
	include './view/htm/my_thread.htm';

} elseif($action == 'uploadavatar') {
	
	$upfile = param('upfile', '', FALSE);
	empty($upfile) AND message(-1, 'upfile 数据为空');
	$json = xn_json_decode($upfile);
	empty($json) AND message(-1, '数据有问题: json 为空');
	
	$name = $json['name'];
	$width = $json['width'];
	$height = $json['height'];
	$data = base64_decode($json['data']);
	$size = strlen($data);
	
	$filename = "$uid.png";
	$dir = substr(sprintf("%09d", $uid), 0, 3).'/';
	$path = $conf['upload_path'].'avatar/'.$dir;
	$url = $conf['upload_url'].'avatar/'.$dir.$filename;
	!is_dir($path) AND (mkdir($path, 0777, TRUE) OR message(-2, '目录创建失败'));
	
	file_put_contents($path.$filename, $data) OR message(-1, '写入文件失败');
	
	user_update($uid, array('avatar'=>$time));
	message(0, $url);
	
}

?>
