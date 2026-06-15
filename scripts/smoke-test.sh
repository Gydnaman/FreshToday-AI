#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
# GreenBite MVP — 冒烟测试脚本（Sprint 1）
# QA Lead: delta
# 用途：CI 部署后跑 8 条 curl 探针 + 5 条断言
# 关联：docs/bmad/api-contract.md §2 / §A.2
# ─────────────────────────────────────────────────────────────
set -euo pipefail

# ── 配置 ────────────────────────────────────────────────────
BASE_URL="${BASE_URL:-http://localhost:8000}"
COOKIE_JAR="$(mktemp -t greenbite_cookies.XXXXXX)"
TS="$(date +%s)"
EMAIL="smoke_${TS}@greenbite.hk"
PASS="SmokeTest123!"

# 颜色
RED=$'\033[0;31m'
GRN=$'\033[0;32m'
YEL=$'\033[1;33m'
NC=$'\033[0m'

# 断言计数
PASS=0
FAIL=0
TOTAL=5

cleanup() {
    rm -f "$COOKIE_JAR"
}
trap cleanup EXIT

assert_eq() {
    local label="$1"
    local actual="$2"
    local expected="$3"
    if [[ "$actual" == "$expected" ]]; then
        echo -e "  ${GRN}PASS${NC}  $label  (got=$actual)"
        PASS=$((PASS+1))
    else
        echo -e "  ${RED}FAIL${NC}  $label  (expected=$expected, got=$actual)"
        FAIL=$((FAIL+1))
    fi
}

# ── 探针 1：健康检查 /up ─────────────────────────────────────
echo -e "${YEL}[1/8]${NC} GET /up"
HEALTH=$(curl -sS -o /dev/null -w "%{http_code}" "${BASE_URL}/up")
echo "  status=$HEALTH"

# ── 探针 2：商品列表 /api/products ───────────────────────────
echo -e "${YEL}[2/8]${NC} GET /api/products"
PROD_CODE=$(curl -sS -o /tmp/products.json -w "%{http_code}" "${BASE_URL}/api/products")
echo "  status=$PROD_CODE"
PROD_COUNT=$(jq '.data | length' /tmp/products.json 2>/dev/null || echo 0)

# ── 探针 3：注册失败（密码不一致）──────────────────────────
echo -e "${YEL}[3/8]${NC} POST /api/register (validation failure)"
REG_FAIL_CODE=$(curl -sS -o /tmp/reg_fail.json -w "%{http_code}" \
    -H "Accept: application/json" \
    -X POST "${BASE_URL}/api/register" \
    -d 'name=Bad&email=fail@x.com&password=abc&password_confirmation=xyz')
echo "  status=$REG_FAIL_CODE"

# ── 探针 4：注册 + 登录成功 ─────────────────────────────────
echo -e "${YEL}[4/8]${NC} POST /api/register (success) + POST /api/login"
curl -sS -c "$COOKIE_JAR" -o /tmp/reg.json -w "" \
    -H "Accept: application/json" \
    -X POST "${BASE_URL}/api/register" \
    -d "name=Smoke&email=${EMAIL}&password=${PASS}&password_confirmation=${PASS}"

LOGIN_CODE=$(curl -sS -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o /tmp/login.json -w "%{http_code}" \
    -H "Accept: application/json" \
    -X POST "${BASE_URL}/api/login" \
    -d "email=${EMAIL}&password=${PASS}")
echo "  login status=$LOGIN_CODE"

# ── 探针 5：/api/me 带 cookie ────────────────────────────────
echo -e "${YEL}[5/8]${NC} GET /api/me (with session cookie)"
ME_CODE=$(curl -sS -b "$COOKIE_JAR" -o /tmp/me.json -w "%{http_code}" \
    -H "Accept: application/json" \
    "${BASE_URL}/api/me")
echo "  status=$ME_CODE"

# ── 探针 6：限流（61 次 /api/products）───────────────────────
echo -e "${YEL}[6/8]${NC} Rate limit: 61x GET /api/products"
RATE_LIMITED=0
for i in $(seq 1 61); do
    code=$(curl -sS -o /dev/null -w "%{http_code}" "${BASE_URL}/api/products")
    if [[ "$code" == "429" ]]; then
        RATE_LIMITED=1
        echo "  hit 429 at request #$i"
        break
    fi
done

# ── 探针 7：错误码格式校验（4xx 应含 error.code）────────────
echo -e "${YEL}[7/8]${NC} Error envelope: POST /api/orders without items"
ERR_CODE=$(curl -sS -b "$COOKIE_JAR" -o /tmp/err.json -w "%{http_code}" \
    -H "Accept: application/json" \
    -X POST "${BASE_URL}/api/orders" -d '{}')
ERR_HAS_CODE=$(jq -r 'has("error") or (.errors != null)' /tmp/err.json 2>/dev/null || echo "false")
echo "  status=$ERR_CODE envelope=$ERR_HAS_CODE"

# ── 探针 8：webhook 注入（模拟 Stripe）─────────────────────
echo -e "${YEL}[8/8]${NC} POST /api/stripe/webhook (mock event)"
WH_PAYLOAD='{"id":"evt_smoke_'"${TS}"'","type":"unknown.event","data":{"object":{"id":"pi_smoke"}}}'
WH_CODE=$(curl -sS -o /tmp/wh.json -w "%{http_code}" \
    -H "Content-Type: application/json" \
    -H "Stripe-Signature: sig_smoke" \
    -X POST "${BASE_URL}/api/stripe/webhook" \
    -d "$WH_PAYLOAD")
echo "  status=$WH_CODE"

# ── 5 条断言 ────────────────────────────────────────────────
echo
echo "════════════ 5 Assertions ════════════"
assert_eq "1) /up returns 200"                  "$HEALTH"         "200"
assert_eq "2) /api/products returns 200"        "$PROD_CODE"      "200"
assert_eq "3) bad registration returns 422"    "$REG_FAIL_CODE"  "422"
assert_eq "4) login returns 200"               "$LOGIN_CODE"     "200"
assert_eq "5) /api/me with cookie returns 200" "$ME_CODE"        "200"

# ── 总结 ────────────────────────────────────────────────────
echo
echo "════════════ Summary ════════════"
echo -e "Pass: ${GRN}${PASS}${NC}/${TOTAL}   Fail: ${RED}${FAIL}${NC}"
echo "Email used: $EMAIL"
echo "Cookie jar: $COOKIE_JAR (已清理)"

if [[ "$FAIL" -gt 0 ]]; then
    echo -e "${RED}SMOKE TEST FAILED${NC}"
    exit 1
fi
echo -e "${GRN}SMOKE TEST PASSED${NC}"
exit 0
