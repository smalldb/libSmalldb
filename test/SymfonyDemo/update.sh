#!/usr/bin/env bash
#
# Update Symfony Demo files
#
set -e

# Source repository
repo="git@github.com:symfony/demo.git"

# Files to import
cat >sparse-checkout <<eof
src/Entity
src/Pagination
src/Repository
LICENSE
eof

# -----

# Check working directory
if [ "$0" != "./update.sh" ]
then
	echo "Unexpected working directory." >&2
	exit 1
fi

# Remove everything
find ./ -maxdepth 1 -mindepth 1 -not -path ./update.sh -not -path ./sparse-checkout -print0 | xargs -0 rm -fr --

# Download Git repository and checkout selected files
git init .
git remote add origin "$repo"
git config core.sparseCheckout true
mv sparse-checkout .git/info/sparse-checkout
git fetch origin --depth 1
git checkout origin/master --detach --force
rm -rf .git

# Get rid of the src subdirectory
mv src/* ./
rmdir src/

# Change PHP namespace
find ./ -type f -name '*.php' -print0 \
| xargs -0 sed -i -e 's/\<App\\/Smalldb\\StateMachine\\Test\\SymfonyDemo\\/'

