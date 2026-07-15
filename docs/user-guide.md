# CrediTrack user guide

## Administrator

Activate both packages, configure the `creditrack_settings` option for the company and lending policy, and assign CrediTrack roles to WordPress users. In the portal, create a client, create a Pending loan, approve it, then disburse it. Disbursement generates the complete schedule. Post repayments from Payments; they allocate oldest installment first and the loan completes only when total repayable is settled. Review operational totals on Dashboard, portfolio grouping under Reports, users under Users, and your own credentials under Profile.

## Loan Officer

Officers can create clients and loans and post repayments. Approval and disbursement appear only when enabled by policy. Officers cannot administer users, global settings, backups, or payment reversals. A Viewer has read-only portal access.

## Operational cautions

Never modify financial rows directly in MySQL. Correct posted payments only through the controlled reversal workflow once enabled. Take host-level backups before updates. Dashboard figures are live ledger aggregates; investigate differences rather than overwriting totals.

## Deliberate demonstration data

From the WordPress root, run `wp creditrack demo --confirm=CREATE-DEMO-DATA`. This creates synthetic clients and only creates missing demo accounts; it never overwrites an existing user's password. Demo users are forced to replace their temporary passwords. Never run this command on a production installation.
