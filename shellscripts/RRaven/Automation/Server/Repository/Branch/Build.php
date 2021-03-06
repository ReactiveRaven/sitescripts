<?php

namespace RRaven\Automation\Server\Repository\Branch;

use RRaven\Automation\Server\Repository\Branch;
use RRaven\Automation\Server;

class Build
{
  private $root = null;
  private $vars = array();
  private $configFiles = array();
  private $eventScripts = array();
  private $apacheConfFile = null;
  
  private $branch = null;
  
  private $domainName = null;
  
  /**
   * Constructs a new Build to run or examine
   *
   * @param string $root folder of site (not necessarily of checkout)
   * @param string[] $vars to use in build
   * @param Branch $branch the build is from
   */
  public function __construct($root, $vars, Branch $branch)
  {
    $this->branch = $branch;
    $this->root = $root;
    $this->vars = $vars;
    if (!isset($this->vars["localroot"]))
    {
      $this->vars["localroot"] = $this->getCheckoutDir();
    }
    if (file_exists($this->getCheckoutDir() . "/build.json"))
    {
      $settings = json_decode(file_get_contents($this->getCheckoutDir() . "/build.json"), true);
      if (isset($settings["rraven"]) && isset($settings["rraven"]["server"]))
      {
        $settings = $settings["rraven"]["server"];
        if (isset($settings["configfiles"]))
        {
          $this->configFiles = $settings["configfiles"];
        }
        if (isset($settings["hooks"]))
        {
          $this->eventScripts = $settings["hooks"];
        }
        if (isset($settings["apacheConfFile"]))
        {
          $this->apacheConfFile = $this->replaceVariables($settings["apacheConfFile"]);
        }
      }
    }
  }
  
  private function getCheckoutDir()
  {
    return $this->root . "/checkout/";
  }
  
  private function replaceVariables($input)
  {
    foreach ($this->vars as $key => $val)
    {
      $input = str_replace("%%" . $key . "%%", $val, $input);
    }
    
    return $input;
  }
  
  public function install()
  {
    echo "        Building (Install)...\n";
    chdir($this->root);
    echo "            Resetting permissions...";
    shell_exec("chown -R " . $this->vars["localuser"] . ":" . $this->vars["localgroup"] . " ./");
    echo "OK\n";
    
    echo "            Building config files...";
    $this->buildConfigFiles();
    echo "OK\n";
    
    echo "            Running scripts (Install)...";
    $this->runInstallScripts();
    echo "OK\n";
    
    echo "        OK\n";
    
    return true;
  }
  
  public function run()
  {
    echo "        Building...\n";
    chdir($this->root);
    
    echo "            Resetting permissions...";
    shell_exec("chown -R " . $this->vars["localuser"] . ":" . $this->vars["localgroup"] . " ./");
    echo "OK\n";
    
    echo "            Building config files...";
    $this->buildConfigFiles();
    echo "OK\n";
    
    echo "            Testing apache config...";
    $this->testApacheConfig();
    echo "OK\n";
    
    echo "            Running scripts...";
    $this->runBuildScripts();
    echo "OK\n";
    
    echo "        OK\n";
    
    return true;
  }
  
  private function runBuildScripts()
  {
    $this->runScripts("build");
  }
  
  private function runInstallScripts()
  {
    $this->runScripts("install");
  }
  
  private function runScripts($key)
  {
    if (isset($this->eventScripts[$key]))
    {
      chdir($this->getCheckoutDir());
      shell_exec("su " . $this->vars["localuser"] . " -c \"" . $this->getCheckoutDir() . "/" . $this->eventScripts[$key] . "\"");
    }
  }
  
  public function getDomainName()
  {
    if (!$this->domainName)
    {
      $bits = explode("/", $this->branch->getRepoString());
      $this->domainName = $bits[count($bits) - 1];
    }
    
    return $this->domainName;
  }
  
  private function testApacheConfig()
  {
    // Make sure the apache sites-enabled directory even exists
    $apache_config_path = "/etc/apache2/sites-enabled/";
    if (!file_exists($apache_config_path))
    {
      throw new \Exception("Cannot find apache enabled-sites directory");
    }
    
    // Keep a backup of correct stuff, deleting the old backup if it got left
    $apache_config_path_backup = rtrim($apache_config_path, "/\\") . "_rraven_automation_server";
    if (file_exists($apache_config_path_backup))
    {
      throw new \Exception("Apache sites-available backup folder already exists. Manually decide on an acceptable sites-enabled folder, remove the rraven_automation_server backup copy, and try again.");
    }
    rename($apache_config_path, $apache_config_path_backup);
    mkdir($apache_config_path);
    link($apache_config_path."/../sites-available/default", $apache_config_path."/000-default");
    
    // Link our config into place, or write our own
    $expected_conf_location = $this->getCheckoutDir(). "/" . $this->apacheConfFile;
    $enabled_sites_name = str_replace("/", ".", $this->branch->getRepoString() . "_" . $this->branch->getName());
    if (is_file($expected_conf_location))
    {
      link($expected_conf_location, $apache_config_path . "/" . $enabled_sites_name);
    }
    else
    {
      $contents = 
        array(
          "<VirtualHost *:80>",
          "  DocumentRoot " . $this->getCheckoutDir() . "/",
          "  ServerName " . $this->branch->getName() . "." . $this->getDomainName(),
          "  ",
          "  CustomLog " . $this->root . "/logs/apache.access combined",
          "  LogLevel info",
          "  ErrorLog " . $this->root . "/logs/apache.error",
          "  ",
          "  php_flag log_errors on",
          "  php_flag display_errors on",
          "  php_value error_reporting 32767",
          "  php_value error_log " . $this->root . "/logs/php.error",
          "</VirtualHost>"
        )
      ;
      
      file_put_contents($apache_config_path . "/" . $enabled_sites_name, implode("\n", $contents));
      // TODO: What about generating a basic config file?
      //throw new \Exception("Cannot find apache config file for '" . $this->branch->getRepoString() . "_" . $this->branch->getName() . "'");
    }
    
    // Test the config
    $config_ok = (shell_exec("apache2ctl configtest > /dev/null 2>&1 && echo -n OK") == "OK");
    
    // If the config is ok, copy it into the backup folder
    if ($config_ok)
    {
      if (file_exists($apache_config_path_backup . "/" . $enabled_sites_name))
      {
        unlink($apache_config_path_backup . "/" . $enabled_sites_name);
      }
      copy($apache_config_path . "/" . $enabled_sites_name, $apache_config_path_backup . "/" . $enabled_sites_name);
    }
    
    // Move the backup folder back into place, deleting the temp one
    if (strlen($apache_config_path) < 10)
    {
      throw new \Exception("Sanity check the apache config path please! Its only " . strlen($apache_config_path) . " characters long and I'm scared. '" . $apache_config_path . "'");
    }
    
    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($apache_config_path), 
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach($files as $file){
      if (!preg_match("/\/[.]+$/", $file))
      {
        if ($file->isDir()){
          rmdir($file->getRealPath());
        } else {
          unlink($file->getRealPath());
        }
      }
    }
    rename($apache_config_path_backup, $apache_config_path);
    
    // If the config was ok, restart the server.
    if ($config_ok)
    {
      shell_exec("apache2ctl graceful");
    }
  }
  
  private function buildConfigFiles()
  {
    foreach ($this->configFiles as $oldFile => $newFile)
    {
      if (file_exists($this->getCheckoutDir() . $oldFile))
      {
        file_put_contents(
          $this->getCheckoutDir() . "/" . $newFile, 
          $this->replaceVariables(
            file_get_contents($this->getCheckoutDir() . "/" . $oldFile)
          )
        );
      }
    }
  }
}