<?php
declare(strict_types=1);

namespace App\Services\EmailSettingService;

use App\Helpers\ResponseError;
use App\Models\EmailSetting;
use App\Models\EmailSubscription;
use App\Models\EmailTemplate;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Settings;
use App\Models\Translation;
use App\Models\User;
use App\Services\CoreService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Exception;
use Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Storage;
use Throwable;
use View;

class EmailSendService extends CoreService
{
    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return EmailSetting::class;
    }

    /**
     * Port 465 is implicit TLS (SMTPS) - STARTTLS on that port never
     * completes the handshake and PHPMailer just times out with a
     * generic "Could not connect to SMTP host". Every other port (25,
     * 587, 2525, ...) expects the STARTTLS upgrade instead. PHPMailer's
     * own SMTPDebug only echoes to stdout, which is discarded in a
     * web/API request, so route it into Laravel's log instead whenever
     * the setting's smtp_debug flag is on.
     */
    private function configureSmtpSecurity(PHPMailer $mail, EmailSetting $emailSetting): void
    {
        $mail->SMTPSecure = (int) $emailSetting->port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        if ($emailSetting->smtp_debug) {
            $mail->SMTPDebug   = SMTP::DEBUG_CONNECTION;
            $mail->Debugoutput = function (string $str, int $level) {
                Log::channel('single')->debug("SMTP[$level]: " . trim($str));
            };
        }
    }

    public function sendSubscriptions(EmailTemplate $emailTemplate): array
    {
        $mail = new PHPMailer(true);

        try {
            $emailSetting = $emailTemplate->emailSetting;

            $mail->CharSet = 'UTF-8';

            // Настройки SMTP
            $mail->isSMTP();
            $mail->SMTPAuth     = $emailSetting->smtp_auth;

            $mail->Host         = $emailSetting->host;
            $mail->Port         = $emailSetting->port;
            $mail->Username     = $emailSetting->from_to;
            $mail->Password     = $emailSetting->password;
            $this->configureSmtpSecurity($mail, $emailSetting);
            $mail->SMTPOptions  = $emailSetting->ssl ?: [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            // От кого
            $mail->setFrom($emailSetting->from_to, $emailSetting->from_site);

            // Кому

            foreach (EmailSubscription::where('active', true)->get() as $subscribe) {

                /** @var EmailSubscription $subscribe */
                $email = data_get($subscribe->user, 'email');

                if (!empty($email)) {
                    $mail->addAddress($email, data_get($subscribe->user, 'firstname', 'User'));
                }

            }

            // Тема письма
            $mail->Subject = $emailTemplate->subject;

            // Тело письма
            $mail->isHTML();
            $mail->Body    = View::make('emails.layout', [
                'title'   => $emailTemplate->subject,
                'content' => $emailTemplate->body,
            ])->render();
            $mail->AltBody = $emailTemplate->alt_body; // Hello, world!

            // Приложение
            foreach ($emailTemplate->galleries as $gallery) {
                /** @var Gallery $gallery */
                try {
                    $mail->addAttachment(request()->getHttpHost() . '/storage/' . $gallery->path);
                } catch (Throwable) {
                    Log::error($mail->ErrorInfo);
                }
            }

            $mail->send();

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];

        } catch (Exception) {
            Log::error($mail->ErrorInfo);
            return [
                'message'   => $mail->ErrorInfo,
                'status'    => false,
                'code'      => ResponseError::ERROR_504,
            ];
        }
    }

    public function sendVerify(User $user): array
    {
        $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_VERIFY)->first();

        $mail = $this->emailBaseAuth($emailTemplate?->emailSetting, $user);

        try {

            $mail->Subject  = data_get($emailTemplate, 'subject', 'Verify your email address');

            $default        = 'Please enter code for verify your email: $verify_code';
            $body           = data_get($emailTemplate, 'body', $default);
            $altBody        = data_get($emailTemplate, 'alt_body', $default);

            $mail->Body     = View::make('emails.layout', [
                'title'   => $mail->Subject,
                'content' => str_replace('$verify_code', $user->verify_token, $body),
            ])->render();
            $mail->AltBody  = str_replace('$verify_code', $user->verify_token, $altBody);

            if (!empty(data_get($emailTemplate, 'galleries'))) {
                foreach ($emailTemplate->galleries as $gallery) {
                    /** @var Gallery $gallery */
                    try {
                        $mail->addAttachment(request()->getHttpHost() . '/storage/' . $gallery->path);
                    } catch (Throwable) {
                        Log::error($mail->ErrorInfo);
                    }
                }
            }

            $mail->send();

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Exception $e) {
            Log::error('ErrorInfo', [
                $mail->ErrorInfo
            ]);
            $this->error($e);
            return [
                'message'   => $mail->ErrorInfo,
                'status'    => false,
                'code'      => ResponseError::ERROR_504,
            ];
        }
    }

    public function sendEmailPasswordReset(User $user, $str): array
    {
        $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_VERIFY)->first();

        $mail = $this->emailBaseAuth($emailTemplate?->emailSetting, $user);

        try {

            $mail->Subject  = data_get($emailTemplate, 'subject', 'Reset password');

            $default        = 'Please enter code for reset your password: $verify_code';
            $body           = data_get($emailTemplate, 'body', $default);
            $altBody        = data_get($emailTemplate, 'alt_body', $default);

            $mail->Body     = View::make('emails.layout', [
                'title'   => $mail->Subject,
                'content' => str_replace('$verify_code', $str, $body),
            ])->render();
            $mail->AltBody  = str_replace('$verify_code', $str, $altBody);

            if (!empty(data_get($emailTemplate, 'galleries'))) {
                foreach ($emailTemplate->galleries as $gallery) {
                    /** @var Gallery $gallery */
                    try {
                        $mail->addAttachment(request()->getHttpHost() . '/storage/' . $gallery->path);
                    } catch (Throwable) {
                        Log::error($mail->ErrorInfo);
                    }
                }
            }

            $mail->send();

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Exception $e) {
            Log::error('ErrorInfo', [
                $mail->ErrorInfo
            ]);
            $this->error($e);
            return [
                'message'   => $mail->ErrorInfo,
                'status'    => false,
                'code'      => ResponseError::ERROR_504,
            ];
        }
    }

    /**
     * @param Order $order
     * @return array
     */
    public function sendOrder(Order $order): array
    {
        $order = $order->fresh([
            'user:id,firstname,lastname,phone,email',
            'currency:id,position,symbol',
            'shop:id,uuid,slug,phone',
            'shop.translation' => fn($q) => $q->select([
                'id',
                'shop_id',
                'locale',
                'title',
                'address',
            ])->where('locale', $this->language),
            'orderDetails' => fn($q) => $q->with([
                'stock.stockExtras.value',
                'stock.product.translation' => fn($q) => $q
                    ->select([
                        'id',
                        'product_id',
                        'locale',
                        'title',
                    ])
                    ->where('locale', $this->language),
                'stock.stockExtras.group.translation' => function ($q) {
                    $q
                        ->select('id', 'extra_group_id', 'locale', 'title')
                        ->where('locale', $this->language);
                },
            ]),
            'transactions.paymentSystem',
            'transactions.children',
            'coupon',
            'myAddress'
        ]);

        if (!$order->user->email) {
            return [
                'message' => 'email is empty', //$mail->ErrorInfo,
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
            ];
        }

        Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $titleKey = "order.email.invoice.$order->status.title";
        $title    = Translation::where(['locale' => $this->language, 'key' => $titleKey])->first()?->value ?? $titleKey;
        $logo     = Settings::where('key', 'logo')->first()?->value;
        $fileName = null;

        $pdf = View::make(
            'order-email-invoice',
            [
                'order' => $order,
                'lang'  => $this->language,
                'title' => $title,
                'logo'  => $logo,
            ]
        )->render();

        try {
            $mail           = $this->emailBaseAuth(null, $order->user);
            $mail->Subject  = $title;
            $mail->Body     = $pdf;
            $mail->addCustomHeader('MIME-Version', '1.0');
            $mail->addCustomHeader('Content-type', 'text/html;charset=UTF-8');
            $mail->send();

            Storage::delete(storage_path("images/$fileName"));

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
            ];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'message' => $e->getMessage(), //$mail->ErrorInfo,
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
            ];
        }
    }

    public function emailBaseAuth(?EmailSetting $emailSetting, User $user): PHPMailer
    {

        if (empty($emailSetting)) {
            $emailSetting = EmailSetting::where('active', true)->latest('updated_at')->first();
        }

        if (empty($emailSetting)) {
            throw new Exception('No active email provider is configured');
        }

        $mail = new PHPMailer(true);
        $mail->isHTML();
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->SMTPAuth     = $emailSetting->smtp_auth;
        $mail->Host         = $emailSetting->host;
        $mail->Port         = $emailSetting->port;
        $mail->Username     = $emailSetting?->from_to;
        $mail->Password     = $emailSetting?->password;
        $this->configureSmtpSecurity($mail, $emailSetting);
        $mail->SMTPOptions  = data_get($emailSetting, 'ssl.ssl.verify_peer') ? $emailSetting->ssl : [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        try {

            $mail->setFrom($emailSetting->from_to, $emailSetting->from_site);
            $mail->addAddress($user->email, $user->name_or_email);

        } catch (Throwable $e) {
            Log::error($mail->ErrorInfo);
            $this->error($e);
        }

        return $mail;
    }

    /**
     * Sends a plain test message using this exact provider row's own
     * credentials, regardless of whether it's marked active - so an admin
     * can verify a new SMTP configuration before switching to it, rather
     * than only finding out it's broken once it becomes "the" active
     * provider (see emailBaseAuth()'s active-only fallback, which this
     * intentionally bypasses).
     */
    public function sendTest(EmailSetting $emailSetting, string $recipientEmail): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isHTML();
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            // A bad host/port otherwise hangs for PHPMailer's default
            // ~5 minute timeout before this ever responds - unacceptable
            // for a "test this now" button an admin is actively waiting on.
            $mail->Timeout     = 15;
            $mail->SMTPAuth    = $emailSetting->smtp_auth;
            $mail->Host        = $emailSetting->host;
            $mail->Port        = $emailSetting->port;
            $mail->Username    = $emailSetting->from_to;
            $mail->Password    = $emailSetting->password;
            $this->configureSmtpSecurity($mail, $emailSetting);
            $mail->SMTPOptions = data_get($emailSetting, 'ssl.ssl.verify_peer') ? $emailSetting->ssl : [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ];

            $mail->setFrom($emailSetting->from_to, $emailSetting->from_site);
            $mail->addAddress($recipientEmail);
            $mail->Subject = 'Test email from ' . ($emailSetting->from_site ?: 'your platform');
            $mail->AltBody = 'This is a test email confirming your SMTP configuration works.';
            $mail->Body    = View::make('emails.layout', [
                'title'   => $mail->Subject,
                'content' => '<p>' . $mail->AltBody . '</p>',
            ])->render();

            $mail->send();

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $mail->ErrorInfo ?: $e->getMessage(),
            ];
        }
    }
}
