<?php

namespace RRaven\Automation;

use RRaven\Automation\Server\SettingsFile as SettingsFile;
use RRaven\Automation\Server\Repository as Repo;

class Server
{
  
  private $vars = array();
  private $repos = array();
  private $settings = array();
  private $packages = array();
  
  public function __construct($settings)
  {
    
    // pull interesting stuff into instance variables
    
    if (isset($settings["vars"]))
    {
      $this->vars = $settings["vars"];
    }
    
    if (isset($settings["repos"]))
    {
      $this->repos = $settings["repos"];
    }
    
    if (isset($settings["packages"]))
    {
      $this->packages = $settings["packages"];
    }
    
    if (isset($settings["settings"]))
    {
      $this->settings = $settings["settings"];
    }
    
  }
  
  /**
   * Returns a repo configured for the current server
   *
   * @param type $repoString
   * @return \RRaven\Automation\Server\Repository
   * @throws Exception 
   */
  public function getRepo($repoString)
  {
    if (!isset($this->repos[$repoString]))
    {
      throw new Exception("Repo string '" . $repoString . "' not found in server");
    }
    
    return new Repo($repoString, SettingsFile::mergeSettings($this->repos[$repoString], $this->vars), $this);
  }
  
  /**
   * Get all repositories in this server
   *
   * @return RRaven\Automation\Server\Repository[]
   * @throws Exception from getRepo calls
   */
  public function getRepos()
  {
    $repos = array();
    foreach (array_keys($this->repos) as $repoString)
    {
      $repos[] = $this->getRepo($repoString);
    }
    
    return $repos;
  }
  
  /**
   * Returns the requested variable, if found.
   *
   * @param string $key
   * @return mixed
   * @throws \InvalidArgumentException if the variable doesn't exist.
   */
  public function getVar($key)
  {
    if (!isset($this->vars[$key]))
    {
      throw new \InvalidArgumentException("Var not found in server '" . $key . "'");
    }
    
    return $this->vars[$key];
  }
  
  /**
   * Returns a list of variables available
   *
   * @return string[]
   */
  public function getVars()
  {
    return array_keys($this->vars);
  }
  
  /**
   * Returns the path to check out repositories into
   *
   * @return type string
   */
  public function getCheckoutPath()
  {
    return (
      isset($this->settings["checkout_path"]) 
        ? $this->settings["checkout_path"] 
        : "/var/RRaven/Automation/Server"
    );
  }
  
  public function createDirectory($path, $mode = 0776, $user = null, $group = null)
  {
    if ($user == null)
    {
      $user = $this->getVar("localuser");
    }
    
    if ($group == null)
    {
      $group = $this->getVar("localgroup");
    }
    
    if (!file_exists($path))
    {
      $bits = explode("/", $path);
      array_pop($bits);
      if (!count($bits))
      {
        throw new \InvalidArgumentException("Ran out of folders when creating directory '" . $path . "'");
      }
      $this->createDirectory(implode("/", $bits), $mode, $user, $group);
      mkdir($path, $mode);
      chown($path, $user);
      chgrp($path, $group);
      
    }
  }
  
  public function update()
  {
    foreach ($this->packages as $verb => $packagelist)
    {
      shell_exec("apt-get " . $verb . " " . implode(" ", $packagelist));
    }
    
    foreach ($this->getRepos() as $repo /* @var $repo Repository */)
    {
      $repo->update();
    }
  }
  
  public function install()
  {
    foreach ($this->packages as $verb => $packagelist)
    {
      shell_exec("apt-get " . $verb . " " . implode(" ", $packagelist));
    }
    
    if (!file_exists($this->getCheckoutPath()))
    {
      $this->createDirectory($this->getCheckoutPath());
    }
    
    foreach ($this->getRepos() as $repo /* @var $repo Repository */)
    {
      $repo->install();
    }
  }
}