<?php
	/**
	 * @auth james
	 * @last update time:2015-01-13
	 * 开放websocket 服务
	 */
	require "./server/websocket.php";
	require "./server/redis_lib.php";
	/*
	 * swoole websocket服务
	 * @auth james add 20141205
	 * 依托于swoole php 扩展
	 */
	class server extends Websocket
	{
		public $redis_lib;

		public function __construct()
		{
			parent::__construct();
			$this->redis_lib = new Redis_lib(__SWOOLE_REDISIP__);
		}

		/**
		 * 接到管理都要发布消息的指令调用
		 */
		public function onAdmin($data, $fd = 0)
		{
			//先检测条件
			if (!$data) {
				return false;
			}
			$data['usertype'] = intval($data['usertype']);
			if (!$data['usertype']) {
				return false;
			}
			//处理来自连接信息
			if (isset($data['tasktype']) && $data['tasktype'] == "checkiosconect" && isset($data['uid'])) {
				$this->checkConnectKeySid($data);
				return false;
			}
			//先检测该用户的未读消息
			if (isset($data['tasktype']) && $data['tasktype'] == "checknopush" && isset($data['uid'])) {
				$this->checkNoPushForUID($data['usertype'], $data['uid']);
				return false;
			}
			//消息队列结构体
			$_arr = array("isAll"    => true,
			              "uid"      => 0,
			              "sdate"    => $data['content']['sdate'],
			              "tdate"    => $data['content']['tdate'],
			              "edate"    => $data['content']['edate'],
			              "usertype" => $data['usertype'],
			              "sid"      => uniqid(), //存储redis消息主体标识
			              "p_uid"    => $data['p_uid']
			);
			unset($data['content']['tdate']);
			unset($data['content']['edate']);
			$_arr['content'] = $data['content'];
			//创建新的任务队列
			$this->server->task($this->json_en(array('tasktype' => "create_datapush", "data" => $_arr)));
		}

		/**
		 * 关闭一个连接时触发
		 * 清除相应的redis
		 */
		public function onClose($serv, $fd)
		{
			$key_sid = $this->redis_lib->zscore(__REDIS_KEYS__ . "KEY_SID", $fd);
			if ($key_sid) {
				$this->redis_lib->zrem(__REDIS_KEYS__ . "KEY_SID", $fd);
				$this->redis_lib->hdel(__REDIS_KEYS__ . "FD_CONF", $fd);
				for ($i = 1; $i < 5; $i++) {
					if ($i != 2) {
						$flg_key_sid = __REDIS_KEYS__ . "KEY_SID" . $i;
						@$this->redis_lib->zrem($flg_key_sid, $key_sid);
						@$this->redis_lib->zremrangebyscore($flg_key_sid, $key_sid, $key_sid);
					}
				}
			}
		}

		/**
		 * 接收到客户端消息时调用
		 */
		public function onMessage($serv, $fd, $data)
		{
			//数据传输
			$result = array("status" => false, "code" => "", "data" => "", "msg" => "");
			if (empty($data) || !is_array($data)) {
				return $this->reBlackMsg($serv, $fd);
			}
			if (isset($data['type']) && $data['type'] == "close") {
				return $this->reBlackMsg($serv, $fd);
			}
			if (isset($data['type']) && $data['type'] == "alive") {
				// return false;
				return $this->reBlackMsg($serv, $fd);
			}
			if (!isset($data['uid']) || !isset($data['usertype'])) {
				return $this->reBlackMsg($serv, $fd);
			}
			$data = array('fd' => $fd, "uid" => intval($data['uid']), "usertype" => intval($data['usertype']), "config" => array());
			$this->checkConnectKeySid($data);
		}

		/**
		 * @param $serv
		 * @param $task_id
		 * @param $from_id
		 * @param $data
		 * @return bool|void
		 * 任务队列
		 */
		public function onTask($serv, $task_id, $from_id, $data)
		{
			$data = $this->json_de($data);
			if (isset($data['tasktype']) && $data['tasktype']) {
				switch ($data['tasktype']) {
					case "other_cleandata":
						//清理无效的垃圾
						$this->cleanDataTemp();
						break;
					case "create_datapush":
						//创建并分解消息
						$_arr = $data['data'];
						if (!isset($_arr['p_uid'])) {
							$this->createDataPush($serv, $_arr);
						} else {
							$this->redis_lib->hset(__REDIS_KEYS__ . "MSG", $_arr['sid'], $this->json_en($_arr['content']));
							$p_uid = $_arr['p_uid'] ? explode(",", $_arr['p_uid']) : 0;
							if ($p_uid) {
								//为指定的用户发
								$_arr['log_info'] = "创建推送,指定用户!";
								$this->addRedisPushLogs($_arr, 1);
								unset($_arr['log_info']);
								unset($_arr['p_uid']);
								$_arr['isAll'] = false;
								foreach ($p_uid as $vo) {
									if ($vo) {
										$_arr['uid'] = $vo;
										$this->createDataPush($serv, $_arr);
									}
								}
							} else {
								//为全部发
								$_arr['log_info'] = "创建推送,全部推送!";
								$this->addRedisPushLogs($_arr, 1);
								unset($_arr['p_uid']);
								unset($_arr['log_info']);
								//创建新的任务队列
								$this->createDataPush($serv, $_arr);
							}
						}
						break;
				}
			}
			return false;
		}

		/**
		 * @param $serv
		 * @param $interval
		 * 定时器
		 */
		public function onTimer($serv, $interval)
		{
			switch ($interval) {
				case 10000: //专处理消息
					$time = time();
					$nopush = $this->redis_lib->zrangebyscore(__REDIS_KEYS__ . "PWAIT_AD", 0, $time);
					$this->redis_lib->zremrangebyscore(__REDIS_KEYS__ . "PWAIT_AD", 0, $time);
					if ($nopush) {
						foreach ($nopush as $vo) {
							if (!$vo) {
								continue;
							}
							$vo = $this->redis_lib->hget(__REDIS_KEYS__ . "MLOG", $vo);
							$vo = $this->json_de($vo);
							if (!$vo) {
								continue;
							}
							$vo['tasktype'] = "wailpushall";
							$vo["log_info"] = "推送中,从待发队列中取出数据";
							$this->addRedisPushLogs($vo, 1);
							unset($vo["log_info"]);
							$vo['content'] = $this->redis_lib->hget(__REDIS_KEYS__ . "MSG", $vo['sid']);
							$vo['content'] = $vo['content'] ? $this->json_de($vo['content']) : array();
							$this->server->task($this->json_en(array('tasktype' => "create_datapush", "data" => $vo)));
						}
					}
					/**
					 * 到晚上四点
					 * 处理执行,剔除未登陆用户过期的数据
					 */
					if (date("H:i:s") == "04:00:00") {
						//开始创建 task 推送消息
						$this->server->task($this->json_en(array('tasktype' => "other_cleandata")));
					}
					break;
			}
		}

		/**
		 * 工作组开始
		 * @param unknown $serv
		 * @param unknown $worker_id
		 */
		public function onWorkerStart($serv, $worker_id)
		{
			$this->processRename($serv, $worker_id);
			if ($worker_id == 0) {
				$serv->addtimer(10000);
				return false;
			}
		}

		/**
		 * @param $data
		 * @return bool
		 * //data=array(uid,usertype,fd(iostoken),config=>array(stime,time_count))
		 */
		private function checkConnectKeySid($data)
		{
			$key_sid = $this->redis_lib->zscore(__REDIS_KEYS__ . "KEY_SID", $data['fd']);
			if (!$key_sid) {
				$key_sid = $this->redis_lib->incr(__REDIS_KEYS__ . "KEY_SID_COUNT");
				$this->redis_lib->zadd(__REDIS_KEYS__ . "KEY_SID", $key_sid, $data['fd']);
			}
			$data['config'] && $this->redis_lib->hset(__REDIS_KEYS__ . "FD_CONF", $data['fd'], $this->json_en($data['config']));
			$flg_key_sid = __REDIS_KEYS__ . "KEY_SID" . $data['usertype'];
			if ($data['uid']) {
				$this->redis_lib->zrem($flg_key_sid, $key_sid);
				$this->redis_lib->zadd($flg_key_sid, $key_sid, $data['uid']);
				//查未读消息
				$this->checkNoPushForUID($data['usertype'], $data['uid']);
			} else {
				$this->redis_lib->zremrangebyscore($flg_key_sid, $key_sid, $key_sid);
				$this->redis_lib->zadd($flg_key_sid, 0, $key_sid);
			}
		}

		/**
		 * @param       $serv
		 * @param int   $usertype
		 * @param int   $uid
		 * @return bool
		 * 检测单用户时的未读的消息
		 * 由redis未读状态 转到 队列推送或redis等待状态
		 */
		private function checkNoPushForUID($usertype = 0, $uid = 0)
		{
			//找未读的消息
			$nopush = $this->redis_lib->zrangebyscore(__REDIS_KEYS__ . "PNO_AD_" . $usertype, $uid, $uid);
			if (!$nopush) {
				return false;
			}
			//找到未读消息
			ksort($nopush); //并排序
			//清除该用户的未读消息
			$this->redis_lib->zremrangebyscore(__REDIS_KEYS__ . "PNO_AD_" . $usertype, $uid, $uid);
			$i = 0;
			$timenow = time();
			foreach ($nopush as $vo) {
				if (!$vo) {
					continue;
				}
				$vo = $this->redis_lib->hget(__REDIS_KEYS__ . "MLOG", $vo);
				$vo = $this->json_de($vo);
				if (!$vo || !isset($vo['tdate']) || !isset($vo['edate'])) {
					continue;
				}
				$_tdate = strtotime($vo['tdate']);
				$_tdate < $timenow && $_tdate = $timenow;
				$_edate = strtotime($vo['edate']);
				$_edate < $timenow && $_edate = $timenow;
				$vo['tdate'] = date("Y-m-d H:i:s", $_tdate + $i * 20);
				$vo['edate'] = date("Y-m-d H:i:s", $_edate + $i * 20);
				$vo["log_info"] = "待发中,用户登陆上线,存入到待发队列";
				$this->addRedisPushLogs($vo, 2); // 1推送中 2待发中 3已失败 4已推送 5未登陆
				unset($vo['log_info']);
				$vo['content'] = $this->redis_lib->hget(__REDIS_KEYS__ . "MSG", $vo['sid']);
				$vo['content'] = $vo['content'] ? $this->json_de($vo['content']) : array();
				$this->server->task($this->json_en(array('tasktype' => "create_datapush", "data" => $vo)));
				$i++;
			}
		}

		private function json_de($result = "")
		{
			return json_decode($result, true);
		}

		/**
		 * @param array $data
		 * @param int   $flg 1推送中 2待发中 3已失败 4已推送 5未登陆 6推送失败,已过期
		 * @param int   $add 0修改 1创建
		 * @return bool
		 *                   创建redis日志,同时记录消息及状态日志
		 */
		private function addRedisPushLogs($data = array(), $flg = 1)
		{
			$sid = trim($data['sid']);
			if ($flg != 1 && isset($data['content'])) unset($data['content']);
			$data['pushflg'] = $flg;
			$this->redis_lib->hset(__REDIS_KEYS__ . "MLOG", $sid, $this->json_en($data));
			if (isset($data['log_info']) && $data['log_info']) {
				@file_put_contents("/data/logs/push/" . date("Ymd") . ".log", date("Y-m-d H:i:s") . "  " . $this->json_en($data) . PHP_EOL, FILE_APPEND);
			}
		}

		/**
		 * 清理临时数据
		 * 1.清除过期未读消息队列,保持整洁
		 */
		private function cleanDataTemp()
		{
			$time = date("Y-m-d H:i:s", time());
			for ($i = 1; $i <= 4; $i++) {
				$nopush = $this->redis_lib->zrangebyscore(__REDIS_KEYS__ . "PNO_AD_" . $i, 0, -1);
				if (!$nopush) {
					continue;
				}
				foreach ($nopush as $vo) {
					if (!$vo) {
						continue;
					}
					$vo = $this->redis_lib->hget(__REDIS_KEYS__ . "MSG", $vo);
					$vo = $this->json_de($vo);
					if (!$vo) {
						continue;
					}
					if ($vo['edate'] <= $time) {
						$this->redis_lib->zrem(__REDIS_KEYS__ . "PNO_AD_" . $i, $vo);
						$vo['log_info'] = "推送失败:过期清除!";
						$this->addRedisPushLogs($vo, 3);
					}
				}
			}
		}

		/**
		 * @param       $serv
		 * @param array $data
		 * @return bool
		 *                      创建消息
		 */
		private function createDataPush($serv, $data = array())
		{
			if (!$data || !isset($data['sid'])) {
				return false;
			}
			$time_now = time();
			//看时间是否符合要求 过期1分钟则丢弃
			if (!isset($data['edate']) || strtotime($data['edate']) <= $time_now - 120) {
				$data['log_info'] = "推送失败,消息过期被丢弃!";
				$this->addRedisPushLogs($data, 3);
				return false;
			}
			if (!isset($data['content']) || !$data['content']) {
				$data['log_info'] = "推送失败,消息内容为空,被丢弃!";
				$this->addRedisPushLogs($data, 3);
				return false;
			}
			if ($data['sdate'] != $data['tdate'] && strtotime($data['tdate']) > $time_now) {
				$this->addRedisTask($data);
				return false;
			}
			//全部推送
			$flg_key_sid = __REDIS_KEYS__ . "KEY_SID" . $data['usertype'];
			if ($data['isAll'] == true) {
				$flg_push_count = $this->redis_lib->zcard($flg_key_sid);
				$flg_push_count_every = 2000;
				for ($i = 1; $i <= ceil($flg_push_count / $flg_push_count_every); $i++) {
					$temp_data = $this->redis_lib->zrange($flg_key_sid, $flg_push_count_every * ($i - 1), $flg_push_count_every * $i - 1);
					if ($temp_data) {
						foreach ($temp_data as $key => $vo) {
							/**
							 * 通过key_sid, 成为 fd,或 iostoen
							 * 登陆 key_sid 有值 $vo指的是key_sid
							 * uid 0 , $key指的是key_sid
							 */
							!$vo && $vo = $key;
							$fd = $this->redis_lib->zrangebyscore(__REDIS_KEYS__ . "KEY_SID", $vo, $vo);
							if (isset($fd[0])) $fd = $fd[0];
							$fd && $this->myPushMsg($serv, $data, $fd);
						}
					}
				}
				return false;
			}
			//按用户单推
			$key_sid = $this->redis_lib->zscore($flg_key_sid, $data['uid']);
			if ($key_sid) {
				$fd = $this->redis_lib->zrangebyscore(__REDIS_KEYS__ . "KEY_SID", $key_sid, $key_sid);
				$this->myPushMsgWritelog($this->myPushMsg($serv, $data, $fd[0]));
			} else {
				//用户未登陆
				$this->addRedisNoPush($data);
			}
			return true;
		}

		/**
		 * @param       $serv
		 * @param array $data
		 * @param       $fd
		 * 消息推送
		 */
		private function myPushMsg($serv, $data = array(), $fd = 0)
		{
			$data['fd_ios'] = $fd;
			if (!$fd) {
				$data['lgos_flg'] = 0;
				return $data;
			}
			//判断是否安卓
			if (is_numeric($fd)) {
				$content = $this->public_error;
				$content['status'] = true;
				$content['data'] = $data['content'];
				$content = $this->json_en($content);
				if (isset($content['data']['iosaction'])) unset($content['data']['iosaction']);
				if (!$serv->exist($fd)) {
					$data['lgos_flg'] = 2;
					return $data;
				}
				@$serv->push($fd, $content);
				$data['lgos_flg'] = 1;
				return $data;
			}
			//如果是ios
			$iosaction = isset($data['content']['iosaction']) ? $data['content']['iosaction'] : "";
			if (!$iosaction) {
				$data['lgos_flg'] = 3;
				return $data;
			}
			//ios推送
			if (!$this->checkPushTime($data['usertype'], $fd)) {
				$data['lgos_flg'] = 5;
				return $data;
			}
			@$this->iosPush($fd, $iosaction, $this->ios_push_config[$data['usertype']]);
			$data['lgos_flg'] = 4;
			return $data;
		}

		private function myPushMsgWritelog($data = array())
		{
			$flg = intval($data['lgos_flg']);
			unset($data['lgos_flg']);
			switch (intval($flg)) {
				case 0:
					$data['log_info'] = "fd为空或不存在!";
					$this->addRedisPushLogs($data, 2);
					break;
				case 1:
					$data['log_info'] = "安卓推送成功!";
					$this->addRedisPushLogs($data, 4);
					break;
				case 2:
					$data['log_info'] = "安卓推送失败!";
					$this->addRedisPushLogs($data, 2);
					break;
				case 3:
					$data['log_info'] = "IOS推送失败:无推送内容(iosaction为空)!";
					$this->addRedisPushLogs($data, 3);
					break;
				case 4:
					$data['log_info'] = "IOS推送成功!";
					$this->addRedisPushLogs($data, 4);
					break;
				case 5:
					$data['log_info'] = "IOS推送失败,重新进入队列:用户设置了不允许推送时间!";
					$this->addRedisPushLogs($data, 2);
					break;
			}
		}

		/**
		 * @param array $data
		 * @return bool
		 * 创建用户没有登陆的redis
		 */
		private function addRedisNoPush($data = array())
		{
			if ($data && isset($data['sid']) && $data['sid']) {
				$this->redis_lib->zadd(__REDIS_KEYS__ . "PNO_AD_" . $data['usertype'], $data['uid'], $data['sid']); //分页管理查
				$data['log_info'] = "未登陆";
				$this->addRedisPushLogs($data, 5); // 1推送中 2待发中 3已失败 4已推送 5未登陆
			}
		}

		/**
		 * @return bool
		 * 检测用户推送时间
		 */
		private function checkPushTime($usertype = 0, $fd = "")
		{
			if (!$usertype || !$fd) return false;
			$userinfo = $this->redis_lib->hget(__REDIS_KEYS__ . "FD_CONF", $fd);
			if (!$userinfo || !isset($userinfo['start_stime'])) {
				return true;
			}
			$date_time = time();
			$_date_time = date("Y-m-d ");
			$userinfo['start_stime'] = strtotime($_date_time . $userinfo['start_stime']);
			$userinfo['stop_etime'] = $userinfo['start_stime'] + intval($userinfo['stop_etime']);
			if ($userinfo['start_stime'] > $date_time && $userinfo['stop_etime'] < $date_time) {
				return false;
			}
			return true;
		}

		/**
		 * @param $message
		 * @return mixed
		 * 向ios推送数据
		 */
		private function iosPush($iostoken = "", $iosaction = array(), $conf = array())
		{
			$result = array('status' => false, 'code' => '', 'data' => '', 'msg' => '');
			$ctx = stream_context_create();
			stream_context_set_option($ctx, 'ssl', 'local_cert', $conf['pem_url']);
			stream_context_set_option($ctx, 'ssl', 'passphrase', $conf['passphrase']);
			$fp = stream_socket_client($conf['ssl_url'], $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
			if (!$fp) {
				$result['msg'] = 'connetct server error';
				return $result;
			}
			$body['aps'] = $iosaction;
			$payload = $this->json_en($body);
			$msg = chr(0) . pack('n', 32) . pack('H*', $iostoken) . pack('n', strlen($payload)) . $payload;
			$len = strlen($msg);
			if ($len > 254) {
				return $result;
			}
			fwrite($fp, $msg, $len);
			fclose($fp);
			$result['status'] = true;
			return $result;
		}

		/**
		 * @param array $data
		 * @return bool
		 * 仍到redis里，以便task定时取数据
		 */
		private function addRedisTask($data = array())
		{
			if ($data && isset($data['sid']) && $data['sid']) {
				$time = isset($data['tdate']) ? strtotime($data['tdate']) : time();
				$this->redis_lib->zadd(__REDIS_KEYS__ . "PWAIT_AD", $time, $data['sid']); //分页管理查
				$data['log_info'] = "待推送中,已存入推送队列";
				$this->addRedisPushLogs($data, 2); // 1推送中 2待发中 3已失败 4已推送 5未登陆
			}
		}

		private function json_en($result = array())
		{
			return json_encode($result, JSON_UNESCAPED_UNICODE);
		}

		/**
		 * @param string $msg
		 * @param bool   $isclose
		 * 返回消处
		 */
		private function reBlackMsg($serv, $fd = 0)
		{
			$fd && $serv->close($fd);
			return false;
		}
	}

	$server = new server();
	$server->run();
