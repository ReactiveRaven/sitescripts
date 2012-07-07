#!/bin/bash

if [ -e /tmp/checkgit.lock ];
then
	echo "checkgit is already running";
	exit 1;
fi;

touch /tmp/checkgit.lock;

cat /etc/apache2/sites-enabled/* |
	grep DocumentRoot |
	cut -f 2- -d "/" |
	while read line;
	do
		echo /$line;
	done |
	while read line;
	do
		php checkgit.php preupdate "$line";

		pushd "$line" >/dev/null 2>&1;
		if git status >/dev/null 2>&1;
		then
			cd $(git rev-parse --show-cdup);
			branch="`git branch | grep "^*" | cut -c 3- | tr -d " \n"`";
			git fetch;

			git pull origin "$branch";

			git submodule update --init;
			popd >/dev/null 2>&1;
		fi;
		popd > /dev/null 2>&1;

		php checkgit.php postupdate "$line";
	done;

rm /tmp/checkgit.lock;
