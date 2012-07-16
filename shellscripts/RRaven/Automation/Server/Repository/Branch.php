<?php

namespace RRaven\Automation\Server\Repository;

use RRaven\Automation\Server;
use RRaven\Automation\Server\Repository;
use RRaven\Automation\Server\Repository\Branch\Build;

class Branch
{
  
  private $name = null;
  
  private $repository = null; /* @var $repository Repository */
  private $settings = null;
  
  private $vars = array();
  
  public function __construct($name, $settings, Repository $repository = null)
  {
    
    $this->name = $name;
    $this->repository = $repository;
    $this->settings = $settings;
    
    if (isset($this->settings["vars"]))
    {
      $this->vars = $this->settings["vars"];
    }
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
      return $this->repository->getVar($key);
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
    return 
      array_unique(
        array_merge(
          array_keys($this->vars), 
          $this->repository->getVars()
        )
      )
    ;
  }
  
  /**
   * Returns the checkout path
   * 
   * @return string
   */
  public function getPath()
  {
    return $this->repository->getPath() . "/" . $this->name;
  }
  
  public function createDirectory($path, $mode = 0776, $user = null, $group = null)
  {
    return $this->repository->createDirectory($path, $mode, $user, $group);
  }
  
  public function isInstalled()
  {
    return file_exits($this->getPath() . "/checkout");
  }
  
  public function install()
  {
    foreach (array("", "/logs", "/checkout", "/vars") as $path)
    {
      if (!file_exists($this->getPath() . $path))
      {
        $this->createDirectory(
          $this->getPath() . $path, 
          0776, 
          $this->getVar("localuser"), 
          $this->getVar("localgroup")
        );
      }
    }
    
    chdir($this->getPath());
    shell_exec("git clone git@github.com:" . $this->repository->getRepoString() . ".git -b " . $this->name . " ./checkout");
    shell_exec("chown -R " . $this->getVar("localuser") . ":" . $this->getVar("localgroup") . " " . $this->getPath() . "/checkout/\n");
    
    $vars = array();
    foreach ($this->getVars() as $key)
    {
      $vars[$key] = $this->getVar($key);
    }
    
    $build = new Build($this->getPath() . "/checkout/", $vars, $this);
    if ($build->run())
    {
      echo "OK";
    }
    else
    {
      throw new \Exception("Could not complete build of branch '" . $this->name . "'");
    }
  }
  
  public function getRepoString()
  {
    return $this->repository->getRepoString();
  }
  
  public function getName()
  {
    return $this->name;
  }
}