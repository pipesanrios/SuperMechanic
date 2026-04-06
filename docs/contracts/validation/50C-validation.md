PHASE: 50C VALIDATION

QA_CONTRACT_START
{
  "phase": "50C",
  "validation_contract_id": "50C-validation-v1",
  "automated_checks": [
    {
      "id": "php_lint_all",
      "type": "php_lint",
      "target": "all"
    },
    {
      "id": "notification_storage_installer_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-storage-installer.php"
    },
    {
      "id": "notification_storage_repository_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-storage-repository.php"
    },
    {
      "id": "notification_storage_service_exists",
      "type": "file_exists",
      "target": "includes/notifications/class-notification-storage-service.php"
    },
    {
      "id": "notifications_admin_controller_exists",
      "type": "file_exists",
      "target": "includes/admin/class-notifications-admin-controller.php"
    }
  ],
  "manual_checks": [
    {
      "id": "notifications_table_ready",
      "description": "tabla sm_notifications disponible con campos de persistencia 50C"
    },
    {
      "id": "notification_service_persists_before_email",
      "description": "Notification_Service guarda notificacion en DB antes de envio email"
    },
    {
      "id": "notifications_admin_page_visible",
      "description": "submenu Notifications visible y pagina carga correctamente"
    },
    {
      "id": "mark_as_read_works",
      "description": "accion Mark as read funciona con nonce y capability"
    }
  ]
}
QA_CONTRACT_END
