#!/usr/bin/env bash
echo "=== 1. 关键可执行文件 ==="
for c in php composer sqlite3 git node npm python curl tar 7z; do
  p=$(command -v "$c" 2>/dev/null)
  if [ -n "$p" ]; then
    echo "YES  $c -> $p"
  else
    echo "NO   $c"
  fi
done

echo ""
echo "=== 2. 临时目录 ==="
d=/tmp/_greenbite_writetest_$$
if mkdir -p "$d" 2>/dev/null; then
  echo "YES  $d created"
  rmdir "$d"
else
  echo "NO   /tmp not writable"
fi

echo ""
echo "=== 3. 用户目录 ==="
echo "USERPROFILE=$USERPROFILE"
echo "HOME=$HOME"
echo "PWD=$PWD"

echo ""
echo "=== 4. PHP/Composer 全局包管理器 ==="
if command -v scoop >/dev/null 2>&1; then echo "YES  scoop"; else echo "NO   scoop"; fi
if command -v choco >/dev/null 2>&1; then echo "YES  choco"; else echo "NO   choco"; fi
if command -v winget >/dev/null 2>&1; then echo "YES  winget"; else echo "NO   winget"; fi
