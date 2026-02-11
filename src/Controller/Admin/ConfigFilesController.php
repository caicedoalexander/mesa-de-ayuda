<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\S3Service;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;

/**
 * Config Files Controller
 *
 * Handles uploading and managing configuration files like:
 * - Gmail client_secret.json
 */
class ConfigFilesController extends AppController
{
    /**
     * Upload configuration file
     *
     * @return \Cake\Http\Response|null|void
     */
    public function upload()
    {
        $this->request->allowMethod(['post']);

        $fileType = $this->request->getData('file_type'); // 'gmail', 's3', 'evolution', etc.
        $file = $this->request->getData('config_file');

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error('No se pudo cargar el archivo. Por favor intenta nuevamente.');
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }

        // Validar tipo de archivo
        $allowedTypes = ['application/json', 'text/plain'];
        if (!in_array($file->getClientMediaType(), $allowedTypes)) {
            $this->Flash->error('El archivo debe ser un JSON válido.');
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }

        // Leer contenido y validar JSON
        $content = file_get_contents($file->getStream()->getMetadata('uri'));
        $jsonData = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->Flash->error('El archivo no es un JSON válido: ' . json_last_error_msg());
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }

        // Determinar destino según el tipo
        $destinations = [
            'gmail' => [
                'path' => CONFIG . 'google' . DS . 'client_secret.json',
                's3_key' => 'config/google/client_secret.json',
                'setting_key' => 'gmail_client_secret_path',
                'success_message' => 'Gmail client_secret.json subido correctamente.'
            ],
        ];

        if (!isset($destinations[$fileType])) {
            throw new BadRequestException('Tipo de archivo no soportado.');
        }

        $destination = $destinations[$fileType];
        $targetPath = $destination['path'];
        $s3Key = $destination['s3_key'];

        // Check if S3 is enabled
        $s3Service = new S3Service();
        $useS3 = $s3Service->isEnabled();

        // Guardar archivo
        try {
            if ($useS3) {
                // Upload to S3
                $tempPath = $file->getStream()->getMetadata('uri');

                if (!$s3Service->uploadFile($tempPath, $s3Key, $file->getClientMediaType())) {
                    throw new \Exception('Failed to upload to S3');
                }

                // Actualizar setting con la clave S3
                $this->_updateConfigPath($destination['setting_key'], $s3Key);

                Log::info('Config file uploaded to S3', [
                    'type' => $fileType,
                    's3_key' => $s3Key,
                    'user' => $this->Authentication->getIdentity()?->get('email')
                ]);
            } else {
                // Local storage (comportamiento original)
                // Crear directorio si no existe
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $file->moveTo($targetPath);

                // Cambiar permisos para www-data
                chmod($targetPath, 0664);
                if (function_exists('posix_getpwnam')) {
                    $wwwData = posix_getpwnam('www-data');
                    if ($wwwData) {
                        chown($targetPath, $wwwData['uid']);
                        chgrp($targetPath, $wwwData['gid']);
                    }
                }

                // Actualizar setting con la ruta local
                $this->_updateConfigPath($destination['setting_key'], $targetPath);

                Log::info('Config file uploaded locally', [
                    'type' => $fileType,
                    'path' => $targetPath,
                    'user' => $this->Authentication->getIdentity()?->get('email')
                ]);
            }

            $this->Flash->success($destination['success_message']);
        } catch (\Exception $e) {
            $this->Flash->error('Error al guardar el archivo: ' . $e->getMessage());
            Log::error('Config file upload failed', [
                'type' => $fileType,
                'error' => $e->getMessage(),
                'user' => $this->Authentication->getIdentity()?->get('email')
            ]);
        }

        return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
    }

    /**
     * Download/view current config file
     *
     * @param string $type File type (gmail)
     * @return \Cake\Http\Response
     */
    public function download(string $type)
    {
        $configs = [
            'gmail' => [
                'local_path' => CONFIG . 'google' . DS . 'client_secret.json',
                's3_key' => 'config/google/client_secret.json',
                'filename' => 'client_secret.json'
            ],
        ];

        if (!isset($configs[$type])) {
            throw new NotFoundException('Archivo no encontrado.');
        }

        $config = $configs[$type];
        $s3Service = new S3Service();
        $useS3 = $s3Service->isEnabled();

        if ($useS3) {
            // Download from S3 to temp file
            if (!$s3Service->fileExists($config['s3_key'])) {
                $this->Flash->error('El archivo de configuración aún no existe en S3.');
                return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
            }

            $tempPath = sys_get_temp_dir() . DS . $config['filename'];
            if (!$s3Service->downloadFile($config['s3_key'], $tempPath)) {
                $this->Flash->error('Error al descargar el archivo desde S3.');
                return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
            }

            $this->response = $this->response->withFile(
                $tempPath,
                ['download' => true, 'name' => $config['filename']]
            );

            // Schedule temp file deletion after response
            register_shutdown_function(function () use ($tempPath) {
                @unlink($tempPath);
            });
        } else {
            // Local file
            $filePath = $config['local_path'];
            if (!file_exists($filePath)) {
                $this->Flash->error('El archivo de configuración aún no existe.');
                return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
            }

            $this->response = $this->response->withFile(
                $filePath,
                ['download' => true, 'name' => basename($filePath)]
            );
        }

        return $this->response;
    }

    /**
     * Delete config file
     *
     * @param string $type File type (gmail)
     * @return \Cake\Http\Response
     */
    public function delete(string $type)
    {
        $this->request->allowMethod(['post', 'delete']);

        $configs = [
            'gmail' => [
                'local_path' => CONFIG . 'google' . DS . 'client_secret.json',
                's3_key' => 'config/google/client_secret.json',
            ],
        ];

        if (!isset($configs[$type])) {
            throw new NotFoundException('Archivo no encontrado.');
        }

        $config = $configs[$type];
        $s3Service = new S3Service();
        $useS3 = $s3Service->isEnabled();

        $deleted = false;

        if ($useS3) {
            // Delete from S3
            if ($s3Service->fileExists($config['s3_key'])) {
                $deleted = $s3Service->deleteFile($config['s3_key']);
                if ($deleted) {
                    Log::info('Config file deleted from S3', [
                        'type' => $type,
                        's3_key' => $config['s3_key'],
                        'user' => $this->Authentication->getIdentity()?->get('email')
                    ]);
                }
            } else {
                $this->Flash->warning('El archivo ya no existe en S3.');
                return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
            }
        } else {
            // Delete from local storage
            $filePath = $config['local_path'];
            if (file_exists($filePath)) {
                $deleted = unlink($filePath);
                if ($deleted) {
                    Log::info('Config file deleted locally', [
                        'type' => $type,
                        'path' => $filePath,
                        'user' => $this->Authentication->getIdentity()?->get('email')
                    ]);
                }
            } else {
                $this->Flash->warning('El archivo ya no existe.');
                return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
            }
        }

        if ($deleted) {
            $this->Flash->success('Archivo de configuración eliminado correctamente.');
        } else {
            $this->Flash->error('Error al eliminar el archivo de configuración.');
        }

        return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
    }

    /**
     * Update config path setting in database
     *
     * @param string $key Setting key
     * @param string $path File path
     * @return void
     */
    private function _updateConfigPath(string $key, string $path): void
    {
        $settingsTable = $this->fetchTable('SystemSettings');
        $setting = $settingsTable->find()->where(['setting_key' => $key])->first();

        if ($setting) {
            $setting->setting_value = $path;
        } else {
            $setting = $settingsTable->newEntity([
                'setting_key' => $key,
                'setting_value' => $path,
                'setting_type' => 'string',
                'description' => 'Path to ' . $key . ' configuration file'
            ]);
        }

        $settingsTable->saveOrFail($setting);
    }
}
