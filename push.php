<?php
    if (!defined('BASEPATH')) {
        exit('No direct script access allowed');
    }

    set_time_limit(100); //本页最大执行时间
    header("Content-type: text/html; charset=utf-8");
    define("WEBPATH", str_replace("\\", "/", __DIR__));

    class Push extends CI_Controller
    {
        public $public_error = array('status' => false, 'code' => '', 'data' => '', 'msg' => '');

        public function __construct()
        {
            parent::__construct();
        }

        /**
         * 推送消息
         * james add 20141117
         * usertype   用户类型 1 用户  3 商户 4 合作商
         * 商户：joint库里的 的 t_seller_account
         * 合作商：joint库里的 的  t_staff
         */
        public function addMsg()
        {
            /**
             * 需发送的数据信息
             */
            $p_uid = trim($this->input->get_post('uid'));
            $p_usertype = intval($this->input->get_post('usertype'));
			if($p_uid){				
				$temp_p_uid = json_decode($p_uid, true); 
				if(!is_null($temp_p_uid)){
					$p_uid = $this->tiqu_uid($temp_p_uid);
				}
			} 
            $p_usertype <= 0 && $p_usertype = 1;

            //如果regcity的id 存在,$p_uid以regcity为准
            $regcity = intval($this->input->get_post('regcity'));
            if ($regcity > 0) {
                $p_uid = array();
                $GLOBALS['db_user'] = $this->user = $this->load->database('user', true);
                switch ($p_usertype) {
                    case 1:
                        //用户
                        $this->load->model('M_user');
                        $param = array("regcity" => $regcity);
                        $temp_puid_count = $this->M_user->getCount($param);
                        if (!$temp_puid_count) {
                            $this->public_error['msg'] = '未找到该城市ID' . $regcity . '下的用户!';
                            backJson($this->public_error);
                        }

                        //按2000,批量取数据,并处理数据
                        $temp_puid_count_mod = 4000;
                        for ($i = 1; $i <= ceil($temp_puid_count / $temp_puid_count_mod);$i++) {
                            $temp_result = $this->M_user->getList($param, $i,$temp_puid_count_mod);
                            $p_uid[] = $this->tiqu_uid($temp_result['data']);
                        }
                        break;
                    case 3:
                        //商家
                        $this->public_error['msg'] = '商家版暂未做按城市发送功能!';
                        backJson($this->public_error);
                        break;
                    case 4:
                        //合作商
                        $this->public_error['msg'] = '合作商版暂未做按城市发送功能!';
                        backJson($this->public_error);
                        break;
                }
            }

            /**
             * 需发送的数据内容
             */
            $msgData = array('tid'         => $this->input->get_post('tid') ? (int)$this->input->get_post('tid') : 0,
                             'title'       => $this->input->get_post('title') ? $this->input->get_post('title') : "",
                             'content'     => $this->input->get_post('content') ? $this->input->get_post('content') : "",
                             'type'        => $this->input->get_post('type') ? (int)$this->input->get_post('type') : 1,
                             'action'      => $this->input->get_post('action') ? $this->input->get_post('action') : "",
                             'iosaction'   => $this->input->get_post('iosaction') ? $this->input->get_post('iosaction') : "",
                             'sdate'       => date("Y-m-d H:i:00"),
                             'tdate'       => $this->input->get_post('tdate') ? strtotime($this->input->get_post('tdate')) : time(),
                             'edate'       => $this->input->get_post('edate') ? strtotime($this->input->get_post('edate')) : time() + 60 * 30,
                             'client'      => $this->tiqu_client($p_usertype),
                             'remind'      => $this->input->get_post('remind') ? (int)$this->input->get_post('remind') : 0,
                             'contenttype' => $this->input->get_post('contenttype') ? (int)$this->input->get_post('contenttype') : 1
            );

            if (!isset($msgData['content']) || !$msgData['content']) {
                $this->public_error['msg'] = '未传content参或为发送的内容为空!';
                backJson($this->public_error);
            }

            ($msgData['tdate'] < time()) && $msgData['tdate'] = time();
            ($msgData['edate'] <= $msgData['tdate']) && $msgData['edate'] = time() + 60 * 30;
            $msgData['tdate'] = date("Y-m-d H:i:00", $msgData['tdate']);
            $msgData['edate'] = date("Y-m-d H:i:00", $msgData['edate']);

            $msgData['usertype'] = $p_usertype;
            !$msgData['action'] && $msgData['action'] = "";

            !$msgData['type'] && $msgData['type'] = 1;
            if ($msgData['type'] == 3) {
                $msgdata_action = json_decode($msgData['action'], true);
                if (!$msgdata_action) {
                    $this->public_error['msg'] = 'type为活动时，action应当为json格式！';
                    backJson($this->public_error);
                }
                $msgData['action'] = $msgdata_action;
            }
            $msgData['iosaction'] = json_decode($msgData['iosaction'], true);
            !$msgData['iosaction'] && $msgData['iosaction'] = "";

            //push
            $data = array('content' => $msgData, 'usertype' => $p_usertype);

            if (!$p_uid || !is_array($p_uid)) {
                $data['p_uid'] = $p_uid;
                $this->addTask($data);
            } else {
                foreach ($p_uid as $vo) {
                    $data['p_uid'] = $vo;
                    $this->addTask($data);
                }
            }

            $this->public_error['status'] = true;
            $this->public_error['msg'] = "已成功创建推送队列,系统会跟据您的设定的条件推送!!";

            backJson($this->public_error);
        }

        /**
         * ios 首次安装 或 登录检测没读消息
         */
        public function iosGetPush()
        {
            $data = array("uid"      => intval($this->input->get_post('uid')) > 0 ? (int)$this->input->get_post('uid') : 0,
                          "usertype" => intval($this->input->get_post('usertype')) > 1 ? (int)$this->input->get_post('usertype') : 1,
                          "fd"       => trim($this->input->get_post('iostoken')) ? trim($this->input->get_post('iostoken')) : "",
                          "config"   => array("start_stime"      => trim($this->input->get_post('stime')) ? trim($this->input->get_post('stime')) : "",
                                              "stop_etime" => intval($this->input->get_post('time_count')) > 0 ? intval($this->input->get_post('time_count')) : 0
                          )
            );

            if (empty($data['fd']) || $data['fd'] == "ios") {
                $this->public_error['status'] = true;
                backJson($this->public_error);
            }
	        
            $data['tasktype'] = "checkiosconect";
            $this->addTask($data);
            $this->public_error['status'] = true;
            backJson($this->public_error);
        }

        /**
         * @param array  $p_uid
         * @param string $u_flg
         * @return string
         * 提取UID
         */
        private function tiqu_uid($p_uid = array(), $u_flg = "uid")
        {
            if (!$p_uid || !is_array($p_uid)) {
                return $p_uid;
            }
            $temp_p_uid = array();
            foreach ($p_uid as $vo) {
                $vo = (array)$vo;
                if (isset($vo[$u_flg])) {
                    $vo = intval($vo[$u_flg]);
                } else {
                    $vo = intval($vo);
                }
                $vo > 0 && $temp_p_uid[] = $vo;
            }
            $p_uid = array_unique($temp_p_uid);
            array_filter($p_uid);
            return $p_uid ? implode(",", $p_uid) : "";
        }

        /**
         * @param int $usertype
         * @return mixed
         * 提取client 安卓解析时,按此规则解析的
         */
        private function tiqu_client($usertype = 0)
        {
            $client = array("0" => 1, //1 客户端
                            "1" => 1, //1 客户端
                            "3" => 2, //商户客户端
                            "4" => 3 //合作商客户端
            );
            return isset($client[$usertype]) ? $client[$usertype] : $client[0];
        }

        /**
         * @param array $data
         * @return bool
         */
        private function addTask($data = array())
        {
            if (!__SWOOLE_TASK_HOST__ || !$data) {
                return false;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://" . __SWOOLE_TASK_HOST__ . ":9601");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POST, 1); //设置为POST方式
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, array("web_key" => "sdsdsdsdsdsdwewewewesd232323232323", "data" => json_encode($data, JSON_UNESCAPED_UNICODE))); //POST数据
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }
