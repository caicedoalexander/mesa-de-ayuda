<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
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
                'setting_key' => 'gmail_client_secret_path',
                'success_message' => 'Gmail client_secret.json subido correctamente.'
            ],
        ];

        if (!isset($destinations[$fileType])) {
            throw new BadRequestException('Tipo de archivo no soportado.');
        }

        $destination = $destinations[$fileType];
        $targetPath = $destination['path'];

        // Crear directorio si no existe
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Guardar archivo
        try {
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

            // Actualizar setting en base de datos
            $this->_updateConfigPath($destination['setting_key'], $targetPath);

            $this->Flash->success($destination['success_message']);
            Log::info('Config file uploaded', [
                'type' => $fileType,
                'path' => $targetPath,
                'user' => $this->Authentication->getIdentity()?->get('email')
            ]);
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
        $paths = [
            'gmail' => CONFIG . 'google' . DS . 'client_secret.json',
        ];

        if (!isset($paths[$type])) {
            throw new NotFoundException('Archivo no encontrado.');
        }

        $filePath = $paths[$type];

        if (!file_exists($filePath)) {
            $this->Flash->error('El archivo de configuración aún no existe.');
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }

        $this->response = $this->response->withFile(
            $filePath,
            ['download' => true, 'name' => basename($filePath)]
        );

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

        $paths = [
            'gmail' => CONFIG . 'google' . DS . 'client_secret.json',
        ];

        if (!isset($paths[$type])) {
            throw new NotFoundException('Archivo no encontrado.');
        }

        $filePath = $paths[$type];

        if (file_exists($filePath)) {
            unlink($filePath);
            $this->Flash->success('Archivo de configuración eliminado correctamente.');
            Log::info('Config file deleted', [
                'type' => $type,
                'user' => $this->Authentication->getIdentity()?->get('email')
            ]);
        } else {
            $this->Flash->warning('El archivo ya no existe.');
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
