<?php
if (isset($_REQUEST['url'])) {
	$url = htmlspecialchars(strip_tags($_REQUEST['url']));
	$isMulti = isset($_REQUEST['isMulti']) ? true : false;
	//使用
	$fetch = new GoogleFontsFetch();

	if ($isMulti) {
		$urlArr = explode(";", $url);
		//验证URL的正确性
		foreach ($urlArr as $k => $val) {
			if (strpos($val, $fetch->gfOriginUrl) == -1) {
				unset($urlArr[$k]); //如果URL不正确,直接删除这个URL
			}
		}
		if (count($urlArr) < 1) {
			echo "URL解析失败!";
			return;
		}
		$zipfile = $fetch->fetchFont($urlArr);
		$fetch->fileDown($zipfile);
	} else if ($url != "" && strpos($url, $fetch->gfOriginUrl) != -1) {
		$zipfile = $fetch->fetchFont($url);
		$fetch->fileDown($zipfile);
	} else {
		echo "URL解析失败!";
		return;
	}
}
echo "请输入googlefonts的URL地址";
