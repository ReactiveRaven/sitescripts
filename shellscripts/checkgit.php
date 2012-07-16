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
	if (
    isset($json["rraven"]) 
    && isset($json["rraven"]["server"]) 
    && isset($json["rraven"]["server"]["hooks"]) 
    && isset($json["rraven"]["server"]["hooks"][$action])
  )
	{
		if (!is_array($json["rraven"]["server"]["hooks"][$action]))
		{
			$json["rraven"]["server"]["hooks"][$action] = array($json["rraven"]["server"]["hooks"][$action]);
		}
		foreach ($json["rraven"]["server"]["hooks"][$action] as $callback)
		{
			echo "Calling '" . $callback . "'\n";
			shell_exec($callback);
		}
	}
}

