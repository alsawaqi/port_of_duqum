# Local vendor login smoke test

Date: 7 September 2026  
Application: `http://www.localhost:1044/index.php/signin`  
Result: The tested login, CR selection, contact approval, and membership suspension scenarios passed. A read-only action display defect was found, corrected, and retested.

## Test method

Created six temporary external vendor identities and three synthetic companies in the local database. Contact identities and approvals used the application's `Vendor_contact_access::prepareContactAccess()` and `approveContact()` services. Company records and membership roles were prepared as test fixtures. Approval transitions were invoked locally through the service, not through the administrator approval screen.

All six users were then signed in through the actual browser sign-in form. Company selection and portal navigation were exercised in the browser. No session impersonation or password bypass was used.

SMS connection, live workflow SMS, and login OTP were disabled before the test and remained disabled. This test covers password authentication and vendor authorization; it did not send SMS, make payments, or repeat the public registration submission flow.

## Browser results

| Scenario | Observed result |
| --- | --- |
| Owner with one active CR | Went directly to Company A. Contact add/edit/delete controls were available. |
| Approved editor contact | Used its own email and password to reach Company A. Could open the bank account add form; contacts were read-only. |
| Approved bidder contact | Reached Company A and its Tenders tab. Opening the internal dashboard redirected back to the vendor portal. |
| Contact with two active CRs | Sign-in displayed Company A / Editor and Company B / Viewer. The pending third CR was excluded. |
| Portal requested before choosing a CR | Returned to the CR selection screen without opening a company workspace. |
| Shared contact selects A, then switches to B | Workspace company, CR, and capabilities changed to the selected membership. Company B showed its own contact count and profile details. Refresh retained B. |
| Returning shared contact signs in again | Received the CR selection screen again. |
| Owner with two active CRs | Selection displayed both companies with Owner roles. Choosing Company B opened B with contact management controls. |
| Contact awaiting approval | Correct password was rejected before approval. The same credentials worked after approval. |
| Selected Company B membership suspended | Refresh removed B access and resolved the remaining active Company A membership. The Switch CR control disappeared. |
| Contact's only membership suspended | Refresh returned to sign-in. Another login attempt failed. |

The six temporary identities were owner, editor, bidder, shared contact, multi-CR owner, and pending-then-approved viewer. Each used a separate email at `example.invalid`. Login uses the person's email/password; the CR is selected afterward when multiple active memberships exist.

## Defect corrected

A viewer could see Add in the Bank Accounts tab, but clicking it correctly produced a server-side 403. The same display omission existed for the other profile sections.

The Bank Accounts, Branches, Credentials, Specialties, and Documents views now render Add only when the current CR membership grants `profile.edit`. Their row edit/delete actions use the same permission check. Existing server authorization remains in place. The Contacts description now says access follows the assigned role, replacing the misleading promise of full access.

Browser retest confirmed:

- An editor still sees Add/Edit/Delete for a synthetic bank record and can open the Add form.
- A viewer sees that bank record without Add/Edit/Delete.
- A viewer sees no Add action in Branches, Credentials, Specialties, or Documents.

## Additional verification

Ten database-backed checks passed: unrelated owner CR denied; explicitly requested pending CR denied; pending CR selection denied; suspended CR denied; other active CR retained; CR-as-login rejected; wrong password rejected; email case normalization accepted; existing identity reused for another CR; global password preserved when another CR supplied a different initial password.

Existing regression checks passed:

- `VendorPortalRoleAuthorizationTest.php`
- `VendorMultiCrAuthenticationTest.php`
- `VendorContactAccessWorkflowTest.php`

PHP syntax checks passed for all seven modified PHP files. The scoped whitespace check passed.

## Cleanup and remaining scope

Removed the three synthetic companies, nine contacts, their membership records, and the synthetic bank record. All six test users were disabled, soft-deleted, and given unusable random passwords. Local verification found zero fixture companies and zero enabled test users remaining. Authentication audit history was retained. The administrator session was restored to Settings / SMS.

No production changes were deployed. Mandatory OTP remains a separate readiness item: the earlier review found guest-owner mobile numbers are not consistently copied to the login account's phone field. That issue was not changed by this login/CR smoke test.
