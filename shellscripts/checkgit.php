<?php

$action = $argv[1];
$directory = $argv[2];
chdir($directory);

while (!file_exists("build.json") && count(explode("/", getcwd())) > 2)
{
	chdir("../");
}

if (file_exists("build.json"))
{
	$json = json_decode(file_get_contents("build.json"), true);
	if (isset($json["rraven"]) && isset($json["rraven"]["build"]) && isset($json["rraven"]["build"][$action]))
	{
		if (!is_array($json["rraven"]["build"][$action]))
		{
			$json["rraven"]["build"][$action] = array($json["rraven"]["build"][$action]);
		}
		foreach ($json["rraven"]["build"][$action] as $callback)
		{
			echo "Calling '" . $callback . "'\n";
			shell_exec($callback);
		}
	}
}

