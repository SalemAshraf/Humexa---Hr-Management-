#!/usr/bin/env python3
"""Second pass: inside {{ }} wrap quoted English literals in $t(), and wrap message-like values."""
import re,sys
MSG={'m','msg','$page.props.flash.success','$page.props.flash.status','page.props.flash.success','successMessage','errorMessage','messages[0]'}
def fix(expr):
    e=expr.strip()
    if '`' in e or '$t(' in e and "'" not in re.sub(r"\$t\('[^']*'[^)]*\)","",e): 
        pass
    if e in MSG: return ' $t(%s) '%e
    def lit(m):
        s=m.group(1)
        if not re.search(r'[A-Za-z]{2}',s): return m.group(0)
        return "$t('%s')"%s
    # skip parts already inside $t(...)
    parts=re.split(r"(\$t\((?:[^()]|\([^()]*\))*\))",expr)
    out=[]
    for p in parts:
        if p.startswith('$t('): out.append(p)
        else: out.append(re.sub(r"'((?:[^'\\]|\\.)*)'",lit,p))
    return ''.join(out)
for f in sys.argv[1:]:
    s=open(f).read()
    head,sep,tail=s.partition('<template>')
    tail=re.sub(r"\{\{(.*?)\}\}",lambda m:'{{'+fix(m.group(1))+'}}',tail,flags=re.S)
    open(f,'w').write(head+sep+tail)
