#!/usr/bin/env python3
"""Wrap static English text in a Vue template with $t(). Prints the keys it wrapped, and the mixed
nodes (text next to {{ }}) it left for hand work. Usage: i18n_wrap.py FILE... (edits in place)."""
import re, sys, json

ATTRS = ['placeholder', 'title', 'aria-label', 'label', 'alt', 'subtitle']

def esc(s):
    return s.replace('\\', '\\\\').replace("'", "\\'")

def norm(s):
    return re.sub(r'\s+', ' ', s).strip()

def process(path):
    src = open(path, encoding='utf-8').read()
    m = re.search(r'<template>.*</template>', src, re.S)
    if not m:
        return [], []
    tpl = m.group(0)
    keys, mixed = [], []

    def text_node(mm):
        raw = mm.group(2)
        txt = norm(raw)
        if not re.search(r'[A-Za-z]{2}', txt):
            return mm.group(0)
        if '{{' in raw or '}}' in raw:
            return mm.group(0)
        if re.search(r'[=;()\[\]]', txt) and not re.search(r'[A-Za-z]{3,} [A-Za-z]{3,}', txt):
            return mm.group(0)
        keys.append(txt)
        lead = mm.group(1); trail = mm.group(3)
        return f"{lead}{{{{ $t('{esc(txt)}') }}}}{trail}"

    # text nodes between a tag end and the next tag start
    new = re.sub(r'((?<![=\-])>)([^<>{}]+?)(<)', text_node, tpl, flags=re.S)

    # static attributes -> bound
    def attr(mm):
        name, val = mm.group(1), mm.group(2)
        txt = norm(val)
        if not re.search(r'[A-Za-z]{2}', txt) or txt.startswith('/') or '{{' in txt:
            return mm.group(0)
        keys.append(txt)
        return f' :{name}="$t(\'{esc(txt)}\')"'
    new = re.sub(r' (' + '|'.join(ATTRS) + r')="([^"]*)"', attr, new)

    # mixed nodes for the report
    for mm in re.finditer(r'(?<![=\-])>([^<>]*\{\{[^<>]*\}\}[^<>]*|[^<>]*[A-Za-z]{3,}[^<>]*\{\{[^<>]*)<', new):
        t = norm(mm.group(1))
        if re.search(r'[A-Za-z]{3,}', re.sub(r"\{\{.*?\}\}", '', t)) and "$t(" not in t:
            mixed.append(t)
    if new != tpl:
        open(path, 'w', encoding='utf-8').write(src.replace(tpl, new))
    return keys, mixed

if __name__ == '__main__':
    out = {}
    for p in sys.argv[1:]:
        k, mx = process(p)
        out[p] = {'keys': k, 'mixed': mx}
    print(json.dumps(out, ensure_ascii=False, indent=1))
