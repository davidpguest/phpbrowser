<?php

//----------------application---------------

//extend standard time limit for pages that take a while to load
set_time_limit(300);

//page launched initially
$url = "http://www.bbc.co.uk";

//curl function to fetch requested page
function obtainpage($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
	curl_setopt($ch, CURLOPT_USERAGENT,'PHP text-only browser 1.0');
	$page = curl_exec($ch);
	if($page===false) {
		echo curl_error($ch);
	}
	curl_close($ch);
	return $page;
}

//pre-processing html to remove as much unwanted html as possible
function minimalist($text) {
	$text = stristr($text, "</body>", true);
	$text = stristr($text, "<body");
	$text = ltrim($text, ">");
	$search = array("/\r/", "/[\n\t]+/", 
			'/<script\b[^>]*>.*?<\/script>/i',
			'/<!--(.*)-->/Uis', 
			'/<style\b[^>]*>.*?<\/style>/i');
	$replace = array('',' ', '', '', '', '');
	$text = preg_replace($search, $replace, $text);
	$text = str_replace("</li>", "&nbsp;", $text);
	return $text;
}

//function to parse html tags for particular attributes
function getattr($text, $attr) {
	$text = str_replace(array('"', "'"), array(' ', ' '), $text);
	$text .= " "; $attr .= "=";
	$text = stristr($text, $attr);
	$text = ltrim(substr($text, strlen($attr)));
	if(stristr($text, ' ')) {
		$text = stristr($text, ' ', true);
	}
	return $text;
}

//encode links so they can be safely passed in a query string
function packlink($link) {
	return strtr(base64_encode($link), '+/=', '._-');
}

//decode them to use later
function unpacklink($link) {
	return base64_decode(strtr($link, '._-', '+/='));
}

//chop text up into segments using links as boundaries
function gettagbit($text) {
	$output = "";
	$images = array();
	$links = array();
	$text = str_replace(array("[", "]"), array("(", ")"), $text);	
	$tagside = ltrim(stristr($text, "<"), "<");
	$tagname = stristr($tagside, ">", true);
	if(stristr($tagname, " ")) {
		$tagname = stristr($tagname, " ", true);
	} 
	$inside = stristr($tagside, ">", true);
	$outside = ltrim(stristr($tagside, ">"), ">");
	$alloweds = array("p", "ul", "ol");
	$specials = array("a", "img");
	if(in_array(ltrim($tagname, "/"), $alloweds)) {
		$tagname = str_replace(array("ul", "ol"), array("p", "p"), $tagname);
		$output .= "<$tagname>";
		if(substr($tagname, 0, 1) == "/") {
			$output .= "";
		}
	} elseif($tagname=="a") {
		$href = getattr($inside, "href");
		if(substr($href,0,4) != "http") {
			$href = $GLOBALS['baseurl'] . "/" . ltrim($href, "/. ");
		}
		$link = "?s=" . packlink($href);
		$output .= " <a href=\"$link\">";
		
	} elseif($tagname=="/a") {
		$output .= "</a> ";
	} elseif($tagname!="a" && $tagname!="/a") {
		$output .= " ";
	} 
	if(strstr($outside, "<")) {
		$addition = trim(stristr($outside, "<", true));
		$output .= $addition;
		$text = stristr($outside, "<");
	} else {
		$output .= trim($outside) ;
		$text="";
	}
	if(count($images)>0) {
		$output .= "<p>Images<br />" . implode("<br />", $images) . "</p>";
	}
	$output = str_replace(array(" .", " ,", " ?", " !", " :", " </a"), array(".", ",", "?", "!", ":", "</a"), $output);
	$output = preg_replace('/\s*<\/a>/', '</a>', $output);
	$output = rtrim($output);
	return array($output, $text);
}

//get base URL from a supplied address
function basify($url) {
	$url = rtrim($url, "/");
	$url = str_replace("//", "**", $url);
	if(stristr($url, "/")) {
		$url = stristr($url, "/", true);
	}
	if(stristr($url, "?")) {
		$url = stristr($url, "?", true);
	}
	if(stristr($url, "#")) {
		$url = stristr($url, "#", true);
	}
	$url = str_replace("**", "//", $url);
	return $url;
}

//sanitise url before displaying on the screen
function cleanurl($url) {
	$url = strip_tags($url);
	$url = filter_var($url, FILTER_SANITIZE_URL);
	$url = htmlspecialchars($url);
	return $url;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="X-UA-Compatible" content="IE=Edge,chrome=1" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />   
<meta charset="utf-8" />   
<title>text-only browser</title>
<style type="text/css">
body { font-family: monospace; }
div.container { text-align: center; }
div.body { margin: 0 auto; width: 90%; max-width: 800px; text-align: left; }
form { display: inline !important; }
input { display: inline; background: #fff;  border: 1px solid #eee; padding: 5px; }
a:link, a:active, a:visited, a:hover { color: #00f; }
</style>
</head>
<body>
<div class="container">
<div class="body">
<br />
<?php

//-------------page display----------------

//handle the display on the screen
$me = $_SERVER['PHP_SELF'];
if(isset($_REQUEST["s"])) {
	$url = unpacklink($_REQUEST["s"]);
} elseif(isset($_REQUEST["p"])) {
	$url = cleanurl($_REQUEST["p"]);
} elseif(isset($_REQUEST["q"])) {
	$url = "https://duckduckgo.com/html?q=" . cleanurl($_REQUEST["q"]);
} elseif(isset($_REQUEST["a"])) {
	$url = cleanurl($_REQUEST["a"]);
	if(!strstr($url, "://")) { $url = "http://$url"; }
}
$baseurl = basify($url);
$base=obtainpage($url);
$text = minimalist($base);
echo "<p><b>$url</b></p>";
if(isset($_REQUEST["f"])) {
	echo "<form method=\"get\" action=\"$me?p=http://duckduckgo.com/html\">
		<input name=\"q\" type=\"text\" style=\"width:50%; height: 20px;\" />&nbsp;
		<input name=\"sub\" type=\"submit\" value=\"?\" />
		</form>";
} elseif(in_array("a", array_keys($_REQUEST))) {
	echo "<form method=\"get\" action=\"$me\">
		<input name=\"a\" type=\"text\" style=\"width:80%; height: 20px;\" value=\"$url\" />&nbsp;
		<input name=\"sub\" type=\"submit\" value=\"...\" />
		</form>";
}
echo "<p><a href=\"$me\">Home</a> <a href=\"$me?a=\">Address</a> <a href=\"$me?f=1\">Search</a></p>";
flush();
while(strstr($text, "<") && strstr($text, ">")) {
	$responsebits = gettagbit($text);
	echo $responsebits[0]; flush();
	$text = $responsebits[1];
}

?>
<br />
</div>
</div>
</body>
</html>
