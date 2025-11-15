<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TicketCommentsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TicketCommentsTable Test Case
 */
class TicketCommentsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TicketCommentsTable
     */
    protected $TicketComments;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.TicketComments',
        'app.Tickets',
        'app.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('TicketComments') ? [] : ['className' => TicketCommentsTable::class];
        $this->TicketComments = $this->getTableLocator()->get('TicketComments', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->TicketComments);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\TicketCommentsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\TicketCommentsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
