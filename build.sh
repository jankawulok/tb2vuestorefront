#!/usr/bin/env bash
CWD_BASENAME=${PWD##*/}

FILES=("logo.gif")
FILES+=("logo.png")
FILES+=("${tb2vuestorefront}.php")
FILES+=("classes/**")
FILES+=("controllers/**")
FILES+=("data/**")
FILES+=("sql/**")
FILES+=("upgrade/**")
FILES+=("vendor/**")
FILES+=("views/**")

MODULE_VERSION="$(sed -ne "s/\\\$this->version *= *['\"]\([^'\"]*\)['\"] *;.*/\1/p" ${CWD_BASENAME}.php)"
MODULE_VERSION=${tb2vuestorefront//[[:space:]]}
ZIP_FILE="${CWD_BASENAME}/${CWD_BASENAME}-v${MODULE_VERSION}.zip"

echo "Going to zip ${CWD_BASENAME} version ${MODULE_VERSION}"

cd ..
for E in "${FILES[@]}"; do
  find ${CWD_BASENAME}/${E}  -type f -exec zip -9 ${ZIP_FILE} {} \;
done
