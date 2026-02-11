# PLAN DE RESOLUCIÓN - CODE SMELLS Y COMPLEJIDAD

Issues de complejidad (COM) y code smells (SMELL) documentados para fusionar al plan principal.

---

## COM-001: Método excesivamente largo - createMimeMessage()

**Severidad**: 🟡 Medio | **Esfuerzo**: 2-4 horas
**Archivo**: GmailService.php líneas 602-721 (120 líneas)

### Root Cause
Construcción manual de mensaje MIME con 120 líneas. Debería extraerse en métodos helper.

### Solución
```php
// ANTES: 120 líneas en un método
private function createMimeMessage(...): string
{
    // Construir From header (14 líneas)
    // Construir To header (16 líneas)
    // Construir CC header (15 líneas)
    // ... total 120 líneas
}

// DESPUÉS: Método principal corto
private function createMimeMessage($to, string $subject, string $htmlBody,
    array $attachments, string $boundary, array $options = []): string
{
    $message = '';
    $message .= $this->buildFromHeader($options);
    $message .= $this->buildToHeader($to);
    $message .= $this->buildCcHeader($options);
    $message .= $this->buildBccHeader($options);
    $message .= $this->buildReplyToHeader($options);
    $message .= $this->buildCustomHeaders($options);
    $message .= $this->buildSubjectHeader($subject);
    $message .= $this->buildMimeHeaders($boundary);
    $message .= $this->buildBodyPart($htmlBody, $boundary);
    $message .= $this->buildAttachmentParts($attachments, $boundary);
    $message .= "--{$boundary}--";

    return $message;
}

// Métodos helper privados (cada uno 5-15 líneas)
private function buildFromHeader(array $options): string
{
    $fromName = $options['from_name'] ?? 'Mesa de Ayuda';
    $fromEmail = $options['from_email'] ?? $this->config['system_email'];

    return "From: " . $this->encodeEmailHeader($fromName, $fromEmail) . "\r\n";
}

private function buildToHeader($to): string
{
    if (is_string($to)) {
        return "To: {$to}\r\n";
    }

    $toEmails = [];
    foreach ($to as $recipient) {
        $toEmails[] = $this->encodeEmailHeader(
            $recipient['name'] ?? '',
            $recipient['email']
        );
    }

    return "To: " . implode(', ', $toEmails) . "\r\n";
}

// ... más métodos helper
```

### Beneficios
- Método principal: 20 líneas (vs 120)
- Cada helper: 5-15 líneas
- Reutilizable y testeable
- Más fácil de mantener

---

## COM-003: createFromEmail() excesivamente largo

**Severidad**: 🟡 Medio | **Esfuerzo**: 3-4 horas
**Archivo**: TicketService.php líneas 150-280 (130 líneas)

### Root Cause
Método hace demasiado: parsear email, validar, crear ticket, descargar attachments, notificar.

### Solución
Extraer submétodos:

```php
public function createFromEmail(array $emailData): ?Ticket
{
    // Validar email data
    if (!$this->validateEmailData($emailData)) {
        return null;
    }

    // Crear ticket entity
    $ticket = $this->createTicketEntity($emailData);

    // Procesar attachments
    $this->processEmailAttachments($emailData, $ticket->id);

    // Marcar email como leído
    $this->gmailService->markAsRead($emailData['message_id']);

    // Enviar notificaciones
    $this->dispatchCreationNotifications('ticket', $ticket,
        $this->emailService, $this->whatsappService);

    return $ticket;
}

private function validateEmailData(array $emailData): bool
{
    return !empty($emailData['from'])
        && !empty($emailData['subject'])
        && !$this->isAutoReply($emailData);
}

private function createTicketEntity(array $emailData): Ticket
{
    $ticketsTable = $this->fetchTable('Tickets');

    $ticket = $ticketsTable->newEntity([
        'ticket_number' => $ticketsTable->generateTicketNumber(),
        'subject' => $emailData['subject'],
        'description' => $this->sanitizeEmailBody($emailData['body_html']),
        'source_email' => $emailData['from'][0]['email'] ?? '',
        'channel' => 'email',
        'status' => 'nuevo',
        'priority' => 'media',
    ]);

    return $ticketsTable->saveOrFail($ticket);
}

private function processEmailAttachments(array $emailData, int $ticketId): void
{
    foreach ($emailData['attachments'] as $attachment) {
        $this->gmailService->downloadAttachment(
            $emailData['message_id'],
            $attachment['attachment_id'],
            $attachment['filename'],
            $attachment['mime_type'],
            'ticket',
            $ticketId
        );
    }
}
```

### Beneficios
- Método principal: ~25 líneas
- Cada helper: 10-30 líneas
- Single responsibility per método
- Más fácil testear individualmente

---

## COM-004: Métodos largos en EmailService con duplicación

**Severidad**: 🟡 Medio | **Esfuerzo**: Resuelto por BLK-002

### Solución
Ya resuelta en BLK-002/ARCH-005 con la refactorización del EmailService.

---

## COM-005: Complejidad moderada en getSlaStatus()

**Severidad**: 🔵 Bajo | **Esfuerzo**: 2-3 horas
**Archivo**: SlaManagementService.php

### Root Cause
Método con múltiples branches calculando estado SLA.

### Solución
Extraer lógica a Strategy Pattern:

```php
interface SlaCalculatorInterface
{
    public function calculate(EntityInterface $entity): array;
}

class TicketSlaCalculator implements SlaCalculatorInterface
{
    public function calculate(EntityInterface $entity): array
    {
        $firstResponseDeadline = $this->calculateFirstResponseDeadline($entity);
        $resolutionDeadline = $this->calculateResolutionDeadline($entity);

        return [
            'first_response' => $this->getStatus($entity->first_response_at, $firstResponseDeadline),
            'resolution' => $this->getStatus($entity->resolved_at, $resolutionDeadline),
        ];
    }

    private function getStatus(?DateTimeInterface $completedAt, DateTimeInterface $deadline): string
    {
        if ($completedAt === null) {
            return $this->isOverdue($deadline) ? 'breached' : 'on_track';
        }

        return $completedAt <= $deadline ? 'met' : 'breached';
    }
}

// Uso
class SlaManagementService
{
    private array $calculators = [];

    public function __construct()
    {
        $this->calculators['ticket'] = new TicketSlaCalculator();
        $this->calculators['pqrs'] = new PqrsSlaCalculator();
        $this->calculators['compra'] = new CompraSlaCalculator();
    }

    public function getSlaStatus(string $entityType, EntityInterface $entity): array
    {
        $calculator = $this->calculators[$entityType];
        return $calculator->calculate($entity);
    }
}
```

### Beneficios
- Strategy Pattern aplicado
- Cada calculator: <50 líneas
- Fácil agregar nuevos entity types
- Testeable individualmente

---

## COM-006: Complejidad moderada en métodos de agregación

**Severidad**: 🔵 Bajo | **Esfuerzo**: 1-2 horas
**Archivo**: StatisticsService.php

### Root Cause
Métodos de agregación con múltiples CASE WHEN. Funciona bien pero podría simplificarse.

### Solución Actual es Aceptable
Los métodos actuales usan SQL CASE expressions que son eficientes:

```php
$query->select([
    'total' => $query->func()->count('*'),
    'nuevos' => $query->func()->sum(
        $query->newExpr()->addCase(
            [['status' => 'nuevo']],
            [1, 0]
        )
    ),
    'en_proceso' => $query->func()->sum(
        $query->newExpr()->addCase(
            [['status' => 'en_proceso']],
            [1, 0]
        )
    ),
    // ...
]);
```

**Recomendación**: Mantener como está. Es performante y legible.

**Alternativa si se vuelve complejo**:
- Mover queries complejas a custom finders en Tables
- Usar Query Builder más expresivo

---

## SMELL-001: Magic strings para headers

**Severidad**: 🔵 Bajo | **Esfuerzo**: <2 horas
**Archivo**: GmailService.php

### Root Cause
Headers de email hardcodeados como strings. Riesgo de typos.

### Solución
Definir constantes:

```php
class GmailService
{
    // Standard email headers
    private const HEADER_FROM = 'From';
    private const HEADER_TO = 'To';
    private const HEADER_CC = 'Cc';
    private const HEADER_BCC = 'Bcc';
    private const HEADER_SUBJECT = 'Subject';
    private const HEADER_DATE = 'Date';
    private const HEADER_REPLY_TO = 'Reply-To';

    // Auto-reply detection
    private const HEADER_AUTO_SUBMITTED = 'Auto-Submitted';
    private const HEADER_X_AUTOREPLY = 'X-Autoreply';
    private const HEADER_PRECEDENCE = 'Precedence';

    // Custom
    private const HEADER_MESA_AYUDA = 'X-Mesa-Ayuda-Notification';

    // Uso
    private function parseHeaders($headers): array
    {
        return [
            'from' => $this->getHeader($headers, self::HEADER_FROM),
            'to' => $this->getHeader($headers, self::HEADER_TO),
            'cc' => $this->getHeader($headers, self::HEADER_CC),
            'subject' => $this->getHeader($headers, self::HEADER_SUBJECT),
        ];
    }
}
```

### Beneficios
- Autocomplete en IDE
- Catch typos en compile time
- Documentación implícita
- Fácil refactoring

---

## SMELL-003: Magic strings para status, channel, email

**Severidad**: 🔵 Bajo | **Esfuerzo**: 2-3 horas
**Archivo**: TicketService.php

### Root Cause
Status, channels, prioridades hardcodeados como strings.

### Solución
Crear enums (PHP 8.1+):

```php
// src/Enum/TicketStatus.php
enum TicketStatus: string
{
    case NUEVO = 'nuevo';
    case EN_PROCESO = 'en_proceso';
    case EN_REVISION = 'en_revision';
    case RESUELTO = 'resuelto';
    case CONVERTIDO = 'convertido';

    public function isOpen(): bool
    {
        return !in_array($this, [self::RESUELTO, self::CONVERTIDO]);
    }
}

enum Channel: string
{
    case EMAIL = 'email';
    case WEB = 'web';
    case WHATSAPP = 'whatsapp';
}

enum Priority: string
{
    case BAJA = 'baja';
    case MEDIA = 'media';
    case ALTA = 'alta';
    case URGENTE = 'urgente';

    public function getColor(): string
    {
        return match($this) {
            self::BAJA => 'green',
            self::MEDIA => 'yellow',
            self::ALTA => 'orange',
            self::URGENTE => 'red',
        };
    }
}

// Uso
class TicketService
{
    public function createFromEmail(array $emailData): ?Ticket
    {
        $ticket = $ticketsTable->newEntity([
            'status' => TicketStatus::NUEVO->value,
            'channel' => Channel::EMAIL->value,
            'priority' => Priority::MEDIA->value,
        ]);
    }
}
```

### Beneficios
- Type safety
- Autocomplete
- No typos posibles
- Métodos helper en enums

---

## SMELL-004: Método no usado - getSystemEmail()

**Severidad**: 🔵 Bajo | **Esfuerzo**: 5 minutos

### Root Cause
Método `getSystemEmail()` en TicketService nunca es llamado.

### Solución
Eliminar método:

```php
// ❌ ELIMINAR - nunca usado
public function getSystemEmail(): string
{
    return $this->systemConfig['system_email'] ?? 'noreply@mesadeayuda.com';
}
```

Verificar con:
```bash
grep -r "getSystemEmail" src/
# Si solo aparece la definición → eliminar
```

---

## SMELL-005: Magic strings de template keys

**Severidad**: 🔵 Bajo | **Esfuerzo**: 1-2 horas
**Archivo**: EmailService.php

### Root Cause
Template keys hardcodeados ('nuevo_ticket', 'ticket_comentario', etc.)

### Solución
Constantes centralizadas:

```php
class EmailTemplates
{
    // Tickets
    const TICKET_CREATED = 'nuevo_ticket';
    const TICKET_COMMENT = 'ticket_comentario';
    const TICKET_STATUS_CHANGED = 'ticket_estado_cambiado';
    const TICKET_ASSIGNED = 'ticket_asignado';

    // PQRS
    const PQRS_CREATED = 'nuevo_pqrs';
    const PQRS_COMMENT = 'pqrs_comentario';

    // Compras
    const COMPRA_CREATED = 'nueva_compra';
    const COMPRA_COMMENT = 'compra_comentario';

    public static function getAll(): array
    {
        return [
            self::TICKET_CREATED => 'Nuevo Ticket Creado',
            self::TICKET_COMMENT => 'Nuevo Comentario en Ticket',
            // ...
        ];
    }
}

// Uso
$emailService->sendEmail(EmailTemplates::TICKET_CREATED, $ticket);
```

---

## SMELL-006: Duplicación de lógica email_to/email_cc parsing

**Severidad**: 🔵 Bajo | **Esfuerzo**: 1 hora
**Archivo**: Múltiples servicios

### Root Cause
Parsing de campos email_to/email_cc duplicado en múltiples lugares.

### Solución
Crear utility class:

```php
// src/Utility/EmailHelper.php
class EmailHelper
{
    public static function parseEmailList(?string $emailList): array
    {
        if (empty($emailList)) {
            return [];
        }

        $decoded = json_decode($emailList, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Fallback: comma-separated
        return array_map('trim', explode(',', $emailList));
    }

    public static function formatEmailList(array $emails): string
    {
        return json_encode($emails);
    }
}

// Uso
$recipients = EmailHelper::parseEmailList($comment->email_to);
```

---

## SMELL-007: Debug logging en producción

**Severidad**: 🔵 Bajo | **Esfuerzo**: 1 hora
**Archivo**: ResponseService.php línea 150

### Root Cause
Logging de debug que no debería estar en producción.

**Código actual**:
```php
Log::debug('Sending email response', [  // ❌ Debug en producción
    'ticket_id' => $ticket->id,
    'to' => $recipients,
]);
```

### Solución
Usar nivel correcto según environment:

```php
// Helper method
private function logEmailSending(int $ticketId, array $recipients): void
{
    $level = env('APP_DEBUG') ? 'debug' : 'info';

    Log::write($level, 'Sending email response', [
        'ticket_id' => $ticketId,
        'recipients_count' => count($recipients),
    ]);
}

// O usar conditional
if (env('APP_DEBUG')) {
    Log::debug('Detailed debug info', $data);
}

Log::info('Email sent', ['ticket_id' => $ticket->id]);
```

---

## RESUMEN

Total: 16 issues documentados

### Complejidad (6)
- COM-001: createMimeMessage largo (120 líneas)
- COM-003: createFromEmail largo (130 líneas)
- COM-004: EmailService duplicación (resuelto por BLK-002)
- COM-005: getSlaStatus complejidad
- COM-006: Métodos agregación (mantener)

### Code Smells (10)
- SMELL-001: Magic strings headers
- SMELL-003: Magic strings status/channel
- SMELL-004: Método no usado
- SMELL-005: Magic strings templates
- SMELL-006: Parsing email duplicado
- SMELL-007: Debug logging en prod

**Total issues documentados hasta ahora: 38 de 77 (49.4%)**

Faltan:
- TRAIT-001, TRAIT-002, TRAIT-003 (1 en progreso)
- MODEL-002 (en progreso)
- CTRL-004, CTRL-005, CTRL-006, CTRL-007 (1 en progreso)
- ARCH-006, ARCH-007, ARCH-008, ARCH-010, ARCH-011, ARCH-017
