# google fonts download local 谷歌字体引用资源分析下载
目前项目总有用到googlefonts, 为了保险起见打算将支付下载到本地, 网上搜了一圈居然没有一款工具可以自动分析并下载自己想要的相关字体资源文件,  所以才有了这个工具!

根据调用URL智能分析并下载相关的资源到本地! 

使用方法,
修改 fetch 中的$gfarr数组内容,将要下载的google字体调用URL放到这里,然后执行
~~~sh
php fetch
~~~

你指定的的字体的相关资源文件都将被下载并打包为zip文件
将你的引用地址修改为
原来的
url("https://fonts.googleapis.com/css?family=Poppins:300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i");
修改为
url("poppins.css");

即可!
Poppins 为你调用功能的字体名称,下载后的字体样式文件名统一小写, 实际的名称和路径根据你的实际修改即可



~~~php
require_once 'GoogleFontsFetch.php';
//使用
$fetch = new GoogleFontsFetch();

// 把要下载的google fronts的url地址放到这里, 然后执行  php GoogleFontsFetch.php
$gfarr = array(
	"https://fonts.googleapis.com/css?family=Poppins:300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i",
	"https://fonts.googleapis.com/css?family=Open+Sans:400,400i,600,600i,700,700i&display=swap&subset=cyrillic-ext",
	"https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&display=swap",
);

if (count($gfarr) == 1) {
	$zipfile = $fetch->fetchFont($gfarr[0]);
} else {
	$zipfile = $fetch->fetchFontMulti($gfarr);
}

//如果命令行执行,返回压缩文件路径,否则下载文件
if ($fetch->isCli()) {
	echo $zipfile;
} else {
	$fetch->fileDown($zipfile);
}
~~~
