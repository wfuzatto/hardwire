#!/usr/bin/env sh
set -eu
GRADLE_VERSION="9.5.0"

if command -v gradle >/dev/null 2>&1; then
  exec gradle "$@"
fi

CACHE_BASE="${HOME:-.}/.gradle/hardwire-bootstrap"
GRADLE_HOME="$CACHE_BASE/gradle-$GRADLE_VERSION"

if [ ! -x "$GRADLE_HOME/bin/gradle" ]; then
  mkdir -p "$CACHE_BASE"
  python3 - "$GRADLE_VERSION" "$CACHE_BASE" <<'PY'
import os, sys, urllib.request, zipfile, tempfile
version, base = sys.argv[1], sys.argv[2]
url = f"https://services.gradle.org/distributions/gradle-{version}-bin.zip"
tmp = os.path.join(tempfile.gettempdir(), f"gradle-{version}-bin.zip")
print(f"Downloading Gradle {version}...")
urllib.request.urlretrieve(url, tmp)
with zipfile.ZipFile(tmp) as z:
    z.extractall(base)
os.remove(tmp)
PY
fi

exec "$GRADLE_HOME/bin/gradle" "$@"
