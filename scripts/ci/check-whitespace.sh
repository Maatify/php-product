#!/usr/bin/env bash
# Fails on trailing whitespace in tracked text files and on `git diff --check`
# defects. Markdown is excluded from the trailing-space scan because two trailing
# spaces are a deliberate hard line break there (the Owner-approved architecture
# document uses them); Markdown still goes through `git diff --check`.
set -euo pipefail

trailing_whitespace=''
if git_grep_output="$(git grep -nI -E '[[:blank:]]$' -- . ':(exclude)vendor' ':(exclude)*.md')"; then
    trailing_whitespace="$git_grep_output"
elif [[ "$?" -ne 1 ]]; then
    echo 'Unable to inspect tracked files for trailing whitespace.' >&2
    exit 1
fi

if [[ -n "$trailing_whitespace" ]]; then
    echo 'Trailing whitespace detected:' >&2
    printf '%s\n' "$trailing_whitespace" >&2
    exit 1
fi

git diff --check
echo 'Whitespace verification passed.'
