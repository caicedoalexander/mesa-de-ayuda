<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Cake\Log\Log;

/**
 * Gmail Email Composer
 *
 * Handles email composition and sending via Gmail API.
 * Extracted from GmailService to follow Single Responsibility Principle.
 *
 * Resolves: ARCH-001 (GmailService SRP violation)
 *
 * Responsibilities:
 * - Create MIME messages with attachments
 * - Handle email encoding (UTF-8, base64)
 * - Send emails via Gmail API
 * - Handle CC, BCC, and Reply-To
 */
class GmailEmailComposer
{
    private Gmail $gmailService;

    /**
     * Constructor
     *
     * @param Gmail $gmailService Gmail service instance
     */
    public function __construct(Gmail $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Send email via Gmail API
     *
     * @param string|array $to Recipient email or array of recipients
     * @param string $subject Subject
     * @param string $htmlBody HTML body
     * @param array $attachments Array of file paths
     * @param array $options Additional options: 'from', 'cc', 'bcc', 'replyTo', 'headers'
     * @return bool Success status
     */
    public function sendEmail($to, string $subject, string $htmlBody, array $attachments = [], array $options = []): bool
    {
        try {
            // Create MIME message
            $boundary = uniqid('boundary_');
            $rawMessage = $this->createMimeMessage($to, $subject, $htmlBody, $attachments, $boundary, $options);

            // Base64 encode for Gmail API
            $encodedMessage = base64_encode($rawMessage);
            $encodedMessage = strtr($encodedMessage, '+/', '-_');
            $encodedMessage = rtrim($encodedMessage, '=');

            $message = new Message();
            $message->setRaw($encodedMessage);

            $this->gmailService->users_messages->send('me', $message);

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending Gmail message: ' . $e->getMessage(), [
                'to' => is_array($to) ? implode(', ', array_keys($to)) : $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create MIME message for sending
     *
     * @param string|array $to Recipient(s)
     * @param string $subject Subject
     * @param string $htmlBody HTML body
     * @param array $attachments Attachments (file paths)
     * @param string $boundary MIME boundary
     * @param array $options Additional options (from, cc, bcc, replyTo, headers)
     * @return string MIME message
     */
    public function createMimeMessage($to, string $subject, string $htmlBody, array $attachments, string $boundary, array $options = []): string
    {
        // Build From header
        if (!empty($options['from'])) {
            if (is_array($options['from'])) {
                $fromEmail = array_keys($options['from'])[0];
                $fromName = $options['from'][$fromEmail];
                $message = "From: " . $this->encodeEmailHeader($fromName, $fromEmail) . "\r\n";
            } else {
                $sanitizedFrom = str_replace(["\r", "\n"], '', (string)$options['from']);
                $message = "From: {$sanitizedFrom}\r\n";
            }
        } else {
            $message = "";
        }

        // Build To header
        $message .= $this->buildRecipientHeader('To', $to);

        // Build CC header
        if (!empty($options['cc'])) {
            $message .= $this->buildRecipientHeader('Cc', $options['cc']);
        }

        // Build BCC header
        if (!empty($options['bcc'])) {
            $message .= $this->buildRecipientHeader('Bcc', $options['bcc']);
        }

        // Reply-To header
        if (!empty($options['replyTo'])) {
            $sanitizedReplyTo = str_replace(["\r", "\n"], '', (string)$options['replyTo']);
            $message .= "Reply-To: {$sanitizedReplyTo}\r\n";
        }

        // Custom headers
        if (!empty($options['headers'])) {
            foreach ($options['headers'] as $headerName => $headerValue) {
                $sanitizedName = str_replace(["\r", "\n"], '', (string)$headerName);
                $sanitizedValue = str_replace(["\r", "\n"], '', (string)$headerValue);
                $message .= "{$sanitizedName}: {$sanitizedValue}\r\n";
            }
        }

        // Sanitize and encode subject for UTF-8 characters (RFC 2047)
        $sanitizedSubject = str_replace(["\r", "\n"], '', $subject);
        $message .= "Subject: " . mb_encode_mimeheader($sanitizedSubject, 'UTF-8') . "\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

        // HTML body part
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        // Attachments
        foreach ($attachments as $filePath) {
            if (file_exists($filePath)) {
                $message .= $this->createAttachmentPart($filePath, $boundary);
            }
        }

        $message .= "--{$boundary}--";

        return $message;
    }

    /**
     * Build recipient header (To, Cc, Bcc)
     *
     * @param string $headerName Header name (To, Cc, Bcc)
     * @param string|array $recipients Recipients
     * @return string Header line
     */
    private function buildRecipientHeader(string $headerName, $recipients): string
    {
        if (is_array($recipients)) {
            $list = [];
            foreach ($recipients as $email => $name) {
                if (is_numeric($email)) {
                    $list[] = str_replace(["\r", "\n"], '', $name);
                } else {
                    $list[] = $this->encodeEmailHeader($name, $email);
                }
            }
            return "{$headerName}: " . implode(', ', $list) . "\r\n";
        }

        $sanitized = str_replace(["\r", "\n"], '', (string)$recipients);
        return "{$headerName}: {$sanitized}\r\n";
    }

    /**
     * Create attachment MIME part
     *
     * @param string $filePath File path
     * @param string $boundary MIME boundary
     * @return string Attachment MIME part
     */
    private function createAttachmentPart(string $filePath, string $boundary): string
    {
        $fileName = basename($filePath);
        $sanitizedFileName = str_replace(["\r", "\n"], '', $fileName);
        $encodedFileName = mb_encode_mimeheader($sanitizedFileName, 'UTF-8');
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $part = "--{$boundary}\r\n";
        $part .= "Content-Type: {$mimeType}; name=\"{$encodedFileName}\"\r\n";
        $part .= "Content-Disposition: attachment; filename=\"{$encodedFileName}\"\r\n";
        $part .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $part .= chunk_split(base64_encode($fileContent)) . "\r\n";

        return $part;
    }

    /**
     * Encode email header with name for UTF-8 characters
     *
     * @param string $name Name
     * @param string $email Email address
     * @return string Encoded header value
     */
    private function encodeEmailHeader(string $name, string $email): string
    {
        // Sanitize to prevent CRLF injection
        $name = str_replace(["\r", "\n"], '', $name);
        $email = str_replace(["\r", "\n"], '', $email);

        // If name contains non-ASCII characters, encode it
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            return mb_encode_mimeheader($name, 'UTF-8') . " <{$email}>";
        }

        return "{$name} <{$email}>";
    }
}
