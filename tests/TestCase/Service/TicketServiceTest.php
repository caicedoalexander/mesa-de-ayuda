<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TicketService;
use Cake\TestSuite\TestCase;

/**
 * App\Service\TicketService Test Case
 */
class TicketServiceTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Service\TicketService
     */
    protected $TicketService;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Tickets',
        'app.Organizations',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->TicketService = new TicketService();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->TicketService);
        parent::tearDown();
    }

    /**
     * Test isEmailInTicketRecipients returns true when email is in To field
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsReturnsTrueForEmailInTo(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with email_to containing the test email
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00001',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'email_to' => [
                ['email' => 'recipient@example.com', 'name' => 'Recipient User'],
                ['email' => 'another@example.com', 'name' => 'Another User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test email in To field
        $result = $method->invoke($this->TicketService, $ticket, 'recipient@example.com');
        $this->assertTrue($result);
    }

    /**
     * Test isEmailInTicketRecipients returns true when email is in CC field
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsReturnsTrueForEmailInCc(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with email_cc containing the test email
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00002',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'email_cc' => [
                ['email' => 'cc@example.com', 'name' => 'CC User'],
                ['email' => 'another-cc@example.com', 'name' => 'Another CC User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test email in CC field
        $result = $method->invoke($this->TicketService, $ticket, 'cc@example.com');
        $this->assertTrue($result);
    }

    /**
     * Test isEmailInTicketRecipients returns true for requester email
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsReturnsTrueForRequesterEmail(): void
    {
        $usersTable = $this->getTableLocator()->get('Users');
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a user
        $user = $usersTable->newEntity([
            'email' => 'requester@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'Requester',
            'role' => 'requester',
            'is_active' => true,
        ]);
        $usersTable->saveOrFail($user);

        // Create a ticket with this requester
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00003',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => $user->id,
            'channel' => 'email',
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test requester email
        $result = $method->invoke($this->TicketService, $ticket, 'requester@example.com');
        $this->assertTrue($result);
    }

    /**
     * Test isEmailInTicketRecipients is case-insensitive
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsIsCaseInsensitive(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with email_to in lowercase
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00004',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'email_to' => [
                ['email' => 'lowercase@example.com', 'name' => 'Test User'],
            ],
            'email_cc' => [
                ['email' => 'ccuser@example.com', 'name' => 'CC User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test uppercase email matching lowercase stored email
        $result = $method->invoke($this->TicketService, $ticket, 'LOWERCASE@EXAMPLE.COM');
        $this->assertTrue($result);

        // Test mixed case email
        $result = $method->invoke($this->TicketService, $ticket, 'LowerCase@Example.Com');
        $this->assertTrue($result);

        // Test CC field case-insensitivity
        $result = $method->invoke($this->TicketService, $ticket, 'CCUSER@EXAMPLE.COM');
        $this->assertTrue($result);
    }

    /**
     * Test isEmailInTicketRecipients handles whitespace
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsHandlesWhitespace(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with email_to
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00005',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'email_to' => [
                ['email' => 'test@example.com', 'name' => 'Test User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test email with leading/trailing whitespace
        $result = $method->invoke($this->TicketService, $ticket, '  test@example.com  ');
        $this->assertTrue($result);
    }

    /**
     * Test isEmailInTicketRecipients returns false for unauthorized email
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsReturnsFalseForUnauthorizedEmail(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with specific recipients
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00006',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'email_to' => [
                ['email' => 'authorized@example.com', 'name' => 'Authorized User'],
            ],
            'email_cc' => [
                ['email' => 'cc-authorized@example.com', 'name' => 'CC Authorized'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test unauthorized email
        $result = $method->invoke($this->TicketService, $ticket, 'unauthorized@example.com');
        $this->assertFalse($result);
    }

    /**
     * Test isEmailInTicketRecipients returns false for empty recipients
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsReturnsFalseForEmptyRecipients(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with no email_to or email_cc
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00007',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test with no recipients
        $result = $method->invoke($this->TicketService, $ticket, 'any@example.com');
        $this->assertFalse($result);
    }

    /**
     * Test isEmailInTicketRecipients handles recipient without email key
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsHandlesRecipientWithoutEmailKey(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with malformed recipient (missing 'email' key)
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00008',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Manually set malformed data (bypass marshalling)
        $ticket->email_to = [
            ['name' => 'No Email Key'], // Missing 'email' key
            ['email' => 'valid@example.com', 'name' => 'Valid User'],
        ];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Should find the valid email
        $result = $method->invoke($this->TicketService, $ticket, 'valid@example.com');
        $this->assertTrue($result);

        // Should not error out on malformed data
        $result = $method->invoke($this->TicketService, $ticket, 'nonexistent@example.com');
        $this->assertFalse($result);
    }

    /**
     * Test isEmailInTicketRecipients with both To and CC fields populated
     *
     * @return void
     */
    public function testIsEmailInTicketRecipientsWithBothToAndCc(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with both email_to and email_cc
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-00009',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'email_to' => [
                ['email' => 'to1@example.com', 'name' => 'To User 1'],
                ['email' => 'to2@example.com', 'name' => 'To User 2'],
            ],
            'email_cc' => [
                ['email' => 'cc1@example.com', 'name' => 'CC User 1'],
                ['email' => 'cc2@example.com', 'name' => 'CC User 2'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->TicketService);
        $method = $reflection->getMethod('isEmailInTicketRecipients');
        $method->setAccessible(true);

        // Test all To recipients
        $this->assertTrue($method->invoke($this->TicketService, $ticket, 'to1@example.com'));
        $this->assertTrue($method->invoke($this->TicketService, $ticket, 'to2@example.com'));

        // Test all CC recipients
        $this->assertTrue($method->invoke($this->TicketService, $ticket, 'cc1@example.com'));
        $this->assertTrue($method->invoke($this->TicketService, $ticket, 'cc2@example.com'));

        // Test unauthorized email
        $this->assertFalse($method->invoke($this->TicketService, $ticket, 'unauthorized@example.com'));
    }
}
