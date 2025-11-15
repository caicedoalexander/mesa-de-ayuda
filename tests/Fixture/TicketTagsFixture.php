<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TicketTagsFixture
 */
class TicketTagsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'ticket_id' => 1,
                'tag_id' => 1,
                'created' => '2025-11-08 13:10:31',
            ],
        ];
        parent::init();
    }
}
