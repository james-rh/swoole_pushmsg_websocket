#! /bin/bash
ps -eaf |grep "server.php" | grep -v "grep"| awk '{print $2}'|xargs kill -9
/usr/local/php/bin/php server.php 
