<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TicketFollowersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TicketFollowersTable Test Case
 */
class TicketFollowersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TicketFollowersTable
     */
    protected $TicketFollowers;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.TicketFollowers',
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
        $config = $this->getTableLocator()->exists('TicketFollowers') ? [] : ['className' => TicketFollowersTable::class];
        $this->TicketFollowers = $this->getTableLocator()->get('TicketFollowers', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->TicketFollowers);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\TicketFollowersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\TicketFollowersTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
