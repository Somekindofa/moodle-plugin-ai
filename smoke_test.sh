#!/usr/bin/env bash
# smoke_test.sh — mod_craftpilot plugin smoke test
#
# Usage:  bash /var/www/html/public/mod/craftpilot/smoke_test.sh
# Exit:   0 = all pass,  1 = one or more failures
#
# Checks:
#   1. AMD JS build validity (define() present, no raw ES6 import)
#   2. PHP syntax of all plugin .php files
#   3. RAG backend health (http://127.0.0.1:8000/api/health)
#   4. DB tables exist: mdl_craftpilot, mdl_craftpilot_conv, mdl_craftpilot_msg
#   5. External functions registered in mdl_external_functions
#   6. Capabilities registered in mdl_capabilities
#   7. Template and CSS files present and non-empty
#   8. Cache purge (php admin/cli/purge_caches.php exits 0)

FAIL=0
WARN=0

# Colour codes (degrade gracefully if not a tty)
if [ -t 1 ]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
    BOLD='\033[1m'; RESET='\033[0m'
else
    RED=''; GREEN=''; YELLOW=''; BOLD=''; RESET=''
fi

pass() { printf "${GREEN}[PASS]${RESET} %s\n" "$*"; }
fail() { printf "${RED}[FAIL]${RESET} %s\n" "$*"; FAIL=$((FAIL + 1)); }
warn() { printf "${YELLOW}[WARN]${RESET} %s\n" "$*"; WARN=$((WARN + 1)); }
info() { printf "\n${BOLD}--- %s${RESET}\n" "$*"; }

# ---------- Auto-detect paths ----------
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Find real config.php (the one with $CFG->dbhost, NOT the public thin-loader)
CONFIG_PHP=""
for candidate in \
        "$PLUGIN_DIR/../../config.php" \
        "$PLUGIN_DIR/../../../config.php"; do
    resolved="$(realpath "$candidate" 2>/dev/null)"
    if [ -f "$resolved" ] && grep -q '\$CFG->dbhost' "$resolved" 2>/dev/null; then
        CONFIG_PHP="$resolved"
        break
    fi
done

# Find purge_caches.php
PURGE_CACHES=""
for candidate in \
        "$PLUGIN_DIR/../../admin/cli/purge_caches.php" \
        "$PLUGIN_DIR/../../../admin/cli/purge_caches.php"; do
    resolved="$(realpath "$candidate" 2>/dev/null)"
    if [ -f "$resolved" ]; then
        PURGE_CACHES="$resolved"
        break
    fi
done

AMD_BUILD="$PLUGIN_DIR/amd/build/chat_interface.min.js"
TEMPLATE="$PLUGIN_DIR/templates/chat_interface.mustache"
STYLES="$PLUGIN_DIR/styles.css"

printf "\n${BOLD}=== mod_craftpilot smoke test ===${RESET}\n"
printf "Plugin dir   : %s\n" "$PLUGIN_DIR"
printf "Config.php   : %s\n" "${CONFIG_PHP:-NOT FOUND}"
printf "Purge caches : %s\n" "${PURGE_CACHES:-NOT FOUND}"

# =============================================================================
info "1/8  AMD JS build validity"

if [ ! -f "$AMD_BUILD" ]; then
    fail "AMD build file missing: $AMD_BUILD"
else
    define_count=$(grep -c 'define(' "$AMD_BUILD" 2>/dev/null; true)
    import_count=$(grep -c '^import ' "$AMD_BUILD" 2>/dev/null; true)

    if [ "$define_count" -gt 0 ] && [ "$import_count" -eq 0 ]; then
        pass "chat_interface.min.js is valid AMD (define() present, no raw ES6 import)"
    elif [ "$import_count" -gt 0 ]; then
        fail "chat_interface.min.js has raw ES6 'import' — rebuild: grunt babel (${import_count} occurrences)"
    else
        fail "chat_interface.min.js missing define() — not a valid AMD module"
    fi
fi

# =============================================================================
info "2/8  PHP syntax (all plugin .php files)"

php_errors=0; php_count=0
while IFS= read -r -d '' phpfile; do
    php_count=$((php_count + 1))
    result=$(php -l "$phpfile" 2>&1)
    if ! echo "$result" | grep -q "No syntax errors"; then
        fail "Syntax error in $(realpath --relative-to="$PLUGIN_DIR" "$phpfile"): $result"
        php_errors=$((php_errors + 1))
    fi
done < <(find "$PLUGIN_DIR" -name "*.php" -not -path "*/node_modules/*" -print0)

[ "$php_errors" -eq 0 ] && pass "All ${php_count} PHP files pass syntax check"

# =============================================================================
info "3/8  RAG backend health (http://127.0.0.1:8000/api/health)"

if ! command -v curl >/dev/null 2>&1; then
    warn "curl not found — skipping RAG backend check"
else
    http_code=$(curl -s -o /tmp/_cp_health.txt -w "%{http_code}" \
                     --connect-timeout 5 --max-time 10 \
                     http://127.0.0.1:8000/api/health 2>/dev/null)
    curl_exit=$?
    body=$(cat /tmp/_cp_health.txt 2>/dev/null); rm -f /tmp/_cp_health.txt

    if [ "$curl_exit" -ne 0 ]; then
        fail "RAG backend unreachable (curl exit $curl_exit) — is the Python service running?"
    elif [ "$http_code" -ne 200 ]; then
        fail "RAG backend returned HTTP $http_code (expected 200)"
    elif echo "$body" | grep -q '"status".*"healthy"'; then
        pass "RAG backend healthy (HTTP 200)"
    else
        warn "RAG backend HTTP 200 but unexpected body: $body"
    fi
fi

# =============================================================================
info "4-6/8  Database checks (tables, external functions, capabilities)"

if [ -z "$CONFIG_PHP" ]; then
    fail "config.php with \$CFG->dbhost not found — DB checks skipped"
else
    php_db_output=$(php << PHPEOF
<?php
\$config = file_get_contents('$CONFIG_PHP');

function extract_cfg(\$config, \$key) {
    if (preg_match('/\\\\\$CFG->' . preg_quote(\$key, '/') . '\s*=\s*[\'"]([^\'"]*)[\'"];/', \$config, \$m)) {
        return \$m[1];
    }
    return '';
}

\$dbhost = extract_cfg(\$config, 'dbhost') ?: 'localhost';
\$dbname = extract_cfg(\$config, 'dbname');
\$dbuser = extract_cfg(\$config, 'dbuser');
\$dbpass = extract_cfg(\$config, 'dbpass');
\$prefix = extract_cfg(\$config, 'prefix') ?: 'mdl_';
\$dbport = (int)(extract_cfg(\$config, 'dbport') ?: 3306);

\$mysqli = new mysqli(\$dbhost, \$dbuser, \$dbpass, \$dbname, \$dbport);
if (\$mysqli->connect_errno) {
    echo "DB_CONNECT_FAIL:" . \$mysqli->connect_error . "\n";
    exit(1);
}

// Check 4: Tables
\$tables = [
    \$prefix . 'craftpilot',
    \$prefix . 'craftpilot_conv',
    \$prefix . 'craftpilot_msg',
];
\$missing = [];
foreach (\$tables as \$t) {
    \$r = \$mysqli->query("SHOW TABLES LIKE '" . \$mysqli->real_escape_string(\$t) . "'");
    if (!\$r || \$r->num_rows === 0) \$missing[] = \$t;
}
echo empty(\$missing)
    ? "TABLES_PASS:All 3 tables exist\n"
    : "TABLES_FAIL:Missing: " . implode(', ', \$missing) . "\n";

// Check 5: External functions
\$expected_fns = [
    'mod_craftpilot_get_user_credentials',
    'mod_craftpilot_manage_conversations',
    'mod_craftpilot_manage_messages',
];
\$fn_tbl = \$prefix . 'external_functions';
\$placeholders = implode(',', array_fill(0, 3, '?'));
\$stmt = \$mysqli->prepare("SELECT name FROM \`\$fn_tbl\` WHERE name IN (\$placeholders)");
\$stmt->bind_param('sss', ...\$expected_fns);
\$stmt->execute();
\$found_fns = [];
\$res = \$stmt->get_result();
while (\$row = \$res->fetch_assoc()) \$found_fns[] = \$row['name'];
\$stmt->close();
\$missing_fns = array_diff(\$expected_fns, \$found_fns);
echo empty(\$missing_fns)
    ? "FUNCS_PASS:All 3 external functions registered\n"
    : "FUNCS_FAIL:Missing: " . implode(', ', \$missing_fns) . "\n";

// Check 6: Capabilities
\$cap_tbl = \$prefix . 'capabilities';
\$stmt2 = \$mysqli->prepare("SELECT name FROM \`\$cap_tbl\` WHERE name IN (?,?)");
\$c1 = 'mod/craftpilot:view'; \$c2 = 'mod/craftpilot:addinstance';
\$stmt2->bind_param('ss', \$c1, \$c2);
\$stmt2->execute();
\$found_caps = [];
\$res2 = \$stmt2->get_result();
while (\$row = \$res2->fetch_assoc()) \$found_caps[] = \$row['name'];
\$stmt2->close();
\$missing_caps = array_diff([\$c1, \$c2], \$found_caps);
echo empty(\$missing_caps)
    ? "CAPS_PASS:Both capabilities registered\n"
    : "CAPS_FAIL:Missing: " . implode(', ', \$missing_caps) . "\n";

\$mysqli->close();
PHPEOF
)

    while IFS= read -r line; do
        tag="${line%%:*}"; msg="${line#*:}"
        case "$tag" in
            DB_CONNECT_FAIL) fail "DB connection failed: $msg" ;;
            TABLES_PASS)     pass "DB tables: $msg" ;;
            TABLES_FAIL)     fail "DB tables: $msg — run Site Admin → Notifications" ;;
            FUNCS_PASS)      pass "External functions: $msg" ;;
            FUNCS_FAIL)      fail "External functions: $msg — purge caches or run upgrade" ;;
            CAPS_PASS)       pass "Capabilities: $msg" ;;
            CAPS_FAIL)       fail "Capabilities: $msg" ;;
            *)               warn "Unexpected PHP output: $line" ;;
        esac
    done <<< "$php_db_output"
fi

# =============================================================================
info "7/8  Template and CSS files"

for label_path in "templates/chat_interface.mustache:$TEMPLATE" "styles.css:$STYLES"; do
    label="${label_path%%:*}"; fpath="${label_path#*:}"
    if [ ! -f "$fpath" ]; then
        fail "$label is missing"
    elif [ ! -s "$fpath" ]; then
        fail "$label is empty (0 bytes)"
    else
        pass "$label present ($(wc -c < "$fpath") bytes)"
    fi
done

# =============================================================================
info "8/8  Cache purge"

if [ -z "$PURGE_CACHES" ]; then
    fail "purge_caches.php not found — cache purge skipped"
else
    purge_out=$(php "$PURGE_CACHES" 2>&1)
    purge_exit=$?
    if [ "$purge_exit" -eq 0 ]; then
        pass "php purge_caches.php exited 0"
    else
        fail "php purge_caches.php exited $purge_exit — $purge_out"
    fi
fi

# =============================================================================
printf "\n${BOLD}=== Summary ===${RESET}\n"
if [ "$FAIL" -eq 0 ] && [ "$WARN" -eq 0 ]; then
    printf "${GREEN}All checks passed.${RESET}\n\n"; exit 0
elif [ "$FAIL" -eq 0 ]; then
    printf "${YELLOW}All checks passed with %d warning(s).${RESET}\n\n" "$WARN"; exit 0
else
    printf "${RED}%d check(s) FAILED, %d warning(s).${RESET}\n\n" "$FAIL" "$WARN"; exit 1
fi
