<!-- templates/playPassChangeOrderTemplate.php -->
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="x-apple-disable-message-reformatting">
    <title></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        table {border-collapse:collapse;border-spacing:0;border:none;margin:0;}
        div, td {padding:0;}
        div {margin:0 !important;}
    </style>
    <![endif]-->
    <style>
        /* Base styles */
        body, table, td, div, p, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: Arial, sans-serif;
        }
        table, td {
            mso-table-lspace: 0;
            mso-table-rspace: 0;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            display: block;
        }
        /* Client-specific fixes */
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        /* Mobile styles */
        @media screen and (max-width: 530px) {
            .unsub {
                display: block !important;
                padding: 8px !important;
                margin-top: 14px !important;
                border-radius: 6px !important;
                background-color: #555555 !important;
                text-decoration: none !important;
                font-weight: bold !important;
            }
            .col-lge {
                max-width: 100% !important;
            }
            .mobile-padding {
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
        }
        /* Larger screen styles */
        @media screen and (min-width: 531px) {
            .col-sml {
                max-width: 27% !important;
            }
            .col-lge {
                max-width: 73% !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;word-spacing:normal;background-color:#659956;">
    <div role="article" aria-roledescription="email" lang="en" style="text-size-adjust:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:#659956;">
        <table role="presentation" style="width:100%;border-spacing:0;border-collapse:collapse;">
            <tr>
                <td align="center" style="padding:0;">
                    <!--[if mso]>
                    <table role="presentation" align="center" style="width:600px;">
                    <tr>
                    <td>
                    <![endif]-->
                    <table role="presentation" style="width:94%;max-width:600px;border:none;border-spacing:0;text-align:left;font-family:Arial,sans-serif;font-size:16px;line-height:22px;color:#363636;margin:10px auto;">
                        <tr>
                            <td style="padding:0;margin:0;background-color:#ffffff;">
                                <img src="https://cedarsprings.camp/email-assets/camp-banner.png" width="600" alt="Cedar Springs Camp" style="width:100%;max-width:600px;height:auto;border:none;display:block;" />
                            </td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="padding:20px 30px 11px 30px;background-color:#ffffff;text-align:center;">
                                <h1 style="margin-top:0;margin-bottom:16px;font-size:26px;line-height:32px;font-weight:bold;letter-spacing:-0.02em;">Play Pass Modification Request</h1>
                                {{ development }}
                            </td>
                        </tr>
                        <tr>
                            <td class="mobile-padding" style="padding:0 30px 11px 30px;background-color:#ffffff;">
                                <p style="margin-top:0;margin-bottom:12px;">A customer has requested modifications to their existing Play Pass registration. This requires manual updates in Ultracamp.</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="mobile-padding" style="padding:0 30px;background-color:#ffffff;">
                                <table role="presentation" style="width:100%;border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:15px;vertical-align:top;">
                                            <p style="margin:0;">
                                                <strong>Camper:</strong> {{ camper_name }}<br>
                                                <strong>Week:</strong> {{ week }}<br>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td class="mobile-padding" style="padding:10px 30px;background-color:#ffffff;">
                                <h3 style="margin-top:10px;margin-bottom:10px;font-size:18px;">Requested Changes:</h3>
                                
                                <h4 style="margin-top:15px;margin-bottom:5px;font-size:16px;">Days:</h4>
                                <p style="margin:0 0 10px 0;">
                                    <strong>Original:</strong> {{ original_days }}<br>
                                    <strong>New:</strong> {{ new_days }}
                                </p>
                                
                                <h4 style="margin-top:15px;margin-bottom:5px;font-size:16px;">Hot Lunch:</h4>
                                <p style="margin:0 0 10px 0;">
                                    <strong>Original:</strong> {{ original_lunch }}<br>
                                    <strong>New:</strong> {{ new_lunch }}
                                </p>
                                
                                <h4 style="margin-top:15px;margin-bottom:5px;font-size:16px;">Morning Extended Care:</h4>
                                <p style="margin:0 0 10px 0;">
                                    <strong>Original:</strong> {{ original_morning_care }}<br>
                                    <strong>New:</strong> {{ new_morning_care }}
                                </p>
                                
                                <h4 style="margin-top:15px;margin-bottom:5px;font-size:16px;">Afternoon Extended Care:</h4>
                                <p style="margin:0 0 10px 0;">
                                    <strong>Original:</strong> {{ original_afternoon_care }}<br>
                                    <strong>New:</strong> {{ new_afternoon_care }}
                                </p>

                                <h4 style="margin-top:15px;margin-bottom:5px;font-size:16px;">Drop Off Window:</h4>
                                <p style="margin:0 0 10px 0;">
                                    <strong>Original:</strong> {{ original_transportation }}<br>
                                    <strong>New:</strong> {{ new_transportation }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td class="mobile-padding" style="padding:20px 30px;background-color:#ffffff;text-align:center;">
                                <a href="https://www.ultracamp.com/admin/accounts/reservationDetail.aspx?id={{ reservation_ucid }}" style="background:#ffc300;text-decoration:none;padding:12px 30px;color:#222222;border-radius:4px;display:inline-block;mso-padding-alt:0;text-underline-color:#ffc300">
                                    <!--[if mso]><i style="letter-spacing:30px;mso-font-width:-100%;mso-text-raise:20pt">&nbsp;</i><![endif]-->
                                    <span style="mso-text-raise:10pt;font-weight:bold;">Load Reservation</span>
                                    <!--[if mso]><i style="letter-spacing:30px;mso-font-width:-100%">&nbsp;</i><![endif]-->
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td class="mobile-padding" style="padding:0 30px 20px 30px;background-color:#ffffff;text-align:center;">
                                <p style="margin:0;">Please update this reservation in Ultracamp manually. The customer has been notified that changes are being processed and will be completed within 2 business days.</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:30px;text-align:center;font-size:12px;color:#ffffff;">
                                <p style="margin:0;" class="unsub">This is an internal notification email, intended only for internal use by the staff of Cedar Springs Camp.</p>
                            </td>
                        </tr>
                    </table>
                    <!--[if mso]>
                    </td>
                    </tr>
                    </table>
                    <![endif]-->
                </td>
            </tr>
        </table>
    </div>
</body>
</html>