#!/bin/bash
set -e
set -o pipefail

files=(wp-sync-db.php class/*.php compatibility/*.php template/*.php module-media-files/class/*.php tests/*.php);
for file in "${files[@]}"
do
  php -l "$file";
done

php tests/media-webp.php
