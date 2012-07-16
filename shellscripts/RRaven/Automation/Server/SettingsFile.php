<?php

namespace RRaven\Automation\Server;

use RRaven\Automation\Server as Server;

class SettingsFile
{
  private $settings = null;
  
  private $servers = array();
  private $globalSettings = null;
  
  public function __construct($filename)
  {
    if (!is_file($filename))
    {
      throw new InvalidArgumentException("Cannot find settings file '" . $filename . "'");
    }
    
    try
    {
      $settings = json_decode(file_get_contents($filename), true);
    }
    catch (Exception $e) 
    {
      throw new \Exception("Cannot parse json from '" . $filename . "'");
      $e = $e;
    }
    
    // Check the required keys are available
    
    if (!self::arrayPathExists($settings, array("rraven", "automation", "servers")))
    {
      throw new \Exception("Cannot find ['rraven']['automation']['servers'] path in '" . $filename . "'");
    }
    
    $this->settings = $settings["rraven"]["automation"]["servers"];
  }
  
  private function getGlobalSettings()
  {
    return 
      (
        $this->globalSettings 
          ? $this->globalSettings 
          : $this->globalSettings = 
            (
              isset($this->settings["*"]) 
                ? $this->settings["*"] 
                : array()
            )
      )
    ;
  }
  
  private static function arrayPathExists($array, $path)
  {
    $tmpArray = $array;
    foreach ($path as $key)
    {
      if (!isset($tmpArray[$key]))
      {
        return false;
      }
      $tmpArray = $tmpArray[$key];
    }
    
    return true;
  }
  
  /**
   * Merges two sets of settings together, recursively adding defaults where 
   * specifics are missing.
   * 
   * @param array $specifics
   * @param array $defaults
   * @return array 
   */
  public static function mergeSettings($specifics, $defaults = null)
  {
    if (!is_array($defaults))
    {
      return $specifics;
    }
    foreach ($defaults as $key => $val)
    {
      if (isset($specifics[$key]) && is_array($var))
      {
        $specifics[$key] = self::mergeSettings($specifics[$key], $val);
      }
      if (!isset($specifics[$key]))
      {
        $specifics[$key] = $val;
      }
    }
    
    return $specifics;
  }
  
  /**
   * Returns a server object if the given hostname is found in this settingsfile
   *
   * @param string $hostname
   * @return \RRaven\Automation\Server
   * @throws \Exception 
   */
  public function getServer($hostname = null)
  {
    if (!$hostname)
    {
      $hostname = gethostname();
    }
    
    if (!isset($this->settings[$hostname]))
    {
      throw new Exception("Server '" . $hostname . "' not found in settings file");
    }
    
    return new Server(self::mergeSettings($this->settings[$hostname], $this->getGlobalSettings()));
  }
}
