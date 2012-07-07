<?php

$script = realpath(dirname(__FILE__) . "/../shellscripts/runcheckscript");

chdir(dirname($script));

echo str_replace("\n", "<br />\n", shell_exec($script . " 2>&1"));
