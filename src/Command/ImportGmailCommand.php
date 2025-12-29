<?php
declare(strict_types=1);

namespace App\Command;

use App\Utility\SettingsEncryptionTrait;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use App\Service\GmailService;
use App\Service\TicketService;

/**
 * ImportGmail command
 *
 * Imports emails from Gmail and creates tickets
 * Usage: bin/cake import_gmail
 */
class ImportGmailCommand extends Command
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;

    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/5/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->setDescription('Import emails from Gmail and create tickets');

        $parser->addOption('max', [
            'short' => 'm',
            'help' => 'Maximum number of messages to import',
            'default' => 50,
        ]);

        $parser->addOption('query', [
            'help' => 'Gmail search query',
            'default' => 'is:unread',
        ]);

        $parser->addOption('delay', [
            'short' => 'd',
            'help' => 'Delay between messages in milliseconds',
            'default' => 1000,
        ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $maxResults = (int) $args->getOption('max');
        $query = (string) $args->getOption('query');
        $delay = (int) $args->getOption('delay');

        $io->out('Starting Gmail import...');
        $io->out("Query: {$query}");
        $io->out("Max results: {$maxResults}");
        $io->out("Delay: {$delay}ms");
        $io->hr();

        try {
            // Get Gmail configuration from settings
            $config = $this->getGmailConfig();

            if (empty($config['refresh_token'])) {
                $io->error('Gmail not configured. Please authorize Gmail in Admin Settings.');
                return self::CODE_ERROR;
            }

            // Load system settings once to pass to services (avoid redundant queries)
            $systemConfig = $this->getSystemSettings();

            // Initialize services
            $gmailService = new GmailService($config);
            $ticketService = new TicketService($systemConfig);

            // Get messages
            $io->out('Fetching messages from Gmail...');
            $messageIds = $gmailService->getMessages($query, $maxResults);

            if (empty($messageIds)) {
                $io->info('No messages found.');
                return self::CODE_SUCCESS;
            }

            $io->out("Found " . count($messageIds) . " messages");
            $io->hr();

            $created = 0;
            $skipped = 0;
            $errors = 0;

            // Pre-fetch existing message IDs for batch checking
            $ticketsTable = $this->fetchTable('Tickets');
            $existingMessageIds = $ticketsTable->find()
                ->select(['gmail_message_id'])
                ->where(['gmail_message_id IN' => $messageIds])
                ->all()
                ->extract('gmail_message_id')
                ->toArray();

            // Process each message
            foreach ($messageIds as $index => $messageId) {
                $io->out("[" . ($index + 1) . "/" . count($messageIds) . "] Processing message: {$messageId}");

                try {
                    // Check if ticket already exists (using pre-fetched data)
                    if (in_array($messageId, $existingMessageIds)) {
                        $io->verbose("  Skipped: Ticket already exists");
                        $skipped++;
                        continue;
                    }

                    // Parse message
                    $emailData = $gmailService->parseMessage($messageId);

                    $io->verbose("  From: {$emailData['from']}");
                    $io->verbose("  Subject: {$emailData['subject']}");

                    // Create ticket
                    $ticket = $ticketService->createFromEmail($emailData);

                    if ($ticket) {
                        try {
                            $io->success("  Created ticket: #{$ticket->ticket_number}");
                        } catch (\Exception $ioException) {
                            // Ignore console write errors on Windows
                        }
                        $created++;

                        // Mark as read
                        $gmailService->markAsRead($messageId);
                    } else {
                        try {
                            $io->error("  Failed to create ticket");
                        } catch (\Exception $ioException) {
                            // Ignore console write errors on Windows
                        }
                        $errors++;
                    }
                } catch (\Exception $e) {
                    try {
                        $io->error("  Error: {$e->getMessage()}");
                    } catch (\Exception $ioException) {
                        // Ignore console write errors on Windows
                    }

                    Log::error('Import Gmail error', [
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $errors++;
                }

                // Configurable delay to avoid rate limits
                if ($delay > 0) {
                    usleep($delay * 1000); // Convert ms to microseconds
                }
            }

            // Summary
            $io->hr();
            $io->out('Import completed!');
            $io->out("  Created: {$created}");
            $io->out("  Skipped: {$skipped}");
            $io->out("  Errors: {$errors}");

            Log::info('Gmail import completed', [
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);

            return self::CODE_SUCCESS;
        } catch (\Exception $e) {
            $io->error('Fatal error: ' . $e->getMessage());
            Log::error('Gmail import fatal error: ' . $e->getMessage());
            return self::CODE_ERROR;
        }
    }

    /**
     * Get Gmail configuration from system settings (with automatic decryption)
     *
     * @return array<string, string>
     */
    private function getGmailConfig(): array
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $settings = $settingsTable->find()
            ->where(['setting_key IN' => ['gmail_refresh_token', 'gmail_client_secret_path']])
            ->all();

        $config = [];
        foreach ($settings as $setting) {
            $key = str_replace('gmail_', '', $setting->setting_key);
            // Decrypt sensitive values
            $config[$key] = $this->shouldEncrypt($setting->setting_key)
                ? $this->decryptSetting($setting->setting_value, $setting->setting_key)
                : $setting->setting_value;
        }

        return $config;
    }

    /**
     * Get all system settings (for passing to services, with automatic decryption)
     *
     * @return array<string, string>
     */
    private function getSystemSettings(): array
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $settings = $settingsTable->find()
            ->select(['setting_key', 'setting_value'])
            ->toArray();

        $config = [];
        foreach ($settings as $setting) {
            $config[$setting->setting_key] = $setting->setting_value;
        }

        // Decrypt sensitive values automatically
        return $this->processSettings($config);
    }
}
