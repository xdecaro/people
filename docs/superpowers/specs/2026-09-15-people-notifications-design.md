# People → Notifications design

## Goal

Integrate People with the shared `com_xdecaronotifications` center so relevant People events appear in the Joomla administrator bell for one configured Joomla user.

## Platform

- Joomla target: 6.1.3 only.
- PHP: 8.3+.
- People remains owner of People business rules.
- Notifications remains owner of notification persistence and delivery.
- People must use the Notifications public component services and must never write Notifications tables directly.

## Recipient

People exposes a component option `notification_recipient_user_id` using Joomla's user picker. The selected Joomla user receives all People notifications. No personal name or numeric user id is hard-coded in source. If no recipient is configured, People saves and state changes continue normally without emitting notifications.

The intended production selection is the Joomla user linked to Luca De Caro, as confirmed in the People person record.

## Events

People emits in-app notifications for:

1. New person created.
2. Important person data changed.
3. Joomla publication state changed: published, suspended/unpublished, trashed, restored.
4. A possible duplicate is detected during save.

Routine saves that do not change meaningful data produce no update notification.

## Privacy

Bell text contains only the person's display name and a generic event description. It does not include values from sensitive fields such as disability, accessibility needs, date/place of birth, tax identifier, address, notes, or other protected profile values.

## Delivery

People boots `com_xdecaronotifications`, calls `NotificationService::create()`, then `DeliveryService::queueForNotification($id, ['in_app'])`. A missing/unavailable Notifications component is logged as a warning and never blocks a People save or state operation.

Each notification uses:

- `source_component = com_xdecaropeople`
- `source_entity = person`
- `source_id = <People id>`
- `recipient_type = user`
- `recipient_id = <configured Joomla user id>`
- `category = people`
- action URL pointing to the relevant People person, except duplicate warnings which point to the Duplicates view.

## Version

Release as People 1.2.16. No database schema migration is required. The release metadata and CI are aligned to Joomla 6.1.3 only.