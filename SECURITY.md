# Security Policy

## Supported versions

Venusian is pre-1.0. No 0.x release receives security fixes or advisories; fixes land in the
next release line. Security support starts with 1.0.

| Version | Security fixes |
|---------|----------------|
| < 1.0   | No             |

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: the **Report a vulnerability** button on this repository's
**Security** tab. If that isn't available, email **info@projectsaturnstudios.com**.

Include what you found, the affected version, and steps to reproduce. Reports are read and
weighed for the release line in development; before 1.0 there is no response-time commitment.

## Security model

- Family names from a `FontRequest` go to fontconfig through ext-fontconfig, escaped for fontconfig's name syntax (`\`, `-`, `,`, `:`), so a name cannot add a property to the pattern.
- The file paths answered are fontconfig's. Fontconfig reads its configuration (`FONTCONFIG_FILE`, or the system's `fonts.conf`) and the font files it names; whoever controls that configuration controls which files are answered.
- This package opens no font file itself and writes nothing. Fontconfig may write its own cache where its configuration says.
