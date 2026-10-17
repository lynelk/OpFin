#!/usr/bin/env python3
"""Check that the core distributable OpFin wordmarks use only path geometry.

Static export verification, not visual QA, trademark clearance or store approval.
"""
from pathlib import Path
import sys
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
FILES = (
    'brand/v3/assets/opfin-wordmark.svg',
    'brand/v3/assets/opfin-lockup-horizontal.svg',
    'brand/v3/assets/opfin-lockup-stacked.svg',
    'brand/v3/assets/opfin-symbol-master.svg',
)
errors = []
for name in FILES:
    doc = ET.parse(ROOT / name).getroot()
    tags = [element.tag.rsplit('}', 1)[-1] for element in doc.iter()]
    unsafe = set(tags).intersection({'text', 'tspan', 'foreignObject', 'image', 'script'})
    paths = [e for e in doc.iter() if e.tag.rsplit('}', 1)[-1] == 'path' and e.get('d')]
    uses_fonts = any('font-family' in str(el.attrib) or 'font:' in str(el.attrib) for el in doc.iter())
    if unsafe or not paths or uses_fonts:
        errors.append(name)
if errors:
    print('Vector master verification failed for '+str(len(errors))+' files (no SVG content disclosed).', file=sys.stderr)
    sys.exit(1)
print('Four core brand vector masters contain outlined path geometry; external visual/trademark clearance remains outstanding.')
