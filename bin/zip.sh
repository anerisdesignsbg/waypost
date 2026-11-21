#!/bin/bash
# Builds dist/<plugin>-<version>.zip, taking the version from the plugin's Version header.
# The folder inside the zip keeps its plain name, so WordPress installs it in the right place.
# Usage: bin/zip.sh <plugin folder> [folder that contains it]
set -e
slug="$1"
parent="${2:-.}"
root="$(cd "$(dirname "$0")/.." && pwd)"
main="$root/$parent/$slug/$slug.php"

version=$(sed -n 's/^[ *]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$main" | head -1)
if [ -z "$version" ]; then
	echo "No Version header in $main" >&2
	exit 1
fi

# The readme's Stable tag must match, or WordPress.org offers the wrong version.
readme="$root/$parent/$slug/readme.txt"
if [ -f "$readme" ]; then
	stable=$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$readme" | head -1)
	if [ -n "$stable" ] && [ "$stable" != "$version" ]; then
		echo "Version $version in $slug.php does not match Stable tag $stable in readme.txt" >&2
		exit 1
	fi
fi

mkdir -p "$root/dist"
# Remove older builds of this plugin so only the current version can be uploaded.
rm -f "$root/dist/$slug.zip" "$root/dist/$slug"-[0-9]*.zip
out="$root/dist/$slug-$version.zip"
(cd "$root/$parent" && zip -qr "$out" "$slug" -x '*.DS_Store')
echo "dist/$slug-$version.zip ready"
