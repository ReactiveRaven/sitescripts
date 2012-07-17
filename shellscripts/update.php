<?php

require "autoloader.php";

chdir(dirname(__FILE__) . "/settings/");
shell_exec("git pull");
chdir(dirname(__FILE__));

RRaven\Automation\Server\SettingsFile::manufacture("settings/settings.json")
  ->getServer(gethostname())
  ->update();
