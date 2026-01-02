# Email Threading Design

**Date:** 2026-01-01
**Author:** Claude Sonnet 4.5
**Status:** Approved

## Overview

Implement email threading functionality to automatically convert email replies to existing tickets into comments, rather than creating duplicate tickets.

## Problem Statement

Currently, the Gmail import system only prevents exact duplicate messages (same `gmail_message_id`). When users reply to:
1. The original ticket email
2. System notifications (ticket created, status changed, new comment)

...the system creates a **new ticket** instead of adding a comment to the existing conversation.

## Requirements

Based on collaborative design session:

1. **Ignore responses to system notifications** - Don't create comments when users reply to automated notifications
2. **Use custom headers** - Mark outgoing notifications with `X-Mesa-Ayuda-Notification: true`
3. **Validate recipients** - Only allow comments from people in the original To/CC of the ticket
4. **Maintain closed status** - Add comments to closed tickets without reopening them
5. **Process attachments** - Download attachments using existing file type validation
6. **Auto-create users** - Create users automatically if they're in To/CC but don't exist in the system
7. **Filter auto-replies** - Detect and ignore out-of-office and auto-reply messages

## Design Decisions

### Approach

**Selected: Extend TicketService with dedicated method**

Rationale:
- Separates business logic (service) from orchestration (command)
- Reusable from other entry points (webhooks, API)
- Consistent with existing architecture
- Easier to test than monolithic command

### Threading Logic

When an email arrives:

```
1. Parse email with GmailService
2. Check filters (in order):
   a. Is auto-reply? → Ignore, mark read
   b. Is response to notification? → Ignore, mark read
   c. Exact duplicate (message_id)? → Skip
   d. Belongs to existing thread? → Create comment
   e. New conversation → Create ticket
```

### Recipient Validation

Allow comments from:
- Anyone in `email_to` array of original ticket
- Anyone in `email_cc` array of original ticket
- The original requester (always permitted)

Case-insensitive email matching.

## Architecture

### Component Modifications

#### 1. GmailService

**New Methods:**

```php
private function isAutoReply(array $headers): bool
```
- Detects auto-replies using standard headers
- Checks: `Auto-Submitted`, `X-Autoreply`, `X-Autorespond`, `Precedence`

```php
private function isSystemNotification(array $headers): bool
```
- Detects system notification responses
- Checks: `X-Mesa-Ayuda-Notification: true`

**Modified Methods:**

```php
public function parseMessage(string $messageId): array
```
- Add `is_auto_reply` to returned data
- Add `is_system_notification` to returned data

```php
private function createMimeMessage(..., array $options = []): string
```
- Support `$options['headers']` for custom headers

#### 2. EmailService

**Modified Methods:**

```php
private function sendEmail(...)
```
- Add `X-Mesa-Ayuda-Notification: true` header to all outgoing notifications
- Pass headers via `$options['headers']` to GmailService

#### 3. TicketService

**New Methods:**

```php
public function createCommentFromEmail(
    \App\Model\Entity\Ticket $ticket,
    array $emailData
): ?\App\Model\Entity\TicketComment
```
- Validates sender is in To/CC
- Creates or finds user
- Creates comment (without sending notifications)
- Processes attachments using existing validation

```php
private function isEmailInTicketRecipients(
    \App\Model\Entity\Ticket $ticket,
    string $email
): bool
```
- Checks `email_to` array
- Checks `email_cc` array
- Checks original requester email
- Case-insensitive matching

#### 4. ImportGmailCommand

**Modified Logic:**

Replace main processing loop to:
1. Filter auto-replies (mark read, skip)
2. Filter notification responses (mark read, skip)
3. Check for duplicate message_id (skip)
4. Check for existing thread_id:
   - If exists → `createCommentFromEmail()`
   - If not → `createFromEmail()` (existing)
5. Mark as read after successful processing

## Error Handling

### 1. Missing Thread ID
- If `gmail_thread_id` is empty, treat as new conversation
- Log warning and create new ticket

### 2. Ticket Without Recipients
- If ticket has no `email_to`/`email_cc` (created from web or migrated data)
- Only allow original requester to comment
- Reject other senders

### 3. Attachment Download Failures
- Log error but continue processing comment
- Don't block comment creation due to attachment errors

### 4. Oversized Email Content
- Truncate body at 65,000 characters
- Log warning with original size
- Prevent database overflow

## Data Flow

```
┌─────────────────┐
│  Gmail API      │
│  (new email)    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  ImportGmailCommand                     │
│  ┌───────────────────────────────────┐  │
│  │ 1. gmailService.parseMessage()    │  │
│  │    → emailData with flags         │  │
│  └───────────────────────────────────┘  │
│  ┌───────────────────────────────────┐  │
│  │ 2. Filter checks:                 │  │
│  │    - is_auto_reply? → Skip        │  │
│  │    - is_system_notification? → Skip│ │
│  │    - duplicate message_id? → Skip │  │
│  └───────────────────────────────────┘  │
│  ┌───────────────────────────────────┐  │
│  │ 3. Find by thread_id              │  │
│  │    TicketsTable.find(thread_id)   │  │
│  └───────────────────────────────────┘  │
│         │                                │
│         ├─────────────┬─────────────────┤
│         ▼             ▼                  │
│    Thread exists   No thread             │
│         │             │                  │
│         ▼             ▼                  │
│  ┌─────────────┐ ┌──────────────────┐   │
│  │ Create      │ │ Create           │   │
│  │ Comment     │ │ Ticket           │   │
│  └─────────────┘ └──────────────────┘   │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  TicketService                          │
│  ┌───────────────────────────────────┐  │
│  │ createCommentFromEmail():         │  │
│  │ 1. isEmailInTicketRecipients()?   │  │
│  │    → No: return null              │  │
│  │ 2. findOrCreateUser()             │  │
│  │ 3. Create TicketComment           │  │
│  │ 4. processEmailAttachments()      │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────┐
│  Comment saved  │
│  No notification│
└─────────────────┘
```

## Testing Strategy

### Unit Tests

1. **GmailServiceTest**
   - `testIsAutoReply()` - Various auto-reply headers
   - `testIsSystemNotification()` - Custom header detection
   - `testParseMessageIncludesFlags()` - New fields in response

2. **TicketServiceTest**
   - `testCreateCommentFromEmailValidRecipient()` - Happy path
   - `testCreateCommentFromEmailUnauthorized()` - Rejects non-recipient
   - `testIsEmailInTicketRecipientsToField()` - Checks To array
   - `testIsEmailInTicketRecipientsCcField()` - Checks CC array
   - `testIsEmailInTicketRecipientsRequester()` - Always allows requester
   - `testIsEmailInTicketRecipientsCaseInsensitive()` - Email matching
   - `testCreateCommentWithAttachments()` - Attachment processing

3. **EmailServiceTest**
   - `testSendEmailIncludesNotificationHeader()` - Header added to outgoing

### Integration Tests

1. **ImportGmailCommandTest**
   - `testImportCreatesCommentForExistingThread()` - Threading works
   - `testImportIgnoresAutoReplies()` - Auto-reply filter
   - `testImportIgnoresNotificationResponses()` - Notification filter
   - `testImportCreatesNewTicketForNewThread()` - Normal flow unchanged

## Security Considerations

1. **Recipient Validation** - Prevents unauthorized users from injecting comments
2. **File Type Validation** - Reuses existing attachment security rules
3. **Content Size Limits** - Prevents database overflow attacks
4. **Auto-reply Filtering** - Prevents spam from automated responses

## Migration Notes

- **No database migrations required** - Uses existing `gmail_thread_id` field
- **Backward compatible** - Existing tickets without thread_id continue to work
- **Existing behavior preserved** - Tickets created from web/API unaffected

## Future Enhancements (Out of Scope)

- Detect conversation intent (new issue vs follow-up)
- Support for email templates with better threading context
- UI indicator showing comments added via email vs manual
- Configurable rules per organization (multi-tenancy)

## Success Criteria

1. ✅ Users can reply to original ticket email → adds comment
2. ✅ Replies to notifications are ignored → no new tickets
3. ✅ Auto-replies are filtered → no spam comments
4. ✅ Only authorized recipients can comment → security maintained
5. ✅ Attachments processed correctly → file handling works
6. ✅ New users auto-created when valid → seamless experience
7. ✅ Closed tickets receive comments without reopening → status preserved

## Implementation Plan

See: `docs/plans/2026-01-01-email-threading-implementation.md` (to be created)
