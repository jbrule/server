<?php

ini_set("memory_limit", "2048M");

define('PS2_START_MARKER', 'symfony [info] {sfRequest} request parameters ');
define('APIV3_START_MARKER', '[KalturaFrontController->run] DEBUG: Params [');
define('APIV3_GETFEED_MARKER', '[syndicationFeedRenderer] [global] DEBUG: getFeed Params [');

define('DB_HOST_NAME', 'dbgoeshere');
define('DB_USER_NAME', 'root');
define('DB_PASSWORD', 'root');

$PS2_TESTED_XML_ACTIONS = array(
		'extwidget.playmanifest', 
		'keditorservices.getmetadata', 
		'keditorservices.getentryinfo', 
		'partnerservices2.executeplaylist',
		'partnerservices2.getentries',
		'partnerservices2.getallentries',
		'partnerservices2.getentry',
		'partnerservices2.getentryroughcuts',
		'partnerservices2.getkshow',
		'partnerservices2.getuiconf',
		'partnerservices2.getwidget',
		'partnerservices2.listentries',
		'partnerservices2.listkshows',
		'partnerservices2.listplaylists',
		'extwidget.embedIframeJs',
		);

$PS2_TESTED_BIN_ACTIONS = array(
		'extwidget.serveFlavor',
		'extwidget.kwidget',
		'extwidget.thumbnail',
		'extwidget.download',
		'keditorservices.flvclipper',
		'extwidget.raw',	
		);

$APIV3_TESTED_ACTIONS = array(
		'syndicationFeed.execute',			// api_v3/getFeed.php
		'playlist.execute',
		'*.get',
		'*.list',
		'*.count',
		'*.serve',
		'*.goto',
		'*.search',
		);

$ID_FIELDS = array('id', 'guid', 'loc', 'title', 'link');

class PartnerSecretPool
{
	protected $secrets = array();

	public function __construct()
	{
		$this->link = mysql_connect(DB_HOST_NAME, DB_USER_NAME, DB_PASSWORD)
			or die('Error: Could not connect: ' . mysql_error() . "\n");

		mysql_select_db('kaltura', $this->link) or die("Error: Could not select 'kaltura' database\n");
	}

	public function __destruct()
	{
		mysql_close($this->link);
	}

	public function getPartnerSecret($partnerId)
	{
		if (isset($this->secrets[$partnerId]))
			return $this->secrets[$partnerId];
		if (!is_numeric($partnerId))
			return null;
			
		$query = "SELECT admin_secret FROM partner WHERE id='{$partnerId}'";
		$result = mysql_query($query, $this->link) or die('Error: Select from func table query failed: ' . mysql_error() . "\n");
		$line = mysql_fetch_array($result, MYSQL_NUM);
		if (!$line)
			return null;
		$this->secrets[$partnerId] = $line[0];
		return $line[0];
	}
}

function extendKsExpiry($ks)
{
	global $partnerSecretPool;

	$decodedKs = base64_decode($ks);
	if (strpos($decodedKs, "|") === false)
		return null;

	list($hash , $ksStr) = explode( "|" , $decodedKs , 2 );
	$splittedStr = explode(';', $ksStr);
	if (count($splittedStr) < 3)
		return null;
	
	$partnerId = $splittedStr[0];
	$splittedStr[2] = time() + 86400;
	$ksStr = implode(';', $splittedStr);
	$adminSecret = $partnerSecretPool->getPartnerSecret($partnerId);
	if (!$adminSecret)
		return null;
	$ks = base64_encode(sha1($adminSecret . $ksStr) . '|' . $ksStr);
	return $ks;
}

function isKsParsable($ks)
{
	$ks = base64_decode($ks, true);
	if (strpos($ks, "|") === false)
		return false;

	list($hash, $ks) = @explode ("|", $ks, 2);
	$ksParts = explode(";", $ks);
	if (count($ksParts) < 3)
		return false;
	
	return true;
}

function print_r_reverse($in) {
    $lines = explode("\n", trim($in));
    if (trim($lines[0]) != 'Array') {
        // bottomed out to something that isn't an array
        return $in;
    } else {
        // this is an array, lets parse it
        if (preg_match("/(\s{5,})\(/", $lines[1], $match)) {
            // this is a tested array/recursive call to this function
            // take a set of spaces off the beginning
            $spaces = $match[1];
            $spaces_length = strlen($spaces);
            $lines_total = count($lines);
            for ($i = 0; $i < $lines_total; $i++) {
                if (substr($lines[$i], 0, $spaces_length) == $spaces) {
                    $lines[$i] = substr($lines[$i], $spaces_length);
                }
            }
        }
        array_shift($lines); // Array
        array_shift($lines); // (
        array_pop($lines); // )
        $in = implode("\n", $lines);
        // make sure we only match stuff with 4 preceding spaces (stuff for this array and not a nested one)
        preg_match_all("/^\s{4}\[(.+?)\] \=\> /m", $in, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $pos = array();
        $previous_key = '';
        $in_length = strlen($in);
        // store the following in $pos:
        // array with key = key of the parsed array's item
        // value = array(start position in $in, $end position in $in)
        foreach ($matches as $match) {
            $key = $match[1][0];
            $start = $match[0][1] + strlen($match[0][0]);
            $pos[$key] = array($start, $in_length);
            if ($previous_key != '') $pos[$previous_key][1] = $match[0][1] - 1;
            $previous_key = $key;
        }
        $ret = array();
        foreach ($pos as $key => $where) {
            // recursively see if the parsed out value is an array too
            $ret[$key] = print_r_reverse(substr($in, $where[0], $where[1] - $where[0]));
        }
        return $ret;
    }
}

function doCurl($url, $params = array(), $files = array(), $range = null)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	if ($params)
	{
		curl_setopt($ch, CURLOPT_POST, 1);
	}
	if (count($files) > 0)
	{
		foreach($files as &$file)
			$file = "@".$file; // let curl know its a file
		curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($params, $files));
	}
	else if ($params)
	{
		$opt = http_build_query($params, null, "&");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $opt);
	}
	if (!is_null($range))
	{
		curl_setopt($ch, CURLOPT_RANGE, $range);
	}
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, '');
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);

	$beforeTime = microtime(true);	
	$result = curl_exec($ch);
	$endTime = microtime(true);
	
	$curlError = curl_error($ch);
	curl_close($ch);
	return array($result, $curlError, $endTime - $beforeTime);
}

function stripXMLInvalidChars($value) 
{
	preg_match_all('/[^\t\n\r\x{20}-\x{d7ff}\x{e000}-\x{fffd}\x{10000}-\x{10ffff}]/u', $value, $invalidChars);
	$invalidChars = reset($invalidChars);
	if (count($invalidChars))
	{
		$value = str_replace($invalidChars, "", $value);
	}
	return $value;
}

function printStringDiff($string1, $string2)
{
	for ($i = 0; $i < strlen($string1); $i++)
	{
		if ($string1[$i] == $string2[$i])
			continue;
			
		print "Byte offset: $i\n";
		print "Char1: " . ord($string1[$i]) . "\n";
		print "Char2: " . ord($string2[$i]) . "\n";
		$start = 0;
		if ($i > 100)
			$start = $i - 100;
		print "String1: " . substr($string1, $start, 200) . "\n";
		print "String2: " . substr($string2, $start, 200) . "\n";
		break;
	}
}

function xmlToArray($xmlstring)
{
	// fix the xml if it's invalid
	$origstring = $xmlstring;
	$xmlstring = @iconv('utf-8', 'utf-8', $xmlstring);
	$xmlstring = stripXMLInvalidChars($xmlstring);
	$xmlstring = str_replace('&', '&amp;', $xmlstring);
	$xmlstring = str_replace(array('&amp;#', '&amp;lt;', '&amp;gt;', '&amp;quot;', '&amp;amp;', '&amp;apos;'), array('&#', '&lt;', '&gt;', '&quot;', '&amp;', '&apos;'), $xmlstring);
	if ($xmlstring != $origstring)
	{
		//printStringDiff($xmlstring, $origstring);
		//return null;
	}

	// parse the xml
	$xml = @simplexml_load_string($xmlstring);
	$json = json_encode($xml);
	$array = json_decode($json,TRUE);
	return $array;
}

function normalizeKS($value, $ks)
{
	$decodedKs = base64_decode($ks);
	$explodedKs = explode('|', $decodedKs);
	if (count($explodedKs) < 2)
		return $value;
	
	list($sig, $ksFields) = $explodedKs;
	$ksFields = explode(';', $ksFields);
	unset($ksFields[2]);		// valid until
	unset($ksFields[4]);		// rand
	$ksFields = implode(';', $ksFields);
	return str_replace($ks, $ksFields, $value);
}

function compareValues($newValue, $oldValue)
{
	return $newValue == $oldValue;
}	
	
function compareArraysInternal($resultNew, $resultOld, $path)
{
	global $ID_FIELDS;

	$errors = array();
	foreach ($resultOld as $key => $oldValue)
	{
		if (!array_key_exists($key, $resultNew))
		{
			$errors[] = "missing field $key (path=$path)";
			continue;
		}
		
		$newValue = $resultNew[$key];
		if (is_array($oldValue) && is_array($newValue))
		{
			$errors = array_merge($errors, compareArrays($newValue, $oldValue, "$path/$key"));
		}
		else if (is_string($oldValue) && is_string($newValue))
		{
			if (!compareValues($newValue, $oldValue))
			{
				$errors[] = "field $key has different value (path=$path new=$newValue old=$oldValue)";
				if (in_array($key, $ID_FIELDS))
					break;		// id is different, all other fields will be different as well
			}
		}
		else
		{
			$errors[] = "field $key has different type (path=$path new=$newValue old=$oldValue)";
		}
	}

	return $errors;
}

function compareArraysById($item1, $item2)
{
	global $ID_FIELDS;

	if (!is_array($item1) || !is_array($item2))
		return 0;
	
	foreach ($ID_FIELDS as $idField)
	{
		if (isset($item1[$idField]) && isset($item2[$idField]) && 
			$item1[$idField] != $item2[$idField])
			return strcmp($item1[$idField], $item2[$idField]);
	}
	
	return 0;
}
	
function compareArrays($resultNew, $resultOld, $path)
{
	global $ID_FIELDS;

	$errors = compareArraysInternal($resultNew, $resultOld, $path);
	if (count($errors) < 2)
		return $errors;
	
	$ids = array();
	$isOnlyIdErrors = true;
	foreach ($errors as $curError)
	{
		$isCurIdError = false;
		foreach ($ID_FIELDS as $idField)
		{
			if (beginsWith($curError, "field {$idField} has different value"))
			{
				$isCurIdError = true;
				break;
			}
		}
		
		if (!$isCurIdError)
		{
			$isOnlyIdErrors = false;
			break;
		}
		$explodedError = explode(' new=', rtrim($curError, ')'));
		$explodedError = explode(' old=', $explodedError[1]);

		$ids[] = "'".$explodedError[0]."'";
		$ids[] = "'".$explodedError[1]."'";
	}
	
	if (!$isOnlyIdErrors)
		return $errors;
	
	usort($resultNew, 'compareArraysById');
	usort($resultOld, 'compareArraysById');
	$newErrors = compareArraysInternal($resultNew, $resultOld, $path);
	if ($newErrors)				// sorting didn't help
		return $errors;
		
	$ids = implode(',', array_unique($ids));
	return array('Different order ' . $ids);
}

function normalizeResultBuffer($result)
{
	global $serviceUrlNew, $serviceUrlOld;
	
	$result = preg_replace('/<executionTime>[0-9\.]+<\/executionTime>/', '', $result);
	$result = preg_replace('/<serverTime>[0-9\.]+<\/serverTime>/', '', $result);
	$result = preg_replace('/<execute_impl_time>[0-9\.]+<\/execute_impl_time>/', '', $result);
	$result = preg_replace('/<execute_time>[0-9\.]+<\/execute_time>/', '', $result);
	$result = preg_replace('/<total_time>[0-9\.]+<\/total_time>/', '', $result);
	$result = preg_replace('/<server_time>[0-9\.]+<\/server_time>/', '', $result);
	$result = preg_replace('/server_time="[0-9\.]+"/', '', $result);
	$result = preg_replace('/kaltura_player_\d+/', 'KP', $result);
	$result = str_replace($serviceUrlNew, $serviceUrlOld, $result);
	
	$patterns = array('/\/ks\/([a-zA-Z0-9+]+=*)/', '/&ks=([a-zA-Z0-9+\/]+=*)/', '/\?ks=([a-zA-Z0-9+\/]+=*)/');
	foreach ($patterns as $pattern)
	{
		preg_match_all($pattern, $result, $matches);
		foreach ($matches[1] as $match)
		{
			$result = normalizeKS($result, $match);
		}
	}
	return $result;
}

function compareResults($resultNew, $resultOld)
{
	$resultNew = normalizeResultBuffer($resultNew);
	$resultOld = normalizeResultBuffer($resultOld);
	if ($resultNew == $resultOld)
		return array();
		
	$resultNew = xmlToArray($resultNew);
	$resultOld = xmlToArray($resultOld);

	if (!$resultNew || !$resultOld)
	{
		return array('failed to parse XMLs');
	}
	
	return compareArrays($resultNew, $resultOld, "");
}

function beginsWith($str, $prefix) 
{
	return (substr($str, 0, strlen($prefix)) === $prefix);
}

function endsWith($str, $postfix) 
{
	return (substr($str, -strlen($postfix)) === $postfix);
}

function getRequestHash($fullActionName, $paramsForHash)
{
	foreach ($paramsForHash as $paramName => $paramValue)
	{
		preg_match('/^\d+\:ks$/', $paramName, $matches);
		if ($matches)
		{
			unset($paramsForHash[$paramName]);
			continue;
		}
	}
	
	$paramsToUnset = array(
		"ks",
		"kalsig",
		"clientTag",
		"callback",
		"sig",
		"ts",
		"3:contextDataParams:uid",
		"contextDataParams:uid",
		"4:filter:uid",
		"filter:uid",
		"4:pager:uid",
		"pager:uid",
		);
	foreach ($paramsToUnset as $paramToUnset)
	{
		unset($paramsForHash[$paramToUnset]);
	}
	return md5($fullActionName . serialize($paramsForHash));
}

function shouldProcessRequest($fullActionName, $parsedParams)
{
	global $testedActions, $testedRequests, $maxTestsPerActionType;
	global $requestNumber, $startPosition, $endPosition;
	
	// test action type count
	if (!array_key_exists($fullActionName, $testedActions))
	{
		$testedActions[$fullActionName] = 0;
	}
	
	if ($maxTestsPerActionType && $testedActions[$fullActionName] > $maxTestsPerActionType)
	{
		return 'no';
	}
	
	// test whether this action was already tested
	$requestHash = getRequestHash($fullActionName, $parsedParams);
	if (in_array($requestHash, $testedRequests))
	{
		return 'no';
	}
	
	// apply start/end positions
	$requestNumber++;
	if ($endPosition != 0 && $requestNumber > $endPosition)
	{
		return 'quit';
	}
			
	if ($requestNumber <= $startPosition)
	{
		return 'no';
	}
	
	$testedRequests[] = $requestHash;
	$testedActions[$fullActionName]++;
	
	return 'yes';
}

function testAction($fullActionName, $parsedParams, $uri, $postParams = array(), $binaryCompare = false)
{
	global $serviceUrlOld, $serviceUrlNew;
	
	print "Testing $fullActionName...";
	
	usleep(200000);         // sleep for 0.2 sec to avoid hogging the server
	
	$range = null;
	if ($binaryCompare)
		$range = '0-262144';		// 256K
	
	for ($retries = 0; $retries < 3; $retries++)
	{
		list($resultNew, $curlErrorNew, $newTime) = doCurl($serviceUrlNew . $uri, $postParams, array(), $range);
		list($resultOld, $curlErrorOld, $oldTime) = doCurl($serviceUrlOld . $uri, $postParams, array(), $range);
		
		if ($curlErrorNew || $curlErrorOld)
		{
			print "Curl error [$curlErrorNew] [$curlErrorOld]\n";
			return;
		}
		
		if ($binaryCompare)
		{
			if ($resultNew === $resultOld)
				$errors = array();
			else
				$errors = array('Data does not match - newSize='.strlen($resultNew).' oldSize='.strlen($resultOld));
		}
		else
		{
			$errors = compareResults($resultNew, $resultOld);
		}
		
		if (!count($errors))
		{
			print sprintf("Ok (new=%.3f old=%.3f)\n", $newTime, $oldTime);
			if ($newTime > $oldTime * 3 && $newTime > 0.5)
			{
				$sig = '';
				if (isset($parsedParams['kalsig']))
					$sig = $parsedParams['kalsig'];
				else if (isset($parsedParams['sig']))
					$sig = $parsedParams['sig'];
				print "Warning: API became slow ({$sig})";
			}			
			return;
		}
		
		if (count($errors) == 1 && beginsWith($errors[0], 'Different order '))
		{
			break;			// retry doesn't help with different order, we can save the time
		}
		
		print "\nRetrying $fullActionName...";
		usleep(1000000);
	}
		
	print "\n-------------------------------------------------------------------------------\n";
	print "\tUrl = $serviceUrlNew$uri\n";
	print "\tPostParams = ".var_export($postParams, true)."\n";
	print "\tTestUrl = $serviceUrlNew$uri&".http_build_query($postParams)."\n";	
	foreach ($errors as $error)
	{
		print "\tError: $error\n";
	}
	
	if (!$binaryCompare && (count($errors) != 1 || !beginsWith($errors[0], 'Different order ')))
	{
		print "Result - new\n";
		print $resultNew . "\n";
		print "Result - old\n";
		print $resultOld . "\n";
	}
}

function extendRequestKss(&$parsedParams)
{
	if (array_key_exists('ks', $parsedParams))
	{
		$ks = $parsedParams['ks'];
		if (isKsParsable($ks))
		{
			$ks = extendKsExpiry($ks);
			if (is_null($ks))
				return false;
			$parsedParams['ks'] = $ks;
		}
	}
	
	for ($i = 1; ; $i++)
	{
		$ksKey = "{$i}:ks";
		if (!array_key_exists($ksKey, $parsedParams))
			break;
		
		$ks = $parsedParams[$ksKey];
		if (isKsParsable($ks))
		{
			$ks = extendKsExpiry($ks);
			if (is_null($ks))
				return false;
			$parsedParams[$ksKey] = $ks;
		}
	}
	
	return true;
}

function isActionApproved($fullActionName, $action)
{
	global $APIV3_TESTED_ACTIONS;
	foreach ($APIV3_TESTED_ACTIONS as $approvedAction)
	{
		if (beginsWith($approvedAction, '*.'))
		{
			if (beginsWith($action, substr($approvedAction, 2)))
				return true;
		}
		else
		{
			if (beginsWith($fullActionName, $approvedAction))
				return true;
		}
	}
	return false;
}

function processMultiRequest($parsedParams)
{
	$paramsByRequest = array();
	foreach ($parsedParams as $paramName => $paramValue)
	{
		$explodedName = explode(':', $paramName);
		if (count($explodedName) <= 1 || !is_numeric($explodedName[0]))
		{
			continue;
		}
		
		$requestIndex = (int)$explodedName[0];
		$paramName = implode(':', array_slice($explodedName, 1));
		if (!array_key_exists($requestIndex, $paramsByRequest))
		{
			$paramsByRequest[$requestIndex] = array();
		}
		$paramsByRequest[$requestIndex][$paramName] = $paramValue;
	}
	
	if (!$paramsByRequest)
	{
		return;
	}
	
	$fullActionName = 'multirequest';
	$maxIndex = max(array_keys($paramsByRequest));
	for ($reqIndex = 1; $reqIndex <= $maxIndex; $reqIndex++)
	{
		if (!array_key_exists($reqIndex, $paramsByRequest) ||
			!array_key_exists('service', $paramsByRequest[$reqIndex]) ||
			!array_key_exists('action', $paramsByRequest[$reqIndex]))
		{
			return;
		}
		
		$service = $paramsByRequest[$reqIndex]['service'];
		$action = $paramsByRequest[$reqIndex]['action'];
		$curFullActionName = strtolower("$service.$action");		
		if (!isActionApproved($curFullActionName, $action))
		{
			return;
		}
		
		$fullActionName .= '/'.$curFullActionName;
	}

	if (!extendRequestKss($parsedParams))
	{
		return;
	}
	
	switch (shouldProcessRequest($fullActionName, $parsedParams))
	{
	case 'quit':
		return true;
		
	case 'no':
		return;
	}
	
	$parsedParams['format'] = '2';		# XML

	$uri = "/api_v3/index.php?service=multirequest";
	
	testAction($fullActionName, $parsedParams, $uri, $parsedParams);
}

function processRequest($parsedParams)
{
	if (!array_key_exists('service', $parsedParams))
	{
		//print "Error: service not specified " . print_r($parsedParams, true) . "\n";
		return;
	}

	$service = $parsedParams['service'];
	unset($parsedParams['service']);
	
	if (beginsWith(strtolower($service), "multirequest"))
	{
		if (strtolower($service) == "multirequest")
		{
			processMultiRequest($parsedParams);
		}
		return;
	}
	
	if (!array_key_exists('action', $parsedParams))
	{
		//print "Error: action not specified " . print_r($parsedParams, true) . "\n";
		return;
	}
		
	$action = $parsedParams['action'];
	unset($parsedParams['action']);
	
	$fullActionName = strtolower("$service.$action");
	$parsedParams['format'] = '2';		# XML
	
	if (!isActionApproved($fullActionName, $action) ||
		!extendRequestKss($parsedParams))
	{
		return;
	}
	
	switch (shouldProcessRequest($fullActionName, $parsedParams))
	{
	case 'quit':
		return true;
		
	case 'no':
		return;
	}
	
	$uri = "/api_v3/index.php?service=$service&action=$action";
	$compareBinary = beginsWith($action, 'serve');
	testAction($fullActionName, $parsedParams, $uri, $parsedParams, $compareBinary);
}

function processFeedRequest($parsedParams)
{
	$fullActionName = "getfeed";

	if (!isActionApproved('syndicationFeed.execute', 'execute') ||
		!extendRequestKss($parsedParams))
	{
		return;
	}
	
	switch (shouldProcessRequest($fullActionName, $parsedParams))
	{
	case 'quit':
		return true;
		
	case 'no':
		return;
	}
	
	$parsedParams['nocache'] = '1';
	
	$uri = "/api_v3/getFeed.php?" . http_build_query($parsedParams, null, "&");
	
	testAction($fullActionName, $parsedParams, $uri);
}

class LogProcessorApiV3
{
	protected $inParams = false;
	protected $isFeed = false;
	protected $params = '';
	
	function processLine($buffer)
	{
		if (!$this->inParams)
		{
			$markerPos = strpos($buffer, APIV3_START_MARKER);
			if ($markerPos !== false)
			{
				$this->params = substr($buffer, $markerPos + strlen(APIV3_START_MARKER));
				$this->inParams = true;
				$this->isFeed = false;
				return false;
			}
			$markerPos = strpos($buffer, APIV3_GETFEED_MARKER);
			if ($markerPos !== false)
			{
				$this->params = substr($buffer, $markerPos + strlen(APIV3_GETFEED_MARKER));
				$this->inParams = true;
				$this->isFeed = true;
				return false;
			}
		}
		else
		{
			if ($buffer[0] == ']')
			{
				$this->inParams = false;
			
				$parsedParams = print_r_reverse($this->params);
				if (print_r($parsedParams, true) != $this->params)
				{
					print "print_r_reverse failed\n";
					return false;
				}

				if ($this->isFeed)
				{
					$shouldQuit = processFeedRequest($parsedParams);
				}
				else
				{
					$shouldQuit = processRequest($parsedParams);
				}
				
				if ($shouldQuit)
				{
					return true;
				}
			}
			else
			{
				$this->params .= $buffer;
			}
		}
	
		return false;
	}
}

function processPS2Request($parsedParams)
{
	global $serviceUrlOld, $serviceUrlNew, $PS2_TESTED_XML_ACTIONS, $PS2_TESTED_BIN_ACTIONS;

	if (!array_key_exists('module', $parsedParams) ||
		!array_key_exists('action', $parsedParams))
	{
		print "Error: module/action not specified " . print_r($parsedParams, true) . "\n";
	}

	$module = $parsedParams['module'];
	$action = $parsedParams['action'];
	unset($parsedParams['module']);
	unset($parsedParams['action']);
	if (isset($parsedParams['format']) && is_numeric($parsedParams['format']))
	{
		$parsedParams['format'] = '2';          # XML
	}
	
	if (strtolower($module) == 'partnerservices2' &&
		strtolower($action) == 'defpartnerservices2base')
	{
		$action = $parsedParams['myaction'];
		unset($parsedParams['myaction']);
	}
	
	$fullActionName = strtolower("$module.$action");
	
	if (!in_array($fullActionName, $PS2_TESTED_XML_ACTIONS) && 
		!in_array($fullActionName, $PS2_TESTED_BIN_ACTIONS))
	{
		return;
	}
	
	switch (shouldProcessRequest($fullActionName, $parsedParams))
	{
	case 'quit':
		return true;
		
	case 'no':
		return;
	}
	
	$uri = "/index.php/$module/$action?" . http_build_query($parsedParams, null, "&");

	$compareBinary = in_array($fullActionName, $PS2_TESTED_BIN_ACTIONS);
	testAction($fullActionName, $parsedParams, $uri, array(), $compareBinary);
}

class LogProcessorPS2
{	
	function processLine($buffer)
	{
		$markerPos = strpos($buffer, PS2_START_MARKER);
		if ($markerPos === false)
			return false;
		$params = trim(substr($buffer, $markerPos + strlen(PS2_START_MARKER)));
		if (!beginsWith($params, 'array (') || !endsWith($params, ')'))
			return false;
		$parsedParams = eval('return ' . $params . ';');

		if (processPS2Request($parsedParams))
		{
			return true;
		}

		return false;
	}
}

function processRegularFile($apiLogPath, $logProcessor)
{
	$handle = @fopen($apiLogPath, "r");
	if (!$handle)
		die('Error: failed to open log file');

	$logStats = fstat($handle);
	$origSize = $logStats['size'];

	while (ftell($handle) < $origSize && ($buffer = fgets($handle)) !== false) 
	{
		if ($logProcessor->processLine($buffer))
			break;
	}

	fclose($handle);
}

function processGZipFile($apiLogPath, $logProcessor)
{
	$handle = @gzopen($apiLogPath, "r");
	if (!$handle)
		die('Error: failed to open log file');

	while (!gzeof($handle)) 
	{
		$buffer = gzgets($handle, 16384);
		if ($logProcessor->processLine($buffer))
			break;
	}

	gzclose($handle);
}

// parse the command line
if ($argc < 5)
	die("Usage:\n\tphp compatCheck <old service url> <new service url> <api log> <api_v3/ps2> [<start position> [<end position> [<max tests per action>]]]\n");

$serviceUrlOld = $argv[1];
$serviceUrlNew = $argv[2];
$apiLogPath = $argv[3];
$logFormat = strtolower($argv[4]);

$partnerSecretPool = new PartnerSecretPool();

if (strpos($apiLogPath, ':') !== false)
{
	$localLogPath = tempnam("/tmp", "CompatCheck");
	print("Copying log file to $localLogPath...\n");
	passthru("rsync -zavx --progress $apiLogPath $localLogPath");
	$apiLogPath = $localLogPath;
}

if (!in_array($logFormat, array('api_v3', 'ps2')))
	die("Log format shoud be either api_v3 or ps2");

if (!beginsWith(strtolower($serviceUrlOld), 'http://'))
	$serviceUrlOld = 'http://' . $serviceUrlOld;
if (!beginsWith(strtolower($serviceUrlNew), 'http://'))
	$serviceUrlNew = 'http://' . $serviceUrlNew;

$startPosition = 0;
$endPosition = 0;
$maxTestsPerActionType = 10;

if ($argc > 5)
	$startPosition = intval($argv[5]);
if ($argc > 6)
	$endPosition = intval($argv[6]);
if ($argc > 7)
	$maxTestsPerActionType = intval($argv[7]);

// init globals
$testedActions = array();
$testedRequests = array();
$requestNumber = 0;

if ($logFormat == 'api_v3')
	$logProcessor = new LogProcessorApiV3();
else
	$logProcessor = new LogProcessorPS2();

$logFileInfo = pathinfo($apiLogPath);

if (array_key_exists('extension', $logFileInfo) && $logFileInfo['extension'] == 'gz')
	processGZipFile($apiLogPath, $logProcessor);
else
	processRegularFile($apiLogPath, $logProcessor);

$partnerSecretPool = null;
