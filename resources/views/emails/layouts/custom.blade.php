<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>@yield('title', $gym->name ?? config('app.name'))</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }

        /* Layout */
        .email-body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; }
        .email-body_inner { width: 570px; margin: 0 auto; padding: 0; background-color: #ffffff; }
        .body-action { width: 100%; margin: 30px auto; padding: 0; text-align: center; }
        .button { display: inline-block; color: #ffffff; text-decoration: none; border-radius: 3px; -webkit-text-size-adjust: none; box-sizing: border-box; }
        .button--primary { background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc; }
        .button--green { background-color: #38c172; border-top: 10px solid #38c172; border-right: 18px solid #38c172; border-bottom: 10px solid #38c172; border-left: 18px solid #38c172; }
        .button--red { background-color: #e3342f; border-top: 10px solid #e3342f; border-right: 18px solid #e3342f; border-bottom: 10px solid #e3342f; border-left: 18px solid #e3342f; }

        /* Typography */
        .content-cell h1 { margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold; text-align: left; }
        .content-cell h2 { margin-top: 0; color: #2d3748; font-size: 16px; font-weight: bold; text-align: left; }
        .content-cell h3 { margin-top: 0; color: #2d3748; font-size: 14px; font-weight: bold; text-align: left; }
        .content-cell p { margin-top: 0; color: #718096; font-size: 16px; line-height: 1.5em; text-align: left; }
        .content-cell p.sub { font-size: 12px; }
        .content-cell ol li { margin-top: 0; color: #718096; font-size: 14px; line-height: 1.5em; text-align: left; }

        /* Utility */
        .align-right { text-align: right; }
        .align-center { text-align: center; }

        @media only screen and (max-width: 600px) {
            .email-body_inner, .email-footer { width: 100% !important; }
        }

        @media only screen and (max-width: 500px) {
            .button { width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f8fafc;">
    <table class="email-body" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0; padding: 0; width: 100%; background-color: #f8fafc;">
        <tr>
            <td align="center" style="padding: 25px 0;">
                <table class="email-body_inner" width="570" cellpadding="0" cellspacing="0" role="presentation" style="width: 570px; margin: 0 auto; background-color: #ffffff;">
                    {{-- Header --}}
                    <tr>
                        <td style="padding: 25px 35px; text-align: center;">
                            @if(isset($gym) && ($gym->pwa_logo_url || $gym->logo_path))
                                <a href="{{ $gym->website ?? '#' }}" style="display: inline-block; text-decoration: none;">
                                    <img src="{{ $gym->pwa_logo_url ?: Storage::disk('public')->url($gym->logo_path) }}"
                                         alt="{{ $gym->name }}"
                                         width="200"
                                         style="max-width: 200px; max-height: 60px; width: auto; height: auto; border: 0;">
                                </a>
                            @elseif(isset($gym))
                                <a href="{{ $gym->website ?? '#' }}" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 19px; font-weight: bold; color: #3d4852; text-decoration: none;">
                                    {{ $gym->name }}
                                </a>
                            @endif
                        </td>
                    </tr>

                    {{-- Content --}}
                    <tr>
                        <td class="content-cell" style="padding: 35px;">
                            @yield('content')

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr><td style="border-top: 1px solid #edeff2; padding-top: 15px;"><p style="margin: 0; font-size: 12px; color: #b0adc5;">Diese E-Mail wurde automatisch generiert.</p></td></tr>
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Footer --}}
                <table class="email-footer" width="570" cellpadding="0" cellspacing="0" role="presentation" style="width: 570px; margin: 0 auto; padding: 0; text-align: center;">
                    <tr>
                        <td style="padding: 35px;" align="center">
                            @if(isset($gym))
                                <p style="margin: 0 0 5px; color: #b0adc5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5em; text-align: center;">
                                    <strong>{{ $gym->name }}</strong>
                                </p>
                                @if($gym->address)
                                    <p style="margin: 0 0 5px; color: #b0adc5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5em; text-align: center;">
                                        {{ $gym->address }}, {{ $gym->postal_code }} {{ $gym->city }}
                                    </p>
                                @endif
                                <p style="margin: 0 0 5px; color: #b0adc5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5em; text-align: center;">
                                    @if($gym->phone)
                                        Tel: {{ $gym->phone }}
                                    @endif
                                    @if($gym->phone && $gym->email)
                                        &bull;
                                    @endif
                                    @if($gym->email)
                                        {{ $gym->email }}
                                    @endif
                                </p>
                                @if($gym->website)
                                    <p style="margin: 0 0 5px; color: #b0adc5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5em; text-align: center;">
                                        <a href="{{ $gym->website }}" style="color: #b0adc5;">{{ $gym->website }}</a>
                                    </p>
                                @endif
                            @endif
                            <p style="margin: 10px 0 0; color: #b0adc5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5em; text-align: center;">
                                &copy; {{ date('Y') }} {{ isset($gym) ? $gym->name : config('app.name') }}. Alle Rechte vorbehalten.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
