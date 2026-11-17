# Security

## Supported versions

The 0.11.x line, the framework's.

## Reporting

Report a vulnerability privately to angel@projectsaturnstudios.com, not in a public issue.

## What this package touches

It hands font family names to fontconfig through ext-fontconfig, escaped for fontconfig's name syntax, and answers the
file paths fontconfig gives back. Fontconfig reads its configuration (`FONTCONFIG_FILE` or the system's `fonts.conf`)
and the font files it names; whoever controls that configuration controls which files are answered. This package
opens no font file itself.
