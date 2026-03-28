<?php

declare(strict_types=1);

namespace Aicl\Notifications\Drivers;

use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * @codeCoverageIgnore External notification service
 */
class EmailDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        $config = $channel->config;
        $to = (array) ($config['to'] ?? []);
        $from = $config['from'] ?? null;
        $subjectPrefix = $config['subject_prefix'] ?? '';

        $title = $payload['title'] ?? 'Notification';
        $body = $payload['body'] ?? '';
        $subject = $subjectPrefix ? "{$subjectPrefix} {$title}" : $title;

        try {
            $message = Mail::raw($body, function ($mail) use ($to, $from, $subject): void {
                $mail->to($to)->subject($subject);

                if ($from) {
                    $mail->from($from);
                }
            });

            return DriverResult::success(response: ['recipients' => $to]);
        } catch (Throwable $e) {
            return DriverResult::failure(error: $e->getMessage());
        }
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['to'])) {
            $errors['to'] = 'At least one recipient email address is required.';
        } else {
            $recipients = (array) $config['to'];
            foreach ($recipients as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors['to'] = "Invalid email address: {$email}";
                    break;
                }
            }
        }

        if (! empty($config['from']) && ! filter_var($config['from'], FILTER_VALIDATE_EMAIL)) {
            $errors['from'] = 'From address must be a valid email.';
        }

        return $errors;
    }

    /**
     * @return array<string, array{type: string, label: string, required: bool}>
     */
    public function configSchema(): array
    {
        return [
            'to' => ['type' => 'array', 'label' => 'Recipient Email(s)', 'required' => true],
            'from' => ['type' => 'email', 'label' => 'From Address', 'required' => false],
            'subject_prefix' => ['type' => 'string', 'label' => 'Subject Prefix', 'required' => false],
        ];
    }
}
