<?php

chdir(dirname(__FILE__) . "/../../");

$files = array(
  "app/config/parameters_default.ini" => "app/config/parameters.ini",
  "server/live_default.conf" => "server/live.conf",
  "server/staging_default.conf" => "server/staging.conf"
);

namespace RRaven\Server;

class Builder
{
  
  
  public static function getVars($rootDirectory) 
  {
    $vars = array();
    foreach (glob($rootDirectory . "/../vars/*") as $filename)
    {
      $fullpath = $rootDirectory . "/../vars/" . $filename;
      if (is_file($fullpath));
      $vars[$filename] = file_get_contents($fullpath);
    }
    
    return $vars;
  }
  
  public static function configFiles($rootDirectory, $files, $vars = null)
  {
    if ($vars == null)
    {
      $vars = self::getVars($rootDirectory);
    }
    foreach ($files as $infile => $outfile)
    {
      $infile = $rootDirectory . $infile;
      $outfile = $rootDirectory . $outfile;
      
      $output = 
        str_replace(
          array_keys($vars), 
          array_values($vars), 
          str_replace( // so you can do %%%%env%%_database_password%%
            "%%env%%", 
            $vars["env"], 
            file_get_contents($infile)
          )
        )
      ;

      $matches = array();

      if (!preg_match_all("/\%\%(?P<variables>[a-z0-9A-Z_-]+)\%\%/", $output, $matches)) {
        if (file_put_contents($outfile, $output))
        {
          echo "[OK] " . $outfile . "\n";
          chmod($outfile, 0440); // ugo-rwx, ug+r -- only user+group can read
        }
        else 
        {
          throw new Exception("[ER] Could not write to '" . $outfile . "'\n");
          exit(1);
        }
      } else {
        throw new Exception("Missing variable" . (count($matches["variables"]) > 1 ? "s" : "") . " for '" . $infile . "' - '" . join("', '", $matches["variables"]) . "'\n");
        exit(1);
      }
    }
  }
}