<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\S3Service;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Cache\Cache;
use Cake\Event\EventInterface;
use Cake\Utility\Text;

/**
 * Users Model
 *
 * @property \App\Model\Table\OrganizationsTable&\Cake\ORM\Association\BelongsTo $Organizations
 * @property \App\Model\Table\TicketCommentsTable&\Cake\ORM\Association\HasMany $TicketComments
 * @property \App\Model\Table\TicketFollowersTable&\Cake\ORM\Association\HasMany $TicketFollowers
 *
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\User> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\User findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\User> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\User|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\User saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\User>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\User> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UsersTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('first_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Organizations', [
            'foreignKey' => 'organization_id',
        ]);
        $this->hasMany('TicketComments', [
            'foreignKey' => 'user_id',
        ]);
        $this->hasMany('TicketFollowers', [
            'foreignKey' => 'user_id',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->email('email', false, 'Debe ser un correo electrónico válido')  // false = less strict, allows localhost
            ->requirePresence('email', 'create')
            ->notEmptyString('email')
            ->add('email', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('password')
            ->maxLength('password', 255)
            ->allowEmptyString('password');

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 100)
            ->requirePresence('first_name', 'create')
            ->notEmptyString('first_name');

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 100)
            ->requirePresence('last_name', 'create')
            ->notEmptyString('last_name');

        $validator
            ->scalar('phone')
            ->maxLength('phone', 50)
            ->allowEmptyString('phone');

        $validator
            ->scalar('role')
            ->maxLength('role', 50)
            ->notEmptyString('role')
            ->inList('role', ['admin', 'agent', 'compras', 'servicio_cliente', 'requester'], 'Rol no válido');

        $validator
            ->integer('organization_id')
            ->allowEmptyString('organization_id');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        $validator
            ->scalar('profile_image')
            ->maxLength('profile_image', 255)
            ->allowEmptyString('profile_image');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']), ['errorField' => 'email']);
        $rules->add($rules->existsIn(['organization_id'], 'Organizations'), ['errorField' => 'organization_id']);

        return $rules;
    }

    /**
     * Allowed image MIME types for profile images
     */
    private const ALLOWED_IMAGE_MIMES = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    /**
     * Save profile image for a user (supports S3 and local storage)
     *
     * @param int $userId User ID
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded file
     * @return array Result with success status and filename or error message
     */
    public function saveProfileImage(int $userId, $uploadedFile): array
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            \Cake\Log\Log::error('Profile image upload error', ['error' => $uploadedFile->getError()]);
            return ['success' => false, 'message' => 'Error al subir el archivo'];
        }

        $filename = basename($uploadedFile->getClientFilename());
        $mimeType = $uploadedFile->getClientMediaType();
        $size = $uploadedFile->getSize();

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED_IMAGE_MIMES[$extension])) {
            return ['success' => false, 'message' => 'Solo se permiten imágenes (JPG, PNG, GIF, WEBP)'];
        }

        if (!in_array($mimeType, self::ALLOWED_IMAGE_MIMES[$extension])) {
            return ['success' => false, 'message' => 'El tipo MIME no coincide con la extensión del archivo'];
        }

        if ($size > 2097152) {
            return ['success' => false, 'message' => 'La imagen no debe superar 2MB'];
        }

        // Verify actual MIME type from file content (finfo security check)
        $tempPath = $uploadedFile->getStream()->getMetadata('uri');
        if ($tempPath && file_exists($tempPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $actualMime = finfo_file($finfo, $tempPath);
                finfo_close($finfo);
                if ($actualMime !== false && !in_array($actualMime, self::ALLOWED_IMAGE_MIMES[$extension])) {
                    \Cake\Log\Log::error('Profile image MIME verification failed', [
                        'claimed' => $mimeType,
                        'actual' => $actualMime,
                    ]);
                    return ['success' => false, 'message' => 'El contenido del archivo no corresponde a una imagen válida'];
                }
            }
        }

        $uniqueFilename = 'user_' . $userId . '_' . Text::uuid() . '.' . $extension;

        // Delete old profile image before uploading new one
        $user = $this->get($userId);
        if ($user->profile_image) {
            $this->deleteProfileImage($user->profile_image);
        }

        // Check if S3 is enabled
        $s3Service = new S3Service();
        if ($s3Service->isEnabled()) {
            return $this->saveProfileImageToS3($s3Service, $uploadedFile, $uniqueFilename, $mimeType);
        }

        return $this->saveProfileImageLocally($uploadedFile, $uniqueFilename);
    }

    /**
     * Save profile image to S3
     *
     * @param S3Service $s3Service S3 service instance
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded file
     * @param string $uniqueFilename Generated filename
     * @param string $mimeType MIME type
     * @return array Result
     */
    private function saveProfileImageToS3(S3Service $s3Service, $uploadedFile, string $uniqueFilename, string $mimeType): array
    {
        $s3Key = 'profile_images/' . $uniqueFilename;
        $tempPath = sys_get_temp_dir() . DS . $uniqueFilename;

        try {
            $uploadedFile->moveTo($tempPath);

            if (!$s3Service->uploadFile($tempPath, $s3Key, $mimeType)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => 'Error al subir la imagen a S3'];
            }

            @unlink($tempPath);

            return ['success' => true, 'filename' => $s3Key];
        } catch (\Exception $e) {
            \Cake\Log\Log::error('Failed to save profile image to S3', ['error' => $e->getMessage()]);
            @unlink($tempPath);
            return ['success' => false, 'message' => 'Error al guardar la imagen'];
        }
    }

    /**
     * Save profile image to local filesystem
     *
     * @param \Psr\Http\Message\UploadedFileInterface $uploadedFile Uploaded file
     * @param string $uniqueFilename Generated filename
     * @return array Result
     */
    private function saveProfileImageLocally($uploadedFile, string $uniqueFilename): array
    {
        $uploadDir = WWW_ROOT . 'uploads' . DS . 'profile_images' . DS;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                \Cake\Log\Log::error('Failed to create profile images directory', ['dir' => $uploadDir]);
                return ['success' => false, 'message' => 'Error al crear directorio de imágenes'];
            }
        }

        $fullPath = $uploadDir . $uniqueFilename;

        try {
            $uploadedFile->moveTo($fullPath);
        } catch (\Exception $e) {
            \Cake\Log\Log::error('Failed to save profile image', [
                'error' => $e->getMessage(),
                'path' => $fullPath,
            ]);
            return ['success' => false, 'message' => 'Error al guardar la imagen'];
        }

        return ['success' => true, 'filename' => 'uploads/profile_images/' . $uniqueFilename];
    }

    /**
     * Delete a profile image file (S3 or local based on file_path origin)
     *
     * Convention: paths starting with 'uploads/' are local, otherwise S3.
     *
     * @param string $filename Relative path to the profile image
     * @return bool Success status
     */
    public function deleteProfileImage(string $filename): bool
    {
        if (empty($filename)) {
            return false;
        }

        // Detect origin from file_path convention
        if (!str_starts_with($filename, 'uploads/')) {
            // S3 file
            $s3Service = new S3Service();
            if ($s3Service->isEnabled()) {
                return $s3Service->deleteFile($filename);
            }

            return false;
        }

        // Local file
        $fullPath = WWW_ROOT . $filename;
        if (file_exists($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }

    /**
     * Get profile image URL with fallback to default avatar
     *
     * Supports both S3 (presigned URL) and local filesystem paths.
     * Convention: paths starting with 'uploads/' are local, otherwise S3.
     *
     * @param string|null $profileImage Profile image path
     * @return string URL to profile image or default avatar
     */
    public function getProfileImageUrl(?string $profileImage): string
    {
        if (empty($profileImage)) {
            return '/img/default-avatar.png';
        }

        // S3 file (no 'uploads/' prefix)
        if (!str_starts_with($profileImage, 'uploads/')) {
            $s3Service = new S3Service();
            if ($s3Service->isEnabled()) {
                $url = $s3Service->getPresignedUrl($profileImage, 60);
                if ($url) {
                    return $url;
                }
            }

            return '/img/default-avatar.png';
        }

        // Local file
        if (file_exists(WWW_ROOT . $profileImage)) {
            return '/' . str_replace(DS, '/', $profileImage);
        }

        return '/img/default-avatar.png';
    }
}
