<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\EmailTemplateService;
use Cake\TestSuite\TestCase;

/**
 * EmailTemplateService Test Case
 *
 * Basic tests to verify template loading and rendering functionality.
 */
class EmailTemplateServiceTest extends TestCase
{
    protected array $fixtures = [
        'app.EmailTemplates',
        'app.SystemSettings',
    ];

    private EmailTemplateService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailTemplateService();
    }

    public function tearDown(): void
    {
        unset($this->service);
        parent::tearDown();
    }

    /**
     * Test getTemplate returns template data
     */
    public function testGetTemplateReturnsTemplateData(): void
    {
        $template = $this->service->getTemplate('nuevo_ticket');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertArrayHasKey('body_html', $template);
    }

    /**
     * Test getTemplate returns null for non-existent template
     */
    public function testGetTemplateReturnsNullForNonExistent(): void
    {
        $template = $this->service->getTemplate('non_existent_template');

        $this->assertNull($template);
    }

    /**
     * Test renderTemplate replaces variables correctly
     */
    public function testRenderTemplateReplacesVariables(): void
    {
        $template = [
            'subject' => 'Ticket {{ticket_number}}',
            'body_html' => '<p>Hello {{name}}, ticket {{ticket_number}}</p>',
        ];

        $variables = [
            'ticket_number' => 'TK-001',
            'name' => 'John Doe',
        ];

        $rendered = $this->service->renderTemplate($template, $variables);

        $this->assertEquals('Ticket TK-001', $rendered['subject']);
        $this->assertStringContainsString('Hello John Doe', $rendered['body']);
        $this->assertStringContainsString('ticket TK-001', $rendered['body']);
    }

    /**
     * Test renderTemplate handles non-scalar values
     */
    public function testRenderTemplateHandlesNonScalarValues(): void
    {
        $template = [
            'subject' => 'Test {{value}}',
            'body_html' => 'Body {{value}}',
        ];

        $variables = [
            'value' => ['array', 'value'], // Non-scalar
        ];

        $rendered = $this->service->renderTemplate($template, $variables);

        // Non-scalar values should be replaced with empty string
        $this->assertEquals('Test ', $rendered['subject']);
        $this->assertEquals('Body ', $rendered['body']);
    }

    /**
     * Test getSystemVariables returns expected keys
     */
    public function testGetSystemVariablesReturnsExpectedKeys(): void
    {
        $variables = $this->service->getSystemVariables();

        $this->assertArrayHasKey('system_title', $variables);
        $this->assertArrayHasKey('current_year', $variables);
        $this->assertEquals(date('Y'), $variables['current_year']);
    }

    /**
     * Test getAndRender convenience method
     */
    public function testGetAndRenderConvenienceMethod(): void
    {
        $rendered = $this->service->getAndRender('nuevo_ticket', [
            'ticket_number' => 'TK-999',
        ]);

        $this->assertIsArray($rendered);
        $this->assertArrayHasKey('subject', $rendered);
        $this->assertArrayHasKey('body', $rendered);
    }

    /**
     * Test getAndRender returns null for non-existent template
     */
    public function testGetAndRenderReturnsNullForNonExistent(): void
    {
        $rendered = $this->service->getAndRender('non_existent', ['key' => 'value']);

        $this->assertNull($rendered);
    }
}
