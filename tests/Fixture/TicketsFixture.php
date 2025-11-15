<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * TicketsFixture
 */
class TicketsFixture extends TestFixture
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
                'ticket_number' => 'Lorem ipsum dolor ',
                'gmail_message_id' => 'Lorem ipsum dolor sit amet',
                'gmail_thread_id' => 'Lorem ipsum dolor sit amet',
                'subject' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'status' => 'Lorem ipsum dolor ',
                'priority' => 'Lorem ipsum dolor ',
                'requester_id' => 1,
                'assignee_id' => 1,
                'organization_id' => 1,
                'channel' => 'Lorem ipsum dolor sit amet',
                'source_email' => 'Lorem ipsum dolor sit amet',
                'created' => '2025-11-08 13:10:25',
                'modified' => '2025-11-08 13:10:25',
                'resolved_at' => '2025-11-08 13:10:25',
                'first_response_at' => '2025-11-08 13:10:25',
            ],
        ];
        parent::init();
    }
}
