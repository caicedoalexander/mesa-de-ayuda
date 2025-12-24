<<<<<<< HEAD
# Sistema Integrado de Gestión Corporativa (Soporte, Compras, PQRS)
=======
# Mesa de Ayuda - Sistema de tickets (CakePHP)
>>>>>>> fb65f96dadc60b8fe030f70cdb0d80569059b7a1

> **Developed in collaboration with Claude Code** 🤖✨

Una plataforma empresarial robusta y moderna construida sobre **CakePHP 5.x**, diseñada para centralizar y automatizar los flujos críticos de la organización. Este sistema no es solo un gestor de tickets; es un ecosistema conectado que integra IA, mensajería instantánea y automatización de flujos de trabajo.

![CakePHP](https://img.shields.io/badge/CakePHP-5.x-red?style=flat-square&logo=cakephp)
![MySQL](https://img.shields.io/badge/Database-MySQL-blue?style=flat-square&logo=mysql)
![n8n](https://img.shields.io/badge/Automation-n8n-EF2C5A?style=flat-square&logo=n8n)
![WhatsApp](https://img.shields.io/badge/Compms-WhatsApp-25D366?style=flat-square&logo=whatsapp)

## 🚀 Módulos Principales

El sistema se divide en tres pilares fundamentales para la operación eficiente:

### 1. 🛠️ Soporte Interno (Helpdesk)
El corazón de la asistencia técnica para colaboradores.
- **Gestión de Tickets**: Ciclo de vida completo (Nuevo -> En Progreso -> Resuelto).
- **Conversión Email-to-Ticket**: Integración con Gmail para convertir correos entrantes en tickets automáticamente.
- **Asignación Inteligente**: Clasificación y distribución basada en carga de trabajo y especialidad.
- **Historial Completo**: Auditoría detallada de todas las interacciones y cambios.

### 2. 🛒 Gestión de Compras
Control total sobre el aprovisionamiento y requisiciones.
- **Flujos de Aprobación**: Procesos estructurados para solicitudes de compra.
- **Trazabilidad**: Seguimiento desde la solicitud hasta la orden de compra.
- **Notificaciones**: Alertas a los responsables en cada etapa del proceso.

### 3. 📢 PQRS (Externo)
Canal de escucha activa para clientes y usuarios externos.
- **Peticiones, Quejas, Reclamos y Sugerencias**.
- **Seguimiento Público**: Portal para que los usuarios consulten el estado de sus solicitudes.
- **Tiempos de Respuesta**: Monitoreo de SLAs para garantizar atención oportuna.

---

## ⚡ Integraciones de Poder

Este proyecto va más allá de un CRUD tradicional, integrando herramientas de vanguardia:

### 🤖 Inteligencia Artificial & Automatización (n8n)
El sistema "piensa" y actúa:
- **Clasificación Automática**: Análisis de contenido de tickets mediante IA para sugerir etiquetas y prioridades.
- **Webhooks Bidireccionales**: Comunicación en tiempo real con workflows de **n8n** para disparar automatizaciones complejas fuera del monolito.

### 💬 WhatsApp Business (Evolution API)
Notificaciones donde los usuarios realmente las ven:
- **Alertas en Tiempo Real**: Notificaciones instantáneas a agentes y usuarios sobre actualizaciones críticas.
- **Mensajería Transaccional**: Confirmaciones de recepción y cambios de estado directo al celular.

### 📧 Integración Profunda con Gmail
- Lectura y procesamiento de adjuntos.
- Mapeo de hilos de conversación para mantener el contexto.

---

## 🛠️ Tecnologías y Estructura

- **Backend**: CakePHP 5.x (PHP 8.1+)
- **Frontend**: Bootstrap 5, Vanilla JS (Enfoque limpio y mantenible)
- **Base de Datos**: MySQL / MariaDB
- **Infraestructura**: Docker ready

### Estructura del Código
- `src/Service/`: Lógica de negocio encapsulada (e.g., `N8nService`, `WhatsappService`, `TicketService`).
- `config/Migrations/`: Control de versiones de base de datos robusto.
- `templates/`: Vistas renderizadas en servidor optimizadas.

## 🏁 Instalación Rápida

1. **Instalar dependencias**
   ```bash
   composer install
   ```

2. **Configuración**
   ```bash
   cp config/app_local.example.php config/app_local.php
   # Configurar DB y credenciales de API (Gmail, WhatsApp, n8n)
   ```

3. **Base de Datos**
   ```bash
   bin/cake migrations migrate
   bin/cake migrations seed
   ```

4. **Desplegar Servidor**
   ```bash
   bin/cake server
   ```

---

_Construido con estándares de código modernos, tipos estrictos y una arquitectura modular para escalar._
