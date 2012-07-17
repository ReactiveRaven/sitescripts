<?php

namespace RRaven\Automation\Server;

use RRaven\Automation\Server;
use RRaven\Automation\Server\Repository\Branch;

class Repository
{
  
  private $server = null; /* @var $server Server */
  
  private $settings = null;
  private $string = null;
  
  private $vars = array();
  private $branches = array();

  /**
   * Creates a new Repository object
   *
   * @param string $repoString
   * @param array $settings
   * @param Server $server 
   */
  public function __construct($repoString, $settings, Server $server = null)
  {
    $this->string = $repoString;
    $this->settings = $settings;
    $this->server = $server;
    
    if (isset($this->settings["vars"]))
    {
      $this->vars = $this->settings["vars"];
    }
    
    if (isset($this->settings["branches"]))
    {
      $this->branches = $this->settings["branches"];
    }
  }
  
  /**
   * Returns the string used to checkout the repository from github
   * 
   * @return type string
   */
  public function getRepoString()
  {
    return $this->string;
  }
  
  public function getBranch($name)
  {
    if (!isset($this->branches[$name]) || $name == "*")
    {
      throw new \InvalidArgumentException("Branch '" . $name . "' not found");
    }
    
    return new Branch($name, $this->branches[$name], $this);
  }
  
  public function getBranches()
  {
    $branches = array();
    foreach ($this->branches as $key => $val)
    {
      $branches[] = new Branch($key, $val, $this);
    }
    
    return $branches;
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
      return $this->server->getVar($key);
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
    return array_unique(array_merge($this->server->getVars(), array_keys($this->vars)));
  }
  
  /**
   * Returns the checkout path for the server
   * 
   * @return string 
   */
  private function getServerPath()
  {
    if ($this->server instanceof Server)
    {
      return $this->server->getCheckoutPath();
    }
    
    return "/var/RRaven/Automation/Server";
  }
  
  /**
   * Returns the checkout path
   * 
   * @return string
   */
  public function getPath()
  {
    return $this->getServerPath() . "/" . $this->getRepoString();
  }
  
  public function createDirectory($path, $mode = 0776, $user = null, $group = null)
  {
    return $this->server->createDirectory($path, $mode, $user, $group);
  }
  
  public function update()
  {
    foreach ($this->getBranches() as $branch /* @var $branch Branch */)
    {
      $branch->update();
    }
  }
  
  public function install()
  {
    if (!file_exists($this->getPath()))
    {
      $this->createDirectory(
        $this->getPath(), 
        0776, 
        $this->getVar("localuser"), 
        $this->getVar("localgroup")
      );
    }
    
    foreach ($this->getBranches() as $branch /* @var $branch Branch */)
    {
      $branch->install();
    }
  }
  
}