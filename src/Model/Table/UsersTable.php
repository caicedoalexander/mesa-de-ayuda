<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Cache\Cache;
use Cake\Event\EventInterface;
use App\Service\S3Service;

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
     * Save profile image for a user
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

        // Sanitize filename
        $filename = basename($uploadedFile->getClientFilename());
        $mimeType = $uploadedFile->getClientMediaType();
        $size = $uploadedFile->getSize();

        // Only allow images
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($extension, $allowedImageExtensions)) {
            return ['success' => false, 'message' => 'Solo se permiten imágenes (JPG, PNG, GIF, WEBP)'];
        }

        // Check file size (max 2MB for profile images)
        if ($size > 2097152) {
            return ['success' => false, 'message' => 'La imagen no debe superar 2MB'];
        }

        // Generate unique filename
        $uniqueFilename = 'user_' . $userId . '_' . \Cake\Utility\Text::uuid() . '.' . $extension;

        // Check if S3 is enabled
        $s3Service = new S3Service();
        $useS3 = $s3Service->isEnabled();

        $storagePath = null;

        if ($useS3) {
            // S3 upload path
            $s3Key = 'profile_images/' . $uniqueFilename;

            // Move to temp location first
            $tempPath = sys_get_temp_dir() . DS . $uniqueFilename;
            try {
                $uploadedFile->moveTo($tempPath);

                // Upload to S3
                if (!$s3Service->uploadFile($tempPath, $s3Key, $mimeType)) {
                    \Cake\Log\Log::error('Failed to upload profile image to S3', ['s3_key' => $s3Key]);
                    @unlink($tempPath);
                    return ['success' => false, 'message' => 'Error al subir la imagen a S3'];
                }

                // Clean up temp file
                @unlink($tempPath);

                // Storage path for S3 (just the S3 key)
                $storagePath = $s3Key;
            } catch (\Exception $e) {
                \Cake\Log\Log::error('Failed to process profile image S3 upload', [
                    'error' => $e->getMessage(),
                ]);
                @unlink($tempPath);
                return ['success' => false, 'message' => 'Error al procesar la imagen'];
            }
        } else {
            // Local storage (original behavior)
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

            // Storage path for local (with "uploads/" prefix)
            $storagePath = 'uploads/profile_images/' . $uniqueFilename;
        }

        // Delete old profile image if exists
        $user = $this->get($userId);
        if ($user->profile_image) {
            $this->deleteProfileImage($user->profile_image);
        }

        return ['success' => true, 'filename' => $storagePath];
    }

    /**
     * Delete a profile image file
     *
     * @param string $filename Relative path to the profile image or S3 key
     * @return bool Success status
     */
    public function deleteProfileImage(string $filename): bool
    {
        if (empty($filename)) {
            return false;
        }

        // Check if S3 is enabled
        $s3Service = new S3Service();
        $useS3 = $s3Service->isEnabled();

        if ($useS3) {
            // Delete from S3
            return $s3Service->deleteFile($filename);
        } else {
            // Delete from local storage
            $fullPath = WWW_ROOT . $filename;
            if (file_exists($fullPath)) {
                return @unlink($fullPath);
            }
        }

        return false;
    }

    /**
     * Get profile image URL with fallback to default avatar
     *
     * @param string|null $profileImage Profile image path or S3 key
     * @return string URL to profile image or default avatar
     */
    public function getProfileImageUrl(?string $profileImage): string
    {
        if (empty($profileImage)) {
            // Return default avatar
            return '/img/default-avatar.png';
        }

        // Check if S3 is enabled
        $s3Service = new S3Service();
        $useS3 = $s3Service->isEnabled();

        if ($useS3) {
            // Generate presigned URL (valid for 60 minutes)
            $presignedUrl = $s3Service->getPresignedUrl($profileImage, 60);
            if ($presignedUrl) {
                return $presignedUrl;
            }
            // If presigned URL fails, return default avatar
            return '/img/default-avatar.png';
        } else {
            // Local file
            if (file_exists(WWW_ROOT . $profileImage)) {
                return '/' . str_replace(DS, '/', $profileImage);
            }
            // Return default avatar
            return '/img/default-avatar.png';
        }
    }
}
