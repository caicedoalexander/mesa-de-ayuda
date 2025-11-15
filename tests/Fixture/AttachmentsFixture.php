<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AttachmentsFixture
 */
class AttachmentsFixture extends TestFixture
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
                'comment_id' => 1,
                'filename' => 'Lorem ipsum dolor sit amet',
                'original_filename' => 'Lorem ipsum dolor sit amet',
                'file_path' => 'Lorem ipsum dolor sit amet',
                'mime_type' => 'Lorem ipsum dolor sit amet',
                'file_size' => 1,
                'is_inline' => 1,
                'content_id' => 'Lorem ipsum dolor sit amet',
                'uploaded_by' => 1,
                'created' => '2025-11-08 13:10:27',
            ],
        ];
        parent::init();
    }
}
