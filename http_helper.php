<?php

/**
 * @Author: Tekin
 * @Date:   2020-03-13 15:03:28
 * @Last Modified 2022-05-31
 */
/**
 * Curl 伪造 IP 并从指定网址获取数据
 * @param $url 接口地址
 * @param $ip 伪造的 IP
 * @return 抓取到的内容
 */
function my_curl_request($url, $data = [], $method = 'GET', $ip = '', $ua = '') {
	$ch = curl_init(); // Curl 初始化
	$timeout = 30; // 超时时间：30s
	curl_setopt($ch, CURLOPT_URL, $url); // 设置 Curl 目标
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); // 设置抓取超时时间
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 跟踪重定向
	if ($method == 'POST') {
		curl_setopt($ch, CURLOPT_POST, 1);
		!empty($data) && curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Encoding: gzip, deflate'));
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); //这个是解释gzip内容
	curl_setopt($ch, CURLOPT_HEADER, 0); // 显示返回的Header区域内容

	$cookie_file = __DIR__ . '/tmp.cookie';
	is_file($cookie_file) && curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); // 读取上面所储存的Cookie信息
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // 存放Cookie信息的文件名称

	curl_setopt($ch, CURLOPT_REFERER, $url); // 伪造来源网址
	!empty($ip) && curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:' . $ip, 'CLIENT-IP:' . $ip)); //伪造IP
	curl_setopt($ch, CURLOPT_USERAGENT, $ua); // 伪造ua

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	$str = curl_exec($ch);
	curl_close($ch); // 结束 Curl

	// 自动检测并转换编码为UTF8
	if (mb_detect_encoding($str, "UTF-8, ISO-8859-1, GBK") != "UTF-8") {
		// 解决特殊汉字转码时的问题 ，如： 囧
		$_str = @iconv("GBK", "UTF-8", $str);

		if (empty($_str)) {
			error_log("url:" . $url . "\n 数据：" . $str . "\n\r", 3, __DIR__ . "/logs/curl_iconv_error_" . date('Y-m-d') . ".log");
		}
		//如果转换失败，直接返回
		return empty($_str) ? $str : $_str;
	} else {
		return $str;
	}

}
/**
 * 获取客户端IP地址
 * @param  integer $type 返回类型 0 返回IP地址; 1 返回IPV4地址数字
 * @return [type]        [description]
 */
function get_client_ip($type = 0) {
	if ($_SERVER["HTTP_CLIENT_IP"] && strcasecmp($_SERVER["HTTP_CLIENT_IP"], "unknown")) {
		$ip = $_SERVER["HTTP_CLIENT_IP"];
	} else {
		if ($_SERVER["HTTP_X_FORWARDED_FOR"] && strcasecmp($_SERVER["HTTP_X_FORWARDED_FOR"], "unknown")) {
			// $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
			$_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim(current($_ip));
		} else {
			if ($_SERVER["REMOTE_ADDR"] && strcasecmp($_SERVER["REMOTE_ADDR"], "unknown")) {
				$ip = $_SERVER["REMOTE_ADDR"];
			} else {
				if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) {
					$ip = $_SERVER['REMOTE_ADDR'];
				} else {
					$ip = "unknown";
				}
			}
		}
	}
	// IP地址合法验证
	$long = sprintf("%u", ip2long($ip));
	$ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
	return $ip[$type];
}
/**
 * 编码转换
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function convert_to_utf8($str) {
	if (mb_detect_encoding($str, "UTF-8, ISO-8859-1, GBK") != "UTF-8") {
		// 解决特殊汉字转码时的问题 ，如： 囧
		return iconv(mb_detect_encoding($str, mb_detect_order(), false), "UTF-8", $str);
	} else {
		return $str;
	}
}
