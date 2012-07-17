<?php

require "autoloader.php";

RRaven\Automation\Server\SettingsFile::manufacture("settings/settings.json")
  ->getServer(gethostname())
  ->install();
