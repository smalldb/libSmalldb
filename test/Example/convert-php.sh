#!/usr/bin/env bash

set -e

sed -i \
	-e 's/App\\StateMachine\\/Smalldb\\StateMachine\\Test\\Example\\/g' \
	-e 's/^[ ]\{24\}/\t\t\t\t\t\t/'  \
	-e 's/^[ ]\{20\}/\t\t\t\t\t/'  \
	-e 's/^[ ]\{16\}/\t\t\t\t/'  \
	-e 's/^[ ]\{12\}/\t\t\t/'  \
	-e 's/^[ ]\{8\}/\t\t/'  \
	-e 's/^[ ]\{4\}/\t/' \
	"$1"

(
	awk '
		$0 ~ "^use " { exit }
		{ print }
	' "$1"

	awk '
		$0 ~ "^use " { u = 1 }
		u == 1 && $0 !~ "use" { exit }
		u == 1 { print }
	' "$1" | sort

	awk '
		$0 ~ "^use " { u = 1 }
		u == 1 && $0 !~ "^use " { u = 2 }
		u == 2 { print }
	' "$1"
) > "$1.tmp"

if [[ -w "$1" ]]
then
	mv -f -- "$1.tmp" "$1"
else
	mv -f -- "$1.tmp" "$1"
	chmod a-w "$1"
fi

