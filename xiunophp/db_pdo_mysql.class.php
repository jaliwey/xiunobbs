<?php

class db_pdo_mysql {
	
	public $conf = array(); // 配置，可以支持主从
	public $wlink = NULL;  // 写连接
	public $rlink = NULL;  // 读连接
	public $link = NULL;   // 最后一次使用的连接
	public $errno = 0;
	public $errstr = '';
	public $sqls = array();
	public $tablepre = '';
	
	public function __construct($conf) {
		$this->conf = $conf;
		$this->tablepre = $conf['master']['tablepre'];
	}
	
	// 根据配置文件连接
	public function connect() {
		$this->wlink = $this->connect_master();
		$this->rlink = $this->connect_slave();
		return $this->wlink && $this->rlink;
	}
	
	// 连接写服务器
	public function connect_master() {
		if($this->wlink) return $this->wlink;
		$conf = $this->conf['master'];
		$this->wlink = $this->real_connect($conf['host'], $conf['user'], $conf['password'], $conf['name'], $conf['charset'], $conf['engine']);
		return $this->wlink;
	}
	
	// 连接从服务器，如果有多台，则随机挑选一台，如果为空，则与主服务器一致。
	public function connect_slave() {
		if($this->rlink) return $this->rlink;
		if(empty($this->conf['slaves'])) {
			if(!$this->wlink) $this->wlink = $this->connect_master();
			$this->rlink = $this->wlink;
		} else {
			$n = array_rand($this->conf['slaves']);
			$conf = $this->conf['slaves'][$n];
			$this->rlink = $this->real_connect($conf['host'], $conf['user'], $conf['password'], $conf['name'], $conf['charset'], $conf['engine']);
		}
		return $this->rlink;
	}
	
	public function real_connect($host, $user, $password, $name, $charset = '', $engine = '') {
		if(strpos($host, ':') !== FALSE) {
			list($host, $port) = explode(':', $host);
		} else {
			$port = 3306;
		}
		try {
			$attr = array(
				PDO::ATTR_TIMEOUT => 5,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			);
			$link = new PDO("mysql:host=$host;port=$port;dbname=$name", $user, $password, $attr);
			//$link->setAttribute(PDO::ATTR_TIMEOUT, 5);
			//$link->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		} catch (Exception $e) {  
			$this->error($e->getCode(), '连接数据库服务器失败:'.$e->getMessage());
			return FALSE;
	        }
	        //$link->setFetchMode(PDO::FETCH_ASSOC);
		$charset AND $link->query("SET names $charset, sql_mode=''");
		 //$link->query('SET NAMES '.($charset ? $charset.',' : '').', sql_mode=""');  
		return $link;
	}
	
	public function sql_find_one($sql) {
		$query = $this->query($sql);
		if(!$query) return $query;
		$query->setFetchMode(PDO::FETCH_ASSOC);
		return $query->fetch();
	}
	
	public function sql_find($sql, $key = NULL) {
		$query = $this->query($sql);
		if(!$query) return $query;
		$query->setFetchMode(PDO::FETCH_ASSOC);
		$arrlist = $query->fetchAll();
		$key AND $arrlist = arrlist_change_key($arrlist, $key);
		return $arrlist;
	}
	
	public function find($table, $cond = array(), $orderby = array(), $page = 1, $pagesize = 10, $key = '', $col = array()) {
		$page = max(1, $page);
		$cond = db_cond_to_sqladd($cond);
		$orderby = db_orderby_to_sqladd($orderby);
		$offset = ($page - 1) * $pagesize;
		$cols = $col ? implode(',', $col) : '*';
		return $this->sql_find("SELECT $cols FROM {$this->tablepre}$table $cond$orderby LIMIT $offset,$pagesize", $key);
		
	}
		
	public function find_one($table, $cond = array(), $orderby = array(), $col = array()) {
		$cond = db_cond_to_sqladd($cond);
		$orderby = db_orderby_to_sqladd($orderby);
		$cols = $col ? implode(',', $col) : '*';
		return $this->sql_find_one("SELECT $cols FROM {$this->tablepre}$table $cond$orderby LIMIT 1");
	}
	
	public function query($sql) {
		if(!$this->rlink && !$this->connect_slave()) return FALSE;
		$link = $this->link = $this->rlink;
		try {
			$query = $link->query($sql);
		} catch (Exception $e) {  
			$this->error($e->getCode(), $e->getMessage());
			return FALSE;
	        }
		if($query === FALSE) $this->error();
		if(count($this->sqls) < 1000) $this->sqls[] = $sql;
		return $query;
	}
	
	public function exec($sql) {
		if(!$this->wlink && !$this->connect_master()) return FALSE;
		$link = $this->link = $this->wlink;
		try {
			$n = $link->exec($sql); // 返回受到影响的行，插入的 id ?
		} catch (Exception $e) {  
			$this->error($e->getCode(), $e->getMessage());
			return FALSE;
	        }
		if(count($this->sqls) < 1000) $this->sqls[] = $sql;
		
		if($n !== FALSE) {
			$pre = strtoupper(substr(trim($sql), 0, 7));
			if($pre == 'INSERT ' || $pre == 'REPLACE') {
				return $this->last_insert_id();
			}
		} else {
			$this->error();
		}
		
		return $n;
	}
	
	// innoDB 通过 information_schema 读取大致的行数
	// SELECT TABLE_ROWS FROM information_schema.tables WHERE TABLE_SCHEMA = '$table' AND TABLE_NAME = '$table';
	// SELECT TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$table';
	public function count($table, $cond = array()) {
		$cond = db_cond_to_sqladd($cond);
		$sql = "SELECT COUNT(*) AS num FROM `$table` $cond";
		$arr = $this->sql_find_one($sql);
		return !empty($arr) ? intval($arr['num']) : $arr;
	}
	
	public function maxid($table, $field, $cond = array()) {
		$sqladd = db_cond_to_sqladd($cond);
		$sql = "SELECT MAX($field) AS maxid FROM `$table` $sqladd";
		$arr = $this->sql_find_one($sql);
		return !empty($arr) ? intval($arr['maxid']) : $arr;
	}
	
	public function truncate($table) {
		return $this->exec("TRUNCATE $table");
	}
	
	public function last_insert_id() {
		return $this->wlink->lastinsertid();
	}
	
	
	/* 表结构相关方法，不用
	// $index = array('uid'=>1, 'dateline'=>-1)
	public function index_create($table, $index) {
		$keys = implode(', ', array_keys($index));
		$keyname = implode('', array_keys($index));
		return $this->exec("ALTER TABLE $table ADD INDEX $keyname($keys)", $this->wlink);
	}
	
	// 删除索引
	public function index_drop($table, $index) {
		$keys = implode(', ', array_keys($index));
		$keyname = implode('', array_keys($index));
		return $this->exec("ALTER TABLE $table DROP INDEX $keyname", $this->wlink);
	}
	
	// 创建表
	public function table_create($table, $ddls, $engineer = '') {
		empty($engineer) && $engineer = 'MyISAM';
		$sql = "CREATE TABLE IF NOT EXISTS $table (\n";
		$sep = '';
		foreach($ddls as $ddl) {
			$sqladd = $this->ddl_to_sqladd($ddl);
			$sql .= $sep.$sqladd;
			$sep = ",\n";
		}
		$sql .= ") ENGINE=$engineer DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
		return $this->exec($sql, $this->wlink);
	}
	
	// DROP table
	public function table_drop($table) {
		$sql = "DROP TABLE IF EXISTS $table";
		return $this->exec($sql, $this->wlink);
	}
	
	public function table_column_add($table, $ddl) {
		$sqladd = $this->ddl_to_sqladd($ddl);
		$sql = "ALTER TABLE $table ADD COLUMN $sqladd;";
		return $this->exec($sql, $this->wlink);
	}
	
	private function ddl_to_sqladd($ddl) {
		$colname = $ddl[0];
		$colattr = $ddl[1];
		$default = strpos($colattr, 'int') !== FALSE ? "'0'" : "''";
		$sqladd = "$colname $colattr NOT NULL DEFAULT $default;";
		return $sqladd;
	}
	
	public function table_column_drop($table, $colname) {
		$sql = "ALTER TABLE $table DROP COLUMN $colname;";
		return $this->exec($sql, $this->wlink);
	}
	*/
	
	public function version() {
		$r = $this->sql_find_one("SELECT VERSION() AS v");
		return $r['v'];
	}
	
	// 设置错误。
	public function error($errno = 0, $errstr = '') {
		$error = $this->link ? $this->link->errorInfo() : array(0, $errno, $errstr);
		$this->errno = $errno ? $errno : (isset($error[1]) ? $error[1] : 0);
		$this->errstr = $errstr ? $errstr : (isset($error[2]) ? $error[2] : '');
		//DEBUG AND trigger_error('Database Error:'.$this->errstr);
	}
	
	public function __destruct() {
		if($this->wlink) $this->wlink = NULL;
		if($this->rlink) $this->rlink = NULL;
	}
}

?>