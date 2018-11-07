<?php
/*
 * Parse the Text/JSON raw data files into the Legends Raw Data database.
 */

if (php_sapi_name() != "cli") die("Can only be run from command line!");

require_once('/home/uesp/secrets/legendsrawdata.secrets');


class CUespParseLegendsTextAssets 
{
	const TEXTASSET_PATH = "/home/uesp/legendsrawdata/data/TextAsset/";
	const SPRITE_PATH    = "/home/uesp/legendsrawdata/data/Sprite/";
	
	public $db = null;
	public $dbWriteInitialized = false;
	public $lastQuery = "";
	
	public $filesParsed = 0;
	public $dataParsed = 0;
	
	
	public function __construct ()
	{
		$this->initDatabaseWrite();
	}
	
	
	public function reportError($msg)
	{
		print("$msg\n");
		return false;
	}
	
	
	public function initDatabaseWrite ()
	{
		global $uespLegendsRawDataWriteDBHost, $uespLegendsRawDataWriteUser, $uespLegendsRawDataWritePW, $uespLegendsRawDataDatabase;
	
		if ($this->dbWriteInitialized) return true;
		
		$this->db = new mysqli($uespLegendsRawDataWriteDBHost, $uespLegendsRawDataWriteUser, $uespLegendsRawDataWritePW, $uespLegendsRawDataDatabase);
		if ($this->db->connect_error) return $this->reportError("Could not connect to mysql database!");
	
		$this->dbWriteInitialized = true;
	
		return $this->createTables();
	}
	
	
	public function createTables()
	{
		$query = "CREATE TABLE IF NOT EXISTS rawJson (
						name TINYTEXT NOT NULL,
						value LONGTEXT NOT NULL,
						PRIMARY KEY (name(100))
					);";
		
		$this->lastQuery = $query;
		$result = $this->db->query($query);
		if ($result === FALSE) return $this->reportError("Failed to create rawJson table!\n".$this->db->error);
		
		$query = "CREATE TABLE IF NOT EXISTS expandedJson (
						name TEXT NOT NULL,
						value TEXT NOT NULL,
						PRIMARY KEY (name(256))
					);";
		
		$this->lastQuery = $query;
		$result = $this->db->query($query);
		if ($result === FALSE) return $this->reportError("Failed to create expandedJson table!\n".$this->db->error);
		
		$query = "TRUNCATE TABLE rawJson";
		$this->lastQuery = $query;
		$result = $this->db->query($query);
		if ($result === FALSE) return $this->reportError("Failed to truncate expandedJson table!\n".$this->db->error);
		
		$query = "TRUNCATE TABLE expandedJson";
		$this->lastQuery = $query;
		$result = $this->db->query($query);
		if ($result === FALSE) return $this->reportError("Failed to truncate rawJson table!\n".$this->db->error);
		
		return true;
	}
	
	
	function expandJson($rootName, $jsonData)
	{
		$expandedJson = array();
		
		foreach ($jsonData as $key => $value)
		{
			$type = gettype($value);
			
			if ($type == "boolean")
			{
				$expandedJson["$rootName.$key"] = $value ? "True" : "False";
				$this->dataParsed++;
			}
			else if ($type == "integer" || $type == "double" || $type == "string")
			{
				$expandedJson["$rootName.$key"] = $value;
				$this->dataParsed++;
			}
			else if ($type == "array")
			{
				$expandedJson = $expandedJson + $this->expandJson("$rootName.$key", $value); 
			}
			else if ($type == "NULL")
			{
				$expandedJson["$rootName.$key"] = '';
				$this->dataParsed++;
			}
			else
			{
				$expandedJson["$rootName.$key"] = "Unknown type '$type' found! '$value'";
				$this->dataParsed++;
			}
		}		
		
		return $expandedJson;		
	}
	
	
	function convertExpandedJsonArrayToString($data)
	{
		$output = "";
		
		foreach ($data as $key => $value)
		{
			$output .= "$key = $value\n";
		}
		
		return $output;
	}
	
	
	function saveData($rootName, $jsonData, $expandedJson)
	{
		$safeRoot = $this->db->real_escape_string($rootName);
		$safeJsonData = $this->db->real_escape_string($jsonData);
				
		$query = "INSERT INTO rawJson(name, value) VALUES('$rootName', '$safeJsonData') ON DUPLICATE KEY UPDATE value='$safeJsonData';";
		$result = $this->db->query($query);
		if ($result === false) return $this->reportError("Failed to insert rawJson value!\n".$this->db->error);
		
		
		foreach ($expandedJson as $key => $value)
		{
			$safeKey = $this->db->real_escape_string($key);
			$safeValue = $this->db->real_escape_string($value);
			
			$query = "INSERT INTO expandedJson(name, value) VALUES('$safeKey', '$safeValue') ON DUPLICATE KEY UPDATE value='$safeValue';";
			$result = $this->db->query($query);
			if ($result === false) return $this->reportError("Failed to insert expandedJson value!\n".$this->db->error);
		}		
		
		return true;
	}
	
	
	function parseTextAssetFile($filename, $baseFilename)
	{
		//$baseFilename = pathinfo($filename, PATHINFO_FILENAME);
		
		print("Parsing JSON file '$baseFilename'...\n");
				
		$fileContents = file_get_contents($filename); 
		if ($fileContents === false) return $this->reportError("\tERROR: Failed to read file '$filename'!");
		
		$jsonData = json_decode($fileContents, true);
		if ($jsonData === null) return $this->reportError("\tERROR: Failed to decode JSON in file '$filename'!");
		
		$this->filesParsed++;
		
		$expandedJson = $this->expandJson($baseFilename, $jsonData);
				
		//$output = $this->convertExpandedJsonArrayToString($expandedJson, '', "\n");
		//print($output);
		
		return $this->saveData($baseFilename, $fileContents, $expandedJson); 
	}
	
	
	function parseAll()
	{
		print("Parsing all JSON files under '".self::TEXTASSET_PATH."'...\n");
		
		$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::TEXTASSET_PATH));
		$files = array(); 

		foreach ($rii as $file) {
		
		    if ($file->isDir()){ 
		        continue;
		    }
		
		    $files[] = $file->getPathname();
		    //print($file->getPathname() . "\n");
		    $baseName = str_replace(self::TEXTASSET_PATH, "", $file->getPathname());
		    $baseName = str_replace(".txt", "", $baseName);
		    
		    $this->parseTextAssetFile($file->getPathname(), $baseName);
		}
		
		print("Parsed {$this->filesParsed} files and {$this->dataParsed} data!\n");
		return true;
	}
	
};


$g_TextAssetParser = new CUespParseLegendsTextAssets();
$g_TextAssetParser->parseAll();



