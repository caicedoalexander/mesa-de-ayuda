<?php
declare(strict_types=1);

namespace App\Utility;

/**
 * Email Parsing Utility
 *
 * Centralizes email address parsing logic used across Gmail services.
 * Eliminates duplication between GmailService and GmailMessageParser.
 *
 * @see \App\Service\GmailService
 * @see \App\Service\Gmail\GmailMessageParser
 */
class EmailParsingUtility
{
    /**
     * Extract email address from "Name <email@example.com>" format
     *
     * @param string $emailString Raw email string
     * @return string Lowercase email address, or empty string if invalid
     */
    public static function extractEmailAddress(string $emailString): string
    {
        // Match email in angle brackets: "Name <email@domain.com>"
        if (preg_match('/<([^>]+)>/', $emailString, $matches)) {
            return strtolower(trim($matches[1]));
        }

        // Just email address without brackets
        $trimmed = trim($emailString);
        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return strtolower($trimmed);
        }

        return $trimmed;
    }

    /**
     * Extract display name from "Name <email@example.com>" format
     *
     * @param string $emailString Raw email string
     * @return string Name portion, or empty string if no name found
     */
    public static function extractName(string $emailString): string
    {
        if (preg_match('/^([^<]+)</', $emailString, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"");
        }

        return '';
    }

    /**
     * Parse recipients header into structured array
     *
     * Parses email headers like "To" or "Cc" that may contain multiple recipients
     * in format: "Name1 <email1@example.com>, Name2 <email2@example.com>"
     *
     * @param string $recipientsHeader Raw header string with recipients
     * @return array Array of recipients with 'name' and 'email' keys
     */
    public static function parseRecipients(string $recipientsHeader): array
    {
        if (empty($recipientsHeader)) {
            return [];
        }

        $recipients = [];

        // Split by comma, handling quoted names that may contain commas
        preg_match_all('/(?:[^,"]|"[^"]*")+/', $recipientsHeader, $matches);

        foreach ($matches[0] as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) {
                continue;
            }

            $email = self::extractEmailAddress($recipient);
            $name = self::extractName($recipient);

            if (!empty($email)) {
                $recipients[] = [
                    'name' => $name ?: $email,
                    'email' => $email,
                ];
            }
        }

        return $recipients;
    }

    /**
     * Get a specific header value from an array of header objects
     *
     * @param array $headers Array of header objects with getName()/getValue()
     * @param string $name Header name to find (case-insensitive)
     * @return string Header value or empty string
     */
    public static function getHeader(array $headers, string $name): string
    {
        $nameLower = strtolower($name);
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === $nameLower) {
                return $header->getValue();
            }
        }

        return '';
    }
}
