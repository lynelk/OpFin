# OpFin v3 Visual QA Checklist

Status: Controlled acceptance checklist  
Updated: 24 September 2026

## Automated/static checks

- [ ] `python3 scripts/sync-brand.py --check` passes.
- [ ] `python3 scripts/verify-security-controls.py` passes.
- [ ] No retired palette/type values remain in active Web styles except documented semantic/compatibility exceptions.
- [ ] Web and mobile builds/tests pass.
- [ ] Generated app/store assets match recorded hashes.
- [ ] Required text colour pairs meet WCAG AA.

## Mobile

Test representative small, mid-size and large Android screens.

- [ ] 100% text scale.
- [ ] 125% text scale.
- [ ] 150% text scale.
- [ ] 200% text scale on critical flows.
- [ ] Portrait layout.
- [ ] Keyboard open on registration/forms.
- [ ] Long UGX amounts.
- [ ] Long names/translated strings where available.
- [ ] No clipped Next Step content.
- [ ] Financial Compass/status content remains understandable.
- [ ] TalkBack labels and focus order checked.
- [ ] Reduced-motion mode checked.
- [ ] High-contrast preference checked where supported.
- [ ] Low-connectivity/provider-error states remain branded and understandable.

## Desktop / Web

At minimum:
- [ ] 320 px viewport.
- [ ] 375 px viewport.
- [ ] 768 px viewport.
- [ ] 1024 px viewport.
- [ ] 1440 px viewport.
- [ ] 200% browser zoom.
- [ ] Keyboard-only navigation.
- [ ] Visible focus.
- [ ] Screen-reader landmarks/headings.
- [ ] No horizontal scroll caused by financial values.
- [ ] Marketing and Workspace visual language is coherent.
- [ ] Product illustration is labelled illustrative where applicable.

## Dark/reverse-background use

OpFin does not require a full dark-mode product merely to prove the logo can survive darkness.

- [ ] reverse monogram/lock-up on Strong Indigo;
- [ ] reverse monogram/lock-up on Indigo;
- [ ] no Apricot text used where contrast fails;
- [ ] partner marks retain sufficient separation;
- [ ] white/ivory assets are not placed on uncontrolled photography without a contrast field.

## Print

Print or proof at:
- [ ] A4 document cover;
- [ ] A4 internal page;
- [ ] presentation handout;
- [ ] greyscale;
- [ ] office-printer quality;
- [ ] high-quality colour output.

Check:
- [ ] minimum logo size;
- [ ] fine rules survive;
- [ ] Apricot remains distinguishable;
- [ ] body copy remains readable;
- [ ] URLs/legal copy are not cut off.

## Store graphics

- [ ] 512 × 512 icon reviewed at full size and at 48 px.
- [ ] 1024 × 500 feature graphic reviewed without unintended text strike-through.
- [ ] screenshot safe areas reviewed against current Play presentation.
- [ ] no illustration is described as a production screenshot.

## Sign-off record

Record:
- candidate version;
- commit SHA;
- reviewer;
- device/browser/printer;
- date;
- defects found;
- defect disposition.

Do not mark this checklist complete based only on source inspection. Physical rendering has an irritating habit of finding problems that CSS felt strongly should not exist.
