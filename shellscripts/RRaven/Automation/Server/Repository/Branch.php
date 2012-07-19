<?php

namespace RRaven\Automation\Server\Repository;

use RRaven\Automation\Server;
use RRaven\Automation\Server\Repository;
use RRaven\Automation\Server\Repository\Branch\Build as BranchBuild;

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
    
    if (!isset($this->vars["branch"]))
    {
      $this->vars["branch"] = $this->name;
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
  
  public function update()
  {
    echo $this->repository->getRepoString() . ":" . $this->name . ": Updating\n";
    
    chdir($this->getPath() . "/checkout");
    
    echo "    Fetching...";
    shell_exec("git fetch > /dev/null 2>&1");
    echo "OK\n";
    
    echo "    Pulling branch...";
    shell_exec("git pull origin " . $this->name . " >/dev/null 2>&1");
    echo "OK\n";
    
    echo "    Submodules...";
    shell_exec("git submodule update --init >/dev/null 2>&1");
    echo "OK\n";
    
    echo "    Update Build...\n";
    $build = new BranchBuild($this->getPath(), $this->buildVarsArray(), $this);
    if (!$build->run())
    {
      throw new \Exception("Could not complete update of branch '" . $this->name . "'");
    }
    
    echo "    OK\nOK\n\n";
  }
  
  private function buildVarsArray()
  {
    $vars = array();
    foreach ($this->getVars() as $key)
    {
      $vars[$key] = $this->getVar($key);
    }
    return $vars;
  }
  
  public function install()
  {
    echo $this->repository->getRepoString() . ":" . $this->name . ": Installing\n";
    
    foreach (array("", "/logs", "/checkout", "/vars") as $path)
    {
      if (!file_exists($this->getPath() . $path))
      {
        echo "    Creating '" .  $path . "'\n";
        $this->createDirectory(
          $this->getPath() . $path, 
          0776, 
          $this->getVar("localuser"), 
          $this->getVar("localgroup")
        );
      }
    }
    
    chdir($this->getPath());
    
    echo "    Cloning...";
    shell_exec("git clone git@github.com:" . $this->repository->getRepoString() . ".git -b " . $this->name . " ./checkout >/dev/null 2>&1");
    echo "OK\n";
    
    chdir($this->getPath() . "/checkout");
   
    echo "    Pulling branch...";
    shell_exec("git pull origin " . $this->name . " >/dev/null 2>&1");
    echo "OK\n";
    
    echo "    Submodules...";
    shell_exec("git submodule update --init >/dev/null 2>&1");
    echo "OK\n";
    
    chdir($this->getPath());
    
    
    echo "    Install Build...\n";
    $build = new BranchBuild($this->getPath(), $this->buildVarsArray(), $this);
    if (!$build->install())
    {
      throw new \Exception("Could not complete install of branch '" . $this->name . "'");
    }
    echo "    OK\nOK\n\n";
    
    return $this->update();
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