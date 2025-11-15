<?php
declare(strict_types=1);

namespace App\Command;

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
            'short' => 'q',
            'help' => 'Gmail search query',
            'default' => 'is:unread',
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
        $maxResults = (int)$args->getOption('max');
        $query = (string)$args->getOption('query');

        $io->out('Starting Gmail import...');
        $io->out("Query: {$query}");
        $io->out("Max results: {$maxResults}");
        $io->hr();

        try {
            // Get Gmail configuration from settings
            $config = $this->getGmailConfig();

            if (empty($config['refresh_token'])) {
                $io->error('Gmail not configured. Please authorize Gmail in Admin Settings.');
                return self::CODE_ERROR;
            }

            // Initialize services
            $gmailService = new GmailService($config);
            $ticketService = new TicketService();

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

            // Process each message
            foreach ($messageIds as $index => $messageId) {
                $io->out("[" . ($index + 1) . "/" . count($messageIds) . "] Processing message: {$messageId}");

                try {
                    // Parse message
                    $emailData = $gmailService->parseMessage($messageId);

                    $io->verbose("  From: {$emailData['from']}");
                    $io->verbose("  Subject: {$emailData['subject']}");

                    // Check if ticket already exists
                    $ticketsTable = $this->fetchTable('Tickets');
                    $existing = $ticketsTable->find()
                        ->where(['gmail_message_id' => $messageId])
                        ->first();

                    if ($existing) {
                        $io->verbose("  Skipped: Ticket already exists (#{$existing->ticket_number})");
                        $skipped++;
                        continue;
                    }

                    // Create ticket
                    $ticket = $ticketService->createFromEmail($emailData);

                    if ($ticket) {
                        $io->success("  Created ticket: #{$ticket->ticket_number}");
                        $created++;

                        // Mark as read
                        $gmailService->markAsRead($messageId);
                    } else {
                        $io->error("  Failed to create ticket");
                        $errors++;
                    }
                } catch (\Exception $e) {
                    $io->error("  Error: {$e->getMessage()}");
                    Log::error('Import Gmail error', [
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $errors++;
                }

                // Small delay to avoid rate limits
                usleep(100000); // 0.1 seconds
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
     * Get Gmail configuration from system settings
     *
     * @return array
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
            $config[$key] = $setting->setting_value;
        }

        return $config;
    }
}
