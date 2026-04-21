<?php
if (!function_exists('config_env')) {
	function config_env($keys, $default = null) {
		foreach ((array)$keys as $key) {
			$value = getenv($key);
			if ($value !== false && $value !== '') {
				return $value;
			}
			if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
				return $_ENV[$key];
			}
			if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
				return $_SERVER[$key];
			}
		}
		return $default;
	}
}

/*数据库配置*/
$dbconfig=array(
	'host' => config_env(['DB_HOST', 'MYSQL_HOST'], 'localhost'), //数据库服务器
	'port' => (int)config_env(['DB_PORT', 'MYSQL_PORT'], 3306), //数据库端口
	'user' => config_env(['DB_USER', 'MYSQL_USER'], ''), //数据库用户名
	'pwd' => config_env(['DB_PASSWORD', 'MYSQL_PASSWORD'], ''), //数据库密码
	'dbname' => config_env(['DB_NAME', 'MYSQL_DATABASE'], ''), //数据库名
	'dbqz' => config_env(['DB_PREFIX'], 'pay') //数据表前缀
);
