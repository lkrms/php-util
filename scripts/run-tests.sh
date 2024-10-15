#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

# run <command> [<argument>...]
function run() {
    printf '==> running:%s\n' "$(printf ' %q' "$@")" >&2
    local s=0
    "$@" || s=$?
    printf '\n' >&2
    return "$s"
}

function run_with_php_versions() {
    local php versions=()
    while [[ $1 == [78][0-9] ]]; do
        if type -P "php$1" >/dev/null; then
            versions[${#versions[@]}]=php$1
        fi
        shift
    done
    for php in "${versions[@]-php}"; do
        run "$php" "$@" || return
    done
}

[[ ${BASH_SOURCE[0]} -ef scripts/run-tests.sh ]] ||
    die "must run from root of package folder"

run scripts/generate.php --check
run tools/php-cs-fixer check --diff --verbose
run tools/pretty-php --diff
run_with_php_versions 83 74 vendor/bin/phpstan
run scripts/stop-mockoon.sh || (($? == 1)) || die 'error stopping mockoon'
run scripts/start-mockoon.sh >/dev/null
trap 'run scripts/stop-mockoon.sh' EXIT
run_with_php_versions 83 74 82 81 80 vendor/bin/phpunit
