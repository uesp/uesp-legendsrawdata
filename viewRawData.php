<?php

require_once('/home/uesp/secrets/legendsrawdata.secrets');
require_once('jsonPrettyPrint.php');


class CUespViewLegendsRawData
{
	const PRINT_DB_ERRORS = true;
	const SPRITE_URL = "/sprites/";
	const PARSE_IMAGE_LINKS = true;
	const PARSE_NAME_LINKS = true;
	const PARSE_RAWKEYS = true;
	const RAWKEY_LOCALIZATION = "assets/appbase/localization/text/en_us/data";
	
	public $inputParams = array();
	
	public $db = null;
	public $dbReadInitialized = false;
	public $lastQuery = "";
	
	public $rawTextAssets = array();
	public $expandedJson = array();	
	public $rawKeys = array();
	
	public $htmlTemplate = "";
	public $templateFile = "";
		
	public $action = "";
	public $rootTextAssetId = "";
	public $searchText = "";
	public $searchResults = array();
	public $prefixId = "";
	
	
	public function __construct()
	{
		$this->templateFile = __DIR__."/templates/viewrawdata_template.txt";
		$this->loadTemplate();
		
		$this->parseInputParams();
	}
	
	
	public function reportError($msg)
	{
		error_log("Legends Raw Data Viewer: $msg");
		
		print($msg);
		
		if ($this->db != null && $this->db->error)
		{
			if (self::PRINT_DB_ERRORS)
			{
				print("<p />DB Error:" . $this->db->error . "<p />");
				print("<p />Last Query:" . $this->lastQuery . "<p />");
			}
			
			error_log("DB Error:" . $this->db->error . "");
			error_log("Last Query:" . $this->lastQuery . "");
		}
		
		return false;
	}
	
	
	public function InitDatabase ()
	{
		global $uespLegendsRawDataReadDBHost, $uespLegendsRawDataReadUser, 	$uespLegendsRawDataReadPW, $uespLegendsRawDataDatabase;
		
		if ($this->dbReadInitialized) return true;
		
		$this->db = new mysqli($uespLegendsRawDataReadDBHost, $uespLegendsRawDataReadUser, $uespLegendsRawDataReadPW, $uespLegendsRawDataDatabase);
		if ($this->db->connect_error) return $this->ReportError("Could not connect to mysql database!");
		
		$this->dbReadInitialized = true;
		return true;
	}
	
	
	public function loadTemplate()
	{
		$this->htmlTemplate = file_get_contents($this->templateFile);
	}
	
	
	public function parseInputParams()
	{
		$this->inputParams = $_REQUEST;
		
		if (array_key_exists('rootid', $this->inputParams)) 
		{
			$this->rootTextAssetId = $this->inputParams['rootid'];
			$this->action = "viewTextAsset";
		}
		
		if (array_key_exists('search', $this->inputParams)) 
		{
			$this->searchText = trim($this->inputParams['search']);
			if ($this->searchText) $this->action = "search";
		}
		
		if (array_key_exists('idprefix', $this->inputParams)) 
		{
			$this->prefixId = trim($this->inputParams['idprefix']);
			if ($this->prefixId) $this->action = "viewPrefix";
		}
		
		if (array_key_exists('prefixid', $this->inputParams)) 
		{
			$this->prefixId = trim($this->inputParams['prefixid']);
			if ($this->prefixId) $this->action = "viewPrefix";
		}
		
		if (array_key_exists('action', $this->inputParams)) $this->action = $this->inputParams['action'];
	}
	
	
	public function outputHtmlHeader ()
	{
		header("Expires: 0");
		header("Pragma: no-cache");
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("content-type: text/html");
	}
	
	
	public function escape($input)
	{
		return htmlspecialchars($input);
	}
	
	
	public function escapenoquotes($input)
	{
		return htmlspecialchars($input, ENT_NOQUOTES);
	}
	
		
	public function urlescape($input)
	{
		return urlencode($input);
	}
	
	
	public function getRawKey($id)
	{
		if ($this->rawKeys[$id] != null) return '"' . $this->rawKeys[$id] . '" (' . $id . ')';
		return $id;
	}
	
	
	public function loadRawKeys()
	{
		$safeName = $this->db->real_escape_string(self::RAWKEY_LOCALIZATION);
		$this->lastQuery = "SELECT * FROM rawJson WHERE name='$safeName' LIMIT 1;";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) return $this->reportError("Failed to load rawkey data from rawJson table!");
		
		$row = $result->fetch_assoc();
		
		$this->rawKeys = json_decode($row['value'], true);
		
		if ($this->rawKeys == NULL) 
		{
			$this->rawKeys = array();
			return  $this->reportError("Failed to parse rawkey data from '$safeName'!");
		}		
		
		return true;
	}
	
	
	public function loadSearchResults()
	{
		if (!$this->InitDatabase()) return false;
		
		$safeSearch = $this->db->real_escape_string($this->searchText);
		$this->lastQuery = "SELECT * FROM expandedJson WHERE name LIKE '%$safeSearch%' or value LIKE '%$safeSearch%';";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) return $this->reportError("Failed to load data from expandedJson table!");
		
		$this->searchResults = array();
		
		while ($row = $result->fetch_assoc())
		{
			$name = $row['name'];
			$this->searchResults[$name] = $row['value'];
		}			
		
		ksort($this->searchResults);
		
		return true;
	}
	
	
	public function loadRootTextAssets()
	{
		if (!$this->InitDatabase()) return false;
		
		$this->lastQuery = "SELECT * FROM rawJson;";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) return $this->reportError("Failed to load data from rawJson table!");
		
		$this->rawTextAssets = array();
		
		while ($row = $result->fetch_assoc())
		{
			$name = $row['name'];
			$this->rawTextAssets[$name] = $row['value'];
		}			
		
		ksort($this->rawTextAssets);
		
		return true;
	}
	
	
	public function loadTextAsset($assetName)
	{
		if (!$this->InitDatabase()) return false;
		
		$this->loadRawKeys();
		
		$safeName = $this->db->real_escape_string($assetName);
		
		$this->lastQuery = "SELECT * FROM rawJson WHERE name='$safeName';";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) return $this->reportError("Failed to load data from rawJson table!");
		
		$this->rawTextAssets = array();
		
		while ($row = $result->fetch_assoc())
		{
			$name = $row['name'];
			$this->rawTextAssets[$name] = $row['value'];
		}			
		
		return true;
	}
	
	
	public function createNamePrefixLinks($name)
	{
		if (!self::PARSE_NAME_LINKS) return $value;
		
		$parts = explode(".", $name);
		$rootName = array_shift($parts);
		$safeName = $this->escape($rootName);
		$safeUrlName = $this->urlescape($rootName);
		
		$output = "<a href='?rootid=$safeUrlName'>$safeName</a>";
		
		$prefixName = $rootName;
		
		foreach ($parts as $name)
		{
			$prefixName .= ".$name";
			
			$safeName = $this->escape($name);
			$safeUrlName = $this->urlescape($prefixName);
			$output .= ".<a href='?prefixid=$safeUrlName'>$safeName</a>";
		}
		
		return $output;
	}
	
	
	public function translateRawKeys($value)
	{
		if (!self::PARSE_RAWKEYS) return $value;
		//"RawKey": "8d251733-e61b-4267-8e09-f9f0fb7b7303-additional_search_tokens"
		$self = $this;
		
		$output = preg_replace_callback('/"RawKey": "([a-zA-Z0-9\-\._]+)"/i', function ($matches) use ($self) {
				$rawKey = $self->getRawKey($matches[1]);
				return '"RawKey": ' . $rawKey;
			}, $value);
		
		return $output;
	}
	
	
	public function createImageLinksFlat($value)
	{
		if (!self::PARSE_IMAGE_LINKS) return $value;
		
		$output = preg_replace('/([a-zA-Z_0-9\-]+\.png)/i', '<a href="' . self::SPRITE_URL .'$1">$1</a>', $value);
		$output = preg_replace('/([a-zA-Z_0-9\-]+)_png(?!\.png)/i', '<a href="' . self::SPRITE_URL .'$1_png.png">$1_png</a>', $output);
		$output = preg_replace('/(ContentPack000\/Images\/[a-zA-Z_0-9\/\-]+\/)([a-zA-Z_0-9\-]+)$/i', '$1<a href="' . self::SPRITE_URL .'$2.png">$2</a>', $output);
		$output = preg_replace('/(ContentPack000\/Cards\/Visuals\/[a-zA-Z_0-9\/\-]+\/)([a-zA-Z_0-9\-]+)$/i', '$1<a href="' . self::SPRITE_URL .'$2.png">$2</a>', $output);
		$output = preg_replace('/\"(ContentPack000\/Cards\/Visuals\/[a-zA-Z_0-9\/\-]+\/)([a-zA-Z_0-9\-]+)\"/i', '"$1<a href="' . self::SPRITE_URL .'$2.png">$2</a>"', $output);
			
		return $output;
	}
	
	
	public function createImageLinks($value)
	{
		if (!self::PARSE_IMAGE_LINKS) return $value;
		
		$output = $value;
		
		$output = preg_replace('/(ContentPack000\/[a-zA-Z_0-9\' \/\-]+\/)([a-z \[\]A-Z_0-9\-]+\.png)/i', '<a href="' . self::SPRITE_URL .'assets/$1$2">$1$2</a>', $output);
		$output = preg_replace('/(appbase\/[a-zA-Z_0-9\' \/\-]+\/)([a-z \[\]A-Z_0-9\-]+\.png)/i', '<a href="' . self::SPRITE_URL .'assets/$1$2">$1$2</a>', $output);
		
		$output = preg_replace('/(ContentPack000\/[a-zA-Z_0-9\' \/\-]+\/)([a-z \[\]A-Z_0-9\-]+_png(?!\.png))/i', '<a href="' . self::SPRITE_URL .'assets/$1$2.png">$1$2</a>', $output);
		$output = preg_replace('/(appbase\/[a-zA-Z_0-9\' \/\-]+\/)([a-z \[\]A-Z_0-9\-]+_png(?!\.png))/i', '<a href="' . self::SPRITE_URL .'assets/$1$2">$1$2.png</a>', $output);
		
		$output = preg_replace('/(ContentPack000\/[a-zA-Z_0-9\' \/\-]+\/)([a-z \[\]A-Z_0-9\-]+)\"/i', '<a href="' . self::SPRITE_URL .'assets/$1$2.png">$1$2</a>"', $output);
		$output = preg_replace('/(appbase\/[a-zA-Z_0-9\' \/\-]+\/)([a-z \[\]A-Z_0-9\-]+)\"/i', '<a href="' . self::SPRITE_URL .'assets/$1$2.png">$1$2</a>"', $output);
			
		return $output;
	}
		
	
	public function getContentViewRootHtml()
	{
		$this->loadRootTextAssets();
		
		$output = "Viewing all root text assets.";
		$output .= "<ol>";
		
		foreach ($this->rawTextAssets as $name => $value)
		{
			$safeName = $this->escape($name);
			$safeUrl = "?rootid=".$this->urlescape($name);
			$output .= "<li><a href='$safeUrl'>$safeName</a></li>";
		}
		
		$output .= "</ol>";
		
		return $output;
	}
	
	
	public function getContentMainMenuHtml()
	{
		$output = "<ol>";		
		$output .= "<li><a href='?action=viewroot'>View Root Text Assets</a></li>";
		$output .= "</ol>";
		
		return $output;
	}
	
	
	public function getContentViewTextAssetHtml($assetName)
	{
		if (!$this->loadTextAsset($assetName)) return $this->reportError("Failed to load text asset '$assetName'!");
		
		$value = $this->rawTextAssets[$assetName];
		
		$safeValue = $this->escapenoquotes($value);
		$safeValue = $this->createImageLinks($safeValue);
		$safeValue = $this->translateRawKeys($safeValue);		
		
		$output  = "<pre class='lrdCodeBlock'>";
		$output .= $safeValue;
		$output .= "</pre>";
		
		return $output;
	}
	
	
	public function getContentSearchHtml()
	{
		$this->loadSearchResults();
		
		$output = "";
		$count = count($this->searchResults);
		
		if ($count <= 0) return "No results found!";
		
		$output .= "Found $count matching results:";
		$output .= "<ol>";
		
		foreach ($this->searchResults as $name => $value)
		{
			$safeName = $this->escape($name);
			$safeName = $this->createNamePrefixLinks($safeName);
			
			$safeValue = $this->escape($value);
			$safeValue = $this->createImageLinks($safeValue);
			
			$output .= "<li>$safeName = $safeValue</li>";
		}		
		
		$output .= "</ol>";
		
		return $output;
	}
	
	
	public function findPrefixData($rootData, $prefix)
	{
		$names = explode(".", $prefix);
		array_shift($names);
		$data = $rootData;
				
		foreach ($names as $name)
		{
			$data = $data[$name];
			
			if ($data == null) 
			{
				$this->reportError("Failed to find the object named '$name' in the data!");
				return null;
			}
		}
		
		return $data;
	}
	
	
	public function getContentViewPrefixHtml()
	{
		$rootAsset = array_shift(explode(".", $this->prefixId));
		if ($rootAsset == null || $rootAsset == "") return "Error: No valid root asset found!";
		
		$this->loadTextAsset($rootAsset);
		
		$data = $this->rawTextAssets[$rootAsset];
		if ($data == null) return "Error: Failed to load the root asset '" . $this->escape($rootAsset) . "'!";
		
		$parsedData = json_decode($data, true);
		
		$prefixData = $this->findPrefixData($parsedData, $this->prefixId);
		if ($prefixData == null) return "Error: Failed to find the prefix '" . $this->escape(prefixId) . "' in the data!";
		
		$output = "<pre>";
		$safePrefixData = $this->escapenoquotes(json_readable_encode($prefixData));
		$safePrefixData = $this->createImageLinks($safePrefixData);
		$output .= $safePrefixData;
		$output .= "</pre>";
		
		return $output;
	}
	
	
	public function getSubTitle()
	{
		if ($this->action == "viewroot") return " -- Viewing All Root Text Assets";
		if ($this->action == "viewTextAsset") return " -- Viewing Root Text Asset '". $this->escape($this->rootTextAssetId) . "'";
		if ($this->action == "search") return " -- Searching For '". $this->escape($this->searchText) . "'";
		if ($this->action == "viewPrefix") return " -- Viewing Prefix '". $this->escape($this->prefixId) . "'";
		
		return "";
	}
		
	
	public function getContentHtml()
	{
		$output = "";
		
		if ($this->action == "viewroot")
			$output = $this->getContentViewRootHtml();
		elseif ($this->action == "viewTextAsset")
		 	$output = $this->getContentViewTextAssetHtml($this->rootTextAssetId);
		elseif ($this->action == "search")
		 	$output = $this->getContentSearchHtml();
		elseif ($this->action == "viewPrefix")
		 	$output = $this->getContentViewPrefixHtml();
		else
			$output = $this->getContentMainMenuHtml();
		
		return $output;
	}
	
	
	public function getBreadCrumbTrail()
	{
		if ($this->action == "viewroot") return "<a href='/'>Back to Home</a>";
		if ($this->action == "search")   return "<a href='/'>Back to Home</a>";
		if ($this->action == "viewTextAsset")  return "<a href='/'>Back to Home</a> : <a href='?action=viewroot'>All Text Assets</a>";
		if ($this->action == "viewPrefix") return "<a href='/'>Back to Home</a>";
		
		return "";
	}
	
	
	public function createOutputHtml()
	{
		$replacePairs = array(
				'{subTitle}' => $this->getSubTitle(),
				'{content}' => $this->getContentHtml(),
				'{breadcrumbTrail}' => $this->getBreadCrumbTrail(),
				'{searchText}' => $this->escape($this->searchText),
		);
	
		$output = strtr($this->htmlTemplate, $replacePairs);
		
		return $output;
	}
	
	
	
	public function outputHtml()
	{
		$this->outputHtmlHeader();
		
		$output = $this->createOutputHtml();
		print($output);
	}
	
};


$g_ViewRawData = new CUespViewLegendsRawData();
$g_ViewRawData->outputHtml();

