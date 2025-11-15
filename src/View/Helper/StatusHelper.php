<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

class StatusHelper extends Helper
{
    /**
     * Renderiza un badge de estado con color según ID.
     * Mapa solicitado:
     * 1 Nuevo -> naranja (warning)
     * 2 Pendiente -> azul (primary)
     * 3 En progreso -> rojo (danger)
     * 4 Resuelto -> verde (success)
     * Otros -> secondary
     *
     * @param int|null $statusId
     * @param string $label
     * @param array $options ['url' => array|string|null] Enlace opcional
     */
    public function badge(string $label, array $options = []): string
    {
        $class = match ($label) {
            'nuevo' => 'bg-warning',
            'pendiente' => 'bg-primary',
            'abierto' => 'bg-danger',
            'resuelto' => 'bg-success',
            default => 'bg-secondary',
        };

        $span = sprintf('<span style="font-size: 12px;" class="text-white text-uppercase lh-1 px-2 py-1 %s">%s</span>', h($class), h($label));

        $url = $options['url'] ?? null;
        if ($url) {
            /** @var \Cake\View\Helper\HtmlHelper $Html */
            $Html = $this->getView()->Html;
            return $Html->link($span, $url, ['escape' => false, 'class' => 'text-decoration-none']);
        }

        return $span;
    }
}
