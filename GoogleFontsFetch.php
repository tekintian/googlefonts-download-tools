<?php
/**
 * GoogleFonts web字体分析下载工具, 仅下载URL中指定的字体和资源
 *
 * 根据调用的URL 自动分析并下载所有想的字体和样式问题引用!
 * 主要针对已经存在的googlefonts引用的本地化下载
 *
 *
 * 其他资源
 * https://github.com/google/fonts Googlefonts官方源码
 *
 * https://www.googlefonts.cn/  第三方的国内谷歌字体ttf文件下载站
 *
 * @Author: tekintian
 * @Date:   2020-05-19 23:23:07
 * @Last Modified 2022-05-31
 */
ini_set('memory_limit', '1024M'); // 临时设置最大内存占用为3G
set_time_limit(0); // 设置脚本最大执行时间 为0 永不过期
defined('CURR_DIR') or define('CURR_DIR', __DIR__);

include_once './test_helper.php';
require_once './http_helper.php';

//GoogleFonts fetch tools
class GoogleFontsFetch {
	private $tryCount = 0; //重试计数
	private $tryMax = 2; //最大重试次数
	public $fontsDir = CURR_DIR . "/fonts/"; //字体保存目录
	public $zipDir = "/zip/"; //字体zip保存目录
	public $fprefix = "https://fonts.gstatic.com/s/"; //字体保存目录
	public $ua = "Mozilla/5.0 (Macintosh; Intel Mac OS X 13_1_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36 Edg/87.0.664.66";
	public $gfOriginUrl = "https://fonts.googleapis.com/css?family=";
	/**
	 * summary
	 */
	public function __construct() {
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$this->ua = $_SERVER['HTTP_USER_AGENT'];
		}
	}
	/**
	 * 批量获取
	 * @param  array  $urlArr [description]
	 * @return [type]         [description]
	 */
	function fetchFontMulti(array $urlArr) {
		$zipName = $this->getZipFilenameMulti($urlArr);
		$zipFile = CURR_DIR . $this->zipDir . $zipName;
		if (is_file($zipFile)) {
			//如果文件已经存在,直接返回
			return $zipFile;
		}

		$allFiles = [];
		foreach ($urlArr as $url) {
			$retFiles = $this->fetchFont($url, true);
			sleep(1); //休眠1秒,防止太快被K
			$allFiles = array_merge($allFiles, $retFiles); //合并所有的文件数组
		}
		return $this->createZipMulti($allFiles, $zipName);
	}
	/**
	 * 分析并获取googlefronts文件
	 * @param  [type] $url [description]
	 * @return [type]      返回zip文件路径
	 */
	function fetchFont($url, $isMulti = false) {
		$zipName = $this->getZipFilename($url);
		$zipFile = CURR_DIR . $this->zipDir . $zipName;
		if (is_file($zipFile) && !$isMulti) {
			//如果文件已经存在,直接返回
			return $zipFile;
		}
		//分析url
		preg_match_all('/family=(.*?):/isu', $url, $fn);
		$fontCss = isset($fn[1][0]) ? strtolower($fn[1][0]) . ".css" : "gfonts.css";
		// fetch google front 这里的UA不能随机,否则结果肯能不是你预期的!
		$raw = my_curl_request($url, [], "GET", get_client_ip(), $this->ua); //获取数据
		//删除url中的 https://fonts.gstatic.com/s/
		$sraw = str_replace($this->fprefix, "", $raw);
		//如果目录不存在,创建
		if (!is_dir($this->fontsDir)) {
			mkdir($this->fontsDir, 0777, true);
		}
		//保存css文件
		file_put_contents($this->fontsDir . $fontCss, $sraw);

		//定义返回的文件数组
		$retFiles = array($this->fontsDir . $fontCss);
		// 获取css文件中的字体文件列表
		preg_match_all('/url\((.*?)\)/isu', $raw, $flist);
		$fileCount = isset($flist[1]) ? count($flist[1]) : 0;
		if ($fileCount > 1) {
			echo "$fontCss file include $fileCount files, begin download... \n";
			foreach ($flist[1] as $k => $val) {
				$this->saveToLocal($val);
				$retFiles[] = $this->fontsDir . substr($val, strlen($this->fprefix));
			}

		}

		$fdir = isset($flist[1][0]) ? $this->getFileDir($flist[1][0]) : "/";
		//固若是多个URL处理,直接返回数组
		if ($isMulti) {
			//返回的是 fdir=>[]
			return array($fdir => $retFiles);
		}
		//创建压缩文件并返回压缩文件全路径
		return $this->createZip($retFiles, $zipName, $fdir);
	}

	/**
	 * 获取字体文件的存储dir
	 * @param  [type] $furl [description]
	 * @return [type]       返回下载的字体文件的存放相对目录, 如果对应目录不存在,这会自动创建
	 */
	function getFileDir($furl) {
		preg_match_all('/https:\/\/fonts\.gstatic\.com\/s\/(.*?)\/(.*?)\/(.*)/isu', $furl, $fm);
		$folder = "";
		if (count($fm) > 1) {
			$folder = $fm[1][0] . "/" . $fm[2][0] . "/";
			if (!is_dir($this->fontsDir . $folder)) {
				mkdir($this->fontsDir . $folder, 0777, true);
			}
		}
		return $folder;
	}
	/**
	 * 保存文件到本地
	 * @param  [type] $furl [description]
	 * @return [type]       [description]
	 */
	function saveToLocal($furl) {
		//匹配文件名称,防止重复下载
		preg_match_all('/https:\/\/fonts\.gstatic\.com\/s\/(.*?)\/(.*?)\/(.*)/isu', $furl, $fm);

		$file = $fname = (count($fm) > 3) ? $fm[1][0] : ""; //字体名称获取
		if (count($fm) > 3) {
			$fver = $fm[2][0];
			$ffname = $fm[3][0];
			if (!is_dir($this->fontsDir . $fname . "/" . $fver)) {
				mkdir($this->fontsDir . $fname . "/" . $fver, 0777, true);
			}
			$file = $this->fontsDir . $fname . "/" . $fver . "/" . $ffname;
		}
		if (!is_file($file) && $file != "") {
			$data = file_get_contents($furl);
			file_put_contents($file, $data);
		}
	}
	/**
	 * 生成压缩文件
	 * @param  [type] $arrfiles [description]
	 * @param  [type] $fileName [description]
	 * @param  [type] $fdir 文件存放目录
	 * @return [type]           返回带有绝对路径的压缩文件名 如如果失败返回 FALSE
	 */
	function createZip($arrfiles, $fileName, $fdir) {
		$zipName = CURR_DIR . $this->zipDir . $fileName;
		$zip = new ZipArchive();
		if ($zip->open($zipName, ZIPARCHIVE::CREATE) !== TRUE) {
			return FALSE;
		}
		//增加空目录到压缩包, 因为文件都是存放到一个目录下的,所以只用执行一次即可
		if ($fdir != "") {
			$zip->addEmptyDir($fdir);
		}
		//将文件添加到压缩包
		foreach ($arrfiles as $k => $path) {
			//判断文件是否存在
			if (is_file($path)) {
				//这里.css文件属于压缩包的顶层文件,其他的文件都需要添加压缩文件的路径
				$filename = $this->fileExt($path) == "css" ? basename($path) : $fdir . basename($path);
				//将文件放到压缩包中
				$zip->addFile($path, $filename); //把文件加入到压缩包中
			}
		}
		$zip->close();
		return $zipName;
	}
	/**
	 * 多个字体文件
	 * @param  [type] $allfiles [description]
	 * @return [type]           [description]
	 */
	function createZipMulti($allfiles, $fileName) {
		$zipName = CURR_DIR . $this->zipDir . $fileName;
		$zip = new ZipArchive();
		if ($zip->open($zipName, ZIPARCHIVE::CREATE) !== TRUE) {
			return FALSE;
		}

		//将文件添加到压缩包
		foreach ($allfiles as $k => $data) {
			$zip->addEmptyDir($k); //这里的k即为目录
			// 循环添加文件
			foreach ($data as $index => $path) {
				//判断文件是否存在
				if (is_file($path)) {
					//第一个是.css文件,不添加目录
					if ($index == 0) {
						$zip->addFile($path, basename($path));
					} else {
						//非第一个都添加目录
						$zip->addFile($path, $k . basename($path)); //把文件加入到压缩包中
					}
					// //这里.css文件属于压缩包的顶层文件,其他的文件都需要添加压缩文件的路径
					// $filename = $this->fileExt($path) == "css" ? basename($path) : $k . basename($path);
					// //将文件放到压缩包中
					// $zip->addFile($path, $filename);
				}
			}
		}
		$zip->close();
		return $zipName;
	}
	/**
	 * 文件下载
	 * @param  [type] $file     文件路径
	 * @param  string $filename 下载时间重新命名的文件名
	 * @param  string $data     下载文件填装的数据内容
	 * @return [type]           [description]
	 */
	public function fileDown($file, $filename = '', $data = '') {
		if (!$data && !is_file($file)) {
			exit;
		}
		$filename = $filename ? $filename : basename($file);
		$filetype = $this->fileExt($filename);
		$filesize = $data ? strlen($data) : filesize($file);
		ob_end_clean();
		@set_time_limit(0);
		if (strpos($this->ua, 'MSIE') !== false) {
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
		} else {
			header('Pragma: no-cache');
		}
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Encoding: none');
		header('Content-Length: ' . $filesize);
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Content-Type: ' . $filetype);
		if ($data) {
			echo $data;
		} else {
			//输出文件 https://www.php.net/manual/en/function.readfile.php
			readfile($file);
		}
		exit;
	}
	/**
	 * 获取文件扩展名
	 * @param  [type] $filename [description]
	 * @return [type]           [description]
	 */
	public function fileExt($filename) {
		return strtolower(trim(substr(strrchr($filename, '.'), 1)));
	}
	/**
	 * 获取压缩文件名
	 * 字体文件名小写_{当前url md5}.zip
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	public function getZipFilename($url) {
		preg_match_all('/family=(.*?):/isu', $url, $fn);
		$fontName = isset($fn[1][0]) ? strtolower($fn[1][0]) : "googlefonts";
		return $fontName . "_" . md5($url) . ".zip";
	}
	/**
	 * 获取压缩文件名 多个url
	 * @param  array  $arrurl [description]
	 * @return [type]         [description]
	 */
	public function getZipFilenameMulti(array $urlArr) {
		$fname = "";
		foreach ($urlArr as $url) {
			preg_match_all('/family=(.*?):/isu', $url, $fn);
			$fname .= isset($fn[1][0]) ? strtolower($fn[1][0]) . "_" : "_";
		}
		return $fname . md5(implode(";", $urlArr)) . ".zip";
	}
	/**
	 * 判断当前的运行环境是否是cli模式
	 * @return boolean  是:true 不是:false
	 */
	public function isCli() {
		return preg_match("/cli/i", php_sapi_name()) ? true : false;
	}
}
