<?php

	/*
	 * websocket服务端类 基于扩展
	 * james add 2015-0813
	 * swoole 1.7.18
	 */
	class Websocket
	{
		public $host; // 监听地址
		public $port; // 监听端口
		public $config; // swoole配置
		public $server; // swoole server
		public $public_error = array("status" => false, "code" => "", "data" => "", "msg" => "");

		/**
		 * 初始化swoole参数
		 * @param unknown $host
		 * @param unknown $port
		 */
		function __construct()
		{
			$this->config = require_once 'config.php';
			$this->host = $this->config['swoole_server_host'];
			$this->port = $this->config['swoole_server_port'];
		}

		/**
		 * 启动服务
		 */
		function run()
		{
			$this->server = new swoole_websocket_server($this->host, $this->port);
			$this->server->set($this->config['swoole_setting']);
			$this->server->addlistener($this->host, 9002, SWOOLE_SOCK_TCP);
			$this->server->on('Message', array($this, 'onReceive'));
			$this->server->on('Request', array($this, 'onRequest'));
			$this->server->on('Close', array($this, 'onClose'));
			$this->server->on('Start', array($this, 'onStart'));
			$this->server->on('WorkerStart', array($this, 'onWorkerStart'));
			$this->server->on('WorkerStop', array($this, 'onWorkerStop'));
			$this->server->on('WorkerError', array($this, 'onWorkerError'));
			$this->server->on('Shutdown', array($this, 'onShutdown'));
			$this->server->on('Task', array($this, 'onTask'));
			$this->server->on('Timer', array($this, 'onTimer'));
			$this->server->on('Finish', array($this, 'onFinish'));
			$this->server->on('ManagerStart', array($this, 'onManagerStart'));
			$this->server->start();
		}

		/**
		 * @param $serv
		 * @param $frame
		 * @return bool
		 * echo "message: ".$frame->data;
		 * $server->push($frame->fd, json_encode(["hello", "world"]));
		 */
		function onRequest($request, $response)
		{
			$post = isset($request->post) ? $request->post : array();
			if (!$post || !isset($post['web_key']) || $post['web_key'] != "sdsdsdsdsdsdwewewewesd232323232323") {
				return $this->request_end($request, $response);
			}
			$data = json_decode($post['data'], true);
			if (!$data) {
				return $this->request_end($request, $response);
			}
			$this->onAdmin($data, $request->fd);
			;
			return $this->request_end($request, $response);
		}

		function request_end($request, $response)
		{
			$response->write("1");
			$response->end();
			return false;
		}

		/**
		 * @param $serv
		 * @param $frame
		 * @return bool
		 * echo "message: ".$frame->data;
		 * $server->push($frame->fd, json_encode(["hello", "world"]));
		 */
		function onReceive($serv, $frame)
		{
			$data = json_decode($frame->data, true);
			if (!$data) {
				return false;
			}
			$this->onMessage($serv, $frame->fd, $data);
		}

		/**
		 * 接收到websocket数据时触发
		 * @param unknown $fd
		 * @param unknown $data
		 */
		function onMessage($serv, $fd, $data)
		{
		}

		function onTimer($serv, $interval)
		{
		}

		/**
		 * 关闭一个连接时触发
		 * @param unknown $serv
		 * @param unknown $fd
		 * @param unknown $from_id
		 */
		function onClose($serv, $fd)
		{
		}

		/**
		 * 管理者专消息
		 * @param $fd
		 * @param $data
		 * @return mixed
		 */
		function onAdmin($data, $fd = 0)
		{
		}

		/**
		 * 主进程开始
		 * @param unknown $serv
		 * @param unknown $worker_id
		 */
		function onStart($serv)
		{
			global $argv;
			swoole_set_process_name("{$argv[0]} {$this->host}:{$this->port} master");
		}

		/**
		 * 工作组开始
		 * @param unknown $serv
		 * @param unknown $worker_id
		 */
		function onWorkerStart($serv, $worker_id)
		{
			$this->processRename($serv, $worker_id);
		}

		/**
		 * @param $serv
		 * @param $worker_id
		 * 工作组进程停止
		 */
		function onWorkerStop($serv, $worker_id)
		{
		}

		/**
		 * @param $serv
		 * @param $worker_id
		 * @param $worker_pid
		 * @param $exit_code
		 * 工作组进程出错
		 */
		function onWorkerError($serv, $worker_id, $worker_pid, $exit_code)
		{
			echo "worker abnormal exit. WorkerId=$worker_id|Pid=$worker_pid|ExitCode=$exit_code\n";
		}

		/**
		 * @param $serv
		 * @param $task_id
		 * @param $from_id
		 * @param $data
		 * 任务开始
		 */
		function onTask($serv, $task_id, $from_id, $data)
		{
		}

		/**
		 * @param $serv
		 * @param $task_id
		 * @param $data
		 * 任务完成
		 */
		function onFinish($serv, $task_id, $data)
		{
		}

		/**
		 * @param $serv
		 * 关闭
		 */
		function onShutdown($serv)
		{
			echo PHP_EOL . date("Y-m-d H:i:s") . " server shutdown!" . PHP_EOL;
		}

		/**
		 * @param $serv
		 * 主进程
		 */
		function onManagerStart($serv)
		{
			global $argv;
			swoole_set_process_name("{$argv[0]} {$this->host}:{$this->port} manager");
		}

		function processRename($serv, $worker_id)
		{
			global $argv;
			if ($worker_id >= $serv->setting['worker_num']) {
				swoole_set_process_name("{$argv[0]} {$this->host}:{$this->port} task");
			} else {
				swoole_set_process_name("{$argv[0]} {$this->host}:{$this->port} worker");
			}
		}
	}
