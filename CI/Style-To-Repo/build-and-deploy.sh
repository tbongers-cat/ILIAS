#!/bin/bash

# This file is part of ILIAS, a powerful learning management system
# published by ILIAS open source e-Learning e.V.
#
# ILIAS is licensed with the GPL-3.0,
# see https://www.gnu.org/licenses/gpl-3.0.en.html
# You should have received a copy of said license along with the
# source code, too.
#
# If this is not the case or you just want to try ILIAS, you'll find
# us at:
# https://www.ilias.de
# https://github.com/ILIAS-eLearning
#
# Build and deploy style specific files.

if [ -z ${PUSH_SECRET} ]
then
  echo "Please ensure you follow the steps in the 'README.md' and you deposit a 'PUSH_SECRET"
  exit
fi

MSG=$(git show-branch --no-name HEAD)
HASH=$(git rev-parse HEAD)
URL="https://github.com/tbongers-cat/ILIAS/commit/${HASH}"
BRANCH=$(git rev-parse --abbrev-ref HEAD)

source "./CI/Style-To-Repo/build.sh"
source "./CI/Style-To-Repo/deploy.sh"
source "./CI/Style-To-Repo/cleanup.sh"

NOW=$(date +'%d.%m.%Y %I:%M:%S')
echo "[${NOW}] Building style folder."
build

NOW=$(date +'%d.%m.%Y %I:%M:%S')
echo "[${NOW}] Deploy style folder."
deploy "${MSG}" "${HASH}" "${URL}" "${BRANCH}" "${PUSH_SECRET}"

NOW=$(date +'%d.%m.%Y %I:%M:%S')
echo "[${NOW}] Cleanup build and deploy artifacts."
removeBuildArtifacts
removeDeployArtifacts

NOW=$(date +'%d.%m.%Y %I:%M:%S')
echo "[${NOW}] Done"