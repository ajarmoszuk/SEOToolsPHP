<?php
ini_set('display_errors', '0');
if (array_search(__FILE__, get_included_files()) === 0) { header("HTTP/1.1 403 Unauthorized" ); die('Unauthorized. Direct access is not allowed.'); }
if (!file_exists(getcwd()."/include/simple_html_dom.php")) { die('simple_html_dom.php was not found. Please re-download version 1.5 at <a href="http://sourceforge.net/projects/simplehtmldom/files/">simplehtmldom.sourceforge.net</a>'); }
libxml_use_internal_errors(true);

class getGeneral extends DOMDocument {
	public function pingHost($host) {
		if (preg_match('/^https?:\/\//', $host)) { $host = preg_replace('/^https?:\/\//', '', $host); }
		$fsock = fsockopen($host, 80, $errno, $errstr, 6);
		$fsockssl = fsockopen($host, 443, $errno, $errstr, 6);
        if (!$fsock) {
        	if (!$fsockssl) {
                return false;
                $host = "www.".$host;
                if (!$fsock) {
        			if (!$fsockssl) {
        				return false;
        			} else {
        				return true;
        			}
        		} else {
        			return true;
        		}
        	} else {
        		return true;
        	}
        } else {
                return true;
        }
	}

	public function getDoctype($sitename) {
		$this->loadHTMLFile($sitename);
		$name = $this->doctype->publicId;
		$name = preg_replace('~.*//DTD(.*?)//.*~', '$1', $name);
		if ($name) {
			return $name;
		} else {
			return 'HTML';
		}
	}

	public function getEncoding($sitename) {
		$html = file_get_html($sitename);
		$el=$html->find('meta[content]',0);
		$fullvalue = $el->content;
		preg_match('/charset=(.+)/', $fullvalue, $matches);
		$encoding = substr($matches[0], strlen("charset="));
		if ($encoding) {
			return $encoding;
		} else {
			return 'UTF-8';
		}

	}

	public function getFavicon($sitename) {
		$domain = parse_url($sitename, PHP_URL_HOST);
		return '<img src="http://www.google.com/s2/favicons?domain='.$domain.'" alt="Favicon"/>';
	}

	public function getAlexaRank($sitename) {
		$xml = simplexml_load_file('http://data.alexa.com/data?cli=10&dat=snbamz&url='.$sitename);
		$rank=isset($xml->SD[1]->POPULARITY)?$xml->SD[1]->POPULARITY->attributes()->TEXT:0;
		$web=(string)$xml->SD[0]->attributes()->HOST;
		return $rank;
	}

	public function getPageSize($sitename) {
		static $regex = '/^Content-Length: *+\K\d++$/im';
    	if (!$fp = @fopen($sitename, 'rb')) {
        	return false;
    	}
    	if (isset($http_response_header) && preg_match($regex, implode("\n", $http_response_header), $matches)) {
        	return (int)$matches[0];
    	}
    	$final = strlen(stream_get_contents($fp));
    	return substr(($final/1024), 0, 7);
	}

	public function checkGoogleAnalytics($sitename) {
		$file = file_get_contents($sitename);
		$ua_regex = "/UA-[0-9]{5,}-[0-9]{1,}/";
       	$result = preg_match_all($ua_regex, $file, $match);
       	if (!$result) {
       		return 'Not Found.';
       	}
       	$getua = array_values(array_filter(array_slice($match,0)));
       	$getuafinal = implode(", ", $getua[0]);
       	if ($getuafinal) {
       		return 'Found. UID: '.$getuafinal;
       	}
	}

	public function getGooglePR($sitename) {
		$query = "http://toolbarqueries.google.com/tbr?client=navclient-auto&ch=" . $this->CheckHash($this->HashURL($sitename)) . "&features=Rank&q=info:" . $sitename . "&num=100&filter=0";
		$data = file_get_contents($query);
		$pos = strpos($data, "Rank_");
			if ($pos === false) {
				return '0';
			} else {
				$pagerank = substr($data, $pos + 9);
				return $pagerank;
			}
		}

	public function getPageTitle($sitename) {
    $str = file_get_contents($sitename);
    	if(strlen($str)>0){
        	preg_match("/\<title\>(.*)\<\/title\>/",$str,$title);
        	return $title[1];
    	}
	}

	public function getOther($sitename, $tag) {
		$tags = get_meta_tags($sitename);
		if ($tags[$tag]) {
			return $tags[$tag];
		} else {
			return 'None';
		}
	}

	private function StrToNum($Str, $Check, $Magic) {
		$Int32Unit = 4294967296;
		$length = strlen($Str);
			for ($i = 0; $i < $length; $i++) {
				$Check*= $Magic;
				if ($Check >= $Int32Unit) {
					$Check = ($Check - $Int32Unit * (int)($Check / $Int32Unit));
					$Check = ($Check < - 2147483648) ? ($Check + $Int32Unit) : $Check;
				}
				$Check+= ord($Str{$i});
			}
		return $Check;
	}

	private function HashURL($String) {
			$Check1 = $this->StrToNum($String, 0x1505, 0x21);
			$Check2 = $this->StrToNum($String, 0, 0x1003F);
			$Check1 >>= 2;
			$Check1 = (($Check1 >> 4) & 0x3FFFFC0) | ($Check1 & 0x3F);
			$Check1 = (($Check1 >> 4) & 0x3FFC00) | ($Check1 & 0x3FF);
			$Check1 = (($Check1 >> 4) & 0x3C000) | ($Check1 & 0x3FFF);
			$T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) << 2) | ($Check2 & 0xF0F);
			$T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000);
			return ($T1 | $T2);
	}

	private function CheckHash($Hashnum) {
			$CheckByte = 0;
			$Flag = 0;
			$HashStr = sprintf('%u', $Hashnum);
			$length = strlen($HashStr);
			for ($i = $length - 1; $i >= 0; $i--) {
				$Re = $HashStr{$i};
				if (1 === ($Flag % 2)) {
					$Re+= $Re;
					$Re = (int)($Re / 10) + ($Re % 10);
				}

				$CheckByte+= $Re;
				$Flag++;
			}

			$CheckByte%= 10;
			if (0 !== $CheckByte) {
				$CheckByte = 10 - $CheckByte;
				if (1 === ($Flag % 2)) {
					if (1 === ($CheckByte % 2)) {
						$CheckByte+= 9;
					}

					$CheckByte >>= 1;
				}
			}
		return '7' . $CheckByte . $HashStr;
	}

	public function checkRobots($url) {
		$exists = $this->remoteFileExists($url."robots.txt");
		if ($exists) {
   			return "Exists";	
		} else {
		    return "Does not exist";
		}
	}

	private function remoteFileExists($url) {
		$curl = curl_init($url);
   		curl_setopt($curl, CURLOPT_NOBODY, true);
    	$result = curl_exec($curl);
    	$ret = false;
    	if ($result !== false) {
        	$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);  
        	if ($statusCode == 200) {
       	     	$ret = true;   
        	}
    	}
    	curl_close($curl);
    	return $ret;
	}

	public function calculateSEO($url) {
		$count = '0';
		if ($this->getPageTitle($url)) {$count = $count + 5;}
		if ($this->getOther($url, 'keywords')) {$count = $count + 5;}
		if ($this->getOther($url, 'author')) {$count = $count + 5;}
		if ($this->getOther($url, 'description')) {$count = $count + 5;}
		if ($this->getEncoding($url)) {$count = $count + 5;}
		if ($this->getFavicon($url)) {$count = $count + 5;}
		if ($this->getAlexaRank($url)) {$count = $count + 10;}
		if ($this->checkRobots($url)) {$count = $count + 10;}
		if ($this->getGooglePR($url) >= 2) {$count = $count + 20;} elseif ($this->getGooglePR($url) >= 5) {$count = $count + 30;} elseif ($this->getGooglePR($url) >= 7) {$count = $count + 40;}
		if ($this->checkGoogleAnalytics($url) == "Not Found.") {} else {$count = $count + 10;}
		return $count;
	}
}

class getResources extends DOMDocument {
	public function getCSS($sitename) {
			$file = file_get_contents($sitename);
			$this->loadHTML($file);
			$domcss = $this->getElementsByTagName('link');
			$getData = '';
			foreach($domcss as $links) {
    			if( strtolower($links->getAttribute('rel')) == "stylesheet" && $links ) {
       				$getData .= "<a href='".$sitename.$links->getAttribute('href')."'>".$sitename.$links->getAttribute('href')."</a><br />";
    			}
			}
			return $getData;
	}

	public function getCSSCount($sitename) {
			$count = '0';
			$file = file_get_contents($sitename);
			$this->loadHTML($file);
			$domcss = $this->getElementsByTagName('link');
			foreach($domcss as $links) {
    			if( strtolower($links->getAttribute('rel')) == "stylesheet" ) {
       				$count++;
    			}
			}
			return $count;
	}

	public function getJS($sitename) {
			$file = file_get_contents($sitename);
			$this->loadHTML($file);
			$domcss = $this->getElementsByTagName('link');
			$domcss2 = $this->getElementsByTagName('script');
			$getData = '';
			foreach($domcss as $links) {
    			if(strtolower($links->getAttribute('rel')) == "javascript" && $links) {
       				$getData .= $links->getAttribute('href') ."<br />";
    			}
			} foreach($domcss2 as $links) {
				if ($links->getAttribute('src') && $links) {
       				$getData .= "<a href='".$sitename.$links->getAttribute('src')."'>".$sitename.$links->getAttribute('src')."</a><br />";
       			}
			}
			return $getData;
	}

	public function getJSCount($sitename) {
			$count = '0';
			$file = file_get_contents($sitename);
			$this->loadHTML($file);
			$domcss = $this->getElementsByTagName('link');
			$domcss2 = $this->getElementsByTagName('script');
			foreach($domcss as $links) {
    			if( strtolower($links->getAttribute('rel')) == "javascript" ) {
       				$count++;
    			}
			} foreach($domcss2 as $links) {
       			$count++;
			}
			return $count;
	}

	public function getImages($sitename) {
		$html = file_get_contents($sitename);
		$this->loadHTML($html);
		$images = $this->getElementsByTagName('img');
		$getData = '';
		foreach ($images as $image) {
  			$getData .= "<a href='".$sitename.$image->getAttribute('src')."'>".$sitename.$image->getAttribute('src')."</a><br />";
		}
		return $getData;
	}

	public function getImageCount($sitename) {
		$count = '0';
		$html = file_get_contents($sitename);
		$this->loadHTML($html);
		$images = $this->getElementsByTagName('img');
		foreach ($images as $image) {
  			$count++;
		}
		return $count;
	}
}

class getMeta extends DOMDocument {
	public function getLinks($url){
	    $url = htmlentities(strip_tags($url));
	    $ExplodeUrlInArray = explode('/',$url);
	    $DomainName = $ExplodeUrlInArray[2];
	    $file = @file_get_contents($url);
	    $h1count = preg_match_all('/(href=["|\'])(.*?)(["|\'])/i',$file,$patterns);
	    $linksInArray = $patterns[2];
	    $CountOfLinks = count($linksInArray);
	    $InternalLinkCount = 0;
	    $ExternalLinkCount = 0;
	    for($Counter=0;$Counter<$CountOfLinks;$Counter++){
	     	if($linksInArray[$Counter] == "" || $linksInArray[$Counter] == "#")
	      	continue;
	    	preg_match('/javascript:/', $linksInArray[$Counter],$CheckJavascriptLink);
	    if($CheckJavascriptLink != NULL)
	    	continue;
	    	$Link = $linksInArray[$Counter];
	    	preg_match('/\?/', $linksInArray[$Counter],$CheckForArgumentsInUrl);
	    if($CheckForArgumentsInUrl != NULL) {
	    	$ExplodeLink = explode('?',$linksInArray[$Counter]);
	    	$Link = $ExplodeLink[0];
	    }
	    preg_match('/'.$DomainName.'/',$Link,$Check);
	    if($Check == NULL) {
	    	preg_match('/http:\/\//',$Link,$ExternalLinkCheck);
	    	if($ExternalLinkCheck == NULL) {
	    		$InternalDomainsInArray[$InternalLinkCount] = $Link;
	    		$InternalLinkCount++;
	    	} else {
	    	$ExternalDomainsInArray[$ExternalLinkCount] = $Link;
	    	$ExternalLinkCount++;
	    	}
	    }
	    else {
	    $InternalDomainsInArray[$InternalLinkCount] = $Link;
	    $InternalLinkCount++;
	    }
	    }
	    $LinksResultsInArray = array(
	    'ExternalLinks'=>$ExternalDomainsInArray,
	    'InternalLinks'=>$InternalDomainsInArray
	    );
	    return $LinksResultsInArray;
    }

	public function getPageText($sitename) {
		$url = file_get_contents($sitename);
		$text = mb_convert_case($url, MB_CASE_LOWER, "UTF-8");
		$text = str_replace("\n\r"," ",$text);
		$text = str_replace("\n"," ",$text);
		$text = str_replace("\r"," ",$text);
		$text = str_replace("/>"," />",$text);
		
		$text = str_replace("&nbsp;"," ",$text);
		
		$text = preg_replace("/<( )*script([^>])*>/i", "<script>", $text);
		$text = preg_replace("/<script[^>]*>[\s\S]*?<\/script>/i", "", $text);
		$text = preg_replace("/<style[^>]*>[\s\S]*?<\/style>/i", "", $text);
		$text = preg_replace("/<iframe[^>]*>[\s\S]*?<\/iframe>/i", "", $text);
		$text = preg_replace("/<head[^>]*>[\s\S]*?<\/head>/i", "", $text);
		$text = preg_replace("/<meta([^>])*\/>/i", "", $text);
		$text = preg_replace("/<script[^>]*>[\s\S]*?<\/script>/i", "", $text);
		$text = preg_replace("/<td[^>]*>/", " ", $text);
		$text = preg_replace("/<p[^>]*>/", " ", $text);
		$text = preg_replace("/<b[^>]*>/", " ", $text);
		$text = preg_replace("/<br[^>]*>/", " ", $text);
		$text = preg_replace("/<[^>]*>/", "", $text);
		
		while(strpos($text,"\t")!==false) $text = str_replace("\t", " ", $text);
		while(strpos($text,'  ')!==false) $text = str_replace("  ", " ", $text);
		
		$text = trim($text);
		$text = html_entity_decode($text, ENT_QUOTES,"UTF-8");
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);
		
		return trim(strtr(trim($text), $trans_tbl));
	}

	public function getWordCount($sitetext, $nofwords) {
		$text = $this->prepare($sitetext);
		while(strpos($text,'  ')!==false) $text = preg_replace("/  /", " ", $text);
		$string = $text;
		$text=explode(" ",trim($text));
		$keywords=array();
		$text=array_unique($text);
		$nr_words=$this->prepare2($string);
		foreach($text as $t=>$k)
		{
			$nr_finds=$this->prepare3($k,$string);	
			if($nr_finds>=$this->minoc && strlen($k)>=$this->minlength) {
				if ($keywords[$k]=$nr_finds > $nofwords) {
					$percent = (($nr_finds / $this->getTotalWords($sitetext)) * 100);
					$keywords[$k]=array($nr_finds,$percent);
				} else {
					$keywords[$k] = array();
				}
			}
		}
		arsort($keywords);
		return $keywords;
	}

	private function prepare($text) {
		$badchar = array("ā","Ā","ī","Ī","ü","ū","Ū","Ü","ṃ","Ṃ","ḥ","Ḥ","ṇ","Ṇ","ṣ","Ṣ","ś","Ś","ṛ","Ṛ","ṝ","Ṝ","ḷ","Ḷ","ṭ","Ṭ","ṭh","Ṭh","ḍ","Ḍ","ḍh","Ḍh","ṅ","Ṅ","ñ","Ñ","ḹ","Ḹ","ľ","š","č","ť","ž","ý","á","í","é","ú","ä","ô","ó","ö","ň","ů","ř","ě","ď","Ř","Ť","Š","Ď","Ě","Č","Ž","Ň","Ľ","Á","É","Í","Ó","Ú","ĺ","Ĺ","Ä","Ö","Ü","ß","ć","đ","ő","ű","ñ","à","â","ç","è","ê","ë","î","ï","û","ù","ü","ÿ","ñ", "œ", "æ","'");
		$newchar = array("a","A","i","I","u","u","U","U","m","M","h","H","n","N","s","S","s","S","r","R","r","R","l","L","t","T","th","Th","d","D","dh","Dh","n","N","n","N","l","L","l","s","c","t","z","y","a","i","e","u","a","o","o","o","n","u","r","e","d","R","T","S","D","E","C","Z","N","L","A","E","I","O","U","l","L","A","O","U","B","c","d","o","u","n","a","a","c","e","e","e","i","i","u","u","u","y","n","oe","ae","-");
		$value = str_replace($badchar, $newchar, $value); 
		$value = preg_replace("@[^A-Za-z0-9\-_\s]+@i", "", $value);
		return $text;
	}

	private function prepare2($str) {
		$tmp=0;
		$tok = strtok ($str," ");
		while ($tok) {
		$tmp++;
		$tok = strtok (" ");
		}
		return $tmp;
	}
	
	private function prepare3($key,$string) {
		$q=0;
		$nr=0;
		$key=strtolower($key);
		$string=strtolower($string);
		while($q==0)
		{
			$pos = @strpos($string,$key);
			if ($pos===false) $q=1;
			else 
			{
				$string = substr ($string,$pos+strlen($key));
				$nr++;
			}
		}
		return $nr;
	}

	public function getTotalWords($text) {
		$text = $this->prepare($text); 
		preg_replace("/[^a-zA-Z0-9äôáéíóúýĺďťňľščžťřěÁÉÍÓÚÝĹĎŤŇĽŠČŽŤŘĚ ]/", "", $text);
		while(strpos($text,'  ')!==false) $text = preg_replace("/\s\s/", " ", $text);
		$text=explode(" ",trim($text));
		return count($text);
	}

}

class getHeadings extends DOMDocument {
	public function getHeading($sitename, $htype) {
		$html = file_get_contents($sitename);
		$this->loadHTML($html);
		$h = $this->getElementsByTagName($htype);
		$getData = '';
		foreach ($h as $hh) {
  			$getData .= $hh->nodeValue.'<br/>';
		}
		return $getData;
	}

	public function getHeadingCount($sitename, $htype) {
		$count = '0';
		$html = file_get_contents($sitename);
		$this->loadHTML($html);
		$h = $this->getElementsByTagName($htype);
		foreach ($h as $hh) {
  			$count++;
		}
		return $count;
	}
}
?>
