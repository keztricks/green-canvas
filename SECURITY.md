# Security policy

## Reporting a vulnerability

Please **do not open a public GitHub issue** for security vulnerabilities.

Email the maintainer directly: **kieran [at] kilburn-phillips.co.uk**

Include:

- A description of the issue and its impact.
- Steps to reproduce, or a proof of concept.
- Any suggested mitigations.

You should expect an acknowledgement within seven days. Green Canvas is a small open-source project run on a best-effort basis — fix turnaround depends on severity and on the maintainer's availability — but credible reports will always be responded to.

## Scope

This repository contains the application source. Any deployment is operated by the branch hosting it; vulnerabilities in a specific deployment (e.g. weak passwords, exposed admin endpoints, misconfigured servers) are the responsibility of that deployment's operators, not this project.

If you find a vulnerability that affects all deployments — for example, an authentication bypass, a SQL injection, a privilege-escalation bug, or sensitive data exposure in the application code itself — that's in scope. Please report it.

## Sensitive data

Green Canvas handles UK electoral-register data and canvassing notes. These are not personal data in the strictest GDPR sense (the register is public for permitted political-organisation use), but they are still data that real people have a reasonable expectation will not be exposed. Operators are expected to:

- Run the app over HTTPS.
- Use strong unique admin passwords.
- Restrict access to the import area to trusted admins only.
- Keep regular backups and have a deletion plan.

If you're a developer pushing to a fork, please **do not commit real electoral-register data** to a public repository. The example data files committed to this repo are licence-permitted public datasets; per-elector data is not.
