<?php

	/**
	 * Class Redis_lib
	 * james add redis lualu
	 * 20141126
	 */
	class Redis_lib
	{
		public $url;

		public function __construct($url = "")
		{
			$this->url = $url ? $url : REDISIP . '/testform?';
		}

		/**
		 * @param     $key
		 * @param     $value
		 * @param int $time_out
		 * @return array|bool|mixed
		 * set valuse
		 */
		public function set($key, $value, $time_out = 0)
		{
			$data = array('fun' => "set", "key" => $key, "value" => $value);
			$result = $this->_sendurldata($data);
			return $result === "OK" ? true : false;
		}

		/**
		 * @param $key
		 * @return array|bool|mixed
		 * get valsue
		 */
		public function get($key)
		{
			$data = array('fun' => "get", "key" => $key);
			return $this->_sendurldata($data);
		}

		/**
		 * @param $key
		 * @param $field
		 * @param $value
		 * @return string
		 * 单个集合
		 */
		public function hset($key, $field, $value, $time_out = 0)
		{
			$data = array('fun' => "hset", "key" => $key, "field" => $field, "value" => $value);
			return $this->_sendurldata($data);
		}

		/**
		 * @param       $key
		 * @param array $value =array($file=>$valus)
		 * @return bool
		 * 多个集合
		 */
		public function hsetall($key, $value = array(), $time_out = 0)
		{
			$flg = true;
			foreach ($value as $field => $vo) {
				$data = array('fun' => "hset", "key" => $key, "field" => $field, "value" => $vo);
				$result = $this->_sendurldata($data);
				if ($result === 'false') {
					$flg = false;
					break;
				}
			}
			return $flg ? true : false;
		}

		/**
		 * @param $key
		 * @param $field
		 * @return string
		 * 得到单个集合
		 */
		public function hget($key, $field)
		{
			$data = array('fun' => "hget", "key" => $key, "field" => $field);
			return $this->_sendurldata($data);
		}

		/**
		 * @param $key
		 * @return array
		 * 得到多个集合
		 */
		public function hgetall($key)
		{
			$data = array('fun' => "hgetall", "key" => $key);
			return $this->spilt_body($this->_sendurldata($data));
		}

		/**
		 * @param $key
		 * @return string
		 * 删除key
		 */
		public function del($key)
		{
			$data = array('fun' => "del", "key" => $key);
			return $this->_sendurldata($data);
		}

		/**
		 * @param $key
		 * @return string
		 * 删除key field
		 */
		public function hdel($key, $field)
		{
			$data = array('fun' => "hdel", "key" => $key, "field" => $field);
			return $this->_sendurldata($data);
		}

		/**
		 * @param $key
		 * @return string
		 * 追加
		 */
		public function rpush($key, $value)
		{
			$data = array('fun' => "rpush", "key" => $key, "value" => $value);
			return $this->_sendurldata($data);
		}

		/**
		 * @param $key
		 * @return string
		 * 给该主建值加 1
		 */
		public function incr($key)
		{
			$data = array('fun' => "incr", "key" => $key);
			return $this->_sendurldata($data);
		}

		/**
		 * 有序集合，统计数量。
		 * @param string $key
		 * @return Ambigous <number, int, mixed>
		 */
		public function zcard($key)
		{
			$data = array('fun' => "zcard", "key" => $key);
			return (int)$this->_sendurldata($data);
		}

		/**
		 * 有序集合，添加score，member
		 * @param string $key
		 * @param int    $score
		 * @param string $value
		 */
		public function zadd($key, $score, $value)
		{
			$data = array('fun' => "zadd", "key" => $key, "score" => $score, "value" => $value);
			return $this->_sendurldata($data);
		}

		/**
		 * 有序集合，批量添加score，member
		 * @param string $key
		 * @param array  $value
		 */
		public function zaddall($key, $value = array())
		{
			$flg = true;
			foreach ($value as $score => $vo) {
				$result = $this->zadd($key, $score, $vo);
				if ($result === 'false') {
					$flg = false;
					break;
				}
			}
			return $flg ? true : false;
		}

		/**
		 * 有序集合，所有 score 值介于 $start 和 $end 之间
		 * @param string $key
		 * @param number $start
		 * @param number $end
		 * @return boolean|multitype:
		 */
		public function zrangebyscore($key, $start = 0, $end = 10)
		{
			$data = array('fun' => "zrangebyscore", "key" => $key, 'min' => $start, 'max' => $end);
			$result = $this->_sendurldata($data);
			if ($result === 'false') {
				return false;
			}
			$result = explode("\n", trim($result, "\n"));
			return $result;
		}

		/**
		 * 返回有序集 key 中，指定区间内的成员。
		 * @param string $key
		 * @param number $start
		 * @param number $stop
		 */
		public function zrange($key, $start = 0, $stop = 10, $withscores = 'WITHSCORES')
		{
			$data = array('fun' => "zrange", "key" => $key, 'start' => $start, 'stop' => $stop, 'withscores' => 'WITHSCORES');
			$result = $this->_sendurldata($data);
			if ($result === 'false') {
				return false;
			}
			return $this->spilt_body($result);
		}

		/**
		 * 返回有序集 key 中，指定区间内的成员。[降序]
		 * @param string $key
		 * @param number $start
		 * @param number $stop
		 * @param string $withscore
		 * @return boolean|Ambigous <multitype:unknown , unknown>
		 */
		public function zrevrange($key, $start = 0, $stop = 10, $withscores = 'WITHSCORES')
		{
			$data = array('fun' => "zrevrange", "key" => $key, 'start' => $start, 'stop' => $stop, 'withscores' => 'WITHSCORES');
			$result = $this->_sendurldata($data);
			if ($result === 'false') {
				return false;
			}
			return $this->spilt_body($result);
		}

		/**
		 * 有序集合，查socore
		 * @param string $key
		 * @param string $value
		 */
		public function zscore($key, $value)
		{
			$data = array('fun' => "zscore", "key" => $key, "value" => $value);
			return $this->_sendurldata($data);
		}

		/**
		 * 有序集合，删除成员
		 * @param string $key
		 * @param string $member
		 */
		public function zrem($key, $value)
		{
			$data = array('fun' => 'zrem', 'key' => $key, 'value' => $value);
			return $this->_sendurldata($data);
		}

		/**
		 * 移除有序集 key 中，所有 score 值介于 min 和 max 之间(包括等于 min 或 max )的成员。
		 * @param string $key
		 * @param number $min
		 * @param number $max
		 */
		public function zremrangebyscore($key, $min, $max)
		{
			$data = array('fun' => 'zremrangebyscore', 'key' => $key, 'min' => $min, 'max' => $max);
			return $this->_sendurldata($data);
		}

		/**
		 * @param       $url
		 * @param array $data
		 * james add 2014
		 */
		private function _sendurldata($data = array())
		{
			//$curl_info = $this->wget($this->url . http_build_query($data));
			$curl_info = $this->wget($this->url, array(), $data);
			$curl_info['Body'] = chop($curl_info['Body']);
			//echo $this->url . http_build_query($data);
			$result = $curl_info['Body'];
			return $result === 'null' ? "" : $result;
		}

		/**
		 * 获取远程内容。
		 * james add
		 * @author Issac
		 * @param string $url 链接。
		 * @param array  $headers 可选，头信息，键值对。
		 * @param array  $form 可选，表单数据，键值对。
		 * @param array  $files 可选，要上传的文件表，键值对。
		 * @param array  $cookies 可选，COOKIES，键值对。
		 * @param string $referer 可选，链接来源。
		 * @param string $useragent 可选，用户代理，默认仿冒 Firefox 17.0。
		 * @param int    $timeout 可选，超时，默认不限制。
		 * @return array 错误返回 false。否则返回远程内容数组，包括：Head 头，Body 响应正文，Info 信息。
		 */
		private function wget($url = null, array $headers = null, array $form = null, array $files = null, array $cookies = null, $referer = null, $useragent = null, $timeout = 0)
		{ 
			if (function_exists('wget')) {
				return wget($url, $headers, $form, $files, $cookies, $referer, $useragent, $timeout);
			}
			static $error;
			if (func_num_args() === 0) {
				return $error;
			}
			if (!function_exists('curl_init')) {
				$error = array('Code' => 999, 'Error' => 'cURL extension is not install.');
				return false;
			}
			if (!isset($useragent)) {
				$useragent = 'Mozilla/5.0 (Windows NT 6.1; rv:17.0) Gecko/20100101 Firefox/17.0';
			}
			$options = array(
				CURLOPT_USERAGENT      => $useragent,
				CURLOPT_FAILONERROR    => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_ENCODING       => 'gzip, deflate',
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_URL            => $url,
				CURLOPT_HEADER         => true
			);
			if (isset($referer)) {
				$options[CURLOPT_REFERER] = $referer;
			}
			if (isset($headers)) {
				$hs = array();
				foreach ($headers as $k => $v) {
					$hs[] = $k . ': ' . $v;
				}
				$options[CURLOPT_HTTPHEADER] = $hs;
			}
			if (isset($cookies)) {
				$cs = array();
				foreach ($cookies as $k => $v) {
					$cs[] = $k . '=' . $v;
				}
				if (count($cs) > 0) {
					$options[CURLOPT_COOKIE] = implode(';', $cs);
				}
			}
			if (isset($timeout) && $timeout > 0) {
				$options[CURLOPT_TIMEOUT] = $timeout;
			}
			if (isset($form) || isset($files)) {
				$posts = array();
				if (isset($form)) {
					foreach ($form as $k => $v) {
						$posts[$k] = $v;
					}
				}
				if (isset($files)) {
					foreach ($files as $k => $f) {
						$posts[$k] = '@' . $f;
					}
				}
				if (count($posts) > 0) {
					$postfields = '';
					foreach ($posts as $k => $v) {
						$postfields .= $k . '=' . $v . '&';
					}
					$postfields = substr($postfields, 0, -1);
					$options[CURLOPT_POST] = true;
					$options[CURLOPT_POSTFIELDS] = $postfields;
				}
			}
			$result = false;
			$error = null;
			if ($conn = curl_init()) {
				if (curl_setopt_array($conn, $options)) {
					$response = curl_exec($conn);
					if ($response !== false && curl_errno($conn) === 0) {
						$result = array();
						$result['Url'] = $url;
						$result['Raw'] = $response;
						list($header, $body) = explode("\r\n\r\n", $response, 2);
						$status = explode("\r\n", $header, 2);
						$result['Status'] = array_shift($status);
						$result['Head'] = array_shift($status);
						$result['Body'] = $body;
						$result['Info'] = curl_getinfo($conn);
					} else {
						$error = array('Code' => curl_errno($conn), 'Error' => curl_error($conn));
					}
				} else {
					$error = array('Code' => 200, 'Error' => 'Options configuration incorrect.');
				}
				curl_close($conn);
			} else {
				$error = array('Code' => 201, 'Error' => 'cURL initialization failed.');
			}
			return $result;
		}

		private function spilt_body($body = "")
		{
			$newtemp = array();
			if (!$body) return $newtemp;
			$temp = explode("\n", $body);
			$len = count($temp);
			if ($len > 0) {
				for ($i = 0; $i < $len - 1; $i = $i + 2) {
					$newtemp[$temp[$i]] = isset($temp[$i + 1]) ? $temp[$i + 1] : '';
				}
			}
			return $newtemp;
		}
	}

?>
