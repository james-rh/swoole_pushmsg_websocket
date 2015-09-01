<?php
	/**
	 * @auth james
	 * @last update time:2015-08-13
	 * config.php 专属配制
	 **/
	date_default_timezone_set('Asia/Shanghai');
	header("Content-type: text/html; charset=utf-8");
	@ini_set('memory_limit', '256M');
	@ini_set('swoole.display_errors', 1);
	define("WEBPATH", str_replace("\\", "/", __DIR__ . '/..'));
	define("__REDIS_KEYS__", "PUSHMSG27"); //主要是区别redis关键字 相当于mysql主库名
	define('__SWOOLE_REDISIP__', 'http://192.168.50.11:7900/testform?'); //存有fd,uid的连接 redis lua 
	return array(
		//websocket配制 即server.php
		'swoole_server_host'       => '0.0.0.0', //默认即可
		'swoole_server_port'       => '9501', //开启websocket的端口
		'mode'                     => SWOOLE_PROCESS,
		'sock_type'                => SWOOLE_SOCK_TCP,
		'swoole_setting'           => array(
			'timeout'                  => 0.5, //select and epoll_wait timeout.
			'worker_num'               => 8, //worker process num
			'task_worker_num'          => 8, //task worker process num
			'backlog'                  => 128, //listen backlog
			'open_cpu_affinity'        => 1,
			'open_tcp_nodelay'         => 1,

			'debug_mode' 		   => 0,

			'log_file'                 => '/tmp/swoole.log', //日志文件
			'daemonize'                => true,
			//'daemonize' => 0,
			'heartbeat_check_interval' => 60, //单位为秒，循环检测僵尸websocket 时间
			'heartbeat_idle_time'      => 310, //单位为秒，僵尸websocket 大于多少秒，踢掉
		),		
		//IOS推送配制 1用户（含普通会员）   3 商户    4 合作商
		'ios_push_config'          => array(
			'1' => array(
				"passphrase" => "123456", //证书密码
				"pem_url"    => WEBPATH . "/pem/userproduction_ck.pem", //开发证书路径
				"ssl_url"    => "ssl://gateway.push.apple.com:2195", //SSL URL  开发
			),
			'3' => array(
				"passphrase" => "123456", //证书密码
				"pem_url"    => WEBPATH . "/pem/aps_production_ck.pem", //开发证书路径
				"ssl_url"    => "ssl://gateway.push.apple.com:2195", //SSL URL  开发
			),
		)
	);
