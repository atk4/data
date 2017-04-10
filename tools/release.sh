#!/bin/bash

# Execute this script while doing a release

check=$(git symbolic-ref HEAD | cut -d / -f3)
version=$(git symbolic-ref HEAD | sed -e 's,.*/\(.*\),\1,')

if [ $check != "release" ]; then
    echo "Must be on release branch"
    exit -1
fi

# Find out previous version
prev_version=$(head -n1 ../CHANGELOG.md | cut -d' ' -f2)

echo "Releasing $prev_version -> $version"

vimr ../CHANGELOG.md

# Compute diffs
git log --graph --pretty=format:'%Cred%h%Creset -%C(yellow)%d%Creset %s %Cgreen(%cr)%Creset' --abbrev-commit --date=relative $prev_version...

git log --pretty=full $prev_version... | grep '#[0-9]*' | sed 's/#\([0-9]*\)/\1/' | while read i; do
    echo '---------------------------------------------------------------------------------'
    ghi --color show $i | head -50
done 

open "https://github.com/atk4/data/compare/$prev_version...develop"
