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
        'app.TicketComments',
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

    /**
     * Test createCommentFromEmail with authorized sender creates comment successfully
     *
     * @return void
     */
    public function testCreateCommentFromEmailWithAuthorizedSenderCreatesComment(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');
        $ticketCommentsTable = $this->getTableLocator()->get('TicketComments');

        // Create a ticket with authorized recipients
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-10001',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'gmail_message_id' => 'original-message-id',
            'gmail_thread_id' => 'thread-123',
            'email_to' => [
                ['email' => 'authorized@example.com', 'name' => 'Authorized User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Email data from authorized sender
        $emailData = [
            'from' => 'Authorized User <authorized@example.com>',
            'subject' => 'Re: Test Ticket',
            'body_html' => '<p>This is my reply to the ticket</p>',
            'body_text' => 'This is my reply to the ticket',
            'gmail_message_id' => 'reply-message-id',
            'gmail_thread_id' => 'thread-123',
        ];

        // Create comment from email
        $comment = $this->TicketService->createCommentFromEmail($ticket, $emailData);

        // Assert comment was created
        $this->assertInstanceOf(\App\Model\Entity\TicketComment::class, $comment);
        $this->assertEquals($ticket->id, $comment->ticket_id);
        $this->assertEquals('<p>This is my reply to the ticket</p>', $comment->body);
        $this->assertEquals('public', $comment->comment_type);
        $this->assertFalse($comment->is_system_comment);
        $this->assertEquals('reply-message-id', $comment->gmail_message_id);
        $this->assertFalse($comment->sent_as_email);

        // Verify comment was saved to database
        $savedComment = $ticketCommentsTable->get($comment->id);
        $this->assertNotNull($savedComment);
    }

    /**
     * Test createCommentFromEmail with unauthorized sender returns null
     *
     * @return void
     */
    public function testCreateCommentFromEmailWithUnauthorizedSenderReturnsNull(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');
        $ticketCommentsTable = $this->getTableLocator()->get('TicketComments');

        // Create a ticket with specific authorized recipients
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-10002',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'gmail_message_id' => 'original-message-id',
            'gmail_thread_id' => 'thread-456',
            'email_to' => [
                ['email' => 'authorized@example.com', 'name' => 'Authorized User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Email data from UNAUTHORIZED sender
        $emailData = [
            'from' => 'Unauthorized Sender <unauthorized@example.com>',
            'subject' => 'Re: Test Ticket',
            'body_html' => '<p>Unauthorized reply attempt</p>',
            'body_text' => 'Unauthorized reply attempt',
            'gmail_message_id' => 'unauthorized-message-id',
            'gmail_thread_id' => 'thread-456',
        ];

        // Get initial comment count
        $initialCount = $ticketCommentsTable->find()->count();

        // Attempt to create comment from unauthorized email
        $result = $this->TicketService->createCommentFromEmail($ticket, $emailData);

        // Assert null was returned
        $this->assertNull($result);

        // Verify no comment was created
        $finalCount = $ticketCommentsTable->find()->count();
        $this->assertEquals($initialCount, $finalCount);
    }

    /**
     * Test createCommentFromEmail truncates body content over 65000 characters
     *
     * @return void
     */
    public function testCreateCommentFromEmailTruncatesOversizedBody(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with authorized recipients
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-10003',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'gmail_message_id' => 'original-message-id',
            'gmail_thread_id' => 'thread-789',
            'email_to' => [
                ['email' => 'sender@example.com', 'name' => 'Sender'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Generate a body larger than 65000 characters
        $largeBody = str_repeat('This is a very long email body. ', 3000); // ~96000 chars

        $emailData = [
            'from' => 'Sender <sender@example.com>',
            'subject' => 'Re: Test Ticket',
            'body_html' => $largeBody,
            'body_text' => $largeBody,
            'gmail_message_id' => 'large-message-id',
            'gmail_thread_id' => 'thread-789',
        ];

        // Create comment from email
        $comment = $this->TicketService->createCommentFromEmail($ticket, $emailData);

        // Assert comment was created
        $this->assertInstanceOf(\App\Model\Entity\TicketComment::class, $comment);

        // Assert body was truncated to 65000 chars
        $this->assertEquals(65000, strlen($comment->body));
        $this->assertLessThan(strlen($largeBody), strlen($comment->body));
    }

    /**
     * Test createCommentFromEmail auto-creates user if not exists
     *
     * @return void
     */
    public function testCreateCommentFromEmailAutoCreatesUser(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');
        $usersTable = $this->getTableLocator()->get('Users');

        // Create a ticket with authorized recipients (including new user email)
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-10004',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'gmail_message_id' => 'original-message-id',
            'gmail_thread_id' => 'thread-auto',
            'email_to' => [
                ['email' => 'newuser@example.com', 'name' => 'New User'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Verify user doesn't exist yet
        $existingUser = $usersTable->find()->where(['email' => 'newuser@example.com'])->first();
        $this->assertNull($existingUser);

        // Email data from new user
        $emailData = [
            'from' => 'New User <newuser@example.com>',
            'subject' => 'Re: Test Ticket',
            'body_html' => '<p>Reply from new user</p>',
            'body_text' => 'Reply from new user',
            'gmail_message_id' => 'new-user-message-id',
            'gmail_thread_id' => 'thread-auto',
        ];

        // Create comment from email
        $comment = $this->TicketService->createCommentFromEmail($ticket, $emailData);

        // Assert comment was created
        $this->assertInstanceOf(\App\Model\Entity\TicketComment::class, $comment);

        // Verify user was auto-created
        $createdUser = $usersTable->find()->where(['email' => 'newuser@example.com'])->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('New', $createdUser->first_name);
        $this->assertEquals('User', $createdUser->last_name);
        $this->assertEquals('requester', $createdUser->role);
        $this->assertTrue($createdUser->is_active);

        // Verify comment is linked to auto-created user
        $this->assertEquals($createdUser->id, $comment->user_id);
    }

    /**
     * Test createCommentFromEmail processes attachments (basic test)
     *
     * @return void
     */
    public function testCreateCommentFromEmailProcessesAttachments(): void
    {
        $ticketsTable = $this->getTableLocator()->get('Tickets');

        // Create a ticket with authorized recipients
        $ticket = $ticketsTable->newEntity([
            'ticket_number' => 'TEST-2026-10005',
            'subject' => 'Test Ticket',
            'description' => 'Test description',
            'status' => 'nuevo',
            'priority' => 'media',
            'requester_id' => 1,
            'channel' => 'email',
            'gmail_message_id' => 'original-message-id',
            'gmail_thread_id' => 'thread-attach',
            'email_to' => [
                ['email' => 'sender@example.com', 'name' => 'Sender'],
            ],
        ]);
        $ticketsTable->saveOrFail($ticket);

        // Email data WITH attachments
        // Note: In real scenario, processEmailAttachments would download from Gmail
        // For this basic test, we just verify the method handles the attachment array
        $emailData = [
            'from' => 'Sender <sender@example.com>',
            'subject' => 'Re: Test Ticket',
            'body_html' => '<p>Reply with attachment</p>',
            'body_text' => 'Reply with attachment',
            'gmail_message_id' => 'attach-message-id',
            'gmail_thread_id' => 'thread-attach',
            'attachments' => [
                [
                    'filename' => 'document.pdf',
                    'mime_type' => 'application/pdf',
                    'attachment_id' => 'gmail-attachment-id-123',
                ],
            ],
        ];

        // Create comment from email
        // Note: This will attempt to download from Gmail API, which may fail in test environment
        // The important part is that the comment is created successfully
        $comment = $this->TicketService->createCommentFromEmail($ticket, $emailData);

        // Assert comment was created even if attachment processing failed
        $this->assertInstanceOf(\App\Model\Entity\TicketComment::class, $comment);
        $this->assertEquals('<p>Reply with attachment</p>', $comment->body);
    }
}
