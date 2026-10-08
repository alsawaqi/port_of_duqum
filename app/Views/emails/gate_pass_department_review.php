<!DOCTYPE html>
<?php
$reference = trim((string) ($request->reference ?? ''));
$department = trim((string) ($request->department_name ?? ''));
$requester = trim((string) ($request->requester_name ?? '')) ?: 'a registered user';
$greeting = $department !== '' ? 'Dear Manager of ' . $department . (preg_match('/\bdepartment$/i', $department) ? ',' : ' Department,') : 'Dear Department Manager,';
$summary = [
    'Request reference' => $reference,
    'Requested by' => $requester,
    'Company' => trim((string) ($request->company_name ?? '')) ?: 'Not specified',
    'Department' => $department ?: 'Not specified',
    'Purpose of visit' => trim((string) ($request->purpose_name ?? '')) ?: 'Not specified',
    'Visit dates' => $visit_from_label . ' – ' . $visit_to_label,
];
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>New gate pass request</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table, td { mso-table-lspace:0; mso-table-rspace:0; }
        table { border-collapse:collapse; }
        @media only screen and (max-width:600px) {
            .email-outer { padding:16px 8px !important; }
            .email-content { padding-left:24px !important; padding-right:24px !important; }
            .email-heading { font-size:25px !important; line-height:32px !important; }
            .email-summary-label { width:112px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f2f5f8;color:#22364b;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;font-size:1px;color:#f2f5f8;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">Gate pass <?= esc($reference) ?> from <?= esc($requester) ?> is ready for your department to review.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f2f5f8">
        <tr><td class="email-outer" align="center" style="padding:36px 16px;">
            <!--[if mso]><table role="presentation" width="640" align="center" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" style="max-width:640px;background-color:#ffffff;border:1px solid #dfe6ed;">
                <tr><td height="5" bgcolor="#0874b7" style="height:5px;font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td class="email-content" style="padding:30px 40px 26px;border-bottom:1px solid #e8edf2;">
                    <img src="<?= esc($logo_url, 'attr') ?>" width="224" height="64" alt="Port of Duqm" style="display:block;width:224px;max-width:100%;height:auto;border:0;">
                    <p style="margin:16px 0 0;font-size:10px;line-height:16px;font-weight:bold;letter-spacing:2px;color:#60758b;">OPERATIONAL PORTAL &nbsp; / &nbsp; GATE PASS</p>
                </td></tr>
                <tr><td class="email-content" style="padding:30px 40px 0;">
                    <p style="margin:0 0 10px;font-size:11px;line-height:17px;font-weight:bold;letter-spacing:1.5px;color:#0874b7;">ACTION REQUIRED</p>
                    <h1 class="email-heading" style="margin:0 0 22px;font-size:28px;line-height:36px;font-weight:bold;color:#12344e;">A new gate pass<br>is ready for your review.</h1>
                    <p style="margin:0 0 12px;font-size:15px;line-height:24px;color:#22364b;overflow-wrap:anywhere;"><?= esc($greeting) ?></p>
                    <p style="margin:0 0 24px;font-size:15px;line-height:25px;color:#52667a;overflow-wrap:anywhere;"><strong style="color:#22364b;"><?= esc($requester) ?></strong> has submitted a new gate pass request to your department. Please sign in to your dashboard to review the request and take the necessary action.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f7f9fb" style="background-color:#f7f9fb;border:1px solid #e1e8ef;table-layout:fixed;">
                        <?php foreach ($summary as $label => $value) { ?>
                        <tr>
                            <td class="email-summary-label" width="152" valign="top" style="padding:12px 14px;border-bottom:1px solid #e1e8ef;color:#617386;font-size:12px;line-height:19px;width:152px;"><?= esc($label) ?></td>
                            <td valign="top" style="padding:12px 14px;border-bottom:1px solid #e1e8ef;color:#22364b;font-size:13px;line-height:19px;font-weight:bold;word-wrap:break-word;overflow-wrap:anywhere;"><?= esc($value) ?></td>
                        </tr>
                        <?php } ?>
                    </table>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:26px;">
                        <tr><td align="center" bgcolor="#076daf" style="background-color:#076daf;border-radius:5px;mso-padding-alt:15px 26px;">
                            <a href="<?= esc($dashboard_url, 'attr') ?>" style="display:inline-block;padding:15px 26px;color:#ffffff;font-size:14px;line-height:20px;font-weight:bold;text-decoration:none;border:1px solid #076daf;border-radius:5px;mso-padding-alt:0;">View dashboard &nbsp; &rarr;</a>
                        </td></tr>
                    </table>
                    <p style="margin:14px 0 28px;font-size:12px;line-height:20px;color:#758497;">Sign in with your registered account to view this request.</p>
                </td></tr>
                <tr><td class="email-content" style="padding:22px 40px 26px;border-top:1px solid #e8edf2;">
                    <p style="margin:0 0 6px;font-size:13px;line-height:20px;font-weight:bold;color:#12344e;">Port of Duqm &middot; Gate Pass Services</p>
                    <p style="margin:0;font-size:11px;line-height:19px;color:#758497;">This is an automated notification from the Operational Portal.</p>
                    <p style="margin:14px 0 0;font-size:11px;line-height:18px;color:#758497;">If the button does not open, copy this address into your browser:<br><a href="<?= esc($dashboard_url, 'attr') ?>" style="color:#076daf;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;"><?= esc($dashboard_url) ?></a></p>
                </td></tr>
                <tr><td height="3" bgcolor="#ad965c" style="height:3px;font-size:0;line-height:0;">&nbsp;</td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</body>
</html>
