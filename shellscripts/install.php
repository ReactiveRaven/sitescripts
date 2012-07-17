<?php

require "autoloader.php";

RRaven\Automation\Server\SettingsFile::manufacture("settings.json")
  ->getServer(gethostname())
  ->install();