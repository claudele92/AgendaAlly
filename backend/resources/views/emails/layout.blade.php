<!DOCTYPE html>
<html lang="en">
<?php
/**
 * @var string $title
 * @var string $content
 */
use App\Models\Settings;

$settings = Settings::whereIn('key', ['logo', 'title', 'instagram', 'facebook', 'twitter', 'linkedin', 'footer_text'])
    ->pluck('value', 'key');

$logo       = $settings->get('logo');
$appName    = $settings->get('title') ?: 'AgendaAlly';
$footerText = $settings->get('footer_text') ?: '&copy; ' . date('Y') . ' ' . $appName;

// Same bare "host/path" shape the storefront footer stores these in -
// prefix with https:// the same way web/components/footer/footer.tsx does,
// and skip the badge entirely when a platform isn't configured, rather
// than link to nothing.
$socials = [
    'IG' => $settings->get('instagram'),
    'FB' => $settings->get('facebook'),
    'X'  => $settings->get('twitter'),
    'in' => $settings->get('linkedin'),
];
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? $appName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:32px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; max-width:600px; width:100%;">
                <tr>
                    <td align="center" style="padding:32px 24px 16px;">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $appName }}" height="40" style="height:40px; width:auto; max-width:220px;">
                        @else
                            <span style="font-size:22px; font-weight:700; color:#111827;">{{ $appName }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 32px 32px; color:#111827; font-size:15px; line-height:1.6;">
                        {!! $content !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 32px; background-color:#18181b;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            @if(collect($socials)->filter()->isNotEmpty())
                                <tr>
                                    <td align="center" style="padding-bottom:16px;">
                                        @foreach($socials as $label => $url)
                                            @if($url)
                                                <a href="https://{{ $url }}" style="display:inline-block; width:32px; height:32px; line-height:32px; border-radius:50%; background-color:#3f3f46; color:#ffffff; text-align:center; text-decoration:none; font-size:12px; font-weight:700; margin:0 4px;">{{ $label }}</a>
                                            @endif
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td align="center" style="color:#a1a1aa; font-size:12px;">
                                    {!! $footerText !!}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
