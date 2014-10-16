<?php
	class bdFundaScraper
	{
		private $arrPageTypes	= array(
			'HOME', // funda page with home info (example: http://www.funda.nl/koop/landgraaf/huis-48832547-eikenboslaan-3/)
			'HOME_PICTURES', // Pictures of HOME
			'HOME_DESCRIPTION', // Description of HOME
			'HOME_DETAILS', // Details of HOME
			'LIST'  // funda page with search list of homes (example: http://www.funda.nl/koop/heerlen/)
		);
		private $strCurrentPageType = false;

		private $arrVariables = array(
			'HOME' => array(
				// variables by regex
				'arrRegex' => array(
					'strStreet' 	=> '@<h1>(.*?)</h1>@is',
					'strImgThumb'	=> '@<img[^<]*?class="thumb"[^<>]*src="(.*?)"@is',
					'strPrice'		=> '@<span class="price">(.*?)</span>@is',
					'strPriceExtra'	=> '@<abbr class="price-ext">(.*?)</abbr>@is'
				),
				// variables method $this->variable();
				'arrByMethod' => array(
					// strHouseID
						'strHouseID',
					// postcode + plaats
						'strPostalCode', 'strCity',
					// kenmerken
						'strSize', 'strRooms', 'strParticulars', 'strHouseType',
					// strDescription
						'txtDescription',
					// detail page
						'txtDetails',
					// fotos
						'arrPictures',
					// verkoop type (koop of huren)
						'strSaleType', // BUY || RENT
					// lat lng coords (in array)
						'arrLatLng'
				)
			),

			'HOME_PICTURES' => array(
				// variables method $this->variable();
				'arrByMethod' => array(
					'arrPictures'
				)
			),

			'HOME_DESCRIPTION' => array(
				'arrRegex' => array(
					'strDescription' => '@<div class="description-full">(.*?)</div>@is'
				)
			),

			'HOME_DETAILS' => array(
				'arrByMethod' => array(
					'strDetails'
				)
			)

		);

		private $strCURLcontent = false;
		private $arrData = array(
			'strURL' => false // overwritten in CURL method.. contains the final correct URL in case of redirect
		);



		/**
		 * @param string $getStrFundaURL the funda URL
		 * @param string $strType        "Home" or "List" 
		 */
		public function __construct($getStrFundaURL, $getStrType = false)
		{
			// Check URL
			if (strpos(strtolower($getStrFundaURL), 'http://www.funda.nl/') !== 0)
				throw new Exception('Unvalid Funda URL "'.$getStrFundaURL.'"', 1);			
			// Check type
			if (! in_array(strtoupper($getStrType), $this->arrPageTypes))
				throw new Exception("Unkown type ".(($getStrType)?'\''.$getStrType.'\' ':'')."in param 2", 1);					
			// Set Type
			$this->strCurrentPageType = strtoupper($getStrType);
			// Do the CURL request
			$this->strCURLcontent = $this->curl($getStrFundaURL);
			$e = '<pre>'.print_r($this->curlStatus,true).'</pre>';
			if ($this->strCURLcontent === false)
				throw new Exception("No content for $this->strURL $e", 1);		
		}

		/**
		 * toSting for debugging!
		 */
		public function __toString()
		{
			// get the file + line nr
			$strFileLine	= '';
			$arrDebug		= debug_backtrace();
			foreach($arrDebug as $arr)  {
				if ($arr['function'] == '__toString') {
					if (! empty($arr['file'])) {
						$strFileLine = ' - '.$arr['file'].' line '.$arr['line'];
					}
					break;
				}
			}
			$arrGetKeys[] = 'strURL';
			// fill the data
			$arrRegexVars  = (! empty($this->arrVariables[$this->strCurrentPageType]['arrRegex']))
				? $this->arrVariables[$this->strCurrentPageType]['arrRegex'] 
				: false ;
			if (is_array($arrRegexVars) && count($arrRegexVars)) {
				foreach($arrRegexVars as $key => $val) {
					$this->$key;	
					$arrGetKeys[] = $key;
				}
			}			
			$arrMethodVars  = (! empty($this->arrVariables[$this->strCurrentPageType]['arrByMethod']))
				? $this->arrVariables[$this->strCurrentPageType]['arrByMethod'] 
				: false ;
			if (is_array($arrMethodVars) && count($arrMethodVars)) {
				foreach($arrMethodVars as $val) {
					$this->$val;
					$arrGetKeys[] = $val;
				}
			}
			// 
			$arrData = $this->arrData;
			unset($arrData['strURL']);
			// return
			return '
				<div style="margin:10px;padding:10px;border:solid 5px blue;background-color:#FFF">
					<strong>Object: </strong>'.get_class($this).$strFileLine.'
					<br /><br />
					<strong>Type: </strong> '.$this->strCurrentPageType.'
					<br /><br />
					<strong>Scraped URL: </strong> '.$this->strURL.'
					<br /><br />
					<strong>Get keys:</strong>
					<br />
					'.implode('<br />', $arrGetKeys).'
					<br /><br />
					<strong>Get values:</strong>
					'.((count($arrData))
						? '<br />'.implode('<br />', array_map(
							function ($v, $k) {
								return (is_array($v)) 
									? $k.' = <pre>'.print_r($v,true).'</pre>'
									: sprintf("%s = %s", $k, $v);
							}, 
							$arrData, array_keys($arrData))
						) 
						: 'geen data beschikbaar' ).'
				</div>
			';
		}

		public function __set($getKey, $getValue) 
		{
			$this->arrData[$getKey] = $getValue;
		}

		public function __get($getKey)
		{
			// check if the data is allready processed
			if (array_key_exists($getKey, $this->arrData))
				return $this->arrData[$getKey];
			// get the var from scrapred content
			$arrRegexVars = (! empty($this->arrVariables[$this->strCurrentPageType]['arrRegex']))
				? $this->arrVariables[$this->strCurrentPageType]['arrRegex']
				: array() ;
			// first try from regex var
			if (array_key_exists($getKey, $arrRegexVars)) {
				preg_match_all(
					$arrRegexVars[$getKey], //pattern
					$this->strCURLcontent, 	//content
					$match					//result
				);
				$value	= strip_tags($match[1][0]);
			} else {
				$value = $this->variable($getKey);
			}
			// return and save the value
			if ($value){
				$this->$getKey = str_replace('&nbsp;', ' ',$value);
				return $value;
			}	
		}

		private function variable($getVar=false)
		{
			$arrByMethods = (! empty($this->arrVariables[$this->strCurrentPageType]['arrByMethod'])) 
				? $this->arrVariables[$this->strCurrentPageType]['arrByMethod']
				: false ;
			if (! is_array($arrByMethods) || ! count($arrByMethods) || ! in_array($getVar, $arrByMethods)) 
				return;
		
			if ($this->strCurrentPageType == 'HOME') {
				switch ($getVar) {
					case 'strHouseID' ;
						$arr = array_reverse(explode('/', $this->strURL));
						foreach ($arr as $val)
							if (strpos($val, '-') !== false)
								return $val;
						break;

					case 'strPostalCode' :
					case 'strCity' :
						preg_match_all('@</h1>[^<]*?<p>(.*?)</p>@is', $this->strCURLcontent, $arrMatches);
						if (empty($arrMatches[1][0])) {
							// no match try to get from URL
							if ($getVar == 'strCity') {
								$arr = explode('/', str_replace('/'.$this->strHouseID.'/', '',  $this->strURL));
								return ucFirst(trim(end($arr)));
							}
							return;
						}
							
						$arr = preg_split("/\\r\\n|\\r|\\n/", $arrMatches[1][0]);
						$str = strip_tags($arr[0]);
						if ($getVar == 'strPostalCode')
							return substr($str, 0, 7);
						else // city
							return trim(substr($str, 7, strlen($str)));
						break;

					// Kenmerken block
					case 'strSize'		:
					case 'strRooms'		:
					case 'strParticulars':
					case 'strHouseType'	:
						$pattern		= '@<table class="specs specs-cats specs-nbhd">(.*?)</table>@is';
						preg_match_all($pattern, $this->strCURLcontent, $block);
						$block			= $block[0][0];
						$pattern		= '@<th.*?>(.*?)</th>[^<]*<td>(.*?)</td>@is';
						preg_match_all($pattern, $block, $kenmerken);
						$arrKenmerken	= array();
						for($i=0; $i<count($kenmerken[0]); $i++){
							$kenmerk_key	= $kenmerken[1][$i];
							$kenmerk_value	= strip_tags($kenmerken[2][$i]);
							$kenmerk_key	= strtolower(str_replace('&nbsp;', ' ', $kenmerk_key));
							$kenmerk_value	= trim(str_replace('&nbsp;', ' ', strip_tags($kenmerk_value)));
							switch ($getVar) {
								case 'strSize':
									if ($kenmerk_key == 'oppervlakte')
										return $kenmerk_value;
									break;
								case 'strRooms':
									if ($kenmerk_key == 'aantal kamers')
										return $kenmerk_value;
									break;
								case 'strParticulars':
									if ($kenmerk_key == 'bijzonderheden')
										return $kenmerk_value;
									break;
								case 'strHouseType'	:
									if (in_array($kenmerk_key, array('soort woonhuis','soort appartement')))
										return $kenmerk_value;
									break;
							}
						}
						break;

					// Foto object
					case 'arrPictures'	:
						try {
							$o = new bdFundaScraper($this->strURL.'fotos/', 'HOME_PICTURES');
							return $o->arrPictures;
						} catch(Exception $e) {
							return false;
						}						
						break;

					case 'txtDescription' :
						try {
							$o = new bdFundaScraper($this->strURL.'omschrijving/', 'HOME_DESCRIPTION');
							return $o->strDescription;
						} catch(Exception $e) {
							return false;
						}	
						break;

					case 'txtDetails' :
						try {
							$o = new bdFundaScraper($this->strURL.'kenmerken/', 'HOME_DETAILS');
							return $o->strDetails;
						} catch(Exception $e) {
							return false;
						}
						break;

					// return BUY or RENT
					case 'strSaleType' :
						$arr = array_reverse(explode('/', $this->strURL));
						if (is_array($arr) && count($arr)) {
							$valPrev = false;
							foreach ($arr as $val) {
								if (strpos(strtolower($val), 'funda') !== false) {
									return (in_array($valPrev, array('nieuwbouw','koop')))
										? 'BUY' : 'RENT' ;
									return $valPrev;
								}
								$valPrev = strtolower($val);
							}
						}
						break;

					case 'arrLatLng' :
						$pattern		= '@Markers.LoadActivePropertyData\({"x":(.*?),"y":(.*?)}@is';
						preg_match_all($pattern, $this->strCURLcontent, $arrMatches);
						if (count($arrMatches) == 3) {
							$arr = array('lat' => $arrMatches[2][0], 'lng' => $arrMatches[1][0]);
							if ($arr['lat'] && $arr['lng'])
								return $arr;
						}
						break;
				}
			} elseif($this->strCurrentPageType == 'HOME_PICTURES') {
				switch ($getVar) {
					// pictures
					//	[0] => Array
					//        (
					//            [small] => http://cloud.funda.nl/valentina_media/045/130/605_klein.jpg
					//            [big] => http://cloud.funda.nl/valentina_media/045/130/605_groot.jpg
					//        )
					//
					//  [1] => Array
					//      (
					//          [small] => http://cloud.funda.nl/valentina_media/045/130/606_klein.jpg
					//          [big] => http://cloud.funda.nl/valentina_media/045/130/606_groot.jpg
					//      )

					case 'arrPictures':
						$pattern		= '@<div id="gallery-carousel" class="carousel">(.*?)</div>@is';
						preg_match_all($pattern, $this->strCURLcontent, $block);
						if (empty($block[0][0])) return;
						$block			= $block[0][0];
						//<a href="http://images.funda.nl/valentinamedia/007/675/453_groot.jpg" id="ctl00_ContentPlaceHolderMain_FotoVergroter_ha" target="PhotoFrame"><img class='thumb' src='http://images.funda.nl/valentinamedia/007/675/453_klein.jpg' alt="geen"' title="geen"' /></a>
						$pattern		= '@<a href="(.*?)".*?><span><img.*?src="(.*?)".*?@is';
						preg_match_all($pattern, $block, $afbeeldingen);

						$arrImages		= array();
						for($i=0; $i<count($afbeeldingen[0]); $i++){
							$img_s			= $afbeeldingen[2][$i];
							//$img_l			= $afbeeldingen[1][$i];
							$img_l			= str_replace('_klein', '_groot', $img_s);

							$arrImages[] = array('small' => $img_s, 'big' => $img_l);
						}
						return (count($arrImages)) ? $arrImages : false ;
						break;
				}
				
			} elseif($this->strCurrentPageType == 'HOME_DETAILS') {
				switch ($getVar) {
					case 'strDetails':
						$pattern		= '@<table class="specs specs-cats" border="0">(.*?)</table>@is';
						preg_match_all($pattern, $this->strCURLcontent, $block);
						if (! empty($block[0][0]))
							return $block[0][0];
						break;
				}
			} elseif ($this->strCurrentPageType == 'LIST') {

			}
		}

		private $curlStatus = array();
		public function curl($getURL) 
		{
			$getURL = trim($getURL);
			$contents = false;
			$ch = curl_init($getURL);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:7.0.1) Gecko/20100101 Firefox/7.0.1");
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$rawContent		= curl_exec($ch);
			$http_status	= curl_getinfo($ch, CURLINFO_HTTP_CODE);
			// status OK
			if (in_array($http_status, array(200)) ) {
				list($headers, $contents)	= explode('<!DOCTYPE', $rawContent);
				$contents		= '<!DOCTYPE'.$contents;
			}
			$this->curlStatus[] = array(
				'url' => $getURL, 
				'status' => $http_status
			); 
			curl_close($ch);
			$this->strURL 			= $getURL;
			// redirect perform new curl
			if ($http_status == 301 || $http_status == 302) {
				$new_url	= preg_replace('/.*Location:\s([^\n]+).*/ims', '$1', $rawContent);
				if (strpos(strtolower($new_url), 'http://www.funda.nl/') !== 0) 
					$new_url = 'http://www.funda.nl'.$new_url;
				$contents	= self::curl($new_url);
			}
			
			return $contents;
		}

	/**
	 * Static create
	 */
		public static function createHomeObjectByURL($getURLorHouseID) 
		{
			
			if (strpos(strtolower($getURLorHouseID), 'http://www.funda.nl/') === 0) {
				return new bdFundaScraper($getURLorHouseID, 'HOME');
			} else {
				// first try rentals.. no result try buy!
				try {
					$o = static::createHomeObjectByURL('http://www.funda.nl/huur/bla/'.$getURLorHouseID.'/');
				} catch(Exception $e) {
					try {
						$o = static::createHomeObjectByURL('http://www.funda.nl/koop/bla/'.$getURLorHouseID.'/');	
					} catch (Exception $e) {
						return false;
					}					
				}
				return $o;
			}
		}
	}
?>