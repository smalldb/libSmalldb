#!/usr/bin/env bash

src="$1"

if [[ "$src" = "" ]]
then
	echo "Usage: $0 path-to-smalldb-demo-dir" >&2
	exit 1
fi

if git diff-index HEAD | grep -q -e '\stest/Example/.*\.php$'
then
	echo "Workdir under test/Example/ is not clean. Aborting." >&2
	exit 2
fi

if [[ ! -f "./Post/Post.php" ]]
then
	echo "There should be ./Post/Post.php in current directory. Aborting." >&2
	exit 3
fi

if [[ ! -f "$src/src/StateMachine/Post/Post.php" ]]
then
	echo "There should be src/StateMachine/Post/Post.php in the source directory. Aborting." >&2
	exit 3
fi

set -e

rsync -arv \
	--exclude "PizzaDelivery/" \
	"$src/src/StateMachine/" "./"

find -type f -name '*.php' -exec ./convert-php.sh '{}' \;
