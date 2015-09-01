# swoole_pushmsg_websocket

纯php扩展swoole 1.7.18 的websocket消息推送(安卓及ios设备) 

swoole 为当前最新版本 1.7.18  
在centos 6.5 及 乌班图14.4server 测试通过. 其余linux服务器未知

本版本涵盖 用户版, 商户版,合作商版 的IOS及安卓APP推送. 

1. 开放端口 9501(仅供外网) 9002(内网端口)
2. redis存储是通过lua .  若无LUA,请更换server/redis_lib.php类
3. server.php 是服务文件
4. push.php  是基于 CI框架文件
5. server.php关闭和重启在start.sh里. 请自行添加swoole的平滑重启或关闭 swoole_reload
6. server.php 可以建立集群环境. 自行修改onMessage 增加主机标识
7. websocket 连接时, 最好自行再增一个客户端的唯一标识.
8. ios推磅证书已删了. 请去找IOS开发人员取. 同时,注意IOS推送地址有开发和生产之分. 注意更改server/config.php 里配制 

 

