<?php

function autoload($className)
{
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if (($lastNsPos = strripos($className, '\\'))) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    require $fileName;
}

spl_autoload_register("autoload");

use RRaven\Automation\Server\SettingsFile;

$settings = new SettingsFile("settings.json");
$server = $settings->getServer("vvps-047130.dailyvps.co.uk"); /* @var $server \RRaven\Automation\Server */

$server->install();
die();

foreach (
  $server->getRepos()
  as $repo /* @var $repo RRaven\Automation\Server\Repository */
)
{
  foreach (
    $repo->getBranches() 
    as $branch /* @var $branch RRaven\Automation\Server\Repository\Branch */
  )
  {
    echo $branch->getPath() . "\n";
    foreach ($branch->getVars() as $key)
    {
      echo $key . ": " . $branch->getVar($key) . "\n";
    }
  }
}